<?php
/**
 * Stores account tokens encrypted with WPBot Core's Secret_Box.
 *
 * One option per account (autoload off), so tokens never load on every
 * request and a single account can be removed without touching others.
 * Values are never returned to the browser; only masks are.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Accounts;

use QuantumCloud\WPBotCore\Crypto\Secret_Box;

defined( 'ABSPATH' ) || exit;

/**
 * Encrypted token storage.
 */
final class Token_Vault {

	/**
	 * Option name prefix; the account key follows.
	 */
	const PREFIX = 'qcld_wpbot_social_token_';

	/**
	 * Save a token.
	 *
	 * @param string $account_key Social account key (network:remote id).
	 * @param string $token       Token.
	 * @return bool False when encryption is unavailable (the token is not stored in plain text).
	 */
	public static function put( $account_key, $token ) {
		if ( ! Secret_Box::is_available() ) {
			return false;
		}
		$stored = Secret_Box::encrypt( (string) $token );
		if ( '' === $stored ) {
			return false;
		}
		update_option( self::option( $account_key ), $stored, false );
		return true;
	}

	/**
	 * Whether a stored token can be read (QA #42).
	 *
	 * @param string $account_key Account key.
	 * @return string "ok", "missing", or "unreadable" (stored but it no longer
	 *                decrypts, for example after a site move changed the salts).
	 */
	public static function status( $account_key ) {
		$stored = get_option( self::option( $account_key ), '' );
		if ( '' === $stored ) {
			return 'missing';
		}
		return null === Secret_Box::decrypt( (string) $stored ) ? 'unreadable' : 'ok';
	}

	/**
	 * Read a token.
	 *
	 * @param string $account_key Account key.
	 * @return string Token, or '' when missing or unreadable (for example
	 *                after a site move changed the salts: reconnect needed).
	 */
	public static function get( $account_key ) {
		$stored = get_option( self::option( $account_key ), '' );
		if ( '' === $stored ) {
			return '';
		}
		$plain = Secret_Box::decrypt( (string) $stored );
		return null === $plain ? '' : $plain;
	}

	/**
	 * Masked token for screens (last 4 characters).
	 *
	 * @param string $account_key Account key.
	 * @return string
	 */
	public static function mask( $account_key ) {
		return Secret_Box::mask( self::get( $account_key ) );
	}

	/**
	 * Remove a token.
	 *
	 * @param string $account_key Account key.
	 * @return void
	 */
	public static function forget( $account_key ) {
		delete_option( self::option( $account_key ) );
	}

	/**
	 * Option name for an account.
	 *
	 * @param string $account_key Account key.
	 * @return string
	 */
	private static function option( $account_key ) {
		return self::PREFIX . substr( hash( 'sha256', (string) $account_key ), 0, 32 );
	}
}
