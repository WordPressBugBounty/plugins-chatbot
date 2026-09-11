<?php
/**
 * Registry for Triggers and Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Core;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Registry class
 */
class Registry
{

	/**
	 * Registered Triggers
	 * 
	 * @var array
	 */
	private static $triggers = array();

	/**
	 * Registered Actions
	 * 
	 * @var array
	 */
	private static $actions = array();

	/**
	 * Register a Trigger
	 * 
	 * @param \WPbot_Automator\Triggers\Trigger $trigger Trigger instance.
	 */
	public static function register_trigger($trigger)
	{
		self::$triggers[$trigger->get_id()] = $trigger;
		$trigger->register();
	}

	/**
	 * Register an Action
	 * 
	 * @param \WPbot_Automator\Actions\Action $action Action instance.
	 */
	public static function register_action($action)
	{
		self::$actions[$action->get_id()] = $action;
	}

	/**
	 * Get all Triggers
	 * 
	 * @return array
	 */
	public static function get_triggers()
	{
		return self::$triggers;
	}

	/**
	 * Get all Actions
	 * 
	 * @return array
	 */
	public static function get_actions()
	{
		return self::$actions;
	}

	/**
	 * Get Trigger by ID
	 * 
	 * @param string $id Trigger ID.
	 * @return \WPbot_Automator\Triggers\Trigger|null
	 */
	public static function get_trigger($id)
	{
		return isset(self::$triggers[$id]) ? self::$triggers[$id] : null;
	}

	/**
	 * Get Action by ID
	 * 
	 * @param string $id Action ID.
	 * @return \WPbot_Automator\Actions\Action|null
	 */
	public static function get_action($id)
	{
		return isset(self::$actions[$id]) ? self::$actions[$id] : null;
	}

	/**
	 * Initialize and register default triggers and actions
	 */
	public static function init()
	{
		global $wpdb;
		error_log( 'WPbot Automator - Registry::init() starting.' );
		error_log( 'WPbot Automator - DB_NAME: ' . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') );
		error_log( 'WPbot Automator - DB_HOST: ' . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') );
		error_log( 'WPbot Automator - Table Prefix: ' . $wpdb->prefix );
		
		// Ensure database tables are created.
		$table_templates = Database::get_email_templates_table();
		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$table_templates'" ) ) {
			error_log( 'WPbot Automator - Email Templates table missing. Running Database::install().' );
			Database::install();
		}
		Database::maybe_upgrade();
		
		error_log('WPbot Automator - Registry::init() starting.');
		error_log('WPbot Automator - DB_NAME: ' . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED'));
		error_log('WPbot Automator - DB_HOST: ' . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED'));
		error_log('WPbot Automator - Table Prefix: ' . $wpdb->prefix);

		// Register WP Core Triggers.
		self::register_trigger(new \WPbot_Automator\Triggers\WordPress_Core());
		error_log('WPbot Automator - WordPress_Core registered.');

		// Register WooCommerce Triggers.
		self::register_trigger(new \WPbot_Automator\Triggers\WooCommerce_Triggers());
		error_log('WPbot Automator - WooCommerce_Triggers registered.');

		// Register Form Triggers.
		self::register_trigger(new \WPbot_Automator\Triggers\Form_Triggers());
		self::register_trigger(new \WPbot_Automator\Triggers\CF7_Triggers());
		self::register_trigger(new \WPbot_Automator\Triggers\Workflow_Triggers());
		error_log('WPbot Automator - Form_Triggers registered.');

		// Register Webhook Trigger.
		self::register_trigger(new \WPbot_Automator\Triggers\Webhook());
		error_log('WPbot Automator - Webhook registered.');

		// Register Facebook Lead Ads Trigger.
		self::register_trigger(new \WPbot_Automator\Triggers\Facebook_Lead_Ads_Triggers());
		error_log('WPbot Automator - Facebook_Lead_Ads_Triggers registered.');

		// Register Tables Triggers.
		self::register_trigger(new \WPbot_Automator\Triggers\Tables_Triggers());
		error_log('WPbot Automator - Tables_Triggers registered.');

		// Register WPBot Triggers.
		self::register_trigger(new \WPbot_Automator\Triggers\WPBot_Triggers());
		error_log('WPbot Automator - WPBot_Triggers registered.');

		// Register Basic Actions.
		self::register_action(new \WPbot_Automator\Actions\Email_Actions());
		self::register_action(new \WPbot_Automator\Actions\Log());
		self::register_action(new \WPbot_Automator\Actions\WordPress_Actions());
		self::register_action(new \WPbot_Automator\Actions\WooCommerce_Actions());
		self::register_action(new \WPbot_Automator\Actions\Webhook_Actions());
		self::register_action(new \WPbot_Automator\Actions\API_Actions());
		self::register_action(new \WPbot_Automator\Actions\Google_Sheets_Actions());
		self::register_action(new \WPbot_Automator\Actions\MailboxLayer_Actions());
		self::register_action(new \WPbot_Automator\Actions\FluentCRM_Actions());
		self::register_action(new \WPbot_Automator\Actions\XML_Parser_Actions());
		self::register_action(new \WPbot_Automator\Actions\Text_Formatter_Actions());
		self::register_action(new \WPbot_Automator\Actions\Unit_Converter_Actions());
		self::register_action(new \WPbot_Automator\Actions\Workflow_Actions());
		self::register_action(new \WPbot_Automator\Actions\Filters_Actions());
		self::register_action(new \WPbot_Automator\Actions\WPBot_Actions());
		error_log('WPbot Automator - Actions registered.');

		// Register Flow Control.
		self::register_action(new \WPbot_Automator\Actions\Iterator_Actions());
		self::register_action(new \WPbot_Automator\Actions\Delay_Actions());


		// Register Messaging Actions.
		self::register_action(new \WPbot_Automator\Actions\Telegram_Actions());
		self::register_action(new \WPbot_Automator\Actions\Facebook_Actions());

		// Register Tables Actions.
		self::register_action(new \WPbot_Automator\Actions\Tables_Actions());
		error_log('WPbot Automator - Tables_Actions registered.');

		// Register CSV Creator Actions.
		self::register_action(new \WPbot_Automator\Actions\CSV_Creator_Actions());
		error_log('WPbot Automator - CSV_Creator_Actions registered.');

		// Register AI Actions.
		self::register_action(new \WPbot_Automator\Actions\OpenAI_Actions());
		self::register_action(new \WPbot_Automator\Actions\Claude_Actions());
		self::register_action(new \WPbot_Automator\Actions\Gemini_Actions());
		self::register_action(new \WPbot_Automator\Actions\Mistral_Actions());
		self::register_action(new \WPbot_Automator\Actions\Groq_Actions());
		self::register_action(new \WPbot_Automator\Actions\Grok_Actions());
		self::register_action(new \WPbot_Automator\Actions\OpenRouter_Actions());

		// Diagnostic: Dump all workflows to log.
		\WPbot_Automator\Engine\Workflow_Runner::debug_dump_all_workflows();

		// TEMPORARY TEST TRIGGER for Workflow 16.
		if (defined('WPBOT_TEST_WF16') && WPBOT_TEST_WF16) {
			error_log('WPbot Automator - FIRING TEST TRIGGER for Workflow 16');
			$fields = array(
				'1' => array('name' => 'Email', 'value' => 'test@example.com', 'id' => 1, 'type' => 'email')
			);
			$form_data = array('id' => 37, 'settings' => array('form_title' => 'Test Form'));
			do_action('wpforms_process_complete', $fields, array(), $form_data, 12345);
		}

		error_log('WPbot Automator - Registry::init() completed.');
	}
}
