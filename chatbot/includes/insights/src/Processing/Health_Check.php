<?php
/**
 * Health information for the Settings screen: storage, background jobs,
 * WP-Cron and ingest errors. The storage check makes a loopback request,
 * so its result is cached and refreshed once a day.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Processing;

use QuantumCloud\Insights\Module;
use QuantumCloud\Insights\Settings;
use QuantumCloud\Insights\Storage\Heatmap_Repository;

defined( 'ABSPATH' ) || exit;

final class Health_Check {

	const STORE_TRANSIENT = 'qcin_health';
	const RUNS_OPTION     = 'qcin_job_runs';

	/**
	 * Count one rejected ingest request (hourly buckets, kept 2 days).
	 */
	public static function count_ingest_error() {
		$key = 'qcin_err_' . gmdate( 'YmdH' );
		if ( wp_using_ext_object_cache() ) {
			wp_cache_add( $key, 0, 'qcin', 2 * DAY_IN_SECONDS );
			wp_cache_incr( $key, 1, 'qcin' );
			return;
		}
		set_transient( $key, (int) get_transient( $key ) + 1, 2 * DAY_IN_SECONDS );
	}

	/**
	 * Rejected ingest requests in the last 24 hours.
	 *
	 * @return int
	 */
	public static function ingest_errors() {
		$total = 0;
		for ( $h = 0; $h < 24; $h++ ) {
			$key    = 'qcin_err_' . gmdate( 'YmdH', time() - $h * HOUR_IN_SECONDS );
			$total += (int) ( wp_using_ext_object_cache() ? wp_cache_get( $key, 'qcin' ) : get_transient( $key ) );
		}
		return $total;
	}

	/**
	 * Remember when a job last finished.
	 *
	 * @param string $job Job hook.
	 */
	public static function record_run( $job ) {
		$runs         = (array) get_option( self::RUNS_OPTION, array() );
		$runs[ $job ] = time();
		update_option( self::RUNS_OPTION, $runs, false );
	}

	/**
	 * Storage health (writable, protected), cached.
	 *
	 * @param bool $refresh Run the check now.
	 * @return array<string,mixed>
	 */
	public static function store( $refresh = false ) {
		$health = get_transient( self::STORE_TRANSIENT );
		if ( false === $health || $refresh ) {
			$health = Module::instance()->store()->health();
			set_transient( self::STORE_TRANSIENT, $health, DAY_IN_SECONDS );
		}
		return (array) $health;
	}

	/**
	 * Daily job: refresh the cached storage check.
	 */
	public function run() {
		self::store( true );
		self::record_run( Scheduler::HEALTH );
	}

	/**
	 * Everything the Settings screen shows.
	 *
	 * @param bool $refresh Re-run the storage check.
	 * @return array<string,mixed>
	 */
	public static function report( $refresh = false ) {
		$runs = (array) get_option( self::RUNS_OPTION, array() );
		$jobs = array();
		/**
		 * Cron hooks shown in the health report with their last and next run.
		 * Features add their own jobs and call Health_Check::record_run( $hook ) when they run.
		 *
		 * @param string[] $jobs Cron hook names.
		 */
		$hooks = (array) apply_filters( 'qcin/health_jobs', array( Scheduler::FINALIZE, Scheduler::AGGREGATE, Scheduler::CLEANUP ) );
		foreach ( array_unique( array_map( 'strval', $hooks ) ) as $job ) {
			$next         = wp_next_scheduled( $job );
			$jobs[ $job ] = array(
				'last' => isset( $runs[ $job ] ) ? gmdate( 'c', (int) $runs[ $job ] ) : null,
				'next' => $next ? gmdate( 'c', (int) $next ) : null,
			);
		}
		// Jobs are overdue when the 5-minute job has not run for an hour while recording is on.
		$last_finalize = (int) ( $runs[ Scheduler::FINALIZE ] ?? 0 );
		$next_finalize = (int) wp_next_scheduled( Scheduler::FINALIZE );
		$overdue       = Settings::get( 'enabled' ) && $next_finalize && $next_finalize < time() - HOUR_IN_SECONDS && $last_finalize < time() - HOUR_IN_SECONDS;

		return array(
			'store'         => self::store( $refresh ),
			'jobs'          => $jobs,
			'cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'jobs_overdue'  => (bool) $overdue,
			'ingest_errors' => self::ingest_errors(),
			'heatmap'       => ( new Heatmap_Repository() )->counts(),
		);
	}
}
