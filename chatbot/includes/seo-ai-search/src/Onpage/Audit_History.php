<?php
/**
 * Page Audit history (F4): every audit is kept with its keyword, score and
 * check results, so a page's score can be followed over time and Results
 * can show the score before and after a fix. Kept for the retention set in
 * Settings (audit_history_months, default 12).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Onpage;

use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\SeoAiSearch\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Audit rows.
 */
final class Audit_History {

	const TRIGGERS = array( 'publish', 'manual', 'scan', 'scheduled', 'fix', 'refresh' );

	/**
	 * Hook the daily clean-up.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_seo/daily', array( __CLASS__, 'prune' ) );
	}

	/**
	 * Record an audit. Saving the same page again within ten minutes with
	 * the same keyword and score (autosaves, quick edits) updates nothing.
	 *
	 * @param int    $post_id Post id.
	 * @param string $keyword Keyword audited for.
	 * @param int    $score   Score.
	 * @param array  $checks  Checklist (id, status, …).
	 * @param string $trigger Trigger.
	 * @return int Row id (0 when skipped).
	 */
	public static function record( $post_id, $keyword, $score, array $checks, $trigger ) {
		global $wpdb;
		$table   = Schema::table( Schema::AUDITS );
		$trigger = in_array( $trigger, self::TRIGGERS, true ) ? $trigger : 'publish';
		$keyword = substr( sanitize_text_field( (string) $keyword ), 0, 191 );
		// Saves, refreshes and fixes in quick succession add one row; runs by hand, scans and schedules always add one.
		if ( in_array( $trigger, array( 'publish', 'refresh', 'fix' ), true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
			$last = $wpdb->get_row( $wpdb->prepare( 'SELECT keyword, score, created_at FROM %i WHERE post_id = %d ORDER BY id DESC LIMIT 1', $table, $post_id ), ARRAY_A );
			if ( $last && $last['keyword'] === $keyword && (int) $last['score'] === (int) $score && strtotime( $last['created_at'] . ' UTC' ) > time() - 10 * MINUTE_IN_SECONDS ) {
				return 0;
			}
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$previous = $wpdb->get_var( $wpdb->prepare( 'SELECT score FROM %i WHERE post_id = %d ORDER BY id DESC LIMIT 1', $table, $post_id ) );
		$compact  = array();
		foreach ( $checks as $check ) {
			if ( isset( $check['id'], $check['status'] ) ) {
				$compact[ (string) $check['id'] ] = (string) $check['status'];
			}
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table, write.
		$wpdb->insert(
			$table,
			array(
				'post_id'      => (int) $post_id,
				'keyword'      => $keyword,
				'score'        => max( 0, min( 100, (int) $score ) ),
				'checks'       => wp_json_encode( $compact ),
				'trigger_kind' => $trigger,
				'created_at'   => current_time( 'mysql', true ),
			)
		);
		$id = (int) $wpdb->insert_id;
		/**
		 * An audit was recorded (the Pro module alerts on score drops).
		 *
		 * @param int      $post_id  Post id.
		 * @param int      $score    New score.
		 * @param int|null $previous Previous score, or null for the first audit.
		 * @param string   $trigger  Trigger.
		 */
		do_action( 'qcld_wpbot_seo/audit_recorded', (int) $post_id, (int) $score, null === $previous ? null : (int) $previous, $trigger );
		return $id;
	}

	/**
	 * History, newest first (a page, or every page).
	 *
	 * @param int $post_id Post id (0 for all).
	 * @param int $limit   Rows.
	 * @param int $offset  Offset.
	 * @return array[] id, post_id, title, keyword, score, trigger, created_at, checks.
	 */
	public static function query( $post_id = 0, $limit = 50, $offset = 0 ) {
		global $wpdb;
		$table = Schema::table( Schema::AUDITS );
		$limit = max( 1, min( 200, (int) $limit ) );
		if ( $post_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE post_id = %d ORDER BY id DESC LIMIT %d OFFSET %d', $table, $post_id, $limit, max( 0, (int) $offset ) ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d', $table, $limit, max( 0, (int) $offset ) ), ARRAY_A );
		}
		return array_map( array( __CLASS__, 'shape' ), (array) $rows );
	}

	/**
	 * One audit.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Schema::table( Schema::AUDITS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, (int) $id ), ARRAY_A );
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Score trend of a page (oldest first, at most one point a day).
	 *
	 * @param int $post_id Post id.
	 * @return array[] day, score.
	 */
	public static function trend( $post_id ) {
		global $wpdb;
		$table = Schema::table( Schema::AUDITS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT DATE(created_at) AS day, score FROM %i WHERE post_id = %d ORDER BY id DESC LIMIT 400', $table, $post_id ), ARRAY_A );
		$days = array();
		foreach ( (array) $rows as $row ) {
			if ( ! isset( $days[ $row['day'] ] ) ) {
				$days[ $row['day'] ] = (int) $row['score'];
			}
		}
		ksort( $days );
		$out = array();
		foreach ( array_slice( $days, -90, null, true ) as $day => $score ) {
			$out[] = array(
				'day'   => (string) $day,
				'score' => $score,
			);
		}
		return $out;
	}

	/**
	 * Score of a page at or before a moment (for before/after in Results).
	 *
	 * @param int    $post_id Post id.
	 * @param string $before  Y-m-d H:i:s (UTC).
	 * @return int|null
	 */
	public static function score_before( $post_id, $before ) {
		global $wpdb;
		$table = Schema::table( Schema::AUDITS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$score = $wpdb->get_var( $wpdb->prepare( 'SELECT score FROM %i WHERE post_id = %d AND created_at <= %s ORDER BY id DESC LIMIT 1', $table, $post_id, $before ) );
		return null === $score ? null : (int) $score;
	}

	/**
	 * Delete rows older than the retention.
	 *
	 * @return int Rows deleted.
	 */
	public static function prune() {
		global $wpdb;
		$table  = Schema::table( Schema::AUDITS );
		$months = max( 1, (int) Settings::get( 'audit_history_months' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', $table, gmdate( 'Y-m-d H:i:s', strtotime( '-' . $months . ' months' ) ) ) );
	}

	/**
	 * Row for callers.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	private static function shape( array $row ) {
		$checks = json_decode( (string) $row['checks'], true );
		return array(
			'id'         => (int) $row['id'],
			'post_id'    => (int) $row['post_id'],
			'title'      => get_the_title( (int) $row['post_id'] ),
			'keyword'    => (string) $row['keyword'],
			'score'      => (int) $row['score'],
			'trigger'    => (string) $row['trigger_kind'],
			'created_at' => (string) $row['created_at'],
			'checks'     => is_array( $checks ) ? $checks : array(),
		);
	}
}
