<?php
/**
 * WPBot Free › Social: what Social Pro adds, shown where it would appear (owner, 12 November 2026).
 * Inactive Pro network cards, Pro menu items (Trends, Inbox, Analytics), Pro tabs and buttons, each
 * marked PRO and leading to the Social Pro Benefits and Features lists. Nothing here runs a Pro
 * feature. Uses the Social module's own hooks; WPBot Free only loads this file while WPBot Pro
 * is not active.
 *
 * @package Chatbot
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Free_Social_Pro_Preview' ) ) {

	/**
	 * Social Pro previews.
	 */
	final class QCLD_WPBot_Free_Social_Pro_Preview {

		/**
		 * The preview view id (one view, with a section).
		 */
		const VIEW = 'pro';

		/**
		 * Hook in.
		 *
		 * @return void
		 */
		public static function init() {
			add_filter( 'qcld_wpbot_social/menu_items', array( __CLASS__, 'menu_items' ), 50 );
			add_filter( 'qcld_wpbot_social/admin_views', array( __CLASS__, 'views' ), 50 );
			add_action( 'qcld_wpbot_social/add_network_cards', array( __CLASS__, 'network_cards' ), 50 );
			add_action( 'qcld_wpbot_social/accounts_actions', array( __CLASS__, 'accounts_actions' ), 50 );
			add_action( 'qcld_wpbot_social/create_tabs', array( __CLASS__, 'create_tabs' ), 50 );
			add_action( 'qcld_wpbot_social/compose_when', array( __CLASS__, 'compose_when' ), 50 );
			add_action( 'qcld_wpbot_social/compose_buttons', array( __CLASS__, 'compose_buttons' ), 50 );
			add_filter( 'qcld_wpbot_social/settings_tabs', array( __CLASS__, 'settings_tabs' ), 50 );
			foreach ( array_keys( self::settings_previews() ) as $tab ) {
				add_action( 'qcld_wpbot_social/settings_tab_' . $tab, array( __CLASS__, 'settings_tab' ) );
			}
		}

		/**
		 * URL of the preview view.
		 *
		 * @param string $section Section (trends, inbox, analytics, networks, queue, team).
		 * @return string
		 */
		public static function url( $section ) {
			return add_query_arg(
				array(
					'page'    => 'wpbot-social',
					'view'    => self::VIEW,
					'section' => sanitize_key( $section ),
				),
				admin_url( 'admin.php' )
			);
		}

		/**
		 * The Pro sections: title and one line.
		 *
		 * @return array<string,array{0:string,1:string}>
		 */
		public static function sections() {
			return array(
				'networks'  => array( __( 'More networks', 'chatbot' ), __( 'Bluesky, Mastodon, Discord, LinkedIn, Pinterest, X, TikTok and YouTube Shorts, plus Reddit, Medium, Nextdoor, Quora and Snapchat by hand.', 'chatbot' ) ),
				'trends'    => array( __( 'Trends', 'chatbot' ), __( 'See what people are talking about, and turn a trend into a post.', 'chatbot' ) ),
				'inbox'     => array( __( 'Inbox', 'chatbot' ), __( 'Comments and messages from every network in one place.', 'chatbot' ) ),
				'analytics' => array( __( 'Analytics', 'chatbot' ), __( 'Reach, clicks and engagement for every post and network.', 'chatbot' ) ),
				'queue'     => array( __( 'Queue and evergreen', 'chatbot' ), __( 'A posting queue with time slots, evergreen reposting and bulk import.', 'chatbot' ) ),
				'team'      => array( __( 'Team and approval', 'chatbot' ), __( 'Brands, roles and approval before anything goes out.', 'chatbot' ) ),
			);
		}

		/**
		 * A small PRO badge.
		 *
		 * @return string
		 */
		private static function badge() {
			return '<span class="qcld-wpbot-pro-label">' . esc_html__( 'PRO', 'chatbot' ) . '</span>';
		}

		/**
		 * Trends, Inbox and Analytics in the Social menu, marked Pro.
		 *
		 * @param array[] $items Items.
		 * @return array[]
		 */
		public static function menu_items( $items ) {
			$sections = self::sections();
			foreach ( array(
				'trends'    => 'dashicons-chart-line',
				'inbox'     => 'dashicons-email-alt',
				'analytics' => 'dashicons-chart-bar',
			) as $id => $icon ) {
				$items[] = array(
					'id'    => 'pro-' . $id,
					'label' => $sections[ $id ][0],
					'url'   => self::url( $id ),
					'icon'  => $icon,
					'pro'   => true,
				);
			}
			return $items;
		}

		/**
		 * The preview view.
		 *
		 * @param array $views Views.
		 * @return array
		 */
		public static function views( $views ) {
			$views[ self::VIEW ] = array(
				'render' => array( __CLASS__, 'render' ),
				'cap'    => 'wpbot_social_view',
				'menu'   => self::current_menu(),
			);
			return $views;
		}

		/**
		 * The menu item to highlight for the preview.
		 *
		 * @return string
		 */
		private static function current_menu() {
			$section = self::current_section();
			if ( in_array( $section, array( 'trends', 'inbox', 'analytics' ), true ) ) {
				return 'pro-' . $section;
			}
			return 'networks' === $section ? 'accounts' : ( 'queue' === $section || 'team' === $section ? 'settings' : 'accounts' );
		}

		/**
		 * The requested section.
		 *
		 * @return string
		 */
		private static function current_section() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view switch.
			$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'networks';
			return array_key_exists( $section, self::sections() ) ? $section : 'networks';
		}

		/**
		 * Render the preview: what this part of Social Pro does, then the Benefits and Features lists.
		 *
		 * @return void
		 */
		public static function render() {
			$sections = self::sections();
			$section  = $sections[ self::current_section() ];
			echo '<section class="qcsoc-card qcld-wpbot-social-pro">';
			echo '<h2>' . esc_html( $section[0] ) . ' ' . self::badge() . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
			echo '<p class="qcsoc-muted">' . esc_html( $section[1] ) . ' ' . esc_html__( 'Available in WPBot Pro.', 'chatbot' ) . '</p>';
			qcld_wpbot_free_pro_lists( 'social_pro', array( 'heading' => false ) );
			echo '</section>';
		}

		/**
		 * Inactive Pro network cards in "Add a network".
		 *
		 * @return void
		 */
		public static function network_cards() {
			$cards = array(
				array( 'bluesky', __( 'Bluesky', 'chatbot' ) ),
				array( 'mastodon', __( 'Mastodon', 'chatbot' ) ),
				array( 'discord', __( 'Discord channel', 'chatbot' ) ),
				array( 'linkedin', __( 'LinkedIn', 'chatbot' ) ),
				array( 'pinterest', __( 'Pinterest', 'chatbot' ) ),
				array( 'x', __( 'X', 'chatbot' ) ),
				array( 'tiktok', __( 'TikTok', 'chatbot' ) ),
				array( 'youtube', __( 'YouTube Shorts', 'chatbot' ) ),
				array( 'manual', __( 'Reddit, Medium, Nextdoor, Quora and Snapchat', 'chatbot' ) ),
			);
			foreach ( $cards as $card ) {
				$initials = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $card[1] ), 0, 2 ) );
				echo '<a class="qcsoc-network is-pro" href="' . esc_url( self::url( 'networks' ) ) . '" data-network="' . esc_attr( $card[0] ) . '">';
				echo '<span class="qcsoc-avatar">' . esc_html( $initials ) . '</span>';
				echo '<strong>' . esc_html( $card[1] ) . '</strong>';
				echo '<span class="qcsoc-muted">' . esc_html( 'manual' === $card[0] ? __( 'Post by hand with ready text', 'chatbot' ) : __( 'Part of WPBot Pro', 'chatbot' ) ) . '</span>';
				echo self::badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
				echo '</a>';
			}
		}

		/**
		 * "Add a brand" (Pro) next to "Connect an account".
		 *
		 * @return void
		 */
		public static function accounts_actions() {
			echo '<a class="button qcld-wpbot-social-pro-btn" href="' . esc_url( self::url( 'team' ) ) . '">' . esc_html__( 'Add a brand', 'chatbot' ) . ' ' . self::badge() . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
		}

		/**
		 * "Bulk import" (Pro) in the Create post tabs.
		 *
		 * @return void
		 */
		public static function create_tabs() {
			echo '<a href="' . esc_url( self::url( 'queue' ) ) . '">' . esc_html__( 'Bulk import', 'chatbot' ) . ' ' . self::badge() . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
		}

		/**
		 * "Add to queue" and "Evergreen" (Pro) among the When choices: shown, not selectable.
		 *
		 * @return void
		 */
		public static function compose_when() {
			foreach ( array( __( 'Add to queue', 'chatbot' ), __( 'Evergreen', 'chatbot' ) ) as $label ) {
				echo '<a class="qcsoc-chip is-pro" href="' . esc_url( self::url( 'queue' ) ) . '">' . esc_html( $label ) . ' ' . self::badge() . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
			}
		}

		/**
		 * "Send for approval" (Pro) next to the save buttons.
		 *
		 * @return void
		 */
		public static function compose_buttons() {
			echo '<a class="button qcld-wpbot-social-pro-btn" href="' . esc_url( self::url( 'team' ) ) . '">' . esc_html__( 'Send for approval', 'chatbot' ) . ' ' . self::badge() . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
		}

		/**
		 * Pro settings tabs: id => section.
		 *
		 * @return array<string,string>
		 */
		private static function settings_previews() {
			return array(
				'pro-queue' => 'queue',
				'pro-inbox' => 'inbox',
				'pro-team'  => 'team',
			);
		}

		/**
		 * Add the Pro settings tabs, marked Pro.
		 *
		 * @param array $tabs Tabs.
		 * @return array
		 */
		public static function settings_tabs( $tabs ) {
			$sections = self::sections();
			foreach ( self::settings_previews() as $tab => $section ) {
				/* translators: %s: settings tab name. */
				$tabs[ $tab ] = sprintf( __( '%s (Pro)', 'chatbot' ), $sections[ $section ][0] );
			}
			return $tabs;
		}

		/**
		 * A Pro settings tab: the Social Pro lists.
		 *
		 * @return void
		 */
		public static function settings_tab() {
			$tab      = str_replace( 'qcld_wpbot_social/settings_tab_', '', (string) current_action() );
			$previews = self::settings_previews();
			$sections = self::sections();
			$section  = isset( $previews[ $tab ] ) ? $sections[ $previews[ $tab ] ] : $sections['queue'];
			echo '<section class="qcsoc-card qcld-wpbot-social-pro">';
			echo '<h2>' . esc_html( $section[0] ) . ' ' . self::badge() . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
			echo '<p class="qcsoc-muted">' . esc_html( $section[1] ) . ' ' . esc_html__( 'Available in WPBot Pro.', 'chatbot' ) . '</p>';
			qcld_wpbot_free_pro_lists( 'social_pro', array( 'heading' => false ) );
			echo '</section>';
		}

		/**
		 * Styles for the inactive cards and Pro links (Social screens only).
		 *
		 * @return void
		 */
		public static function styles() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Screen check only.
			if ( ! isset( $_GET['page'] ) || 'wpbot-social' !== $_GET['page'] ) {
				return;
			}
			echo '<style id="qcld-wpbot-social-pro-css">'
				. '.qcsoc-network.is-pro{opacity:.78;border-style:dashed}'
				. '.qcsoc-network.is-pro:hover,.qcsoc-network.is-pro:focus{opacity:1}'
				. '.qcsoc-network.is-pro .qcld-wpbot-pro-label{margin:6px 0 0;align-self:flex-start}'
				. '.qcsoc-chip.is-pro{text-decoration:none;opacity:.8}'
				. '.qcld-wpbot-social-pro-btn{display:inline-flex;align-items:center;gap:4px}'
				. '</style>';
		}
	}

	QCLD_WPBot_Free_Social_Pro_Preview::init();
	add_action( 'admin_head', array( 'QCLD_WPBot_Free_Social_Pro_Preview', 'styles' ) );
}
