<?php
/**
 * The Help Center's on/off switch (owner decision H5: off by default).
 *
 * Owner, 19 November 2026: the Help Center is not a chatbot module. It is switched on from
 * AI / Settings (Admin\Ai_Settings_Section) and keeps its own option; it is not listed in
 * the Module Manager (Pro) or on the Features page (Free). Until 0.16 the switch lived in the
 * chatbot's module registry; the first read moves that choice to the option.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter;

defined( 'ABSPATH' ) || exit;

/**
 * Answers whether the Help Center is on, and switches it.
 */
final class Feature_Switch {

	/**
	 * The switch: '1' on, '' off.
	 */
	const OPTION = 'qcld_wpbot_help_center_enabled';

	/**
	 * Old registry id and stored path (until 0.16).
	 */
	const ID = 'help-center';

	/**
	 * Old stored path in the chatbot's module registry (until 0.16).
	 */
	const PATH = 'help-center/help-center.php';

	/**
	 * The chatbot's module registry option (until 0.16).
	 */
	const REGISTRY_OPTION = 'wpbot_master_addons';

	/**
	 * Whether the Help Center is on.
	 *
	 * @return bool
	 */
	public static function is_on() {
		/**
		 * Filters whether the Help Center is on.
		 *
		 * @param bool $on Whether the owner switched it on.
		 */
		return (bool) apply_filters( 'qcld_wpbot_help_center/enabled', self::stored() );
	}

	/**
	 * The stored choice. On the first read after an update, the old registry choice moves here.
	 *
	 * @return bool
	 */
	public static function stored() {
		$value = get_option( self::OPTION, null );
		if ( null === $value || false === $value ) {
			$value = self::migrate();
		}
		return self::is_on_value( $value );
	}

	/**
	 * Switch the Help Center on or off.
	 *
	 * @param bool $on On.
	 * @return void
	 */
	public static function set( $on ) {
		update_option( self::OPTION, $on ? '1' : '', true );
	}

	/**
	 * Whether a stored option value means on.
	 *
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	public static function is_on_value( $value ) {
		return is_scalar( $value ) && '1' === (string) $value;
	}

	/**
	 * Whether an old registry state had the Help Center active.
	 *
	 * @param mixed $state Registry state (array with 'active' => path => name).
	 * @return bool
	 */
	public static function was_on_in_registry( $state ) {
		return is_array( $state ) && isset( $state['active'] ) && is_array( $state['active'] ) && array_key_exists( self::PATH, $state['active'] );
	}

	/**
	 * Move the registry choice (until 0.16) to the option and drop the old entry.
	 *
	 * @return string The stored value.
	 */
	private static function migrate() {
		$state = get_option( self::REGISTRY_OPTION, array() );
		$value = self::was_on_in_registry( $state ) ? '1' : '';
		add_option( self::OPTION, $value, '', true );
		self::forget_registry_entry();
		return $value;
	}

	/**
	 * Remove the old Help Center entry from the chatbot's module registry (active and inactive
	 * lists), so the Module Manager no longer lists it. Runs on the first read and on admin
	 * requests while an entry is still stored.
	 *
	 * @return bool Whether an entry was removed.
	 */
	public static function forget_registry_entry() {
		$state = get_option( self::REGISTRY_OPTION, array() );
		if ( ! is_array( $state ) ) {
			return false;
		}
		$found = false;
		foreach ( array( 'active', 'inactive' ) as $list ) {
			if ( isset( $state[ $list ] ) && is_array( $state[ $list ] ) && array_key_exists( self::PATH, $state[ $list ] ) ) {
				unset( $state[ $list ][ self::PATH ] );
				$found = true;
			}
		}
		if ( $found ) {
			update_option( self::REGISTRY_OPTION, $state );
		}
		return $found;
	}
}
