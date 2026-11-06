<?php
/**
 * Site search log: counts what visitors search for on the site, per day
 * and term, and how often a search found nothing. Aggregate only: no IP,
 * user, session or time of day is stored, terms are lower-cased, cut to
 * 100 characters and passed through the redactor (an email typed into the
 * search box is never stored).
 *
 * This is AI Writer's only front-end code. It runs on the main search
 * query of the public site, skips logged-in editors and obvious bots, and
 * can be switched off in Settings > Topic Finder.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Topics;

use QuantumCloud\AiWriter\Settings;
use QuantumCloud\AiWriter\Storage\Schema;
use QuantumCloud\WPBotCore\Privacy\Redactor;

defined( 'ABSPATH' ) || exit;

/**
 * Search logger.
 */
final class Search_Logger {

	/**
	 * Days of log kept.
	 */
	const KEEP_DAYS = 180;

	/**
	 * Hook the logger.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_log' ), 99 );
	}

	/**
	 * Log the current search page view.
	 *
	 * @return void
	 */
	public static function maybe_log() {
		global $wp_query;
		if ( is_admin() || ! is_search() || ! $wp_query || ! $wp_query->is_main_query() || is_paged() || ! Settings::get( 'search_logger' ) ) {
			return;
		}
		if ( current_user_can( 'edit_posts' ) || self::is_bot() ) {
			return;
		}
		self::record( (string) get_search_query( false ), 0 === (int) $wp_query->found_posts );
	}

	/**
	 * Count one search.
	 *
	 * @param string $term     Raw term.
	 * @param bool   $no_match Whether nothing was found.
	 * @return bool Whether it was counted.
	 */
	public static function record( $term, $no_match ) {
		global $wpdb;
		$term = self::normalise( $term );
		if ( '' === $term ) {
			return false;
		}
		$table = Schema::table( Schema::SEARCH_LOG );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
		return false !== $wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (day, term_hash, term, searches, zero_results) VALUES (%s, %s, %s, 1, %d) ON DUPLICATE KEY UPDATE searches = searches + 1, zero_results = zero_results + %d", current_time( 'Y-m-d' ), md5( $term ), $term, $no_match ? 1 : 0, $no_match ? 1 : 0 ) );
	}

	/**
	 * Clean a term: plain text, lower case, redacted, 2–100 characters.
	 *
	 * @param string $term Term.
	 * @return string '' when not worth keeping.
	 */
	public static function normalise( $term ) {
		$term = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $term ) ) );
		$term = Redactor::redact( $term );
		$term = function_exists( 'mb_strtolower' ) ? mb_strtolower( $term, 'UTF-8' ) : strtolower( $term );
		$term = function_exists( 'mb_substr' ) ? mb_substr( $term, 0, 100, 'UTF-8' ) : substr( $term, 0, 100 );
		// Nothing left but markers or punctuation.
		$bare = trim( (string) preg_replace( '/\[(email|phone|card|address|postcode|link|name)\]|[^\p{L}\p{N}]+/u', '', $term ) );
		return ( function_exists( 'mb_strlen' ) ? mb_strlen( $bare ) : strlen( $bare ) ) >= 2 ? $term : '';
	}

	/**
	 * Most searched terms in a period.
	 *
	 * @param int $days  Days back from today.
	 * @param int $limit Max terms.
	 * @param int $skip  Days to skip at the end (for "the period before").
	 * @return array[] term, searches, zero_results.
	 */
	public static function top( $days = 28, $limit = 50, $skip = 0 ) {
		global $wpdb;
		$table = Schema::table( Schema::SEARCH_LOG );
		$end   = gmdate( 'Y-m-d', current_time( 'timestamp' ) - $skip * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Local day boundaries, as stored.
		$start = gmdate( 'Y-m-d', current_time( 'timestamp' ) - ( $skip + $days - 1 ) * DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Local day boundaries, as stored.
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT term, SUM(searches) AS searches, SUM(zero_results) AS zero_results FROM {$table} WHERE day BETWEEN %s AND %s GROUP BY term_hash, term ORDER BY searches DESC LIMIT %d", $start, $end, max( 1, (int) $limit ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
		return array_map(
			static function ( $row ) {
				return array(
					'term'         => (string) $row['term'],
					'searches'     => (int) $row['searches'],
					'zero_results' => (int) $row['zero_results'],
				);
			},
			(array) $rows
		);
	}

	/**
	 * Delete log rows older than KEEP_DAYS.
	 *
	 * @return void
	 */
	public static function prune() {
		global $wpdb;
		$table = Schema::table( Schema::SEARCH_LOG );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE day < %s", gmdate( 'Y-m-d', time() - self::KEEP_DAYS * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table; name from code.
	}

	/**
	 * Obvious crawlers (their searches are not visitor demand).
	 *
	 * @return bool
	 */
	private static function is_bot() {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
		return '' === $agent || (bool) preg_match( '/bot|crawl|spider|slurp|preview|fetch|monitor|curl|wget|python|headless/', $agent );
	}
}
