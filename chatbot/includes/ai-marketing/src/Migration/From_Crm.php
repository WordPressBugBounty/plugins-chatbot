<?php
/**
 * One-time copy of the old AI Marketing data (wpbot_crm_*) into the new tables.
 *
 * - Copies only: the old tables, options and the chatbot's User Data are
 *   never changed or dropped (owner, 19 Nov 2026).
 * - Idempotent: every copied row is recorded in legacy_map, so running it
 *   again skips what is already there. A contact whose email already exists
 *   in the new tables is merged, never duplicated; an opt-out always wins.
 * - Safe: nothing sends because of the copy. Campaigns that were scheduled
 *   or sending, and every automation, arrive paused; queued emails arrive as
 *   "skipped" so the owner decides what to resume.
 * - Background: WPBot Core job steps of BATCH rows each, with a report in
 *   the option REPORT_OPTION.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Migration;

use QuantumCloud\AiMarketing\Module;
use QuantumCloud\AiMarketing\Settings;
use QuantumCloud\AiMarketing\Storage\Schema;
use QuantumCloud\WPBotCore\Jobs\Job_Runner;
use QuantumCloud\WPBotCore\Jobs\Job_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Migrator.
 */
final class From_Crm {

	const STEP          = 'marketing_migrate';
	const REPORT_OPTION = 'qcld_wpbot_marketing_migration';
	const BATCH         = 300;

	/**
	 * Kinds in copy order (each later kind maps ids of the earlier ones).
	 *
	 * @var string[]
	 */
	const KINDS = array( 'settings', 'group', 'contact', 'membership', 'template', 'campaign', 'automation', 'message', 'event' );

	/**
	 * Old table per kind.
	 *
	 * @var array<string,string>
	 */
	const SOURCE = array(
		'group'      => 'groups',
		'contact'    => 'contacts',
		'membership' => 'group_members',
		'template'   => 'templates',
		'campaign'   => 'campaigns',
		'automation' => 'automations',
		'message'    => 'messages',
		'event'      => 'events',
	);

	/**
	 * Register the step handler and start the copy once, automatically.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_core/job_handlers', array( __CLASS__, 'handlers' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_start' ) );
	}

	/**
	 * Step handler for WPBot Core's job runner.
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
	 * Start the copy the first time an administrator opens the admin, when old data exists.
	 *
	 * @return void
	 */
	public static function maybe_start() {
		if ( ! current_user_can( 'manage_options' ) || ! Legacy_Tables::present() ) {
			return;
		}
		$report = self::report();
		if ( 'none' === $report['status'] ) {
			self::start();
		}
	}

	/**
	 * Create the copy job (or return the running one).
	 *
	 * @return int Job id (0 when it could not start).
	 */
	public static function start() {
		$report = self::report();
		if ( 'running' === $report['status'] && ! empty( $report['job_id'] ) ) {
			$job = Job_Store::get( (int) $report['job_id'] );
			if ( $job && in_array( $job['status'], Job_Store::ACTIVE, true ) ) {
				return (int) $report['job_id'];
			}
		}
		$job_id = Job_Store::create(
			Module::ID,
			'migrate',
			array( 'title' => __( 'Copy existing AI Marketing data', 'chatbot' ) ),
			array( self::step( 'settings', 0 ) )
		);
		if ( $job_id ) {
			$report['status']      = 'running';
			$report['job_id']      = $job_id;
			$report['started_at']  = current_time( 'mysql', true );
			$report['finished_at'] = '';
			self::save_report( $report );
		}
		return $job_id;
	}

	/**
	 * Run the copy for a few seconds now (from the admin screen).
	 *
	 * @param int $seconds Time budget.
	 * @return array Report.
	 */
	public static function run_now( $seconds = 20 ) {
		$job_id = self::start();
		if ( $job_id && class_exists( Job_Runner::class ) ) {
			Job_Runner::run_for( $job_id, max( 1, (int) $seconds ) );
		}
		return self::report();
	}

