<?php
/**
 * Front-end side of the asset cache.
 *
 * WPBot keeps enqueuing its files as before; this class only swaps each
 * registered file's URL for its cached copy at the last moment, so handles,
 * dependencies and add-ons keep working. It also moves the floating
 * chatbot's stylesheets out of the <head> (non-blocking styles), loads icon
 * fonts and animations only when the chatbot first needs them, and adds
 * display=swap to Google Fonts. Registered only when no kill switch is set.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end hooks.
 */
class Frontend {

	/**
	 * The module.
	 *
	 * @var Module
	 */
	private $module;

	/**
	 * Whether this page view uses the cache (null until decided).
	 *
	 * @var bool|null
	 */
	private $on = null;

	/**
	 * Whether styles load without blocking on this page (null until decided at wp_head).
	 *
	 * @var bool|null
	 */
	private $nonblocking = null;

	/**
	 * Handles held back from the <head>.
	 *
	 * @var string[]
	 */
	private $moved = array();

	/**
	 * Handles loaded on demand: handle => href.
	 *
	 * @var array<string,string>
	 */
	private $on_demand = array();

	/**
	 * The last WPBot stylesheet of the footer pass (the reveal rule follows it).
	 *
	 * @var string
	 */
	private $last_style = '';

	/**
	 * Cached files printed on this page, for the guard: list of [type, handle, cached URL, original URL, bundle element id (bundles only)].
	 *
	 * @var array<int,array>
	 */
	private $served = array();

	/**
	 * Original URLs of swapped files: type:handle => URL.
	 *
	 * @var array<string,string>
	 */
	private $originals = array();

	/**
	 * Whether the error collector was printed in the head.
	 *
	 * @var bool
	 */
	private $collecting = false;

	/**
	 * Combine, when the setting is on.
	 *
	 * @var Combine|null
	 */
	private $combine = null;

	/**
	 * Whether the reveal rule was printed.
	 *
	 * @var bool
	 */
	private $revealed = false;

