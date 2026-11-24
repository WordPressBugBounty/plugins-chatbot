<?php
/**
 * Help Center in WPBot Free: loads the bundled core module
 * (includes/help-center/) and registers WPBot Free as its host.
 *
 * The folder is a synced copy of qcld/wpbot-help-center (npm run
 * sync:help-center, the same commit WPBot Pro ships); never edit it here.
 * Help Center Pro is never bundled in WPBot Free.
 *
 * Until the module is synced this file does nothing. The module decides
 * whether it runs: it is off by default and switched on from AI / Settings
 * (its own option; it is not a chatbot module). When it is off it loads no
 * post type, menu or rewrite rules.
 *
 * Loaded from qcld-wpwbot.php after the chatbot module.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '7.4', '<' ) || ! file_exists( __DIR__ . '/help-center/help-center.php' ) ) {
	return;
}

if ( ! function_exists( 'qcld_wpbot_free_help_center_deactivate' ) ) {
	/**
	 * When WPBot Free is deactivated, drop the stored rewrite rules: WordPress rebuilds them on the
	 * next request, with the Help Center's addresses only if another host still runs it (so
	 * /help/ is a real 404 instead of the home page once no WPBot plugin is active).
	 *
	 * @return void
	 */
	function qcld_wpbot_free_help_center_deactivate() {
		delete_option( 'rewrite_rules' );
	}
	register_deactivation_hook( dirname( __DIR__ ) . '/qcld-wpwbot.php', 'qcld_wpbot_free_help_center_deactivate' );
}

if ( ! function_exists( 'qcld_wpbot_free_help_center_register' ) ) {
	/**
	 * Register WPBot Free as the Help Center host.
	 *
	 * @param object $module The booted module (\QuantumCloud\HelpCenter\Module).
	 * @return void
	 */
	function qcld_wpbot_free_help_center_register( $module ) {
		require_once __DIR__ . '/class-qcld-wpbot-free-help-center-adapter.php';
		$module->register_host( new QCLD_WPBot_Free_Help_Center_Adapter() );
	}
	add_action( 'qcld_wpbot_help_center/loaded', 'qcld_wpbot_free_help_center_register' );
}

// The core's loader boots the newest bundled copy on plugins_loaded, after the chatbot module.
require_once __DIR__ . '/help-center/help-center.php';
