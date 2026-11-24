<?php
/**
 * The ChatBot Subscribers list (PLAN.md feature C1): every email the chatbot
 * collects, on any channel, lands in AI Marketing automatically.
 *
 * - One canonical hook, fired by the hosts, the chatbot module and the channel add-ons:
 *     do_action( 'qcld_wpbot_chat/lead_captured', array(
 *         'name' => …, 'email' => …, 'phone' => …, 'url' => …, 'language' => …,
 *         'channel' => 'web|messenger|instagram|whatsapp|telegram|live_chat|ticket|hub:<site>',
 *         'consent' => 'marketing' (asked to subscribe) | 'contact' (only asked to be contacted),
 *         'conversation_id' => …,
 *     ) );
 *   Without "consent" the lead counts as a subscription (the chatbot module's
 *   subscribe route fires it without one).
 * - "marketing" leads become subscribed (or pending with double opt-in);
 *   "contact" leads become leads: in the list, never emailed until they opt in.
 * - Each contact is tagged with its channel and the reason is kept.
 * - On by default (setting capture_chatbot).
 * - Unsubscribing in the chat unsubscribes the contact here too.
 * - The backfill imports the chatbot's earlier subscribers (User Data) once,
 *   on the owner's click, in background steps.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Capture;

use QuantumCloud\AiMarketing\Audience\Contacts;
use QuantumCloud\AiMarketing\Audience\Lists;
use QuantumCloud\AiMarketing\Module;
use QuantumCloud\AiMarketing\Sending\Links;
use QuantumCloud\AiMarketing\Settings;
use QuantumCloud\WPBotCore\Jobs\Job_Runner;
use QuantumCloud\WPBotCore\Jobs\Job_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Chatbot capture.
 */
final class Chatbot {

	const HOOK           = 'qcld_wpbot_chat/lead_captured';
	const SYSTEM_KEY     = 'chatbot';
	const STEP           = 'marketing_chatbot_backfill';
	const BACKFILL       = 'qcld_wpbot_marketing_chatbot_backfill';
	const BATCH          = 200;
	const CHANNEL_PREFIX = 'chatbot_';

	/**
	 * Leads handled on this request (email|phone => true), so one lead fired twice counts once.
	 *
	 * @var array<string,bool>
	 */
	private static $seen = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( self::HOOK, array( __CLASS__, 'on_lead' ), 10, 1 );
		add_action( 'qcld_mailing_list_unsubscription_by_user', array( __CLASS__, 'on_chat_unsubscribe' ), 10, 1 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The chatbot's existing public hook.
		add_filter( 'qcld_wpbot_core/job_handlers', array( __CLASS__, 'handlers' ) );
	}

	/**
	 * Channel labels.
	 *
	 * @return array<string,string>
	 */
	public static function channels() {
		return array(
			'web'       => __( 'Website chat', 'chatbot' ),
			'messenger' => __( 'Messenger', 'chatbot' ),
			'instagram' => __( 'Instagram', 'chatbot' ),
			'whatsapp'  => __( 'WhatsApp', 'chatbot' ),
			'telegram'  => __( 'Telegram', 'chatbot' ),
			'live_chat' => __( 'Live chat', 'chatbot' ),
			'ticket'    => __( 'Support ticket', 'chatbot' ),
			'hub'       => __( 'Connected site', 'chatbot' ),
			'backfill'  => __( 'Earlier chatbot subscribers', 'chatbot' ),
		);
	}

