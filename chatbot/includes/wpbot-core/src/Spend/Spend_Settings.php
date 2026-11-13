<?php
/**
 * The owner's spending settings: period, limits, paused groups and alerts.
 *
 * One small option, autoloaded because every AI call reads it. Every limit is the owner's
 * choice: by default nothing is limited (0 = no limit).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Spend;

use QuantumCloud\WPBotCore\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes qcld_wpbot_core_spend.
 */
final class Spend_Settings {

	const OPTION = 'qcld_wpbot_core_spend';

	/**
	 * Highest limit accepted, in USD.
	 */
	const MAX_LIMIT = 1000000;

	/**
	 * Request-level cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'period'           => 'monthly',
			'reset_day'        => 1,
			'reset_weekday'    => 1,
			'global'           => 0.0,
			'limits'           => array(),
			'paused'           => array(),
			'thresholds'       => array( 50, 80, 100 ),
			'recipients'       => array(),
			'prefix'           => '',
			'retention_days'   => 90,
			'retention_months' => 13,
			'spike_factor'     => 5.0,
			'spike_minimum'    => 1.0,
			'weekly_summary'   => true,
			'summary_weekday'  => 1,
			'migrated'         => false,
		);
	}

	/**
	 * All settings, with old module budgets moved in once.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$saved = get_option( self::OPTION, array() );
		$all   = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		if ( empty( $all['migrated'] ) ) {
			$all = self::migrate_budgets( $all );
			update_option( self::OPTION, $all, true );
		}
		self::$cache = $all;
		return $all;
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
	 * Limit of a group (0 = none).
	 *
	 * @param string $group Group id.
	 * @return float
	 */
	public static function limit( $group ) {
		$limits = (array) self::get( 'limits' );
		return isset( $limits[ $group ] ) ? (float) $limits[ $group ] : 0.0;
	}

	/**
	 * Whether the owner paused a group.
	 *
	 * @param string $group Group id.
	 * @return bool
	 */
	public static function is_paused( $group ) {
		return in_array( $group, (array) self::get( 'paused' ), true );
	}

	/**
	 * Save a partial update after sanitising it.
	 *
	 * @param array $changes Key => value.
	 * @return array Saved settings.
	 */
	public static function update( array $changes ) {
		$all = self::sanitize( $changes, self::all() );
		update_option( self::OPTION, $all, true );
		self::$cache = $all;
		self::sync_module_budgets( $all );

		/**
		 * Spending settings were saved (jobs paused by a limit get another chance).
		 *
		 * @param array $all Settings.
		 */
		do_action( 'qcld_wpbot_core/spend_settings_saved', $all );
		return $all;
	}

