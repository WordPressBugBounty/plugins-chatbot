<?php
/**
 * Newest-copy loader for the SEO & AI Search core module.
 *
 * Boots on plugins_loaded priority 6, after WPBot Core (priority 1) and AI
 * Writer (priority 5). When WPBot Core is missing, too old, or lacks the
 * SEO additions, the module stays off and tells the admin.
 *
 * @package QuantumCloud\SeoAiSearch
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'QCLD_WPBot_Seo_Loader', false ) ) {

	/**
	 * Collects bundled copies and boots the newest one.
	 */
	final class QCLD_WPBot_Seo_Loader {

		/**
		 * Lowest WPBot Core version this module works with.
		 */
		const MIN_CORE = '0.6.0';

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
		 * Whether the loaded WPBot Core has what this module needs.
		 *
		 * @return bool
		 */
		public static function core_ready() {
			return defined( 'QCLD_WPBOT_CORE_VERSION' )
				&& version_compare( QCLD_WPBOT_CORE_VERSION, self::MIN_CORE, '>=' )
				// The SEO additions (Analytics, quota counters) ship with WPBot Core's seo-ai-search changes.
				&& class_exists( '\QuantumCloud\WPBotCore\Connections\Analytics' )
				&& class_exists( '\QuantumCloud\WPBotCore\Connections\Quota' );
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
			if ( ! self::core_ready() ) {
				add_action( 'admin_notices', array( __CLASS__, 'core_missing_notice' ) );
				return;
			}
			self::$booted = true;

			$versions = array_keys( self::$copies );
			usort( $versions, 'version_compare' );
			$version = (string) end( $versions );
			$dir     = self::$copies[ $version ];

			define( 'QCLD_WPBOT_SEO_VERSION', $version );
			define( 'QCLD_WPBOT_SEO_DIR', $dir );
			define( 'QCLD_WPBOT_SEO_FILE', $dir . '/seo-ai-search.php' );

			require_once $dir . '/src/autoload.php';
			require_once $dir . '/src/Api/functions.php';

			\QuantumCloud\SeoAiSearch\Module::instance()->boot();

			/**
			 * Fires once SEO & AI Search has booted. Hosts register their
			 * adapter here; the Pro module boots on it (priority 20).
			 */
			do_action( 'qcld_wpbot_seo/loaded' );
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
			echo '<div class="notice notice-error"><p>' . esc_html__( 'SEO & AI Search needs the WPBot Core module, which is missing or out of date. Reinstall or update the plugin.', 'chatbot' ) . '</p></div>';
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

	add_action( 'plugins_loaded', array( 'QCLD_WPBot_Seo_Loader', 'boot' ), 6 );
}

if ( ! function_exists( 'qcld_wpbot_seo_register_version' ) ) {
	/**
	 * Register one bundled copy of SEO & AI Search.
	 *
	 * @param string $version Version.
	 * @param string $dir     Folder.
	 * @return void
	 */
	function qcld_wpbot_seo_register_version( $version, $dir ) {
		QCLD_WPBot_Seo_Loader::register( $version, $dir );
	}
}
