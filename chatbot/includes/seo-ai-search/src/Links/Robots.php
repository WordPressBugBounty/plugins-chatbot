<?php
/**
 * Rules from robots.txt for polite checks of other sites (pure parser plus a
 * cached fetch).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Links;

use QuantumCloud\SeoAiSearch\Technical\Scan;

defined( 'ABSPATH' ) || exit;

/**
 * Is a path on another site allowed for our checker?
 */
final class Robots {

	/**
	 * Our product token in robots.txt groups.
	 */
	const TOKEN = 'wpbot';

	/**
	 * Allowed by this robots.txt text? Longest matching rule wins; Allow wins ties.
	 *
	 * @param string $robots robots.txt text.
	 * @param string $path   Path with query.
	 * @return bool
	 */
	public static function allowed( $robots, $path ) {
		$groups  = self::groups( (string) $robots );
		$rules   = isset( $groups[ self::TOKEN ] ) ? $groups[ self::TOKEN ] : ( isset( $groups['*'] ) ? $groups['*'] : array() );
		$best    = -1;
		$allowed = true;
		foreach ( $rules as $rule ) {
			if ( '' === $rule['path'] ) {
				continue;
			}
			$regex = '#^' . str_replace( array( '\*', '\$' ), array( '.*', '$' ), preg_quote( $rule['path'], '#' ) ) . '#';
			if ( preg_match( $regex, (string) $path ) ) {
				$length = strlen( $rule['path'] );
				if ( $length > $best || ( $length === $best && $rule['allow'] ) ) {
					$best    = $length;
					$allowed = $rule['allow'];
				}
			}
		}
		return $allowed;
	}

	/**
	 * Rules per user agent (lowercase).
	 *
	 * @param string $robots robots.txt text.
	 * @return array agent => list of allow, path.
	 */
	public static function groups( $robots ) {
		$groups  = array();
		$agents  = array();
		$in_rule = false;
		foreach ( preg_split( '/\R/', $robots ) as $line ) {
			$line = trim( (string) preg_replace( '/#.*$/', '', $line ) );
			if ( ! preg_match( '/^([A-Za-z-]+)\s*:\s*(.*)$/', $line, $m ) ) {
				continue;
			}
			$field = strtolower( $m[1] );
			$value = trim( $m[2] );
			if ( 'user-agent' === $field ) {
				if ( $in_rule ) {
					$agents  = array();
					$in_rule = false;
				}
				$agent            = strtolower( $value );
				$agents[]         = $agent;
				$groups[ $agent ] = isset( $groups[ $agent ] ) ? $groups[ $agent ] : array();
			} elseif ( 'allow' === $field || 'disallow' === $field ) {
				$in_rule = true;
				foreach ( $agents as $agent ) {
					$groups[ $agent ][] = array(
						'allow' => 'allow' === $field,
						'path'  => $value,
					);
				}
			}
		}
		return $groups;
	}

	/**
	 * May we fetch this URL? robots.txt cached a day per host; unreachable
	 * robots.txt means allowed (as search engines treat a 4xx).
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function may_fetch( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return false;
		}
		$origin = ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
		$key    = 'qcld_wpbot_seo_robots_' . md5( $origin );
		$text   = get_transient( $key );
		if ( false === $text ) {
			$response = wp_safe_remote_get(
				$origin . '/robots.txt',
				array(
					'timeout'             => 5,
					'user-agent'          => Scan::user_agent(),
					'limit_response_size' => 200000,
				)
			);
			$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			$text     = 200 === $code ? (string) wp_remote_retrieve_body( $response ) : '';
			set_transient( $key, $text, DAY_IN_SECONDS );
		}
		$path = ( isset( $parts['path'] ) ? $parts['path'] : '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
		return self::allowed( (string) $text, $path );
	}
}
