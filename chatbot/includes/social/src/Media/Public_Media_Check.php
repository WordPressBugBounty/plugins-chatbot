<?php
/**
 * Best-effort check that networks can download images from this site
 * (Instagram and Threads fetch media by URL). Local, private-network and
 * password-protected sites fail; a firewall that blocks Meta's fetcher can
 * still pass, so the result is labelled best effort (QA #27).
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Media;

defined( 'ABSPATH' ) || exit;

/**
 * Public media check.
 */
final class Public_Media_Check {

	/**
	 * Cache key.
	 */
	const TRANSIENT = 'qcld_wpbot_social_public_media';

	/**
	 * Whether the site looks reachable from the internet.
	 *
	 * @param bool $fresh Ignore the cached answer.
	 * @return array{ok:bool, reason:string}
	 */
	public static function result( $fresh = false ) {
		$cached = $fresh ? false : get_transient( self::TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$result = self::host_problem( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( '' === $result ) {
			$probe    = includes_url( 'images/w-logo-blue.png' );
			$response = wp_remote_head(
				$probe,
				array(
					'timeout'    => 8,
					'user-agent' => 'facebookexternalhit/1.1',
					'cookies'    => array(),
				)
			);
			$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			if ( 401 === $code || 403 === $code ) {
				$result = __( 'The site asks visitors for a password or blocks unknown visitors, so networks cannot download images.', 'chatbot' );
			}
		}
		$out = array(
			'ok'     => '' === $result,
			'reason' => $result,
		);
		set_transient( self::TRANSIENT, $out, 6 * HOUR_IN_SECONDS );
		return $out;
	}

	/**
	 * Problem with a host name, or '' when it looks public.
	 *
	 * @param string $host Host.
	 * @return string
	 */
	public static function host_problem( $host ) {
		$host = strtolower( trim( $host ) );
		if ( '' === $host || 'localhost' === $host || preg_match( '/\.(local|localhost|test|example|invalid|internal|lan)$/', $host ) ) {
			return __( 'This site runs on a local address, so networks cannot download its images.', 'chatbot' );
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) && ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return __( 'This site runs on a private network address, so networks cannot download its images.', 'chatbot' );
		}
		return '';
	}
}
