<?php
/**
 * Launcher first, widget on demand.
 *
 * The widget's styles and scripts (the host's, found by URL) are taken out of
 * the page's queue; the page gets a small inline launcher (assets/js/launcher.js)
 * and a JSON list of what to load. The chat icon shows at once; the widget
 * loads on the first interaction with it, on browser idle when a setting needs
 * the widget early (auto-open, retargeting, keep open). Pages with an inline
 * chat shortcode load the widget normally (its markup carries inline scripts).
 * Nothing render-blocking, one inline script.
 *
 * Hosts opt in by giving widget_assets() on their adapter. Site owners can turn
 * it off with the option qcld_wpbot_chat_lazy_widget = "0" or the filter.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Widget;

use QuantumCloud\Chatbot\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Lazy widget loader.
 */
final class Lazy_Loader {

	/**
	 * Options that need the widget soon after the page loads.
	 */
	const EAGER_OPTIONS = array( 'enable_wp_chatbot_auto_open', 'enable_wp_chatbot_exit_intent', 'enable_wp_chatbot_scroll_open', 'enable_wp_chatbot_open_initial', 'wp_keep_chat_window_open', 'enable_wp_chatbot_ret_user_show', 'enable_wp_chatbot_floating_notification', 'qc_wpbot_floating_notification' );

	/**
	 * Shortcodes that show the chat inside the page.
	 */
	const INLINE_SHORTCODES = array( 'wpwbot', 'chatbot-widget', 'wpbot-page', 'wpwbot_app', 'wpbot-click-chat', 'wpbot-floating-icon', 'wpwbot_products', 'wpwbot_cart' );

	/**
	 * Host settings: url (assets under it belong to the widget), exclude (sub-paths that do not).
	 *
	 * @var array
	 */
	private static $assets = array();

	/**
	 * Deferred handles.
	 *
	 * @var array{scripts:string[],styles:string[]}
	 */
	private static $deferred = array(
		'scripts' => array(),
		'styles'  => array(),
	);

	/**
	 * The chatbot is switched off on this page: the widget's assets are dropped (B6).
	 *
	 * @var bool
	 */
	private static $off = false;

