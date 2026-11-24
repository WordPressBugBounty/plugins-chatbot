<?php
/**
 * Prints the chat window (templates/window.php) and its colour tokens.
 *
 * Host data (agent picture and name, texts, start menu) is read here so the template stays the
 * same in WPBot Pro and WPBot Free.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Window;

defined( 'ABSPATH' ) || exit;

/**
 * Window output.
 */
final class Window_Render {

	/**
	 * Print the window.
	 *
	 * @param string $context floating, embedded or full.
	 * @param string $mode    light or dark to override the theme (empty: the theme's).
	 * @param string $design  Another available design for this window only (embedded; empty: the site's).
	 * @param string $width   Most width of an embedded window ("400px", "80%"; empty: full).
	 * @return void
	 */
	public static function output( $context, $mode = '', $design = '', $width = '' ) {
		$theme = Window_Theme::effective();
		$scope = '';
		if ( '' !== $design && $design !== $theme['design'] ) {
			// This window only: the design's own defaults, with colours scoped to its container.
			$theme = Window_Theme::effective_for( Window_Theme::sanitize( Window_Theme::defaults( $design ) ) );
			$scope = self::tokens_css( $theme );
		}
		if ( in_array( $mode, array( 'light', 'dark' ), true ) ) {
			$theme['mode'] = $mode;
		}
		$data          = self::data( $theme, $context );
		$data['scope'] = $scope;
		$data['width'] = $width;
		/**
		 * The data the window template uses.
		 *
		 * @param array  $data    Template data.
		 * @param array  $theme   Effective window theme.
		 * @param string $context floating, embedded or full.
		 */
		$data = (array) apply_filters( 'qcld_wpbot_chat/window_data', $data, $theme, $context );
		include dirname( __DIR__, 2 ) . '/templates/window.php';
	}

	/**
	 * Everything the template needs.
	 *
	 * @param array  $theme   Effective theme.
	 * @param string $context floating, embedded or full.
	 * @return array
	 */
	public static function data( array $theme, $context ) {
		$layout = $theme['layout'];
		if ( 'floating' !== $context ) {
			$layout = $context;
		}
		$classes = array(
			'qcw',
			'qcw-d-' . $theme['design'],
			'qcw-l-' . $layout,
			// The window opens on the chat button's side (Icons → Position), or where its own setting says.
			'qcw-side-' . ( 'left' === \QuantumCloud\Chatbot\Widget\Launcher_Position::side() ? 'left' : $theme['side'] ),
			'qcw-mat-' . $theme['material']['type'],
			'qcw-in-' . $theme['material']['inside'],
			'qcw-h-' . $theme['material']['header'],
			'qcw-m-' . $theme['motion']['messages'],
			'qcw-t-' . $theme['motion']['typing'],
			'qcw-feel-' . $theme['motion']['feel'],
			'qcw-sg-' . $theme['suggestionsStyle'],
			'qcw-sm-' . $theme['startMenuStyle'],
		);
		if ( 'holo' === $theme['design'] ) {
			$classes[] = 'qcw-hh-' . $theme['holo']['header'];
			$classes[] = 'qcw-hb-' . $theme['holo']['replies'];
			$classes[] = 'qcw-hs-' . $theme['holo']['send'];
		}
		foreach ( array( 'breathe', 'scan', 'stagger' ) as $flag ) {
			if ( $theme['motion'][ $flag ] ) {
				$classes[] = 'qcw-' . $flag;
			}
		}
		if ( $theme['push'] ) {
			$classes[] = 'qcw-push';
		}
		if ( $theme['customFonts'] ) {
			$classes[] = 'qcw-custom-fonts';
		}
		if ( '1' === (string) get_option( 'enable_wp_chatbot_rtl', '' ) ) {
			$classes[] = 'wp-chatbot-rtl';
		}
		$avatar = self::avatar_location();
		if ( 'side' !== $avatar ) {
			$classes[] = 'qcw-av-' . $avatar;
		}
		return array(
			'context'     => $context,
			'floating'    => 'floating' === $context,
			'theme'       => $theme,
			'classes'     => $classes,
			'mode'        => $theme['mode'],
			'name'        => self::text( 'qlcd_wp_chatbot_agent', 'Carrie' ),
			'agent'       => self::agent_image(),
			'placeholder' => self::text( 'qlcd_wp_chatbot_send_a_msg', __( 'Send a message', 'chatbot' ) ),
			'close'       => self::text( 'qlcd_wp_chatbot_close_lan', __( 'Close', 'chatbot' ) ),
			'reset'       => self::text( 'qlcd_wp_chatbot_reset_lan', __( 'Start a new conversation', 'chatbot' ) ),
			'transcript'  => self::transcript_on(),
			'bottom_menu' => '1' === (string) get_option( 'qcld_enable_the_bottom_start_menu', '' ) && $theme['guide']['bottom'],
			'menu'        => $theme['menu'],
			'guide'       => Window_Guidance::data( $theme ),
		);
	}

