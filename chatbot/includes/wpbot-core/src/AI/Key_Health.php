<?php
/**
 * Remembers failing keys so screens can show a bold notice with the fix.
 *
 * A failure is recorded per module and provider; the next successful call
 * clears it. Modules read notices() and render them in their own style.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Failing-key status store.
 */
final class Key_Health {

	const OPTION = 'qcld_wpbot_core_key_health';

	/**
	 * Record a failure if its cause needs the owner.
	 *
	 * @param string $module   Module id.
	 * @param string $provider Provider id.
	 * @param string $cause    Error_Classifier cause.
	 * @return void
	 */
	public static function record_failure( $module, $provider, $cause ) {
		if ( ! in_array( $cause, Error_Classifier::HEALTH_CAUSES, true ) ) {
			return;
		}
		$all        = self::load();
		$definition = Providers::get( $provider );
		$explained  = Error_Classifier::explain( $cause, $definition ? $definition['label'] : $provider );
		$previous   = isset( $all[ $module ][ $provider ] ) ? $all[ $module ][ $provider ] : array();

		$all[ $module ][ $provider ] = array(
			'cause_code' => $cause,
			'cause'      => $explained['cause'],
			'fix'        => $explained['fix'],
			'first_seen' => isset( $previous['first_seen'] ) && $previous['cause_code'] === $cause ? $previous['first_seen'] : time(),
			'last_seen'  => time(),
		);
		self::save( $all );

		/**
		 * Fires when a module's key status changes.
		 *
		 * @param string $module   Module id.
		 * @param string $provider Provider id.
		 * @param string $cause    Cause, or '' when cleared.
		 */
		do_action( 'qcld_wpbot_core/key_status_changed', $module, $provider, $cause );
	}

	/**
	 * Clear a failure after a successful call.
	 *
	 * @param string $module   Module id.
	 * @param string $provider Provider id.
	 * @return void
	 */
	public static function record_success( $module, $provider ) {
		$all = self::load();
		if ( ! isset( $all[ $module ][ $provider ] ) ) {
			return;
		}
		unset( $all[ $module ][ $provider ] );
		if ( array() === $all[ $module ] ) {
			unset( $all[ $module ] );
		}
		self::save( $all );

		/** This action is documented in Key_Health::record_failure(). */
		do_action( 'qcld_wpbot_core/key_status_changed', $module, $provider, '' );
	}

	/**
	 * Current notices for a module.
	 *
	 * @param string $module Module id.
	 * @return array[] Each: provider, provider_label, cause_code, cause, fix, since.
	 */
	public static function notices( $module ) {
		$all     = self::load();
		$notices = array();
		foreach ( isset( $all[ $module ] ) ? (array) $all[ $module ] : array() as $provider => $entry ) {
			$definition = Providers::get( $provider );
			$notices[]  = array(
				'provider'       => (string) $provider,
				'provider_label' => $definition ? $definition['label'] : (string) $provider,
				'cause_code'     => (string) $entry['cause_code'],
				'cause'          => (string) $entry['cause'],
				'fix'            => (string) $entry['fix'],
				'since'          => (int) $entry['first_seen'],
			);
		}
		return $notices;
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

	/**
	 * Save the option.
	 *
	 * @param array $all Data.
	 * @return void
	 */
	private static function save( array $all ) {
		update_option( self::OPTION, $all, false );
	}
}
