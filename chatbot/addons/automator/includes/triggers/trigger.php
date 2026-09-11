<?php
/**
 * Abstract Trigger Class
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Class Trigger
 */
abstract class Trigger {

	/**
	 * Trigger ID
	 * 
	 * @var string
	 */
	protected $id;

	/**
	 * Trigger Title
	 * 
	 * @var string
	 */
	protected $title;

	/**
	 * Trigger Group (e.g., 'WordPress', 'WooCommerce')
	 * 
	 * @var string
	 */
	protected $group;

	/**
	 * Get Trigger ID
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get Trigger Title
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * Get Trigger Group
	 */
	public function get_group() {
		return $this->group;
	}

	/**
	 * Register the trigger (hooks)
	 */
	abstract public function register();

	/**
	 * Process the trigger execution
	 * 
	 * @param array $data Data passed from the hook.
	 */
	protected function run( $data = array() ) {
		// This will call the Workflow Engine to find and run matching workflows.
		$full_trigger_id = $this->id;
		if ( isset( $data['sub_id'] ) ) {
			$full_trigger_id .= ':' . $data['sub_id'];
		}
		
		\WPbot_Automator\Engine\Workflow_Runner::run_workflows_for_trigger( $full_trigger_id, $data['data'] );
	}
}
