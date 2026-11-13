<?php
/**
 * Keeps other plugins' admin notices off WPBot's own screens.
 *
 * Other plugins print review requests, promotions and nags on every admin
 * screen through the admin notice hooks. On a screen this plugin renders,
 * this guard removes those callbacks just before WordPress prints notices.
 * Notices from this plugin (its bundled modules included) and from
 * WordPress itself stay. Every other admin screen is left alone.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Admin_Notice_Guard' ) ) {

	/**
	 * Admin notice guard for WPBot screens.
	 */
	final class QCLD_WPBot_Admin_Notice_Guard {

		/**
		 * Notice hooks WordPress prints in admin-header.php.
		 */
		const HOOKS = array( 'admin_notices', 'all_admin_notices', 'user_admin_notices', 'network_admin_notices' );

		/**
		 * This plugin's folder (with a trailing slash).
		 *
		 * @var string
		 */
		private static $root = '';

		/**
		 * Hook in (admin only).
		 *
		 * @param string $plugin_dir This plugin's folder.
		 * @return void
		 */
		public static function init( $plugin_dir ) {
			self::$root = trailingslashit( wp_normalize_path( (string) $plugin_dir ) );
			// in_admin_header runs just before the notice hooks; run last so late additions are seen too.
			add_action( 'in_admin_header', array( __CLASS__, 'guard' ), PHP_INT_MAX );
		}

		/**
		 * Remove other plugins' notice callbacks on this plugin's screens.
		 *
		 * @return void
		 */
		public static function guard() {
			if ( ! self::is_own_screen() ) {
				return;
			}
			global $wp_filter;
			foreach ( self::HOOKS as $hook ) {
				if ( empty( $wp_filter[ $hook ] ) || ! is_object( $wp_filter[ $hook ] ) ) {
					continue;
				}
				foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $entry ) {
						if ( ! self::keep( $entry['function'] ) ) {
							remove_action( $hook, $entry['function'], $priority );
						}
					}
				}
			}
		}

		/**
		 * Whether the current admin page is rendered by this plugin: the callback
		 * registered for the page's hook lives in this plugin's folder.
		 *
		 * @return bool
		 */
		public static function is_own_screen() {
			global $hook_suffix, $wp_filter;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check.
			if ( empty( $_GET['page'] ) || empty( $hook_suffix ) || empty( $wp_filter[ $hook_suffix ] ) ) {
				return false;
			}
			$own = false;
			foreach ( $wp_filter[ $hook_suffix ]->callbacks as $callbacks ) {
				foreach ( $callbacks as $entry ) {
					$file = self::file_of( $entry['function'] );
					if ( '' !== $file ) {
						$own = 0 === strpos( $file, self::$root );
						break 2;
					}
				}
			}
			/**
			 * Whether other plugins' admin notices are hidden on this screen.
			 *
			 * @param bool   $own         True when this plugin renders the page.
			 * @param string $hook_suffix The admin page hook.
			 */
			return (bool) apply_filters( 'qcld_wpbot_hide_foreign_notices', $own, (string) $hook_suffix );
		}

		/**
		 * Whether a notice callback stays: this plugin's own or WordPress's.
		 *
		 * @param mixed $callback Callback.
		 * @return bool
		 */
		public static function keep( $callback ) {
			$file = self::file_of( $callback );
			if ( '' === $file ) {
				return true; // Unknown source (internal function): leave it.
			}
			if ( 0 === strpos( $file, self::$root ) ) {
				return true;
			}
			$core = array( wp_normalize_path( ABSPATH . 'wp-admin/' ), wp_normalize_path( ABSPATH . WPINC . '/' ) );
			foreach ( $core as $dir ) {
				if ( 0 === strpos( $file, $dir ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * File that defines a callback ('' when it can't be told).
		 *
		 * @param mixed $callback Callback.
		 * @return string Normalized path.
		 */
		public static function file_of( $callback ) {
			try {
				if ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
					$callback = explode( '::', $callback, 2 );
				}
				if ( is_array( $callback ) && 2 === count( $callback ) ) {
					$ref = new ReflectionMethod( $callback[0], (string) $callback[1] );
				} elseif ( $callback instanceof Closure || ( is_string( $callback ) && function_exists( $callback ) ) ) {
					$ref = new ReflectionFunction( $callback );
				} elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
					$ref = new ReflectionMethod( $callback, '__invoke' );
				} else {
					return '';
				}
				$file = $ref->getFileName();
				return is_string( $file ) ? wp_normalize_path( $file ) : '';
			} catch ( ReflectionException $e ) {
				return '';
			}
		}
	}
}
