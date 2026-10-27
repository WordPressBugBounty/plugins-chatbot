<?php
/**
 * Workflow Runner Engine
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Engine;

use WPbot_Automator\Core\Database;
use WPbot_Automator\Core\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Workflow_Runner
 */
class Workflow_Runner {

	/**
	 * Maximum nesting of workflows started from inside other workflows
	 * (sub-workflows, or actions that fire triggers such as "insert table record").
	 */
	const MAX_DEPTH = 5;

	/**
	 * Workflow IDs currently executing in this request, keyed by ID.
	 *
	 * @var array
	 */
	private static $running = array();

	/**
	 * Run workflows matching a trigger
	 *
	 * @param string $trigger_id Full trigger ID (e.g., 'wordpress_core:user_register').
	 * @param array $trigger_data Data from the trigger.
	 * @return int Number of workflows whose trigger matched and started.
	 */
	public static function run_workflows_for_trigger( $trigger_id, $trigger_data ) {
		global $wpdb;
		$table = Database::get_workflows_table();

		// Extract sub-trigger ID for better matching (e.g., 'wpforms_submit' from 'wpforms:wpforms_submit').
		$trigger_id_str = is_array( $trigger_id ) ? wp_json_encode( $trigger_id ) : (string) $trigger_id;
		$sub_id         = ( strpos( $trigger_id_str, ':' ) !== false ) ? explode( ':', $trigger_id_str )[1] : $trigger_id_str;

		// Look up candidates in the trigger index: no query at all when nothing listens.
		$ids = self::candidate_ids( $trigger_id_str );
		if ( empty( $ids ) ) {
			return 0;
		}

		$workflows = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE status = 'active' AND id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')',
			ARRAY_A
		);

		if ( empty( $workflows ) ) {
			return 0;
		}

