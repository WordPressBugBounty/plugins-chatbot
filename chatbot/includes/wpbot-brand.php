<?php
/**
 * The product logo on WPBot's admin screens (same as WPBot Pro, owner, 17 November 2026).
 *
 * One place decides which logo every admin header shows: the WPBot logo. WPBot Free uses the
 * chatbot module's copy (includes/chatbot/assets/img/wpbot-logo.webp) instead of shipping the
 * larger GIF WPBot Pro uses; without the module it falls back to images/chatbot.png. The chat
 * window's own icons (launcher, agent image) are separate settings and are not affected.
 *
 * @package Chatbot
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_brand_logo_is_custom' ) ) {
	/**
	 * Whether another logo replaces the WPBot logo (WPBot Pro's White Label; never in WPBot Free).
	 *
	 * @return bool
	 */
	function qcld_wpbot_brand_logo_is_custom() {
		return false;
	}
}

if ( ! function_exists( 'qcld_wpbot_brand_logo_url' ) ) {
	/**
	 * The logo shown in WPBot's admin headers.
	 *
	 * @return string URL.
	 */
	function qcld_wpbot_brand_logo_url() {
		$main = dirname( __DIR__ ) . '/qcld-wpwbot.php';
		$url  = file_exists( dirname( __DIR__ ) . '/includes/chatbot/assets/img/wpbot-logo.webp' )
			? plugins_url( 'includes/chatbot/assets/img/wpbot-logo.webp', $main )
			: plugins_url( 'images/chatbot.png', $main );
		/** This filter is documented in WPBot Pro's includes/wpbot-brand.php. */
		return (string) apply_filters( 'qcld_wpbot_brand_logo_url', $url, false );
	}
}
