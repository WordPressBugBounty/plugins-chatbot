<?php
/**
 * Status of an outside URL: a polite HEAD request (GET when HEAD is not
 * allowed), short timeout, WPBot User-Agent with the site URL, cached a day.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Technical;

defined( 'ABSPATH' ) || exit;

/**
 * Outbound link checks.
 */
final class Link_Check {

	/**
	 * HTTP status (0 when unreachable).
	 *
	 * @param string $url URL.
	 * @return int
	 */
	public static function status( $url ) {
		$url = esc_url_raw( (string) $url, array( 'http', 'https' ) );
		if ( '' === $url ) {
			return 0;
		}
		$key    = 'qcld_wpbot_seo_link_' . md5( $url );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$args     = array(
			'timeout'     => 5,
			'redirection' => 5,
			'user-agent'  => Scan::user_agent(),
		);
		$response = wp_safe_remote_head( $url, $args );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( in_array( $code, array( 0, 403, 405, 501 ), true ) ) {
			// Some servers refuse HEAD; ask for the page once.
			$args['limit_response_size'] = 65536;
			$response                    = wp_safe_remote_get( $url, $args );
			$code                        = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		}
		set_transient( $key, $code, DAY_IN_SECONDS );
		return $code;
	}
}
