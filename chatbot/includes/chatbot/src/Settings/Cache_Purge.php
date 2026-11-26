<?php
/**
 * Clear the site's caches after WPBot settings are saved (owner, 25 November 2026: "Can we clear the
 * WordPress cache automatically every time the plugin settings is Saved?").
 *
 * A save is an admin POST by a user who may manage options, from a WPBot screen (its `page`
 * argument, or the page it was sent from, is a WPBot page), that changed at least one option. AJAX
 * requests count only for saving actions (the action's name says save, settings, option or update),
 * so polling and the heartbeat never clear anything. Caches are cleared once, at the end of the
 * request:
 * - the persistent object cache (Redis, Memcached), when the site has one;
 * - page caches: WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, Autoptimize, SiteGround
 *   Speed Optimizer and WP Fastest Cache (through the Asset Optimizer module's integrations, when it
 *   is bundled), plus Hummingbird, Breeze, Cache Enabler, Nginx Helper, WP-Optimize and WP Engine.
 *
 * Off with the filter qcld_wpbot_chat/purge_cache_on_save. Fires qcld_wpbot_chat/cache_purged with
 * the names of what was cleared.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Cache purge after a settings save.
 */
final class Cache_Purge {

	/**
	 * Option names that change on their own during admin requests (never a settings save).
	 */
	const IGNORED = array( 'cron', 'rewrite_rules', 'recently_activated', 'recently_edited', 'auto_updater.lock', 'core_updater.lock' );

	/**
	 * Whether an option changed in this request.
	 *
	 * @var bool
	 */
	private static $changed = false;

	/**
	 * Whether the purge is scheduled for the end of this request.
	 *
	 * @var bool
	 */
	private static $hooked = false;

	/**
	 * Hook in (admin requests only).
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'updated_option', array( __CLASS__, 'changed' ) );
		add_action( 'added_option', array( __CLASS__, 'changed' ) );
		add_action( 'deleted_option', array( __CLASS__, 'changed' ) );
	}

	/**
	 * An option changed: schedule the purge when this request is a WPBot settings save.
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	public static function changed( $option ) {
		if ( self::$hooked || ! self::counts( (string) $option ) || ! self::is_settings_save() ) {
			return;
		}
		self::$changed = true;
		self::$hooked  = true;
		add_action( 'shutdown', array( __CLASS__, 'purge' ), 0 );
	}

	/**
	 * Whether a change to this option counts as a settings change.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	public static function counts( $option ) {
		if ( '' === $option || '_' === $option[0] || in_array( $option, self::IGNORED, true ) ) {
			// Transients (_transient_*, _site_transient_*) and other internal values.
			return false;
		}
		return false === strpos( $option, '_transient_' );
	}

	/**
	 * Whether this request saves WPBot settings.
	 *
	 * @return bool
	 */
	public static function is_settings_save() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		if ( 'POST' !== $method || ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		// phpcs:disable WordPress.Security.NonceVerification -- Only reads which screen sent the request; each save checks its own nonce.
		$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		$page   = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification
		if ( wp_doing_ajax() && ( 'heartbeat' === $action || ! preg_match( '/save|setting|option|update/', $action ) ) ) {
			return false;
		}
		if ( '' === $page ) {
			$page = self::referer_page();
		}
		$is_wpbot = self::is_wpbot_page( $page ) || ( ! wp_doing_ajax() && self::is_wpbot_page( $action ) );
		/**
		 * Whether this admin request saves WPBot settings (caches are cleared after it).
		 *
		 * @param bool   $is_wpbot Whether it came from a WPBot screen.
		 * @param string $page     The screen's page argument ('' when unknown).
		 * @param string $action   The request's action argument.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/is_settings_save', $is_wpbot, $page, $action );
	}

	/**
	 * The page argument of the screen the request was sent from ('' when none).
	 *
	 * @return string
	 */
	public static function referer_page() {
		$referer = function_exists( 'wp_get_referer' ) ? (string) wp_get_referer() : '';
		if ( '' === $referer ) {
			return '';
		}
		$query = (string) wp_parse_url( $referer, PHP_URL_QUERY );
		parse_str( $query, $args );
		return isset( $args['page'] ) && is_string( $args['page'] ) ? sanitize_key( $args['page'] ) : '';
	}

	/**
	 * Whether a page slug (or admin-post action) belongs to WPBot.
	 *
	 * @param string $slug Page slug or action.
	 * @return bool
	 */
	public static function is_wpbot_page( $slug ) {
		return '' !== $slug && (bool) preg_match( '/^(wpbot|qcld[-_]wpbot|qcld_wb_|wbcs[-_]|chatbot|retarget|qcld[-_]chatbot)/', strtolower( $slug ) );
	}

	/**
	 * Clear the caches (once per request).
	 *
	 * @return string[] Names of what was cleared.
	 */
	public static function purge() {
		if ( ! self::$changed ) {
			return array();
		}
		self::$changed = false;
		/**
		 * Whether WPBot clears the site's caches after its settings are saved.
		 *
		 * @param bool $enabled Enabled (default true).
		 */
		if ( ! apply_filters( 'qcld_wpbot_chat/purge_cache_on_save', true ) ) {
			return array();
		}
		$cleared = array();
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() && function_exists( 'wp_cache_flush' ) && wp_cache_flush() ) {
			$cleared[] = 'Object cache';
		}
		$cleared = array_merge( $cleared, self::purge_page_caches() );
		/**
		 * Caches were cleared after a WPBot settings save.
		 *
		 * @param string[] $cleared Names of what was cleared.
		 */
		do_action( 'qcld_wpbot_chat/cache_purged', $cleared );
		return $cleared;
	}

