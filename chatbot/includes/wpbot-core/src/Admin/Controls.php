<?php
/**
 * One look for checkboxes, radio buttons and on/off switches on every WPBot
 * screen (owner, 11 Nov 2026: "make a global checkbox component").
 *
 * - Plain checkboxes and radio buttons on WPBot screens get the shared tick
 *   style automatically (assets/css/controls.css). Inputs hidden by older
 *   screens' own label tricks stay hidden.
 * - A switch is opt-in: `<label class="wpbot-switch"><input type="checkbox"> Text</label>`.
 * - A tick row: `<label class="wpbot-check"><input type="checkbox"> Text</label>`.
 * - `class="wpbot-native"` on an input keeps WordPress's own look.
 *
 * WPBot screens are the pages under the host's menu (and the shell's
 * pages); other plugins add theirs with the
 * `qcld_wpbot_core/ui_screen` filter.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Admin;

use QuantumCloud\WPBotCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Shared form controls.
 */
final class Controls {

	const HANDLE = 'qcld-wpbot-core-controls';

	/**
	 * Body class on WPBot screens.
	 */
	const BODY_CLASS = 'qcwpbot-ui';

	/**
	 * Hook into the admin.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ), 20 );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Register and load the stylesheet, with the host's brand colour.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( wp_style_is( self::HANDLE, 'enqueued' ) ) {
			return;
		}
		$version = defined( 'QCLD_WPBOT_CORE_VERSION' ) ? QCLD_WPBOT_CORE_VERSION : false;
		wp_enqueue_style( self::HANDLE, Core::url( 'assets/css/controls.css' ), array(), $version );
		$css = self::brand_css();
		if ( '' !== $css ) {
			wp_add_inline_style( self::HANDLE, $css );
		}
	}

	/**
	 * Load the controls on WPBot screens.
	 *
	 * @return void
	 */
	public static function maybe_enqueue() {
		if ( self::is_wpbot_screen() ) {
			self::enqueue();
		}
	}

	/**
	 * Add the body class on WPBot screens.
	 *
	 * @param string $classes Space-separated classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		if ( self::is_wpbot_screen() ) {
			$classes = trim( (string) $classes . ' ' . self::BODY_CLASS );
		}
		return $classes;
	}

	/**
	 * Whether the current admin page is a WPBot screen.
	 *
	 * @return bool
	 */
	public static function is_wpbot_screen() {
		$is   = false;
		$host = Core::instance()->host();
		if ( $host && function_exists( 'get_admin_page_parent' ) ) {
			$parent = (string) $host->get_menu_parent();
			$is     = '' !== $parent && 'options-general.php' !== $parent && get_admin_page_parent() === $parent;
		}
		/**
		 * Whether the current admin page gets WPBot's shared controls.
		 *
		 * @param bool $is Whether it is a page under the host's menu.
		 */
		return (bool) apply_filters( 'qcld_wpbot_core/ui_screen', $is );
	}

	/**
	 * The host's primary colour as a CSS variable (for screens outside the shell).
	 *
	 * @return string
	 */
	private static function brand_css() {
		$host  = Core::instance()->host();
		$brand = $host ? (array) $host->get_brand() : array();
		if ( empty( $brand['primary'] ) || ! preg_match( '/^#[0-9a-fA-F]{3,8}$/', (string) $brand['primary'] ) ) {
			return '';
		}
		return 'body.' . self::BODY_CLASS . '{--wpbot-ui-primary:' . $brand['primary'] . ';}';
	}
}
