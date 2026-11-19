<?php
/**
 * Live preview for the chat button designer (owner, 18 November 2026).
 *
 * The designer shows the site's own front page in a frame, with the theme being edited applied on
 * top of the saved one, so the preview is exactly what visitors will see. Only a user who may
 * change the design, with a valid nonce, gets the override; everyone else gets the site as it is.
 * Small changes (finish, color, motion) are sent to the frame and applied at once (launcher-design.js);
 * changes that rebuild the floating box or the icon reload the frame with the new values.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Widget;

use QuantumCloud\Chatbot\Admin\Launcher_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Designer preview.
 */
final class Launcher_Preview {

	const ARG   = 'qcld_wpbot_preview';
	const NONCE = 'qcld_wpbot_preview';
	const THEME = 'qcld_wpbot_theme';
	const ICON  = 'qcld_wpbot_icon';
	const PATH  = 'qcld_wpbot_icon_path';

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_chat/launcher_theme', array( __CLASS__, 'theme' ), 99 );
		add_filter( 'option_wp_chatbot_icon', array( __CLASS__, 'icon' ), 99 );
		add_filter( 'option_wp_chatbot_custom_icon_path', array( __CLASS__, 'icon_path' ), 99 );
		add_action( 'template_redirect', array( __CLASS__, 'frame' ) );
	}

	/**
	 * Whether this request is a valid designer preview.
	 *
	 * @return bool
	 */
	public static function active() {
		static $active = null;
		if ( null === $active ) {
			$active = false;
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the nonce is verified here.
			if ( ! is_admin() && isset( $_GET[ self::ARG ], $_GET['_wpnonce'] ) && function_exists( 'wp_get_current_user' ) ) {
				$active = (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::NONCE )
					&& current_user_can( Launcher_Settings::capability() );
			}
			// phpcs:enable
		}
		return $active;
	}

	/**
	 * The frame's address for the designer.
	 *
	 * @return string
	 */
	public static function url() {
		return add_query_arg(
			array(
				self::ARG  => '1',
				'_wpnonce' => wp_create_nonce( self::NONCE ),
			),
			home_url( '/' )
		);
	}

	/**
	 * The edited theme on top of the saved one (checked by Launcher_Theme::sanitize() after this).
	 *
	 * @param array $theme Saved theme.
	 * @return array
	 */
	public static function theme( $theme ) {
		if ( ! self::active() || empty( $_GET[ self::THEME ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in active().
			return $theme;
		}
		$edited = json_decode( wp_unslash( (string) $_GET[ self::THEME ] ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked in active(); every value is checked by Launcher_Theme::sanitize().
		if ( ! is_array( $edited ) ) {
			return $theme;
		}
		foreach ( $edited as $key => $value ) {
			if ( is_array( $value ) && isset( $theme[ $key ] ) && is_array( $theme[ $key ] ) ) {
				$theme[ $key ] = array_merge( $theme[ $key ], $value );
			} else {
				$theme[ $key ] = $value;
			}
		}
		return $theme;
	}

	/**
	 * The icon picked in the designer (a file name from the hosts' Icons list).
	 *
	 * @param mixed $icon Saved icon.
	 * @return mixed
	 */
	public static function icon( $icon ) {
		if ( ! self::active() || empty( $_GET[ self::ICON ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in active().
			return $icon;
		}
		$picked = sanitize_file_name( wp_unslash( (string) $_GET[ self::ICON ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in active().
		return '' !== $picked ? $picked : $icon;
	}

	/**
	 * A custom icon just uploaded in the designer, before it is saved.
	 *
	 * @param mixed $path Saved address.
	 * @return mixed
	 */
	public static function icon_path( $path ) {
		if ( ! self::active() || empty( $_GET[ self::PATH ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in active().
			return $path;
		}
		$url = esc_url_raw( wp_unslash( (string) $_GET[ self::PATH ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in active().
		return '' !== $url ? $url : $path;
	}

	/**
	 * In the frame, for both designs: the designer's "Replay page load" and "Open or close the chat",
	 * and the chat widget loads at once (the lazy launcher would wait for the first click).
	 *
	 * @return void
	 */
	public static function frame_script() {
		echo '<script>(function(){'
			. 'function face(){return document.querySelector("#wp-chatbot-ball .wp-chatbot-ball");}'
			. 'function load(then){if(window.qcldWpbotChatLoad){window.qcldWpbotChatLoad(then);}else if(then){then();}}'
			. 'window.addEventListener("load",function(){load();});'
			. 'window.addEventListener("message",function(e){var d=e.data;if(e.origin!==window.location.origin||!d||"qcld-wpbot-preview"!==d.type){return;}'
			. 'if(d.replay){var b=document.getElementById("wp-chatbot-ball");if(b){b.style.animation="none";void b.offsetWidth;b.style.animation="";}}'
			// On small screens the hosts hide the button while the chat is open: close with the window's own close button.
			. 'if(d.toggle){load(function(){var f=face();var b=document.getElementById("wp-chatbot-ball");'
			. 'if(document.querySelector(".active-chat-board")&&(!b||!b.offsetParent)){var c=[].slice.call(document.querySelectorAll("#wp-chatbot-desktop-close,#wp-chatbot-mobile-close,#wp-chatbot-desktop-close1,.wp-chatbot-start-close")).filter(function(x){return x.offsetParent;})[0];if(c){c.click();return;}}'
			. 'if(f){f.click();}});}'
			. '});'
			. '})();</script>' . "\n";
	}

	/**
	 * In the frame: no admin bar, and the floating box shows even if it was dismissed earlier.
	 *
	 * @return void
	 */
	public static function frame() {
		if ( ! self::active() ) {
			return;
		}
		show_admin_bar( false );
		add_action(
			'wp_head',
			static function () {
				echo '<script>try{sessionStorage.removeItem("wpChatbotNotification");}catch(e){}</script>' . "\n";
			},
			0
		);
		add_action( 'wp_footer', array( __CLASS__, 'frame_script' ), 100 );
	}
}
