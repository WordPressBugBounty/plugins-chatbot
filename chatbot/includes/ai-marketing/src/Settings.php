<?php
/**
 * Module settings (one option, autoloaded, merged with defaults).
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing;

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
final class Settings {

	const OPTION = 'qcld_wpbot_marketing_settings';

	/**
	 * Booleans.
	 *
	 * @var string[]
	 */
	const BOOLS = array( 'double_opt_in', 'track_opens', 'track_clicks', 'delete_on_uninstall', 'capture_chatbot', 'capture_wpforms' );

	/**
	 * Defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'from_name'           => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'from_email'          => (string) get_option( 'admin_email' ),
			'reply_to'            => '',
			'company_address'     => '',
			'double_opt_in'       => false,
			'track_opens'         => true,
			'track_clicks'        => true,
			'delete_on_uninstall' => false,
			'capture_chatbot'     => true,
			'capture_wpforms'     => true,
		);
	}

	/**
	 * All settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Save known keys, sanitized. Unknown keys are ignored.
	 *
	 * @param array $input Input.
	 * @return array<string,mixed> Saved settings.
	 */
	public static function save( array $input ) {
		$current = self::all();
		foreach ( self::clean( $input ) as $key => $value ) {
			$current[ $key ] = $value;
		}
		update_option( self::OPTION, $current, true );
		return $current;
	}

	/**
	 * Sanitize the known keys present in the input.
	 *
	 * @param array $input Input.
	 * @return array<string,mixed>
	 */
	public static function clean( array $input ) {
		$out = array();
		foreach ( self::defaults() as $key => $default ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$value = $input[ $key ];
			if ( in_array( $key, self::BOOLS, true ) ) {
				$out[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			} elseif ( in_array( $key, array( 'from_email', 'reply_to' ), true ) ) {
				$email       = sanitize_email( (string) $value );
				$out[ $key ] = is_email( $email ) ? $email : '';
			} elseif ( 'company_address' === $key ) {
				$out[ $key ] = sanitize_textarea_field( (string) $value );
			} else {
				$out[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $out;
	}
}
