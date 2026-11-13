<?php
/**
 * The owner's own developer apps for social networks (App ID and secret),
 * encrypted at rest, never sent to the browser in full.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use QuantumCloud\WPBotCore\Crypto\Secret_Box;

defined( 'ABSPATH' ) || exit;

/**
 * App credentials in qcld_wpbot_core_social_apps (autoload off).
 */
final class Social_Apps {

	/**
	 * Option name.
	 */
	const OPTION = 'qcld_wpbot_core_social_apps';

	/**
	 * Known apps: id => label. Modules add apps through the filter.
	 *
	 * @return array<string,string>
	 */
	public static function apps() {
		return (array) apply_filters(
			'qcld_wpbot_core/social_apps',
			array(
				'meta'    => 'Meta (Facebook and Instagram)',
				'threads' => 'Threads',
			)
		);
	}

	/**
	 * Client id ('' when none).
	 *
	 * @param string $app App id.
	 * @return string
	 */
	public static function client_id( $app ) {
		$all = self::load();
		return isset( $all[ $app ]['client_id'] ) ? (string) $all[ $app ]['client_id'] : '';
	}

	/**
	 * Client secret, decrypted ('' when none or unreadable).
	 *
	 * @param string $app App id.
	 * @return string
	 */
	public static function client_secret( $app ) {
		$all = self::load();
		if ( empty( $all[ $app ]['client_secret'] ) ) {
			return '';
		}
		$plain = Secret_Box::decrypt( (string) $all[ $app ]['client_secret'] );
		return null === $plain ? '' : $plain;
	}

	/**
	 * Whether both id and secret are saved.
	 *
	 * @param string $app App id.
	 * @return bool
	 */
	public static function has( $app ) {
		return '' !== self::client_id( $app ) && '' !== self::client_secret( $app );
	}

	/**
	 * Save an app. A blank or masked secret keeps the stored one.
	 *
	 * @param string $app     App id.
	 * @param array  $changes client_id, client_secret, clear (bool).
	 * @return array Public view of the app.
	 */
	public static function update( $app, array $changes ) {
		$app = sanitize_key( $app );
		if ( ! isset( self::apps()[ $app ] ) ) {
			return array();
		}
		$all     = self::load();
		$current = isset( $all[ $app ] ) ? (array) $all[ $app ] : array();
		if ( ! empty( $changes['clear'] ) ) {
			$current = array();
		}
		if ( isset( $changes['client_id'] ) ) {
			$current['client_id'] = (string) preg_replace( '/[^A-Za-z0-9._\-]/', '', (string) $changes['client_id'] );
		}
		if ( isset( $changes['client_secret'] ) ) {
			$secret = (string) preg_replace( '/[^\x21-\x7E]/', '', (string) $changes['client_secret'] );
			if ( '' !== $secret && ! self::is_mask( (string) $changes['client_secret'] ) ) {
				$current['client_secret'] = Secret_Box::encrypt( $secret );
			}
		}
		$all[ $app ] = $current;
		update_option( self::OPTION, $all, false );
		return self::public_view( $app );
	}

	/**
	 * Masked view for settings screens.
	 *
	 * @param string $app App id.
	 * @return array{client_id:string, has_secret:bool, secret_hint:string}
	 */
	public static function public_view( $app ) {
		$secret = self::client_secret( $app );
		return array(
			'client_id'   => self::client_id( $app ),
			'has_secret'  => '' !== $secret,
			'secret_hint' => Secret_Box::mask( $secret ),
		);
	}

	/**
	 * Stored apps.
	 *
	 * @return array
	 */
	private static function load() {
		$all = get_option( self::OPTION, array() );
		return is_array( $all ) ? $all : array();
	}

	/**
	 * Whether a posted value is a mask of the stored secret ("••••abcd" from
	 * this screen, "********abcd" from the chatbot screens), not a new one.
	 *
	 * @param string $value Posted value.
	 * @return bool
	 */
	public static function is_mask( $value ) {
		return (bool) preg_match( '/^(?:\x{2022}+|\*+)[^\x{2022}*]{0,4}$/u', (string) $value );
	}
}