	/**
	 * Constructor.
	 *
	 * @param Module $module The module.
	 */
	public function __construct( Module $module ) {
		$this->module = $module;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'style_loader_src', array( $this, 'style_src' ), 20, 2 );
		add_filter( 'script_loader_src', array( $this, 'script_src' ), 20, 2 );
		add_filter( 'script_loader_tag', array( $this, 'script_tag' ), 20, 3 );
		add_action( 'wp_print_scripts', array( $this, 'defer_scripts' ), 1 );
		add_action( 'wp_print_footer_scripts', array( $this, 'defer_scripts' ), 1 );
		add_action( 'wp_head', array( $this, 'collect_errors' ), 1 );
		add_action( 'wp_head', array( $this, 'head' ), 7 );
		add_filter( 'print_styles_array', array( $this, 'filter_styles' ), 20 );
		add_filter( 'style_loader_tag', array( $this, 'style_tag' ), 20, 2 );
		add_action( 'wp_print_footer_scripts', array( $this, 'footer' ), 11 );
	}

	/**
	 * Turn on combining for this request.
	 *
	 * @param Combine $combine Combine.
	 * @return void
	 */
	public function set_combine( Combine $combine ) {
		$this->combine = $combine;
		$combine->register();
	}

	/**
	 * Whether this page view uses the cache (not for the ?wpbot_ao=off preview).
	 *
	 * @return bool
	 */
	public function on() {
		if ( null === $this->on ) {
			$this->on = ! is_admin() && ! $this->is_preview_off();
		}
		return $this->on;
	}

	/**
	 * Whether an administrator asked to preview the original files (?wpbot_ao=off).
	 *
	 * @return bool
	 */
	public function is_preview_off() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only switch for one page view.
		if ( ! isset( $_GET['wpbot_ao'] ) || 'off' !== sanitize_key( wp_unslash( $_GET['wpbot_ao'] ) ) ) {
			return false;
		}
		$host = $this->module->host();
		return null !== $host && current_user_can( $host->get_capability() );
	}

	/**
	 * Swap a registered stylesheet's URL for its cached copy; add
	 * display=swap to WPBot's Google Fonts.
	 *
	 * @param string $src    Source URL.
	 * @param string $handle Handle.
	 * @return string
	 */
	public function style_src( $src, $handle ) {
		if ( ! $this->on() || ! is_string( $src ) || '' === $src || ! $this->module->registry()->has( 'style', $handle ) ) {
			return $src;
		}
		if ( false !== strpos( $src, 'fonts.googleapis.com' ) ) {
			return false === strpos( $src, 'display=' ) ? add_query_arg( 'display', 'swap', $src ) : $src;
		}
		$styles = wp_styles();
		if ( is_rtl() && isset( $styles->registered[ $handle ] ) && ! empty( $styles->registered[ $handle ]->extra['rtl'] ) ) {
			// A style with its own RTL file: WordPress derives that file's URL from the original, so keep the original.
			return $src;
		}
		$cached = $this->module->processor()->cached_url( 'style', $handle, $src );
		if ( '' === $cached ) {
			return $src;
		}
		$this->originals[ 'style:' . $handle ] = $src;
		return $cached;
	}

	/**
	 * Swap a registered script's URL for its cached copy.
	 *
	 * @param string $src    Source URL.
	 * @param string $handle Handle.
	 * @return string
	 */
	public function script_src( $src, $handle ) {
		if ( ! $this->on() || ! is_string( $src ) || '' === $src || ! $this->module->registry()->has( 'script', $handle ) ) {
			return $src;
		}
		$cached = $this->module->processor()->cached_url( 'script', $handle, $src );
		if ( '' === $cached ) {
			return $src;
		}
		$this->originals[ 'script:' . $handle ] = $src;
		return $cached;
	}

	/**
	 * Remember each cached script printed, for the guard.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Handle.
	 * @param string $src    Printed URL.
	 * @return string
	 */
	public function script_tag( $tag, $handle, $src ) {
		if ( isset( $this->originals[ 'script:' . $handle ] ) ) {
			$this->served[] = array( 'script', $handle, (string) $src, $this->originals[ 'script:' . $handle ] );
		}
		$this->served_bundle( $handle, (string) $src );
		return $tag;
	}

	/**
	 * A bundle printed: each member gets a guard entry pointing at the bundle.
	 *
	 * @param string $handle Bundle handle.
	 * @param string $src    Printed URL.
	 * @return void
	 */
	private function served_bundle( $handle, $src ) {
		$printed = null === $this->combine ? array() : $this->combine->printed();
		if ( ! isset( $printed[ $handle ] ) ) {
			return;
		}
		list( $type, $members, $originals ) = $printed[ $handle ];
		foreach ( $members as $i => $member ) {
			$this->served[] = array( $type, $member, $src, (string) $originals[ $i ], $handle . '-css' );
		}
	}

	/**
	 * Mark WPBot's scripts "defer" through the WordPress 6.3 loading-strategy
	 * API. WordPress itself keeps normal loading where a dependent script or
	 * an "after" inline script needs it; older versions ignore the data.
	 *
	 * @return void
	 */
	public function defer_scripts() {
		if ( ! $this->on() || ! $this->module->settings()->get( 'defer_js' ) ) {
			return;
		}
		$scripts = wp_scripts();
		foreach ( $this->module->registry()->all()['script'] as $handle ) {
			if ( isset( $scripts->registered[ $handle ] ) && ! $scripts->get_data( $handle, 'strategy' ) && ! $this->module->registry()->is_skipped( 'script', $handle, (string) $scripts->registered[ $handle ]->src ) ) {
				$scripts->add_data( $handle, 'strategy', 'defer' );
			}
		}
	}

	/**
	 * First thing in the <head>: a tiny listener that notes which files
	 * failed to load or threw, for the guard at the end of the page.
	 *
	 * @return void
	 */
	public function collect_errors() {
		if ( ! $this->on() || ! $this->has_queued_files() ) {
			return;
		}
		$this->collecting = true;
		wp_print_inline_script_tag(
			'(function(w){var a=w.wpbotAo=w.wpbotAo||{},e=a.err={};w.addEventListener("error",function(v){var t=v.target,u=t&&t!==w&&(t.src||t.href);if(u){e[u]="load"}else if(v.filename){e[v.filename]=(v.error&&v.error.name==="SyntaxError")||/SyntaxError/.test(v.message)?"syntax":"runtime"}},true)})(window);',
			array( 'id' => 'wpbot-ao-errors' )
		);
	}

	/**
	 * Decide, before the <head> styles print, whether this page loads
	 * WPBot's styles without blocking, and print the rule that keeps the
	 * chatbot hidden until its styles are in.
	 *
	 * @return void
	 */
	public function head() {
		$this->nonblocking = false;
		if ( ! $this->on() || ! $this->module->settings()->get( 'nonblocking_css' ) || $this->module->host()->is_embedded_page() ) {
			return;
		}
		$selectors = $this->selectors();
		if ( '' === $selectors || ! $this->has_queued_styles() ) {
			return;
		}
		$this->nonblocking = true;
		echo '<style id="wpbot-ao-hold">' . $selectors . "{visibility:hidden}</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selectors() keeps only CSS selector characters.
	}

	/**
	 * Whether any registered WPBot script or style is enqueued on this page
	 * (wp_enqueue_scripts has run by now: it is the first wp_head callback).
	 *
	 * @return bool
	 */
	private function has_queued_files() {
		$scripts = wp_scripts();
		foreach ( $this->module->registry()->all()['script'] as $handle ) {
			if ( in_array( $handle, $scripts->queue, true ) ) {
				return true;
			}
		}
		return $this->has_queued_styles();
	}

	/**
	 * Whether any registered WPBot style is enqueued.
	 *
	 * @return bool
	 */
	private function has_queued_styles() {
		$styles = wp_styles();
		foreach ( $this->module->registry()->all()['style'] as $handle ) {
			if ( in_array( $handle, $styles->queue, true ) ) {
				return true;
			}
			foreach ( $styles->queue as $queued ) {
				if ( $this->depends_on( $styles, $queued, $handle ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether a style handle depends on another one, directly or not.
	 *
	 * @param \WP_Styles $styles Styles.
	 * @param string     $handle Handle.
	 * @param string     $needle Dependency to look for.
	 * @param int        $depth  Recursion guard.
	 * @return bool
	 */
	private function depends_on( $styles, $handle, $needle, $depth = 0 ) {
		if ( $depth > 10 || ! isset( $styles->registered[ $handle ] ) ) {
			return false;
		}
		foreach ( (array) $styles->registered[ $handle ]->deps as $dep ) {
			if ( $dep === $needle || $this->depends_on( $styles, $dep, $needle, $depth + 1 ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Hold WPBot's stylesheets back from the <head> (they print with the
	 * footer, in the same order), and take the on-demand ones out entirely.
	 *
	 * @param string[] $to_do Handles about to print.
	 * @return string[]
	 */
	public function filter_styles( $to_do ) {
		if ( ! $this->on() || ! is_array( $to_do ) || ! $this->nonblocking ) {
			return $to_do;
		}
		$styles    = wp_styles();
		$registry  = $this->module->registry();
		$on_demand = $this->module->host()->get_on_demand_styles();
		$in_head   = doing_action( 'wp_head' ) || doing_action( 'wp_print_styles' );
		$keep      = array();
		$ours      = array();

		foreach ( $to_do as $handle ) {
			if ( in_array( $handle, $on_demand, true ) && $registry->has( 'style', $handle ) && ! $this->needed_by_others( $styles, $to_do, $handle, $on_demand ) ) {
				$this->on_demand[ $handle ] = $this->href( $styles, $handle );
				$styles->done[]             = $handle;
				continue;
			}
			if ( $in_head && $registry->has( 'style', $handle ) && ! $this->needed_by_others( $styles, $to_do, $handle, array() ) ) {
				$this->moved[] = $handle;
				continue;
			}
			$keep[] = $handle;
			if ( $registry->has( 'style', $handle ) ) {
				$ours[] = $handle;
			}
		}

		// The reveal rule follows the last WPBot stylesheet of the pass that prints the held-back ones.
		if ( ! $in_head && array() !== $ours && array() === array_diff( $this->moved, array_merge( $styles->done, $keep ) ) ) {
			$this->last_style = end( $ours );
		}
		return $keep;
	}

	/**
	 * Whether a non-WPBot style about to print depends on this handle (then
	 * it must stay where it is, or that style would lose its base).
	 *
	 * @param \WP_Styles $styles  Styles.
	 * @param string[]   $to_do   Handles about to print.
	 * @param string     $handle  WPBot handle.
	 * @param string[]   $ignored Handles that do not count.
	 * @return bool
	 */
	private function needed_by_others( $styles, array $to_do, $handle, array $ignored ) {
		$registry = $this->module->registry();
		foreach ( $to_do as $other ) {
			if ( $other !== $handle && ! in_array( $other, $ignored, true ) && ! $registry->has( 'style', $other ) && $this->depends_on( $styles, $other, $handle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Final URL of a registered style, as WordPress would print it.
	 *
	 * @param \WP_Styles $styles Styles.
	 * @param string     $handle Handle.
	 * @return string
	 */
	private function href( $styles, $handle ) {
		$item = $styles->registered[ $handle ];
		$ver  = null === $item->ver ? '' : ( $item->ver ? $item->ver : $styles->default_version );
		return (string) $styles->_css_href( $item->src, $ver, $handle );
	}

	/**
	 * Print the reveal rule right after WPBot's last stylesheet.
	 *
	 * @param string $tag    Link tag.
	 * @param string $handle Handle.
	 * @return string
	 */
	public function style_tag( $tag, $handle ) {
		if ( isset( $this->originals[ 'style:' . $handle ] ) && ! isset( $this->on_demand[ $handle ] ) && preg_match( "/href='([^']+)'/", $tag, $match ) ) {
			$this->served[] = array( 'style', $handle, html_entity_decode( $match[1] ), $this->originals[ 'style:' . $handle ] );
		}
		if ( preg_match( "/href='([^']+)'/", $tag, $match ) ) {
			$this->served_bundle( $handle, html_entity_decode( $match[1] ) );
		}
		$last = $this->last_style;
		if ( null !== $this->combine && '' !== $last && '' !== $this->combine->bundle_of( $last ) ) {
			$last = $this->combine->bundle_of( $last );
		}
		if ( $this->nonblocking && ! $this->revealed && '' !== $last && $handle === $last ) {
			$this->revealed = true;
			$tag           .= $this->reveal_rule();
		}
		return $tag;
	}

	/**
	 * The rule that shows the chatbot again.
	 *
	 * @return string
	 */
	private function reveal_rule() {
		return '<style id="wpbot-ao-show">' . $this->selectors() . "{visibility:visible}</style>\n";
	}

	/**
	 * The host's chatbot selectors, joined, with only characters that can
	 * appear in a CSS selector (no "<", braces or semicolons), so they are
	 * safe inside a <style> element.
	 *
	 * @return string
	 */
	public function selectors() {
		$clean = array();
		foreach ( (array) $this->module->host()->get_chatbot_selectors() as $selector ) {
			$selector = trim( (string) preg_replace( '/[^A-Za-z0-9#.\-_ >+~:()\[\]="\'^$*|]/', '', (string) $selector ) );
			if ( '' !== $selector ) {
				$clean[] = $selector;
			}
		}
		return implode( ',', $clean );
	}

	/**
	 * After the footer styles: make sure the chatbot is revealed, and hand the
	 * on-demand styles to the guard.
	 *
	 * @return void
	 */
	public function footer() {
		if ( $this->nonblocking && ! $this->revealed ) {
			$this->revealed = true;
			echo $this->reveal_rule(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selectors() keeps only CSS selector characters.
		}
		if ( ! $this->on() || ( array() === $this->on_demand && array() === $this->served && ! $this->has_queued_files() ) ) {
			return;
		}
		$styles = array();
		foreach ( $this->on_demand as $handle => $href ) {
			$styles[] = array(
				'id'   => $handle . '-css',
				'href' => $href,
			);
		}
		$files = array();
		foreach ( $this->served as $item ) {
			$entry = array(
				't'    => $item[0],
				'h'    => $item[1],
				'src'  => $item[2],
				'orig' => $item[3],
				'key'  => isset( $item[4] ) ? '' : ( preg_match( '/-([a-f0-9]{12})\.min\.(js|css)/', $item[2], $m ) ? $m[1] : '' ),
			);
			if ( isset( $item[4] ) ) {
				$entry['id'] = $item[4];
			}
			$files[] = $entry;
		}
		$selectors = explode( ',', $this->selectors() );
		$config    = array(
			'styles' => $styles,
			'root'   => $selectors[0],
			'files'  => $files,
			'ajax'   => admin_url( 'admin-ajax.php' ),
		);
		/**
		 * Filter what the browser guard receives (cached-page safety adds its part here).
		 *
		 * @param array<string,mixed> $config Guard config.
		 */
		$config = (array) apply_filters( 'qcld_wpbot_ao_guard_config', $config );
		$json   = wp_json_encode( $config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );
		wp_print_inline_script_tag( '(function(w,c){var a=w.wpbotAo=w.wpbotAo||{};for(var k in c){a[k]=c[k]}})(window,' . $json . ');', array( 'id' => 'wpbot-ao-guard-config' ) );
		$this->print_module_script( Registry::GUARD, 'assets/ao-guard.js' );
		if ( ! empty( $config['fresh'] ) ) {
			$this->print_module_script( Registry::FRESH, 'assets/ao-fresh.js' );
		}
	}

	/**
	 * Print one of the module's own scripts (deferred), always from the module
	 * folder: they must work when the cache folder has just been emptied.
	 *
	 * @param string $handle Handle (id).
	 * @param string $file   Path inside the module.
	 * @return void
	 */
	private function print_module_script( $handle, $file ) {
		wp_print_script_tag(
			array(
				'id'    => $handle,
				'src'   => add_query_arg( 'ver', QCLD_WPBOT_AO_VERSION, plugins_url( $file, QCLD_WPBOT_AO_FILE ) ),
				'defer' => true,
			)
		);
	}
}
