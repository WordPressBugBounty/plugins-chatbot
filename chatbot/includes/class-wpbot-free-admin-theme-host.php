<?php
/**
 * WPBot Free as a host of the stand-alone admin theme module (includes/admin-theme/, see its README.md).
 *
 * WPBot Free's pages: everything in its menu (wpbot_openAi), and every admin page whose callback is
 * in this plugin's folder. When WPBot Pro is active, WPBot Free stops before this file loads and
 * Pro's copy of the module runs instead (users keep the same choice: it is stored per user).
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/admin-theme/admin-theme.php';

add_filter(
	'qcld_admin_theme_plugins',
	function ( $plugins ) {
		$plugins['wpbot-free'] = array(
			'name'  => 'WPBot',
			'dirs'  => array( dirname( __DIR__ ) ),
			'menus' => array( 'wpbot_openAi' ),
			// WPBot's own post types: their list, edit and taxonomy screens (Help Center articles and their
			// knowledge bases, categories, tags and glossary; AI Forms entries; support tickets, canned replies).
			'post_types' => array( 'wpbot_article', 'wpbot_form_entry', 'qcld_ticket', 'qcld_ticket_thread', 'qcld_canned_reply' ),
			// WPBot's own markup: the shared page header and its font (same file as WPBot Pro).
			'css'   => plugins_url( 'css/admin-theme-wpbot.css', __DIR__ ),
			// The live chat widget preview keeps the colours the owner chose.
			'skip'  => array( '#wp-chatbot-chat-container' ),
		);
		return $plugins;
	}
);