	/**
	 * Ask every active page cache plugin to clear its cache.
	 *
	 * @return string[] Plugin names.
	 */
	public static function purge_page_caches() {
		$cleared = array();
		if ( class_exists( '\\QuantumCloud\\AssetOptimizer\\Compat' ) && class_exists( '\\QuantumCloud\\AssetOptimizer\\Compat\\Exclusions' ) ) {
			$known = \QuantumCloud\AssetOptimizer\Compat::known();
			foreach ( \QuantumCloud\AssetOptimizer\Compat::integrations() as $class ) {
				if ( ! class_exists( $class ) ) {
					continue;
				}
				$integration = new $class( new \QuantumCloud\AssetOptimizer\Compat\Exclusions() );
				if ( $integration->active() && $integration->can_purge() && $integration->purge() ) {
					$slug      = $integration->slug();
					$cleared[] = isset( $known[ $slug ]['name'] ) ? $known[ $slug ]['name'] : $slug;
				}
			}
		} else {
			$cleared = self::purge_known_fallback();
		}
		// Plugins the Asset Optimizer module has no integration for. Each call is the plugin's own public API.
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Other plugins' purge actions.
		if ( defined( 'WPHB_VERSION' ) ) {
			do_action( 'wphb_clear_page_cache' );
			$cleared[] = 'Hummingbird';
		}
		if ( defined( 'BREEZE_VERSION' ) ) {
			do_action( 'breeze_clear_all_cache' );
			$cleared[] = 'Breeze';
		}
		if ( class_exists( '\\Cache_Enabler' ) ) {
			do_action( 'cache_enabler_clear_complete_cache' );
			$cleared[] = 'Cache Enabler';
		}
		if ( defined( 'NGINX_HELPER_BASENAME' ) ) {
			do_action( 'rt_nginx_helper_purge_all' );
			$cleared[] = 'Nginx Helper';
		}
		// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		if ( function_exists( 'WP_Optimize' ) && is_object( WP_Optimize() ) && method_exists( WP_Optimize(), 'get_page_cache' ) ) {
			$cache = WP_Optimize()->get_page_cache();
			if ( is_object( $cache ) && method_exists( $cache, 'purge' ) ) {
				$cache->purge();
				$cleared[] = 'WP-Optimize';
			}
		}
		if ( class_exists( '\\WpeCommon' ) ) {
			foreach ( array( 'purge_memcached', 'purge_varnish_cache' ) as $method ) {
				if ( method_exists( '\\WpeCommon', $method ) ) {
					call_user_func( array( '\\WpeCommon', $method ) );
				}
			}
			$cleared[] = 'WP Engine';
		}
		return array_values( array_unique( $cleared ) );
	}

	/**
	 * The most common page caches, when the Asset Optimizer module is not bundled.
	 *
	 * @return string[] Plugin names.
	 */
	private static function purge_known_fallback() {
		$cleared = array();
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$cleared[] = 'WP Rocket';
		}
		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_purge_all', 'WPBot settings saved' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's own purge action.
			$cleared[] = 'LiteSpeed Cache';
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
			$cleared[] = 'W3 Total Cache';
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$cleared[] = 'WP Super Cache';
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
			$cleared[] = 'SiteGround Speed Optimizer';
		}
		return $cleared;
	}

	/**
	 * Forget this request's state (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$changed = false;
		self::$hooked  = false;
	}
}
