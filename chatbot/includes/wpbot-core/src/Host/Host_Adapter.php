<?php
/**
 * Contract between WPBot Core and the plugin that bundles it.
 *
 * Everything host-specific (menus, branding, where the main AI settings and
 * the ChatBot's data live) goes through this interface, so the module itself
 * never refers to a particular plugin.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Host;

defined( 'ABSPATH' ) || exit;

interface Host_Adapter {

	/**
	 * Stable host id, for example "wpbot-pro".
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Human name of the host plugin.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Admin menu slug that suite pages (Permissions) are added under.
	 *
	 * @return string
	 */
	public function get_menu_parent();

	/**
	 * Brand values used as CSS variables on suite pages.
	 *
	 * Keys: primary, primary_dark, primary_soft, border, background, text,
	 * muted, danger, success, radius.
	 *
	 * @return array<string,string>
	 */
	public function get_brand();

	/**
	 * Host stylesheets to load on suite pages: registered handles, or
	 * arrays with handle, src and ver.
	 *
	 * @return array
	 */
	public function get_admin_styles();

	/**
	 * Print the host's admin page header.
	 *
	 * @return void
	 */
	public function render_admin_header();

	/**
	 * The host's main AI settings. Module key sets that follow AI Settings
	 * (the default since round R3) read them on every request; "own" sets copy
	 * them once.
	 *
	 * Shape: array(
	 *   'active'    => provider id or '',
	 *   'secondary' => backup provider id or '' (optional),
	 *   'providers' => array( id => array( 'key', 'model', 'enabled', 'url' ) ),
	 * )
	 *
	 * @return array
	 */
	public function get_ai_import();

	/**
	 * URL of the host's main AI settings screen.
	 *
	 * @return string
	 */
	public function get_ai_settings_url();

	/**
	 * Read-only chat data sources (Chat_Source objects) the host offers.
	 *
	 * @return \QuantumCloud\WPBotCore\Chat\Chat_Source[]
	 */
	public function get_chat_sources();

	/**
	 * Callable that searches the host's chatbot knowledge, or null.
	 *
	 * Signature: function( string $query, int $limit ): string|\WP_Error.
	 *
	 * @return callable|null
	 */
	public function get_knowledge_search();

	/**
	 * Callable that adds a published post to the host's chatbot knowledge, or null.
	 *
	 * Signature: function( int $post_id ): true|\WP_Error.
	 *
	 * @return callable|null
	 */
	public function get_knowledge_writer();
}
