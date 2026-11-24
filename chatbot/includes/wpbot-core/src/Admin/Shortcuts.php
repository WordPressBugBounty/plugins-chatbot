<?php
/**
 * WPBot Shortcuts (owner, 20 November 2026): a button on every WPBot page, "Visit this page
 * often? Add shortcut to the Admin Bar", adds the page to a "WPBot Shortcuts" menu on the
 * WordPress admin bar. The menu shows once the first shortcut is added.
 *
 * - Each user keeps their own shortcuts (user meta), at most MAX.
 * - The menu ends with "Manage shortcuts…", a page to rename, reorder and remove them.
 * - A WPBot page is a page of a WPBot host's menu (Host_Adapter::get_menu_parent()), including
 *   the pages a host lists elsewhere; modules and hosts can add pages with the filter
 *   qcld_wpbot_core/shortcut_page.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Admin;

use QuantumCloud\WPBotCore\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Admin bar shortcuts.
 */
final class Shortcuts {

	/**
	 * User meta: the user's shortcuts.
	 */
	const META = 'qcld_wpbot_core_shortcuts';

	/**
	 * At most this many shortcuts a user.
	 */
	const MAX = 20;

	/**
	 * The Manage shortcuts page.
	 */
	const SLUG = 'wpbot-shortcuts';

	/**
	 * Admin bar node.
	 */
	const NODE = 'wpbot-shortcuts';

	/**
	 * Script and style handle.
	 */
	const HANDLE = 'qcld-wpbot-core-shortcuts';

