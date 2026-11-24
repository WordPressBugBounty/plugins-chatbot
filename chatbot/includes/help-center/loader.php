<?php
/**
 * Newest-copy loader for the WPBot Help Center core module.
 *
 * WPBot Free and WPBot Pro (and later KBx) each bundle a copy. Every copy
 * registers itself; on plugins_loaded (priority 6, after WPBot Core at 1 and
 * the chatbot module at 3) only the newest one boots, so a site never runs two
 * Help Centers.
 *
 * @package QuantumCloud\HelpCenter
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Help_Center_Loader', false ) ) {

	/**
	 * Collects bundled copies and boots the newest one.
	 */
	final class QCLD_WPBot_Help_Center_Loader {

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
		 * @param string $version Module version.
		 * @param string $dir     Module folder.
		 * @return void
		 */
		public static function register( $version, $dir ) {
			$version = (string) $version;
			if ( ! isset( self::$copies[ $version ] ) ) {
				self::$copies[ $version ] = rtrim( (string) $dir, '/\\' );
			}
			// The host was included after plugins_loaded (for example while it is being activated).
			if ( ! self::$booted && did_action( 'plugins_loaded' ) ) {
				self::boot();
			}
		}

		/**
		 * The newest registered version, or an empty string.
		 *
		 * @return string
		 */
		public static function newest() {
			if ( array() === self::$copies ) {
				return '';
			}
			$versions = array_keys( self::$copies );
			usort( $versions, 'version_compare' );
			return (string) end( $versions );
		}

		/**
		 * Boot the newest copy.
		 *
		 * @return void
		 */
		public static function boot() {
			if ( self::$booted || array() === self::$copies ) {
				return;
			}
			self::$booted = true;

			$version = self::newest();
			$dir     = self::$copies[ $version ];

			define( 'QCLD_WPBOT_HELP_CENTER_VERSION', $version );
			define( 'QCLD_WPBOT_HELP_CENTER_DIR', $dir );
			define( 'QCLD_WPBOT_HELP_CENTER_FILE', $dir . '/help-center.php' );

			require_once $dir . '/src/autoload.php';
			require_once $dir . '/src/functions.php';

			\QuantumCloud\HelpCenter\Module::instance()->boot();
		}

		/**
		 * Registered copies (for diagnostics and tests).
		 *
		 * @return array<string,string>
		 */
		public static function copies() {
			return self::$copies;
		}
	}

	add_action( 'plugins_loaded', array( 'QCLD_WPBot_Help_Center_Loader', 'boot' ), 6 );
}//end if

if ( ! function_exists( 'qcld_wpbot_help_center_register_version' ) ) {
	/**
	 * Register a bundled copy of the module.
	 *
	 * @param string $version Module version.
	 * @param string $dir     Module folder.
	 * @return void
	 */
	function qcld_wpbot_help_center_register_version( $version, $dir ) {
		QCLD_WPBot_Help_Center_Loader::register( $version, $dir );
	}
}
