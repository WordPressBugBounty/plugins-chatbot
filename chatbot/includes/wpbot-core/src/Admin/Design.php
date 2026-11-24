<?php
/**
 * WPBot admin design (owner, 20 November 2026: "Premium Studio", emerald accent).
 *
 * One look for every WPBot admin page, in Light and Dark:
 * - Header band: a graphite band at the top of the page (WordPress's in_admin_header) with the
 *   brand, the page search (WordPress's command palette), the light/dark switch, a Notices bell,
 *   the shortcut star, Docs and Help (no tab row since 20 November 2026; filter design_band_tabs).
 * - Notices: admin notices (WordPress's and other plugins') never show inside a WPBot page.
 *   They go into the Notices bell; feedback for the user's own action (a success notice or one
 *   marked "wpb-strip") shows as one slim line under the band.
 * - Page head and section tabs: page_head() prints a title, a short description, the page's
 *   main actions and underlined section tabs; the Shell's menu uses the same tabs.
 * - Footer: WordPress's footer line says whether WPBot works, the version and help links.
 * - Tokens: assets/css/design.css holds every colour as a --wpb-* token (Light and Dark), with
 *   WordPress's system font. No custom font.
 *
 * Hosts and modules never check the edition; a host adds its links, logo, theme switch and the
 * active tab through the filters below.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Admin;

use QuantumCloud\WPBotCore\Core;
use QuantumCloud\WPBotCore\Diagnostics\Error_Log;

defined( 'ABSPATH' ) || exit;

/**
 * Admin design.
 */
final class Design {

	const HANDLE = 'qcld-wpbot-core-design';

	/**
	 * Whether this request is a WPBot admin page (null until known).
	 *
	 * @var bool|null
	 */
	private static $active = null;

	/**
	 * Whether notices are being captured.
	 *
	 * @var bool
	 */
	private static $capturing = false;

