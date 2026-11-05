<?php
/**
 * Newest-copy loader for WPBot Core.
 *
 * Several plugins may bundle their own copy of this module. Each copy calls
 * qcld_wpbot_core_register_version(); on plugins_loaded (priority 1) the copy
 * with the highest version is booted and the others are ignored. The first
 * copy included defines this class for everyone, so it must stay small and
 * backwards compatible.
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Core_Loader', false ) ) {

	/**
	 * Collects bundled copies and boots the newest one.
	 */
	final class QCLD_WPBot_Core_Loader {

		/**
		 * Bundled copies, keyed by version.
		 *
		 * @var array<string,string>
		 */
		private static $copies = array();

		/**
		 * Whether a copy is already running.
		 *
		 * @var bool
		 */
		private static $booted = false;

		/**
		 * Remember one bundled copy.
		 *
		 * @param string $version Version of the copy.
		 * @param string $dir     Absolute folder of the copy.
		 * @return void
		 */
		public static function register( $version, $dir ) {
			$version = (string) $version;
			if ( ! isset( self::$copies[ $version ] ) ) {
				self::$copies[ $version ] = rtrim( (string) $dir, '/\\' );
			}

			// Included late (after plugins_loaded): boot straight away.
			if ( ! self::$booted && did_action( 'plugins_loaded' ) ) {
				self::boot();
			}
		}

		/**
		 * Boot the newest registered copy, once.
		 *
		 * @return void
		 */
		public static function boot() {
			if ( self::$booted || array() === self::$copies ) {
				return;
			}
			self::$booted = true;

			$versions = array_keys( self::$copies );
			usort( $versions, 'version_compare' );
			$version = (string) end( $versions );
			$dir     = self::$copies[ $version ];

			define( 'QCLD_WPBOT_CORE_VERSION', $version );
			define( 'QCLD_WPBOT_CORE_DIR', $dir );
			define( 'QCLD_WPBOT_CORE_FILE', $dir . '/wpbot-core.php' );

			require_once $dir . '/src/autoload.php';

			\QuantumCloud\WPBotCore\Core::instance()->boot();

			/**
			 * Fires once the newest copy of WPBot Core has booted.
			 * Hosts register their adapter here; modules hook in after it.
			 */
			do_action( 'qcld_wpbot_core/loaded' );
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

	add_action( 'plugins_loaded', array( 'QCLD_WPBot_Core_Loader', 'boot' ), 1 );
}

if ( ! function_exists( 'qcld_wpbot_core_register_version' ) ) {
	/**
	 * Register one bundled copy of WPBot Core.
	 *
	 * @param string $version Version of the copy.
	 * @param string $dir     Folder of the copy.
	 * @return void
	 */
	function qcld_wpbot_core_register_version( $version, $dir ) {
		QCLD_WPBot_Core_Loader::register( $version, $dir );
	}
}
