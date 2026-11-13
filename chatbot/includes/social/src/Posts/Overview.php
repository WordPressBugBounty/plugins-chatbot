<?php
/**
 * Overview tiles on the Posts screen (basic analytics, every edition):
 * published in the last 30 days, scheduled, waiting for you and needing
 * attention, plus published per network. Read from Social's own tables
 * only; no network is asked.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Posts;

use QuantumCloud\Social\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Overview tiles.
 */
final class Overview {

	/**
	 * Days counted for "Published".
	 */
	const DAYS = 30;

	/**
	 * Target states counted as scheduled, needing attention or waiting for the owner.
	 */
	const SCHEDULED = array( 'scheduled', 'queued', 'sending', 'retry' );
	// "unknown"/"reconciling" may already be live: never shown as scheduled, so nobody posts again by hand.
	const ATTENTION = array( 'failed', 'check', 'missed', 'unknown', 'reconciling' );
	const MANUAL    = array( 'manual' );

	/**
	 * Tiles for the current person, or null when they see only some brands
	 * (the tiles count every brand).
	 *
	 * @param int[]|null $brands Brand_Access::allowed().
	 * @return array|null
	 */
	public static function current( $brands ) {
		if ( null !== $brands ) {
			return null;
		}
		global $wpdb;
		$targets = Schema::table( 'post_targets' );
		$since   = gmdate( 'Y-m-d H:i:s', time() - self::DAYS * DAY_IN_SECONDS );
		$rows    = (array) $wpdb->get_results( $wpdb->prepare( "SELECT state, network, COUNT(*) AS n, SUM(CASE WHEN done_at >= %s THEN 1 ELSE 0 END) AS recent FROM {$targets} GROUP BY state, network", $since ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table, counts only.
		return self::tiles( $rows );
	}

	/**
	 * Add up grouped rows (state, network, n, recent).
	 *
	 * @param array $rows Rows.
	 * @return array{published:int, scheduled:int, attention:int, manual:int, networks:array<string,int>}
	 */
	public static function tiles( array $rows ) {
		$tiles = array(
			'published' => 0,
			'scheduled' => 0,
			'attention' => 0,
			'manual'    => 0,
			'networks'  => array(),
		);
		foreach ( $rows as $row ) {
			$row   = (array) $row;
			$state = isset( $row['state'] ) ? (string) $row['state'] : '';
			$n     = isset( $row['n'] ) ? (int) $row['n'] : 0;
			if ( 'done' === $state ) {
				$recent = isset( $row['recent'] ) ? (int) $row['recent'] : 0;
				if ( $recent > 0 ) {
					$network                       = isset( $row['network'] ) ? (string) $row['network'] : '';
					$tiles['published']           += $recent;
					$tiles['networks'][ $network ] = ( isset( $tiles['networks'][ $network ] ) ? $tiles['networks'][ $network ] : 0 ) + $recent;
				}
			} elseif ( in_array( $state, self::SCHEDULED, true ) ) {
				$tiles['scheduled'] += $n;
			} elseif ( in_array( $state, self::ATTENTION, true ) ) {
				$tiles['attention'] += $n;
			} elseif ( in_array( $state, self::MANUAL, true ) ) {
				$tiles['manual'] += $n;
			}
		}
		arsort( $tiles['networks'] );
		return $tiles;
	}
}
