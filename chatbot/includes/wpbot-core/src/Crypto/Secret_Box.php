<?php
/**
 * Encrypts secrets (API keys, OAuth tokens) before they are stored.
 *
 * Uses libsodium's secretbox (XSalsa20-Poly1305), which WordPress 6.2+
 * always provides through its bundled sodium_compat. The 32-byte key comes
 * from QCLD_WPBOT_CORE_KEY in wp-config.php when defined, otherwise from the
 * site's AUTH salts. If the salts change, old values can no longer be read;
 * callers treat that as "key missing" and ask the owner to enter it again.
 *
 * Stored format: "qcs1:" + base64( nonce . ciphertext ).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Crypto;

defined( 'ABSPATH' ) || exit;

/**
 * Symmetric encryption for values kept in the options table.
 */
final class Secret_Box {

	const PREFIX = 'qcs1:';

	/**
	 * Whether encryption is available on this server.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return function_exists( 'sodium_crypto_secretbox' ) && function_exists( 'random_bytes' );
	}

	/**
	 * Encrypt a value. Empty input stays empty.
	 *
	 * @param string $plain Secret.
	 * @return string Encrypted value, or '' when encryption is unavailable.
	 */
	public static function encrypt( $plain ) {
		$plain = (string) $plain;
		if ( '' === $plain || ! self::is_available() ) {
			return '';
		}
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plain, $nonce, self::key() );
		return self::PREFIX . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary to text for storage, not obfuscation.
	}

	/**
	 * Decrypt a stored value.
	 *
	 * @param string $stored Value produced by encrypt().
	 * @return string|null Plain value, '' for an empty input, null when it cannot be read.
	 */
	public static function decrypt( $stored ) {
		$stored = (string) $stored;
		if ( '' === $stored ) {
			return '';
		}
		if ( 0 !== strpos( $stored, self::PREFIX ) || ! self::is_available() ) {
			return null;
		}
		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Reverses encrypt().
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}
		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		try {
			$plain = sodium_crypto_secretbox_open( $cipher, $nonce, self::key() );
		} catch ( \Exception $e ) {
			return null;
		}
		return false === $plain ? null : $plain;
	}

	/**
	 * Show only the last four characters of a secret.
	 *
	 * @param string $plain Secret.
	 * @return string For example "••••3fA9", or '' for no secret.
	 */
	public static function mask( $plain ) {
		$plain = (string) $plain;
		if ( '' === $plain ) {
			return '';
		}
		return str_repeat( '•', 4 ) . substr( $plain, -4 );
	}

	/**
	 * Derive the 32-byte encryption key.
	 *
	 * @return string Binary key.
	 */
	private static function key() {
		if ( defined( 'QCLD_WPBOT_CORE_KEY' ) && '' !== (string) QCLD_WPBOT_CORE_KEY ) {
			return hash( 'sha256', (string) QCLD_WPBOT_CORE_KEY, true );
		}
		return hash_hmac( 'sha256', 'qcld-wpbot-core-secret-box', wp_salt( 'auth' ), true );
	}
}
