<?php
/**
 * The new chat window on the front end (owner, 20 November 2026: one window, eight designs).
 *
 * The hosts (WPBot Pro, WPBot Free) ask the module to print the window through the
 * `qcld_wpbot_chat/window_render` filter just before they would include an old template; when it
 * returns true they print nothing themselves. The window keeps every ID, class and hook the
 * existing conversation script and the add-ons use, so they keep working unchanged. On the front
 * end the old theme option reads as "template-01" so the script takes its plain path (no Mini
 * mode or Extended UI special cases); the real value stays saved for the migration and the admin.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Window;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end window.
 */
final class Window {

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_chat/window_render', array( __CLASS__, 'render' ), 10, 2 );
		if ( is_admin() ) {
			return;
		}
		// Only while a front-end page is built (not WP-CLI, cron or REST, where the real value is read and saved).
		add_action( 'template_redirect', array( __CLASS__, 'front_end' ) );
		add_action( 'wp_head', array( __CLASS__, 'print_head' ), 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 30 );
	}

	/**
	 * A front-end page is being built: the old theme reads as template-01 from here on.
	 *
	 * @return void
	 */
	public static function front_end() {
		add_filter( 'pre_option_qcld_wb_chatbot_theme', array( __CLASS__, 'legacy_template' ) );
	}

	/**
	 * Whether the new window replaces the old templates (always, unless a site or test turns it off).
	 *
	 * @return bool
	 */
	public static function active() {
		/**
		 * Whether the new chat window replaces the old templates.
		 *
		 * @param bool $on Default true.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/new_window', true );
	}

	/**
	 * On the front end the old theme reads as template-01, the conversation script's plain path.
	 *
	 * @param mixed $value Value from an earlier filter (false = read the option).
	 * @return mixed
	 */
	public static function legacy_template( $value ) {
		return self::active() ? 'template-01' : $value;
	}

	/**
	 * Print the window for a host (filter callback).
	 *
	 * @param bool         $printed Whether something already printed it.
	 * @param array|string $args    context (floating, embedded, full), mode (light or dark, for
	 *                              [wpbot-page dark_design="true"]), and for embedded windows design
	 *                              (an available design id) and width (e.g. "400px"); or the context as a string.
	 * @return bool True when the window was printed.
	 */
	public static function render( $printed, $args = array() ) {
		if ( $printed || ! self::active() ) {
			return (bool) $printed;
		}
		$args    = is_array( $args ) ? $args : array( 'context' => (string) $args );
		$context = isset( $args['context'] ) && in_array( $args['context'], array( 'floating', 'embedded', 'full' ), true ) ? $args['context'] : 'floating';
		$mode    = isset( $args['mode'] ) && in_array( $args['mode'], array( 'light', 'dark' ), true ) ? $args['mode'] : '';
		$design  = isset( $args['design'] ) && 'embedded' === $context && Window_Designs::available( (string) $args['design'] ) ? (string) $args['design'] : '';
		$width   = isset( $args['width'] ) && preg_match( '/^\d{2,4}(px|%)$/', (string) $args['width'] ) ? (string) $args['width'] : '';
		Window_Render::output( $context, $mode, $design, $width );
		return true;
	}

	/**
	 * Where the window script reports a start menu click (ranks "Popular on this page").
	 *
	 * @return array { click: REST URL, page: post id of this page }
	 */
	public static function script_data() {
		return array(
			'click' => function_exists( 'rest_url' ) ? esc_url_raw( rest_url( \QuantumCloud\Chatbot\Rest\Routes::NS . '/window/click' ) ) : '',
			'page'  => function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0,
		);
	}

	/**
	 * The window script's texts (three-dot menu, toasts), translated.
	 *
	 * @return array
	 */
	public static function strings() {
		return array(
			'newChat'    => __( 'Start a new conversation', 'chatbot' ),
			'newConfirm' => __( 'Start a new conversation? This chat will be cleared.', 'chatbot' ),
			'cancel'     => __( 'Cancel', 'chatbot' ),
			'startOver'  => __( 'Start over', 'chatbot' ),
			'email'      => __( 'Email me this chat', 'chatbot' ),
			'download'   => __( 'Download the chat', 'chatbot' ),
			'sound'      => __( 'Sound', 'chatbot' ),
			'on'         => __( 'On', 'chatbot' ),
			'off'        => __( 'Off', 'chatbot' ),
			'textSize'   => __( 'Text size', 'chatbot' ),
			'smaller'    => __( 'Smaller text', 'chatbot' ),
			'larger'     => __( 'Larger text', 'chatbot' ),
			'language'   => __( 'Language', 'chatbot' ),
			'person'     => __( 'Talk to a person', 'chatbot' ),
			'personText' => __( 'I would like to talk to a person', 'chatbot' ),
			'started'    => __( 'New conversation started', 'chatbot' ),
			'saved'      => __( 'Chat saved as a text file', 'chatbot' ),
			'you'        => __( 'You', 'chatbot' ),
		);
	}

	/**
	 * Head: the colour tokens and the rule that keeps the window hidden until it opens (the
	 * stylesheet may load later, on the first click on the chat button).
	 *
	 * @return void
	 */
	public static function print_head() {
		if ( ! self::active() ) {
			return;
		}
		echo '<style id="qcld-window-tokens">' . Window_Render::tokens_css( Window_Theme::effective() ) . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from checked hex colours and numbers only.
	}

	/**
	 * Styles and script (the Lazy Loader may hold them until the first interaction).
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! self::active() ) {
			return;
		}
		$file = defined( 'QCLD_WPBOT_CHAT_FILE' ) ? QCLD_WPBOT_CHAT_FILE : dirname( __DIR__, 2 ) . '/chatbot.php';
		wp_enqueue_style( 'qcld-window', plugins_url( 'assets/window/window.css', $file ), array(), QCLD_WPBOT_CHAT_VERSION );
		wp_enqueue_script( 'qcld-window', plugins_url( 'assets/window/window.js', $file ), array(), QCLD_WPBOT_CHAT_VERSION, true );
		wp_add_inline_script( 'qcld-window', 'window.qcwStrings=' . wp_json_encode( self::strings() ) . ';window.qcwData=' . wp_json_encode( self::script_data() ) . ';', 'before' );
		/**
		 * Extra window styles and scripts (WPBot Pro's designs and motion).
		 *
		 * @param string $file The module's main file (for plugins_url()).
		 */
		do_action( 'qcld_wpbot_chat/window_enqueue', $file );
	}
}
