<?php
/**
 * Newest-copy loader for the WPBot Asset Cache core module.
 *
 * Every plugin that bundles the module calls qcld_wpbot_ao_register_version()
 * from its copy of asset-optimizer.php. On plugins_loaded (priority 1) the
 * loader boots the newest registered copy and fires `qcld_wpbot_ao_loaded`,
 * where hosts register their adapter and the Pro module adds its features.
 *
 * Keep this file small and backwards compatible: whichever bundled copy is
 * included first defines the loader class for everyone.
 *
 * @package QuantumCloud\AssetOptimizer
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- The loader class and its registration function must ship in one small file shared by every bundled copy.

if ( ! class_exists( 'QCLD_WPBot_AO_Loader', false ) ) {

	/**
	 * Picks the newest bundled copy of the module and boots it.
	 */
	final class QCLD_WPBot_AO_Loader {

		/**
		 * Registered copies: version => directory.
		 *
		 * @var array<string,string>
		 */
		private static $copies = array();

		/**
		 * Whether a copy has been booted.
		 *
		 * @var bool
		 */
		private static $booted = false;

		/**
		 * Register one bundled copy.
		 *
		 * @param string $version Semantic version of the copy.
		 * @param string $dir     Absolute path to the copy's folder.
		 * @return void
		 */
		public static function register( $version, $dir ) {
			$version = (string) $version;
			if ( ! isset( self::$copies[ $version ] ) ) {
				self::$copies[ $version ] = untrailingslashit( (string) $dir );
			}

			// The host was included after plugins_loaded (for example while it is being activated).
			if ( did_action( 'plugins_loaded' ) && ! self::$booted ) {
				self::boot();
			}
		}

		/**
		 * Boot the newest registered copy.
		 *
		 * @return void
		 */
		public static function boot() {
			if ( self::$booted || array() === self::$copies ) {
				return;
			}
			self::$booted = true;

			uksort( self::$copies, 'version_compare' );
			$versions = array_keys( self::$copies );
			$version  = end( $versions );
			$dir      = self::$copies[ $version ];

			define( 'QCLD_WPBOT_AO_VERSION', $version );
			define( 'QCLD_WPBOT_AO_DIR', $dir );
			define( 'QCLD_WPBOT_AO_FILE', $dir . '/asset-optimizer.php' );

			require_once $dir . '/src/autoload.php';
			require_once $dir . '/src/functions.php';

			/**
			 * Fires once the newest copy of WPBot Asset Cache is loaded.
			 * The Pro module adds its filters first (priority 5); hosts then
			 * register their adapter (priority 10), which sets the module up.
			 *
			 * @param string $version Version of the booted copy.
			 */
			do_action( 'qcld_wpbot_ao_loaded', $version );
		}

		/**
		 * Registered copies, for diagnostics.
		 *
		 * @return array<string,string>
		 */
		public static function copies() {
			return self::$copies;
		}
	}

	add_action( 'plugins_loaded', array( 'QCLD_WPBot_AO_Loader', 'boot' ), 1 );
}

if ( ! function_exists( 'qcld_wpbot_ao_register_version' ) ) {
	/**
	 * Register a bundled copy of the module.
	 *
	 * @param string $version Version of the copy.
	 * @param string $dir     Folder of the copy.
	 * @return void
	 */
	function qcld_wpbot_ao_register_version( $version, $dir ) {
		QCLD_WPBot_AO_Loader::register( $version, $dir );
	}
}
