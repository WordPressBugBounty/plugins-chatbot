<?php
/**
 * Contract between the core module and the plugin that bundles it.
 *
 * Everything WPBot-specific (handles, capability, where the tab appears,
 * embedded-chatbot pages, endpoint data) comes from the host adapter, so the
 * core stays the same in WPBot Free and WPBot Pro.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer\Host;

defined( 'ABSPATH' ) || exit;

/**
 * Host adapter interface. Extend Abstract_Host_Adapter rather than
 * implementing this directly, so new optional methods get defaults.
 */
interface Host_Adapter {

	/**
	 * Stable host id, for example "wpbot-pro" or "wpbot-free".
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Package slug, used as the cache subfolder: "pro" or "free".
	 *
	 * @return string
	 */
	public function get_package();

	/**
	 * Capability that may open the Performance tab and change its settings:
	 * the same one ChatBot Settings uses.
	 *
	 * @return string
	 */
	public function get_capability();

	/**
	 * Absolute path of the host plugin's folder. Only files inside it are ever read.
	 *
	 * @return string
	 */
	public function get_root_dir();

	/**
	 * URL of the host plugin's folder.
	 *
	 * @return string
	 */
	public function get_root_url();

	/**
	 * The host's main plugin file (for the deactivation hook).
	 *
	 * @return string
	 */
	public function get_plugin_file();

	/**
	 * WPBot core handles the cache may process.
	 *
	 * @return array{script: string[], style: string[]}
	 */
	public function get_handles();

	/**
	 * Whether the host was already installed before this module first ran
	 * (the cache then starts off and the admin is invited to turn it on).
	 *
	 * @return bool
	 */
	public function is_existing_install();

	/**
	 * Whether the current admin screen belongs to the host (for notices and assets).
	 *
	 * @return bool
	 */
	public function is_host_screen();

	/**
	 * Whether the current admin request is the ChatBot Settings screen.
	 *
	 * @return bool
	 */
	public function is_settings_screen();

	/**
	 * Admin URL of the Performance tab.
	 *
	 * @return string
	 */
	public function get_settings_url();

	/**
	 * Hook the Performance tab into the host's settings screen.
	 *
	 * @param callable $render_nav     Prints the tab's navigation item.
	 * @param callable $render_section Prints the tab's content section.
	 * @return void
	 */
	public function register_tab( callable $render_nav, callable $render_section );
}
