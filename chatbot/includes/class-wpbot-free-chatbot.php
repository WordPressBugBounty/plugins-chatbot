<?php
/**
 * WPBot Free: loads the bundled chatbot core module (includes/chatbot/) and
 * registers WPBot Free as its host. Required by qcld-wpwbot.php after the
 * early exit that stops WPBot Free when WPBot Pro is active, so on a site with
 * both only Pro's copy runs.
 *
 * @package Chatbot
 */

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '7.4', '<' ) || ! file_exists( __DIR__ . '/chatbot/chatbot.php' ) ) {
	return;
}

if ( ! function_exists( 'qcld_wpbot_free_chatbot_register' ) ) {
	/**
	 * Register WPBot Free as the chatbot module's host.
	 *
	 * @param \QuantumCloud\Chatbot\Module $module Module.
	 * @return void
	 */
	function qcld_wpbot_free_chatbot_register( $module ) {
		require_once __DIR__ . '/class-wpbot-free-chatbot-adapter.php';
		$module->register_host( new QCLD_WPBot_Free_Chatbot_Adapter() );
	}
	add_action( 'qcld_wpbot_chat/loaded', 'qcld_wpbot_free_chatbot_register' );
}

if ( ! function_exists( 'qcld_wpbot_free_chatbot_features' ) ) {
	/**
	 * Optional features (Help Desk and later ones): Free has no Module Manager, so the module's
	 * Features page lists them, and the ones the owner turned on are loaded here.
	 *
	 * @return void
	 */
	function qcld_wpbot_free_chatbot_features() {
		if ( ! function_exists( 'qcld_wpbot_chat_modules' ) ) {
			return;
		}
		foreach ( qcld_wpbot_chat_modules()->active_files() as $qcld_wpbot_free_feature_file ) {
			require_once $qcld_wpbot_free_feature_file;
		}
	}
	add_filter( 'qcld_wpbot_chat/features_page', '__return_true' );
	add_action( 'qcld_wpbot_chat/host_ready', 'qcld_wpbot_free_chatbot_features' );
}

require_once __DIR__ . '/chatbot/chatbot.php';