		$started = 0;
		foreach ( $workflows as $workflow ) {
			$matched = false;
			self::execute_workflow( $workflow, $trigger_data, $trigger_id, $matched );
			if ( $matched ) {
				++$started;
			}
		}
		return $started;
	}

	/**
	 * IDs of active workflows that listen to a trigger.
	 *
	 * @param string $trigger_id Full trigger ID (e.g. 'wpforms:wpforms_submit').
	 * @return int[]
	 */
	public static function candidate_ids( $trigger_id ) {
		$trigger_id = (string) $trigger_id;
		$keys       = array( $trigger_id );
		if ( false !== strpos( $trigger_id, ':' ) ) {
			$keys[] = explode( ':', $trigger_id )[1];
		}
		return \WPbot_Automator\Core\Trigger_Index::workflow_ids_for( $keys );
	}

	/**
	 * Execute a single workflow
	 *
	 * @param array  $workflow     Workflow row from DB.
	 * @param array  $trigger_data Data from the trigger.
	 * @param string $trigger_id   The ID of the trigger that fired.
	 * @param bool   $matched      Set to true when a trigger node matched and the workflow ran.
	 */
	public static function execute_workflow( $workflow, $trigger_data, $trigger_id = '', &$matched = null ) {
		$matched = false;
		$data    = json_decode( $workflow['workflow_data'], true );
		if ( ! $data || empty( $data['nodes'] ) ) {
			return $trigger_data;
		}

		$nodes       = $data['nodes'];
		$connections = isset( $data['connections'] ) ? $data['connections'] : array();
		$trigger_id  = (string) $trigger_id;

		// Find starting node (trigger) that matches the trigger that fired.
		$trigger_node = null;
		foreach ( $nodes as $node ) {
			if ( 'trigger' === $node['type'] ) {
				$node_action_id = isset( $node['data']['actionId'] ) ? $node['data']['actionId'] : '';
				$node_app_id    = isset( $node['appData']['id'] ) ? $node['appData']['id'] : '';

				// Try to match by full ID or action ID.
				if ( $trigger_id ) {
					// Webhooks: the node ID is the secret in the URL, so it MUST match exactly.
					if ( isset( $trigger_data['target_node_id'] ) ) {
						if ( hash_equals( (string) $node['id'], (string) $trigger_data['target_node_id'] ) ) {
							$trigger_node = $node;
							break;
						}
						continue; // Skip other triggers in this workflow if we have a specific target.
					}

					if ( $node_action_id === $trigger_id || ( $node_app_id . ':' . $node_action_id ) === $trigger_id ) {
						$trigger_node = $node;
						break;
					}
					// Partial match for sub-id.
					$sub_id = strpos( $trigger_id, ':' ) !== false ? explode( ':', $trigger_id )[1] : $trigger_id;
					if ( $node_action_id === $sub_id ) {
						$trigger_node = $node;
						break;
					}
				} else {
					// Fallback to first trigger if no trigger_id provided.
					$trigger_node = $node;
					break;
				}
			}
		}

		if ( ! $trigger_node ) {
			return $trigger_data;
		}

		// Filter by trigger configuration (e.g., Specific Product).
		if ( ! empty( $trigger_node['data']['config'] ) && ! self::trigger_filters_pass( $trigger_node['data']['config'], $trigger_data ) ) {
			return $trigger_data;
		}

		$workflow_id = isset( $workflow['id'] ) ? (int) $workflow['id'] : 0;
		if ( ! self::enter( $workflow_id, $trigger_id ) ) {
			return $trigger_data;
		}
		$matched = true;

		// ── Inject nested app_id.sub_id into trigger_data for token parsing ──
		$trigger_app_id = isset( $trigger_node['data']['appId'] ) ? sanitize_key( $trigger_node['data']['appId'] ) : ( strpos( $trigger_id, ':' ) !== false ? explode( ':', $trigger_id )[0] : $trigger_id );
		$trigger_sub_id = isset( $trigger_node['data']['actionId'] ) ? sanitize_key( $trigger_node['data']['actionId'] ) : ( strpos( $trigger_id, ':' ) !== false ? explode( ':', $trigger_id )[1] : $trigger_id );

		if ( ! empty( $trigger_app_id ) && ! empty( $trigger_sub_id ) ) {
			if ( ! isset( $trigger_data[ $trigger_app_id ] ) || ! is_array( $trigger_data[ $trigger_app_id ] ) ) {
				$trigger_data[ $trigger_app_id ] = array();
			}
			$temp_data = $trigger_data; // Avoid infinite recursion
			unset( $temp_data[ $trigger_app_id ] );
			$trigger_data[ $trigger_app_id ][ $trigger_sub_id ] = $temp_data;
		}

		// Find following actions.
		$current_node_id = $trigger_node['id'];
		$executed_nodes  = array( $current_node_id );

		try {
			return self::run_next_nodes( $current_node_id, $nodes, $connections, $trigger_data, $executed_nodes, $workflow_id, $trigger_id );
		} finally {
			self::leave( $workflow_id );
		}
	}

	/**
	 * Continue a workflow after a node (used by the Delay action when its cron fires).
	 *
	 * Loads the workflow as it is now, so a deleted or deactivated workflow does not resume.
	 *
	 * @param int    $workflow_id   Workflow ID.
	 * @param string $after_node_id Node whose successors should run.
	 * @param array  $trigger_data  Data captured when the workflow paused.
	 * @param string $trigger_id    Original trigger ID (for logs).
	 * @return array|null Final data, or null when the workflow could not resume.
	 */
	public static function resume_from_node( $workflow_id, $after_node_id, $trigger_data, $trigger_id = '' ) {
		global $wpdb;
		$table    = Database::get_workflows_table();
		$workflow = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $workflow_id ) ), ARRAY_A );

		if ( ! $workflow || 'active' !== $workflow['status'] ) {
			self::log_execution( $workflow_id, $trigger_id, 'delay', array( 'success' => false, 'message' => 'Delay: workflow was deleted or deactivated; remaining steps skipped.' ) );
			return null;
		}

		$data = json_decode( $workflow['workflow_data'], true );
		if ( empty( $data['nodes'] ) || null === self::find_node( $after_node_id, $data['nodes'] ) ) {
			self::log_execution( $workflow_id, $trigger_id, 'delay', array( 'success' => false, 'message' => 'Delay: the delay step no longer exists in this workflow; remaining steps skipped.' ) );
			return null;
		}

		if ( ! self::enter( (int) $workflow_id, $trigger_id ) ) {
			return null;
		}

		$executed_nodes = array( $after_node_id );
		try {
			return self::run_next_nodes( $after_node_id, $data['nodes'], isset( $data['connections'] ) ? $data['connections'] : array(), is_array( $trigger_data ) ? $trigger_data : array(), $executed_nodes, (int) $workflow_id, $trigger_id );
		} finally {
			self::leave( (int) $workflow_id );
		}
	}

	/**
	 * Apply the "only when…" settings of a trigger node.
	 *
	 * @param array $config       Trigger node config.
	 * @param array $trigger_data Data from the trigger.
	 * @return bool
	 */
	private static function trigger_filters_pass( $config, $trigger_data ) {
		// Specific Product filter for WooCommerce.
		if ( ! empty( $config['product_id'] ) ) {
			$selected_product_id = (int) $config['product_id'];
			$order_product_ids   = isset( $trigger_data['product_ids'] ) ? array_map( 'intval', (array) $trigger_data['product_ids'] ) : array();
			if ( ! in_array( $selected_product_id, $order_product_ids, true ) ) {
				return false;
			}
		}

		// WooCommerce order status filter: only proceed when new_status matches.
		if ( ! empty( $config['new_status'] ) ) {
			$actual_status = isset( $trigger_data['new_status'] ) ? sanitize_key( $trigger_data['new_status'] ) : '';
			if ( sanitize_key( $config['new_status'] ) !== $actual_status ) {
				return false;
			}
		}

		// "Specific form" filter for form triggers.
		if ( ! empty( $config['form_id'] ) ) {
			$actual_form = isset( $trigger_data['form_id'] ) && is_scalar( $trigger_data['form_id'] ) ? (string) $trigger_data['form_id'] : '';
			if ( (string) $config['form_id'] !== $actual_form ) {
				return false;
			}
		}

		// "Specific table" filter for Tables triggers.
		if ( ! empty( $config['table_id'] ) ) {
			$actual_table = isset( $trigger_data['table_id'] ) && is_scalar( $trigger_data['table_id'] ) ? (int) $trigger_data['table_id'] : 0;
			if ( (int) $config['table_id'] !== $actual_table ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Re-entry and depth guard. Stops a workflow from (directly or indirectly) triggering itself,
	 * e.g. "new table record → insert record" or "post published → update post".
	 */
	private static function enter( $workflow_id, $trigger_id ) {
		if ( isset( self::$running[ $workflow_id ] ) || count( self::$running ) >= self::MAX_DEPTH ) {
			self::log_execution(
				$workflow_id,
				$trigger_id,
				'loop_guard',
				array(
					'success' => false,
					'message' => 'Skipped: this run was started by another running workflow and would loop.',
				)
			);
			return false;
		}
		self::$running[ $workflow_id ] = true;
		return true;
	}

	/**
	 * Release the re-entry guard.
	 */
	private static function leave( $workflow_id ) {
		unset( self::$running[ $workflow_id ] );
	}

	/**
	 * Whether an action result means "stop this branch".
	 */
	private static function is_failure( $result ) {
		return false === $result || ( is_array( $result ) && isset( $result['success'] ) && ! $result['success'] );
	}

	/**
	 * Recursively run next nodes in the flow
	 */
	private static function run_next_nodes( $current_node_id, $nodes, $connections, $trigger_data, &$executed_nodes, $workflow_id = 0, $trigger_id = '' ) {
		// Find connections originating from current node.
		foreach ( $connections as $connection ) {
			if ( isset( $connection['from'] ) && $connection['from'] === $current_node_id ) {
				$target_node_id = $connection['to'];

				// Prevent infinite loops.
				if ( in_array( $target_node_id, $executed_nodes, true ) ) {
					continue;
				}

				$target_node = self::find_node( $target_node_id, $nodes );

				if ( ! $target_node || ( 'action' !== $target_node['type'] && 'trigger' !== $target_node['type'] ) ) {
					continue;
				}

				$app_id = self::get_node_app_id( $target_node_id, $nodes );

				// ── ITERATOR HANDLING ────────────────────────────────────────
				if ( 'iterator' === $app_id ) {
					$iterator_node_id = $target_node_id;
					$executed_nodes[] = $iterator_node_id;

					// Get array key from config.
					$cfg       = isset( $target_node['data']['config'] ) ? $target_node['data']['config'] : array();
					$array_key = isset( $cfg['array_key'] ) ? trim( $cfg['array_key'] ) : 'items';
					$items     = self::resolve_items( $trigger_data, $array_key );

					self::log_execution(
						$workflow_id,
						$trigger_id,
						$app_id,
						empty( $items )
							? array( 'success' => true, 'message' => sprintf( 'Iterator: no items found at "%s"; loop skipped.', $array_key ) )
							: array( 'success' => true, 'message' => sprintf( 'Iterator started with %d item(s).', count( $items ) ) )
					);

					// Collect all nodes between iterator and iterator_end.
					$loop_node_ids   = array();
					$iterator_end_id = null;
					self::collect_loop_nodes( $iterator_node_id, $nodes, $connections, $loop_node_ids, $iterator_end_id );

					foreach ( $items as $index => $item ) {
						// Merge the current item into trigger data so actions can use {item.name} etc.
						$loop_data = $trigger_data;
						if ( is_array( $item ) ) {
							foreach ( $item as $k => $v ) {
								$loop_data[ 'item.' . $k ] = $v;
								$loop_data[ 'item_' . $k ] = $v;
							}
						} else {
							$loop_data['item']       = $item;
							$loop_data['item_value'] = $item;
						}
						$loop_data['item_index'] = $index;

						// Execute loop body nodes in order.
						$loop_executed = array();
						self::run_loop_nodes( $iterator_node_id, $loop_node_ids, $iterator_end_id, $nodes, $connections, $loop_data, $loop_executed, $workflow_id, $trigger_id );
					}

					// Continue from iterator_end node.
					if ( $iterator_end_id ) {
						$executed_nodes[] = $iterator_end_id;
						$trigger_data     = self::run_next_nodes( $iterator_end_id, $nodes, $connections, $trigger_data, $executed_nodes, $workflow_id, $trigger_id );
					}

					continue; // Sibling branches of the current node still run.
				}

				// ── ITERATOR_END: skip (handled inside loop) ─────────────────
				if ( 'iterator_end' === $app_id ) {
					continue;
				}

				// ── STANDARD ACTION ──────────────────────────────────────────
				$result = self::run_action_node( $target_node, $app_id, $trigger_data, $nodes, $connections, $workflow_id, $trigger_id );

				// Halt flag: Delay action paused this branch; WP Cron resumes it later.
				if ( is_array( $result ) && ! empty( $result['__halt'] ) ) {
					continue;
				}

				if ( is_array( $result ) && isset( $result['__return_data'] ) ) {
					return $result['__return_data']; // Immediate return from sub-workflow
				}

				if ( self::is_failure( $result ) ) {
					continue; // Stop this branch.
				}

				$trigger_data     = self::merge_result( $trigger_data, $result, $target_node_id );
				$executed_nodes[] = $target_node_id;
				$trigger_data     = self::run_next_nodes( $target_node_id, $nodes, $connections, $trigger_data, $executed_nodes, $workflow_id, $trigger_id );
			}
		}
		return $trigger_data;
	}

	/**
	 * Execute one action node and log the result.
	 *
	 * @return mixed Action result (array|bool), or a failure array when the app has no backend.
	 */
	private static function run_action_node( $target_node, $app_id, $trigger_data, $nodes, $connections, $workflow_id, $trigger_id ) {
		$action = Registry::get_action( $app_id );

		if ( ! $action ) {
			$result = array(
				'success' => false,
				'message' => sprintf( 'Step "%s" (%s) is not available in this version, so this branch stopped.', isset( $target_node['data']['label'] ) ? $target_node['data']['label'] : $target_node['id'], $app_id ),
			);
			self::log_execution( $workflow_id, $trigger_id, $app_id, $result );
			return $result;
		}

		$node_data            = isset( $target_node['data'] ) ? $target_node['data'] : array();
		$node_data['node_id'] = $target_node['id'];

		// Context for actions that pause (Delay) or need to know where they are.
		$node_data['__workflow_context'] = array(
			'workflow_id' => $workflow_id,
			'trigger_id'  => $trigger_id,
			'node_id'     => $target_node['id'],
		);

		$result = $action->execute( $node_data, $trigger_data );
		self::log_execution( $workflow_id, $trigger_id, $app_id, $result );

		return $result;
	}

	/**
	 * Merge a successful result into the running data, also under "<node id>.<key>"
	 * so downstream tokens like {node-action-1.faq_title} resolve.
	 */
	private static function merge_result( $trigger_data, $result, $node_id ) {
		if ( ! is_array( $result ) ) {
			return $trigger_data;
		}

		// Keys an action marks as node-only don't replace existing top-level data.
		$node_only = isset( $result['__node_only'] ) ? (array) $result['__node_only'] : array();
		unset( $result['__node_only'] );

		$top = $result;
		foreach ( $node_only as $key ) {
			if ( array_key_exists( $key, $trigger_data ) ) {
				unset( $top[ $key ] );
			}
		}
		$trigger_data = array_merge( $trigger_data, $top );
		foreach ( $result as $rkey => $rval ) {
			if ( is_string( $rval ) || is_numeric( $rval ) || is_bool( $rval ) ) {
				$trigger_data[ $node_id . '.' . $rkey ] = (string) $rval;
			}
		}
		return $trigger_data;
	}

	/**
	 * Find the array to loop over: a top-level key, a dot path (order.items), or a JSON string.
	 *
	 * @return array
	 */
	private static function resolve_items( $data, $path ) {
		$value = null;
		if ( '' === $path ) {
			return array();
		}

		if ( array_key_exists( $path, $data ) ) {
			$value = $data[ $path ];
		} else {
			$value = $data;
			foreach ( explode( '.', $path ) as $segment ) {
				if ( is_string( $value ) ) {
					$decoded = json_decode( $value, true );
					$value   = is_array( $decoded ) ? $decoded : null;
				}
				if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
					$value = null;
					break;
				}
				$value = $value[ $segment ];
			}
		}

		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : null;
		}

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Collect all node IDs between an iterator and its matching iterator_end.
	 */
	private static function collect_loop_nodes( $iterator_node_id, $nodes, $connections, &$loop_node_ids, &$iterator_end_id ) {
		$visited = array();
		$queue   = array( $iterator_node_id );

		while ( ! empty( $queue ) ) {
			$current = array_shift( $queue );

			foreach ( $connections as $connection ) {
				if ( isset( $connection['from'] ) && $connection['from'] === $current ) {
					$next_id = $connection['to'];

					if ( in_array( $next_id, $visited, true ) ) {
						continue;
					}
					$visited[] = $next_id;

					if ( ! self::find_node( $next_id, $nodes ) ) {
						continue;
					}

					if ( 'iterator_end' === self::get_node_app_id( $next_id, $nodes ) ) {
						$iterator_end_id = $next_id;
					} else {
						$loop_node_ids[] = $next_id;
						$queue[]         = $next_id;
					}
				}
			}
		}
	}

	/**
	 * Run the nodes inside an iterator loop body.
	 */
	private static function run_loop_nodes( $iterator_node_id, $loop_node_ids, $iterator_end_id, $nodes, $connections, &$loop_data, &$loop_executed, $workflow_id, $trigger_id ) {
		// Find first node after the iterator.
		foreach ( $connections as $connection ) {
			if ( isset( $connection['from'] ) && $connection['from'] === $iterator_node_id ) {
				$first_node_id = $connection['to'];

				if ( 'iterator_end' === self::get_node_app_id( $first_node_id, $nodes ) ) {
					continue;
				}

				self::run_loop_body_node( $first_node_id, $loop_node_ids, $iterator_end_id, $nodes, $connections, $loop_data, $loop_executed, $workflow_id, $trigger_id );
			}
		}
	}

	/**
	 * Run a single node in the loop body and recurse.
	 */
	private static function run_loop_body_node( $node_id, $loop_node_ids, $iterator_end_id, $nodes, $connections, &$loop_data, &$loop_executed, $workflow_id, $trigger_id ) {
		if ( in_array( $node_id, $loop_executed, true ) ) {
			return;
		}

		$app_id = self::get_node_app_id( $node_id, $nodes );
		if ( 'iterator_end' === $app_id ) {
			return;
		}

		$target_node = self::find_node( $node_id, $nodes );
		if ( ! $target_node ) {
			return;
		}

		$loop_executed[] = $node_id;
		$result          = self::run_action_node( $target_node, $app_id, $loop_data, $nodes, $connections, $workflow_id, $trigger_id );

		// A Delay inside the loop schedules the rest of this iteration and stops it here.
		if ( is_array( $result ) && ! empty( $result['__halt'] ) ) {
			return;
		}

		if ( self::is_failure( $result ) ) {
			return; // Stop this loop iteration's branch.
		}

		$loop_data = self::merge_result( $loop_data, $result, $node_id );

		// Continue to next loop body node.
		foreach ( $connections as $connection ) {
			if ( isset( $connection['from'] ) && $connection['from'] === $node_id ) {
				$next_id = $connection['to'];
				if ( 'iterator_end' === self::get_node_app_id( $next_id, $nodes ) ) {
					continue; // End of this path; sibling paths may still run.
				}
				self::run_loop_body_node( $next_id, $loop_node_ids, $iterator_end_id, $nodes, $connections, $loop_data, $loop_executed, $workflow_id, $trigger_id );
			}
		}
	}

	/**
	 * Helper: find a node object by ID.
	 *
	 * @return array|null
	 */
	private static function find_node( $node_id, $nodes ) {
		foreach ( $nodes as $node ) {
			if ( isset( $node['id'] ) && $node['id'] === $node_id ) {
				return $node;
			}
		}
		return null;
	}

	/**
	 * Helper: get the app ID for a given node ID.
	 */
	private static function get_node_app_id( $node_id, $nodes ) {
		$node = self::find_node( $node_id, $nodes );
		if ( ! $node ) {
			return '';
		}
		$app_id = isset( $node['appData']['id'] ) ? $node['appData']['id'] : '';
		if ( ! $app_id && isset( $node['data']['appId'] ) ) {
			$app_id = $node['data']['appId'];
		}
		if ( ! $app_id && strpos( $node_id, '-' ) !== false ) {
			$app_id = explode( '-', $node_id )[0];
		}
		return $app_id;
	}

	/**
	 * Log execution results
	 */
	public static function log_execution( $workflow_id, $trigger_id, $action_id, $result ) {
		$status        = 'success';
		$error_message = '';
		$log_data      = '';

		if ( is_array( $result ) ) {
			if ( isset( $result['success'] ) && ! $result['success'] ) {
				$status = 'failed';
			}
			$error_message = isset( $result['message'] ) && is_scalar( $result['message'] ) ? (string) $result['message'] : '';
			$log_data      = wp_json_encode( $result );
		} elseif ( false === $result ) {
			$status        = 'failed';
			$error_message = 'Action returned false';
		} else {
			$log_data = is_scalar( $result ) ? (string) $result : '';
		}

		Database::add_log( array(
			'workflow_id'   => $workflow_id,
			'trigger_type'  => (string) $trigger_id,
			'action_id'     => (string) $action_id,
			'status'        => $status,
			'log_data'      => $log_data,
			'error_message' => $error_message,
		) );
	}
}
