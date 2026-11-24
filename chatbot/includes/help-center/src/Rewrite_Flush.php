<?php
/**
 * Flushes rewrite rules once after the Help Center is switched on or off.
 *
 * The switch is the option qcld_wpbot_help_center_enabled (Feature_Switch, saved from
 * AI / Settings). When it moves between on and off, a flag is set;
 * the rules are flushed once, late on init, on the first request whose Help
 * Center post type and rewrite rules have registered (on) or not (off).
 *
 * These listeners (and the knowledge cleanup they announce through
 * qcld_wpbot_help_center/switched) are the only code that runs while the Help
 * Center is off.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter;

defined( 'ABSPATH' ) || exit;

/**
 * Switch watcher.
 */
final class Rewrite_Flush {

	/**
	 * Flag option.
	 */
	const FLAG = 'qcld_wpbot_help_center_flush_rewrite';

	/**
	 * Version of the module's URL rules. Raise it when a release adds or changes a rule.
	 */
	const RULES = 4;

	/**
	 * Option holding the rules version the stored rewrite rules were built with.
	 */
	const RULES_OPTION = 'qcld_wpbot_help_center_rules';

	/**
	 * Transient: missing rules were rebuilt within the last minute.
	 */
	const HEALED = 'qcld_wpbot_help_center_rules_heal';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_option_' . Feature_Switch::OPTION, array( self::class, 'added' ), 10, 2 );
		add_action( 'update_option_' . Feature_Switch::OPTION, array( self::class, 'changed' ), 10, 2 );
		add_action( 'init', array( self::class, 'maybe_flush' ), 999 );
	}

	/**
	 * The switch option was created (first run, or moved from the old registry).
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New value.
	 * @return void
	 */
	public static function added( $option, $value ) {
		unset( $option );
		if ( self::is_on_in( $value ) ) {
			self::request();
			self::announce( true );
		}
	}

	/**
	 * The switch option changed.
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $value     New value.
	 * @return void
	 */
	public static function changed( $old_value, $value ) {
		$on = self::is_on_in( $value );
		if ( self::is_on_in( $old_value ) !== $on ) {
			self::request();
			self::announce( $on );
		}
	}

	/**
	 * Flush once if a change asked for it, on a request whose content model matches the new state.
	 *
	 * The request that saves the switch still runs with the old state (on: no post type yet;
	 * off: the post type is still registered). Flushing there would build the wrong rules and
	 * use up the flag, so it waits for the next request. When the Help Center is on and its rules are missing anyway (another plugin
	 * flushed in a request where it was not registered yet, a restored database), they are
	 * rebuilt, at most once a minute.
	 *
	 * @return void
	 */
	public static function maybe_flush() {
		$on         = Feature_Switch::is_on_value( get_option( Feature_Switch::OPTION ) );
		$registered = post_type_exists( Content\Post_Type::NAME );
		if ( $on !== $registered ) {
			return;
			// This request still runs with the other state.
		}
		$asked = (bool) get_option( self::FLAG );
		$stale = $on && self::RULES !== (int) get_option( self::RULES_OPTION, 0 );
		$heal  = $on && ! $asked && ! $stale && ! self::rules_present( get_option( 'rewrite_rules' ), Settings::base() ) && ! get_transient( self::HEALED );
		if ( ! $asked && ! $stale && ! $heal ) {
			return;
		}
		if ( $heal ) {
			set_transient( self::HEALED, 1, MINUTE_IN_SECONDS );
		}
		delete_option( self::FLAG );
		update_option( self::RULES_OPTION, self::RULES );
		flush_rewrite_rules( false );
	}

	/**
	 * Whether the stored rewrite rules include the Help Center's address.
	 *
	 * @param mixed  $rules Stored rules (option rewrite_rules); empty with plain permalinks.
	 * @param string $base  Help Center address.
	 * @return bool True also when there are no stored rules (plain permalinks).
	 */
	public static function rules_present( $rules, $base ) {
		if ( ! is_array( $rules ) || array() === $rules ) {
			return true;
		}
		foreach ( array_keys( $rules ) as $pattern ) {
			if ( false !== strpos( (string) $pattern, $base . '/' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a stored switch value is on.
	 *
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	public static function is_on_in( $value ) {
		return Feature_Switch::is_on_value( $value );
	}

	/**
	 * Tell the module the switch changed (Knowledge removes or re-reads the articles).
	 *
	 * @param bool $on Whether the Help Center is on now.
	 * @return void
	 */
	private static function announce( $on ) {
		/**
		 * The Help Center was switched on or off. Runs while it may be off.
		 *
		 * @param bool $on Whether it is on now.
		 */
		do_action( 'qcld_wpbot_help_center/switched', (bool) $on );
	}

	/**
	 * Ask for a flush on the next request.
	 *
	 * @return void
	 */
	private static function request() {
		update_option( self::FLAG, 1, false );
	}
}
