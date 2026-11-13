<?php
/**
 * Host part of the chatbot engine's legacy widget AI bridge (wpbot-chatbot Legacy\Ai_Bridge):
 * keeps the "related pages" links the old per-provider handlers added under AI answers when
 * page suggestions are on. Same file in WPBot Pro and WPBot Free.
 *
 * @package Chatbot
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_ai_bridge_page_links' ) ) {
	/**
	 * Add page suggestions to a bridged AI answer.
	 *
	 * @param string $html  Answer HTML.
	 * @param object $reply Engine reply.
	 * @return string
	 */
	function qcld_wpbot_ai_bridge_page_links( $html, $reply ) {
		if ( '1' !== (string) get_option( 'page_suggestion_enabled' ) || ! class_exists( 'Qcld_WPBot_Common_Functions' ) || ! method_exists( 'Qcld_WPBot_Common_Functions', 'qcpd_relevant_pagelink' ) ) {
			return $html;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Guarded by the engine's legacy shims (same-site check, rate limit).
		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : ( isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '' );
		if ( '' === $keyword ) {
			return $html;
		}
		$links = array_slice( (array) Qcld_WPBot_Common_Functions::qcpd_relevant_pagelink( $keyword ), 0, 5, true );
		if ( ! $links ) {
			return $html;
		}
		$label  = maybe_unserialize( get_option( 'qlcd_wp_chatbot_relevant_post_link_openai' ) );
		$locale = function_exists( 'get_wpbot_locale' ) ? get_wpbot_locale() : get_locale();
		if ( is_array( $label ) ) {
			$label = isset( $label[ $locale ] ) ? $label[ $locale ] : reset( $label );
			$label = is_array( $label ) ? implode( '', $label ) : (string) $label;
		}
		return $html . '<p><em>' . esc_html( (string) $label ) . '</em></p><p>' . implode( '<br>', array_map( 'wp_kses_post', $links ) ) . '</p>';
	}
	add_filter( 'qcld_wpbot_chat/ai_bridge_html', 'qcld_wpbot_ai_bridge_page_links', 10, 2 );
}
