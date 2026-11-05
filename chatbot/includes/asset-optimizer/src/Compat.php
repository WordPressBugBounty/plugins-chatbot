<?php
/**
 * Caching and optimisation plugins on the same site.
 *
 * Detection only lives here; each plugin's exclusion and purge integration is
 * a small class in src/Compat/ that fails silently when its hooks are gone.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the caching plugins that are active, tells their optimisers to
 * leave WPBot's files alone while WPBot's cache is on, and purges their page
 * caches after WPBot's cache is cleared when the owner opted in.
 */
class Compat {

	/**
	 * The module (null when only detecting).
	 *
	 * @var Module|null
	 */
	private $module;

	/**
	 * Constructor.
	 *
	 * @param Module|null $module The module.
	 */
	public function __construct( $module = null ) {
		$this->module = $module;
	}

	/**
	 * Integration classes.
	 *
	 * @return string[]
	 */
	public static function integrations() {
		return array(
			Compat\Wp_Rocket::class,
			Compat\Litespeed::class,
			Compat\Autoptimize::class,
			Compat\W3_Total_Cache::class,
			Compat\Sg_Optimizer::class,
			Compat\Wp_Super_Cache::class,
			Compat\Wp_Fastest_Cache::class,
		);
	}

	/**
	 * Active integrations.
	 *
	 * @param Compat\Exclusions $exclusions What to exclude.
	 * @return Compat\Integration[]
	 */
	public static function active_integrations( Compat\Exclusions $exclusions ) {
		$out = array();
		foreach ( self::integrations() as $class ) {
			$integration = new $class( $exclusions );
			if ( $integration->active() ) {
				$out[ $integration->slug() ] = $integration;
			}
		}
		return $out;
	}

	/**
	 * Hooks (registered only while WPBot's cache is on): exclusions now, and a
	 * purge after Clear when the owner turned it on.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'plugins_loaded', array( $this, 'exclude' ), 20 );
		if ( did_action( 'plugins_loaded' ) ) {
			$this->exclude();
		}
		add_action( 'wpbot_asset_cache_cleared', array( $this, 'maybe_purge' ) );
	}

	/**
	 * Add every active integration's exclusion filters (once).
	 *
	 * @return void
	 */
	public function exclude() {
		static $done = false;
		if ( $done || null === $this->module ) {
			return;
		}
		$done = true;
		foreach ( self::active_integrations( $this->exclusions() ) as $integration ) {
			$integration->exclude();
		}
	}

	/**
	 * What optimisers must leave alone.
	 *
	 * @return Compat\Exclusions
	 */
	public function exclusions() {
		$x            = new Compat\Exclusions();
		$host         = $this->module->host();
		$handles      = $this->module->registry()->all();
		$x->paths     = array_values(
			array_filter(
				array(
					trailingslashit( (string) wp_parse_url( $host->get_root_url(), PHP_URL_PATH ) ),
					trailingslashit( (string) wp_parse_url( dirname( $this->module->storage()->base()['url'] ), PHP_URL_PATH ) ),
				),
				static function ( $path ) {
					return '/' !== $path;
				}
			)
		);
		$x->scripts   = $handles['script'];
		$x->styles    = $handles['style'];
		$x->markers   = array_values( array_unique( array_merge( $host->get_inline_markers(), array( 'wpbotAo', 'wpbotAoRan' ) ) ) );
		$x->selectors = $host->get_chatbot_selectors();

		/**
		 * Filter what caching and optimisation plugins are told to leave alone.
		 *
		 * @param Compat\Exclusions $x Exclusions.
		 */
		return apply_filters( 'qcld_wpbot_ao_exclusions', $x );
	}

	/**
	 * After Clear: purge the page caches when the owner opted in.
	 *
	 * @return string[] Names of the plugins purged.
	 */
	public function maybe_purge() {
		if ( null === $this->module || ! $this->module->settings()->get( 'purge_page_cache' ) ) {
			return array();
		}
		$purged = array();
		$known  = self::known();
		foreach ( self::active_integrations( $this->exclusions() ) as $slug => $integration ) {
			if ( $integration->can_purge() && $integration->purge() ) {
				$purged[] = isset( $known[ $slug ] ) ? $known[ $slug ]['name'] : $slug;
			}
		}
		return $purged;
	}

	/**
	 * Detected plugins for which WPBot excludes itself automatically: slug => bool.
	 *
	 * @return array<string,bool>
	 */
	public static function automatic() {
		$out = array();
		foreach ( self::integrations() as $class ) {
			$integration = new $class( new Compat\Exclusions() );
			if ( $integration->active() ) {
				$out[ $integration->slug() ] = $integration->automatic();
			}
		}
		return $out;
	}

	/**
	 * Detected plugins with a page cache WPBot can purge.
	 *
	 * @return string[] Slugs.
	 */
	public static function purgeable() {
		$out = array();
		foreach ( self::integrations() as $class ) {
			$integration = new $class( new Compat\Exclusions() );
			if ( $integration->active() && $integration->can_purge() ) {
				$out[] = $integration->slug();
			}
		}
		return $out;
	}

	/**
	 * Known plugins: slug => [name, how to detect].
	 *
	 * @return array<string,array{name: string, constant?: string, class?: string, function?: string}>
	 */
	public static function known() {
		return array(
			'wp-rocket'        => array(
				'name'     => 'WP Rocket',
				'constant' => 'WP_ROCKET_VERSION',
			),
			'litespeed-cache'  => array(
				'name'     => 'LiteSpeed Cache',
				'constant' => 'LSCWP_V',
			),
			'w3-total-cache'   => array(
				'name'     => 'W3 Total Cache',
				'constant' => 'W3TC',
			),
			'wp-super-cache'   => array(
				'name'     => 'WP Super Cache',
				'function' => 'wp_cache_clear_cache',
			),
			'autoptimize'      => array(
				'name'     => 'Autoptimize',
				'constant' => 'AUTOPTIMIZE_PLUGIN_VERSION',
			),
			'sg-cachepress'    => array(
				'name'  => 'SiteGround Speed Optimizer',
				'class' => 'SiteGround_Optimizer\\Loader\\Loader',
			),
			'perfmatters'      => array(
				'name'     => 'Perfmatters',
				'constant' => 'PERFMATTERS_VERSION',
			),
			'cloudflare'       => array(
				'name'     => 'Cloudflare',
				'constant' => 'CLOUDFLARE_PLUGIN_DIR',
			),
			'wp-fastest-cache' => array(
				'name'  => 'WP Fastest Cache',
				'class' => 'WpFastestCache',
			),
		);
	}

	/**
	 * Active caching and optimisation plugins: slug => name.
	 *
	 * @return array<string,string>
	 */
	public static function detect() {
		$found = array();
		foreach ( self::known() as $slug => $plugin ) {
			if ( ( isset( $plugin['constant'] ) && defined( $plugin['constant'] ) )
				|| ( isset( $plugin['class'] ) && class_exists( $plugin['class'], false ) )
				|| ( isset( $plugin['function'] ) && function_exists( $plugin['function'] ) ) ) {
				$found[ $slug ] = $plugin['name'];
			}
		}

		/**
		 * Filter the detected caching plugins.
		 *
		 * @param array<string,string> $found slug => name.
		 */
		return (array) apply_filters( 'qcld_wpbot_ao_detected_plugins', $found );
	}
}
