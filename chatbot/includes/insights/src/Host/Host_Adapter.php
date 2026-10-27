<?php
/**
 * Contract between the module and the plugin that bundles it.
 *
 * The module never calls host functions directly. Everything it needs from
 * the host (menu placement, capability, branding, AI keys to copy) comes through here.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Host;

defined( 'ABSPATH' ) || exit;

interface Host_Adapter {

	/**
	 * Short machine id of the host, e.g. 'wpbot'.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Human name of the host, e.g. 'WPBot'.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Capability required to see and manage Insights.
	 *
	 * @return string
	 */
	public function get_capability();

	/**
	 * Slug of the host's top-level admin menu, or null for an own top-level menu.
	 *
	 * @return string|null
	 */
	public function get_menu_parent();

	/**
	 * Label of the Insights menu item and page title, e.g. 'Site Insight'.
	 *
	 * @return string
	 */
	public function get_menu_title();

	/**
	 * Submenu slugs to place the Insights item above, in order of preference.
	 * The first slug found in the parent menu wins; none found means last.
	 *
	 * @return string[]
	 */
	public function get_menu_before();

	/**
	 * AI keys and models saved in the host's own AI settings, copied once
	 * into Insights' AI settings (and again when the owner asks).
	 *
	 * Shape: array(
	 *     'active'    => 'openai',
	 *     'providers' => array( 'openai' => array( 'key' => '...', 'model' => 'gpt-4o-mini' ), ... ),
	 * )
	 * Provider ids: openai, claude, gemini, grok, openrouter, mistral, ollama.
	 * Self-hosted providers (ollama) pass 'url' (server address) instead of a key.
	 * Empty array when none.
	 *
	 * @return array<string,mixed>
	 */
	public function get_ai_import();

	/**
	 * Brand tokens. Keys (all optional): logo_url, primary, primary_dark,
	 * primary_soft, primary_border, page_bg, surface, text, muted, danger,
	 * success, radius, font_family.
	 *
	 * @return array<string,string>
	 */
	public function get_brand();

	/**
	 * Host admin stylesheets to load on Insights screens so they match the
	 * host's own pages. Each item: array( 'handle' => ..., 'src' => ..., 'ver' => ... ).
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_admin_styles();

	/**
	 * Print the host's admin page header (logo, product name, version).
	 */
	public function render_admin_header();

	/**
	 * Print the host's admin page footer, if any.
	 */
	public function render_admin_footer();

	/**
	 * URL of the host's settings screen (used for "Set up AI" links).
	 *
	 * @return string
	 */
	public function get_settings_url();

	/**
	 * URL of the host's AI settings screen.
	 *
	 * @return string
	 */
	public function get_ai_settings_url();
}
