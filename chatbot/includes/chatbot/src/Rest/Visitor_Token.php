<?php
/**
 * Visitor token for the public chat routes: fetched when the chat opens, so
 * full-page caching never serves a stale one (WordPress nonces in cached pages
 * expire). It is an HMAC of the issue time with the site's salt, valid 24 hours.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Visitor token.
 */
final class Visitor_Token {

	/**
	 * Seconds a token is valid.
	 */
	const TTL = DAY_IN_SECONDS;

	/**
	 * Issue a token.
	 *
	 * @param int|null $now Time (tests).
	 * @return string
	 */
	public static function issue( $now = null ) {
		$now = null === $now ? time() : (int) $now;
		return 'v1.' . $now . '.' . self::sign( $now );
	}

	/**
	 * Check a token.
	 *
	 * @param string   $token Token.
	 * @param int|null $now   Time (tests).
	 * @return bool
	 */
	public static function valid( $token, $now = null ) {
		$now = null === $now ? time() : (int) $now;
		if ( ! is_string( $token ) || ! preg_match( '/^v1\.(\d{9,11})\.([a-f0-9]{32})$/', $token, $parts ) ) {
			return false;
		}
		$issued = (int) $parts[1];
		if ( $issued > $now + 300 || $now - $issued > self::TTL ) {
			return false;
		}
		return hash_equals( self::sign( $issued ), $parts[2] );
	}

	/**
	 * Signature.
	 *
	 * @param int $issued Issue time.
	 * @return string
	 */
	private static function sign( $issued ) {
		return substr( hash_hmac( 'sha256', 'qcld_wpbot_chat|' . $issued, wp_salt( 'nonce' ) ), 0, 32 );
	}
}
