<?php
/**
 * WPBot Social settings (one option, autoload off). SM1 holds the Advanced
 * tab; later milestones add Publishing, Links, Writing, Inbox and Team.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social;

defined( 'ABSPATH' ) || exit;

/**
 * Settings with defaults and sanitizing.
 */
final class Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'qcld_wpbot_social_settings';

	/**
	 * Defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'check_tokens'         => true,
			'runner'               => 'wp_cron',
			'log_days'             => 30,
			'delete_on_uninstall'  => false,
			'scopes'               => array( 'publish_pages', 'publish_instagram' ),
			'failure_email'        => true,
			'failure_email_to'     => '',
			'default_time'         => '09:30',
			'utm_enabled'          => true,
			'utm_medium'           => 'social',
			'auto_share_types'     => array( 'post' ),
			'auto_share_mode'      => 'draft',
			'auto_share_delay'     => 15,
			'auto_share_cap'       => 3,
			'auto_share_ai'        => true,
			'auto_share_skip_cats' => array(),
			'missed_after'         => 6,
			'resize_per_network'   => true,
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
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
	 * @param array $changes Changes.
	 * @return array<string,mixed> Saved settings.
	 */
	public static function update( array $changes ) {
		$all = self::all();
		foreach ( self::sanitize( $changes ) as $key => $value ) {
			$all[ $key ] = $value;
		}
		update_option( self::OPTION, $all, false );
		return $all;
	}

	/**
	 * Sanitize known keys.
	 *
	 * @param array $input Input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ) {
		$out = array();
		foreach ( array( 'check_tokens', 'delete_on_uninstall', 'failure_email', 'utm_enabled', 'auto_share_ai', 'resize_per_network' ) as $flag ) {
			if ( array_key_exists( $flag, $input ) ) {
				$out[ $flag ] = filter_var( $input[ $flag ], FILTER_VALIDATE_BOOLEAN );
			}
		}
		if ( isset( $input['runner'] ) ) {
			$out['runner'] = in_array( $input['runner'], array( 'wp_cron', 'server_cron' ), true ) ? $input['runner'] : 'wp_cron';
		}
		if ( isset( $input['log_days'] ) ) {
			$days            = (int) $input['log_days'];
			$out['log_days'] = in_array( $days, array( 7, 30, 90, 365 ), true ) ? $days : 30;
		}
		if ( isset( $input['failure_email_to'] ) ) {
			$out['failure_email_to'] = sanitize_email( (string) $input['failure_email_to'] );
		}
		if ( isset( $input['default_time'] ) ) {
			$out['default_time'] = preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', (string) $input['default_time'] ) ? (string) $input['default_time'] : '09:30';
		}
		if ( isset( $input['utm_medium'] ) ) {
			$medium            = sanitize_key( (string) $input['utm_medium'] );
			$out['utm_medium'] = '' !== $medium ? substr( $medium, 0, 30 ) : 'social';
		}
		if ( isset( $input['missed_after'] ) ) {
			$out['missed_after'] = min( 168, max( 0, (int) $input['missed_after'] ) );
		}
		if ( isset( $input['auto_share_types'] ) ) {
			$out['auto_share_types'] = array_values( array_intersect( array_map( 'sanitize_key', (array) $input['auto_share_types'] ), array_keys( Posts\Auto_Share::types() ) ) );
		}
		if ( isset( $input['auto_share_mode'] ) ) {
			$out['auto_share_mode'] = 'schedule' === $input['auto_share_mode'] ? 'schedule' : 'draft';
		}
		if ( isset( $input['auto_share_delay'] ) ) {
			$out['auto_share_delay'] = min( 1440, max( 0, (int) $input['auto_share_delay'] ) );
		}
		if ( isset( $input['auto_share_cap'] ) ) {
			$out['auto_share_cap'] = min( 20, max( 1, (int) $input['auto_share_cap'] ) );
		}
		if ( isset( $input['auto_share_skip_cats'] ) ) {
			$out['auto_share_skip_cats'] = array_values( array_filter( array_map( 'absint', (array) $input['auto_share_skip_cats'] ) ) );
		}
		if ( isset( $input['scopes'] ) ) {
			$allowed       = array_keys( Accounts\Meta_Scopes::options() );
			$out['scopes'] = array_values( array_intersect( array_map( 'sanitize_key', (array) $input['scopes'] ), $allowed ) );
		}
		return $out;
	}

	/**
	 * Settings to export: no accounts, no secrets.
	 *
	 * @return array<string,mixed>
	 */
	public static function export() {
		return array(
			'format'   => 'wpbot-social-settings',
			'version'  => 1,
			'settings' => self::all(),
		);
	}

	/**
	 * Import exported settings.
	 *
	 * @param array $data Exported data.
	 * @return bool
	 */
	public static function import( array $data ) {
		if ( ! isset( $data['format'], $data['settings'] ) || 'wpbot-social-settings' !== $data['format'] || ! is_array( $data['settings'] ) ) {
			return false;
		}
		self::update( $data['settings'] );
		return true;
	}
}
