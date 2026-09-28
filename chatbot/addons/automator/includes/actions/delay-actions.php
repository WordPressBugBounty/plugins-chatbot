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

		// Pull the workflow context the runner embeds when it calls us.
		// This is set via $action_data['__workflow_context'] by the modified runner.
		$context = isset( $action_data['__workflow_context'] ) ? $action_data['__workflow_context'] : array();

		$workflow_id     = isset( $context['workflow_id'] )     ? absint( $context['workflow_id'] )     : 0;
		$trigger_id      = isset( $context['trigger_id'] )      ? $context['trigger_id']                : '';
		$remaining_nodes = isset( $context['remaining_nodes'] ) ? $context['remaining_nodes']            : array();
		$connections     = isset( $context['connections'] )     ? $context['connections']                : array();

		if ( empty( $remaining_nodes ) || ! $workflow_id ) {
			// Nothing to schedule — just return success (no downstream nodes).
			return array(
				'success' => true,
				'message' => sprintf(
					'Delay: %d %s queued but no downstream nodes found; nothing to schedule.',
					$delay_amount,
					$delay_unit
				),
			);
		}

		// Persist the checkpoint.
		$scheduled_at = gmdate( 'Y-m-d H:i:s', time() + $delay_seconds );
		$task_id      = Database::add_scheduled_task(
			array(
				'workflow_id'     => $workflow_id,
				'trigger_id'      => (string) $trigger_id,
				'trigger_data'    => $trigger_data,
				'remaining_nodes' => $remaining_nodes,
				'connections'     => $connections,
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
		$trigger_id      = $task['trigger_id'];
		$trigger_data    = is_array( $task['trigger_data'] )    ? $task['trigger_data']    : array();
		$remaining_nodes = is_array( $task['remaining_nodes'] ) ? $task['remaining_nodes'] : array();
		$connections     = is_array( $task['connections'] )     ? $task['connections']      : array();

		// error_log( sprintf(
		// 	'WPbot Automator - Delay: Resuming workflow %d from task %d. Remaining nodes: %d.',
		// 	$workflow_id,
		// 	$task_id,
		// 	count( $remaining_nodes )
		// ) );

		if ( empty( $remaining_nodes ) ) {
			return;
		}

		// Re-use the runner to execute remaining nodes directly.
		// We grab the first remaining node id and pass the rest as the full set.
		$first_node_id  = $remaining_nodes[0];
		$executed_nodes = array(); // Fresh slate — all remaining nodes are eligible.

		// The runner's run_next_nodes is private, so we build a minimal fake
		// workflow structure and call execute_workflow with only the remaining nodes.
		$fake_workflow = array(
			'id'            => $workflow_id,
			'workflow_data' => wp_json_encode(
				array(
					'nodes'       => $remaining_nodes,
					'connections' => $connections,
				)
			),
		);

		// The first remaining_nodes entry is the node immediately after the
		// delay node. We mark it as a "trigger" type with a wildcard so the
		// runner's trigger-matching logic accepts it as the starting point.
		$fake_workflow['workflow_data'] = wp_json_encode(
			array(
				'nodes'       => array_map(
					function( $node, $idx ) {
						if ( 0 === $idx ) {
							// Re-type as trigger so execute_workflow starts here.
							$node['type'] = 'trigger';
						}
						return $node;
					},
					$remaining_nodes,
					array_keys( $remaining_nodes )
				),
				'connections' => $connections,
			)
		);

		Workflow_Runner::execute_workflow( $fake_workflow, $trigger_data, $first_node_id );

		// error_log( sprintf(
		// 	'WPbot Automator - Delay: Workflow %d resumed and completed from task %d.',
		// 	$workflow_id,
		// 	$task_id
		// ) );
	}
}
