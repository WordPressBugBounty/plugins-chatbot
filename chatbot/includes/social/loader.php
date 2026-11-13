<?php
/**
 * Newest-copy loader for the WPBot Social core module.
 *
 * Boots on plugins_loaded priority 6, after WPBot Core (priority 1) and AI
 * Writer (priority 5). Social checks for the Core features it needs, not only
 * the Core version: two copies of Core can share a version number, and the
 * first one registered wins (build-brief A1, QA #1).
 *
 * @package QuantumCloud\Social
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Social_Loader', false ) ) {

	/**
	 * Collects bundled copies and boots the newest one.
	 */
	final class QCLD_WPBot_Social_Loader {

		/**
		 * Lowest WPBot Core version this module works with.
		 */
		const MIN_CORE = '0.15.0';

		/**
		 * Core classes Social relies on. Missing ones keep Social off.
		 *
		 * @var string[]
		 */
		const REQUIRED_CORE_CLASSES = array(
			'QuantumCloud\\WPBotCore\\Crypto\\Secret_Box',
			'QuantumCloud\\WPBotCore\\Jobs\\Job_Store',
			'QuantumCloud\\WPBotCore\\Privacy\\Redactor',
		);

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
		 * Core features found missing at boot, for the admin notice.
		 *
		 * @var string[]
		 */
		private static $missing = array();

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
		 * Core features that are missing, if any.
		 *
		 * @return string[]
		 */
		public static function missing_core_features() {
			$missing = array();
			if ( ! defined( 'QCLD_WPBOT_CORE_VERSION' ) || version_compare( QCLD_WPBOT_CORE_VERSION, self::MIN_CORE, '<' ) ) {
				$missing[] = 'WPBot Core ' . self::MIN_CORE;
			}
			foreach ( self::REQUIRED_CORE_CLASSES as $class_name ) {
				if ( ! class_exists( $class_name ) ) {
					$missing[] = $class_name;
				}
			}
			return $missing;
		}

		/**
		 * Boot the newest copy when WPBot Core has what Social needs.
		 *
		 * @return void
		 */
		public static function boot() {
			if ( self::$booted || array() === self::$copies ) {
				return;
			}
			self::$missing = self::missing_core_features();
			if ( array() !== self::$missing ) {
				add_action( 'admin_notices', array( __CLASS__, 'core_missing_notice' ) );
				return;
			}
			self::$booted = true;

			$versions = array_keys( self::$copies );
			usort( $versions, 'version_compare' );
			$version = (string) end( $versions );
			$dir     = self::$copies[ $version ];

			define( 'QCLD_WPBOT_SOCIAL_VERSION', $version );
			define( 'QCLD_WPBOT_SOCIAL_DIR', $dir );
			define( 'QCLD_WPBOT_SOCIAL_FILE', $dir . '/social.php' );

			require_once $dir . '/src/autoload.php';

			\QuantumCloud\Social\Module::instance()->boot();

			/**
			 * Fires once WPBot Social has booted. The host registers its adapter
			 * here; the Master module boots on it (priority 20).
			 */
			do_action( 'qcld_wpbot_social/loaded' );
		}

		/**
		 * Admin notice when WPBot Core lacks what Social needs.
		 *
		 * @return void
		 */
		public static function core_missing_notice() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>' . esc_html__( 'WPBot Social is off: the bundled WPBot Core is missing or too old. Update the plugin, or deactivate other plugins that bundle an older WPBot Core.', 'chatbot' ) . '</p></div>';
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

	add_action( 'plugins_loaded', array( 'QCLD_WPBot_Social_Loader', 'boot' ), 6 );
}

if ( ! function_exists( 'qcld_wpbot_social_register_version' ) ) {
	/**
	 * Register one bundled copy of WPBot Social.
	 *
	 * @param string $version Version.
	 * @param string $dir     Folder.
	 * @return void
	 */
	function qcld_wpbot_social_register_version( $version, $dir ) {
		QCLD_WPBot_Social_Loader::register( $version, $dir );
	}
}
