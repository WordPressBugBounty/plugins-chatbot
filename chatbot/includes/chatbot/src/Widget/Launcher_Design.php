<?php
/**
 * Prints the new chat button design on the site (owner, 17 November 2026): the theme's CSS in the
 * head and the floating box script in the footer, inline, so it works with and without the lazy
 * launcher and needs no extra requests. Nothing is printed for the classic design.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Widget;

use QuantumCloud\Chatbot\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end output of the launcher theme.
 */
final class Launcher_Design {

	/**
	 * Masks for the close X and the send arrow (the icon's mask comes from Launcher_Icons).
	 */
	const MASKS = array(
		'x'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M7 7l10 10M17 7L7 17" stroke="#000" stroke-width="2.4" stroke-linecap="round"/></svg>',
		'send' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5 12h13M13 6l6 6-6 6" stroke="#000" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>',
	);

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( Launcher_Theme::class, 'remember_first_design' ), 4 );
		if ( is_admin() ) {
			return;
		}
		add_action( 'wp_head', array( __CLASS__, 'print_head' ), 2 );
		add_action( 'wp_footer', array( __CLASS__, 'print_footer' ), 99 );
	}

	/**
	 * Whether the design is printed on this request.
	 *
	 * @return bool
	 */
	public static function active() {
		$on = Launcher_Theme::modern() && ! Options::on( 'disable_wp_chatbot' ) && ! wp_is_json_request();
		/**
		 * Whether the new chat button design is printed on this page.
		 *
		 * @param bool $on On.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/launcher_design', $on );
	}

	/**
	 * Head: theme variables, the design's CSS and its classes on <html> (before the button paints).
	 *
	 * @return void
	 */
	public static function print_head() {
		if ( ! self::active() ) {
			return;
		}
		echo '<style id="qcld-wpbot-design-css">' . self::css() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme values are checked; the rest is the module's file.
		echo '<script>document.documentElement.classList.add(' . implode( ',', array_map( 'wp_json_encode', Launcher_Theme::classes() ) ) . ');</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON strings of fixed class names.
	}

	/**
	 * Footer: the floating box script and its settings.
	 *
	 * @return void
	 */
	public static function print_footer() {
		if ( ! self::active() ) {
			return;
		}
		$theme  = Launcher_Theme::get();
		$config = array(
			'layout'   => $theme['box']['layout'],
			'typing'   => (bool) $theme['box']['typing'] && $theme['motion']['enabled'],
			'ask'      => __( 'Type your question…', 'chatbot' ),
			'askLabel' => __( 'Ask a question in the chat', 'chatbot' ),
			'label'    => __( 'Chat with us', 'chatbot' ),
			'hi'       => __( 'Hi!', 'chatbot' ),
			'preview'  => Launcher_Preview::active(),
		);
		echo '<script type="application/json" id="qcld-wpbot-design-config">' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < and & escaped.
		echo '<script id="qcld-wpbot-design-js">' . self::asset( 'assets/js/launcher-design.js' ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the module's own file.
	}

	/**
	 * The design's CSS: theme variables, icon masks, then the module's stylesheet.
	 *
	 * @return string
	 */
	public static function css() {
		$icon  = Launcher_Theme::icon_shown();
		$masks = '--qcld-wpbot-mask-icon:' . Launcher_Icons::mask( Launcher_Icons::exists( $icon ) ? $icon : 'molecule' ) . ';';
		foreach ( self::MASKS as $name => $svg ) {
			$masks .= '--qcld-wpbot-mask-' . $name . ':url("data:image/svg+xml,' . rawurlencode( $svg ) . '");';
		}
		$masks .= '--qcld-wpbot-mask-hand:' . Launcher_Icons::mask( 'hand' ) . ';';
		$vars   = str_replace( '}', ';' . $masks . '}', Launcher_Theme::css_vars() );
		return $vars . self::asset( 'assets/css/launcher-design.css' );
	}

	/**
	 * A module file, comments and indentation removed, safe inside a style or script tag.
	 *
	 * @param string $rel Path from the module folder.
	 * @return string
	 */
	public static function asset( $rel ) {
		$file = dirname( __DIR__, 2 ) . '/' . $rel;
		$code = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		$code = preg_replace( '#/\*.*?\*/#s', '', $code );
		if ( '.js' === substr( $rel, -3 ) ) {
			$code = preg_replace( '#^\s*//.*$#m', '', $code );
			$code = str_replace( '</script', '<\/script', $code );
		} else {
			$code = str_replace( '</style', '<\/style', $code );
		}
		return trim( preg_replace( "/\n\s*/", "\n", $code ) );
	}
}