	/**
	 * Query arguments that never belong in a shortcut (one-time states and nonces).
	 */
	const DROP_ARGS = array( '_wpnonce', 'nonce', '_wp_http_referer', 'action', 'action2', 'message', 'msg', 'updated', 'settings-updated', 'saved', 'deleted', 'error', 'notice', 'paged' );

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'admin_bar_menu', array( self::class, 'admin_bar' ), 80 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'bar_style' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'bar_style' ) );
		add_action( 'admin_menu', array( self::class, 'register_page' ), 99 );
		add_action( 'admin_footer', array( self::class, 'button' ) );
		add_action( 'admin_post_qcld_wpbot_core_shortcuts', array( self::class, 'handle_manage' ) );
	}

	// Storage.

	/**
	 * A user's shortcuts.
	 *
	 * @param int $user_id User.
	 * @return array<int,array{url:string,title:string,group:string}>
	 */
	public static function items( $user_id ) {
		$saved = get_user_meta( (int) $user_id, self::META, true );
		$out   = array();
		foreach ( is_array( $saved ) ? $saved : array() as $item ) {
			$item = self::clean_item( is_array( $item ) ? $item : array() );
			if ( null !== $item && ! self::find( $out, $item['url'] ) ) {
				$out[] = $item;
			}
		}
		return array_slice( $out, 0, self::MAX );
	}

	/**
	 * Add a shortcut (or keep it when it is there).
	 *
	 * @param int    $user_id User.
	 * @param string $url     Admin URL or path.
	 * @param string $title   Name.
	 * @param string $group   Section it belongs to.
	 * @return array{ok:bool,items:array,code:string}
	 */
	public static function add( $user_id, $url, $title, $group = '' ) {
		$items = self::items( $user_id );
		$item  = self::clean_item(
			array(
				'url'   => $url,
				'title' => $title,
				'group' => $group,
			)
		);
		if ( null === $item ) {
			return array(
				'ok'    => false,
				'items' => $items,
				'code'  => 'invalid',
			);
		}
		if ( self::find( $items, $item['url'] ) ) {
			return array(
				'ok'    => true,
				'items' => $items,
				'code'  => 'exists',
			);
		}
		if ( count( $items ) >= self::MAX ) {
			return array(
				'ok'    => false,
				'items' => $items,
				'code'  => 'full',
			);
		}
		$items[] = $item;
		self::store( $user_id, $items );
		return array(
			'ok'    => true,
			'items' => $items,
			'code'  => 'added',
		);
	}

	/**
	 * Remove a shortcut.
	 *
	 * @param int    $user_id User.
	 * @param string $url     Admin URL or path.
	 * @return array The shortcuts left.
	 */
	public static function remove( $user_id, $url ) {
		$path  = self::path( $url );
		$items = array_values(
			array_filter(
				self::items( $user_id ),
				static function ( $item ) use ( $path ) {
					return $item['url'] !== $path;
				}
			)
		);
		self::store( $user_id, $items );
		return $items;
	}

	/**
	 * Replace the list (Manage shortcuts: new names and order; missing ones are removed).
	 *
	 * @param int   $user_id User.
	 * @param array $items   url, title, group.
	 * @return array What was saved.
	 */
	public static function replace( $user_id, array $items ) {
		$out = array();
		foreach ( $items as $item ) {
			$item = self::clean_item( is_array( $item ) ? $item : array() );
			if ( null !== $item && ! self::find( $out, $item['url'] ) ) {
				$out[] = $item;
			}
		}
		$out = array_slice( $out, 0, self::MAX );
		self::store( $user_id, $out );
		return $out;
	}

	/**
	 * The admin path a shortcut keeps: "admin.php?page=…" with its own arguments, no nonces or
	 * one-time states. '' when the URL is not a page of this site's admin.
	 *
	 * @param string $url Admin URL or path.
	 * @return string
	 */
	public static function path( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$admin       = function_exists( 'admin_url' ) ? admin_url() : '/wp-admin/';
		$admin_parts = wp_parse_url( $admin );
		$parts       = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! is_array( $admin_parts ) ) {
			return '';
		}
		// Another site's address is never a shortcut.
		if ( isset( $parts['host'] ) && ( ! isset( $admin_parts['host'] ) || strtolower( $parts['host'] ) !== strtolower( $admin_parts['host'] ) ) ) {
			return '';
		}
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '';
		if ( false !== strpos( $path, '/' ) ) {
			$admin_path = isset( $admin_parts['path'] ) ? (string) $admin_parts['path'] : '/wp-admin/';
			if ( 0 !== strpos( $path, $admin_path ) ) {
				return '';
			}
			$path = substr( $path, strlen( $admin_path ) );
		}
		$file = $path;
		if ( ! preg_match( '/^[a-z0-9_-]+\.php$/', $file ) ) {
			return '';
		}
		$args = array();
		if ( isset( $parts['query'] ) ) {
			parse_str( (string) $parts['query'], $args );
		}
		$clean = array();
		foreach ( $args as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || in_array( $key, self::DROP_ARGS, true ) || is_array( $value ) ) {
				continue;
			}
			$clean[ $key ] = sanitize_text_field( (string) $value );
		}
		if ( 'admin.php' === $file && empty( $clean['page'] ) ) {
			return '';
		}
		// The page argument first, so the same page is always the same shortcut.
		if ( isset( $clean['page'] ) ) {
			// Page names keep their case (AI Settings is "wpbot_openAi").
			$clean = array( 'page' => preg_replace( '/[^A-Za-z0-9_\-]/', '', $clean['page'] ) ) + $clean;
		}
		return $clean ? $file . '?' . http_build_query( $clean, '', '&' ) : $file;
	}

	/**
	 * A clean shortcut, or null when its address is not usable.
	 *
	 * @param array $item url, title, group.
	 * @return array{url:string,title:string,group:string}|null
	 */
	private static function clean_item( array $item ) {
		$path = self::path( isset( $item['url'] ) ? (string) $item['url'] : '' );
		if ( '' === $path ) {
			return null;
		}
		$title = self::text( isset( $item['title'] ) ? (string) $item['title'] : '' );
		$group = self::text( isset( $item['group'] ) ? (string) $item['group'] : '' );
		return array(
			'url'   => $path,
			'title' => '' !== $title ? $title : $path,
			'group' => $group,
		);
	}

	/**
	 * A short label.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function text( $text ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 80 ) : substr( $text, 0, 80 );
	}

	/**
	 * Whether a list has a shortcut to this path.
	 *
	 * @param array  $items Shortcuts.
	 * @param string $path  Path.
	 * @return bool
	 */
	private static function find( array $items, $path ) {
		foreach ( $items as $item ) {
			if ( $item['url'] === $path ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Save a user's list (removed when empty).
	 *
	 * @param int   $user_id User.
	 * @param array $items   Shortcuts.
	 * @return void
	 */
	private static function store( $user_id, array $items ) {
		if ( $items ) {
			update_user_meta( (int) $user_id, self::META, array_values( $items ) );
		} else {
			delete_user_meta( (int) $user_id, self::META );
		}
	}

	// Admin bar.

	/**
	 * The "WPBot Shortcuts" menu, once the user has a shortcut.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 * @return void
	 */
	public static function admin_bar( $bar ) {
		if ( ! is_object( $bar ) || ! is_user_logged_in() ) {
			return;
		}
		$items = self::items( get_current_user_id() );
		if ( ! $items ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => self::NODE,
				'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'WPBot Shortcuts', 'chatbot' ) . '</span>',
				'href'  => admin_url( $items[0]['url'] ),
				'meta'  => array( 'class' => 'qcld-wpbot-shortcuts' ),
			)
		);
		foreach ( $items as $i => $item ) {
			$group = '' !== $item['group'] && $item['group'] !== $item['title'] ? '<span class="qcld-wpbot-shortcut-group">' . esc_html( $item['group'] ) . '</span>' : '';
			$bar->add_node(
				array(
					'parent' => self::NODE,
					'id'     => self::NODE . '-' . $i,
					'title'  => '<span class="qcld-wpbot-shortcut-title">' . esc_html( $item['title'] ) . '</span>' . $group,
					'href'   => admin_url( $item['url'] ),
				)
			);
		}
		$bar->add_node(
			array(
				'parent' => self::NODE,
				'id'     => self::NODE . '-manage',
				'title'  => esc_html__( 'Manage shortcuts…', 'chatbot' ),
				'href'   => self::manage_url(),
				'meta'   => array( 'class' => 'qcld-wpbot-shortcuts-manage' ),
			)
		);
	}

	/**
	 * The menu's icon and layout, where the admin bar shows.
	 *
	 * @return void
	 */
	public static function bar_style() {
		if ( ! is_admin_bar_showing() ) {
			return;
		}
		wp_add_inline_style( 'admin-bar', self::bar_css() );
	}

	/**
	 * Admin bar styles.
	 *
	 * @return string
	 */
	public static function bar_css() {
		return '#wpadminbar #wp-admin-bar-' . self::NODE . ' > .ab-item .ab-icon:before{content:"\f155";top:2px}'
			. '#wpadminbar #wp-admin-bar-' . self::NODE . ' .ab-submenu .ab-item{display:flex!important;align-items:center;gap:16px;min-width:240px}'
			. '#wpadminbar #wp-admin-bar-' . self::NODE . ' .qcld-wpbot-shortcut-group{margin-left:auto;color:#a7aaad;font-size:11px}'
			. '#wpadminbar #wp-admin-bar-' . self::NODE . ' .qcld-wpbot-shortcuts-manage{border-top:1px solid rgba(255,255,255,.12);margin-top:4px;padding-top:4px}'
			. '@media screen and (max-width:782px){#wpadminbar #wp-admin-bar-' . self::NODE . '{display:block}#wpadminbar #wp-admin-bar-' . self::NODE . ' > .ab-item .ab-label{display:none}#wpadminbar #wp-admin-bar-' . self::NODE . ' > .ab-item .ab-icon:before{font-size:26px;top:3px}}';
	}

	// The button.

	/**
	 * Whether the current admin screen is a WPBot page.
	 *
	 * @return bool
	 */
	public static function is_wpbot_page() {
		global $plugin_page, $submenu;
		$slug = is_string( $plugin_page ) ? $plugin_page : '';
		if ( '' === $slug || self::SLUG === $slug ) {
			$on = false;
		} else {
			$on      = false;
			$parent  = function_exists( 'get_admin_page_parent' ) ? (string) get_admin_page_parent() : '';
			$parents = array();
			foreach ( Core::instance()->hosts() as $host ) {
				$parents[] = (string) $host->get_menu_parent();
			}
			foreach ( array_filter( $parents ) as $menu ) {
				if ( $menu === $slug || $menu === $parent ) {
					$on = true;
					break;
				}
				foreach ( isset( $submenu[ $menu ] ) ? (array) $submenu[ $menu ] : array() as $entry ) {
					if ( isset( $entry[2] ) && $entry[2] === $slug ) {
						$on = true;
						break 2;
					}
				}
			}
		}
		/**
		 * Whether the "Add shortcut to the Admin Bar" button shows on this admin page.
		 *
		 * @param bool   $on   Whether it is a page of a WPBot menu.
		 * @param string $slug The page argument.
		 */
		return (bool) apply_filters( 'qcld_wpbot_core/shortcut_page', $on, $slug );
	}

	/**
	 * Print the button (placed under the page header by shortcuts.js).
	 *
	 * @return void
	 */
	public static function button() {
		if ( ! is_user_logged_in() || ! self::is_wpbot_page() ) {
			return;
		}
		$version = defined( 'QCLD_WPBOT_CORE_VERSION' ) ? QCLD_WPBOT_CORE_VERSION : '1';
		wp_enqueue_style( self::HANDLE, Core::url( 'assets/css/shortcuts.css' ), array(), $version );
		wp_enqueue_script( self::HANDLE, Core::url( 'assets/js/shortcuts.js' ), array(), $version, true );
		$items = self::items( get_current_user_id() );
		wp_localize_script(
			self::HANDLE,
			'qcldWpbotShortcuts',
			array(
				'rest'   => esc_url_raw( rest_url( 'wpbot-core/v1/shortcuts' ) ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'admin'  => esc_url_raw( admin_url() ),
				'manage' => esc_url_raw( self::manage_url() ),
				'title'  => self::text( (string) get_admin_page_title() ),
				'urls'   => wp_list_pluck( $items, 'url' ),
				'max'    => self::MAX,
				'i18n'   => array(
					'ask'     => __( 'Visit this page often?', 'chatbot' ),
					'add'     => __( 'Add shortcut to the Admin Bar', 'chatbot' ),
					'added'   => __( 'Added to', 'chatbot' ),
					'menu'    => __( 'WPBot Shortcuts', 'chatbot' ),
					'remove'  => __( 'Remove', 'chatbot' ),
					'manage'  => __( 'Manage shortcuts…', 'chatbot' ),
					'full'    => __( 'You have 20 shortcuts. Remove one in Manage shortcuts to add this page.', 'chatbot' ),
					'failed'  => __( 'The shortcut could not be saved. Reload the page and try again.', 'chatbot' ),
					'working' => __( 'Saving…', 'chatbot' ),
					// The header band's short labels (WPBot Core's admin design).
					'short'   => __( 'Add shortcut', 'chatbot' ),
					'shorted' => __( 'Shortcut added', 'chatbot' ),
				),
			)
		);
		// The script is printed after this hook's other output; make sure it runs.
		wp_print_styles( self::HANDLE );
		wp_print_scripts( self::HANDLE );
	}

	// Manage shortcuts.

	/**
	 * Address of the Manage shortcuts page.
	 *
	 * @return string
	 */
	public static function manage_url() {
		return admin_url( 'admin.php?page=' . self::SLUG );
	}

	/**
	 * Register the page (not in any menu: the admin bar links to it).
	 *
	 * @return void
	 */
	public static function register_page() {
		add_submenu_page( '', __( 'WPBot Shortcuts', 'chatbot' ), __( 'WPBot Shortcuts', 'chatbot' ), 'read', self::SLUG, array( self::class, 'render_manage' ) );
	}

	/**
	 * The Manage shortcuts page.
	 *
	 * @return void
	 */
	public static function render_manage() {
		$items = self::items( get_current_user_id() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A message after the redirect.
		$saved = isset( $_GET['qcld-saved'] );
		echo '<div class="wrap qcld-wpbot-shortcuts-page"><h1>' . esc_html__( 'WPBot Shortcuts', 'chatbot' ) . '</h1>';
		if ( $saved ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Shortcuts saved.', 'chatbot' ) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Your shortcuts in the admin bar. Rename them, change their order or remove them. To add a page, open it and click "Add shortcut to the Admin Bar" under its header.', 'chatbot' ) . '</p>';
		if ( ! $items ) {
			echo '<p><strong>' . esc_html__( 'You have no shortcuts yet.', 'chatbot' ) . '</strong></p></div>';
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'qcld_wpbot_core_shortcuts' );
		echo '<input type="hidden" name="action" value="qcld_wpbot_core_shortcuts">';
		echo '<table class="widefat striped qcld-wpbot-shortcuts-table"><thead><tr><th scope="col">' . esc_html__( 'Name', 'chatbot' ) . '</th><th scope="col">' . esc_html__( 'Page', 'chatbot' ) . '</th><th scope="col">' . esc_html__( 'Order', 'chatbot' ) . '</th><th scope="col">' . esc_html__( 'Remove', 'chatbot' ) . '</th></tr></thead><tbody>';
		$last = count( $items ) - 1;
		foreach ( $items as $i => $item ) {
			echo '<tr><td><input type="text" class="regular-text" name="shortcuts[' . (int) $i . '][title]" value="' . esc_attr( $item['title'] ) . '" aria-label="' . esc_attr__( 'Name', 'chatbot' ) . '">';
			echo '<input type="hidden" name="shortcuts[' . (int) $i . '][url]" value="' . esc_attr( $item['url'] ) . '"><input type="hidden" name="shortcuts[' . (int) $i . '][group]" value="' . esc_attr( $item['group'] ) . '"></td>';
			echo '<td><a href="' . esc_url( admin_url( $item['url'] ) ) . '">' . esc_html( '' !== $item['group'] ? $item['group'] : $item['url'] ) . '</a></td>';
			echo '<td>';
			echo '<button type="submit" class="button button-small" name="move" value="' . esc_attr( $i . ':up' ) . '"' . disabled( 0 === $i, true, false ) . ' aria-label="' . esc_attr__( 'Move up', 'chatbot' ) . '">&uarr;</button> ';
			echo '<button type="submit" class="button button-small" name="move" value="' . esc_attr( $i . ':down' ) . '"' . disabled( $last === $i, true, false ) . ' aria-label="' . esc_attr__( 'Move down', 'chatbot' ) . '">&darr;</button>';
			echo '</td><td><label><input type="checkbox" name="shortcuts[' . (int) $i . '][remove]" value="1"> <span class="screen-reader-text">' . esc_html__( 'Remove', 'chatbot' ) . '</span></label></td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save shortcuts', 'chatbot' ) . '</button></p></form></div>';
	}

	/**
	 * Save Manage shortcuts.
	 *
	 * @return void
	 */
	public static function handle_manage() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please log in.', 'chatbot' ) );
		}
		check_admin_referer( 'qcld_wpbot_core_shortcuts' );
		$posted = isset( $_POST['shortcuts'] ) && is_array( $_POST['shortcuts'] ) ? wp_unslash( $_POST['shortcuts'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is cleaned by replace().
		$move   = isset( $_POST['move'] ) ? sanitize_text_field( wp_unslash( $_POST['move'] ) ) : '';
		self::replace( get_current_user_id(), self::posted_items( (array) $posted, $move ) );
		wp_safe_redirect( add_query_arg( 'qcld-saved', '1', self::manage_url() ) );
		exit;
	}

	/**
	 * The list Manage shortcuts posted: names, removals, and one move ("index:up" or "index:down").
	 *
	 * @param array  $posted Rows: url, title, group, remove.
	 * @param string $move   Move.
	 * @return array
	 */
	public static function posted_items( array $posted, $move = '' ) {
		$rows = array_values( $posted );
		if ( preg_match( '/^(\d+):(up|down)$/', (string) $move, $m ) ) {
			$from = (int) $m[1];
			$to   = 'up' === $m[2] ? $from - 1 : $from + 1;
			if ( isset( $rows[ $from ], $rows[ $to ] ) ) {
				$keep          = $rows[ $to ];
				$rows[ $to ]   = $rows[ $from ];
				$rows[ $from ] = $keep;
			}
		}
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['remove'] ) ) {
				continue;
			}
			$out[] = array(
				'url'   => isset( $row['url'] ) ? (string) $row['url'] : '',
				'title' => isset( $row['title'] ) ? (string) $row['title'] : '',
				'group' => isset( $row['group'] ) ? (string) $row['group'] : '',
			);
		}
		return $out;
	}
}
