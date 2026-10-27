<?php
/**
 * One unit of background work.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Jobs;

use QuantumCloud\WPBot\CRM\Domain\AudienceResolver;
use QuantumCloud\WPBot\CRM\Domain\CampaignRepository;
use QuantumCloud\WPBot\CRM\Domain\CampaignService;
use QuantumCloud\WPBot\CRM\Domain\ContactRepository;
use QuantumCloud\WPBot\CRM\Domain\EventRepository;
use QuantumCloud\WPBot\CRM\Domain\MessageRepository;
use QuantumCloud\WPBot\CRM\Mail\Mailer;
use QuantumCloud\WPBot\CRM\Support\Dates;
use QuantumCloud\WPBot\CRM\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * activate due campaigns → enqueue recipients → send one batch → finish campaigns →
 * advance automation runs → daily housekeeping. Must run under the Scheduler lock.
 */
final class Tick {

	const HOUSEKEEPING_OPTION = 'wpbot_crm_housekeeping_at';
	const EVENT_RETENTION     = 365;

	/**
	 * Campaigns.
	 *
	 * @var CampaignRepository
	 */
	private $campaigns;

	/**
	 * Messages.
	 *
	 * @var MessageRepository
	 */
	private $messages;

	/**
	 * Contacts.
	 *
	 * @var ContactRepository
	 */
	private $contacts;

	/**
	 * Run stats.
	 *
	 * @var array
	 */
	private $stats = array(
		'processed'   => 0,
		'sent'        => 0,
		'failed'      => 0,
		'skipped'     => 0,
		'activated'   => 0,
		'finished'    => 0,
		'automations' => 0,
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->campaigns = new CampaignRepository();
		$this->messages  = new MessageRepository();
		$this->contacts  = new ContactRepository();
	}

	/**
	 * Run one tick.
	 *
	 * @param int $max_seconds Wall-time budget for the send loop.
	 * @return array Stats.
	 */
	public function run( $max_seconds = 20 ) {
		$deadline = microtime( true ) + max( 1, (int) $max_seconds );

		$this->activate_scheduled();
		$sending = array();
		foreach ( $this->campaigns->by_status( 'sending' ) as $campaign ) {
			$campaign = $this->prepare( $campaign );
			if ( $campaign ) {
				$sending[] = $campaign;
			}
		}
		$this->send_batch( $sending, $deadline );
		$this->finish_campaigns();

		$engine = 'QuantumCloud\\WPBot\\CRM\\Domain\\AutomationEngine';
		if ( class_exists( $engine ) && method_exists( $engine, 'run_due' ) ) {
			$this->stats['automations'] = (int) call_user_func( array( $engine, 'run_due' ), 100 );
		}

		$this->housekeeping();
		return $this->stats;
	}

	/**
	 * Scheduled campaigns whose time has come → sending.
	 */
	private function activate_scheduled() {
		foreach ( $this->campaigns->due_scheduled( Dates::now() ) as $id ) {
			$this->campaigns->set_status( $id, 'sending', array( 'started_at' => Dates::now() ) );
			++$this->stats['activated'];
		}
	}

	/**
	 * Enqueue recipients once per campaign. Campaigns whose audience is empty are marked failed.
	 *
	 * @param array $campaign Campaign row.
	 * @return array|null Campaign still sending, or null.
	 */
	private function prepare( array $campaign ) {
		if ( (int) $campaign['recipients'] > 0 ) {
			return $campaign;
		}
		$settings = is_array( $campaign['settings'] ) && ! empty( $campaign['settings'] ) ? $campaign['settings'] : (array) $campaign['audience'];
		( new AudienceResolver() )->enqueue_for_campaign( (int) $campaign['id'], $settings );
		$counts = $this->messages->counts_for_campaign( (int) $campaign['id'] );
		if ( $counts['total'] <= 0 ) {
			$this->campaigns->set_status( (int) $campaign['id'], 'failed', array( 'finished_at' => Dates::now() ) );
			return null;
		}
		$this->campaigns->update( (int) $campaign['id'], array( 'recipients' => $counts['total'] ) );
		$campaign['recipients'] = $counts['total'];
		return $campaign;
	}

