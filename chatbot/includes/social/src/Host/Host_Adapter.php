<?php
/**
 * What WPBot Social needs from the plugin that bundles it.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Host;

defined( 'ABSPATH' ) || exit;

/**
 * Host adapter.
 */
interface Host_Adapter {

	/**
	 * Host id, for example "wpbot-pro".
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
	 * Parent menu slug.
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
	 * Brand values for the Shell: primary, primary_dark, primary_soft, text, muted, background, radius.
	 *
	 * @return array<string,string>
	 */
	public function get_brand();

	/**
	 * Extra admin styles: each [ handle, src, ver ].
	 *
	 * @return array[]
	 */
	public function get_admin_styles();

	/**
	 * Print the host's header above the Shell.
	 *
	 * @return void
	 */
	public function render_admin_header();

	/**
	 * License level: "master", "professional", "starter" or "" (none).
	 * WPBot Social runs only on "master" (owner decision, build-brief A1).
	 *
	 * @return string
	 */
	public function get_license();

	/**
	 * URL of the host's AI settings.
	 *
	 * @return string
	 */
	public function get_ai_settings_url();
}
