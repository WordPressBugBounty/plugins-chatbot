<?php
/**
 * AI Writer's side of WPBot Core's shared "is AI ready?" check (CR-5).
 *
 * WPBot Core decides whether AI can run for the writer module and why not
 * (no key, turned off, no model, failing key, budget reached). AI Writer
 * points that check at its own "Settings & AI Provider" screen, passes the
 * answer to its screens and editors, and loads the shared notice style.
 * With an older WPBot Core (no readiness check) nothing changes: the
 * failing-key notice is shown as before.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Admin;

use QuantumCloud\AiWriter\Module;

defined( 'ABSPATH' ) || exit;

/**
 * AI readiness for the writer module.
 */
final class Ai_Readiness {

	/**
	 * WPBot Core's script and style handle for the shared notice.
	 */
	const HANDLE = 'qcld-wpbot-core-ai-readiness';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'qcld_wpbot_core/ai_settings_url', array( __CLASS__, 'settings_url' ), 10, 2 );
	}

	/**
	 * Point the writer module's notice at AI Writer's own settings screen.
	 *
	 * @param string $url    URL so far.
	 * @param string $module Module id.
	 * @return string
	 */
	public static function settings_url( $url, $module ) {
		if ( Module::ID !== $module || ! current_user_can( Module::cap( 'settings' ) ) ) {
			return $url;
		}
		return Admin_Controller::item_url( 'settings' );
	}

	/**
	 * Whether WPBot Core has the readiness check.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'qcld_wpbot_core_ai_readiness' );
	}

	/**
	 * The current readiness, or null with an older WPBot Core.
	 *
	 * @return array|null status, ready, message, action_label, url, warning …
	 */
	public static function state() {
		if ( ! self::available() ) {
			return null;
		}
		$state = qcld_wpbot_core_ai_readiness( Module::ID );
		if ( ! is_array( $state ) ) {
			return null;
		}
		// Only people who can change settings get the button to them.
		if ( ! current_user_can( Module::cap( 'settings' ) ) ) {
			$state['url'] = '';
		}
		return $state;
	}

	/**
	 * Load the shared notice style (when WPBot Core registered it).
	 *
	 * @return void
	 */
	public static function enqueue_style() {
		if ( wp_style_is( self::HANDLE, 'registered' ) ) {
			wp_enqueue_style( self::HANDLE );
		}
	}
}