	/**
	 * A job step definition.
	 *
	 * @param string $kind   Kind.
	 * @param int    $cursor Last id (or offset for memberships).
	 * @return array
	 */
	private static function step( $kind, $cursor ) {
		return array(
			'kind'  => self::STEP,
			/* translators: %s: kind of data, for example "contact". */
			'label' => sprintf( __( 'Copy %s', 'chatbot' ), $kind ),
			'input' => array(
				'kind'   => $kind,
				'cursor' => (int) $cursor,
			),
		);
	}

	/**
	 * Copy one batch, then queue the next batch or the next kind.
	 *
	 * @param array $step Step (input: kind, cursor).
	 * @param array $job  Job.
	 * @return array Output.
	 */
	public static function run_step( array $step, array $job ) {
		$input  = isset( $step['input'] ) ? (array) $step['input'] : array();
		$kind   = isset( $input['kind'] ) ? (string) $input['kind'] : '';
		$cursor = isset( $input['cursor'] ) ? (int) $input['cursor'] : 0;
		if ( ! in_array( $kind, self::KINDS, true ) ) {
			return array( 'skipped' => true );
		}

		$result = self::copy( $kind, $cursor );
		self::add_counts( $kind, $result );

		if ( $result['more'] ) {
			Job_Store::add_steps( (int) $job['id'], array( self::step( $kind, $result['cursor'] ) ) );
		} else {
			$next = self::next_kind( $kind );
			if ( '' !== $next ) {
				Job_Store::add_steps( (int) $job['id'], array( self::step( $next, 0 ) ) );
			} else {
				$report                = self::report();
				$report['status']      = 'done';
				$report['finished_at'] = current_time( 'mysql', true );
				self::save_report( $report );
			}
		}
		return array(
			'kind'   => $kind,
			'copied' => $result['copied'],
		);
	}

	/**
	 * The kind after another, or ''.
	 *
	 * @param string $kind Kind.
	 * @return string
	 */
	public static function next_kind( $kind ) {
		$index = array_search( $kind, self::KINDS, true );
		return ( false !== $index && isset( self::KINDS[ $index + 1 ] ) ) ? self::KINDS[ $index + 1 ] : '';
	}

	/**
	 * Copy one batch of a kind.
	 *
	 * @param string $kind   Kind.
	 * @param int    $cursor Cursor.
	 * @return array{copied:int,merged:int,skipped:int,more:bool,cursor:int}
	 */
	public static function copy( $kind, $cursor ) {
		$out = array(
			'copied'  => 0,
			'merged'  => 0,
			'skipped' => 0,
			'more'    => false,
			'cursor'  => $cursor,
		);
		if ( 'settings' === $kind ) {
			$out['copied'] = self::copy_settings() ? 1 : 0;
			return $out;
		}
		if ( 'membership' === $kind ) {
			$rows = Legacy_Tables::memberships( $cursor, self::BATCH );
			foreach ( $rows as $row ) {
				++$out[ self::copy_membership( $row ) ];
			}
			$out['cursor'] = $cursor + count( $rows );
			$out['more']   = count( $rows ) === self::BATCH;
			return $out;
		}
		$rows = Legacy_Tables::rows_after( self::SOURCE[ $kind ], $cursor, self::BATCH );
		foreach ( $rows as $row ) {
			$legacy_id = (int) $row['id'];
			if ( self::mapped( $kind, $legacy_id ) ) {
				++$out['skipped'];
			} else {
				$method = 'copy_' . $kind;
				++$out[ self::$method( $row ) ];
			}
			$out['cursor'] = $legacy_id;
		}
		$out['more'] = count( $rows ) === self::BATCH;
		return $out;
	}

	/* ── Per-kind copies. Each returns copied, merged or skipped. ── */

	/**
	 * Old settings into the new option, once, when the new one was never saved.
	 *
	 * @return bool Whether anything was copied.
	 */
	private static function copy_settings() {
		if ( false !== get_option( Settings::OPTION, false ) ) {
			return false;
		}
		$old = Legacy_Tables::settings();
		if ( array() === $old ) {
			return false;
		}
		$keep = array_intersect_key( $old, array_flip( array( 'from_name', 'from_email', 'reply_to', 'company_address', 'double_opt_in', 'track_opens', 'track_clicks' ) ) );
		Settings::save( $keep );
		return true;
	}

