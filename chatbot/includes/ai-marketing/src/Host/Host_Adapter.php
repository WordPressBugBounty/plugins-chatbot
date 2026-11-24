<?php
/**
 * What WPBot AI Marketing needs from the plugin that bundles it.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Host;

defined( 'ABSPATH' ) || exit;

/**
 * Host adapter.
 */
interface Host_Adapter {

	/**
	 * Host id, for example "wpbot-pro" or "wpbot-free".
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
	 * Extra admin styles: each [ handle, src, ver ] or array( handle, src, ver ) keys.
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
	 * How AI Marketing runs on this host (owner decisions D2 and D10, 19 Nov 2026):
	 * - "essentials": the core features (WPBot Free, Starter, Professional);
	 * - "master": the core features and the Pro module (WPBot Pro Master with an active license);
	 * - "off": paused; data is kept, menus, REST and jobs are off.
	 * Any other value counts as "off".
	 *
	 * @return string
	 */
	public function get_marketing_mode();

	/**
	 * URL of the host's AI settings.
	 *
	 * @return string
	 */
	public function get_ai_settings_url();

	/**
	 * Where to send someone who wants the Master features.
	 *
	 * @return string
	 */
	public function get_upgrade_url();

	/**
	 * The chatbot's saved subscribers (User Data), or null when the host has none.
	 *
	 * @return Lead_Source|null
	 */
	public function get_lead_source();
}
