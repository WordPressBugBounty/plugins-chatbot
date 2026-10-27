<?php
/**
 * Plugin Name: WPBot- Automator
 * Plugin URI: https://www.wpbot.pro/
 * Description: A powerful automation plugin for WordPress with a visual no-code builder.
 * Version: 1.3.2
 * Author: QuantumCloud
 * Author URI: https://www.wpbot.pro/
 * Text Domain: wpbot-automator
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPLv2 or later
 */

namespace WPbot_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Check if the standalone WPBot Automator plugin is active to prevent fatal errors from redeclaration.
if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if ( is_plugin_active( 'wpbot/wpbot-automator.php' ) ) {
	return; // Abort loading the addon. The standalone plugin will load instead.
}
// A standalone copy installed under another folder name may already have loaded.
if ( class_exists( 'WPbot_Automator\\Autoloader', false ) ) {
	return;
}

// Define Constants.
if ( ! defined( 'WPBOT_AUTOMATOR_VERSION' ) ) {
	define( 'WPBOT_AUTOMATOR_VERSION', '1.3.2' );
}
if ( ! defined( 'WPBOT_AUTOMATOR_PLUGIN_DIR' ) ) {
	define( 'WPBOT_AUTOMATOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WPBOT_AUTOMATOR_PLUGIN_URL' ) ) {
	define( 'WPBOT_AUTOMATOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Include Autoloader.
require_once WPBOT_AUTOMATOR_PLUGIN_DIR . 'includes/autoloader.php';

use WPbot_Automator\Autoloader;

if ( ! class_exists( 'WPbot_Automator\WPbot_Automator' ) ) {
	/**
	 * Main Plugin Class
	 */
	class WPbot_Automator {

		/**
		 * Instance of this class
		 * 
		 * @var WPbot_Automator|null
		 */
		private static $instance = null;

		/**
		 * Get the instance of the class
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->define_constants();
			
			// Run Autoloader.
			Autoloader::run();

			$this->init_hooks();
			
			// Initialize the Core Plugin logic.
			Core\Plugin::init();
		}

		/**
		 * Define constants
		 */
		private function define_constants() {
			// Constants already defined at file level for simplicity in some contexts,
			// but typically we can put more here if needed.
		}

		/**
		 * Initialize Hooks
		 */
		private function init_hooks() {
			// As an addon, we use admin_init to check and run installation
			add_action( 'admin_init', array( $this, 'maybe_install' ) );
		}

		public function maybe_install() {
			$installed_version = get_option( 'wpbot_automator_version' );
			if ( $installed_version !== WPBOT_AUTOMATOR_VERSION ) {
				Core\Database::install();
				Core\Default_Workflows::install();
				Core\Trigger_Index::rebuild();
				update_option( 'wpbot_automator_version', WPBOT_AUTOMATOR_VERSION );
			}
		}
	}
}

/**
 * Initialize the plugin
 */
if(!function_exists(__NAMESPACE__ . '\wpbot_automator_init')) {
	function wpbot_automator_init() {
		return WPbot_Automator::get_instance();
	}
}

wpbot_automator_init();
