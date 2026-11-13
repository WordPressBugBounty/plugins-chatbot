<?php
/**
 * General CRM settings (option wpbot_crm_settings).
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Typed access to the CRM settings, with defaults, sanitising and API-key masking.
 *
 * API keys are stored encrypted (Crypto). all()/get() return decrypted values for
 * internal use; for_response() returns them masked for the admin UI.
 */
final class Settings {

	const OPTION = 'wpbot_crm_settings';
	const MASK   = '••••';

	const PROVIDERS = array( 'openai', 'gemini', 'claude', 'openrouter', 'grok' );
	const BOOLS     = array( 'double_opt_in', 'track_opens', 'track_clicks', 'gdpr_mode' );
	const INTS      = array( 'emails_per_second', 'batch_size', 'batch_interval' );

	/**
	 * Request-level cache of the decrypted settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default values (API-CONTRACT §3.9). Site name/admin email are read at runtime.
	 *
	 * @return array
	 */
	public static function defaults() {
		$site_name   = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$admin_email = (string) get_option( 'admin_email' );

		$defaults = array(
			'from_name'         => $site_name,
			'from_email'        => $admin_email,
			'reply_to'          => $admin_email,
			'emails_per_second' => 10,
			'company_name'      => $site_name,
			'company_address'   => '',
			'email_footer'      => '<p>&copy; ' . gmdate( 'Y' ) . ' ' . esc_html( $site_name ) . '. All rights reserved.</p>',
			'unsubscribe_text'  => 'Unsubscribe',
			'double_opt_in'     => false,
			'batch_size'        => 50,
			'batch_interval'    => 60,
			'track_opens'       => false,
			'track_clicks'      => false,
			'gdpr_mode'         => false,
			'ai_provider'       => 'openai',
			'ai_model'          => '', // R10: empty = the provider's recommended model (WPBot Core's catalogue).
		);
		foreach ( self::PROVIDERS as $provider ) {
			$defaults[ $provider . '_api_key' ] = '';
		}

		/**
		 * Filters the CRM settings defaults. Keys added here are also accepted by save().
		 *
		 * @param array $defaults Defaults.
		 */
		return (array) apply_filters( 'wpbot_crm/settings_defaults', $defaults );
	}

	/**
	 * All settings, typed, with API keys decrypted.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$stored   = get_option( self::OPTION, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$settings = array_merge( self::defaults(), $stored );

		foreach ( self::BOOLS as $key ) {
			$settings[ $key ] = self::to_bool( $settings[ $key ] );
		}
		foreach ( self::INTS as $key ) {
			$settings[ $key ] = max( 1, (int) $settings[ $key ] );
		}
		foreach ( self::key_fields() as $key ) {
			$settings[ $key ] = Crypto::decrypt( (string) $settings[ $key ] );
		}

		self::$cache = $settings;
		return $settings;
	}

	/**
	 * One setting.
	 *
	 * @param string $key      Key.
	 * @param mixed  $fallback Value when the key is unknown.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Boolean setting.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public static function bool( $key ) {
		return self::to_bool( self::get( $key, false ) );
	}

	/**
	 * Integer setting.
	 *
	 * @param string $key Key.
	 * @return int
	 */
	public static function int( $key ) {
		return (int) self::get( $key, 0 );
	}

	/**
	 * String setting.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function string( $key ) {
		$value = self::get( $key, '' );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Decrypted API key for an AI provider ('' when not set).
	 *
	 * @param string $provider openai|gemini|claude|openrouter|grok.
	 * @return string
	 */
	public static function api_key( $provider ) {
		return self::string( sanitize_key( $provider ) . '_api_key' );
	}

	/**
	 * Settings shaped for the REST response: real booleans, ints, masked API keys.
	 *
	 * @return array
	 */
	public static function for_response() {
		$settings = self::all();
		foreach ( self::key_fields() as $key ) {
			$settings[ $key ] = self::mask( $settings[ $key ] );
		}
		return $settings;
	}

	/**
	 * Sanitise and merge-save settings. Unknown keys are ignored; a posted API key that
	 * looks masked keeps the stored key.
	 *
	 * @param array $input Raw input (strings, 1/0 or booleans).
	 * @return array The saved settings, shaped by for_response().
	 */
	public static function save( array $input ) {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$known  = self::defaults();

		foreach ( $input as $key => $value ) {
			if ( ! array_key_exists( $key, $known ) ) {
				continue;
			}
			if ( in_array( $key, self::key_fields(), true ) ) {
				$value = trim( sanitize_text_field( (string) $value ) );
				if ( self::is_masked( $value ) ) {
					continue;
				}
				$stored[ $key ] = Crypto::encrypt( $value );
				continue;
			}
			$stored[ $key ] = self::sanitize_value( $key, $value );
		}

		update_option( self::OPTION, $stored, false );
		self::reset_cache();
		return self::for_response();
	}

	/**
	 * Mask a secret as "••••" + last 4 characters ('' stays '').
	 *
	 * @param string $value Secret.
	 * @return string
	 */
	public static function mask( $value ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}
		return self::MASK . ( strlen( $value ) > 4 ? substr( $value, -4 ) : '' );
	}

	/**
	 * Whether a submitted value is a mask (and therefore means "unchanged").
	 *
	 * @param string $value Submitted value.
	 * @return bool
	 */
	public static function is_masked( $value ) {
		return 0 === strpos( (string) $value, self::MASK );
	}

	/**
	 * Clear the request cache (after writing the option directly).
	 */
	public static function reset_cache() {
		self::$cache = null;
	}

	/**
	 * Names of the API-key fields.
	 *
	 * @return string[]
	 */
	public static function key_fields() {
		$fields = array();
		foreach ( self::PROVIDERS as $provider ) {
			$fields[] = $provider . '_api_key';
		}
		return $fields;
	}

	/**
	 * Loose boolean cast (accepts 1/0, '1'/'0', 'true'/'false', 'yes', 'on').
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function to_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}
		return ! empty( $value );
	}

	/**
	 * Sanitise one non-secret field.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	private static function sanitize_value( $key, $value ) {
		if ( in_array( $key, self::BOOLS, true ) ) {
			return self::to_bool( $value );
		}
		if ( in_array( $key, self::INTS, true ) ) {
			return max( 1, absint( $value ) );
		}
		switch ( $key ) {
			case 'from_email':
			case 'reply_to':
				return sanitize_email( (string) $value );
			case 'email_footer':
				return wp_kses_post( (string) $value );
			case 'company_address':
				return sanitize_textarea_field( (string) $value );
			case 'ai_provider':
				$value = sanitize_key( (string) $value );
				return in_array( $value, self::PROVIDERS, true ) ? $value : 'openai';
		}
		if ( is_array( $value ) ) {
			return map_deep( $value, 'sanitize_text_field' );
		}
		if ( is_bool( $value ) || is_int( $value ) ) {
			return $value;
		}
		return sanitize_text_field( (string) $value );
	}
}
