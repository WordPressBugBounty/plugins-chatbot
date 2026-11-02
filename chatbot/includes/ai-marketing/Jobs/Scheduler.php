<?php
/**
 * WP-Cron schedule and single-runner lock for the sending tick.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Jobs;

use QuantumCloud\WPBot\CRM\Domain\Schema;
use QuantumCloud\WPBot\CRM\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `wpbot_crm_minute` cron interval and the `wpbot_crm/tick` event, and runs
 * Tick under an option-based mutex shared by cron and the admin "process now" route.
 *
 * The tick is only scheduled while there is work: repositories call wake() when they queue
 * work, and the tick unschedules itself once nothing is pending. Housekeeping runs daily.
 */
final class Scheduler {

	const HOOK              = 'wpbot_crm/tick';
	const HOUSEKEEPING_HOOK = 'wpbot_crm/housekeeping';
	const SCHEDULE          = 'wpbot_crm_minute';
	const LOCK              = 'wpbot_crm_tick_lock';

	/**
	 * Result of the last tick run in this request.
	 *
	 * @var array
	 */
	private static $last_result = array();

	/**
	 * Register hooks and make sure the daily housekeeping event is scheduled.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- interval >= 60s.
		add_action( self::HOOK, array( __CLASS__, 'cron' ) );
		add_action( self::HOUSEKEEPING_HOOK, array( __CLASS__, 'housekeeping' ) );
		if ( ! wp_next_scheduled( self::HOUSEKEEPING_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOUSEKEEPING_HOOK );
		}
	}

	/**
	 * Schedule the tick if it is not scheduled. Called whenever work is queued.
	 */
	public static function wake() {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- interval >= 60s.
		wp_schedule_event( time() + MINUTE_IN_SECONDS, self::SCHEDULE, self::HOOK );
	}

	/**
	 * Whether any campaign, automation run or queued message still needs the tick.
	 *
	 * @return bool
	 */
	public static function has_pending() {
		global $wpdb;
		$campaigns = Schema::table( 'campaigns' );
		$runs      = Schema::table( 'automation_runs' );
		$messages  = Schema::table( 'messages' );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_var( "SELECT 1 FROM {$campaigns} WHERE status IN ('scheduled','sending') LIMIT 1" )
			|| $wpdb->get_var( "SELECT 1 FROM {$runs} WHERE status = 'waiting' LIMIT 1" )
			|| $wpdb->get_var( "SELECT 1 FROM {$messages} WHERE status = 'queued' AND campaign_id IS NULL LIMIT 1" );
		// phpcs:enable
	}

	/**
	 * Add the CRM interval: `batch_interval` seconds from settings, at least 60.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function cron_schedules( $schedules ) {
		$interval                    = max( MINUTE_IN_SECONDS, (int) Settings::get( 'batch_interval', 60 ) );
		$schedules[ self::SCHEDULE ] = array(
			'interval' => $interval,
			/* translators: %d: seconds. */
			'display'  => sprintf( __( 'WPBot CRM queue (every %d seconds)', 'chatbot' ), $interval ),
		);
		return $schedules;
	}

	/**
	 * Cron callback.
	 */
	public static function cron() {
		if ( self::run_now( 20 ) && ! self::has_pending() ) {
			self::unschedule();
		}
	}

	/**
	 * Daily cron callback.
	 */
	public static function housekeeping() {
		( new Tick() )->housekeeping();
	}

	/**
	 * Runs one tick under the shared lock. Returns false if another tick holds the lock.
	 *
	 * @param int $max_seconds Wall-time budget for sending.
	 * @return bool
	 */
	public static function run_now( $max_seconds = 20 ) {
		$max_seconds = max( 1, (int) $max_seconds );
		if ( ! self::acquire( $max_seconds ) ) {
			self::$last_result = array( 'locked' => true );
			return false;
		}
		try {
			self::$last_result = ( new Tick() )->run( $max_seconds );
		} finally {
			self::release();
		}
		return true;
	}

	/**
	 * Stats from the last run_now() in this request (processed, sent, failed, skipped, …).
	 *
	 * @return array
	 */
	public static function last_result() {
		return self::$last_result;
	}

	/**
	 * Remove the tick event (queue drained, or plugin deactivation).
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Take the lock. A lock older than its expiry + 60 s (about 2 minutes) is taken over.
	 *
	 * @param int $max_seconds Expected run time.
	 * @return bool
	 */
	private static function acquire( $max_seconds ) {
		global $wpdb;
		$expires = time() + max( MINUTE_IN_SECONDS, $max_seconds + 30 );
		if ( add_option( self::LOCK, $expires, '', 'no' ) ) {
			return true;
		}
		$current = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $current > 0 && $current + MINUTE_IN_SECONDS > time() ) {
			return false;
		}
		// Compare-and-swap so two processes cannot both take over a stale lock.
		$taken = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->options,
			array( 'option_value' => (string) $expires ),
			array(
				'option_name'  => self::LOCK,
				'option_value' => (string) $current,
			)
		);
		wp_cache_delete( self::LOCK, 'options' );
		if ( 0 === $current && ! $taken ) {
			// Row vanished between the two queries: try a fresh add.
			return add_option( self::LOCK, $expires, '', 'no' );
		}
		return (bool) $taken;
	}

	/**
	 * Release the lock.
	 */
	private static function release() {
		delete_option( self::LOCK );
	}
}
