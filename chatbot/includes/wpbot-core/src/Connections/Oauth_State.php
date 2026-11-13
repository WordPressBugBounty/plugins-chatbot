<?php
/**
 * One-time OAuth state: random, bound to the user who started the login,
 * single use, expires in 10 minutes. Also keeps the PKCE verifier on the
 * server (Social build-brief A4, QA #13).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * OAuth state store (transients keyed by a hash of the state).
 */
final class Oauth_State {

	/**
	 * Lifetime in seconds.
	 */
	const TTL = 600;

	/**
	 * Transient prefix.
	 */
	const PREFIX = 'qcld_wpbot_core_oauth_';

	/**
	 * Start a login.
	 *
	 * @param array $payload app, return, capability, and anything the callback needs.
	 * @param bool  $pkce    Whether to create a PKCE verifier.
	 * @return array{state:string, challenge:string} The state, and the S256 code challenge ('' without PKCE).
	 */
	public static function create( array $payload, $pkce = false ) {
		$state                 = self::random( 32 );
		$payload['user']       = (int) get_current_user_id();
		$payload['created_at'] = time();
		$challenge             = '';
		if ( $pkce ) {
			$payload['verifier'] = self::random( 64 );
			$challenge           = self::challenge( $payload['verifier'] );
		}
		set_transient( self::PREFIX . hash( 'sha256', $state ), $payload, self::TTL );
		return array(
			'state'     => $state,
			'challenge' => $challenge,
		);
	}

	/**
	 * Use a state once.
	 *
	 * @param string $state State from the redirect.
	 * @return array|WP_Error Payload, or an error when unknown, expired, used, or started by another user.
	 */
	public static function consume( $state ) {
		$state = (string) $state;
		if ( '' === $state || strlen( $state ) > 128 ) {
			return self::expired();
		}
		$key     = self::PREFIX . hash( 'sha256', $state );
		$payload = get_transient( $key );
		// Only the request that actually deletes the state may use it: the
		// delete is atomic in the database and in persistent object caches,
		// so two callbacks racing with the same state cannot both pass.
		if ( ! delete_transient( $key ) ) {
			return self::expired();
		}
		if ( ! is_array( $payload ) || empty( $payload['created_at'] ) || time() - (int) $payload['created_at'] > self::TTL ) {
			return self::expired();
		}
		if ( (int) get_current_user_id() !== (int) $payload['user'] || 0 === (int) $payload['user'] ) {
			return new WP_Error( 'qcld_wpbot_core_oauth_user', __( 'This sign-in was started by another user. Click Connect again.', 'chatbot' ), array( 'status' => 403 ) );
		}
		return $payload;
	}

	/**
	 * S256 PKCE challenge for a verifier.
	 *
	 * @param string $verifier Verifier.
	 * @return string
	 */
	public static function challenge( $verifier ) {
		return rtrim( strtr( base64_encode( hash( 'sha256', (string) $verifier, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- PKCE requires base64url.
	}

	/**
	 * URL-safe random string.
	 *
	 * @param int $length Length.
	 * @return string
	 */
	private static function random( $length ) {
		return substr( rtrim( strtr( base64_encode( random_bytes( $length ) ), '+/', '-_' ), '=' ), 0, $length ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe random token.
	}

	/**
	 * Expired-state error.
	 *
	 * @return WP_Error
	 */
	private static function expired() {
		return new WP_Error( 'qcld_wpbot_core_oauth_state', __( 'This sign-in link has expired or was already used. Go back and click Connect again.', 'chatbot' ), array( 'status' => 400 ) );
	}
}
