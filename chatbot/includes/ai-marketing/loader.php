<?php
/**
 * Newest-copy loader for the WPBot AI Marketing core module.
 *
 * Boots on plugins_loaded priority 7, after WPBot Core (priority 1), AI
 * Writer (5) and Social (6). AI Marketing checks for the Core features it
 * needs, not only the Core version: two copies of Core can share a version
 * number, and the first one registered wins.
 *
 * @package QuantumCloud\AiMarketing
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Marketing_Loader', false ) ) {

	/**
	 * Collects bundled copies and boots the newest one.
	 */
	final class QCLD_WPBot_Marketing_Loader {

		/**
		 * Lowest WPBot Core version this module works with.
		 */
		const MIN_CORE = '0.15.0';

		/**
		 * Core classes AI Marketing relies on. Missing ones keep it off.
		 *
		 * @var string[]
		 */
		const REQUIRED_CORE_CLASSES = array(
			'QuantumCloud\\WPBotCore\\Jobs\\Job_Store',
			'QuantumCloud\\WPBotCore\\Privacy\\Redactor',
			'QuantumCloud\\WPBotCore\\Admin\\Shell',
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
		 * Boot the newest copy when WPBot Core has what AI Marketing needs.
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

			define( 'QCLD_WPBOT_MARKETING_VERSION', $version );
			define( 'QCLD_WPBOT_MARKETING_DIR', $dir );
			define( 'QCLD_WPBOT_MARKETING_FILE', $dir . '/ai-marketing.php' );

			require_once $dir . '/src/autoload.php';

			\QuantumCloud\AiMarketing\Module::instance()->boot();

			/**
			 * Fires once AI Marketing has booted. The host registers its adapter
			 * here (Module::register_host()); the Pro module boots on it at
			 * priority 20.
			 */
			do_action( 'qcld_wpbot_marketing/loaded' );
		}

		/**
		 * Admin notice when WPBot Core lacks what AI Marketing needs.
		 *
		 * @return void
		 */
		public static function core_missing_notice() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>' . esc_html__( 'WPBot AI Marketing is off: the bundled WPBot Core is missing or too old. Update the plugin, or deactivate other plugins that bundle an older WPBot Core.', 'chatbot' ) . '</p></div>';
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

	add_action( 'plugins_loaded', array( 'QCLD_WPBot_Marketing_Loader', 'boot' ), 7 );
}

if ( ! function_exists( 'qcld_wpbot_marketing_register_version' ) ) {
	/**
	 * Register one bundled copy of WPBot AI Marketing.
	 *
	 * @param string $version Version.
	 * @param string $dir     Folder.
	 * @return void
	 */
	function qcld_wpbot_marketing_register_version( $version, $dir ) {
		QCLD_WPBot_Marketing_Loader::register( $version, $dir );
	}
}
