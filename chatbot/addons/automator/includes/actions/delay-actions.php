<?php
/**
 * Delay / Wait Action
 *
 * Pauses workflow execution for a configurable duration using WordPress Cron
 * (wp_schedule_single_event). The remaining nodes and trigger data are
 * persisted to the wpbot_automator_scheduled_tasks DB table so they can be
 * resumed accurately after the delay fires.
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
 * Class Delay_Actions
 */
class Delay_Actions extends Action {

	/**
	 * WP Cron hook name used to resume deferred workflows.
	 */
	const CRON_HOOK = 'wpbot_automator_resume_delayed_workflow';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'delay';
		$this->group = __( 'Flow Control', 'wpbot-automator' );

		// Register the cron callback once per request.
		add_action( self::CRON_HOOK, array( $this, 'resume_workflow' ), 10, 1 );
	}

	/**
	 * Execute the action.
	 *
	 * When the workflow runner hits this node it will call execute() with
	 * extra context that the runner passes via $action_data['__context'].
	 *
	 * Because the runner doesn't natively support "pausing", we intercept
	 * execution here by:
	 *  1. Resolving the delay in seconds.
	 *  2. Collecting the remaining nodes (everything downstream of this node).
	 *  3. Persisting the checkpoint to the DB.
	 *  4. Scheduling a single WP Cron event.
	 *  5. Returning a special '__halt' flag so the runner stops this branch.
	 *
	 * @param array $action_data Configuration for this action from the workflow.
	 * @param array $trigger_data Data passed from the trigger.
	 * @return array
	 */
	public function execute( $action_data, $trigger_data ) {
		$action_id = isset( $action_data['actionId'] ) ? $action_data['actionId'] : '';

		if ( 'delay_execution' !== $action_id ) {
			return array( 'success' => false, 'message' => 'Unknown delay action.' );
		}

		$cfg          = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$delay_amount = isset( $cfg['delay_amount'] ) ? absint( $cfg['delay_amount'] ) : 0;
		$delay_unit   = isset( $cfg['delay_unit'] ) ? sanitize_key( $cfg['delay_unit'] ) : 'minutes';

		// Convert to seconds.
		$multiplier = array(
			'minutes' => MINUTE_IN_SECONDS,
			'hours'   => HOUR_IN_SECONDS,
			'days'    => DAY_IN_SECONDS,
		);
		$delay_seconds = $delay_amount * ( isset( $multiplier[ $delay_unit ] ) ? $multiplier[ $delay_unit ] : MINUTE_IN_SECONDS );

		if ( $delay_seconds <= 0 ) {
			return array( 'success' => false, 'message' => 'Delay: invalid delay amount or unit.' );
		}

		// The runner passes where we are, so the cron job can continue after this node.
		$context = isset( $action_data['__workflow_context'] ) ? $action_data['__workflow_context'] : array();

		$workflow_id = isset( $context['workflow_id'] ) ? absint( $context['workflow_id'] ) : 0;
		$trigger_id  = isset( $context['trigger_id'] ) ? $context['trigger_id'] : '';
		$node_id     = isset( $context['node_id'] ) ? (string) $context['node_id'] : ( isset( $action_data['node_id'] ) ? (string) $action_data['node_id'] : '' );

		if ( ! $workflow_id || '' === $node_id ) {
			return array( 'success' => false, 'message' => 'Delay: workflow context missing; cannot schedule the remaining steps.' );
		}

		// Persist the checkpoint.
		$scheduled_at = gmdate( 'Y-m-d H:i:s', time() + $delay_seconds );
		$task_id      = Database::add_scheduled_task(
			array(
				'workflow_id'     => $workflow_id,
				'trigger_id'      => (string) $trigger_id,
				'trigger_data'    => $trigger_data,
				'remaining_nodes' => array( 'resume_after' => $node_id ),
				'connections'     => array(),
				'scheduled_at'    => $scheduled_at,
			)
		);

		if ( ! $task_id ) {
			return array( 'success' => false, 'message' => 'Delay: failed to persist scheduled task to DB.' );
		}

		// Schedule the WP Cron event.
		$scheduled = wp_schedule_single_event(
			time() + $delay_seconds,
			self::CRON_HOOK,
			array( $task_id )
		);

		if ( false === $scheduled ) {
			// Cron scheduling failed — clean up and bail.
			Database::delete_scheduled_task( $task_id );
			return array( 'success' => false, 'message' => 'Delay: wp_schedule_single_event() failed.' );
		}

		$human_delay = $delay_amount . ' ' . $delay_unit;

		// error_log( sprintf(
		// 	'WPbot Automator - Delay: Workflow %d paused for %s. Task ID: %d. Resumes at %s.',
		// 	$workflow_id,
		// 	$human_delay,
		// 	$task_id,
		// 	$scheduled_at
		// ) );

		// Signal the workflow runner to halt further synchronous execution on
		// this branch by returning the special __halt key.
		return array(
			'success'   => true,
			'__halt'    => true,
			'task_id'   => $task_id,
			'message'   => sprintf( 'Delay: workflow paused for %s. Will resume at %s.', $human_delay, $scheduled_at ),
		);
	}

	/**
	 * WP Cron callback: resume the deferred workflow.
	 *
	 * Called by WordPress Cron after the delay has elapsed.
	 *
	 * @param int $task_id Scheduled task row ID.
	 */
	public function resume_workflow( $task_id ) {
		$task = Database::get_scheduled_task( absint( $task_id ) );

		if ( ! $task ) {
			//error_log( sprintf( 'WPbot Automator - Delay: resume_workflow called with unknown task ID %d.', $task_id ) );
			return;
		}

		if ( 'pending' !== $task['status'] ) {
			// Already processed (guard against duplicate cron fires).
			return;
		}

		// Mark as completed immediately to prevent double execution.
		Database::complete_scheduled_task( $task_id );

		$workflow_id     = (int) $task['workflow_id'];
		$trigger_id      = (string) $task['trigger_id'];
		$trigger_data    = is_array( $task['trigger_data'] ) ? $task['trigger_data'] : array();
		$remaining_nodes = is_array( $task['remaining_nodes'] ) ? $task['remaining_nodes'] : array();

		if ( ! empty( $remaining_nodes['resume_after'] ) ) {
			$after_node_id = (string) $remaining_nodes['resume_after'];
		} else {
			// Tasks queued by older versions stored the downstream nodes; the delay node is
			// the source of the connection that leads to the first of them.
			$after_node_id = '';
			$first_id      = isset( $remaining_nodes[0]['id'] ) ? $remaining_nodes[0]['id'] : '';
			$connections   = is_array( $task['connections'] ) ? $task['connections'] : array();
			foreach ( $connections as $conn ) {
				if ( $first_id && isset( $conn['to'], $conn['from'] ) && $conn['to'] === $first_id ) {
					$after_node_id = (string) $conn['from'];
					break;
				}
			}
		}

		if ( '' === $after_node_id ) {
			Workflow_Runner::log_execution( $workflow_id, $trigger_id, 'delay', array( 'success' => false, 'message' => 'Delay: could not find where to resume.' ) );
			return;
		}

		Workflow_Runner::resume_from_node( $workflow_id, $after_node_id, $trigger_data, $trigger_id );
	}
}
