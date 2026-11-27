<?php
/**
 * WPBot Free in WPBot Core's admin design (owner, 20 November 2026: "Premium Studio", emerald).
 *
 * WPBot Core prints the header band, the Notices bell and the footer on every WPBot page
 * (Admin\Design). WPBot Free adds what only it knows (WPBot Free's code stops when Pro is active):
 * - its links (Docs, Help, Upgrade to Pro), logo (the animated WPBot logo), version and light/dark switch;
 * - which band tab a ChatBot page belongs to (the ChatBot tab);
 * - page heads in place of the old "WPBot - Growth Suite" / "WPBot Control Panel" headers (head());
 * - the admin theme's emerald palette and system font on WPBot pages (css/admin-theme-wpbot.css),
 *   with Light for users who never chose a look.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

/**
 * WPBot Free admin design.
 */
final class QCLD_WPBot_Free_Design {

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function boot() {
		add_filter( 'qcld_wpbot_core/design_page', array( __CLASS__, 'is_page' ), 10, 2 );
		add_filter( 'qcld_wpbot_core/design_links', array( __CLASS__, 'links' ) );
		add_filter( 'qcld_wpbot_core/design_footer_links', array( __CLASS__, 'footer_links' ) );
		add_filter( 'qcld_wpbot_core/design_logo', array( __CLASS__, 'logo' ) );
		add_filter( 'qcld_wpbot_core/design_version', array( __CLASS__, 'version' ) );
		add_filter( 'qcld_wpbot_core/design_active_tab', array( __CLASS__, 'active_tab' ) );
		add_action( 'qcld_wpbot_core/design_actions', array( __CLASS__, 'actions' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 30 );
		// The admin theme: Light for users who never chose a look; no serif heading font.
		add_filter( 'qcld_admin_theme_default_mode', array( __CLASS__, 'default_mode' ) );
		add_filter( 'qcld_admin_theme_font_url', '__return_empty_string' );
		add_filter( 'qcld_admin_theme_plugins', array( __CLASS__, 'theme_skip' ), 20 );
		// The GRID's status tiles: where they show, and the chat numbers (WPBot Core's Hud_Stats).
		add_filter( 'qcld_wpbot_core/design_hud_tiles', array( __CLASS__, 'hud_tiles' ), 10, 2 );
		add_filter( 'qcld_wpbot_core/hud_stats', array( __CLASS__, 'hud_stats' ) );
	}

	/**
	 * Whether WPBot Core's design is on for this page.
	 *
	 * @return bool
	 */
	public static function on() {
		return class_exists( '\\QuantumCloud\\WPBotCore\\Admin\\Design' ) && \QuantumCloud\WPBotCore\Admin\Design::active();
	}

	/**
	 * Print a page head (title, description, actions, section tabs) when the design is on.
	 *
	 * @param string $title Title.
	 * @param string $desc  Short description ('' for none).
	 * @param array  $args  More Design::page_head() arguments (items, current, actions).
	 * @return bool Whether it was printed (callers print their old header otherwise).
	 */
	public static function head( $title, $desc = '', array $args = array() ) {
		if ( ! self::on() ) {
			return false;
		}
		\QuantumCloud\WPBotCore\Admin\Design::page_head(
			array_merge(
				array(
					'title'       => $title,
					'description' => $desc,
				),
				$args
			)
		);
		return true;
	}

	/**
	 * ChatBot pages outside the WPBot menu (the add-ons' "Bot - ..." pages) get the design too.
	 *
	 * @param bool   $on   Whether it is a page of a WPBot menu.
	 * @param string $slug Page argument.
	 * @return bool
	 */
	public static function is_page( $on, $slug ) {
		if ( $on || '' === (string) $slug ) {
			return (bool) $on;
		}
		if ( in_array( (string) $slug, self::chatbot_pages(), true ) ) {
			return true;
		}
		$parent = function_exists( 'get_admin_page_parent' ) ? (string) get_admin_page_parent() : '';
		return in_array( $parent, array( 'wpbot', 'wpbot-panel', 'wpbot_openAi' ), true );
	}

	/**
	 * The host layer of the design: system font, the admin theme's emerald tokens.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! self::on() ) {
			return;
		}
		$file = dirname( __DIR__ ) . '/css/wpbot-design.css';
		wp_enqueue_style( 'qcld-wpbot-design', plugins_url( 'css/wpbot-design.css', dirname( __DIR__ ) . '/qcld-wpwbot.php' ), array(), (string) filemtime( $file ) );
		// GRID look (owner, 21 November 2026): the hosts' own page markup; applies only under html.qa-scifi.
		$scifi = dirname( __DIR__ ) . '/css/wpbot-scifi.css';
		wp_enqueue_style( 'qcld-wpbot-scifi', plugins_url( 'css/wpbot-scifi.css', dirname( __DIR__ ) . '/qcld-wpwbot.php' ), array( 'qcld-wpbot-design' ), (string) filemtime( $scifi ) );
		// Browsers never fill in or offer to save API keys and secrets on WPBot pages (same as WPBot Pro).
		$autofill = dirname( __DIR__ ) . '/js/wpbot-no-autofill.js';
		wp_enqueue_script( 'qcld-wpbot-no-autofill', plugins_url( 'js/wpbot-no-autofill.js', dirname( __DIR__ ) . '/qcld-wpwbot.php' ), array(), (string) filemtime( $autofill ), true );
	}

	/**
	 * Docs and Help in the band.
	 *
	 * @return array[]
	 */
	public static function links() {
		return array(
			array(
				'label'   => __( 'Docs', 'chatbot' ),
				'url'     => 'https://www.wpbot.pro/help/',
				'new_tab' => true,
			),
			array(
				'label' => __( 'Help', 'chatbot' ),
				'url'   => admin_url( 'admin.php?page=wpbot-help' ),
			),
			array(
				'label'   => __( 'Upgrade to Pro', 'chatbot' ),
				'url'     => 'https://www.wpbot.pro/',
				'new_tab' => true,
			),
		);
	}

	/**
	 * Footer links.
	 *
	 * @return array[]
	 */
	public static function footer_links() {
		$links   = self::links();
		$links[] = array(
			'label'   => __( 'Get Support', 'chatbot' ),
			'url'     => 'https://wordpress.org/support/plugin/chatbot/',
			'new_tab' => true,
		);
		return $links;
	}

	/**
	 * The product logo in the band (owner, 20 November 2026: "bring back my WPBot animated gif
	 * logo"): the animated WPBot logo, or the White Label logo when one is set.
	 *
	 * @param string $url Default '' (WPBot Core's mark).
	 * @return string
	 */
	public static function logo( $url ) {
		return function_exists( 'qcld_wpbot_brand_logo_url' ) ? qcld_wpbot_brand_logo_url() : $url;
	}

	/**
	 * "WPBot Pro 15.8.2" in the footer.
	 *
	 * @return string
	 */
	public static function version() {
		return trim( 'WPBot Free ' . ( defined( 'QCLD_wpCHATBOT_VERSION' ) ? QCLD_wpCHATBOT_VERSION : '' ) );
	}

	/**
	 * A ChatBot page (Sessions, Retargeting, the add-ons' pages) selects the ChatBot tab.
	 *
	 * @param string $slug Open page.
	 * @return string
	 */
	public static function active_tab( $slug ) {
		$parent = function_exists( 'get_admin_page_parent' ) ? (string) get_admin_page_parent() : '';
		if ( in_array( (string) $slug, self::chatbot_pages(), true ) || in_array( $parent, array( 'wpbot', 'wpbot-panel' ), true ) ) {
			return 'wpbot-panel';
		}
		return $slug;
	}

	/**
	 * The ChatBot pages (its tabs: Chat Sessions, AI Actions, Simple Text Responses...).
	 *
	 * @return string[]
	 */
	private static function chatbot_pages() {
		$pages = array( 'wpbot', 'wpbot-panel', 'wbcs-botsessions-page', 'simple-text-response', 'email-subscription', 'wpbot_ai_actions' );
		if ( class_exists( 'qcld_wb_Chatbot_free' ) && method_exists( 'qcld_wb_Chatbot_free', 'qcld_wb_chatbot_get_instance' ) ) {
			$pages = array_merge( $pages, array_keys( (array) qcld_wb_Chatbot_free::qcld_wb_chatbot_get_instance()->get_unified_tabs() ) );
		}
		return array_values( array_filter( array_map( 'strval', $pages ), 'strlen' ) );
	}

	/**
	 * The light / dark switch in the band.
	 *
	 * @return void
	 */
	public static function actions() {
		do_action( 'qcld_admin_theme_switch' );
	}

	/**
	 * Light for users who never chose a look.
	 *
	 * @param string $mode Default.
	 * @return string
	 */
	public static function default_mode( $mode ) {
		return 'classic' === $mode ? 'light' : $mode;
	}

	/**
	 * The design's own parts already use the tokens: the color mapping leaves them alone.
	 *
	 * @param array $plugins Admin theme plugins.
	 * @return array
	 */
	public static function theme_skip( $plugins ) {
		if ( isset( $plugins['wpbot-free'] ) && is_array( $plugins['wpbot-free'] ) ) {
			$plugins['wpbot-free']['skip'] = array_merge(
				isset( $plugins['wpbot-free']['skip'] ) ? (array) $plugins['wpbot-free']['skip'] : array(),
				array( '.wpb-band', '.wpb-pagehead', '.wpb-subnav', '.wpb-strip-slot', '.wpb-more__menu', '#wpfooter .wpb-foot', '.qcin-sidebar', '.qcwpbot-sidebar', '.qcld-wpbot-subnav',
					// Start Menu builders: their buttons are saved as HTML, so the theme's colour classes must never land on them.
					'#qc_menu_area', '.qc_menu_list_container', '.qcld-mb-editor' )
			);
		}
		return $plugins;
	}

	/**
	 * The GRID's status tiles (WPBot Core's Hud_Stats) show on the ChatBot dashboard.
	 *
	 * @param bool   $show Default false.
	 * @param string $page The open page argument.
	 * @return bool
	 */
	public static function hud_tiles( $show, $page ) {
		return in_array( (string) $page, array( 'wpbot-panel', 'wpbot' ), true ) ? true : (bool) $show;
	}

	/**
	 * The chat numbers for the GRID's status tiles, from the chatbot module's tables (the same in
	 * WPBot Free and WPBot Pro): conversations and AI answers in the last 24 hours (with the 24 hours
	 * before, and a series), leads from User Data, and how many conversations of the last 7 days the
	 * AI resolved without a handoff. Times are site time, like the tables. Real values only.
	 *
	 * @param array $stats Stats (Hud_Stats::blank() shape).
	 * @return array
	 */
	public static function hud_stats( $stats ) {
		global $wpdb;
		$stats    = is_array( $stats ) ? $stats : array();
		$messages = $wpdb->prefix . 'qcld_wpbot_chat_messages';
		$leads    = $wpdb->prefix . 'wpbot_subscription';
		$now      = (int) strtotime( current_time( 'mysql' ) );
		$at       = function ( $seconds ) use ( $now ) {
			return gmdate( 'Y-m-d H:i:s', $now - (int) $seconds );
		};
		$day      = $at( DAY_IN_SECONDS );
		$two      = $at( 2 * DAY_IN_SECONDS );
		$week     = $at( WEEK_IN_SECONDS );

		if ( class_exists( '\\QuantumCloud\\Chatbot\\Sessions\\Session_Store' ) && method_exists( '\\QuantumCloud\\Chatbot\\Sessions\\Session_Store', 'enabled' ) ) {
			$stats['status']['sessions'] = (bool) \QuantumCloud\Chatbot\Sessions\Session_Store::enabled();
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own tables; numbers cached by Hud_Stats for a minute.
		if ( self::hud_table( $messages ) ) {
			$user = "SELECT COUNT( DISTINCT session_id ) FROM {$messages} WHERE role = 'user' AND created >= %s";
			$ai   = "role = 'bot' AND source = 'ai' AND model <> ''";

			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table name from $wpdb->prefix; values are placeholders.
			$stats['conversations']['now']    = (int) $wpdb->get_var( $wpdb->prepare( $user, $day ) );
			$stats['conversations']['prev']   = (int) $wpdb->get_var( $wpdb->prepare( $user . ' AND created < %s', $two, $day ) );
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$stats['conversations']['series'] = self::hud_buckets( $wpdb->get_results( $wpdb->prepare( "SELECT FLOOR( TIMESTAMPDIFF( HOUR, %s, created ) ) AS b, COUNT( DISTINCT session_id ) AS c FROM {$messages} WHERE role = 'user' AND created >= %s GROUP BY b", $day, $day ) ), 24 );

			$stats['answers']['now']    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$messages} WHERE {$ai} AND created >= %s", $day ) );
			$stats['answers']['prev']   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$messages} WHERE {$ai} AND created >= %s AND created < %s", $two, $day ) );
			$stats['answers']['series'] = self::hud_buckets( $wpdb->get_results( $wpdb->prepare( "SELECT FLOOR( TIMESTAMPDIFF( HOUR, %s, created ) / 3 ) AS b, COUNT(*) AS c FROM {$messages} WHERE {$ai} AND created >= %s GROUP BY b", $day, $day ) ), 8 );

			$sessions = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( DISTINCT session_id ) FROM {$messages} WHERE {$ai} AND created >= %s", $week ) );
			$resolved = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( DISTINCT a.session_id ) FROM {$messages} a WHERE a.role = 'bot' AND a.source = 'ai' AND a.model <> '' AND a.created >= %s AND NOT EXISTS ( SELECT 1 FROM {$messages} b WHERE b.session_id = a.session_id AND b.source IN ( 'live', 'handoff' ) )", $week ) );

			$stats['resolved']['sessions']   = $sessions;
			$stats['resolved']['rate']       = $sessions > 0 ? (int) round( $resolved * 100 / $sessions ) : null;
			$stats['resolved']['handoffs']   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( DISTINCT session_id ) FROM {$messages} WHERE source IN ( 'live', 'handoff' ) AND created >= %s", $week ) );
			$stats['resolved']['unanswered'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$messages} WHERE role = 'bot' AND source IN ( 'fallback', 'none' ) AND created >= %s", $week ) );
		}

		if ( self::hud_table( $leads ) ) {
			$start                     = gmdate( 'Y-m-d 00:00:00', $now - 13 * DAY_IN_SECONDS );
			$stats['leads']['total']   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$leads}" );
			$stats['leads']['day']     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$leads} WHERE date >= %s", $day ) );
			$stats['leads']['series']  = self::hud_buckets( $wpdb->get_results( $wpdb->prepare( "SELECT DATEDIFF( date, %s ) AS b, COUNT(*) AS c FROM {$leads} WHERE date >= %s GROUP BY b", $start, $start ) ), 14 );
		}
		// phpcs:enable

		return $stats;
	}

	/**
	 * Rows of (b, c) into a list of $count numbers, oldest first.
	 *
	 * @param array $rows  Query rows with b (bucket) and c (count).
	 * @param int   $count Buckets.
	 * @return int[]
	 */
	private static function hud_buckets( $rows, $count ) {
		$out = array_fill( 0, $count, 0 );
		foreach ( (array) $rows as $row ) {
			$b = isset( $row->b ) ? (int) $row->b : -1;
			if ( $b >= 0 && $b < $count ) {
				$out[ $b ] = (int) $row->c;
			}
		}
		return $out;
	}

	/**
	 * Whether a table exists (once per request).
	 *
	 * @param string $table Table name.
	 * @return bool
	 */
	private static function hud_table( $table ) {
		static $seen = array();
		if ( ! isset( $seen[ $table ] ) ) {
			global $wpdb;
			$seen[ $table ] = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Schema check.
		}
		return $seen[ $table ];
	}
}

QCLD_WPBot_Free_Design::boot();