	/**
	 * Normalize a lead from the hook (pure; unit tested).
	 *
	 * @param mixed $lead Hook argument.
	 * @return array|null name, email, phone, url, language, channel, consent, site, conversation_id; null when unusable.
	 */
	public static function normalize( $lead ) {
		if ( ! is_array( $lead ) ) {
			return null;
		}
		$email = isset( $lead['email'] ) ? strtolower( trim( (string) $lead['email'] ) ) : '';
		$phone = isset( $lead['phone'] ) ? trim( preg_replace( '/[^0-9+()\-. ]/', '', (string) $lead['phone'] ) ) : '';
		if ( '' !== $email && ! is_email( $email ) ) {
			$email = '';
		}
		if ( '' === $email && '' === $phone ) {
			return null;
		}
		$channel = isset( $lead['channel'] ) ? strtolower( trim( (string) $lead['channel'] ) ) : 'web';
		$site    = '';
		if ( 0 === strpos( $channel, 'hub:' ) ) {
			$site    = substr( $channel, 4 );
			$channel = 'hub';
		}
		$channel = sanitize_key( $channel );
		if ( ! array_key_exists( $channel, self::channels() ) ) {
			$channel = 'web';
		}
		$consent = isset( $lead['consent'] ) ? sanitize_key( (string) $lead['consent'] ) : 'marketing';
		$consent = in_array( $consent, array( 'marketing', 'contact' ), true ) ? $consent : 'marketing';
		return array(
			'name'            => isset( $lead['name'] ) ? sanitize_text_field( (string) $lead['name'] ) : '',
			'email'           => $email,
			'phone'           => $phone,
			'url'             => isset( $lead['url'] ) ? esc_url_raw( (string) $lead['url'] ) : '',
			'language'        => isset( $lead['language'] ) ? sanitize_text_field( (string) $lead['language'] ) : '',
			'channel'         => $channel,
			'consent'         => $consent,
			'site'            => sanitize_text_field( $site ),
			'conversation_id' => isset( $lead['conversation_id'] ) ? sanitize_text_field( (string) $lead['conversation_id'] ) : '',
		);
	}

	/**
	 * Split "Ann Smith" into first and last name (pure).
	 *
	 * @param string $name Full name.
	 * @return array{first_name:string,last_name:string}
	 */
	public static function split_name( $name ) {
		$parts = preg_split( '/\s+/', trim( (string) $name ), 2 );
		return array(
			'first_name' => isset( $parts[0] ) ? (string) $parts[0] : '',
			'last_name'  => isset( $parts[1] ) ? (string) $parts[1] : '',
		);
	}

	/**
	 * A lead from the chatbot.
	 *
	 * @param mixed $lead Lead (see the class comment).
	 * @return void
	 */
	public static function on_lead( $lead ) {
		if ( ! Settings::get( 'capture_chatbot' ) ) {
			return;
		}
		$lead = self::normalize( $lead );
		if ( ! $lead ) {
			return;
		}
		$key = ( '' !== $lead['email'] ? $lead['email'] : $lead['phone'] ) . '|' . $lead['consent'];
		if ( isset( self::$seen[ $key ] ) ) {
			return;
		}
		self::$seen[ $key ] = true;
		self::save( $lead, 'marketing' === $lead['consent'] );
	}

