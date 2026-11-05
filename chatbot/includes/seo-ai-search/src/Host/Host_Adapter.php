<?php
/**
 * What a host plugin (WPBot Pro now, WPBot Free later) tells the module.
 *
 * Every host-specific detail sits behind this interface: menu placement,
 * branding, Pro previews and the outreach mailer. The module never checks
 * which edition is installed.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Host;

defined( 'ABSPATH' ) || exit;

/**
 * Host adapter contract.
 */
interface Host_Adapter {

	/**
	 * Host id, e.g. "wpbot-pro".
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Host name shown to admins.
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
	 * Submenu slug(s) the item goes directly after; the first found wins.
	 *
	 * @return string|string[]
	 */
	public function get_menu_after();

	/**
	 * Brand values: primary, primary_dark, primary_soft, border, background,
	 * text, muted, danger, success, radius.
	 *
	 * @return array
	 */
	public function get_brand();

	/**
	 * Stylesheets for the module page: handles or [handle, src, ver].
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
	 * Inactive Pro preview items for the left menu (Free hosts only).
	 * Each: [ 'id' => string, 'label' => string, 'text' => string,
	 * 'features' => string[] (optional bullet list of what Pro adds),
	 * 'url' => string and 'button' => string (optional link to learn more),
	 * 'tab' => 'section:name' (optional: show it as an inactive tab inside a
	 * section instead of a left-menu item; sections keywords, technical, links,
	 * aiSearch; skipped when a tab with that name exists) ].
	 * Plain text only. Nothing is shown when the owner hides Pro previews.
	 *
	 * @return array
	 */
	public function get_pro_previews();

	/**
	 * URL of the module's settings in this host.
	 *
	 * @return string
	 */
	public function get_settings_url();

	/**
	 * Mailer for outreach sending (Pro): callable( $to, $subject, $html, $args ): bool|WP_Error, or null.
	 *
	 * @return callable|null
	 */
	public function get_mailer();
}