	/**
	 * Whether the band was printed.
	 *
	 * @var bool
	 */
	private static $band = false;

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function hook() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'current_screen', array( __CLASS__, 'setup' ) );
	}

	/**
	 * Whether the current admin page gets the WPBot design.
	 *
	 * @return bool
	 */
	public static function active() {
		if ( null !== self::$active ) {
			return self::$active;
		}
		global $plugin_page;
		$slug = is_string( $plugin_page ) ? $plugin_page : '';
		$on   = '' !== $slug && Shortcuts::is_wpbot_page();
		/**
		 * Whether this admin page gets the WPBot design (header band, Notices bell, footer).
		 *
		 * @param bool   $on   Whether it is a page of a WPBot menu.
		 * @param string $slug The page argument.
		 */
		self::$active = (bool) apply_filters( 'qcld_wpbot_core/design_page', $on, $slug );
		return self::$active;
	}

	/**
	 * Forget the cached answer (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$active    = null;
		self::$capturing = false;
		self::$band      = false;
	}

	/**
	 * Set up a WPBot page.
	 *
	 * @return void
	 */
	public static function setup() {
		if ( ! self::active() ) {
			return;
		}
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'in_admin_header', array( __CLASS__, 'render_band' ), 100 );
		foreach ( array( 'network_admin_notices', 'user_admin_notices', 'admin_notices' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'capture_start' ), PHP_INT_MIN );
		}
		add_action( 'all_admin_notices', array( __CLASS__, 'capture_start' ), PHP_INT_MIN );
		add_action( 'all_admin_notices', array( __CLASS__, 'capture_end' ), PHP_INT_MAX );
		add_filter( 'admin_footer_text', array( __CLASS__, 'footer_left' ), 99 );
		add_filter( 'update_footer', array( __CLASS__, 'footer_right' ), 99 );
	}

	/**
	 * Body class.
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		return trim( $classes . ' wpbot-design' );
	}

	/**
	 * An asset's version: WPBot Core's version plus the file's time, so a browser loads the new
	 * file after every update, also a test build that keeps the version number.
	 *
	 * @param string $file Path inside WPBot Core.
	 * @return string|false
	 */
	private static function asset_version( $file ) {
		$version = defined( 'QCLD_WPBOT_CORE_VERSION' ) ? (string) QCLD_WPBOT_CORE_VERSION : '';
		$path    = defined( 'QCLD_WPBOT_CORE_DIR' ) ? QCLD_WPBOT_CORE_DIR . '/' . $file : '';
		$time    = '' !== $path && is_readable( $path ) ? (int) filemtime( $path ) : 0;
		if ( $time > 0 ) {
			return ( '' !== $version ? $version . '.' : '' ) . $time;
		}
		return '' !== $version ? $version : false;
	}

	/**
	 * Style and script.
	 *
	 * @return void
	 */
	public static function enqueue() {
		wp_enqueue_style( self::HANDLE, Core::url( 'assets/css/design.css' ), array(), self::asset_version( 'assets/css/design.css' ) );
		wp_enqueue_script( self::HANDLE, Core::url( 'assets/js/design.js' ), array(), self::asset_version( 'assets/js/design.js' ), true );
		// GRID (owner, 21 November 2026): applies only while the admin theme's GRID look is on (html.qa-scifi).
		wp_enqueue_style( self::HANDLE . '-scifi', Core::url( 'assets/css/scifi.css' ), array( self::HANDLE ), self::asset_version( 'assets/css/scifi.css' ) );
		wp_enqueue_script( self::HANDLE . '-scifi', Core::url( 'assets/js/scifi.js' ), array( self::HANDLE ), self::asset_version( 'assets/js/scifi.js' ), true );
		wp_localize_script(
			self::HANDLE,
			'qcldWpbotDesign',
			array(
				'tabs' => array_map(
					function ( $tab ) {
						return array(
							'label' => $tab['label'],
							'url'   => $tab['url'],
						);
					},
					self::tabs()
				),
				'i18n' => array(
					'more'      => __( 'More', 'chatbot' ),
					'notices'   => __( 'Notices', 'chatbot' ),
					'none'      => __( 'No notices.', 'chatbot' ),
					/* translators: %d: number of notices */
					'count'     => __( '%d notices', 'chatbot' ),
					'go'        => __( 'Go to', 'chatbot' ),
					'search'    => __( 'Search settings and pages', 'chatbot' ),
					'dismissed' => __( 'Dismissed', 'chatbot' ),
				),
				'hud'  => self::hud(),
			)
		);
	}

	/**
	 * What the GRID look's readouts show (assets/js/scifi.js): the band title, the menu name, the
	 * open page's tab, the version and whether WPBot works. Real values only.
	 *
	 * @return array title, menu, section, index (1-based tab number, 0 outside the menu), version, errors.
	 */
	public static function hud() {
		$section = '';
		$index   = 0;
		foreach ( self::tabs() as $i => $tab ) {
			if ( ! empty( $tab['current'] ) ) {
				$section = $tab['label'];
				$index   = $i + 1;
				break;
			}
		}
		return array(
			/** This filter is documented in render_band(). */
			'title'   => (string) apply_filters( 'qcld_wpbot_core/design_title', __( 'Master Control', 'chatbot' ) ),
			'menu'    => self::menu_name(),
			'section' => $section,
			'index'   => $index,
			'version' => self::version_text(),
			'errors'  => self::recent_errors(),
			/**
			 * Whether the GRID shows its status tiles on this page (Hud_Stats; the host's dashboard pages).
			 *
			 * @param bool   $show Default false.
			 * @param string $page The open page argument.
			 */
			'tiles'   => (bool) apply_filters( 'qcld_wpbot_core/design_hud_tiles', false, self::page_slug() ) && current_user_can( Hud_Stats::capability() ),
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'action'  => Hud_Stats::ACTION,
			'nonce'   => wp_create_nonce( Hud_Stats::NONCE ),
			'i18n'    => array(
				'sector'  => __( 'Sector', 'chatbot' ),
				'node'    => __( 'Node', 'chatbot' ),
				'build'   => __( 'Build', 'chatbot' ),
				'link'    => __( 'Link', 'chatbot' ),
				'online'  => __( 'Online', 'chatbot' ),
				'nominal' => __( 'All systems nominal', 'chatbot' ),
				/* translators: %d: number of errors */
				'errors'  => __( '%d errors in 7 days', 'chatbot' ),
				'tiles'   => array(
					'conversations' => __( 'Conversations', 'chatbot' ),
					'answers'       => __( 'AI answers', 'chatbot' ),
					'leads'         => __( 'Leads', 'chatbot' ),
					'resolved'      => __( 'Resolved by AI', 'chatbot' ),
					'status'        => __( 'Bot status', 'chatbot' ),
					'day'           => __( '24h', 'chatbot' ),
					'week'          => __( '7 days', 'chatbot' ),
					'captured'      => __( 'captured', 'chatbot' ),
					'today'         => __( 'in 24h', 'chatbot' ),
					'handoffs'      => __( 'Handoffs', 'chatbot' ),
					'unanswered'    => __( 'Unanswered', 'chatbot' ),
					'sessions'      => __( 'Conversations', 'chatbot' ),
					'errors'        => __( 'Errors 7d', 'chatbot' ),
					'setup'         => __( 'Setup', 'chatbot' ),
					'off'           => __( 'Chat Sessions is off: turn it on to count conversations.', 'chatbot' ),
					'ready'         => __( 'AI ready', 'chatbot' ),
					'no_key'        => __( 'No AI key', 'chatbot' ),
					'not_enabled'   => __( 'AI off', 'chatbot' ),
					'no_model'      => __( 'No AI model', 'chatbot' ),
					'failing'       => __( 'AI failing', 'chatbot' ),
					'budget'        => __( 'Budget reached', 'chatbot' ),
					'live'          => __( 'Live', 'chatbot' ),
				),
			),
		);
	}

	/**
	 * The open admin page argument ('' outside admin.php?page=).
	 *
	 * @return string
	 */
	private static function page_slug() {
		global $plugin_page;
		return is_string( $plugin_page ) ? $plugin_page : '';
	}

	/**
	 * Errors recorded in the last 7 days (0 while the error log is off).
	 *
	 * @return int
	 */
	private static function recent_errors() {
		return class_exists( Error_Log::class ) && Error_Log::enabled() ? (int) Error_Log::count_recent() : 0;
	}

	/**
	 * The version text (e.g. "WPBot Pro 15.8.2").
	 *
	 * @return string
	 */
	private static function version_text() {
		/**
		 * The version shown in the footer (e.g. "WPBot Pro 15.8.2"). Empty by default: a host that is
		 * not WPBot (a second host) shows no WPBot name; the WPBot hosts pass their own.
		 *
		 * @param string $version Version text.
		 */
		return (string) apply_filters( 'qcld_wpbot_core/design_version', '' );
	}

	/**
	 * The host whose menu this page belongs to: the first with a menu in WordPress.
	 *
	 * @return string Parent menu slug, '' for none.
	 */
	public static function parent() {
		global $submenu;
		foreach ( Core::instance()->hosts() as $host ) {
			$parent = (string) $host->get_menu_parent();
			if ( '' !== $parent && ! empty( $submenu[ $parent ] ) ) {
				return $parent;
			}
		}
		return '';
	}

	/**
	 * One tab per page of the host's WordPress menu (what the user may open).
	 *
	 * @return array[] Each: slug, label, url, count (int), current (bool), external (bool).
	 */
	public static function tabs() {
		global $submenu, $plugin_page;
		$parent = self::parent();
		$tabs   = array();
		$here   = is_string( $plugin_page ) ? $plugin_page : '';
		/**
		 * The tab of the open page, for pages outside the host's menu (a module's own pages).
		 *
		 * @param string $slug The open page argument.
		 */
		$active = (string) apply_filters( 'qcld_wpbot_core/design_active_tab', $here );
		foreach ( '' !== $parent ? (array) $submenu[ $parent ] : array() as $entry ) {
			if ( ! isset( $entry[0], $entry[2] ) || ( isset( $entry[1] ) && ! current_user_can( $entry[1] ) ) ) {
				continue;
			}
			$slug  = (string) $entry[2];
			$label = self::label( (string) $entry[0] );
			if ( '' === $label ) {
				continue;
			}
			$external = (bool) preg_match( '#^https?://#i', $slug );
			if ( $external ) {
				continue; // Outside links (support, upgrade) stay in WordPress's menu.
			}
			$tabs[] = array(
				'slug'     => $slug,
				'label'    => $label,
				'url'      => $external || false !== strpos( $slug, '.php' ) ? $slug : add_query_arg( 'page', $slug, admin_url( 'admin.php' ) ),
				'count'    => self::count( (string) $entry[0] ),
				'current'  => $slug === $active,
				'external' => $external,
			);
		}
		/**
		 * Header band tabs.
		 *
		 * @param array[] $tabs Each: slug, label, url, count, current, external.
		 */
		return (array) apply_filters( 'qcld_wpbot_core/design_tabs', $tabs );
	}

	/**
	 * A menu title without its count bubble and screen reader text.
	 *
	 * @param string $title Menu title (HTML).
	 * @return string
	 */
	public static function label( $title ) {
		// Twice: a count bubble holds a span of its own.
		$pattern = '#<span[^>]*class="[^"]*(screen-reader-text|count|awaiting-mod|update-plugins|badge|pill)[^"]*"[^>]*>.*?</span>#is';
		$title   = (string) preg_replace( $pattern, '', (string) preg_replace( $pattern, '', $title ) );
		return trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES, 'UTF-8' ) ) );
	}

	/**
	 * The number in a menu title's count bubble.
	 *
	 * @param string $title Menu title (HTML).
	 * @return int
	 */
	public static function count( $title ) {
		if ( preg_match( '#<span[^>]*class="[^"]*(count|awaiting-mod|update-plugins|badge|pill)[^"]*"[^>]*>(.*?)</span>#is', $title, $m ) && preg_match( '/\d+/', wp_strip_all_tags( $m[2] ), $n ) ) {
			return (int) $n[0];
		}
		return 0;
	}

	/**
	 * The menu name above the title (the host's top-level menu, e.g. "WPBot Pro").
	 *
	 * @return string
	 */
	public static function menu_name() {
		global $menu;
		$parent = self::parent();
		foreach ( (array) $menu as $entry ) {
			if ( isset( $entry[0], $entry[2] ) && $entry[2] === $parent ) {
				return self::label( (string) $entry[0] );
			}
		}
		return 'WPBot';
	}

	/**
	 * Print the header band.
	 *
	 * @return void
	 */
	public static function render_band() {
		if ( self::$band || ! self::active() ) {
			return;
		}
		self::$band = true;
		/**
		 * The band's title.
		 *
		 * @param string $title Title.
		 */
		$title = (string) apply_filters( 'qcld_wpbot_core/design_title', __( 'Master Control', 'chatbot' ) );
		/**
		 * The band's logo: an image address, '' for WPBot's mark.
		 *
		 * @param string $url Image address.
		 */
		$logo = (string) apply_filters( 'qcld_wpbot_core/design_logo', '' );
		/**
		 * The band's text links (Docs, Help).
		 *
		 * @param array[] $links Each: label, url, new_tab (bool).
		 */
		$links = (array) apply_filters( 'qcld_wpbot_core/design_links', array() );
		$tabs  = self::tabs();
		$home  = $tabs ? $tabs[0]['url'] : admin_url();
		/**
		 * Whether the band shows a row of tabs, one per page of the host's menu. Off since the
		 * owner's call of 20 November 2026 ("remove all the menu items at the top on all pages"):
		 * WordPress's menu and the band's search lead to every page.
		 *
		 * @param bool $show Show the tabs.
		 */
		$show_tabs = $tabs && (bool) apply_filters( 'qcld_wpbot_core/design_band_tabs', false );
		?>
		<div class="wpb-band<?php echo $show_tabs ? '' : ' wpb-band--no-tabs'; ?>" role="banner">
			<div class="wpb-band__top">
				<a class="wpb-band__brand" href="<?php echo esc_url( $home ); ?>">
					<span class="wpb-band__mark<?php echo '' !== $logo ? ' has-logo' : ''; ?>" aria-hidden="true">
						<?php if ( '' !== $logo ) : ?>
							<img src="<?php echo esc_url( $logo ); ?>" alt="">
						<?php else : ?>
							<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M5 5l4.5 4.5M19 5l-4.5 4.5M5 19l4.5-4.5M19 19l-4.5-4.5"/></svg>
						<?php endif; ?>
					</span>
					<span class="wpb-band__title">
						<small><?php echo esc_html( self::menu_name() ); ?></small>
						<b><?php echo esc_html( $title ); ?></b>
					</span>
				</a>
				<div class="wpb-band__actions">
					<button type="button" class="wpb-band__search" hidden>
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
						<span class="wpb-band__search-label"><?php esc_html_e( 'Search settings and pages', 'chatbot' ); ?></span>
						<kbd>Ctrl K</kbd>
					</button>
					<?php
					/**
					 * Print controls in the band, before the Notices bell (the host's light/dark switch).
					 */
					do_action( 'qcld_wpbot_core/design_actions' );
					?>
					<div class="wpb-notices">
						<button type="button" class="wpb-band__icon wpb-notices__bell" aria-expanded="false" aria-controls="wpb-notices-panel" title="<?php esc_attr_e( 'Notices', 'chatbot' ); ?>">
							<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 1112 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 20a2 2 0 004 0"/></svg>
							<span class="screen-reader-text"><?php esc_html_e( 'Notices', 'chatbot' ); ?></span>
							<span class="wpb-count wpb-notices__count" hidden>0</span>
						</button>
						<div class="wpb-notices__panel" id="wpb-notices-panel" hidden>
							<div class="wpb-notices__head"><?php esc_html_e( 'Notices', 'chatbot' ); ?></div>
							<div class="wpb-notices__list"></div>
							<p class="wpb-notices__empty"><?php esc_html_e( 'No notices.', 'chatbot' ); ?></p>
						</div>
					</div>
					<span class="wpb-band__slot" data-wpb-slot="shortcut"></span>
					<?php foreach ( $links as $link ) : ?>
						<?php
						if ( empty( $link['label'] ) || empty( $link['url'] ) ) {
							continue;
						}
						?>
						<a class="wpb-band__link" href="<?php echo esc_url( $link['url'] ); ?>"<?php echo ! empty( $link['new_tab'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $link['label'] ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
			<?php if ( $show_tabs ) : ?>
				<nav class="wpb-band__tabs" aria-label="<?php echo esc_attr( self::menu_name() ); ?>" data-wpb-overflow>
					<?php foreach ( $tabs as $tab ) : ?>
						<a class="wpb-band__tab<?php echo $tab['current'] ? ' is-current' : ''; ?>" href="<?php echo esc_url( $tab['url'] ); ?>"<?php echo $tab['current'] ? ' aria-current="page"' : ''; ?><?php echo $tab['external'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
							<?php echo esc_html( $tab['label'] ); ?>
							<?php if ( $tab['count'] > 0 ) : ?>
								<span class="wpb-count"><?php echo esc_html( number_format_i18n( $tab['count'] ) ); ?></span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</div>
		<div class="wpb-strip-slot" aria-live="polite"></div>
		<?php
	}

	/**
	 * Start keeping admin notices out of the page.
	 *
	 * @return void
	 */
	public static function capture_start() {
		if ( self::$capturing || ! self::active() ) {
			return;
		}
		self::$capturing = true;
		ob_start();
	}

	/**
	 * Print the kept notices in a hidden holder: design.js moves them into the Notices bell
	 * before WordPress's script would move them under the page header.
	 *
	 * @return void
	 */
	public static function capture_end() {
		if ( ! self::$capturing ) {
			return;
		}
		self::$capturing = false;
		$html            = (string) ob_get_clean();
		if ( '' !== trim( $html ) ) {
			echo '<div class="wpb-notice-holder" hidden>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Notices as WordPress printed them.
		}
		/**
		 * The top of a WPBot page's content, after the notices were kept for the bell: print
		 * page parts here that used to be printed during the notice hooks (they would end in
		 * the bell otherwise).
		 */
		do_action( 'qcld_wpbot_core/design_page_top' );
	}

	/**
	 * Footer, left: whether WPBot works and the version.
	 *
	 * @return string
	 */
	public static function footer_left() {
		$errors = self::recent_errors();
		if ( $errors > 0 ) {
			/* translators: %d: number of errors */
			$status = '<span class="wpb-foot__dot is-warn"></span>' . esc_html( sprintf( _n( '%d error in the last 7 days', '%d errors in the last 7 days', $errors, 'chatbot' ), $errors ) );
		} else {
			$status = '<span class="wpb-foot__dot"></span>' . esc_html__( 'All systems working', 'chatbot' );
		}
		$version = self::version_text();
		return '<span class="wpb-foot">' . $status . ( '' !== $version ? '<span class="wpb-foot__ver">' . esc_html( $version ) . '</span>' : '' ) . '</span>';
	}

	/**
	 * Footer, right: help links.
	 *
	 * @return string
	 */
	public static function footer_right() {
		/**
		 * Footer links (Docs, Get Support).
		 *
		 * @param array[] $links Each: label, url, new_tab (bool).
		 */
		$links = (array) apply_filters( 'qcld_wpbot_core/design_footer_links', (array) apply_filters( 'qcld_wpbot_core/design_links', array() ) );
		$out   = array();
		foreach ( $links as $link ) {
			if ( ! empty( $link['label'] ) && ! empty( $link['url'] ) ) {
				$out[] = '<a href="' . esc_url( $link['url'] ) . '"' . ( ! empty( $link['new_tab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( $link['label'] ) . '</a>';
			}
		}
		return '<span class="wpb-foot wpb-foot--links">' . implode( '', $out ) . '</span>';
	}

	/**
	 * Print a page head: title, description, actions and section tabs.
	 *
	 * @param array $args title, description, actions (HTML, already escaped), items (section tabs,
	 *                    as Shell::items()), current (item id), label (section menu name).
	 * @return void
	 */
	public static function page_head( array $args ) {
		$title = isset( $args['title'] ) ? (string) $args['title'] : '';
		$desc  = isset( $args['description'] ) ? (string) $args['description'] : '';
		?>
		<div class="wpb-pagehead">
			<div class="wpb-pagehead__text">
				<h1 class="wpb-pagehead__title"><?php echo esc_html( $title ); ?></h1>
				<?php if ( '' !== $desc ) : ?>
					<p class="wpb-pagehead__desc"><?php echo esc_html( $desc ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $args['actions'] ) ) : ?>
				<div class="wpb-pagehead__actions"><?php echo wp_kses_post( (string) $args['actions'] ); ?></div>
			<?php endif; ?>
		</div>
		<?php
		if ( ! empty( $args['items'] ) ) {
			self::subnav( (array) $args['items'], isset( $args['current'] ) ? (string) $args['current'] : '', isset( $args['label'] ) ? (string) $args['label'] : $title );
		}
	}

	/**
	 * Print section tabs (underlined; what does not fit goes under "More"; a select menu on phones).
	 *
	 * @param array  $items   Items as Shell::items() takes them.
	 * @param string $current Current item id.
	 * @param string $label   Menu name for screen readers.
	 * @return void
	 */
	public static function subnav( array $items, $current, $label ) {
		$items   = Shell::items( $items );
		$current = sanitize_key( $current );
		if ( ! $items ) {
			return;
		}
		$id = 'wpb-subnav-' . wp_unique_id();
		?>
		<nav class="wpb-subnav" aria-label="<?php echo esc_attr( $label ); ?>">
			<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<select class="wpb-subnav__select" id="<?php echo esc_attr( $id ); ?>" onchange="if(this.value){window.location.href=this.value;}">
				<?php foreach ( $items as $item ) : ?>
					<option value="<?php echo esc_url( $item['url'] ); ?>" <?php selected( $item['id'], $current ); ?>><?php echo esc_html( $item['label'] . ( '' !== $item['badge'] ? ' (' . $item['badge'] . ')' : '' ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<div class="wpb-subnav__list" data-wpb-overflow>
				<?php foreach ( $items as $item ) : ?>
					<a class="wpb-subnav__item<?php echo $item['id'] === $current ? ' is-current' : ''; ?><?php echo $item['pro'] ? ' is-pro' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>" data-item="<?php echo esc_attr( $item['id'] ); ?>"<?php echo $item['id'] === $current ? ' aria-current="page"' : ''; ?>>
						<?php echo esc_html( $item['label'] ); ?>
						<?php if ( $item['pro'] ) : ?>
							<span class="wpb-pro"><?php esc_html_e( 'Pro', 'chatbot' ); ?></span>
						<?php elseif ( '' !== $item['badge'] ) : ?>
							<span class="wpb-count"><?php echo esc_html( $item['badge'] ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>
		</nav>
		<?php
	}
}
