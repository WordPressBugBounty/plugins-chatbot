<?php
/**
 * Iterator Actions
 *
 * Acts as a marker for the workflow engine to loop over an array in trigger_data.
 * The actual loop logic is handled in Workflow_Runner.
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Iterator_Actions
 */
class Iterator_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'iterator';
		$this->group = __( 'Flow Control', 'wpbot-automator' );
	}

	/**
	 * Execute the action.
	 * The Iterator itself is a no-op — the engine handles the looping.
	 *
	 * @param array $action_data Configuration for this action from the workflow.
	 * @param array $trigger_data Data passed from the trigger.
	 * @return array
	 */
	public function execute( $action_data, $trigger_data ) {
		// This is handled directly in Workflow_Runner. Just mark success.
		return array( 'success' => true, 'message' => 'Iterator started.' );
	}
}
