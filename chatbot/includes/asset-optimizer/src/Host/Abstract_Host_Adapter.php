<?php
/**
 * Base host adapter with defaults for the optional parts of the contract.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer\Host;

defined( 'ABSPATH' ) || exit;

/**
 * Hosts extend this class and override what differs.
 */
abstract class Abstract_Host_Adapter implements Host_Adapter {

	/**
	 * Default: administrators.
	 *
	 * @return string
	 */
	public function get_capability() {
		return 'manage_options';
	}

	/**
	 * CSS classes of the Performance tab's icon, in the icon font the host's
	 * settings screen loads. Default: Font Awesome (WPBot Pro).
	 *
	 * @return string
	 */
	public function get_tab_icon() {
		return 'fa fa-tachometer';
	}

	/**
	 * Default: a fresh install.
	 *
	 * @return bool
	 */
	public function is_existing_install() {
		return false;
	}

	/**
	 * Default: the settings screen is the only host screen we know about.
	 *
	 * @return bool
	 */
	public function is_host_screen() {
		return $this->is_settings_screen();
	}

	/**
	 * Whether the current front-end page shows the chatbot as part of its
	 * content (shortcode pages, mobile app page). Such pages keep normal,
	 * blocking style loading so the layout does not jump.
	 *
	 * @return bool
	 */
	public function is_embedded_page() {
		return false;
	}

	/**
	 * CSS selectors of the floating chatbot, hidden until its styles have
	 * loaded when styles load without blocking the page.
	 *
	 * @return string[]
	 */
	public function get_chatbot_selectors() {
		return array();
	}

	/**
	 * Style handles that are loaded only when the chatbot first needs them
	 * (for example icon fonts and animations), through the host's feature loader.
	 *
	 * @return string[]
	 */
	public function get_on_demand_styles() {
		return array();
	}

	/**
	 * Fresh, per-visitor data for cached pages: security tokens, visitor
	 * details and the live show/hide answer. Keys are the JS objects to
	 * update, values the properties to set.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fresh_data() {
		return array();
	}

	/**
	 * The visitor fields of get_fresh_data() as a logged-out visitor's page
	 * holds them. Pages printed for logged-in users carry these instead, and
	 * the guard fills in the real values from the fresh-data endpoint.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_anonymous_visitor_data() {
		return array();
	}

	/**
	 * Live values of the show/hide rules: open (opening hours), mobile, logged_in.
	 *
	 * @return array{open: bool, mobile: bool, logged_in: bool}
	 */
	public function get_live_values() {
		return array(
			'open'      => true,
			'mobile'    => wp_is_mobile(),
			'logged_in' => is_user_logged_in(),
		);
	}

	/**
	 * Names that mark the host's inline scripts, for optimisers' exclusion lists.
	 *
	 * @return string[]
	 */
	public function get_inline_markers() {
		return array();
	}

	/**
	 * Connect live show/hide rules: the host asks $enabled whether to use them
	 * on this page view and passes its rules table (eight "0"/"1" characters,
	 * index = open * 4 + mobile * 2 + logged_in) to $table. Default: the host
	 * has no live rules.
	 *
	 * @param callable $enabled Returns bool.
	 * @param callable $table   Receives the table.
	 * @return void
	 */
	public function register_live_rules( callable $enabled, callable $table ) {
	}
}
