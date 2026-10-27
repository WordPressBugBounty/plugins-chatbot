<?php
/**
 * Background jobs on WP-Cron: closing idle sessions (with quick-back
 * detection), heatmap and scroll aggregation, cleanup and a daily health
 * check. Each job takes a lock, works in small batches and stops early.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Processing;

use QuantumCloud\Insights\Module;
use QuantumCloud\Insights\Settings;
use QuantumCloud\Insights\Storage\Heatmap_Repository;
use QuantumCloud\Insights\Storage\Session_Repository;
use QuantumCloud\Insights\Storage\Signal_Repository;

defined( 'ABSPATH' ) || exit;

final class Scheduler {

	const FINALIZE  = 'qcin_finalize_sessions';
	const AGGREGATE = 'qcin_aggregate';
	const CLEANUP   = 'qcin_cleanup';
	const MORE      = 'qcin_cleanup_more';
	const HEALTH    = 'qcin_health_check';

	const QUICK_BACK_MS = 5000;
	const TIME_LIMIT    = 20;

	/**
	 * Hooks and schedules.
	 */
	public function register() {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_action( self::FINALIZE, array( $this, 'finalize_sessions' ) );
		add_action( self::AGGREGATE, array( $this, 'aggregate' ) );
		add_action( self::CLEANUP, array( $this, 'cleanup' ) );
		add_action( self::MORE, array( $this, 'cleanup' ) );
		add_action( self::HEALTH, array( new Health_Check(), 'run' ) );
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
	}

	/**
	 * Add a five-minute interval.
	 *
	 * @param array<string,array<string,mixed>> $schedules Schedules.
	 * @return array<string,array<string,mixed>>
	 */
	public function schedules( $schedules ) {
		$schedules['qcin_5min'] = array(
			'interval' => 300,
			'display'  => 'Every 5 minutes (WPBot Insights)',
		);
		return $schedules;
	}

	/**
	 * Make sure every recurring event is scheduled.
	 */
	public function ensure_scheduled() {
		$events = array(
			self::FINALIZE  => array( 60, 'qcin_5min' ),
			self::AGGREGATE => array( 600, 'hourly' ),
			self::CLEANUP   => array( 3600, 'daily' ),
			self::HEALTH    => array( 7200, 'daily' ),
		);
		foreach ( $events as $hook => $when ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time() + $when[0], $when[1], $hook );
			}
		}
	}

	/**
	 * Remove scheduled events (uninstall).
	 */
	public static function unschedule() {
		foreach ( array( self::FINALIZE, self::AGGREGATE, self::CLEANUP, self::MORE, self::HEALTH, \QuantumCloud\Insights\Reports\Reports::HOOK ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Close sessions idle for 30+ minutes, detect quick backs and announce
	 * each finished session.
	 */
	public function finalize_sessions() {
		if ( ! $this->lock( self::FINALIZE ) ) {
			return;
		}
		$repo    = new Session_Repository();
		$signals = new Signal_Repository();
		$before  = gmdate( 'Y-m-d H:i:s', time() - 1800 );
		foreach ( $repo->close_idle( $before, 500 ) as $session_id ) {
			$this->detect_quick_backs( $repo, $signals, $session_id );
			$row = $repo->find_session_by_id( $session_id );
			if ( $row ) {
				/**
				 * A session was closed after 30 minutes without activity.
				 *
				 * @param object $session Session row.
				 */
				do_action( 'qcin/session_finalized', $row );
			}
		}
		Health_Check::record_run( self::FINALIZE );
		$this->unlock( self::FINALIZE );
	}

	/**
	 * A quick back: the visitor opened a page and returned to the previous
	 * page within 5 seconds (A, B, A with B shorter than 5 s).
	 *
	 * @param Session_Repository $repo       Sessions.
	 * @param Signal_Repository  $signals    Signals.
	 * @param int                $session_id Session id.
	 */
	private function detect_quick_backs( Session_Repository $repo, Signal_Repository $signals, $session_id ) {
		$seq = $repo->pageview_sequence( $session_id );
		$now = current_time( 'mysql', true );
		$hit = 0;
		for ( $i = 1, $n = count( $seq ); $i < $n - 1; $i++ ) {
			$prev = $seq[ $i - 1 ];
			$curr = $seq[ $i ];
			$next = $seq[ $i + 1 ];
			if ( (int) $next->url_id === (int) $prev->url_id && (int) $curr->url_id !== (int) $prev->url_id && (int) $curr->duration_ms < self::QUICK_BACK_MS ) {
				$hit |= $signals->insert(
					$session_id,
					(int) $curr->id,
					array(
						array(
							'type'     => 'quickback',
							'ts'       => (int) $curr->duration_ms,
							'selector' => '',
							'data'     => array(),
						),
					),
					array(),
					$now
				);
			}
		}
		$repo->add_flags( $session_id, $hit );
	}

	/**
	 * Fold raw clicks and finished pageviews into the daily aggregates.
	 */
	public function aggregate() {
		if ( ! $this->lock( self::AGGREGATE ) ) {
			return;
		}
		$start = microtime( true );
		$heat  = new Heatmap_Repository();
		do {
			$done = $heat->aggregate_clicks( 5000 );
		} while ( 5000 === $done && $this->has_time( $start ) );

		$before = gmdate( 'Y-m-d H:i:s', time() - 1800 );
		do {
			$done = $heat->aggregate_scroll( $before, 5000 );
		} while ( 5000 === $done && $this->has_time( $start ) );

		Health_Check::record_run( self::AGGREGATE );
		$this->unlock( self::AGGREGATE );
	}

	/**
	 * Recording bytes that count against the storage limit (on this server).
	 *
	 * @param Session_Repository $repo Repository.
	 * @return int
	 */
	public static function used_bytes( Session_Repository $repo ) {
		/**
		 * Bytes counted against the storage limit. A store that moves
		 * recordings off this server reports only what is still here, and
		 * excludes moved sessions with qcin/oldest_query.
		 *
		 * @param int $bytes Total recording bytes.
		 */
		return (int) apply_filters( 'qcin/storage_used_bytes', $repo->total_bytes() );
	}

	/**
	 * Delete sessions past retention, enforce the storage cap, prune raw
	 * clicks (7 days) and daily aggregates (13 months). Reschedules itself
	 * when there is more to delete.
	 */
	public function cleanup() {
		if ( ! $this->lock( self::CLEANUP ) ) {
			return;
		}
		$start = microtime( true );
		$repo  = new Session_Repository();
		$store = Module::instance()->store();
		$heat  = new Heatmap_Repository();
		$more  = false;

		$before = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * (int) Settings::get( 'retention_days' ) );
		$rows   = $repo->expired( $before, 200 );
		foreach ( $rows as $row ) {
			$store->delete_session( $row->uuid );
			$repo->delete_session( (int) $row->id );
		}
		if ( count( $rows ) === 200 ) {
			$more = true;
		}

		$cap   = (int) Settings::get( 'storage_cap_mb' ) * 1048576;
		$guard = 0;
		while ( self::used_bytes( $repo ) > $cap && $guard < 5 && $this->has_time( $start ) ) {
			++$guard;
			$oldest = $repo->oldest( 100 );
			if ( empty( $oldest ) ) {
				break;
			}
			foreach ( $oldest as $row ) {
				$store->delete_session( $row->uuid );
				$repo->delete_session( (int) $row->id );
			}
		}
		if ( self::used_bytes( $repo ) > $cap ) {
			$more = true;
		}

		// Raw clicks are only needed until they are aggregated; keep 7 days.
		$deleted = $heat->prune_clicks( gmdate( 'Y-m-d H:i:s', time() - Heatmap_Repository::raw_days() * DAY_IN_SECONDS ), 5000 );
		if ( 5000 === $deleted ) {
			$more = true;
		}
		$heat->prune_bins( gmdate( 'Y-m-d', time() - 396 * DAY_IN_SECONDS ) );

		Health_Check::record_run( self::CLEANUP );
		$this->unlock( self::CLEANUP );
		if ( $more && ! wp_next_scheduled( self::MORE ) ) {
			wp_schedule_single_event( time() + 120, self::MORE );
		}
	}

	/**
	 * Whether a job still has time and memory left.
	 *
	 * @param float $start Start time.
	 * @return bool
	 */
	private function has_time( $start ) {
		if ( microtime( true ) - $start > self::TIME_LIMIT ) {
			return false;
		}
		$limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		return $limit <= 0 || memory_get_usage() < 0.8 * $limit;
	}

	/**
	 * Take a 10-minute lock.
	 *
	 * @param string $job Job name.
	 * @return bool
	 */
	private function lock( $job ) {
		$key = 'qcin_lock_' . $job;
		if ( get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, 600 );
		return true;
	}

	/**
	 * Release a lock.
	 *
	 * @param string $job Job name.
	 */
	private function unlock( $job ) {
		delete_transient( 'qcin_lock_' . $job );
	}
}
