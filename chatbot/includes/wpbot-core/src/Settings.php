<?php
/**
 * Shared settings of WPBot Core (budgets, job runner, data removal).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the qcld_wpbot_core_settings option.
 */
final class Settings {

	const OPTION = 'qcld_wpbot_core_settings';

	/**
	 * Defaults. Every limit is an owner setting; none is enforced by us.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'budgets'             => array(), // module => monthly USD, 0 = none.
			'cron_time_budget'    => 20,      // Seconds per WP-Cron batch.
			'max_step_attempts'   => 3,
			'delete_on_uninstall' => false,
		);
	}

	/**
	 * All settings merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Save a partial update after sanitising it.
	 *
	 * @param array $changes Key => value.
	 * @return array Saved settings.
	 */
	public static function update( array $changes ) {
		$all = self::all();

		if ( isset( $changes['budgets'] ) && is_array( $changes['budgets'] ) ) {
			foreach ( $changes['budgets'] as $module => $usd ) {
				$all['budgets'][ sanitize_key( $module ) ] = max( 0, round( (float) $usd, 2 ) );
			}
		}
		if ( isset( $changes['cron_time_budget'] ) ) {
			$all['cron_time_budget'] = min( 50, max( 5, absint( $changes['cron_time_budget'] ) ) );
		}
		if ( isset( $changes['max_step_attempts'] ) ) {
			$all['max_step_attempts'] = min( 10, max( 1, absint( $changes['max_step_attempts'] ) ) );
		}
		if ( isset( $changes['delete_on_uninstall'] ) ) {
			$all['delete_on_uninstall'] = (bool) $changes['delete_on_uninstall'];
		}

		update_option( self::OPTION, $all, false );
		return $all;
	}

	/**
	 * Monthly budget in USD for a module (0 = no budget).
	 *
	 * @param string $module Module id.
	 * @return float
	 */
	public static function budget( $module ) {
		$budgets = (array) self::get( 'budgets' );
		return isset( $budgets[ $module ] ) ? (float) $budgets[ $module ] : 0.0;
	}
}
