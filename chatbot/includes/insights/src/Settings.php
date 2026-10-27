<?php
/**
 * Module settings stored in one option, with defaults chosen for shared hosting.
 * Every limit here is a setting the site owner can change (WordPress.org Guideline 5).
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights;

defined( 'ABSPATH' ) || exit;

final class Settings {

	const OPTION         = 'qcin_settings';
	const VERSION_OPTION = 'qcin_settings_version';
	const VERSION        = 2;

	/**
	 * Cached settings.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $cache = null;

	/**
	 * Default values.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'             => false,
			'sample_rate'         => 100,
			'heatmap_sample_rate' => 100,
			'daily_cap'           => 1000,
			'retention_days'      => 30,
			'storage_cap_mb'      => 1024,
			'exclude_roles'       => array( 'administrator', 'editor' ),
			'exclude_urls'        => '',
			'consent_mode'        => 'auto',
			'respect_dnt'         => true,
			'mask_all_text'       => false,
			'mask_selectors'      => '',
			'block_selectors'     => '',
			'delete_on_uninstall' => false,
		);
	}

	/**
	 * One-time changes to stored settings when defaults change.
	 *
	 * Version 2: the default share of sessions to record went from 25% to
	 * 100%. Sites still on the old default (25) move to 100; a value the
	 * owner changed is kept.
	 */
	public static function maybe_upgrade() {
		$version = (int) get_option( self::VERSION_OPTION, 1 );
		if ( $version >= self::VERSION ) {
			return;
		}
		$stored = get_option( self::OPTION, null );
		if ( is_array( $stored ) && isset( $stored['sample_rate'] ) && 25 === (int) $stored['sample_rate'] ) {
			$stored['sample_rate'] = 100;
			update_option( self::OPTION, $stored, false );
			self::$cache = null;
		}
		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Validate and save settings. Unknown keys are dropped.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed> Saved settings.
	 */
	public static function update( array $input ) {
		$current = self::all();
		$clean   = $current;

		$bools = array( 'enabled', 'respect_dnt', 'mask_all_text', 'delete_on_uninstall' );
		foreach ( $bools as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$clean[ $key ] = (bool) rest_sanitize_boolean( $input[ $key ] );
			}
		}

		$ints = array(
			'sample_rate'         => array( 1, 100 ),
			'heatmap_sample_rate' => array( 0, 100 ),
			'daily_cap'           => array( 1, 1000000 ),
			'retention_days'      => array( 1, 3650 ),
			'storage_cap_mb'      => array( 50, 1048576 ),
		);
		foreach ( $ints as $key => $range ) {
			if ( array_key_exists( $key, $input ) ) {
				$clean[ $key ] = max( $range[0], min( $range[1], (int) $input[ $key ] ) );
			}
		}

		if ( array_key_exists( 'consent_mode', $input ) ) {
			$mode                  = sanitize_key( $input['consent_mode'] );
			$clean['consent_mode'] = in_array( $mode, array( 'auto', 'always', 'never' ), true ) ? $mode : 'auto';
		}

		if ( array_key_exists( 'exclude_roles', $input ) ) {
			$roles                  = is_array( $input['exclude_roles'] ) ? $input['exclude_roles'] : array();
			$clean['exclude_roles'] = array_values( array_unique( array_map( 'sanitize_key', $roles ) ) );
		}

		foreach ( array( 'exclude_urls', 'mask_selectors', 'block_selectors' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$clean[ $key ] = self::clean_lines( (string) $input[ $key ] );
			}
		}

		update_option( self::OPTION, $clean, false );
		self::$cache = null;
		return self::all();
	}

	/**
	 * Clear the in-memory cache (tests, option changes).
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Split a textarea into trimmed, sanitized, non-empty lines.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function clean_lines( $text ) {
		$lines = preg_split( '/\r\n|\r|\n/', $text );
		$out   = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( wp_strip_all_tags( $line ) );
			if ( '' !== $line && strlen( $line ) <= 300 ) {
				$out[] = $line;
			}
		}
		return implode( "\n", array_slice( array_unique( $out ), 0, 100 ) );
	}

	/**
	 * Lines of a textarea setting as an array.
	 *
	 * @param string $key Setting key.
	 * @return string[]
	 */
	public static function lines( $key ) {
		$value = (string) self::get( $key, '' );
		return '' === $value ? array() : explode( "\n", $value );
	}
}
