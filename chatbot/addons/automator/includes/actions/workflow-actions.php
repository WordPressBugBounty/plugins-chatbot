<?php
/**
 * Workflow Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

use WPbot_Automator\Core\Database;
use WPbot_Automator\Engine\Workflow_Runner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Workflow_Actions
 */
class Workflow_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'workflow_actions';
		$this->group = __( 'Workflows', 'wpbot-automator' );
	}

	/**
	 * Execute the action
	 */
	public function execute( $action_data, $trigger_data ) {
		$action_id = isset( $action_data['actionId'] ) ? $action_data['actionId'] : '';

		switch ( $action_id ) {
			case 'call_sub_workflow':
				return $this->call_sub_workflow( $action_data, $trigger_data );
			case 'return_values':
				return $this->return_values( $action_data, $trigger_data );
			default:
				return array( 'success' => false, 'message' => 'Unknown workflow action.' );
		}
	}

	/**
	 * Call a sub-workflow
	 */
	private function call_sub_workflow( $action_data, $trigger_data ) {
		$config      = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$workflow_id = isset( $config['workflow_id'] ) ? absint( $config['workflow_id'] ) : 0;
		$inputs      = isset( $config['inputs'] ) ? $config['inputs'] : array();
		$node_id     = isset( $action_data['node_id'] ) ? $action_data['node_id'] : 'sub_workflow';

		if ( ! $workflow_id ) {
			return array( 'success' => false, 'message' => 'No sub-workflow selected.' );
		}

		global $wpdb;
		$table    = Database::get_workflows_table();
		$workflow = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $workflow_id ), ARRAY_A );

		if ( ! $workflow ) {
			return array( 'success' => false, 'message' => 'Sub-workflow not found.' );
		}

		// Prepare input data for the sub-workflow.
		$sub_trigger_data = array();
		if ( is_array( $inputs ) ) {
			foreach ( $inputs as $pair ) {
				if ( ! empty( $pair['key'] ) ) {
					$sub_trigger_data[ $pair['key'] ] = $this->parse_tokens( $pair['value'], $trigger_data );
				}
			}
		}

		// Execute the sub-workflow.
		$result_data = Workflow_Runner::execute_workflow( $workflow, $sub_trigger_data, 'sub_workflow_trigger' );

		// Return the result data namespaced by the node_id.
		return array(
			'success' => true,
			$node_id  => $result_data,
		);
	}

	/**
	 * Return values from a sub-workflow
	 */
	private function return_values( $action_data, $trigger_data ) {
		$config = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$values = isset( $config['values'] ) ? $config['values'] : array();

		$return_data = array();
		if ( is_array( $values ) ) {
			foreach ( $values as $pair ) {
				if ( ! empty( $pair['key'] ) ) {
					$return_data[ $pair['key'] ] = $this->parse_tokens( $pair['value'], $trigger_data );
				}
			}
		}

		return array(
			'success'       => true,
			'__return_data' => $return_data,
		);
	}
}
