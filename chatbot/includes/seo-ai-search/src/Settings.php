<?php
/**
 * Module settings: one option (autoload off), defaults, sanitising.
 *
 * Secrets (PageSpeed and Bing keys) are kept apart in Secrets, encrypted.
 * AI keys live in WPBot Core's per-module key set ("seo").
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the settings option.
 */
final class Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'qcld_wpbot_seo_settings';

	/**
	 * Allowed values for choice fields.
	 */
	const ENUMS = array(
		'email_day' => array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ),
	);

	/**
	 * Default values (plan 7.2).
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Connections and data.
			'use_analytics'          => true,
			'storage_months'         => 16,
			'audit_history_months'   => 12,
			'country'                => '',
			'language'               => '',
			// Scans.
			'scan_batch'             => 10,
			// llms.txt (R4): off until the owner turns it on.
			'llms_enabled'           => false,
			'llms_summary'           => '',
			'llms_details'           => '',
			'llms_max'               => 100,
			'llms_types'             => array( 'page', 'post', 'product' ),
			'llms_pinned'            => array(),
			'llms_excluded'          => array(),
			// Research: ask before a run that costs more than this (USD; 0 = always ask).
			'research_confirm_above' => 0.5,
			'excluded_paths'         => array(),
			// Automation on publish.
			'audit_on_publish'       => true,
			'auto_meta'              => false,
			'automation_types'       => array( 'post', 'page', 'product' ),
			// Redirects and 404s.
			'log_404'                => true,
			'keep_404_days'          => 30,
			'simple_redirects'       => true,
			// AI crawlers: id => allow|block (empty = allow).
			'ai_crawlers'            => array(),
			// Results.
			'results_days'           => array( 14, 28, 90 ),
			// Email summary.
			'email_enabled'          => true,
			'email_recipients'       => array(),
			'email_day'              => 'monday',
			'hide_email_footer'      => false,
			// Opportunity rules.
			'near_min_position'      => 8,
			'near_max_position'      => 20,
			'min_impressions'        => 100,
			'decay_drop_percent'     => 30,
			// Admin.
			'hide_pro_previews'      => false,
			'cited_youtube'          => false,
			'cited_platforms'        => array(),
			'cited_disclosure'       => '',
			'gbp_on'                 => false,
			'delete_on_uninstall'    => false,
		);
	}

	/**
	 * All settings, saved values over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$all   = array_merge( self::defaults(), array_intersect_key( $saved, self::defaults() ) );
		/**
		 * Settings after defaults; extensions read their own keys from the
		 * same option through qcld_wpbot_seo/settings_fields.
		 */
		return (array) apply_filters( 'qcld_wpbot_seo/settings', $all );
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
	 * Save changes (unknown keys are ignored).
	 *
	 * @param array $changes Key => value.
	 * @return array All settings after saving.
	 */
	public static function update( array $changes ) {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		foreach ( self::defaults() as $key => $default_value ) {
			if ( array_key_exists( $key, $changes ) ) {
				$saved[ $key ] = self::sanitize( $key, $changes[ $key ], $default_value );
			}
		}
		update_option( self::OPTION, $saved, false );
		return self::all();
	}

	/**
	 * Clean one value.
	 *
	 * @param string $key     Key.
	 * @param mixed  $value   Value.
	 * @param mixed  $fallback Default (sets the type).
	 * @return mixed
	 */
	public static function sanitize( $key, $value, $fallback ) {
		switch ( $key ) {
			case 'storage_months':
				return self::clamp( $value, 3, 600 );
			case 'audit_history_months':
				return self::clamp( $value, 1, 120 );
			case 'scan_batch':
				return self::clamp( $value, 1, 50 );
			case 'keep_404_days':
				return self::clamp( $value, 1, 365 );
			case 'near_min_position':
				return self::clamp( $value, 2, 50 );
			case 'near_max_position':
				return self::clamp( $value, 3, 100 );
			case 'min_impressions':
				return self::clamp( $value, 1, 1000000 );
			case 'decay_drop_percent':
				return self::clamp( $value, 5, 95 );
			case 'llms_summary':
				return substr( sanitize_text_field( (string) $value ), 0, 300 );
			case 'llms_details':
				return substr( sanitize_textarea_field( (string) $value ), 0, 2000 );
			case 'llms_max':
				return self::clamp( $value, 5, 1000 );
			case 'llms_types':
				return array_values( array_filter( self::list_of( $value, 20, 40, 'key' ), 'post_type_exists' ) );
			case 'llms_pinned':
			case 'llms_excluded':
				return array_slice( array_values( array_unique( array_filter( array_map( 'absint', is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value ) ) ) ) ), 0, 200 );
			case 'research_confirm_above':
				return round( max( 0.0, min( 1000.0, (float) $value ) ), 2 );
			case 'country':
				return strtoupper( substr( (string) preg_replace( '/[^A-Za-z]/', '', (string) $value ), 0, 2 ) );
			case 'language':
				return strtolower( substr( (string) preg_replace( '/[^A-Za-z]/', '', (string) $value ), 0, 3 ) );
			case 'excluded_paths':
				return self::list_of( $value, 50, 200, 'path' );
			case 'cited_disclosure':
				return substr( sanitize_text_field( (string) $value ), 0, 160 );
			case 'cited_platforms':
				return self::list_of( $value, 20, 30, 'key' );
			case 'automation_types':
				return self::list_of( $value, 20, 40, 'key' );
			case 'email_recipients':
				return array_values( array_filter( array_map( 'sanitize_email', self::list_of( $value, 10, 200, 'text' ) ), 'is_email' ) );
			case 'results_days':
				$days = array_map( 'absint', (array) $value );
				$days = array_values(
					array_unique(
						array_filter(
							$days,
							static function ( $day ) {
								return $day >= 7 && $day <= 365;
							}
						)
					)
				);
				sort( $days );
				return array() === $days ? $fallback : array_slice( $days, 0, 5 );
			case 'ai_crawlers':
				$out = array();
				foreach ( (array) $value as $id => $rule ) {
					$id = sanitize_key( $id );
					if ( '' !== $id && in_array( $rule, array( 'allow', 'block' ), true ) ) {
						$out[ $id ] = $rule;
					}
				}
				return $out;
		}
		if ( isset( self::ENUMS[ $key ] ) ) {
			return in_array( $value, self::ENUMS[ $key ], true ) ? $value : $fallback;
		}
		if ( is_bool( $fallback ) ) {
			return (bool) rest_sanitize_boolean( $value );
		}
		if ( is_int( $fallback ) ) {
			return (int) $value;
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Integer within bounds.
	 *
	 * @param mixed $value Value.
	 * @param int   $min   Lowest.
	 * @param int   $max   Highest.
	 * @return int
	 */
	private static function clamp( $value, $min, $max ) {
		return max( $min, min( $max, (int) $value ) );
	}

	/**
	 * A cleaned list from an array or a newline/comma separated string.
	 *
	 * @param mixed  $value Value.
	 * @param int    $max   Most items.
	 * @param int    $chars Most characters per item.
	 * @param string $kind  path|key|text.
	 * @return string[]
	 */
	private static function list_of( $value, $max, $chars, $kind ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\r\n,]+/', $value );
		}
		$out = array();
		foreach ( (array) $value as $item ) {
			$item = trim( (string) $item );
			if ( 'key' === $kind ) {
				$item = sanitize_key( $item );
			} elseif ( 'path' === $kind ) {
				$item = '/' . ltrim( (string) preg_replace( '/[^A-Za-z0-9\/_.*\-]/', '', $item ), '/' );
				$item = '/' === $item ? '' : $item;
			} else {
				$item = sanitize_text_field( $item );
			}
			if ( '' !== $item ) {
				$out[] = substr( $item, 0, $chars );
			}
		}
		return array_slice( array_values( array_unique( $out ) ), 0, $max );
	}
}
