<?php
/**
 * The Help Center in the WPBot menu (owner, 19 November 2026): one "Help Center" item under
 * WPBot, just above Permissions, instead of a top-level WordPress menu. The Help Center's own
 * screens (articles, knowledge bases, categories, Organize, Settings…) are reached from a row of
 * tabs at the top of each of them (for every user who can open them). Only while the Help Center is on.
 *
 * The WPBot menu is found by its Permissions item (wpbot-permissions), the same in WPBot Free
 * and Pro, so no host code is needed. Without it, or for users who cannot open the WPBot menu,
 * the Help Center keeps its own top-level menu.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Admin;

use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Menu placement and the Help Center tabs.
 */
final class Menu {

	/**
	 * The WPBot item the Help Center goes above.
	 */
	const BEFORE = 'wpbot-permissions';

	/**
	 * The WPBot menu's own first page (fallback when Permissions is missing).
	 */
	const WPBOT_PAGE = 'wpbot_openAi';

	/**
	 * Tab order (slugs); other screens follow in their menu order.
	 */
	const TAB_ORDER = array(
		'wpbot-help-center-start',
		'edit.php?post_type=wpbot_article',
		'post-new.php?post_type=wpbot_article',
		'wpbot-help-center-organize',
		'edit-tags.php?taxonomy=wpbot_kb&amp;post_type=wpbot_article',
		'edit-tags.php?taxonomy=wpbot_article_cat&amp;post_type=wpbot_article',
		'edit-tags.php?taxonomy=wpbot_article_tag&amp;post_type=wpbot_article',
		'edit-tags.php?taxonomy=wpbot_glossary&amp;post_type=wpbot_article',
		'wpbot-help-center-gaps',
		'wpbot-help-center-analytics',
		'wpbot-help-center-design',
		'wpbot-help-center-ai',
		'wpbot-help-center-settings',
	);

	/**
	 * The WPBot parent the Help Center moved under in this request ('' when it did not move).
	 *
	 * @var string
	 */
	private static $parent = '';

