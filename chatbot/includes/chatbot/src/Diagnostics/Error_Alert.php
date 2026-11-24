<?php
/**
 * Error log alert (owner, 14 November 2026): when WPBot's error log holds significant errors,
 * show how many and link to AI Settings > Error log.
 *
 * - Significant: entries of level "error" (crashes, PHP errors, failed AI and outside-service
 *   calls, failed emails and background jobs, browser errors) seen in the last 7 days. Warnings
 *   and notices do not count. Repeats are one entry, as on the Error log screen.
 * - A red count bubble on the host's WPBot menu item, on its AI Settings item and on "Error log"
 *   in the AI Settings menu.
 * - A notice at the top of WPBot's admin screens (the pages under the menu that holds AI
 *   Settings), with the number and a link to the error log. Dismissing it hides it for that user
 *   until an error newer than the dismissal is recorded. Not shown on the Error log itself.
 *
 * Needs WPBot Core's error log; with an older WPBot Core nothing is shown. Only users who can
 * manage options (the Error log's own capability) see the bubble and the notice.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Diagnostics;

use QuantumCloud\Chatbot\Admin\Ai_Settings_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Error count, menu bubbles and notice.
 */
final class Error_Alert {

	/**
	 * Days of errors counted.
	 */
	const DAYS = 7;

	/**
	 * User meta: last_seen (GMT) of the newest error when the user dismissed the notice.
	 */
	const DISMISSED = 'qcld_wpbot_chat_error_alert_dismissed';

	/**
	 * Dismiss action (admin-post) and nonce.
	 */
	const DISMISS_ACTION = 'qcld_wpbot_chat_error_alert_dismiss';

	/**
	 * Option: show the alert ('1', default) or not ('0'). Site-wide, autoloaded.
	 */
	const OPTION = 'qcld_wpbot_chat_error_alert';

	/**
	 * Summary of this request.
	 *
	 * @var array{count:int,newest:string}|null
	 */
	private static $summary = null;

	/**
	 * Hook in (admin).
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu_bubbles' ), PHP_INT_MAX );
		add_action( 'admin_head', array( __CLASS__, 'bubble_style' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ), 2 );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( __CLASS__, 'dismiss' ) );
		// The Error log settings form posts to WPBot Core's handler; the alert switch is saved
		// first (priority 5), then Core saves its keys and redirects.
		add_action( 'admin_post_qcld_wpbot_core_errors_settings', array( __CLASS__, 'save_setting' ), 5 );
	}

	/**
	 * Whether the current user sees the alert.
	 *
	 * @return bool
	 */
	public static function allowed() {
		return Error_Log_Screen::available() && self::active() && current_user_can( 'manage_options' );
	}

	/**
	 * Whether the alert shows at all: the owner's switch is on and the error log records.
	 *
	 * @return bool
	 */
	public static function active() {
		return self::enabled() && self::log_enabled();
	}

