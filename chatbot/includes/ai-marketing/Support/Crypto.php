<?php
/**
 * Symmetric encryption for secrets stored in options (API keys).
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Support;

defined( 'ABSPATH' ) || exit;

/**
 * AES-256-CBC with a key derived from wp_salt( 'auth' ).
 *
 * Encrypted values look like "enc:<base64(iv . ciphertext)>". When OpenSSL is not
 * available values are stored as plain text, and decrypt() passes plain text through.
 */
final class Crypto {

	const PREFIX = 'enc:';
	const CIPHER = 'aes-256-cbc';

	/**
	 * Whether OpenSSL encryption is available.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_random_pseudo_bytes' );
	}

	/**
	 * Whether a stored value carries the encryption prefix.
	 *
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	public static function is_encrypted( $value ) {
		return is_string( $value ) && 0 === strpos( $value, self::PREFIX );
	}

	/**
	 * Encrypt a plain-text secret. Empty and already-encrypted values are returned unchanged.
	 *
	 * @param string $plain Plain text.
	 * @return string
	 */
	public static function encrypt( $plain ) {
		$plain = (string) $plain;
		if ( '' === $plain || self::is_encrypted( $plain ) || ! self::available() ) {
			return $plain;
		}
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv );
		if ( false === $cipher ) {
			return $plain;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary-safe storage.
		return self::PREFIX . base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt a stored value. Values without the prefix are returned as-is (plain text).
	 * Returns an empty string when decryption fails.
	 *
	 * @param string $stored Stored value.
	 * @return string
	 */
	public static function decrypt( $stored ) {
		$stored = (string) $stored;
		if ( ! self::is_encrypted( $stored ) ) {
			return $stored;
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- binary-safe storage.
		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
		if ( false === $raw || strlen( $raw ) <= 16 ) {
			return '';
		}
		$plain = openssl_decrypt( substr( $raw, 16 ), self::CIPHER, self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
		return false === $plain ? '' : $plain;
	}

	/**
	 * 32-byte key derived from the site's auth salt.
	 *
	 * @return string
	 */
	private static function key() {
		return hash( 'sha256', 'wpbot_crm|' . wp_salt( 'auth' ), true );
	}
}
