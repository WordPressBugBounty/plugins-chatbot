<?php
/**
 * WPBot Free › ChatBot settings: a "WPBot Pro" tab with the Benefits and Features of the Pro
 * channels and add-ons that have no screen in WPBot Free (live chat, WooCommerce, WhatsApp,
 * Messenger, Telegram, tickets, white label, embed, activity hours, notifications, retargeting,
 * voice, CRM, extended search, multi-language), and the Pro list on the Performance tab
 * (owner, 12 November 2026).
 *
 * @package Chatbot
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Free_Settings_Pro_Tab' ) ) {

	/**
	 * Settings › WPBot Pro tab.
	 */
	final class QCLD_WPBot_Free_Settings_Pro_Tab {

		/**
		 * Features on the tab, in order.
		 *
		 * @return string[]
		 */
		public static function features() {
			return array( 'live_chat', 'woocommerce', 'whatsapp', 'messenger', 'telegram', 'crm', 'tickets', 'embed', 'notification_builder', 'retargeting', 'activity_hours', 'voice', 'search', 'languages', 'white_label' );
		}

		/**
		 * Hook in.
		 *
		 * @return void
		 */
		public static function init() {
			add_action( 'qcld_wpbot_settings_tabs_before_custom_css', array( __CLASS__, 'nav' ), 20 );
			add_action( 'qcld_wpbot_settings_sections_before_custom_css', array( __CLASS__, 'section' ), 20 );
			add_action( 'qcld_wpbot_ao_render_settings', array( __CLASS__, 'performance' ), 50 );
			add_action( 'qcld_wpbot_chat/spend_report_actions', array( __CLASS__, 'spend_export' ), 50 );
		}

		/**
		 * The tab in the settings navigation.
		 *
		 * @return void
		 */
		public static function nav() {
			printf(
				'<li tab-data="pro" data-section="#section-flip-pro"><a href="%1$s"> <span class="wpwbot-admin-tab-icon"> <span class="dashicons dashicons-star-filled" aria-hidden="true"></span> </span> <span class="wpwbot-admin-tab-name">%2$s</span> <span class="qcld-wpbot-pro-label">%3$s</span> </a></li>',
				esc_url( admin_url( 'admin.php?page=wpbot-panel&tab=pro' ) ),
				esc_html__( 'WPBot Pro', 'chatbot' ),
				esc_html__( 'PRO', 'chatbot' )
			);
		}

		/**
		 * The tab's section: one compact Benefits and Features card per Pro channel or add-on.
		 *
		 * @return void
		 */
		public static function section() {
			echo '<section id="section-flip-pro" class="qcld-wpbot-settings-pro">';
			echo '<div class="top-section"><h3 class="qcld-wpbot-main-tabs-title">' . esc_html__( 'What WPBot Pro adds to your chatbot', 'chatbot' ) . '</h3>';
			echo '<p class="qc-opt-dcs">' . esc_html__( 'Channels, add-ons and settings that are part of WPBot Pro. Your WPBot Free settings and data stay when you upgrade.', 'chatbot' ) . '</p></div>';
			foreach ( self::features() as $id ) {
				qcld_wpbot_free_pro_lists( $id, array( 'compact' => true ) );
			}
			echo '</section>';
			// Open the tab when the address asks for it (&tab=pro), as the Performance tab does.
			echo '<script>jQuery(window).on("load",function(){if(/[?&]tab=pro(&|$)/.test(location.search)){setTimeout(function(){jQuery(\'.wp-chatbot-tabs nav li[tab-data="pro"] > a\').first().trigger("click");},0);}});</script>';
		}

		/**
		 * AI Usage report: CSV export is part of WPBot Pro (shown, not usable).
		 *
		 * @return void
		 */
		public static function spend_export() {
			echo ' <button type="button" class="qcwp-btn" disabled title="' . esc_attr__( 'CSV export, spike alerts and the weekly summary email are part of WPBot Pro', 'chatbot' ) . '">' . esc_html__( 'Export CSV', 'chatbot' ) . ' <span class="qcld-wpbot-pro-label">' . esc_html__( 'PRO', 'chatbot' ) . '</span></button>';
		}

		/**
		 * Performance tab: what Asset Optimizer Pro adds.
		 *
		 * @return void
		 */
		public static function performance() {
			echo '<div class="qcld-wpbot-ao-field">';
			echo '<p class="qc-opt-dcs"><b>' . esc_html__( 'Load the chat engine on page load, when the browser is idle or on the first interaction, and save colours and bot settings as files.', 'chatbot' ) . '</b> <span class="qcld-wpbot-pro-label">' . esc_html__( 'PRO', 'chatbot' ) . '</span></p>';
			qcld_wpbot_free_pro_lists( 'more', array( 'compact' => true ) );
			echo '</div>';
		}
	}

	QCLD_WPBot_Free_Settings_Pro_Tab::init();
}
