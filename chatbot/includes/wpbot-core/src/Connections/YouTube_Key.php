<?php
/**
 * The owner's own YouTube Data API key, shared by every module. Encrypted
 * at rest, masked to the last four characters, never sent to the browser.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use QuantumCloud\WPBotCore\Crypto\Secret_Box;

defined( 'ABSPATH' ) || exit;

/**
 * YouTube key in qcld_wpbot_core_connections['youtube'].
 */
final class YouTube_Key {

	/**
	 * Plain key ('' when missing). Server-side only.
	 *
	 * @return string
	 */
	public static function get() {
		$all   = get_option( Stock_Keys::OPTION, array() );
		$value = is_array( $all ) && isset( $all['youtube']['key'] ) ? (string) $all['youtube']['key'] : '';
		$plain = '' !== $value ? Secret_Box::decrypt( $value ) : '';
		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * Save or clear the key.
	 *
	 * @param array $changes key, clear_key.
	 * @return array Public view.
	 */
	public static function update( array $changes ) {
		$all = get_option( Stock_Keys::OPTION, array() );
		$all = is_array( $all ) ? $all : array();
		if ( ! empty( $changes['clear_key'] ) ) {
			$all['youtube'] = array();
		} elseif ( isset( $changes['key'] ) && '' !== trim( (string) $changes['key'] ) ) {
			$all['youtube'] = array(
				'key'   => Secret_Box::encrypt( (string) preg_replace( '/[^\x21-\x7E]/', '', (string) $changes['key'] ) ),
				'error' => '',
			);
		}
		update_option( Stock_Keys::OPTION, $all, false );
		return self::public_view();
	}

	/**
	 * Record or clear the last failure (shown as a bold notice).
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public static function set_error( $message ) {
		$all = get_option( Stock_Keys::OPTION, array() );
		if ( ! is_array( $all ) || empty( $all['youtube']['key'] ) || ( isset( $all['youtube']['error'] ) && $all['youtube']['error'] === $message ) ) {
			return;
		}
		$all['youtube']['error'] = (string) $message;
		update_option( Stock_Keys::OPTION, $all, false );
	}

	/**
	 * Masked view.
	 *
	 * @return array
	 */
	public static function public_view() {
		$all   = get_option( Stock_Keys::OPTION, array() );
		$plain = self::get();
		$error = is_array( $all ) && isset( $all['youtube']['error'] ) ? (string) $all['youtube']['error'] : '';
		return array(
			'has_key'  => '' !== $plain,
			'key_hint' => Secret_Box::mask( $plain ),
			'status'   => '' === $plain ? 'not_connected' : ( '' !== $error ? 'failing' : 'connected' ),
			'message'  => $error,
		);
	}
}