	/**
	 * The owner's "Show an alert when WPBot records errors" switch (on by default).
	 *
	 * @return bool
	 */
	public static function enabled() {
		/**
		 * Whether WPBot shows its error alert (menu count bubbles and the notice). Overrides the
		 * owner's setting both ways.
		 *
		 * @param bool $enabled The owner's setting (on by default).
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/error_alert_enabled', '0' !== (string) get_option( self::OPTION, '1' ) );
	}

	/**
	 * Whether WPBot Core's error log records anything. An older Core has no switch: on.
	 *
	 * @return bool
	 */
	public static function log_enabled() {
		$log = '\QuantumCloud\WPBotCore\Diagnostics\Error_Log';
		return ! method_exists( $log, 'enabled' ) || $log::enabled();
	}

	/**
	 * The alert setting a posted form asks for: '1' or '0', or null to keep the saved value.
	 * Only a form that says it sent the switch (alert_sent=1) changes it, and not while
	 * "Record errors" is unticked: the switch is then greyed out and not posted.
	 *
	 * @param array $post The posted fields.
	 * @return string|null
	 */
	public static function posted_setting( array $post ) {
		if ( empty( $post['alert_sent'] ) || ( ! empty( $post['record_sent'] ) && empty( $post['record'] ) ) ) {
			return null;
		}
		return ! empty( $post['alert'] ) ? '1' : '0';
	}

	/**
	 * Save the alert switch (admin-post, before WPBot Core's handler saves the rest).
	 *
	 * @return void
	 */
	public static function save_setting() {
		if ( ! Error_Log_Screen::available() || ! current_user_can( 'manage_options' ) ) {
			return;
			// Core's handler refuses the request.
		}
		$report = '\QuantumCloud\WPBotCore\Diagnostics\Support_Report';
		check_admin_referer( $report::NONCE );
		$value = self::posted_setting( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above; only flags are read.
		if ( null !== $value ) {
			update_option( self::OPTION, $value, true );
		}
	}

	/**
	 * Significant errors: how many in the last DAYS days, and when the newest was last seen.
	 *
	 * @return array{count:int,newest:string}
	 */
	public static function summary() {
		if ( null !== self::$summary ) {
			return self::$summary;
		}
		$summary = array(
			'count'  => 0,
			'newest' => '',
		);
		// Alert or log off: nothing to count, so no query.
		if ( Error_Log_Screen::available() && self::active() ) {
			$log     = '\QuantumCloud\WPBotCore\Diagnostics\Error_Log';
			$found   = $log::items(
				array(
					'level'    => 'error',
					// Errors from other plugins, the theme or WordPress are not WPBot's to alert about.
					'origin'   => 'wpbot',
					'since'    => gmdate( 'Y-m-d H:i:s', time() - self::DAYS * DAY_IN_SECONDS ),
					'per_page' => 200,
				)
			);
			$summary = self::count_items( isset( $found['items'] ) ? (array) $found['items'] : array(), isset( $found['total'] ) ? (int) $found['total'] : 0 );
		}
		/**
		 * The error log alert's numbers (count of significant errors, newest last_seen in GMT).
		 *
		 * @param array{count:int,newest:string} $summary Summary.
		 */
		$summary       = (array) apply_filters( 'qcld_wpbot_chat/error_alert_summary', $summary );
		self::$summary = array(
			'count'  => isset( $summary['count'] ) ? max( 0, (int) $summary['count'] ) : 0,
			'newest' => isset( $summary['newest'] ) ? (string) $summary['newest'] : '',
		);
		return self::$summary;
	}

	/**
	 * Count the errors that can affect visitors' chats. A request from the site to itself (a
	 * background self-check such as the llms.txt test or the upload protection test) is left out:
	 * the visitor's chat never depends on it (QA 9.1: a red banner on every WPBot screen).
	 *
	 * @param array[] $items Newest errors first (up to one page).
	 * @param int     $total All errors (when more than one page).
	 * @return array{count:int,newest:string}
	 */
	public static function count_items( array $items, $total ) {
		$home    = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$skipped = 0;
		$newest  = '';
		foreach ( $items as $item ) {
			if ( ! self::counts( (array) $item, $home ) ) {
				++$skipped;
				continue;
			}
			if ( '' === $newest && isset( $item['last_seen'] ) ) {
				$newest = (string) $item['last_seen'];
			}
		}
		return array(
			'count'  => max( 0, max( (int) $total, count( $items ) ) - $skipped ),
			'newest' => $newest,
		);
	}

	/**
	 * Whether one error counts for the alert.
	 *
	 * @param array  $item Error log item (source, detail.host).
	 * @param string $home The site's host.
	 * @return bool
	 */
	public static function counts( array $item, $home ) {
		$host   = isset( $item['detail']['host'] ) ? strtolower( (string) $item['detail']['host'] ) : '';
		$counts = ! ( isset( $item['source'] ) && 'http' === $item['source'] && '' !== $host && strtolower( (string) $home ) === $host );
		// Only WPBot's own errors (an older WPBot Core gives no origin: everything it logged was WPBot's).
		if ( isset( $item['origin'] ) && 'wpbot' !== $item['origin'] ) {
			$counts = false;
		}
		/**
		 * Whether an error counts for the red "WPBot recorded N errors" alert.
		 *
		 * @param bool  $counts Default: WPBot's own errors, except requests from the site to itself.
		 * @param array $item   Error log item.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/error_alert_counts', $counts, $item );
	}

	/**
	 * Forget the summary of this request (after the log changed, and in tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$summary = null;
	}

	/**
	 * Whether the notice shows: there are errors, and none was dismissed since the newest one.
	 *
	 * @param int    $count     Significant errors.
	 * @param string $newest    Newest error's last_seen (GMT, Y-m-d H:i:s).
	 * @param string $dismissed The user's dismissal mark ('' = never).
	 * @return bool
	 */
	public static function should_show( $count, $newest, $dismissed ) {
		if ( (int) $count < 1 ) {
			return false;
		}
		return '' === (string) $dismissed || strcmp( (string) $newest, (string) $dismissed ) > 0;
	}

	/**
	 * WordPress's count bubble (the same markup as the update and comment counts).
	 *
	 * @param int    $count Count.
	 * @param string $label Words for screen readers, such as "3 errors in the error log".
	 * @return string '' when the count is 0.
	 */
	public static function bubble( $count, $label ) {
		$count = (int) $count;
		if ( $count < 1 ) {
			return '';
		}
		return sprintf(
			' <span class="awaiting-mod count-%1$d qcwp-error-bubble"><span class="pending-count" aria-hidden="true">%1$d</span><span class="screen-reader-text">%2$s</span></span>',
			$count,
			esc_html( $label )
		);
	}

	/**
	 * Words for screen readers.
	 *
	 * @param int $count Count.
	 * @return string
	 */
	public static function label( $count ) {
		/* translators: %d: number of errors. */
		return sprintf( _n( '%d error in the error log', '%d errors in the error log', (int) $count, 'chatbot' ), (int) $count );
	}

	/**
	 * Admin pages that count as WPBot screens: the menu that holds AI Settings and its pages.
	 *
	 * @param array  $submenu WordPress's $submenu.
	 * @param string $slug    AI Settings page slug.
	 * @return array{parent:string,pages:string[]} parent: the menu's slug ('' when AI Settings is not in a menu).
	 */
	public static function screens( array $submenu, $slug ) {
		foreach ( $submenu as $parent => $items ) {
			$pages = array();
			$found = false;
			foreach ( (array) $items as $item ) {
				if ( isset( $item[2] ) ) {
					$pages[] = (string) $item[2];
					$found   = $found || (string) $item[2] === (string) $slug;
				}
			}
			if ( $found ) {
				return array(
					'parent' => (string) $parent,
					'pages'  => array_values( array_unique( array_merge( array( (string) $parent ), $pages ) ) ),
				);
			}
		}
		return array(
			'parent' => '',
			'pages'  => array( (string) $slug ),
		);
	}

	/**
	 * Add the bubble to the menu that holds AI Settings and to its AI Settings item.
	 *
	 * @param array  $menu    WordPress's $menu.
	 * @param array  $submenu WordPress's $submenu.
	 * @param string $slug    AI Settings page slug.
	 * @param string $bubble  Bubble markup.
	 * @return array{0:array,1:array} The menu and submenu.
	 */
	public static function add_bubbles( array $menu, array $submenu, $slug, $bubble ) {
		if ( '' === $bubble ) {
			return array( $menu, $submenu );
		}
		$parent = self::screens( $submenu, $slug )['parent'];
		if ( '' === $parent ) {
			return array( $menu, $submenu );
		}
		foreach ( $menu as $i => $item ) {
			if ( isset( $item[0], $item[2] ) && (string) $item[2] === $parent && false === strpos( (string) $item[0], 'qcwp-error-bubble' ) ) {
				$menu[ $i ][0] = $item[0] . $bubble;
			}
		}
		foreach ( (array) $submenu[ $parent ] as $i => $item ) {
			if ( isset( $item[0], $item[2] ) && (string) $item[2] === (string) $slug && false === strpos( (string) $item[0], 'qcwp-error-bubble' ) ) {
				$submenu[ $parent ][ $i ][0] = $item[0] . $bubble;
			}
		}
		return array( $menu, $submenu );
	}

	/**
	 * Bubbles on the WPBot menu item and its AI Settings item.
	 *
	 * @return void
	 */
	public static function menu_bubbles() {
		global $menu, $submenu;
		if ( ! self::allowed() || ! is_array( $menu ) || ! is_array( $submenu ) ) {
			return;
		}
		$count                          = self::summary()['count'];
		list( $new_menu, $new_submenu ) = self::add_bubbles( $menu, $submenu, Ai_Settings_Page::SLUG, self::bubble( $count, self::label( $count ) ) );
		$menu                           = $new_menu; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Adding the count bubble, as WordPress core does for updates.
		$submenu                        = $new_submenu; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Same.
	}

	/**
	 * Keep the error bubble red in every admin color scheme (some schemes make counts blue or
	 * dark, which on the highlighted WPBot menu item is hard to see).
	 *
	 * @return void
	 */
	public static function bubble_style() {
		if ( ! self::allowed() || self::summary()['count'] < 1 ) {
			return;
		}
		echo '<style id="qcwp-error-bubble-css">#adminmenu .qcwp-error-bubble,#adminmenu li.current a .qcwp-error-bubble,#adminmenu li:hover a .qcwp-error-bubble,#adminmenu li a.wp-has-current-submenu .qcwp-error-bubble{background:#d63638!important;color:#fff!important}</style>';
	}

	/**
	 * The bubble for an AI Settings menu entry (only "Error log" has one).
	 *
	 * @param string $section Section id.
	 * @return string
	 */
	public static function section_bubble( $section ) {
		if ( 'errors' !== $section || ! self::allowed() ) {
			return '';
		}
		$count = self::summary()['count'];
		return self::bubble( $count, self::label( $count ) );
	}

	/**
	 * The notice at the top of WPBot's screens.
	 *
	 * @return void
	 */
	public static function notice() {
		if ( ! self::allowed() ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Which screen is open.
		$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		// phpcs:enable
		global $submenu;
		$screens = self::screens( is_array( $submenu ) ? $submenu : array(), Ai_Settings_Page::SLUG );
		$pages   = array_map( 'sanitize_key', $screens['pages'] );
		/**
		 * Admin pages (slugs) that show the error log notice.
		 *
		 * @param string[] $pages Page slugs: the menu that holds AI Settings and its pages.
		 */
		$pages = (array) apply_filters( 'qcld_wpbot_chat/error_alert_pages', $pages );
		if ( '' === $page || ! in_array( $page, $pages, true ) || ( sanitize_key( Ai_Settings_Page::SLUG ) === $page && 'errors' === $section ) ) {
			return;
		}
		$summary = self::summary();
		if ( ! self::should_show( $summary['count'], $summary['newest'], (string) get_user_meta( get_current_user_id(), self::DISMISSED, true ) ) ) {
			return;
		}
		$dismiss = wp_nonce_url(
			add_query_arg(
				array(
					'action'           => self::DISMISS_ACTION,
					'_wp_http_referer' => rawurlencode( (string) wp_unslash( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_get_referer() validates it on the way back.
				),
				admin_url( 'admin-post.php' )
			),
			self::DISMISS_ACTION
		);
		echo '<div class="notice notice-error qcwp-error-alert" role="region" aria-labelledby="qcwp-error-alert-title"><p>';
		echo '<span class="dashicons dashicons-warning" aria-hidden="true"></span> ';
		/* translators: 1: number of errors, 2: number of days. */
		echo '<strong id="qcwp-error-alert-title">' . esc_html( sprintf( _n( 'WPBot recorded %1$d error in the last %2$d days.', 'WPBot recorded %1$d errors in the last %2$d days.', $summary['count'], 'chatbot' ), $summary['count'], self::DAYS ) ) . '</strong> ';
		echo esc_html__( 'Something may not work for your visitors.', 'chatbot' ) . ' ';
		echo '<a class="button button-primary" href="' . esc_url( Ai_Settings_Page::url( 'errors' ) ) . '">' . esc_html__( 'Open the error log', 'chatbot' ) . '</a> ';
		echo '<a class="button-link qcwp-error-alert__dismiss" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss until a new error', 'chatbot' ) . '</a>';
		echo '</p></div>';
		echo '<style>.qcwp-error-alert p{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px}.qcwp-error-alert .dashicons{color:#d63638}.qcwp-error-alert__dismiss{margin-left:auto}</style>';
	}

	/**
	 * Dismiss the notice until a newer error is recorded, then go back.
	 *
	 * @return void
	 */
	public static function dismiss() {
		check_admin_referer( self::DISMISS_ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'chatbot' ), 403 );
		}
		$newest = self::summary()['newest'];
		update_user_meta( get_current_user_id(), self::DISMISSED, '' !== $newest ? $newest : gmdate( 'Y-m-d H:i:s' ) );
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : Ai_Settings_Page::url( 'errors' ) );
		exit;
	}
}
