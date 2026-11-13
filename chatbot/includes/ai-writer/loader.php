<?php
/**
 * Newest-copy loader for the AI Writer core module.
 *
 * Boots on plugins_loaded priority 5, after WPBot Core (priority 1). When
 * WPBot Core is missing or too old, AI Writer stays off and tells the admin.
 *
 * @package QuantumCloud\AiWriter
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Writer_Loader', false ) ) {

	/**
	 * Collects bundled copies and boots the newest one.
	 */
	final class QCLD_WPBot_Writer_Loader {

		/**
		 * Lowest WPBot Core version this module works with.
		 */
		const MIN_CORE = '0.12.0';

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
		 * @param string $version Version.
		 * @param string $dir     Folder.
		 * @return void
		 */
		public static function register( $version, $dir ) {
			$version = (string) $version;
			if ( ! isset( self::$copies[ $version ] ) ) {
				self::$copies[ $version ] = rtrim( (string) $dir, '/\\' );
			}
			if ( ! self::$booted && did_action( 'plugins_loaded' ) ) {
				self::boot();
			}
		}

		/**
		 * Boot the newest copy when WPBot Core is ready.
		 *
		 * @return void
		 */
		public static function boot() {
			if ( self::$booted || array() === self::$copies ) {
				return;
			}
			if ( ! defined( 'QCLD_WPBOT_CORE_VERSION' ) || version_compare( QCLD_WPBOT_CORE_VERSION, self::MIN_CORE, '<' ) ) {
				add_action( 'admin_notices', array( __CLASS__, 'core_missing_notice' ) );
				return;
			}
			self::$booted = true;

			$versions = array_keys( self::$copies );
			usort( $versions, 'version_compare' );
			$version = (string) end( $versions );
			$dir     = self::$copies[ $version ];

			define( 'QCLD_WPBOT_WRITER_VERSION', $version );
			define( 'QCLD_WPBOT_WRITER_DIR', $dir );
			define( 'QCLD_WPBOT_WRITER_FILE', $dir . '/ai-writer.php' );

			require_once $dir . '/src/autoload.php';
			require_once $dir . '/src/Api/functions.php';

			\QuantumCloud\AiWriter\Module::instance()->boot();

			/**
			 * Fires once AI Writer has booted. Hosts register their adapter
			 * here; the Pro module boots on it (priority 20).
			 */
			do_action( 'qcld_wpbot_writer/loaded' );
		}

		/**
		 * Admin notice when WPBot Core is not available.
		 *
		 * @return void
		 */
		public static function core_missing_notice() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>' . esc_html__( 'AI Writer needs its shared core module, which is missing or out of date. Reinstall or update the plugin.', 'chatbot' ) . '</p></div>';
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

	add_action( 'plugins_loaded', array( 'QCLD_WPBot_Writer_Loader', 'boot' ), 5 );
}

if ( ! function_exists( 'qcld_wpbot_writer_register_version' ) ) {
	/**
	 * Register one bundled copy of AI Writer.
	 *
	 * @param string $version Version.
	 * @param string $dir     Folder.
	 * @return void
	 */
	function qcld_wpbot_writer_register_version( $version, $dir ) {
		QCLD_WPBot_Writer_Loader::register( $version, $dir );
	}
}
