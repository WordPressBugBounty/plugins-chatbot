<?php
/**
 * Help Center admin (HC1-06): menu entries, Organize, Settings, the editor
 * panel and the link to the chatbot's knowledge gaps. Only while it is on.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Admin;

use QuantumCloud\HelpCenter\Content\Post_Type;

defined( 'ABSPATH' ) || exit;

/**
 * Admin entry point.
 */
final class Admin {

	/**
	 * Hook everything in.
	 *
	 * @return void
	 */
	public static function init() {
		Order::init();
		Organize_Page::init();
		Settings_Page::init();
		Design_Page::init();
		Analytics_Page::init();
		Start_Page::init();
		Menu::init();
		Bulk_Publish::init();
		Ai_Page::init();
		add_action( 'admin_menu', array( self::class, 'gaps_link' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'editor_panel' ) );
	}

	/**
	 * Page slug of "Knowledge Gaps" (forwards to the chatbot's Train screen).
	 */
	const GAPS_SLUG = 'wpbot-help-center-gaps';

	/**
	 * "Knowledge Gaps" under Help Center: one gap list (decision M5), the chatbot's Train screen.
	 * A page of its own that forwards there, because the ChatBot hub shows Train only as a tab
	 * and no menu link may point at it (chatbot rebuild R1).
	 *
	 * @return void
	 */
	public static function gaps_link() {
		if ( ! class_exists( '\QuantumCloud\Chatbot\Admin\Train_Page' ) ) {
			return;
		}
		$hook = add_submenu_page(
			'edit.php?post_type=' . Post_Type::NAME,
			__( 'Knowledge gaps', 'chatbot' ),
			__( 'Knowledge Gaps', 'chatbot' ),
			\QuantumCloud\Chatbot\Admin\Train_Page::capability(),
			self::GAPS_SLUG,
			'__return_null',
			80
		);
		if ( $hook ) {
			add_action( 'load-' . $hook, array( self::class, 'go_to_gaps' ) );
		}
	}

	/**
	 * Forward to the Train screen (the source lets it filter later, qcld/wpbot-chatbot#29).
	 *
	 * @return void
	 */
	public static function go_to_gaps() {
		wp_safe_redirect( admin_url( 'admin.php?page=' . \QuantumCloud\Chatbot\Admin\Train_Page::SLUG . '&source=help-center' ) );
		exit;
	}

	/**
	 * The "Help Center" panel in the article editor.
	 *
	 * @return void
	 */
	public static function editor_panel() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Post_Type::NAME !== $screen->post_type ) {
			return;
		}
		$file    = ( defined( 'QCLD_WPBOT_HELP_CENTER_DIR' ) ? QCLD_WPBOT_HELP_CENTER_DIR : dirname( __DIR__, 2 ) ) . '/help-center.php';
		$version = defined( 'QCLD_WPBOT_HELP_CENTER_VERSION' ) ? QCLD_WPBOT_HELP_CENTER_VERSION : '0';
		wp_enqueue_script( 'wpbot-help-editor-panel', plugins_url( 'assets/editor-panel.js', $file ), array( 'wp-plugins', 'wp-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-core-data', 'wp-data' ), $version, true );
		wp_set_script_translations( 'wpbot-help-editor-panel', 'chatbot' );
		wp_add_inline_script( 'wpbot-help-editor-panel', 'window.qcldWpbotHelpCenterPanel = ' . wp_json_encode( array( 'canPublish' => current_user_can( 'publish_wpbot_articles' ) ) ) . ';', 'before' );
	}
}
