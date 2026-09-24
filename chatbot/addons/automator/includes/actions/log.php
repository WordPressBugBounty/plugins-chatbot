<?php
/**
 * Log Action
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Log
 */
class Log extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'log';
		$this->group = __( 'Utility', 'wpbot-automator' );
		$this->title = __( 'Log Message', 'wpbot-automator' );
	}

	/**
	 * Execute the action
	 */
	public function execute( $action_config, $trigger_data ) {
		$message = isset( $action_config['message'] ) ? $action_config['message'] : 'Logged via automation';
		
		// In a real scenario, this would write to our custom logs table.
		//error_log( 'WPBOT-AUTOMATOR: ' . $message );
		
		return true;
	}
}