	/**
	 * CSS custom properties for the colour tokens (light, dark and the automatic switch), the
	 * size and the material. Only checked hex colours and numbers go in.
	 *
	 * @param array $theme Effective theme.
	 * @return string
	 */
	public static function tokens_css( array $theme ) {
		$light = self::vars( $theme['colors']['light'] );
		$dark  = self::vars( $theme['colors']['dark'] );
		$size  = '--qcw-w:' . (int) $theme['width'] . 'px;--qcw-h:' . (int) $theme['height'] . 'px;--qcw-sw:' . (int) $theme['sideWidth'] . 'px;--qcw-r:' . (int) $theme['radius'] . 'px;--qcw-blur:' . (int) $theme['material']['blur'] . 'px;--qcw-alpha:' . (int) $theme['material']['opacity'] . '%;';
		$css   = '.qcw{' . $size . $light . '}';
		$css  .= '.qcw[data-qcw-mode="dark"]{' . $dark . 'color-scheme:dark}';
		$css  .= '@media (prefers-color-scheme:dark){.qcw[data-qcw-mode="auto"]{' . $dark . 'color-scheme:dark}}';
		// Hidden until the conversation script opens it, even before the stylesheet arrives.
		$css .= '.qcw #wp-chatbot-board-container:not(.active-chat-board){display:none}';
		return $css;
	}

	/**
	 * One palette as CSS variables.
	 *
	 * @param array $palette Tokens.
	 * @return string
	 */
	private static function vars( array $palette ) {
		$out = '';
		foreach ( Window_Colors::ordered( $palette ) as $token => $hex ) {
			$out .= '--qcw-' . strtolower( preg_replace( '/([A-Z])/', '-$1', $token ) ) . ':' . $hex . ';';
		}
		return $out;
	}

	/**
	 * A text option the hosts store as a string, a serialized list or a list per language.
	 *
	 * @param string $name     Option name.
	 * @param string $fallback Text when empty.
	 * @return string
	 */
	public static function text( $name, $fallback ) {
		$value = maybe_unserialize( get_option( $name, '' ) );
		if ( is_array( $value ) ) {
			$locale = function_exists( 'get_locale' ) ? get_locale() : '';
			if ( isset( $value[ $locale ] ) ) {
				$value = $value[ $locale ];
			}
			while ( is_array( $value ) ) {
				$value = $value ? reset( $value ) : '';
			}
		}
		$value = trim( wp_strip_all_tags( (string) $value ) );
		return '' !== $value ? $value : $fallback;
	}

	/**
	 * The agent picture chosen or uploaded in the admin (Agent Image), as the hosts resolve it.
	 *
	 * @return string URL.
	 */
	public static function agent_image() {
		$base   = defined( 'QCLD_wpCHATBOT_IMG_URL' ) ? (string) QCLD_wpCHATBOT_IMG_URL : '';
		$chosen = (string) get_option( 'wp_chatbot_agent_image', '' );
		$custom = (string) get_option( 'wp_chatbot_custom_agent_path', '' );
		if ( 'custom-agent.png' === $chosen && '' !== $custom ) {
			$url = $custom;
		} elseif ( '' !== $chosen ) {
			$url = $base . $chosen;
		} else {
			$url = $base . 'custom-agent.png';
		}
		/**
		 * The agent picture in the chat window header.
		 *
		 * @param string $url URL.
		 */
		return esc_url_raw( (string) apply_filters( 'qcld_wpbot_chat/window_agent_image', $url ) );
	}

	/**
	 * Where the agent picture sits beside the chatbot's messages: "side" (beside the bubble, the
	 * default), "inside" (in the bubble) or "hidden". WPBot Free saves it under Customize ChatBot
	 * Design → Avatar Location (`qcld_chatbot_avatar_location`); hosts without the setting get "side".
	 *
	 * @return string side, inside or hidden.
	 */
	public static function avatar_location() {
		/**
		 * Where the agent picture sits beside the chatbot's messages.
		 *
		 * @param string $location side, inside or hidden.
		 */
		$location = (string) apply_filters( 'qcld_wpbot_chat/window_avatar_location', (string) get_option( 'qcld_chatbot_avatar_location', 'side' ) );
		return in_array( $location, array( 'inside', 'hidden' ), true ) ? $location : 'side';
	}

	/**
	 * Whether visitors can have the transcript emailed (Chat Sessions saves it; not switched off).
	 *
	 * @return bool
	 */
	private static function transcript_on() {
		$sessions = function_exists( 'qcld_wpbot_is_active_chat_history' ) ? true === qcld_wpbot_is_active_chat_history() : false;
		/**
		 * Whether the window offers "Email me this chat".
		 *
		 * @param bool $on Chat Sessions active and the transcript email not switched off.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/window_transcript', $sessions && '1' !== (string) get_option( 'disable_email_transcript', '' ) );
	}
}