	/**
	 * Send up to batch_size queued campaign messages.
	 *
	 * @param array[] $campaigns Sending campaigns.
	 * @param float   $deadline  microtime() deadline.
	 */
	private function send_batch( array $campaigns, $deadline ) {
		$budget = max( 1, Settings::int( 'batch_size' ) );
		$pause  = (int) floor( 1000000 / max( 1, Settings::int( 'emails_per_second' ) ) );

		foreach ( $campaigns as $campaign ) {
			if ( $budget <= 0 || microtime( true ) >= $deadline ) {
				break;
			}
			$allowed = min( $budget, $this->throttle_allowance( $campaign ) );
			if ( $allowed <= 0 ) {
				continue;
			}
			$context = CampaignService::send_context( $campaign );
			foreach ( $this->messages->claim( $allowed, (int) $campaign['id'] ) as $message ) {
				if ( microtime( true ) >= $deadline ) {
					break 2;
				}
				$contact = $this->contacts->find( (int) $message['contact_id'] );
				if ( ! $contact || ! in_array( $contact['status'], AudienceResolver::SENDABLE, true ) ) {
					$this->messages->mark_skipped( (int) $message['id'], 'Contact is no longer subscribed.' );
					++$this->stats['skipped'];
					continue;
				}
				$context['message_id'] = (int) $message['id'];
				$result                = Mailer::send_to_contact( $contact, (string) $campaign['subject'], (string) $campaign['content_html'], $context );
				++$this->stats['processed'];
				--$budget;
				$this->throttle_count( $campaign );
				if ( is_wp_error( $result ) ) {
					++$this->stats['failed'];
				} else {
					++$this->stats['sent'];
				}
				if ( $pause > 0 ) {
					usleep( $pause );
				}
			}
		}
	}

	/**
	 * Mark sending campaigns with nothing left in the queue as sent.
	 */
	private function finish_campaigns() {
		foreach ( $this->campaigns->by_status( 'sending' ) as $campaign ) {
			if ( (int) $campaign['recipients'] > 0 && 0 === $this->messages->remaining_for_campaign( (int) $campaign['id'] ) ) {
				$this->campaigns->set_status( (int) $campaign['id'], 'sent', array( 'finished_at' => Dates::now() ) );
				++$this->stats['finished'];
			}
		}
	}

	/**
	 * Messages this campaign may still send in the current one-minute window (send_throttle = emails/min).
	 *
	 * @param array $campaign Campaign.
	 * @return int
	 */
	private function throttle_allowance( array $campaign ) {
		$limit = isset( $campaign['settings']['send_throttle'] ) ? (int) $campaign['settings']['send_throttle'] : 0;
		if ( $limit <= 0 ) {
			return PHP_INT_MAX;
		}
		$window = $this->throttle_window( (int) $campaign['id'] );
		return max( 0, $limit - $window['count'] );
	}

	/**
	 * Count one send against the campaign's throttle window.
	 *
	 * @param array $campaign Campaign.
	 */
	private function throttle_count( array $campaign ) {
		if ( empty( $campaign['settings']['send_throttle'] ) ) {
			return;
		}
		$window = $this->throttle_window( (int) $campaign['id'] );
		++$window['count'];
		set_transient( 'wpbot_crm_throttle_' . (int) $campaign['id'], $window, 2 * MINUTE_IN_SECONDS );
	}

	/**
	 * Current window {start, count}; a new window starts after 60 s.
	 *
	 * @param int $campaign_id Campaign id.
	 * @return array{start:int,count:int}
	 */
	private function throttle_window( $campaign_id ) {
		$window = get_transient( 'wpbot_crm_throttle_' . $campaign_id );
		if ( ! is_array( $window ) || ! isset( $window['start'], $window['count'] ) || time() - (int) $window['start'] >= MINUTE_IN_SECONDS ) {
			$window = array(
				'start' => time(),
				'count' => 0,
			);
		}
		return $window;
	}

	/**
	 * Once a day: purge old events.
	 */
	private function housekeeping() {
		$last = (int) get_option( self::HOUSEKEEPING_OPTION, 0 );
		if ( time() - $last < DAY_IN_SECONDS ) {
			return;
		}
		update_option( self::HOUSEKEEPING_OPTION, time(), false );
		( new EventRepository() )->purge_older_than( Dates::days_ago_utc( self::EVENT_RETENTION ) );
	}
}