	/**
	 * Save a normalized lead into the ChatBot Subscribers list.
	 *
	 * @param array $lead       Normalized lead.
	 * @param bool  $own_opt_in The person asked to subscribe just now.
	 * @param array $options    Passed to Contacts::capture().
	 * @return array|\WP_Error Capture result.
	 */
	public static function save( array $lead, $own_opt_in, array $options = array() ) {
		$list_id  = self::list_id();
		$channels = self::channels();
		$channel  = $channels[ $lead['channel'] ];
		$where    = 'hub' === $lead['channel'] && '' !== $lead['site'] ? $lead['site'] : $lead['url'];
		$reason   = 'marketing' === $lead['consent']
			/* translators: 1: channel, e.g. WhatsApp, 2: page or site. */
			? sprintf( __( 'Subscribed in the chatbot (%1$s) %2$s', 'chatbot' ), $channel, $where )
			/* translators: 1: channel, 2: page or site. */
			: sprintf( __( 'Left an email in the chatbot (%1$s) to be contacted %2$s', 'chatbot' ), $channel, $where );
		$groups = array( $list_id => trim( $reason ) );
		$tag_id = Lists::ensure_system( self::CHANNEL_PREFIX . $lead['channel'], sprintf( /* translators: %s: channel. */ __( 'Chat: %s', 'chatbot' ), $channel ), 'tag' );
		if ( $tag_id ) {
			$groups[ $tag_id ] = trim( $reason );
		}
		$data   = array_merge(
			self::split_name( $lead['name'] ),
			array(
				'email'        => $lead['email'],
				'phone'        => $lead['phone'],
				'language'     => $lead['language'],
				'attributes'   => array_filter(
					array(
						'chat_channel'      => $lead['channel'],
						'chat_page'         => $lead['url'],
						'chat_site'         => $lead['site'],
						'chat_conversation' => $lead['conversation_id'],
					)
				),
				'consent_text' => $reason,
			)
		);
		$result = Contacts::capture( $data, $lead['consent'], 'chatbot', $groups, $own_opt_in, $options );
		if ( ! is_wp_error( $result ) ) {
			if ( '' !== $lead['conversation_id'] ) {
				// Later chat events of this conversation (unanswered question, handoff) find the person.
				\QuantumCloud\AiMarketing\Audience\Identities::link( (int) $result['id'], 'chat_session', $lead['conversation_id'] );
			}
			Links::event( (int) $result['id'], null, 'chat_lead', array_intersect_key( $lead, array_flip( array( 'channel', 'consent', 'url', 'site', 'conversation_id' ) ) ) );
			/**
			 * A chatbot lead was saved in AI Marketing.
			 *
			 * @param int   $contact_id Contact id.
			 * @param array $lead       Normalized lead.
			 * @param array $result     id, created, status.
			 */
			do_action( 'qcld_wpbot_marketing/chatbot_lead', (int) $result['id'], $lead, $result );
		}
		return $result;
	}

	/**
	 * The ChatBot Subscribers list id (created on first use).
	 *
	 * @return int
	 */
	public static function list_id() {
		return Lists::ensure_system( self::SYSTEM_KEY, __( 'ChatBot Subscribers', 'chatbot' ) );
	}

	/**
	 * Someone unsubscribed in the chat.
	 *
	 * @param string $email Email.
	 * @return void
	 */
	public static function on_chat_unsubscribe( $email ) {
		$contact = Contacts::by_email( (string) $email );
		if ( $contact ) {
			Links::unsubscribe( $contact, 'chat' );
		}
	}

	/* ── Backfill of the chatbot's earlier subscribers. ── */

	/**
	 * Step handler.
	 *
	 * @param array $handlers Handlers.
	 * @return array
	 */
	public static function handlers( $handlers ) {
		$handlers               = (array) $handlers;
		$handlers[ self::STEP ] = array( __CLASS__, 'run_step' );
		return $handlers;
	}

	/**
	 * Backfill report: status (none, running, done), job_id, imported, existing, skipped, total.
	 *
	 * @return array
	 */
	public static function backfill_report() {
		$stored          = get_option( self::BACKFILL, array() );
		$report          = array_merge(
			array(
				'status'   => 'none',
				'job_id'   => 0,
				'imported' => 0,
				'existing' => 0,
				'skipped'  => 0,
			),
			is_array( $stored ) ? $stored : array()
		);
		$source          = self::source();
		$report['total'] = $source ? $source->count() : 0;
		return $report;
	}

	/**
	 * Start (or continue) the backfill and run it for a few seconds.
	 *
	 * @param int $seconds Time budget.
	 * @return array Report.
	 */
	public static function backfill( $seconds = 15 ) {
		$report = self::backfill_report();
		$job    = $report['job_id'] ? Job_Store::get( (int) $report['job_id'] ) : null;
		if ( ! $job || ! in_array( $job['status'], Job_Store::ACTIVE, true ) ) {
			$job_id = Job_Store::create(
				Module::ID,
				'chatbot_backfill',
				array( 'title' => __( 'Import earlier chatbot subscribers', 'chatbot' ) ),
				array( self::backfill_step( 0 ) )
			);
			$report = array_merge(
				$report,
				array(
					'status'   => 'running',
					'job_id'   => $job_id,
					'imported' => 0,
					'existing' => 0,
					'skipped'  => 0,
				)
			);
			update_option( self::BACKFILL, $report, false );
		}
		if ( $report['job_id'] && class_exists( Job_Runner::class ) ) {
			Job_Runner::run_for( (int) $report['job_id'], max( 1, (int) $seconds ) );
		}
		return self::backfill_report();
	}

