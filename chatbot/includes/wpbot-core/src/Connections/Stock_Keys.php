<?php
/**
 * Keys for stock image services (Pexels, Pixabay, Unsplash), shared by every
 * module. Encrypted at rest, masked to the last four characters, never sent
 * to the browser. Unsplash also has an on/off switch (off by default).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use QuantumCloud\WPBotCore\Crypto\Secret_Box;

defined( 'ABSPATH' ) || exit;

/**
 * Stock image keys in qcld_wpbot_core_connections.
 */
final class Stock_Keys {

	const OPTION = 'qcld_wpbot_core_connections';

	/**
	 * Supported services.
	 *
	 * @var string[]
	 */
	const SERVICES = array( 'pexels', 'pixabay', 'unsplash' );

	/**
	 * Plain key of a service ('' when missing or unreadable). Server-side only.
	 *
	 * @param string $service Service id.
	 * @return string
	 */
	public static function get( $service ) {
		$all   = self::load();
		$plain = isset( $all['stock'][ $service ]['key'] ) ? Secret_Box::decrypt( $all['stock'][ $service ]['key'] ) : '';
		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * Whether Unsplash is switched on (and has a key).
	 *
	 * @return bool
	 */
	public static function unsplash_enabled() {
		$all = self::load();
		return ! empty( $all['stock']['unsplash']['enabled'] ) && '' !== self::get( 'unsplash' );
	}

	/**
	 * Save keys and the Unsplash switch.
	 *
	 * @param array $changes service => array( key, clear_key, enabled ).
	 * @return array Public view.
	 */
	public static function update( array $changes ) {
		$all = self::load();
		foreach ( self::SERVICES as $service ) {
			if ( ! isset( $changes[ $service ] ) || ! is_array( $changes[ $service ] ) ) {
				continue;
			}
			$entry = isset( $all['stock'][ $service ] ) ? (array) $all['stock'][ $service ] : array();
			if ( ! empty( $changes[ $service ]['clear_key'] ) ) {
				$entry['key'] = '';
			} elseif ( isset( $changes[ $service ]['key'] ) && '' !== trim( (string) $changes[ $service ]['key'] ) ) {
				$entry['key'] = Secret_Box::encrypt( (string) preg_replace( '/[^\x21-\x7E]/', '', (string) $changes[ $service ]['key'] ) );
			}
			if ( 'unsplash' === $service && isset( $changes[ $service ]['enabled'] ) ) {
				$entry['enabled'] = (bool) $changes[ $service ]['enabled'];
			}
			$all['stock'][ $service ] = $entry;
		}
		update_option( self::OPTION, $all, false );
		return self::public_view();
	}

	/**
	 * Masked view for settings screens.
	 *
	 * @return array
	 */
	public static function public_view() {
		$view = array();
		$all  = self::load();
		foreach ( self::SERVICES as $service ) {
			$plain            = self::get( $service );
			$view[ $service ] = array(
				'has_key'  => '' !== $plain,
				'key_hint' => Secret_Box::mask( $plain ),
				'enabled'  => 'unsplash' === $service ? ! empty( $all['stock']['unsplash']['enabled'] ) : '' !== $plain,
			);
		}
		return $view;
	}

	/**
	 * Load the option.
	 *
	 * @return array
	 */
	private static function load() {
		$all = get_option( self::OPTION, array() );
		return is_array( $all ) ? $all : array();
	}
}
