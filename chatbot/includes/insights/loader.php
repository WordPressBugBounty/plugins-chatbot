<?php
/**
 * WPBot Insights module loader.
 *
 * Every plugin that bundles this module calls qcin_register_version() from its
 * copy of insights.php. On plugins_loaded (priority 1) the loader picks the
 * newest registered copy, registers only that copy's autoloader, and fires
 * the `qcin/loaded` action. Hosts register their adapter inside that action.
 *
 * Keep this file small and backwards compatible: whichever bundled copy is
 * included first defines the loader class for everyone.
 *
 * @package QuantumCloud\Insights
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCIN_Loader', false ) ) {

	/**
	 * Picks the newest bundled copy of the module and boots it.
	 */
	final class QCIN_Loader {

		/**
		 * Registered copies: version => directory.
		 *
		 * @var array<string,string>
		 */
		private static $versions = array();

		/**
		 * Whether a copy has been booted.
		 *
		 * @var bool
		 */
		private static $booted = false;

		/**
		 * Register one bundled copy of the module.
		 *
		 * @param string $version Semantic version of the copy.
		 * @param string $dir     Absolute path to the copy's folder.
		 */
		public static function register( $version, $dir ) {
			$version = (string) $version;
			if ( ! isset( self::$versions[ $version ] ) ) {
				self::$versions[ $version ] = untrailingslashit( $dir );
			}

			// A host included the module after plugins_loaded already ran.
			if ( did_action( 'plugins_loaded' ) && ! self::$booted ) {
				self::boot();
			}
		}

		/**
		 * Boot the newest registered copy.
		 */
		public static function boot() {
			if ( self::$booted || empty( self::$versions ) ) {
				return;
			}
			self::$booted = true;

			uksort( self::$versions, 'version_compare' );
			$version = array_key_last( self::$versions );
			$dir     = self::$versions[ $version ];

			define( 'QCIN_VERSION', $version );
			define( 'QCIN_DIR', $dir );
			define( 'QCIN_FILE', $dir . '/insights.php' );

			require_once $dir . '/src/autoload.php';

			/**
			 * Fires once the newest copy of WPBot Insights is loaded.
			 * Hosts register their adapter here.
			 */
			do_action( 'qcin/loaded' );
		}

		/**
		 * Registered versions, for diagnostics.
		 *
		 * @return array<string,string>
		 */
		public static function versions() {
			return self::$versions;
		}
	}

	add_action( 'plugins_loaded', array( 'QCIN_Loader', 'boot' ), 1 );
}

if ( ! function_exists( 'qcin_register_version' ) ) {
	/**
	 * Register a bundled copy of the module.
	 *
	 * @param string $version Version of the copy.
	 * @param string $dir     Folder of the copy.
	 */
	function qcin_register_version( $version, $dir ) {
		QCIN_Loader::register( $version, $dir );
	}
}
