<?php
/**
 * Health of social connections per account, with one action when it changes
 * (like qcld_wpbot_core/key_status_changed for AI keys).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

defined( 'ABSPATH' ) || exit;

/**
 * Connection status store in qcld_wpbot_core_connection_status (autoload off).
 */
final class Connection_Status {

	/**
	 * Option name.
	 */
	const OPTION = 'qcld_wpbot_core_connection_status';

	/**
	 * Allowed statuses.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'ok', 'expired', 'revoked', 'error' );

	/**
	 * Record a status. Fires qcld_wpbot_core/connection_status_changed only
	 * when it differs from the last one.
	 *
	 * @param string $connection Connection id, for example "meta" or "telegram".
	 * @param string $account    Account id within the connection.
	 * @param string $status     ok, expired, revoked or error.
	 * @param string $cause      Plain-language cause (no secrets).
	 * @return bool Whether it changed.
	 */
	public static function set( $connection, $account, $status, $cause = '' ) {
		$connection = sanitize_key( $connection );
		$account    = (string) preg_replace( '/[^A-Za-z0-9_.:\-]/', '', (string) $account );
		if ( ! in_array( $status, self::STATUSES, true ) || '' === $connection || '' === $account ) {
			return false;
		}
		$all      = self::load();
		$previous = isset( $all[ $connection ][ $account ]['status'] ) ? $all[ $connection ][ $account ]['status'] : 'ok';
		$cause    = Social_Http::scrub( sanitize_text_field( (string) $cause ) );

		$all[ $connection ][ $account ] = array(
			'status' => $status,
			'cause'  => $cause,
			'at'     => time(),
		);
		update_option( self::OPTION, $all, false );
		if ( $previous === $status ) {
			return false;
		}

		/**
		 * Fires when a connection's health changes.
		 *
		 * @param string $connection Connection id.
		 * @param string $account    Account id.
		 * @param string $status     New status.
		 * @param string $cause      Cause.
		 */
		do_action( 'qcld_wpbot_core/connection_status_changed', $connection, $account, $status, $cause );
		return true;
	}

	/**
	 * Current status of an account (ok when never recorded).
	 *
	 * @param string $connection Connection id.
	 * @param string $account    Account id.
	 * @return array{status:string, cause:string, at:int}
	 */
	public static function get( $connection, $account ) {
		$all = self::load();
		return isset( $all[ $connection ][ $account ] ) ? $all[ $connection ][ $account ] : array(
			'status' => 'ok',
			'cause'  => '',
			'at'     => 0,
		);
	}

	/**
	 * Forget an account (when the module disconnects it).
	 *
	 * @param string $connection Connection id.
	 * @param string $account    Account id.
	 * @return void
	 */
	public static function forget( $connection, $account ) {
		$all = self::load();
		unset( $all[ $connection ][ $account ] );
		update_option( self::OPTION, $all, false );
	}

	/**
	 * Stored statuses.
	 *
	 * @return array
	 */
	private static function load() {
		$all = get_option( self::OPTION, array() );
		return is_array( $all ) ? $all : array();
	}
}
