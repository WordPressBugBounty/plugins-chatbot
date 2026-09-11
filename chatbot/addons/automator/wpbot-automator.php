<?php
/**
 * Plugin Name: WPBot- Automator
 * Plugin URI: https://www.wpbot.pro/
 * Description: A powerful automation plugin for WordPress with a visual no-code builder.
 * Version: 1.3.1
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

// Define Constants.
if ( ! defined( 'WPBOT_AUTOMATOR_VERSION' ) ) {
	define( 'WPBOT_AUTOMATOR_VERSION', '1.3.1' );
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

add_action('admin_init', function() {
    if (isset($_GET['install_cf_workflow'])) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpbot_automator_workflows';
        
        $workflow = array(
            'name'        => 'Conversational Form to Sheet, AI & Email',
            'description' => 'Saves conversational form data to Google Sheets, generates an AI summary, and emails the response.',
            'workflow_data' => array(
                'nodes'       => array(
                    array(
                        'id'       => 'trigger-1',
                        'type'     => 'trigger',
                        'position' => array('x' => 100, 'y' => 100),
                        'data'     => array(
                            'appId'    => 'wpbot',
                            'actionId' => 'conversationalforms_submit',
                            'label'    => 'Conversational Forms Submitted',
                        ),
                    ),
                    array(
                        'id'       => 'action-1',
                        'type'     => 'action',
                        'position' => array('x' => 400, 'y' => 100),
                        'data'     => array(
                            'appId'    => 'google_sheets',
                            'actionId' => 'gs_add_row',
                            'label'    => 'Add row to Google Sheet',
                            'config'   => array(
                                'spreadsheet_id' => 'your_google_sheet_id_here',
                                'sheet_name'     => 'Sheet1',
                                'row_data'       => array(
                                    '{{wpbot.conversationalforms_submit.name}}',
                                    '{{wpbot.conversationalforms_submit.email}}',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'id'       => 'action-2',
                        'type'     => 'action',
                        'position' => array('x' => 700, 'y' => 100),
                        'data'     => array(
                            'appId'    => 'openai',
                            'actionId' => 'chat_completion',
                            'label'    => 'Generate Summary',
                            'config'   => array(
                                'model'       => 'gpt-4o-mini',
                                'prompt'      => "Summarize the following form submission: Name: {{wpbot.conversationalforms_submit.name}}, Email: {{wpbot.conversationalforms_submit.email}}",
                                'max_tokens'  => '150',
                                'temperature' => '0.7',
                            ),
                        ),
                    ),
                    array(
                        'id'       => 'action-3',
                        'type'     => 'action',
                        'position' => array('x' => 1000, 'y' => 100),
                        'data'     => array(
                            'appId'    => 'mail',
                            'actionId' => 'send_email',
                            'label'    => 'Send Email Response',
                            'config'   => array(
                                'to_email'    => '{{wpbot.conversationalforms_submit.email}}',
                                'subject'     => 'Thank you for your submission',
                                'body'        => "Hello {{wpbot.conversationalforms_submit.name}},\n\nHere is a summary of your submission:\n{{openai.chat_completion.response}}",
                                'is_html'     => false,
                            ),
                        ),
                    ),
                ),
                'connections' => array(
                    array('id' => 'conn-1', 'source' => 'trigger-1', 'target' => 'action-1'),
                    array('id' => 'conn-2', 'source' => 'action-1', 'target' => 'action-2'),
                    array('id' => 'conn-3', 'source' => 'action-2', 'target' => 'action-3'),
                ),
            ),
        );

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE name = %s", $workflow['name']));
        if (!$exists) {
            $wpdb->insert(
                $table,
                array(
                    'name'          => $workflow['name'],
                    'description'   => $workflow['description'],
                    'workflow_data' => wp_json_encode($workflow['workflow_data']),
                    'status'        => 'active',
                ),
                array('%s', '%s', '%s', '%s')
            );
        } else {
            $wpdb->update(
                $table,
                array(
                    'description'   => $workflow['description'],
                    'workflow_data' => wp_json_encode($workflow['workflow_data']),
                ),
                array('id' => $exists),
                array('%s', '%s'),
                array('%d')
            );
        }
    }
});

wpbot_automator_init();
