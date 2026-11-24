<?php
/**
 * The sending queue. A message is sent at most once:
 *
 * 1. claim(): one UPDATE marks a batch "sending" with a unique claim token,
 *    only on rows still "queued" with no claim. Two runners can never claim
 *    the same row, so no lock is needed (audit C1).
 * 2. Each claimed row is checked again (the contact must still be mailable
 *    and not suppressed, the campaign still sending), then sent once and
 *    marked "sent" or "failed". A row never goes back to "queued".
 * 3. recover(): a row left in "sending" (PHP died mid-send) becomes
 *    "unknown" and is never retried automatically.
 *
 * Runs from WP-Cron every minute while there is work, and right away when a
 * campaign starts.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Sending;

use QuantumCloud\AiMarketing\Audience\Contacts;
use QuantumCloud\AiMarketing\Audience\Rules;
use QuantumCloud\AiMarketing\Audience\Suppression;
use QuantumCloud\AiMarketing\Content\Campaigns;
use QuantumCloud\AiMarketing\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Sending queue.
 */
final class Queue {

	const HOOK        = 'qcld_wpbot_marketing_send';
	const SCHEDULE    = 'qcld_wpbot_marketing_minute';
	const BATCH       = 50;
	const STUCK_AFTER = 900;
	const TIME_BUDGET = 20;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- One minute, only while there is work.
		add_action( self::HOOK, array( __CLASS__, 'cron' ) );
		add_action( 'init', array( __CLASS__, 'ensure_scheduled' ), 20 );
	}

	/**
	 * Every-minute schedule.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function schedules( $schedules ) {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute (AI Marketing sending)', 'chatbot' ),
		);
		return $schedules;
	}

	/**
	 * Keep the cron event only while there is work.
	 *
	 * @return void
	 */
	public static function ensure_scheduled() {
		$has  = self::has_work();
		$next = wp_next_scheduled( self::HOOK );
		if ( $has && ! $next ) {
			wp_schedule_event( time() + 30, self::SCHEDULE, self::HOOK );
		} elseif ( ! $has && $next ) {
			wp_clear_scheduled_hook( self::HOOK );
		}
	}

	/**
	 * Cron run.
	 *
	 * @return void
	 */
	public static function cron() {
		Campaigns::start_due();
		self::process( self::TIME_BUDGET );
	}

	/**
	 * Whether anything is waiting to be sent or scheduled.
	 *
	 * @return bool
	 */
	public static function has_work() {
		global $wpdb;
		$messages  = Schema::table( 'messages' );
		$campaigns = Schema::table( 'campaigns' );
		return (bool) $wpdb->get_var( "SELECT 1 FROM `{$messages}` WHERE status IN ('queued','sending') LIMIT 1" ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
			|| (bool) $wpdb->get_var( "SELECT 1 FROM `{$campaigns}` WHERE status = 'scheduled' LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Queue a campaign for contacts (each contact once per campaign).
	 *
	 * @param int   $campaign_id Campaign id.
	 * @param int[] $contact_ids Contact ids.
	 * @return int Rows added.
	 */
	public static function enqueue_campaign( $campaign_id, array $contact_ids ) {
		global $wpdb;
		$table = Schema::table( 'messages' );
		$now   = current_time( 'mysql', true );
		$added = 0;
		foreach ( array_chunk( array_map( 'intval', $contact_ids ), 500 ) as $chunk ) {
			$values = array();
			foreach ( $chunk as $contact_id ) {
				$values[] = $wpdb->prepare( '(%d, %d, %s, %s)', (int) $campaign_id, $contact_id, 'queued', $now );
			}
			$added += (int) $wpdb->query( "INSERT IGNORE INTO `{$table}` (campaign_id, contact_id, status, queued_at) VALUES " . implode( ',', $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table; each value prepared above.
		}
		self::ensure_scheduled();
		return $added;
	}

	/**
	 * Queue one journey email for a contact.
	 *
	 * @param int    $journey_id Journey id.
	 * @param string $step_key   Step key.
	 * @param int    $contact_id Contact id.
	 * @return int Message id.
	 */
	public static function enqueue_journey( $journey_id, $step_key, $contact_id ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'messages' ),
			array(
				'journey_id' => (int) $journey_id,
				'step_key'   => substr( (string) $step_key, 0, 40 ),
				'contact_id' => (int) $contact_id,
				'status'     => 'queued',
				'queued_at'  => current_time( 'mysql', true ),
			)
		);
		self::ensure_scheduled();
		return (int) $wpdb->insert_id;
	}

	/**
	 * Claim a batch atomically.
	 *
	 * @param int $limit Batch size.
	 * @return array[] Claimed rows.
	 */
	public static function claim( $limit ) {
		global $wpdb;
		$messages  = Schema::table( 'messages' );
		$campaigns = Schema::table( 'campaigns' );
		$token     = wp_generate_password( 32, false, false );
		// While automation is paused (kill switch), journey emails wait in the queue.
		$journeys = self::automation_paused() ? '' : ' OR ( campaign_id IS NULL AND journey_id IS NOT NULL )';
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own tables; atomic claim.
			$wpdb->prepare(
				"UPDATE `{$messages}` SET status = 'sending', claim = %s, claimed_at = %s, attempts = attempts + 1 WHERE status = 'queued' AND claim IS NULL AND ( campaign_id IN ( SELECT id FROM `{$campaigns}` WHERE status = 'sending' ){$journeys} ) ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table names; fixed SQL fragment.
				$token,
				current_time( 'mysql', true ),
				max( 1, (int) $limit )
			)
		);
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$messages}` WHERE claim = %s ORDER BY id ASC", $token ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * How many emails may go out in this run: -1 for no limit (warm-up and daily caps set it).
	 *
	 * @return int
	 */
	public static function budget() {
		/**
		 * Emails that may be sent now (-1: no limit). The Pro module's warm-up lowers it.
		 *
		 * @param int $budget Budget.
		 */
		$budget = (int) apply_filters( 'qcld_wpbot_marketing/send_budget', -1 );
		return $budget < 0 ? -1 : $budget;
	}

	/**
	 * Emails sent by the module since a time (all campaigns and journeys).
	 *
	 * @param int $since Unix time.
	 * @return int
	 */
	public static function sent_total( $since ) {
		global $wpdb;
		$messages = Schema::table( 'messages' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$messages}` WHERE status IN ('sent','unknown') AND sent_at >= %s", gmdate( 'Y-m-d H:i:s', (int) $since ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Whether a contact opened or clicked emails of a journey since a time (for branches).
	 *
	 * @param int    $contact_id Contact id.
	 * @param int    $journey_id Journey id.
	 * @param string $since      MySQL time (UTC).
	 * @return array{sent:bool,opened:bool,clicked:bool}
	 */
	public static function engagement( $contact_id, $journey_id, $since ) {
		global $wpdb;
		$messages = Schema::table( 'messages' );
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS sent, MAX(opens) AS opens, MAX(clicks) AS clicks FROM `{$messages}` WHERE contact_id = %d AND journey_id = %d AND status IN ('sent','unknown') AND queued_at >= %s", (int) $contact_id, (int) $journey_id, (string) $since ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return array(
			'sent'    => $row && (int) $row['sent'] > 0,
			'opened'  => $row && (int) $row['opens'] > 0,
			'clicked' => $row && (int) $row['clicks'] > 0,
		);
	}

	/**
	 * Whether automated sending is paused (the kill switch in Master).
	 *
	 * @return bool
	 */
	public static function automation_paused() {
		/**
		 * Pause every automation: journeys stop advancing and their emails wait in the queue.
		 *
		 * @param bool $paused Paused.
		 */
		return (bool) apply_filters( 'qcld_wpbot_marketing/automation_paused', false );
	}

	/**
	 * Ask the guardrails whether an automated email may go to a contact now.
	 *
	 * @param array $contact Contact.
	 * @param array $context kind (journey, autopilot), journey_id, step_key, campaign_id.
	 * @return true|WP_Error A WP_Error with data retry_at (timestamp) to try later, or without to skip.
	 */
	public static function may_send( array $contact, array $context ) {
		/**
		 * Guardrails for automated email (frequency caps, quiet hours, budgets).
		 *
		 * @param true|WP_Error $allowed True, or a WP_Error (data retry_at: try again at that time).
		 * @param array         $contact Contact.
		 * @param array         $context kind, journey_id, step_key, campaign_id.
		 */
		$allowed = apply_filters( 'qcld_wpbot_marketing/may_send', true, $contact, $context );
		return is_wp_error( $allowed ) ? $allowed : true;
	}

	/**
	 * Emails sent to a contact since a time (for frequency caps).
	 *
	 * @param int    $contact_id Contact id.
	 * @param int    $since      Unix time.
	 * @param string $kind       all, campaign or journey.
	 * @return int
	 */
	public static function sent_count( $contact_id, $since, $kind = 'all' ) {
		global $wpdb;
		$messages = Schema::table( 'messages' );
		$extra    = 'journey' === $kind ? ' AND journey_id IS NOT NULL' : ( 'campaign' === $kind ? ' AND campaign_id IS NOT NULL' : '' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$messages}` WHERE contact_id = %d AND status IN ('queued','sending','sent','unknown') AND queued_at >= %s{$extra}", (int) $contact_id, gmdate( 'Y-m-d H:i:s', (int) $since ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table; fixed SQL fragment.
	}

	/**
	 * Send for up to $seconds.
	 *
	 * @param int $seconds Time budget.
	 * @return array{sent:int,failed:int,skipped:int}
	 */
	public static function process( $seconds ) {
		$start = microtime( true );
		$done  = array(
			'sent'    => 0,
			'failed'  => 0,
			'skipped' => 0,
		);
		self::recover();
		$budget = self::budget();
		while ( microtime( true ) - $start < $seconds && 0 !== $budget ) {
			$rows = self::claim( $budget > 0 ? min( self::BATCH, $budget ) : self::BATCH );
			if ( ! $rows ) {
				break;
			}
			foreach ( $rows as $row ) {
				$result = self::send_one( $row );
				++$done[ $result ];
				if ( $budget > 0 && 'sent' === $result ) {
					--$budget;
				}
			}
		}
		Campaigns::finish_done();
		self::ensure_scheduled();
		return $done;
	}

	/**
	 * Send one claimed row. Returns sent, failed or skipped.
	 *
	 * @param array $row Message row.
	 * @return string
	 */
	public static function send_one( array $row ) {
		$contact = Contacts::get( (int) $row['contact_id'] );
		if ( ! $contact || empty( $contact['email'] ) || ! Rules::mailable( (string) $contact['status'] ) || Suppression::blocked( (string) $contact['email'] ) ) {
			self::finish( $row, 'skipped', __( 'The contact is no longer subscribed.', 'chatbot' ) );
			return 'skipped';
		}
		$email = $row['campaign_id'] ? Campaigns::email_for( (int) $row['campaign_id'] ) : apply_filters( 'qcld_wpbot_marketing/journey_email', null, $row );
		if ( ! is_array( $email ) ) {
			self::finish( $row, 'skipped', __( 'The email is no longer available.', 'chatbot' ) );
			return 'skipped';
		}
		$built  = Mailer::build( $email, $contact, (int) $row['id'] );
		$result = Mailer::send(
			$built,
			array(
				'message_id'  => (int) $row['id'],
				'campaign_id' => (int) $row['campaign_id'],
				'journey_id'  => (int) $row['journey_id'],
				'kind'        => $row['campaign_id'] ? 'campaign' : 'journey',
			)
		);
		if ( true === $result ) {
			self::finish( $row, 'sent', '', Mailer::last_id() );
			Links::event( (int) $contact['id'], (int) $row['id'], 'sent', array() );
			/**
			 * An email went out.
			 *
			 * @param int   $message_id Message id.
			 * @param int   $contact_id Contact id.
			 * @param array $row        Message row: campaign_id, journey_id, step_key.
			 */
			do_action( 'qcld_wpbot_marketing/email_sent', (int) $row['id'], (int) $contact['id'], $row );
			return 'sent';
		}
		self::finish( $row, 'failed', $result->get_error_message() );
		return 'failed';
	}

	/**
	 * Final status of a claimed row (and the campaign's counters).
	 *
	 * @param array  $row    Row.
	 * @param string $status sent, failed or skipped.
	 * @param string $error  Error text.
	 * @param string $provider_id The provider's message id.
	 * @return void
	 */
	private static function finish( array $row, $status, $error, $provider_id = '' ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'messages' ),
			array(
				'status'      => $status,
				'error'       => '' !== $error ? substr( $error, 0, 500 ) : null,
				'sent_at'     => 'sent' === $status ? $now : null,
				'provider_id' => (string) $provider_id,
			),
			array(
				'id'    => (int) $row['id'],
				'claim' => (string) $row['claim'],
			)
		);
		if ( $row['campaign_id'] && in_array( $status, array( 'sent', 'failed' ), true ) ) {
			$campaigns = Schema::table( 'campaigns' );
			$column    = 'sent' === $status ? 'sent' : 'failed';
			$wpdb->query( $wpdb->prepare( "UPDATE `{$campaigns}` SET {$column} = {$column} + 1 WHERE id = %d", (int) $row['campaign_id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table; column from a fixed pair.
		}
	}

	/**
	 * Rows left "sending" too long become "unknown" (never resent automatically).
	 *
	 * @return int Rows changed.
	 */
	public static function recover() {
		global $wpdb;
		$messages = Schema::table( 'messages' );
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - self::STUCK_AFTER );
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE `{$messages}` SET status = 'unknown', error = %s WHERE status = 'sending' AND claimed_at < %s", __( 'Sending was interrupted; not retried, so nobody gets it twice.', 'chatbot' ), $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}
}