	/**
	 * One backfill step.
	 *
	 * @param int $after Last User Data id done.
	 * @return array
	 */
	private static function backfill_step( $after ) {
		return array(
			'kind'  => self::STEP,
			'label' => __( 'Import chatbot subscribers', 'chatbot' ),
			'input' => array( 'after' => (int) $after ),
		);
	}

	/**
	 * Import one page of User Data.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array
	 */
	public static function run_step( array $step, array $job ) {
		$source = self::source();
		$after  = isset( $step['input']['after'] ) ? (int) $step['input']['after'] : 0;
		$rows   = $source ? $source->page( $after, self::BATCH ) : array();
		$report = get_option( self::BACKFILL, array() );
		$report = is_array( $report ) ? $report : array();
		foreach ( $rows as $row ) {
			$after = (int) $row['id'];
			$lead  = self::normalize(
				array(
					'name'    => $row['name'],
					'email'   => $row['email'],
					'phone'   => $row['phone'],
					'url'     => $row['url'],
					'channel' => 'backfill',
					'consent' => 'marketing',
				)
			);
			if ( ! $lead ) {
				$report['skipped'] = ( isset( $report['skipped'] ) ? (int) $report['skipped'] : 0 ) + 1;
				continue;
			}
			$existing = '' !== $lead['email'] ? Contacts::by_email( $lead['email'] ) : null;
			$result   = self::save( $lead, false, array( 'skip_double_opt_in' => true ) );
			if ( ! is_wp_error( $result ) && ! empty( $row['unsubscribed'] ) ) {
				$contact = Contacts::get( (int) $result['id'] );
				if ( $contact ) {
					Links::unsubscribe( $contact, 'chat' );
				}
			}
			$key            = is_wp_error( $result ) ? 'skipped' : ( $existing ? 'existing' : 'imported' );
			$report[ $key ] = ( isset( $report[ $key ] ) ? (int) $report[ $key ] : 0 ) + 1;
		}
		if ( count( $rows ) === self::BATCH ) {
			Job_Store::add_steps( (int) $job['id'], array( self::backfill_step( $after ) ) );
		} else {
			$report['status']      = 'done';
			$report['finished_at'] = current_time( 'mysql', true );
		}
		update_option( self::BACKFILL, $report, false );
		return array( 'after' => $after );
	}

	/**
	 * Summary for the Capture screen.
	 *
	 * @return array
	 */
	public static function overview() {
		global $wpdb;
		$list_id  = self::list_id();
		$list     = Lists::get( $list_id );
		$channels = array();
		foreach ( Lists::all( 'tag' ) as $tag ) {
			if ( 0 === strpos( $tag['system_key'], self::CHANNEL_PREFIX ) ) {
				$channels[ substr( $tag['system_key'], strlen( self::CHANNEL_PREFIX ) ) ] = (int) $tag['members'];
			}
		}
		$members = \QuantumCloud\AiMarketing\Storage\Schema::table( 'memberships' );
		$people  = \QuantumCloud\AiMarketing\Storage\Schema::table( 'contacts' );
		$by      = (array) $wpdb->get_results( $wpdb->prepare( "SELECT c.status, COUNT(*) AS n FROM `{$members}` m JOIN `{$people}` c ON c.id = m.contact_id WHERE m.list_id = %d GROUP BY c.status", $list_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own tables.
		$status  = array();
		foreach ( $by as $row ) {
			$status[ (string) $row['status'] ] = (int) $row['n'];
		}
		return array(
			'enabled'  => (bool) Settings::get( 'capture_chatbot' ),
			'list'     => $list,
			'channels' => $channels,
			'labels'   => self::channels(),
			'status'   => $status,
			'backfill' => self::backfill_report(),
		);
	}

	/**
	 * The host's lead source.
	 *
	 * @return \QuantumCloud\AiMarketing\Host\Lead_Source|null
	 */
	private static function source() {
		$host = Module::instance()->host();
		return $host ? $host->get_lead_source() : null;
	}
}
