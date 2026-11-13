<?php
/**
 * WPBot Core's shared form controls (ticks, radio buttons, switches) on the ChatBot screens
 * opened from the ChatBot tabs. They are registered without a parent menu, so WPBot Core does not
 * count them as WPBot screens (chatbot features round 6, QA: their checkboxes looked broken).
 *
 * @package WPBot Free
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_free_ui_screen' ) ) {
	/**
	 * Whether the current admin page is a ChatBot screen without a parent menu.
	 *
	 * @param bool $is Whether WPBot Core already counts it (main menu).
	 * @return bool
	 */
	function qcld_wpbot_free_ui_screen( $is ) {
		if ( $is ) {
			return $is;
		}
		$page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Which screen is open; nothing is saved.
		$pages = array( 'wpbot', 'wbcs-botsessions-page', 'wbcs-botsessions-reports', 'wpbot_ai_actions', 'simple-text-response', 'email-subscription', 'wpbot_support_page', 'wpbot_help_page' );
		/**
		 * ChatBot screens without a parent menu that get WPBot's shared controls.
		 *
		 * @param string[] $pages Page slugs.
		 */
		$pages = (array) apply_filters( 'qcld_wpbot_ui_hidden_pages', $pages );
		return '' !== $page && in_array( $page, $pages, true );
	}
	add_filter( 'qcld_wpbot_core/ui_screen', 'qcld_wpbot_free_ui_screen' );
}
