<?php
/**
 * Workflow Triggers
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Workflow_Triggers
 */
class Workflow_Triggers extends Trigger {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'workflow';
		$this->group = __( 'Workflows', 'wpbot-automator' );
	}

	/**
	 * Get available sub-triggers
	 */
	public static function get_sub_triggers() {
		return array(
			'sub_workflow_trigger' => array(
				'title' => __( 'Sub-Workflow Trigger', 'wpbot-automator' ),
				'description' => __( 'Triggered when called from another workflow.', 'wpbot-automator' ),
			),
		);
	}

	/**
	 * Register the triggers
	 */
	public function register() {
		// No direct registration needed as it's called manually by Call_Sub_Workflow action.
	}
}