	/**
	 * The submenu file WordPress chose for this screen, before the filter below.
	 *
	 * @var string
	 */
	private static $current = '';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( self::class, 'move' ), 999 );
		add_filter( 'qcld_wpbot_menu_order', array( self::class, 'order' ) );
		add_filter( 'parent_file', array( self::class, 'parent_file' ) );
		add_filter( 'submenu_file', array( self::class, 'submenu_file' ), 10, 2 );
		add_action( 'in_admin_header', array( self::class, 'tabs' ) );
		add_action( 'admin_head', array( self::class, 'styles' ) );
		// WPBot Core's admin design (shared rules 2026-11-20): Help Center screens get the band,
		// the Notices bell and the footer, and the tabs become its section tabs.
		add_filter( 'qcld_wpbot_core/design_page', array( self::class, 'design_page' ) );
		add_filter( 'qcld_wpbot_core/design_active_tab', array( self::class, 'design_active_tab' ) );
		add_action( 'qcld_wpbot_core/design_page_top', array( self::class, 'design_tabs' ) );
	}

	/**
	 * Whether WPBot Core's design is on for this request.
	 *
	 * @return bool
	 */
	public static function design_on() {
		return class_exists( '\\QuantumCloud\\WPBotCore\\Admin\\Design' ) && method_exists( '\\QuantumCloud\\WPBotCore\\Admin\\Design', 'active' ) && \QuantumCloud\WPBotCore\Admin\Design::active() && self::is_help_center_screen();
	}

	/**
	 * Turn the design on for Help Center screens in the WPBot menu (not in the block editor).
	 *
	 * @param bool $on Whether the page is in a WPBot menu.
	 * @return bool
	 */
	public static function design_page( $on ) {
		if ( $on || '' === self::$parent || ! self::is_help_center_screen() ) {
			return (bool) $on;
		}
		$screen = get_current_screen();
		return ! ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() );
	}

	/**
	 * "Help Center" is the current band tab on Help Center screens.
	 *
	 * @param string $slug Open page.
	 * @return string
	 */
	public static function design_active_tab( $slug ) {
		return self::is_help_center_screen() ? self::own() : $slug;
	}

	/**
	 * The Help Center tabs as WPBot Core section tabs.
	 *
	 * @return void
	 */
	public static function design_tabs() {
		if ( ! self::design_on() || ! method_exists( '\\QuantumCloud\\WPBotCore\\Admin\\Design', 'subnav' ) ) {
			return;
		}
		$items   = array();
		$current = '';
		foreach ( self::tab_items() as $tab ) {
			$id      = sanitize_key( str_replace( array( '.php', '?', '&amp;', '&', '=' ), '-', $tab['slug'] ) );
			$items[] = array(
				'id'    => $id,
				'label' => $tab['label'],
				'url'   => $tab['url'],
			);
			if ( $tab['current'] ) {
				$current = $id;
			}
		}
		if ( count( $items ) > 1 ) {
			\QuantumCloud\WPBotCore\Admin\Design::subnav( $items, $current, __( 'Help Center', 'chatbot' ) );
		}
	}

	/**
	 * The Help Center's own menu slug.
	 *
	 * @return string
	 */
	public static function own() {
		return 'edit.php?post_type=' . Post_Type::NAME;
	}

	/**
	 * Move the Help Center under WPBot.
	 *
	 * @return void
	 */
	public static function move() {
		global $menu, $submenu;
		if ( ! is_array( $submenu ) || empty( $submenu[ self::own() ] ) ) {
			return;
		}
		$parent = self::wpbot_parent( $submenu );
		if ( '' === $parent || ! self::can_open( $parent, is_array( $menu ) ? $menu : array() ) ) {
			return;
		}
		$first = self::first_item( $submenu[ self::own() ] );
		if ( null === $first ) {
			return;
		}
		$submenu[ $parent ] = self::insert( $submenu[ $parent ], array( __( 'Help Center', 'chatbot' ), $first[1], self::own(), __( 'Help Center', 'chatbot' ) ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Placing the menu item.
		remove_menu_page( self::own() );
		self::$parent = $parent;
	}

	/**
	 * The WPBot parent menu: the one holding Permissions, else the one opening AI / Settings.
	 *
	 * @param array $submenu Global submenu.
	 * @return string
	 */
	public static function wpbot_parent( array $submenu ) {
		foreach ( $submenu as $parent => $items ) {
			foreach ( (array) $items as $item ) {
				if ( isset( $item[2] ) && self::BEFORE === $item[2] ) {
					return (string) $parent;
				}
			}
		}
		return isset( $submenu[ self::WPBOT_PAGE ] ) ? self::WPBOT_PAGE : '';
	}

	/**
	 * Whether the current user can open the WPBot top-level menu.
	 *
	 * @param string $slug Parent slug.
	 * @param array  $menu Global menu.
	 * @return bool
	 */
	private static function can_open( $slug, array $menu ) {
		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) && $slug === $item[2] ) {
				return isset( $item[1] ) && current_user_can( $item[1] );
			}
		}
		return false;
	}

	/**
	 * The first Help Center screen the user may open (usually All Articles).
	 *
	 * @param array $items Help Center submenu items.
	 * @return array|null
	 */
	private static function first_item( array $items ) {
		ksort( $items );
		foreach ( $items as $item ) {
			// All Articles when the user may open it (Get started is the first tab, not the landing page).
			if ( isset( $item[1], $item[2] ) && self::own() === $item[2] && current_user_can( $item[1] ) ) {
				return $item;
			}
		}
		foreach ( $items as $item ) {
			if ( isset( $item[1], $item[2] ) && current_user_can( $item[1] ) ) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * Insert the item above Permissions (at the end when Permissions is missing), keeping the order.
	 *
	 * @param array $items WPBot submenu items (position => item).
	 * @param array $item  The Help Center item.
	 * @return array Items, numbered again in order.
	 */
	public static function insert( array $items, array $item ) {
		ksort( $items );
		$out    = array();
		$placed = false;
		foreach ( $items as $existing ) {
			if ( ! $placed && isset( $existing[2] ) && self::BEFORE === $existing[2] ) {
				$out[]  = $item;
				$placed = true;
			}
			$out[] = $existing;
		}
		if ( ! $placed ) {
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * The hosts sort the WPBot menu by a list of slugs late on admin_menu: put the Help Center
	 * just before Permissions in it.
	 *
	 * @param mixed $order Menu slugs.
	 * @return array
	 */
	public static function order( $order ) {
		$order = array_values( array_diff( is_array( $order ) ? $order : array(), array( self::own() ) ) );
		$at    = array_search( self::BEFORE, $order, true );
		if ( false === $at ) {
			return $order;
		}
		array_splice( $order, (int) $at, 0, array( self::own() ) );
		return $order;
	}

	/**
	 * Whether this admin screen belongs to the Help Center.
	 *
	 * @return bool
	 */
	public static function is_help_center_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}
		if ( Post_Type::NAME === $screen->post_type ) {
			return true;
		}
		return ! empty( $screen->taxonomy ) && in_array( $screen->taxonomy, self::taxonomies(), true );
	}

	/**
	 * The Help Center's taxonomies.
	 *
	 * @return string[]
	 */
	private static function taxonomies() {
		return array( Taxonomies::KB, Taxonomies::CATEGORY, Taxonomies::TAG, \QuantumCloud\HelpCenter\Glossary\Glossary::TAXONOMY );
	}

	/**
	 * Open the WPBot menu on Help Center screens.
	 *
	 * @param string $parent_file Parent file.
	 * @return string
	 */
	public static function parent_file( $parent_file ) {
		global $_wp_real_parent_file;
		if ( '' === self::$parent || ! self::is_help_center_screen() ) {
			return $parent_file;
		}
		// WordPress looks for the parent again right after this filter (get_admin_page_parent()) and
		// would find the Help Center's own, hidden list. Point that list at WPBot. Set only here,
		// while the menu is drawn: page access was checked before, with the real parent.
		if ( ! is_array( $_wp_real_parent_file ) ) {
			$_wp_real_parent_file = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- See above.
		}
		$_wp_real_parent_file[ self::own() ] = self::$parent; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- See above.
		return self::$parent;
	}

	/**
	 * Mark "Help Center" as the current WPBot item on Help Center screens.
	 *
	 * @param string|null $submenu_file Submenu file.
	 * @param string      $parent_file  Parent file.
	 * @return string|null
	 */
	public static function submenu_file( $submenu_file, $parent_file = '' ) {
		unset( $parent_file );
		if ( ! self::is_help_center_screen() ) {
			return $submenu_file;
		}
		self::$current = (string) $submenu_file;
		return '' === self::$parent ? $submenu_file : self::own();
	}

	/**
	 * The Help Center screens as tabs: label, URL, whether current.
	 *
	 * @return array<int,array{label:string,url:string,current:bool}>
	 */
	public static function tab_items() {
		global $submenu, $plugin_page;
		$items = isset( $submenu[ self::own() ] ) ? (array) $submenu[ self::own() ] : array();
		ksort( $items );
		$items = self::sort_tabs( array_values( $items ) );
		$out   = array();
		foreach ( $items as $item ) {
			if ( ! isset( $item[0], $item[1], $item[2] ) || ! current_user_can( $item[1] ) ) {
				continue;
			}
			$slug  = (string) $item[2];
			$page  = false === strpos( $slug, '.php' );
			$url   = $page ? add_query_arg( 'page', $slug, admin_url( self::own() ) ) : admin_url( $slug );
			$now   = $page ? ( (string) $plugin_page === $slug ) : ( '' === (string) $plugin_page && self::$current === $slug );
			$out[] = array(
				'slug'    => $slug,
				'label'   => wp_strip_all_tags( (string) $item[0] ),
				'url'     => $url,
				'current' => $now,
			);
		}
		return $out;
	}

	/**
	 * Put the screens in tab order: listed slugs first (TAB_ORDER), then the rest as they were.
	 *
	 * @param array $items Submenu items.
	 * @return array
	 */
	public static function sort_tabs( array $items ) {
		$rank = array();
		foreach ( self::TAB_ORDER as $i => $slug ) {
			$rank[ str_replace( '&amp;', '&', $slug ) ] = $i;
		}
		$keyed = array();
		foreach ( array_values( $items ) as $pos => $item ) {
			$slug    = isset( $item[2] ) ? str_replace( '&amp;', '&', (string) $item[2] ) : '';
			$keyed[] = array( isset( $rank[ $slug ] ) ? $rank[ $slug ] : count( $rank ), $pos, $item );
		}
		usort(
			$keyed,
			static function ( $a, $b ) {
				return $a[0] === $b[0] ? $a[1] - $b[1] : $a[0] - $b[0];
			}
		);
		return array_column( $keyed, 2 );
	}

	/**
	 * Print the tabs at the top of Help Center screens (not in the block editor).
	 *
	 * @return void
	 */
	public static function tabs() {
		if ( ! self::is_help_center_screen() || self::design_on() ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return;
		}
		$items = self::tab_items();
		if ( count( $items ) < 2 ) {
			return;
		}
		echo '<nav class="qcld-hc-tabs" aria-label="' . esc_attr__( 'Help Center', 'chatbot' ) . '"><span class="qcld-hc-tabs-title"><span class="dashicons dashicons-sos" aria-hidden="true"></span>' . esc_html__( 'Help Center', 'chatbot' ) . '</span>';
		foreach ( $items as $tab ) {
			printf( '<a href="%1$s" class="qcld-hc-tab%2$s"%3$s>%4$s</a>', esc_url( $tab['url'] ), $tab['current'] ? ' is-current' : '', $tab['current'] ? ' aria-current="page"' : '', esc_html( $tab['label'] ) );
		}
		echo '</nav>';
	}

	/**
	 * The tabs' look.
	 *
	 * @return void
	 */
	public static function styles() {
		if ( ! self::is_help_center_screen() || self::design_on() ) {
			return;
		}
		echo '<style id="qcld-hc-tabs-css">.qcld-hc-tabs{display:flex;flex-wrap:wrap;align-items:center;gap:4px;margin:12px 20px 0 0;padding:6px 8px;background:#fff;border:1px solid #dcdcde;border-radius:6px}.qcld-hc-tabs-title{display:inline-flex;align-items:center;gap:4px;font-weight:600;margin-right:8px;color:#1d2327}.qcld-hc-tab{padding:5px 10px;border-radius:4px;text-decoration:none;color:#2c3338}.qcld-hc-tab:hover,.qcld-hc-tab:focus{background:#f0f0f1;color:#135e96}.qcld-hc-tab.is-current{background:#2271b1;color:#fff}@media (max-width:782px){.qcld-hc-tabs{margin-right:10px}}</style>';
	}
}
