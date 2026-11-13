<?php
/**
 * The chatbot module's admin pages (Train, Features, and pages other modules add such as
 * WPBot Pro's AI Advanced), for the host's ChatBot menu.
 *
 * Hosts show these pages in their own ChatBot menu (the row of tabs on the ChatBot screens)
 * instead of the WordPress menu: they return false from qcld_wpbot_chat/wp_menu_items, read
 * pages() for their tabs and print their header on qcld_wpbot_chat/admin_page_header. The
 * pages stay registered under the host's chatbot menu (so WordPress keeps that menu open and
 * highlighted on them) and are only hidden from it. A host that does none of this still gets
 * the pages in its chatbot menu, as before.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin pages registry.
 */
final class Admin_Pages {

	/**
	 * Stylesheet handle.
	 */
	const STYLE = 'qcld-wpbot-chat-admin';

	/**
	 * Hook in (admin only).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_menu', array( __CLASS__, 'title' ), PHP_INT_MAX );
		add_action( 'admin_head', array( __CLASS__, 'hide_menu_items' ) );
	}

	/**
	 * Whether the pages appear in the WordPress menu (hosts with their own ChatBot menu say no).
	 *
	 * @return bool
	 */
	public static function in_wp_menu() {
		/**
		 * Show the chatbot module's pages in the WordPress menu.
		 *
		 * @param bool $show Default true.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/wp_menu_items', true );
	}

	/**
	 * Hide the pages' items in the WordPress menu when the host lists them in its own menu.
	 *
	 * @return void
	 */
	public static function hide_menu_items() {
		if ( self::in_wp_menu() ) {
			return;
		}
		$rules = array();
		foreach ( self::slugs() as $slug ) {
			$rules[] = '#adminmenu .wp-submenu a[href$="page=' . sanitize_key( $slug ) . '"]';
		}
		echo '<style id="qcld-wpbot-chat-menu-items">' . implode( ',', $rules ) . '{display:none!important}</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from sanitized slugs.
	}

	/**
	 * Pages outside the WordPress menu (parent '') have no title for the admin header: give
	 * ours theirs.
	 *
	 * @return void
	 */
	public static function title() {
		global $title;
		if ( ! empty( $title ) || ! self::is_current() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the page slug.
		foreach ( self::pages() as $item ) {
			if ( $page === $item['slug'] ) {
				$title = $item['title']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The admin header reads it.
				return;
			}
		}
	}

	/**
	 * The pages, in menu order.
	 *
	 * @return array[] Each: slug, title, icon (dashicons class), capability, count (int).
	 */
	public static function pages() {
		$pages = array(
			array(
				'slug'       => Train_Page::SLUG,
				'title'      => __( 'Train', 'chatbot' ),
				'icon'       => 'dashicons-welcome-learn-more',
				'capability' => Train_Page::capability(),
				'count'      => 0,
			),
		);
		if ( Features_Page::enabled() ) {
			$pages[] = array(
				'slug'       => Features_Page::SLUG,
				'title'      => __( 'Features', 'chatbot' ),
				'icon'       => 'dashicons-screenoptions',
				'capability' => 'manage_options',
				'count'      => 0,
			);
		}
		/**
		 * The chatbot module's admin pages for the host's ChatBot menu (add yours here).
		 *
		 * @param array[] $pages Each: slug, title, icon, capability, count.
		 */
		$pages = (array) apply_filters( 'qcld_wpbot_chat/admin_pages', $pages );
		$out   = array();
		foreach ( $pages as $page ) {
			$page = (array) $page;
			$slug = isset( $page['slug'] ) ? sanitize_key( (string) $page['slug'] ) : '';
			if ( '' === $slug ) {
				continue;
			}
			$out[ $slug ] = array(
				'slug'       => $slug,
				'title'      => isset( $page['title'] ) ? (string) $page['title'] : $slug,
				'icon'       => isset( $page['icon'] ) ? sanitize_html_class( (string) $page['icon'] ) : 'dashicons-admin-generic',
				'capability' => isset( $page['capability'] ) ? (string) $page['capability'] : 'manage_options',
				'count'      => Train_Page::SLUG === $slug ? self::train_count() : ( isset( $page['count'] ) ? max( 0, (int) $page['count'] ) : 0 ),
			);
		}
		return array_values( $out );
	}

	/**
	 * Slugs of the pages.
	 *
	 * @return string[]
	 */
	public static function slugs() {
		return array_merge( array( Train_Page::SLUG, Features_Page::SLUG ), wp_list_pluck( self::pages(), 'slug' ) );
	}

	/**
	 * Whether the current admin request is one of the pages.
	 *
	 * @return bool
	 */
	public static function is_current() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the page slug.
		return '' !== $page && in_array( $page, self::slugs(), true );
	}

	/**
	 * Open knowledge gaps plus changes waiting for approval (cached), for the current user only
	 * when they may see Train.
	 *
	 * @return int
	 */
	public static function train_count() {
		return current_user_can( Train_Page::capability() ) ? Train_Page::open_count() : 0;
	}

	/**
	 * A count badge in WordPress's style ('' for 0).
	 *
	 * @param int $count Count.
	 * @return string HTML.
	 */
	public static function badge( $count ) {
		$count = (int) $count;
		if ( $count < 1 ) {
			return '';
		}
		return ' <span class="awaiting-mod count-' . $count . '"><span class="pending-count">' . esc_html( number_format_i18n( $count ) ) . '</span></span>';
	}

	/**
	 * The top of a page: the host's header and ChatBot menu, or a plain title when no host
	 * prints one.
	 *
	 * @param string $slug  Page slug.
	 * @param string $title Page title.
	 * @return void
	 */
	public static function header( $slug, $title ) {
		echo '<div class="wrap qcld-wpbot-page ' . esc_attr( str_replace( 'qcld-wpbot-ai-pro', 'qcld-wpbot-ai-pro qcld-wpbot-ai-advanced', $slug ) ) . '">';
		if ( has_action( 'qcld_wpbot_chat/admin_page_header' ) ) {
			/**
			 * Print the host's header and ChatBot menu at the top of a module page.
			 *
			 * @param string $slug Page slug.
			 */
			do_action( 'qcld_wpbot_chat/admin_page_header', $slug );
		}
		// WordPress moves admin notices to just after this marker (not into the header).
		echo '<hr class="wp-header-end" style="display:none"><h1 class="qcld-wpbot-page-title">' . esc_html( $title ) . '</h1>';
	}

	/**
	 * Close a page opened with header().
	 *
	 * @return void
	 */
	public static function footer() {
		echo '</div>';
	}

	/**
	 * The module's admin stylesheet on its pages.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! self::is_current() ) {
			return;
		}
		wp_enqueue_style( self::STYLE, plugins_url( 'assets/admin/pages.css', defined( 'QCLD_WPBOT_CHAT_FILE' ) ? QCLD_WPBOT_CHAT_FILE : dirname( __DIR__, 2 ) . '/chatbot.php' ), array(), defined( 'QCLD_WPBOT_CHAT_VERSION' ) ? QCLD_WPBOT_CHAT_VERSION : '1' );
	}
}
