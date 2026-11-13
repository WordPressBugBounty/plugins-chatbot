<?php
/**
 * ChatBot rate limits per user role and for visitors who are not logged in (AI Settings > Limits
 * and cost). The settings are the ones WPBot's "Common AI Settings" saved (is_rate_limiting_enabled,
 * rate_limit_{role}, rate_limit_timeframe_{role} in seconds, rate_limit_guest,
 * qlcd_wp_chatbot_ai_rate_limiting_message); until round R2 only the old answer handlers read them,
 * now the engine does too.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\RateLimit;

defined( 'ABSPATH' ) || exit;

/**
 * Role limits.
 */
final class Role_Limits {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_chat/rate_limit_text', array( __CLASS__, 'text' ) );
	}

	/**
	 * Whether the owner turned the role limits on.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return '1' === (string) get_option( 'is_rate_limiting_enabled', '0' );
	}

	/**
	 * The roles that can have a limit, plus 'guest'.
	 *
	 * @return array<string,string> role => name.
	 */
	public static function roles() {
		$roles = array();
		if ( function_exists( 'wp_roles' ) ) {
			foreach ( wp_roles()->get_names() as $role => $name ) {
				$roles[ sanitize_key( (string) $role ) ] = translate_user_role( (string) $name );
			}
		}
		$roles['guest'] = __( 'Visitors (not logged in)', 'chatbot' );
		return $roles;
	}

	/**
	 * Limit and window for a role: [ max (0 = none), window seconds ].
	 *
	 * @param string $role Role or 'guest'.
	 * @return int[]
	 */
	public static function limit( $role ) {
		$role   = sanitize_key( (string) $role );
		$max    = max( 0, (int) get_option( 'rate_limit_' . $role, 0 ) );
		$window = (int) get_option( 'rate_limit_timeframe_' . $role, 0 );
		$window = $window >= HOUR_IN_SECONDS ? $window : ( 'guest' === $role ? DAY_IN_SECONDS : 2 * DAY_IN_SECONDS );
		return array( $max, $window );
	}

	/**
	 * Count one AI answer for the current visitor; false when their limit is reached.
	 *
	 * @param string $ip Visitor IP (for visitors who are not logged in).
	 * @return bool
	 */
	public static function hit( $ip ) {
		$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
		if ( $user && ! empty( $user->ID ) ) {
			$roles = isset( $user->roles ) ? array_values( (array) $user->roles ) : array();
			$role  = $roles ? (string) $roles[0] : 'subscriber';
			$who   = 'u' . (int) $user->ID;
		} else {
			$role = 'guest';
			$who  = (string) $ip;
		}
		list( $max, $window ) = self::limit( $role );
		if ( 0 === $max ) {
			return true;
		}
		$key   = Limiter::key( 'ai_role', $who, $window );
		$count = (int) get_transient( $key );
		if ( $count >= $max ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}

	/**
	 * Save the limits.
	 *
	 * @param bool   $enabled On.
	 * @param array  $limits  role => [ max, hours ].
	 * @param string $message Message when the limit is reached ('' = default).
	 * @return void
	 */
	public static function save( $enabled, array $limits, $message ) {
		update_option( 'is_rate_limiting_enabled', $enabled ? 1 : 0 );
		$known = self::roles();
		foreach ( $limits as $role => $row ) {
			$role = sanitize_key( (string) $role );
			if ( ! isset( $known[ $role ] ) ) {
				continue;
			}
			$row   = (array) $row;
			$max   = isset( $row['max'] ) ? max( 0, (int) $row['max'] ) : 0;
			$hours = isset( $row['hours'] ) ? max( 1, min( 24 * 31, (int) $row['hours'] ) ) : 24;
			update_option( 'rate_limit_' . $role, $max );
			update_option( 'rate_limit_timeframe_' . $role, $hours * HOUR_IN_SECONDS );
		}
		update_option( 'qlcd_wp_chatbot_ai_rate_limiting_message', sanitize_text_field( $message ) );
	}

	/**
	 * The saved message when the limit is reached ('' = default).
	 *
	 * @return string
	 */
	public static function message() {
		$saved = maybe_unserialize( get_option( 'qlcd_wp_chatbot_ai_rate_limiting_message', '' ) );
		if ( is_array( $saved ) ) {
			$saved = (string) reset( $saved );
		}
		return is_string( $saved ) && '1' !== $saved ? trim( $saved ) : '';
	}

	/**
	 * The limit text: the owner's message when the role limits are on.
	 *
	 * @param string $text Default.
	 * @return string
	 */
	public static function text( $text ) {
		$saved = self::enabled() ? self::message() : '';
		return '' !== $saved ? $saved : $text;
	}
}
