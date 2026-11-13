<?php
/**
 * Function qcld_wpbot_seo_get_dashboard_data(): headline numbers for the Growth Dashboard.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Api;

use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\WPBotCore\Connections\Analytics;
use QuantumCloud\WPBotCore\Connections\Google;

defined( 'ABSPATH' ) || exit;

/**
 * Builds dashboard data.
 */
final class Dashboard_Data {

	/**
	 * Dashboard numbers.
	 *
	 * @param array $args days.
	 * @return array
	 */
	public static function get( array $args = array() ) {
		$days   = isset( $args['days'] ) ? (int) $args['days'] : 28;
		$period = Period::last( $days );
		$now    = Data_Store::totals( $period['start'], $period['end'] );
		$before = Data_Store::totals( $period['prev_start'], $period['prev_end'] );
		$data   = array(
			'period'              => $period,
			'totals'              => array_merge(
				$now,
				array(
					'trend' => array(
						'clicks'      => self::change( $before['clicks'], $now['clicks'] ),
						'impressions' => self::change( $before['impressions'], $now['impressions'] ),
						'ctr'         => self::change( $before['ctr'], $now['ctr'] ),
						'position'    => round( $now['position'] - $before['position'], 1 ),
					),
				)
			),
			'movers'              => self::movers( $period ),
			'top_actions'         => array_slice( \QuantumCloud\SeoAiSearch\Opportunities\Opportunities::query( array( 'limit' => 5 ) )['items'], 0, 5 ),
			'critical_issues'     => self::critical_issues(),
			'health_score'        => self::health_score(),
			'results'             => \QuantumCloud\SeoAiSearch\Results\Results::summary( 28 ),
			/**
			 * Latest AI visibility score (0–100), from the Pro module; null without one.
			 *
			 * @param int|null $score Score.
			 */
			'ai_visibility_score' => apply_filters( 'qcld_wpbot_seo/ai_visibility_score', null ),
			'connections'         => array(
				'gsc'          => Google::connected() ? Google::status()['status'] : 'not_connected',
				'ga'           => Analytics::status()['status'],
				'pagespeed'    => '' !== \QuantumCloud\SeoAiSearch\Secrets::get( 'pagespeed' ) ? 'connected' : 'not_connected',
				'keyword_data' => \QuantumCloud\SeoAiSearch\Keywords\Volumes::connected() ? 'connected' : 'not_connected',
			),
		);
		/**
		 * Dashboard data; later milestones and the Pro module add their parts.
		 *
		 * @param array $data Data.
		 * @param array $args Arguments.
		 */
		return (array) apply_filters( 'qcld_wpbot_seo/dashboard_data', $data, $args );
	}

	/**
	 * Health score from the latest finished technical scan, or null.
	 *
	 * @return int|null
	 */
	public static function health_score() {
		$scan = \QuantumCloud\SeoAiSearch\Technical\Scan::latest( 'done' );
		return $scan ? $scan['score'] : null;
	}

	/**
	 * High-severity issues in the latest finished scan.
	 *
	 * @return int
	 */
	public static function critical_issues() {
		global $wpdb;
		$scan = \QuantumCloud\SeoAiSearch\Technical\Scan::latest( 'done' );
		if ( ! $scan ) {
			return 0;
		}
		$table = \QuantumCloud\SeoAiSearch\Storage\Schema::table( \QuantumCloud\SeoAiSearch\Storage\Schema::ISSUES );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE scan_id = %d AND severity = 'high'", $table, $scan['id'] ) );
	}

	/**
	 * Pages with the biggest change in clicks (up and down).
	 *
	 * @param array $period Period.
	 * @return array List of url, post_id, title, clicks, before, change.
	 */
	public static function movers( array $period ) {
		$now    = Data_Store::period_rows( 'page', $period['start'], $period['end'], 1, 500 );
		$before = array();
		foreach ( Data_Store::period_rows( 'page', $period['prev_start'], $period['prev_end'], 1, 500 ) as $row ) {
			$before[ $row['url_id'] ] = $row['clicks'];
		}
		$moves = array();
		foreach ( $now as $row ) {
			$was = isset( $before[ $row['url_id'] ] ) ? $before[ $row['url_id'] ] : 0;
			if ( max( $was, $row['clicks'] ) < 10 ) {
				continue;
			}
			$post_id = Data_Store::post_for_url( $row['url_id'] );
			$moves[] = array(
				'url'     => esc_url_raw( $row['url'], array( 'http', 'https' ) ),
				'post_id' => $post_id,
				'title'   => $post_id ? get_the_title( $post_id ) : '',
				'clicks'  => $row['clicks'],
				'before'  => $was,
				'change'  => $row['clicks'] - $was,
			);
		}
		usort(
			$moves,
			static function ( $a, $b ) {
				return abs( $b['change'] ) <=> abs( $a['change'] );
			}
		);
		return array_slice( $moves, 0, 5 );
	}

	/**
	 * Relative change, rounded (0.25 = +25%); null when there was nothing before.
	 *
	 * @param float $before Before.
	 * @param float $after  After.
	 * @return float|null
	 */
	public static function change( $before, $after ) {
		if ( (float) $before <= 0 ) {
			return null;
		}
		return round( ( (float) $after - (float) $before ) / (float) $before, 3 );
	}
}
