<?php
/**
 * One-time notice about settings removed in round R2 (owner, 10 November 2026), shown only on
 * sites that used them:
 * - OpenAI Assistants (OpenAI shut the API down): the chatbot's engine now answers;
 * - the ChatKit workflow (OpenAI Agent mode): no longer used;
 * - the separate OpenAI fine-tuned model field: the chosen OpenAI model answers.
 * The saved values stay stored. Admins dismiss it once for the site.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Removed settings notice.
 */
final class Removed_Settings_Notice {

	/**
	 * Option: dismissed.
	 */
	const OPTION = 'qcld_wpbot_chat_removed_settings_notice';

	/**
	 * Dismiss action.
	 */
	const ACTION = 'qcld_wpbot_chat_dismiss_removed';

	/**
	 * Hook in (admin).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'dismiss' ) );
	}

	/**
	 * What this site used of the removed settings.
	 *
	 * @return string[] Sentences.
	 */
	public static function used() {
		$used = array();
		if ( '1' === (string) get_option( 'is_asst_enabled', '' ) ) {
			$used[] = __( 'OpenAI Assistants: OpenAI shut the Assistants API down, so the chatbot now answers with your OpenAI model, your Knowledge Base and your AI Settings.', 'chatbot' );
		}
		if ( '' !== trim( (string) get_option( 'chatkit_workflow_id', '' ) ) ) {
			$used[] = __( 'ChatKit workflow (OpenAI Agent): no longer used; the chatbot answers with your AI Settings.', 'chatbot' );
		}
		if ( '' !== trim( (string) get_option( 'qcld_openai_custom_model', '' ) ) ) {
			$used[] = __( 'Custom fine-tuned OpenAI model: the separate field is gone; the OpenAI model chosen in AI Settings answers.', 'chatbot' );
		}
		return $used;
	}

	/**
	 * Whether to show the notice.
	 *
	 * @return bool
	 */
	public static function due() {
		return current_user_can( 'manage_options' ) && ! get_option( self::OPTION ) && array() !== self::used();
	}

	/**
	 * The notice's text and dismiss link.
	 *
	 * @return string HTML.
	 */
	private static function html() {
		$items = '';
		foreach ( self::used() as $sentence ) {
			$items .= '<li>' . esc_html( $sentence ) . '</li>';
		}
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
		return '<p><strong>' . esc_html__( 'Some AI settings this site used were removed:', 'chatbot' ) . '</strong></p><ul style="list-style:disc;margin-left:20px">' . $items . '</ul><p>' . esc_html__( 'Your saved values are kept.', 'chatbot' ) . ' <a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'chatbot' ) . '</a></p>';
	}

	/**
	 * Admin notice (every admin screen, until dismissed; not on AI Settings, which shows it inline).
	 *
	 * @return void
	 */
	public static function render() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the page slug.
		if ( strtolower( Ai_Settings_Page::SLUG ) === $page || ! self::due() ) {
			return;
		}
		echo '<div class="notice notice-warning qcld-wpbot-removed-settings">' . self::html() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in html().
	}

	/**
	 * The notice inside AI Settings.
	 *
	 * @return void
	 */
	public static function render_inline() {
		if ( self::due() ) {
			echo '<div class="qcwp-notice is-warn qcld-wpbot-removed-settings">' . self::html() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in html().
		}
	}

	/**
	 * Dismiss for the site.
	 *
	 * @return void
	 */
	public static function dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'chatbot' ), 403 );
		}
		check_admin_referer( self::ACTION );
		update_option( self::OPTION, time(), false );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