	/**
	 * Merge sanitised changes into current settings (pure).
	 *
	 * @param array $changes Changes.
	 * @param array $all     Current settings.
	 * @return array
	 */
	public static function sanitize( array $changes, array $all ) {
		if ( isset( $changes['period'] ) ) {
			$all['period'] = 'weekly' === $changes['period'] ? 'weekly' : 'monthly';
		}
		if ( isset( $changes['reset_day'] ) ) {
			$all['reset_day'] = min( 28, max( 1, (int) $changes['reset_day'] ) );
		}
		if ( isset( $changes['reset_weekday'] ) ) {
			$all['reset_weekday'] = min( 7, max( 1, (int) $changes['reset_weekday'] ) );
		}
		if ( isset( $changes['global'] ) ) {
			$all['global'] = self::money( $changes['global'] );
		}
		if ( isset( $changes['limits'] ) && is_array( $changes['limits'] ) ) {
			foreach ( $changes['limits'] as $group => $usd ) {
				$group = sanitize_key( (string) $group );
				if ( Features::exists( $group ) ) {
					$all['limits'][ $group ] = self::money( $usd );
				}
			}
		}
		if ( isset( $changes['paused'] ) && is_array( $changes['paused'] ) ) {
			$paused = array();
			foreach ( $changes['paused'] as $group ) {
				$group = sanitize_key( (string) $group );
				if ( Features::exists( $group ) ) {
					$paused[] = $group;
				}
			}
			$all['paused'] = array_values( array_unique( $paused ) );
		}
		if ( isset( $changes['thresholds'] ) && is_array( $changes['thresholds'] ) ) {
			$values = array();
			foreach ( $changes['thresholds'] as $value ) {
				$value = (int) $value;
				if ( $value >= 1 && $value <= 100 ) {
					$values[] = $value;
				}
			}
			$values = array_values( array_unique( $values ) );
			sort( $values );
			$all['thresholds'] = array_slice( $values, 0, 5 );
		}
		if ( isset( $changes['recipients'] ) ) {
			$list = is_array( $changes['recipients'] ) ? $changes['recipients'] : preg_split( '/[\s,;]+/', (string) $changes['recipients'] );
			$out  = array();
			foreach ( (array) $list as $email ) {
				$email = sanitize_email( (string) $email );
				if ( '' !== $email && is_email( $email ) ) {
					$out[] = strtolower( $email );
				}
			}
			$all['recipients'] = array_slice( array_values( array_unique( $out ) ), 0, 10 );
		}
		if ( isset( $changes['prefix'] ) ) {
			$all['prefix'] = substr( sanitize_text_field( (string) $changes['prefix'] ), 0, 40 );
		}
		if ( isset( $changes['retention_days'] ) ) {
			$all['retention_days'] = min( 365, max( 7, (int) $changes['retention_days'] ) );
		}
		if ( isset( $changes['retention_months'] ) ) {
			$all['retention_months'] = min( 36, max( 1, (int) $changes['retention_months'] ) );
		}
		if ( isset( $changes['spike_factor'] ) ) {
			$all['spike_factor'] = min( 50.0, max( 2.0, round( (float) $changes['spike_factor'], 1 ) ) );
		}
		if ( isset( $changes['spike_minimum'] ) ) {
			$all['spike_minimum'] = min( 1000.0, max( 0.1, round( (float) $changes['spike_minimum'], 2 ) ) );
		}
		if ( isset( $changes['weekly_summary'] ) ) {
			$all['weekly_summary'] = (bool) $changes['weekly_summary'];
		}
		if ( isset( $changes['summary_weekday'] ) ) {
			$all['summary_weekday'] = min( 7, max( 1, (int) $changes['summary_weekday'] ) );
		}
		return $all;
	}

	/**
	 * Move the old monthly module budgets (WPBot Core settings "budgets") into group limits,
	 * once. A group fed by several modules keeps the highest of their budgets (pure).
	 *
	 * @param array $all Settings.
	 * @return array
	 */
	public static function migrate_budgets( array $all ) {
		$budgets = (array) Settings::get( 'budgets' );
		foreach ( $budgets as $module => $usd ) {
			$usd   = self::money( $usd );
			$group = Features::group_of_module( (string) $module );
			if ( $usd > 0 && ( ! isset( $all['limits'][ $group ] ) || (float) $all['limits'][ $group ] < $usd ) ) {
				$all['limits'][ $group ] = $usd;
			}
		}
		$all['migrated'] = true;
		return $all;
	}

	/**
	 * Keep the old module budgets equal to their group limits, so code that still reads
	 * Settings::budget() sees the same number as the spend screens.
	 *
	 * @param array $all Spend settings.
	 * @return void
	 */
	private static function sync_module_budgets( array $all ) {
		$budgets = (array) Settings::get( 'budgets' );
		$changed = false;
		foreach ( $budgets as $module => $usd ) {
			$group = Features::group_of_module( (string) $module );
			$limit = isset( $all['limits'][ $group ] ) ? (float) $all['limits'][ $group ] : 0.0;
			if ( (float) $usd !== $limit ) {
				$budgets[ $module ] = $limit;
				$changed            = true;
			}
		}
		if ( $changed ) {
			Settings::update( array( 'budgets' => $budgets ) );
		}
	}

	/**
	 * USD amount, two decimals, within bounds.
	 *
	 * @param mixed $value Value.
	 * @return float
	 */
	public static function money( $value ) {
		return min( (float) self::MAX_LIMIT, max( 0.0, round( (float) $value, 2 ) ) );
	}

	/**
	 * Forget the request cache (tests, and after another process saved).
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}
}
