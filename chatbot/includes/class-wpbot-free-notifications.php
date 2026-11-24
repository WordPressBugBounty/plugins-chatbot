<?php
/**
 * Fine-grained email notification settings in WPBot Free (owner, 24 November 2026: "need to add
 * notification settings to free").
 *
 * The settings, the gate and the catalog are the same files as WPBot Pro's
 * (class-qcld-wpbot-notification-*.php), so the saved choices (option qcld_wpbot_notification_prefs)
 * carry over when a site upgrades. This file only trims the list to what WPBot Free sends: a
 * notification shows when its sending code is part of this plugin (a listed file exists, a listed
 * function is defined, or for a class method its module's folder is here). Pro-only features (forms,
 * support tickets, the store) and Pro-only emails drop out, and a group with nothing left is not shown.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPBot_Free_Notifications' ) ) {

	/**
	 * WPBot Free's notification list.
	 */
	final class WPBot_Free_Notifications {

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public static function init() {
			add_filter( 'qcld_wpbot_notification_catalog', array( __CLASS__, 'trim' ), 20 );
		}

		/**
		 * The module folder a group's class methods live in (relative to the plugin).
		 *
		 * @return array<string,string> Group id => folder ('' = always here).
		 */
		public static function group_dirs() {
			return array(
				'chatbot'   => 'includes/chatbot',
				'livechat'  => 'includes/chatbot',
				'spend'     => 'includes/chatbot',
				'sessions'  => 'includes/chat-sessions',
				'marketing' => 'includes/ai-marketing',
				'social'    => 'includes/social',
				'seo'       => 'includes/seo-ai-search',
				'writer'    => 'includes/ai-writer',
				'insights'  => 'includes/insights',
				'other'     => '',
			);
		}

		/**
		 * Keep the notifications whose code is in this plugin.
		 *
		 * @param mixed $groups Catalog groups.
		 * @return mixed
		 */
		public static function trim( $groups ) {
			if ( ! is_array( $groups ) ) {
				return $groups;
			}
			$root = defined( 'QCLD_wpCHATBOT_PLUGIN_DIR_PATH' ) ? QCLD_wpCHATBOT_PLUGIN_DIR_PATH : plugin_dir_path( __DIR__ );
			$dirs = self::group_dirs();
			foreach ( $groups as $gid => $group ) {
				if ( ! is_array( $group ) || empty( $group['items'] ) || ! is_array( $group['items'] ) ) {
					continue;
				}
				$module = isset( $dirs[ $gid ] ) ? $dirs[ $gid ] : null;
				foreach ( $group['items'] as $nid => $item ) {
					if ( ! self::present( (array) $item, $root, $module ) ) {
						unset( $groups[ $gid ]['items'][ $nid ] );
					}
				}
				if ( empty( $groups[ $gid ]['items'] ) ) {
					unset( $groups[ $gid ] );
				}
			}
			return $groups;
		}

		/**
		 * Whether a notification's sending code is in this plugin (pure apart from the file system).
		 *
		 * @param array       $item   Catalog item (files, fn).
		 * @param string      $root   Plugin folder.
		 * @param string|null $module The group's module folder ('' = always here, null = not known).
		 * @return bool
		 */
		public static function present( array $item, $root, $module ) {
			$files = isset( $item['files'] ) ? (array) $item['files'] : array();
			$fns   = isset( $item['fn'] ) ? (array) $item['fn'] : array();
			if ( ! $files && ! $fns ) {
				return true; // The catch-all for any other WPBot email.
			}
			foreach ( $files as $file ) {
				if ( is_file( trailingslashit( $root ) . ltrim( (string) $file, '/' ) ) ) {
					return true;
				}
			}
			foreach ( $fns as $fn ) {
				$fn = (string) $fn;
				if ( false === strpos( $fn, '::' ) ) {
					if ( function_exists( $fn ) ) {
						return true;
					}
				} elseif ( null !== $module && ( '' === $module || is_dir( trailingslashit( $root ) . $module ) ) ) {
					return true;
				}
			}
			return false;
		}
	}

	WPBot_Free_Notifications::init();
}
