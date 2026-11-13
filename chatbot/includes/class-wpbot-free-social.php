<?php
/**
 * WPBot Social in WPBot Free: loads the bundled Social core module
 * (includes/social/) on top of WPBot Core and registers WPBot Free as its
 * host, in "free" mode (Facebook Page, Instagram, Threads, Google Business
 * Profile, Telegram channel, WhatsApp "Post yourself"; scheduling, Posts,
 * Calendar, tracked links, Write with AI for one network).
 *
 * The folder is a synced copy of https://github.com/qcld/wpbot-social
 * (npm run sync:social -- --core <sha>); never edit it here. The Social
 * Master module never ships in WPBot Free (build-scripts/check-social-guard.js).
 *
 * Loaded from qcld-wpwbot.php after the "stop when WPBot Pro is active"
 * block, so it never runs next to WPBot Pro.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '7.4', '<' ) || ! file_exists( __DIR__ . '/wpbot-core/wpbot-core.php' ) || ! file_exists( __DIR__ . '/social/social.php' ) ) {
	return;
}

// The loaders inside pick the newest bundled copy of each module, so another
// WPBot plugin with the same modules never loads them twice.
require_once __DIR__ . '/wpbot-core/wpbot-core.php';
require_once __DIR__ . '/social/social.php';

if ( ! function_exists( 'qcld_wpbot_free_core_register' ) ) {
	/**
	 * Register WPBot Free as the WPBot Core host (shared with AI Writer and
	 * SEO & AI Search; whichever loader runs first defines it).
	 *
	 * @return void
	 */
	function qcld_wpbot_free_core_register() {
		require_once __DIR__ . '/class-wpbot-free-core-adapter.php';
		\QuantumCloud\WPBotCore\Core::instance()->register_host( QCLD_WPBot_Free_Core_Adapter::instance() );
	}
	add_action( 'qcld_wpbot_core/loaded', 'qcld_wpbot_free_core_register' );
}

if ( ! function_exists( 'qcld_wpbot_free_social_register' ) ) {
	/**
	 * Register WPBot Free as the Social host.
	 *
	 * @return void
	 */
	function qcld_wpbot_free_social_register() {
		require_once __DIR__ . '/class-wpbot-free-core-adapter.php';
		require_once __DIR__ . '/class-wpbot-free-social-adapter.php';
		\QuantumCloud\Social\Module::instance()->register_host( new QCLD_WPBot_Free_Social_Adapter() );
	}
	add_action( 'qcld_wpbot_social/loaded', 'qcld_wpbot_free_social_register' );
}

if ( ! function_exists( 'qcld_wpbot_free_social_menu_order' ) ) {
	/**
	 * Keep Social above WPBot's "Upgrade to Pro" link in the WPBot menu (the link
	 * is added before Social; functions.php renumbers the menu at 9999).
	 *
	 * @return void
	 */
	function qcld_wpbot_free_social_menu_order() {
		global $submenu;
		if ( empty( $submenu['wpbot_openAi'] ) || ! is_array( $submenu['wpbot_openAi'] ) ) {
			return;
		}
		$items   = array_values( $submenu['wpbot_openAi'] );
		$social  = null;
		$upgrade = null;
		foreach ( $items as $index => $item ) {
			if ( isset( $item[2] ) && 'wpbot-social' === $item[2] ) {
				$social = $index;
			} elseif ( null === $upgrade && isset( $item[0] ) && false !== strpos( (string) $item[0], 'qc-up-pro-link' ) ) {
				$upgrade = $index;
			}
		}
		if ( null === $social || null === $upgrade || $social < $upgrade ) {
			return;
		}
		$entry = $items[ $social ];
		array_splice( $items, $social, 1 );
		array_splice( $items, $upgrade, 0, array( $entry ) );
		$submenu['wpbot_openAi'] = $items; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reorder only.
	}
	add_action( 'admin_menu', 'qcld_wpbot_free_social_menu_order', 10000 );
}

if ( ! function_exists( 'qcld_wpbot_free_social_deactivate' ) ) {
	/**
	 * On WPBot Free deactivation, clear Social's scheduled events (publish tick, daily
	 * checks, auto-share singles). Social's schedulers add them back when it starts again
	 * (WPBot Free reactivated, or WPBot Pro Master). WPBot Free returns early while WPBot
	 * Pro is active, so this hook is never registered next to WPBot Pro and Pro's events
	 * are left alone.
	 *
	 * @return void
	 */
	function qcld_wpbot_free_social_deactivate() {
		$hooks = array();
		foreach ( (array) _get_cron_array() as $events ) {
			foreach ( array_keys( (array) $events ) as $hook ) {
				if ( 0 === strpos( (string) $hook, 'qcld_wpbot_social' ) ) {
					$hooks[ $hook ] = true;
				}
			}
		}
		foreach ( array_keys( $hooks ) as $hook ) {
			wp_unschedule_hook( $hook );
		}
	}
	register_deactivation_hook( dirname( __DIR__ ) . '/qcld-wpwbot.php', 'qcld_wpbot_free_social_deactivate' );
}
