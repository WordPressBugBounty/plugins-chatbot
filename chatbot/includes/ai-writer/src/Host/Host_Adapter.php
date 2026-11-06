<?php
/**
 * Contract between AI Writer and the plugin that bundles it.
 *
 * Menus, branding and edition-specific lists (Pro previews) come from the
 * host, so the module never names a plugin or checks for an edition.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Host;

defined( 'ABSPATH' ) || exit;

interface Host_Adapter {

	/**
	 * Stable host id, for example "wpbot-pro".
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Host name.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Parent menu slug for the AI Writer screen.
	 *
	 * @return string
	 */
	public function get_menu_parent();

	/**
	 * Menu label.
	 *
	 * @return string
	 */
	public function get_menu_title();

	/**
	 * Submenu slug (or list of slugs, first found wins) to insert AI Writer
	 * before; '' or an empty list appends it.
	 *
	 * @return string|string[]
	 */
	public function get_menu_before();

	/**
	 * Brand values (same keys as WPBot Core's adapter).
	 *
	 * @return array<string,string>
	 */
	public function get_brand();

	/**
	 * Host stylesheets for AI Writer screens: registered handles, or arrays
	 * with handle, src and ver.
	 *
	 * @return array
	 */
	public function get_admin_styles();

	/**
	 * Print the host's page header.
	 *
	 * @return void
	 */
	public function render_admin_header();

	/**
	 * Inactive "Pro" previews to show (empty in hosts that include Pro).
	 *
	 * Each: array( 'id' => string, 'label' => string, 'where' => screen id ).
	 *
	 * @return array[]
	 */
	public function get_pro_previews();

	/**
	 * URL of the host's main settings.
	 *
	 * @return string
	 */
	public function get_settings_url();
}
