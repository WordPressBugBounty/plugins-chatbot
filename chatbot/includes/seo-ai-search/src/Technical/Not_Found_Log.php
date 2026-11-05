<?php
/**
 * 404 log: pages visitors asked for that do not exist, counted per URL.
 * Stores the path, the referring site and counts only (no IP addresses,
 * no user agents). Kept for the days the owner sets.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Technical;

use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\SeoAiSearch\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * 404 log.
 */
final class Not_Found_Log {

	/**
	 * Hook the front end and the daily pruning.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'template_redirect', array( __CLASS__, 'log' ), 99 );
		add_action( 'qcld_wpbot_seo/daily', array( __CLASS__, 'prune' ) );
	}

	/**
	 * Count a 404 page view.
	 *
	 * @return void
	 */
	public static function log() {
		if ( ! is_404() || ! Settings::get( 'log_404' ) || is_admin() ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$uri = substr( (string) preg_replace( '#^https?://[^/]+#i', '', $uri ), 0, 500 );
		if ( '' === $uri || preg_match( '#\.(css|js|map|png|jpe?g|gif|webp|svg|ico|woff2?|ttf)(\?|$)#i', $uri ) ) {
			return;
		}
		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), PHP_URL_HOST ) : '';
		self::record( $uri, $referrer );
	}

	/**
	 * Add one hit.
	 *
	 * @param string $uri      Path and query.
	 * @param string $referrer Referring host.
	 * @return void
	 */
	public static function record( $uri, $referrer = '' ) {
		global $wpdb;
		$table = Schema::table( Schema::NOT_FOUND );
		$now   = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (url_hash,url,referrer,hits,first_seen,last_seen) VALUES (%s,%s,%s,1,%s,%s) ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), referrer = IF(VALUES(referrer) <> '', VALUES(referrer), referrer)", md5( $uri ), $uri, substr( sanitize_text_field( $referrer ), 0, 191 ), $now, $now ) );
	}

	/**
	 * Logged 404s, most hits first.
	 *
	 * @param int $limit Rows.
	 * @return array
	 */
	public static function query( $limit = 100 ) {
		global $wpdb;
		$table = Schema::table( Schema::NOT_FOUND );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, url, referrer, hits, first_seen, last_seen FROM {$table} ORDER BY hits DESC, last_seen DESC LIMIT %d", max( 1, min( 500, (int) $limit ) ) ), ARRAY_A );
		foreach ( $rows as $index => $row ) {
			$rows[ $index ]['id']         = (int) $row['id'];
			$rows[ $index ]['hits']       = (int) $row['hits'];
			$rows[ $index ]['redirected'] = null !== Redirects::match( (string) $row['url'] );
		}
		return $rows;
	}

	/**
	 * Remove one entry (after a redirect was added, or by the owner).
	 *
	 * @param int $id Id.
	 * @return void
	 */
	public static function delete( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->delete( Schema::table( Schema::NOT_FOUND ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Drop entries not seen within the kept days.
	 *
	 * @return void
	 */
	public static function prune() {
		global $wpdb;
		$table = Schema::table( Schema::NOT_FOUND );
		$days  = max( 1, (int) Settings::get( 'keep_404_days' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE last_seen < %s", gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );
	}
}
