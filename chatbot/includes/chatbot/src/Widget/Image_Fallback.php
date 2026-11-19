<?php
/**
 * The chat button icon and the agent pictures are never shown broken (owner, 18 November 2026).
 *
 * A saved picture can stop working: a file an older version shipped, a deleted upload, an address
 * on another site. The hosts already swap a missing built-in file for the default when the page is
 * built; this catches the rest in the browser. A small script, printed first in the head, listens
 * for images that fail to load inside the chat (the button, the floating box and the chat window,
 * in every template) and shows the default icon or agent picture instead; if that fails too, the
 * image is hidden rather than shown as a broken picture.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end image fallback.
 */
final class Image_Fallback {

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		if ( is_admin() ) {
			return;
		}
		add_action( 'wp_head', array( __CLASS__, 'print_script' ), 1 );
	}

	/**
	 * The default pictures (the host's images folder).
	 *
	 * @return array{icon:string,agent:string}
	 */
	public static function defaults() {
		$base = defined( 'QCLD_wpCHATBOT_IMG_URL' ) ? (string) QCLD_wpCHATBOT_IMG_URL : '';
		$urls = array(
			'icon'  => '' !== $base ? $base . 'icon-0.png' : '',
			'agent' => '' !== $base ? $base . 'agent-0.png' : '',
		);
		/**
		 * The pictures shown when a chat icon or agent picture does not load.
		 *
		 * @param array $urls icon, agent.
		 */
		$urls = (array) apply_filters( 'qcld_wpbot_chat/image_fallbacks', $urls );
		return array(
			'icon'  => isset( $urls['icon'] ) ? esc_url_raw( (string) $urls['icon'] ) : '',
			'agent' => isset( $urls['agent'] ) ? esc_url_raw( (string) $urls['agent'] ) : '',
		);
	}

	/**
	 * The script (also used by the tests).
	 *
	 * @return string
	 */
	public static function script() {
		$urls = self::defaults();
		// Error events do not bubble, so the listener runs in the capture phase; it is in the head,
		// before any chat markup, so no failure is missed.
		return '(function(d,u){'
			. 'var chat="#wp-chatbot-chat-container,#wp-chatbot-ball,#wp-chatbot-notification-container,.wp-chatbot-ball,#wp-chatbot-board-container,.wpbot-saas-live-chat,#wp-chatbot-messages-container";'
			. 'd.addEventListener("error",function(e){var i=e.target;'
			. 'if(!i||i.tagName!=="IMG"||!i.closest||!i.closest(chat))return;'
			. 'var n=+(i.getAttribute("data-qcld-wpbot-fb")||0);'
			. 'var f=i.closest("#wp-chatbot-ball,.wp-chatbot-ball")?u.icon:u.agent;'
			. 'if(n<1&&f&&i.src!==f){i.setAttribute("data-qcld-wpbot-fb","1");i.removeAttribute("srcset");i.src=f;return;}'
			. 'i.setAttribute("data-qcld-wpbot-fb","2");i.style.visibility="hidden";'
			. '},true);'
			. '})(document,' . wp_json_encode( $urls, JSON_HEX_TAG ) . ');';
	}

	/**
	 * Head: the script.
	 *
	 * @return void
	 */
	public static function print_script() {
		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return;
		}
		echo '<script id="qcld-wpbot-image-fallback">' . self::script() . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed code; the two addresses are JSON-encoded.
	}
}
