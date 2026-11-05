<?php
/**
 * WP-Cron runner for background and bulk jobs.
 *
 * Runs every five minutes and right after a job is queued. Jobs that a
 * browser screen is driving (seen in the last 20 seconds) are left to the
 * browser runner. Works when WP-Cron is late: the browser runner and the
 * Resume button keep jobs moving.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Jobs;

use QuantumCloud\WPBotCore\AI\Result_Cache;
use QuantumCloud\WPBotCore\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Background runner on WP-Cron.
 */
final class Cron_Runner {

	const HOOK     = 'qcld_wpbot_core_run_jobs';
	const SCHEDULE = 'qcld_wpbot_core_five_minutes';

	/**
	 * Seconds after which a browser screen is considered gone.
	 */
	const BROWSER_GRACE = 20;

	/**
	 * Hook the runner into WordPress.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- Five minutes is the intended interval.
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'ensure_scheduled' ), 20 );
	}

	/**
	 * Add the five-minute schedule.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function add_schedule( $schedules ) {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every five minutes (WPBot jobs)', 'chatbot' ),
		);
		return $schedules;
	}

	/**
	 * Make sure the recurring event exists.
	 *
	 * @return void
	 */
	public static function ensure_scheduled() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::SCHEDULE, self::HOOK );
		}
	}

	/**
	 * Ask WP-Cron to run soon (after a job was queued or resumed).
	 *
	 * @return void
	 */
	public static function kick() {
		if ( ! wp_next_scheduled( self::HOOK, array( 'kick' ) ) ) {
			wp_schedule_single_event( time(), self::HOOK, array( 'kick' ) );
		}
	}

	/**
	 * Work through active jobs within the time budget.
	 *
	 * @return void
	 */
	public static function run() {
		$budget   = (int) Settings::get( 'cron_time_budget' );
		$deadline = microtime( true ) + max( 5, $budget );

		foreach ( Job_Store::active_ids() as $job_id ) {
			if ( microtime( true ) >= $deadline ) {
				break;
			}
			$job = Job_Store::get( $job_id );
			if ( ! $job ) {
				continue;
			}
			if ( ! empty( $job['browser_seen_at'] ) && strtotime( $job['browser_seen_at'] . ' UTC' ) > time() - self::BROWSER_GRACE ) {
				continue;
			}
			Job_Runner::run_for( $job_id, (int) ceil( $deadline - microtime( true ) ) );
		}

		if ( false === get_transient( 'qcld_wpbot_core_daily_sweep' ) ) {
			set_transient( 'qcld_wpbot_core_daily_sweep', 1, DAY_IN_SECONDS );
			Result_Cache::prune();
		}

		// More work left: ask for another run soon rather than in five minutes.
		if ( array() !== Job_Store::active_ids( 1 ) ) {
			self::kick();
		}
	}

	/**
	 * Remove scheduled events (uninstall).
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::HOOK, array( 'kick' ) );
	}
}