	/**
	 * A list or tag.
	 *
	 * @param array $row Old row.
	 * @return string
	 */
	private static function copy_group( array $row ) {
		global $wpdb;
		$table = Schema::table( 'lists' );
		$type  = 'tag' === $row['type'] ? 'tag' : 'list';
		$slug  = sanitize_title( '' !== (string) $row['slug'] ? $row['slug'] : $row['name'] );
		$found = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE type = %s AND slug = %s", $type, $slug ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( $found ) {
			self::map( 'group', (int) $row['id'], $found );
			return 'merged';
		}
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$table,
			array(
				'type'        => $type,
				'name'        => (string) $row['name'],
				'slug'        => $slug,
				'description' => (string) $row['description'],
				'origin'      => 'migration',
				'legacy_id'   => (int) $row['id'],
				'created_at'  => (string) $row['created_at'],
				'updated_at'  => (string) $row['updated_at'],
			)
		);
		return self::map_insert( 'group', (int) $row['id'] );
	}

	/**
	 * A contact. Merged into an existing contact with the same email; an opt-out wins.
	 *
	 * @param array $row Old row.
	 * @return string
	 */
	private static function copy_contact( array $row ) {
		global $wpdb;
		$table  = Schema::table( 'contacts' );
		$email  = strtolower( trim( (string) $row['email'] ) );
		$mapped = self::map_status( (string) $row['status'] );
		if ( '' === $email ) {
			return 'skipped';
		}
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, status FROM `{$table}` WHERE email = %s", $email ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		if ( $existing ) {
			$status = self::merge_status( (string) $existing['status'], $mapped['status'] );
			if ( $status !== $existing['status'] ) {
				$wpdb->update( $table, array( 'status' => $status ), array( 'id' => (int) $existing['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			}
			self::map( 'contact', (int) $row['id'], (int) $existing['id'] );
			return 'merged';
		}
		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $row['token'] );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$table,
			array(
				'email'            => $email,
				'phone'            => (string) $row['phone'],
				'first_name'       => (string) $row['first_name'],
				'last_name'        => (string) $row['last_name'],
				'company'          => (string) $row['company'],
				'website'          => (string) $row['website'],
				'status'           => $mapped['status'],
				'lifecycle'        => $mapped['lifecycle'],
				'source'           => (string) $row['source'],
				'attributes'       => $row['attributes'],
				'token'            => 32 === strlen( $token ) ? $token : self::token(),
				'confirmed_at'     => $row['confirmed_at'],
				'unsubscribed_at'  => $row['unsubscribed_at'],
				'last_activity_at' => $row['last_activity_at'],
				'legacy_id'        => (int) $row['id'],
				'created_at'       => (string) $row['created_at'],
				'updated_at'       => (string) $row['updated_at'],
			)
		);
		return self::map_insert( 'contact', (int) $row['id'] );
	}

	/**
	 * A list or tag membership.
	 *
	 * @param array $row Old row (group_id, contact_id, added_at).
	 * @return string
	 */
	private static function copy_membership( array $row ) {
		global $wpdb;
		$list    = self::mapped( 'group', (int) $row['group_id'] );
		$contact = self::mapped( 'contact', (int) $row['contact_id'] );
		if ( ! $list || ! $contact ) {
			return 'skipped';
		}
		$table = Schema::table( 'memberships' );
		$done  = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$table}` (list_id, contact_id, reason, confidence, origin, added_at) VALUES (%d, %d, %s, 100, 'migration', %s)", $list, $contact, '', (string) $row['added_at'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return $done ? 'copied' : 'skipped';
	}

	/**
	 * A template.
	 *
	 * @param array $row Old row.
	 * @return string
	 */
	private static function copy_template( array $row ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'templates' ),
			array(
				'name'         => (string) $row['name'],
				'category'     => (string) $row['category'],
				'subject'      => (string) $row['subject'],
				'content_html' => $row['content_html'],
				'design'       => $row['design'],
				'is_builtin'   => (int) $row['is_builtin'],
				'legacy_id'    => (int) $row['id'],
				'created_at'   => (string) $row['created_at'],
				'updated_at'   => (string) $row['updated_at'],
			)
		);
		return self::map_insert( 'template', (int) $row['id'] );
	}

	/**
	 * A campaign. Scheduled or sending campaigns arrive paused.
	 *
	 * @param array $row Old row.
	 * @return string
	 */
	private static function copy_campaign( array $row ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'campaigns' ),
			array(
				'name'         => (string) $row['name'],
				'subject'      => (string) $row['subject'],
				'preview_text' => (string) $row['preview_text'],
				'from_name'    => (string) $row['from_name'],
				'from_email'   => (string) $row['from_email'],
				'reply_to'     => (string) $row['reply_to'],
				'content_html' => $row['content_html'],
				'template_id'  => $row['template_id'] ? self::mapped( 'template', (int) $row['template_id'] ) : null,
				'audience'     => wp_json_encode( self::remap_audience( self::json( $row['audience'] ), array( __CLASS__, 'mapped_group' ) ) ),
				'settings'     => wp_json_encode( self::remap_audience( self::json( $row['settings'] ), array( __CLASS__, 'mapped_group' ) ) ),
				'status'       => self::map_campaign_status( (string) $row['status'] ),
				'origin'       => 'migration',
				'scheduled_at' => $row['scheduled_at'],
				'started_at'   => $row['started_at'],
				'finished_at'  => $row['finished_at'],
				'recipients'   => (int) $row['recipients'],
				'sent'         => (int) $row['sent'],
				'failed'       => (int) $row['failed'],
				'legacy_id'    => (int) $row['id'],
				'created_at'   => (string) $row['created_at'],
				'updated_at'   => (string) $row['updated_at'],
			)
		);
		return self::map_insert( 'campaign', (int) $row['id'] );
	}

	/**
	 * An automation, as a paused journey (the owner reviews it in the new builder).
	 *
	 * @param array $row Old row.
	 * @return string
	 */
	private static function copy_automation( array $row ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'journeys' ),
			array(
				'name'           => (string) $row['name'],
				'status'         => 'paused',
				'mode'           => 'draft',
				'trigger_type'   => (string) $row['trigger_type'],
				'trigger_config' => $row['trigger_config'],
				'steps'          => $row['steps'],
				'settings'       => wp_json_encode( array( 'migrated_status' => (string) $row['status'] ) ),
				'origin'         => 'migration',
				'enrolled'       => (int) $row['enrolled'],
				'completed'      => (int) $row['completed'],
				'legacy_id'      => (int) $row['id'],
				'created_at'     => (string) $row['created_at'],
				'updated_at'     => (string) $row['updated_at'],
			)
		);
		return self::map_insert( 'automation', (int) $row['id'] );
	}

	/**
	 * A sent (or queued) email, for stats and for old tracking links.
	 *
	 * @param array $row Old row.
	 * @return string
	 */
	private static function copy_message( array $row ) {
		global $wpdb;
		$contact = self::mapped( 'contact', (int) $row['contact_id'] );
		if ( ! $contact ) {
			return 'skipped';
		}
		$status = self::map_message_status( (string) $row['status'] );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'messages' ),
			array(
				'campaign_id'    => $row['campaign_id'] ? self::mapped( 'campaign', (int) $row['campaign_id'] ) : null,
				'journey_id'     => $row['automation_id'] ? self::mapped( 'automation', (int) $row['automation_id'] ) : null,
				'step_key'       => $row['step_key'],
				'contact_id'     => $contact,
				'status'         => $status,
				'claim'          => 'legacy',
				'error'          => 'skipped' === $status ? __( 'Not sent before the update to the new AI Marketing.', 'chatbot' ) : $row['error'],
				'queued_at'      => (string) $row['queued_at'],
				'sent_at'        => $row['sent_at'],
				'first_open_at'  => $row['first_open_at'],
				'opens'          => (int) $row['opens'],
				'first_click_at' => $row['first_click_at'],
				'clicks'         => (int) $row['clicks'],
				'legacy_id'      => (int) $row['id'],
			)
		);
		return self::map_insert( 'message', (int) $row['id'] );
	}

	/**
	 * A timeline event.
	 *
	 * @param array $row Old row.
	 * @return string
	 */
	private static function copy_event( array $row ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'events' ),
			array(
				'contact_id' => (int) self::mapped( 'contact', (int) $row['contact_id'] ),
				'message_id' => $row['message_id'] ? self::mapped( 'message', (int) $row['message_id'] ) : null,
				'type'       => substr( (string) $row['type'], 0, 30 ),
				'data'       => $row['data'],
				'legacy_id'  => (int) $row['id'],
				'created_at' => (string) $row['created_at'],
			)
		);
		return self::map_insert( 'event', (int) $row['id'] );
	}

	/* ── Pure mapping rules (unit tested). ── */

	/**
	 * Old contact status => new status and lifecycle.
	 * "customer" was mailable in the old module and stays mailable.
	 *
	 * @param string $old Old status.
	 * @return array{status:string,lifecycle:string}
	 */
	public static function map_status( $old ) {
		switch ( $old ) {
			case 'unsubscribed':
			case 'bounced':
			case 'complained':
			case 'pending':
				return array(
					'status'    => $old,
					'lifecycle' => '',
				);
			case 'customer':
				return array(
					'status'    => 'subscribed',
					'lifecycle' => 'customer',
				);
			default:
				return array(
					'status'    => 'subscribed',
					'lifecycle' => '',
				);
		}
	}

	/**
	 * Status after merging an old contact into an existing one: an opt-out
	 * (unsubscribed, bounced, complained) on either side wins.
	 *
	 * @param string $current Existing status.
	 * @param string $old     Old (mapped) status.
	 * @return string
	 */
	public static function merge_status( $current, $old ) {
		foreach ( array( 'complained', 'unsubscribed', 'bounced' ) as $opt_out ) {
			if ( $opt_out === $current || $opt_out === $old ) {
				return $opt_out;
			}
		}
		return $current;
	}

	/**
	 * Old campaign status => new: nothing resumes sending by itself.
	 *
	 * @param string $old Old status.
	 * @return string
	 */
	public static function map_campaign_status( $old ) {
		return in_array( $old, array( 'scheduled', 'sending' ), true ) ? 'paused' : ( in_array( $old, array( 'draft', 'sent', 'paused', 'failed' ), true ) ? $old : 'draft' );
	}

	/**
	 * Old message status => new: queued emails are not sent by the copy.
	 *
	 * @param string $old Old status.
	 * @return string
	 */
	public static function map_message_status( $old ) {
		return in_array( $old, array( 'sent', 'failed' ), true ) ? $old : 'skipped';
	}

	/**
	 * Replace old list/tag ids in a campaign audience with new ids.
	 * Ids that were not copied are dropped.
	 *
	 * @param array    $settings Audience or settings array.
	 * @param callable $map      Old group id => new id (0 when unknown).
	 * @return array
	 */
	public static function remap_audience( array $settings, $map ) {
		foreach ( array( 'lists', 'tags', 'list_ids', 'tag_ids', 'exclude_lists', 'exclude_tags' ) as $key ) {
			if ( ! isset( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
				continue;
			}
			$ids = array();
			foreach ( $settings[ $key ] as $old ) {
				$new = (int) call_user_func( $map, (int) $old );
				if ( $new > 0 ) {
					$ids[] = $new;
				}
			}
			$settings[ $key ] = array_values( array_unique( $ids ) );
		}
		return $settings;
	}

	/* ── Map table. ── */

	/**
	 * New id of a copied old row, or 0.
	 *
	 * @param string $kind      Kind.
	 * @param int    $legacy_id Old id.
	 * @return int
	 */
	public static function mapped( $kind, $legacy_id ) {
		global $wpdb;
		$table = Schema::table( 'legacy_map' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT new_id FROM `{$table}` WHERE kind = %s AND legacy_id = %d", $kind, (int) $legacy_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * New id of an old list or tag (callback for remap_audience()).
	 *
	 * @param int $legacy_id Old group id.
	 * @return int
	 */
	public static function mapped_group( $legacy_id ) {
		return self::mapped( 'group', $legacy_id );
	}

	/**
	 * Record a mapping.
	 *
	 * @param string $kind      Kind.
	 * @param int    $legacy_id Old id.
	 * @param int    $new_id    New id.
	 * @return void
	 */
	private static function map( $kind, $legacy_id, $new_id ) {
		global $wpdb;
		$table = Schema::table( 'legacy_map' );
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$table}` (kind, legacy_id, new_id) VALUES (%s, %d, %d)", $kind, (int) $legacy_id, (int) $new_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Map the row just inserted (or report it skipped when the insert failed).
	 *
	 * @param string $kind      Kind.
	 * @param int    $legacy_id Old id.
	 * @return string
	 */
	private static function map_insert( $kind, $legacy_id ) {
		global $wpdb;
		$new_id = (int) $wpdb->insert_id;
		if ( $new_id <= 0 ) {
			return 'skipped';
		}
		self::map( $kind, $legacy_id, $new_id );
		return 'copied';
	}

	/* ── Report. ── */

	/**
	 * The copy report.
	 *
	 * @return array{status:string,job_id:int,started_at:string,finished_at:string,counts:array}
	 */
	public static function report() {
		$stored = get_option( self::REPORT_OPTION, array() );
		return array_merge(
			array(
				'status'      => 'none',
				'job_id'      => 0,
				'started_at'  => '',
				'finished_at' => '',
				'counts'      => array(),
			),
			is_array( $stored ) ? $stored : array()
		);
	}

	/**
	 * Report with the old tables' row counts, for the screen.
	 *
	 * @return array
	 */
	public static function overview() {
		$report           = self::report();
		$report['legacy'] = array( 'present' => Legacy_Tables::present() );
		foreach ( self::SOURCE as $kind => $table ) {
			$report['legacy'][ $kind ] = Legacy_Tables::count( $table );
		}
		$report['legacy']['automation_runs'] = Legacy_Tables::count( 'automation_runs' );
		$report['carried']                   = self::carried();
		return $report;
	}

	/**
	 * How many old rows of each kind are now in the new tables (copied or merged, over every run).
	 *
	 * @return array<string,int>
	 */
	public static function carried() {
		global $wpdb;
		$map  = Schema::table( 'legacy_map' );
		$rows = (array) $wpdb->get_results( "SELECT kind, COUNT(*) AS n FROM `{$map}` GROUP BY kind", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		$out  = array();
		foreach ( $rows as $row ) {
			$out[ (string) $row['kind'] ] = (int) $row['n'];
		}
		$members           = Schema::table( 'memberships' );
		$out['membership'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$members}` WHERE origin = 'migration'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
		return $out;
	}

	/**
	 * Save the report.
	 *
	 * @param array $report Report.
	 * @return void
	 */
	private static function save_report( array $report ) {
		update_option( self::REPORT_OPTION, $report, false );
	}

	/**
	 * Add a batch's counts to the report.
	 *
	 * @param string $kind   Kind.
	 * @param array  $result Batch result.
	 * @return void
	 */
	private static function add_counts( $kind, array $result ) {
		$report = self::report();
		$counts = isset( $report['counts'][ $kind ] ) ? (array) $report['counts'][ $kind ] : array();
		foreach ( array( 'copied', 'merged', 'skipped' ) as $key ) {
			$counts[ $key ] = ( isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0 ) + (int) $result[ $key ];
		}
		$report['counts'][ $kind ] = $counts;
		self::save_report( $report );
	}

	/* ── Helpers. ── */

	/**
	 * Decode a JSON column to an array.
	 *
	 * @param mixed $value Column value.
	 * @return array
	 */
	private static function json( $value ) {
		$decoded = is_string( $value ) ? json_decode( $value, true ) : null;
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * A new 32-character contact token.
	 *
	 * @return string
	 */
	private static function token() {
		return wp_generate_password( 32, false, false );
	}
}
