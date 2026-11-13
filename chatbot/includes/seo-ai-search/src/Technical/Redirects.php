<?php
/**
 * Simple redirects, used only when no SEO or redirect plugin handles
 * redirects (so they never compete). An early, cached front-end handler;
 * loops and chains are refused when saving. The Pro module adds pattern
 * redirects and bulk import through the qcld_wpbot_seo/redirect_match filter.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Technical;

use QuantumCloud\SeoAiSearch\Results\Change_Log;
use QuantumCloud\SeoAiSearch\Seo\Seo_Plugins;
use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\SeoAiSearch\Storage\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Redirect store and handler.
 */
final class Redirects {

	/**
	 * Cached source => [target, code, id] map.
	 */
	const CACHE = 'qcld_wpbot_seo_redirect_map';

	/**
	 * Hook the handler (early, before WordPress decides on a 404).
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 1 );
	}

	/**
	 * Whether this module's redirects are on.
	 *
	 * @return bool
	 */
	public static function active() {
		return (bool) Settings::get( 'simple_redirects' ) && ! Seo_Plugins::handles_redirects();
	}

	/**
	 * Redirect the current request when a rule matches.
	 *
	 * @return void
	 */
	public static function handle() {
		if ( is_admin() || ! self::active() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$path  = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		$match = self::match( $path );
		if ( null === $match ) {
			return;
		}
		self::count( $match['id'] );
		if ( 410 === $match['code'] ) {
			status_header( 410 );
			nocache_headers();
			exit;
		}
		wp_redirect( $match['target'], $match['code'], \QuantumCloud\SeoAiSearch\Module::agent_token() . ' SEO' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Targets are validated on save (same site or an http(s) URL the owner entered).
		exit;
	}

	/**
	 * Rule for a path, or null.
	 *
	 * @param string $path Request path (or path with query).
	 * @return array|null target, code, id.
	 */
	public static function match( $path ) {
		$key = self::normalize( (string) strtok( (string) $path, '?' ) );
		$map = self::map();
		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}
		/**
		 * Other matches (the Pro module adds pattern redirects).
		 *
		 * @param array|null $match Match.
		 * @param string     $key   Normalized path.
		 */
		$match = apply_filters( 'qcld_wpbot_seo/redirect_match', null, $key );
		return is_array( $match ) ? $match : null;
	}

	/**
	 * All simple redirects.
	 *
	 * @return array
	 */
	public static function all() {
		global $wpdb;
		$table = Schema::table( Schema::REDIRECTS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, source, target, code, type, hits, last_hit, created_at FROM %i ORDER BY id DESC LIMIT 1000', $table ), ARRAY_A );
		foreach ( $rows as $index => $row ) {
			$rows[ $index ]['id']   = (int) $row['id'];
			$rows[ $index ]['code'] = (int) $row['code'];
			$rows[ $index ]['hits'] = (int) $row['hits'];
		}
		return $rows;
	}

	/**
	 * Add or replace a redirect.
	 *
	 * @param string $source Path on this site.
	 * @param string $target Path on this site or an http(s) URL.
	 * @param int    $code   301, 302, 307, 308 or 410.
	 * @param string $type   simple (Pro: pattern).
	 * @return int|WP_Error Redirect id.
	 */
	public static function save( $source, $target, $code = 301, $type = 'simple' ) {
		global $wpdb;
		$code   = in_array( (int) $code, array( 301, 302, 307, 308, 410 ), true ) ? (int) $code : 301;
		$source = self::normalize( (string) $source );
		$target = 410 === $code ? '' : self::clean_target( (string) $target );
		if ( '' === $source || '/' === $source ) {
			return new WP_Error( 'qcld_wpbot_seo_bad_redirect', __( 'Enter the old address as a path on this site, for example /old-page/ (not the home page).', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( 410 !== $code && '' === $target ) {
			return new WP_Error( 'qcld_wpbot_seo_bad_target', __( 'Enter where visitors should go: a path on this site or a full http(s) address.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( 'simple' === $type && 410 !== $code && self::loops( $source, $target ) ) {
			return new WP_Error( 'qcld_wpbot_seo_redirect_loop', __( 'This redirect would send visitors in a circle. Choose another target.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$table = Schema::table( Schema::REDIRECTS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (source,source_hash,target,code,type,created_at) VALUES (%s,%s,%s,%d,%s,%s) ON DUPLICATE KEY UPDATE target = VALUES(target), code = VALUES(code)', $table, $source, md5( $source ), $target, $code, sanitize_key( $type ), current_time( 'mysql', true ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE source_hash = %s AND type = %s', $table, md5( $source ), sanitize_key( $type ) ) );
		delete_transient( self::CACHE );
		Change_Log::record(
			array(
				'kind'   => 'redirect',
				'field'  => $source,
				'after'  => 410 === $code ? '410' : $target,
				'source' => 'fix',
			)
		);
		return $id;
	}

	/**
	 * Remove a redirect.
	 *
	 * @param int $id Id.
	 * @return void
	 */
	public static function delete( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->delete( Schema::table( Schema::REDIRECTS ), array( 'id' => (int) $id ), array( '%d' ) );
		delete_transient( self::CACHE );
	}

	/**
	 * Path form used as the key: leading slash, no trailing slash (except /), lower-case.
	 *
	 * @param string $path Path or URL of this site.
	 * @return string
	 */
	public static function normalize( $path ) {
		$path = trim( (string) $path );
		if ( preg_match( '#^https?://#i', $path ) ) {
			$path = (string) wp_parse_url( $path, PHP_URL_PATH );
		}
		$path = '/' . ltrim( (string) preg_replace( '#[^A-Za-z0-9/_.~%!$&\'()*+,;=:@\-]#', '', $path ), '/' );
		$path = '/' === $path ? '/' : untrailingslashit( $path );
		return strtolower( substr( $path, 0, 500 ) );
	}

	/**
	 * Same-site path, or a full http(s) URL.
	 *
	 * @param string $target Target.
	 * @return string
	 */
	public static function clean_target( $target ) {
		$target = trim( $target );
		if ( '' !== $target && '/' === $target[0] && 0 !== strpos( $target, '//' ) ) {
			return home_url( self::normalize( $target ) . ( '/' !== substr( $target, -1 ) || '/' === $target ? '' : '/' ) );
		}
		return (string) esc_url_raw( $target, array( 'http', 'https' ) );
	}

	/**
	 * Whether following redirects from the target comes back to the source.
	 *
	 * @param string $source Normalized source.
	 * @param string $target Target URL.
	 * @return bool
	 */
	public static function loops( $source, $target ) {
		$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$map  = self::map();
		$seen = array( $source => true );
		$next = $target;
		for ( $hop = 0; $hop < 10; $hop++ ) {
			if ( strtolower( (string) wp_parse_url( $next, PHP_URL_HOST ) ) !== $home ) {
				return false;
			}
			$key = self::normalize( (string) wp_parse_url( $next, PHP_URL_PATH ) );
			if ( isset( $seen[ $key ] ) ) {
				return true;
			}
			$seen[ $key ] = true;
			if ( ! isset( $map[ $key ] ) ) {
				return false;
			}
			$next = $map[ $key ]['target'];
		}
		return true;
	}

	/**
	 * Cached map of simple redirects.
	 *
	 * @return array
	 */
	private static function map() {
		$map = get_transient( self::CACHE );
		if ( is_array( $map ) ) {
			return $map;
		}
		global $wpdb;
		$table = Schema::table( Schema::REDIRECTS );
		$map   = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, source, target, code FROM %i WHERE type = 'simple'", $table ), ARRAY_A ) as $row ) {
			$map[ $row['source'] ] = array(
				'target' => (string) $row['target'],
				'code'   => (int) $row['code'],
				'id'     => (int) $row['id'],
			);
		}
		set_transient( self::CACHE, $map, DAY_IN_SECONDS );
		return $map;
	}

	/**
	 * Count a hit.
	 *
	 * @param int $id Redirect id.
	 * @return void
	 */
	private static function count( $id ) {
		global $wpdb;
		$table = Schema::table( Schema::REDIRECTS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET hits = hits + 1, last_hit = %s WHERE id = %d', $table, current_time( 'mysql', true ), (int) $id ) );
	}
}