	/**
	 * Hook, when the host gives its widget assets and the feature is on.
	 *
	 * @param object|null $host Host adapter.
	 * @return void
	 */
	public static function init( $host ) {
		if ( is_admin() || ! $host || ! method_exists( $host, 'widget_assets' ) ) {
			return;
		}
		$assets = (array) $host->widget_assets();
		if ( empty( $assets['url'] ) ) {
			return;
		}
		self::$assets = array(
			'url'     => trailingslashit( (string) $assets['url'] ),
			'exclude' => isset( $assets['exclude'] ) ? array_map( 'strval', (array) $assets['exclude'] ) : array(),
		);
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'take' ), PHP_INT_MAX );
		add_action( 'wp_head', array( __CLASS__, 'print_head' ), 1 );
		add_action( 'wp_print_footer_scripts', array( __CLASS__, 'print_launcher' ), 1 );
	}

	/**
	 * Whether the launcher is used on this request.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$on = '0' !== (string) get_option( 'qcld_wpbot_chat_lazy_widget', '1' );
		if ( is_customize_preview() || wp_is_json_request() || ( defined( 'WPBOT_LAZY_WIDGET_DISABLE' ) && WPBOT_LAZY_WIDGET_DISABLE ) ) {
			$on = false;
		}
		/**
		 * Whether the widget loads on demand behind the launcher.
		 *
		 * @param bool $on On.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/lazy_widget', $on );
	}

	/**
	 * Whether a URL is one of the widget's assets.
	 *
	 * @param string $src Asset URL.
	 * @param array  $assets url, exclude.
	 * @return bool
	 */
	public static function is_widget_asset( $src, array $assets ) {
		$src = (string) $src;
		$url = (string) $assets['url'];
		if ( '' === $src || '' === $url ) {
			return false;
		}
		$path = preg_replace( '#^https?:#', '', $src );
		$base = preg_replace( '#^https?:#', '', $url );
		if ( 0 !== strpos( $path, $base ) ) {
			return false;
		}
		$rel = substr( $path, strlen( $base ) );
		foreach ( $assets['exclude'] as $prefix ) {
			if ( '' !== $prefix && 0 === strpos( $rel, $prefix ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Take the widget's handles out of the queue (scripts and styles).
	 *
	 * @return void
	 */
	public static function take() {
		if ( ! self::enabled() || 'now' === self::mode() ) {
			return;
			// Inline chats print scripts that run while the page loads: load the widget normally.
		}
		foreach ( array(
			'scripts' => wp_scripts(),
			'styles'  => wp_styles(),
		) as $kind => $deps ) {
			foreach ( $deps->queue as $handle ) {
				$item = isset( $deps->registered[ $handle ] ) ? $deps->registered[ $handle ] : null;
				if ( $item && is_string( $item->src ) && self::is_widget_asset( $item->src, self::$assets ) ) {
					self::$deferred[ $kind ][] = $handle;
				}
			}
			if ( self::$deferred[ $kind ] ) {
				$deps->dequeue( self::$deferred[ $kind ] );
			}
		}
		// B6: with "Disable chatbot" on (and no chat shortcode on the page) nothing of the widget loads.
		self::$off = Options::on( 'disable_wp_chatbot' );
	}

	/**
	 * In the head: hide the widget's markup except the icon until its styles load.
	 *
	 * @return void
	 */
	public static function print_head() {
		if ( self::$off || ! self::$deferred['scripts'] ) {
			return;
		}
		echo '<style id="qcld-wpbot-launcher-css">' . self::css() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from numbers and fixed text.
		echo '<script>document.documentElement.classList.add("qcld-wpbot-lazy");</script>' . "\n";
	}

	/**
	 * Print the launcher before the footer scripts.
	 *
	 * @return void
	 */
	public static function print_launcher() {
		if ( ! self::enabled() ) {
			return;
		}
		if ( self::$off || ! self::$deferred['scripts'] ) {
			return;
			// The head did not hide the widget, or the chatbot is off.
		}
		self::take();
		// Widget assets enqueued late (add-ons in wp_footer).
		$config = array(
			'mode'     => self::mode(),
			'trigger'  => '#wp-chatbot-ball-container, #wp-chatbot-ball, .wp-chatbot-ball, .wp-chatbot-notification-container, .wpbot-click-chat, .wpbot-floating-icon',
			'open'     => '.wp-chatbot-ball',
			'prefetch' => self::prefetch_delay(),
			'styles'   => self::manifest( wp_styles(), self::$deferred['styles'], 'style' ),
			'scripts'  => self::manifest( wp_scripts(), self::$deferred['scripts'], 'script' ),
		);
		/**
		 * Launcher configuration (mode: interaction, idle or now; selectors; assets).
		 *
		 * @param array $config Config.
		 */
		$config = (array) apply_filters( 'qcld_wpbot_chat/launcher_config', $config );

		echo '<script type="application/json" id="qcld-wpbot-launcher-config">' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < and & escaped.
		echo '<script id="qcld-wpbot-launcher-js">' . self::script() . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the module's own file.
	}

	/**
	 * Milliseconds after the page loaded before the widget's files are fetched
	 * into the browser cache (not run). 0 = never (default): on a slow line a
	 * click during the prefetch downloaded everything twice, and hosts without
	 * cache headers do not reuse prefetched files.
	 *
	 * @return int
	 */
	public static function prefetch_delay() {
		/**
		 * Delay before the widget's files are prefetched after the page loaded (0 turns it off).
		 *
		 * @param int $ms Milliseconds; default 0 (off).
		 */
		return max( 0, (int) apply_filters( 'qcld_wpbot_chat/prefetch_delay', 0 ) );
	}

	/**
	 * When to load: now (inline chat on the page), idle (a setting needs it) or interaction.
	 *
	 * @return string
	 */
	public static function mode() {
		$post = is_singular() ? get_queried_object() : null;
		if ( $post instanceof \WP_Post ) {
			foreach ( self::INLINE_SHORTCODES as $tag ) {
				if ( has_shortcode( (string) $post->post_content, $tag ) ) {
					return 'now';
				}
			}
		}
		foreach ( self::EAGER_OPTIONS as $option ) {
			if ( Options::on( $option ) ) {
				return 'idle';
			}
		}
		return 'interaction';
	}

	/**
	 * Ordered list of what to load: dependencies the page does not print, then the handles.
	 *
	 * @param \WP_Dependencies $deps    Scripts or styles.
	 * @param string[]         $handles Deferred handles.
	 * @param string           $type    script or style.
	 * @return array[]
	 */
	public static function manifest( $deps, array $handles, $type ) {
		$printed        = clone $deps;
		$printed->to_do = array();
		$printed->all_deps( $deps->queue );
		$skip = array_merge( $printed->to_do, $deps->done );

		$wanted         = clone $deps;
		$wanted->to_do  = array();
		$wanted->done   = array();
		$wanted->groups = array();
		$wanted->all_deps( $handles );

		$list = array();
		foreach ( $wanted->to_do as $handle ) {
			if ( in_array( $handle, $skip, true ) || ! isset( $deps->registered[ $handle ] ) ) {
				continue;
			}
			$item = $deps->registered[ $handle ];
			$orig = self::src( $deps, $item );
			$src  = '' !== $orig ? self::filtered( $orig, $handle, $type ) : '';
			$orig = $orig !== $src ? $orig : '';
			if ( 'style' === $type ) {
				if ( '' === $src ) {
					continue;
				}
				$after  = $deps->get_data( $handle, 'after' );
				$list[] = array(
					'id'    => $handle,
					'src'   => $src,
					'orig'  => $orig,
					'media' => is_string( $item->args ) && '' !== $item->args ? $item->args : 'all',
					'after' => is_array( $after ) ? implode( "\n", $after ) : '',
				);
				continue;
			}
			$data   = $deps->get_data( $handle, 'data' );
			$before = $deps->get_data( $handle, 'before' );
			$after  = $deps->get_data( $handle, 'after' );
			$list[] = array(
				'id'     => $handle,
				'src'    => $src,
				'orig'   => $orig,
				'before' => trim( ( is_string( $data ) ? $data : '' ) . "\n" . ( is_array( $before ) ? implode( "\n", array_filter( $before, 'is_string' ) ) : '' ) ),
				'after'  => is_array( $after ) ? implode( "\n", array_filter( $after, 'is_string' ) ) : '',
			);
		}//end foreach
		return $list;
	}

	/**
	 * Full URL of an asset as WordPress would print it (with its version), before the loader filters.
	 *
	 * @param \WP_Dependencies $deps Scripts or styles.
	 * @param \_WP_Dependency  $item Item.
	 * @return string
	 */
	private static function src( $deps, $item ) {
		$src = is_string( $item->src ) ? $item->src : '';
		if ( '' === $src ) {
			return '';
		}
		if ( ! preg_match( '|^(https?:)?//|', $src ) && ! ( $deps->content_url && 0 === strpos( $src, $deps->content_url ) ) ) {
			$src = $deps->base_url . $src;
		}
		if ( null !== $item->ver ) {
			$src = add_query_arg( 'ver', false === $item->ver ? $deps->default_version : $item->ver, $src );
		}
		return esc_url_raw( $src );
	}

	/**
	 * URL after the loader filters (Asset Cache serves its minified copy here).
	 *
	 * @param string $src    Original URL.
	 * @param string $handle Handle.
	 * @param string $type   script or style.
	 * @return string
	 */
	private static function filtered( $src, $handle, $type ) {
		/** This filter is documented in wp-includes/class-wp-scripts.php */
		return esc_url_raw( (string) apply_filters( $type . '_loader_src', $src, $handle ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- core filters script_loader_src / style_loader_src.
	}

	/**
	 * Launcher CSS: only the icon shows until the widget's styles arrive.
	 *
	 * @return string
	 */
	public static function css() {
		$x    = (int) Options::get( 'wp_chatbot_position_x', 50 );
		$y    = (int) Options::get( 'wp_chatbot_position_y', 50 );
		$unit = '%' === (string) get_option( 'wp_chatbot_position_in' ) ? '%' : 'px';
		$x    = $x > 0 ? $x : 50;
		$y    = $y > 0 ? $y : 50;
		return '.qcld-wpbot-lazy #wp-chatbot-chat-container{position:fixed;right:0;bottom:0;width:0;height:0;overflow:hidden;visibility:hidden}'
			. '.qcld-wpbot-lazy #wp-chatbot-ball{position:fixed;right:' . $x . $unit . ';bottom:' . $y . $unit . ';z-index:999999;visibility:visible}'
			. '.qcld-wpbot-lazy #wp-chatbot-ball .wp-chatbot-ball{display:block;width:60px;height:60px;padding:0;border:0;border-radius:50%;background:transparent;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.25);overflow:hidden}'
			. '.qcld-wpbot-lazy #wp-chatbot-ball img{display:block;width:60px;height:60px;border-radius:50%;object-fit:cover}'
			. '.qcld-wpbot-lazy #wp-chatbot-ball .wp-chatbot-ball-animator{display:none}'
			. '.qcld-wpbot-loading #wp-chatbot-ball .wp-chatbot-ball{opacity:.7}';
	}

	/**
	 * Launcher script (the module's file, read once per request).
	 *
	 * @return string
	 */
	private static function script() {
		$file = dirname( __DIR__, 2 ) . '/assets/js/launcher.js';
		$code = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		$code = preg_replace( '#^\s*//.*$#m', '', $code );
		$code = preg_replace( '#/\*.*?\*/#s', '', $code );
		return str_replace( '</script', '<\/script', trim( preg_replace( "/\n\s*/", "\n", $code ) ) );
	}

	/**
	 * Deferred handles (tests, diagnostics).
	 *
	 * @return array
	 */
	public static function deferred() {
		return self::$deferred;
	}
}
