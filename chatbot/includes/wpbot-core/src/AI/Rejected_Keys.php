<?php
/**
 * AI keys a provider rejected (owner, 20 November 2026: one invalid key filled the error log).
 *
 * After a provider answers "invalid key" (HTTP 401 / invalid_key), the same key is not sent to
 * that provider again for a while: calls fail at once with the same "invalid key" error, without
 * a request, so screens and background jobs stop repeating it and the error log records it once.
 * A new key is a different key and is used at once; "Test key" checks again (forget()).
 *
 * Keys are never stored: only a keyed hash of provider and key, with an expiry.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Rejected AI keys.
 */
final class Rejected_Keys {

	/**
	 * Option: hash => expiry (not autoloaded).
	 */
	const OPTION = 'qcld_wpbot_core_rejected_keys';

	/**
	 * At most this many entries.
	 */
	const MAX = 30;

	/**
	 * Hook in: a saved key is checked again at once (keys live in several options).
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'updated_option', array( self::class, 'key_saved' ) );
		add_action( 'added_option', array( self::class, 'key_saved' ) );
	}

	/**
	 * Forget the rejections when an option that holds AI keys changes.
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	public static function key_saved( $option ) {
		$option = (string) $option;
		if ( self::OPTION !== $option && preg_match( '/(api_?key|_keys?$|vault)/i', $option ) ) {
			self::forget();
		}
	}

	/**
	 * Whether the provider rejected this key lately.
	 *
	 * @param string $provider Provider id.
	 * @param string $key      Plain key.
	 * @return bool
	 */
	public static function is_rejected( $provider, $key ) {
		if ( '' === (string) $key ) {
			return false;
		}
		$list = self::load();
		$id   = self::id( $provider, $key );
		return isset( $list[ $id ] ) && $list[ $id ] > time();
	}

	/**
	 * Remember a key the provider rejected.
	 *
	 * @param string $provider Provider id.
	 * @param string $key      Plain key.
	 * @return void
	 */
	public static function remember( $provider, $key ) {
		if ( '' === (string) $key ) {
			return;
		}
		$list                                = self::load();
		$list[ self::id( $provider, $key ) ] = time() + self::ttl();
		arsort( $list );
		update_option( self::OPTION, array_slice( $list, 0, self::MAX, true ), false );
	}

	/**
	 * Note a provider's answer: remember the key when the answer was "invalid key".
	 *
	 * @param string $provider Provider id.
	 * @param string $key      Plain key.
	 * @param string $cause    Error_Classifier cause.
	 * @return void
	 */
	public static function note( $provider, $key, $cause ) {
		if ( Error_Classifier::INVALID_KEY === $cause ) {
			self::remember( $provider, $key );
		}
	}

	/**
	 * Check a provider's keys again from now on ("Test key", or every provider when '' is given).
	 *
	 * @param string $provider Provider id, or '' for all.
	 * @return void
	 */
	public static function forget( $provider = '' ) {
		if ( '' === (string) $provider ) {
			delete_option( self::OPTION );
			return;
		}
		$prefix = sanitize_key( (string) $provider ) . ':';
		$list   = array_filter(
			self::load(),
			static function ( $expires, $id ) use ( $prefix ) {
				return 0 !== strpos( (string) $id, $prefix );
			},
			ARRAY_FILTER_USE_BOTH
		);
		if ( $list ) {
			update_option( self::OPTION, $list, false );
		} else {
			delete_option( self::OPTION );
		}
	}

	/**
	 * How long a rejected key is not sent again (seconds; default six hours).
	 *
	 * @return int
	 */
	private static function ttl() {
		/**
		 * How long WPBot waits before sending a key a provider rejected again (seconds).
		 *
		 * @param int $seconds Six hours.
		 */
		return max( 60, (int) apply_filters( 'qcld_wpbot_core/rejected_key_ttl', 6 * HOUR_IN_SECONDS ) );
	}

	/**
	 * Saved entries that have not expired.
	 *
	 * @return array<string,int>
	 */
	private static function load() {
		$list = get_option( self::OPTION, array() );
		$now  = time();
		$out  = array();
		foreach ( is_array( $list ) ? $list : array() as $id => $expires ) {
			if ( (int) $expires > $now ) {
				$out[ (string) $id ] = (int) $expires;
			}
		}
		return $out;
	}

	/**
	 * Entry id: provider, then a keyed hash of the key (the key itself is never stored).
	 *
	 * @param string $provider Provider id.
	 * @param string $key      Plain key.
	 * @return string
	 */
	private static function id( $provider, $key ) {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'qcld-wpbot-core';
		return sanitize_key( (string) $provider ) . ':' . substr( hash_hmac( 'sha256', (string) $key, $salt ), 0, 32 );
	}
}
