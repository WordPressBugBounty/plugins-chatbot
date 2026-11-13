<?php
/**
 * The order of the WPBot admin menu (owner, 11 November 2026).
 *
 * Modules add their pages to the WPBot menu at different times, so the order used to depend on when each
 * one registered. This puts the menu in the owner's order once every page is added. Pages not in the
 * list keep their relative order after the listed ones, and pages a site doesn't have are skipped.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Menu_Order' ) ) {

	/**
	 * Sorts the WPBot submenu.
	 */
	final class QCLD_WPBot_Menu_Order {

		/**
		 * Menu slugs in the owner's order: AI Settings, Business Context, ChatBot (then its Train and Features pages), AI Writer,
		 * SEO & AI Search, Social, Site Insight, AI Marketing, (Automator), Permissions, Master License,
		 * Module Manager, Help, Get Support, (Upgrade to Pro).
		 */
		const ORDER = array(
			'wpbot_openAi',
			'wpbot-business-context',
			'wpbot-panel',
			'qcld-wpbot-train',
			'qcld-wpbot-features',
			'wpbot-ai-writer',
			'wpbot-seo-ai-search',
			'wpbot-social',
			'qcin-insights',
			'wpbot-marketing',
			'wpbot-automator',
			'wpbot-permissions',
			'wpbot_pro_license_settings',
			'wpbot-modules',
			'wpbot_license_page',
			'https://qc.turbopowers.com',
			'https://www.wpbot.pro/',
		);

		/**
		 * Hook in.
		 *
		 * @return void
		 */
		public static function init() {
			add_action( 'admin_menu', array( __CLASS__, 'apply' ), PHP_INT_MAX - 10 );
		}

		/**
		 * Sort the WPBot submenu (WPBot Pro: under wpbot-panel; WPBot Free: under wpbot_openAi).
		 *
		 * @return void
		 */
		public static function apply() {
			global $submenu;
			/**
			 * The WPBot admin menu order (menu slugs).
			 *
			 * @param string[] $order Slugs.
			 */
			$order = (array) apply_filters( 'qcld_wpbot_menu_order', self::ORDER );
			foreach ( array( 'wpbot-panel', 'wpbot_openAi' ) as $parent ) {
				if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
					continue;
				}
				$submenu[ $parent ] = self::sort( $submenu[ $parent ], $order ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reordering the menu WordPress built.
			}
		}

		/**
		 * Sort submenu items by slug: listed slugs in list order, then the rest in their old order.
		 *
		 * @param array    $items Submenu items ( [ title, capability, slug, ... ] ).
		 * @param string[] $order Slugs.
		 * @return array
		 */
		public static function sort( array $items, array $order ) {
			$rank = array_flip( array_values( $order ) );
			$keyed = array();
			$i     = 0;
			foreach ( $items as $item ) {
				$slug    = isset( $item[2] ) ? (string) $item[2] : '';
				$keyed[] = array(
					'item' => $item,
					'rank' => isset( $rank[ $slug ] ) ? $rank[ $slug ] : count( $rank ),
					'pos'  => $i++,
				);
			}
			usort(
				$keyed,
				function ( $a, $b ) {
					return $a['rank'] === $b['rank'] ? $a['pos'] - $b['pos'] : $a['rank'] - $b['rank'];
				}
			);
			return array_values( wp_list_pluck( $keyed, 'item' ) );
		}
	}

	QCLD_WPBot_Menu_Order::init();
}
