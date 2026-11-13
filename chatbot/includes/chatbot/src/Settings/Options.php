<?php
/**
 * Reads the options WPBot already stores, with the defaults each edition had.
 *
 * Free and Pro stored a few switches differently (for example Pro asks Simple
 * Text Responses only when "qc_bot_enable_str" is on, Free always asks them).
 * The host adapter can give its own defaults through option_defaults(), so the
 * module never asks which edition is installed.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Option reader.
 */
final class Options {

	/**
	 * Module defaults for options that may not exist yet.
	 */
	const DEFAULTS = array(
		'qc_bot_enable_str'            => '1',
		'disable_sitesearch'           => '',
		'qc_site_search_priority'      => '',
		'qc_bot_str_max_limit'         => '3',
		'qc_bot_str_weight'            => '0.4',
		'qc_bot_str_turnoff_fulltext'  => '',
		'wpbot_search_result_number'   => '5',
		'wpbot_search_image_size'      => 'thumbnail',
		'qcld_wpbot_chat_ai_day_limit' => 60,
	);

	/**
	 * Host defaults, cached per request.
	 *
	 * @var array|null
	 */
	private static $host_defaults = null;

	/**
	 * Read an option. Missing or empty-string values use the host's default, then the module's.
	 *
	 * @param string $name    Option.
	 * @param mixed  $fallback_value Fallback when nobody has a default.
	 * @return mixed
	 */
	public static function get( $name, $fallback_value = null ) {
		$value = get_option( $name, null );
		if ( null !== $value && false !== $value ) {
			return $value;
		}
		$defaults = self::defaults();
		if ( array_key_exists( $name, $defaults ) ) {
			return $defaults[ $name ];
		}
		return $fallback_value;
	}

	/**
	 * True when a "1"/"on"/"yes"/true switch is on.
	 *
	 * @param string $name Option.
	 * @return bool
	 */
	public static function on( $name ) {
		$value = self::get( $name, '' );
		return true === $value || 1 === $value || in_array( strtolower( (string) $value ), array( '1', 'on', 'yes', 'true', 'checked' ), true );
	}

	/**
	 * All defaults: module, then host, then the filter.
	 *
	 * @return array
	 */
	public static function defaults() {
		if ( null === self::$host_defaults ) {
			$host      = function_exists( 'qcld_wpbot_chat' ) ? qcld_wpbot_chat()->host() : null;
			$from_host = ( $host && method_exists( $host, 'option_defaults' ) ) ? (array) $host->option_defaults() : array();
			/**
			 * Defaults for options a site may not have stored yet.
			 *
			 * @param array $defaults Option name => default.
			 */
			self::$host_defaults = (array) apply_filters( 'qcld_wpbot_chat/option_defaults', array_merge( self::DEFAULTS, $from_host ) );
		}
		return self::$host_defaults;
	}

	/**
	 * Forget cached defaults (tests, host change).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$host_defaults = null;
	}

	/**
	 * A visitor-facing text option. These are stored serialized, as a list of
	 * variants, or per language (locale => text or list). One variant is picked.
	 *
	 * @param string $name     Option.
	 * @param string $language Locale, e.g. en_US; '' for the site's.
	 * @param string $fallback Text when the option is empty.
	 * @return string
	 */
	public static function text( $name, $language = '', $fallback = '' ) {
		$value = maybe_unserialize( get_option( $name, '' ) );
		if ( is_array( $value ) ) {
			$locale = '' !== $language ? $language : self::locale();
			if ( isset( $value[ $locale ] ) ) {
				$value = $value[ $locale ];
			} elseif ( ! self::is_list( $value ) ) {
				$value = reset( $value );
			}
		}
		if ( is_array( $value ) ) {
			$value = array_values( array_filter( array_map( 'strval', $value ), 'strlen' ) );
			$value = $value ? $value[ array_rand( $value ) ] : '';
		}
		$value = (string) $value;
		return '' !== trim( $value ) ? $value : $fallback;
	}

	/**
	 * Site locale.
	 *
	 * @return string
	 */
	public static function locale() {
		return function_exists( 'get_locale' ) ? get_locale() : 'en_US';
	}

	/**
	 * True for a list (0..n-1 keys).
	 *
	 * @param array $value Array.
	 * @return bool
	 */
	private static function is_list( array $value ) {
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
