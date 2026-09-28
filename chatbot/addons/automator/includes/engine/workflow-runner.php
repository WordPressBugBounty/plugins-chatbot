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
	 * Run workflows matching a trigger
	 * 
	 * @param string $trigger_id Full trigger ID (e.g., 'wordpress_core:user_register').
	 * @param array $trigger_data Data from the trigger.
	 */
	public static function run_workflows_for_trigger( $trigger_id, $trigger_data ) {
		// error_log( sprintf( 'WPbot Automator - Workflow_Runner::run_workflows_for_trigger called with trigger data: %s', $trigger_id ) );
		// error_log( sprintf( 'WPbot Automator - Workflow_Runner::run_workflows_for_trigger: %s', $trigger_id ) );
		global $wpdb;
		$table = Database::get_workflows_table();

		// Check total count in table.
		$total_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		//error_log( sprintf( 'WPbot Automator - Total workflows in table: %s', $total_count ) );

		// Extract sub-trigger ID for better matching (e.g., 'wpforms_submit' from 'wpforms:wpforms_submit').
		$trigger_id_str = is_array( $trigger_id ) ? wp_json_encode( $trigger_id ) : (string) $trigger_id;
		$sub_id         = ( strpos( $trigger_id_str, ':' ) !== false ) ? explode( ':', $trigger_id_str )[1] : $trigger_id_str;

		// Force string conversion and ensure scalar before passing to prepare.
		$trigger_id_param = is_scalar( $trigger_id_str ) ? strval( $trigger_id_str ) : wp_json_encode( $trigger_id_str );
		$sub_id_param     = is_scalar( $sub_id ) ? strval( $sub_id ) : wp_json_encode( $sub_id );

		// // error_log( sprintf( 'WPbot Automator - Querying workflows for: %s and %s in table %s', $trigger_id_param, $sub_id_param, $table ) );

		$workflows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'active' AND (workflow_data LIKE %s OR workflow_data LIKE %s)",
				'%' . $wpdb->esc_like( $trigger_id_param ) . '%',
				'%' . $wpdb->esc_like( $sub_id_param ) . '%'
			),
			ARRAY_A
		);

		// // error_log( sprintf( 'WPbot Automator - Found %d matching workflows', count( $workflows ) ) );

		if ( empty( $workflows ) ) {
			return;
		}

		foreach ( $workflows as $workflow ) {
			self::execute_workflow( $workflow, $trigger_data, $trigger_id );
		}
	}

	/**
	 * Execute a single workflow
	 * 
	 * @param array $workflow Workflow row from DB.
	 * @param array $trigger_data Data from the trigger.
	 * @param string $trigger_id The ID of the trigger that fired.
	 */
	public static function execute_workflow( $workflow, $trigger_data, $trigger_id = '' ) {
		// error_log( 'WPbot Automator - execute_workflow starting for ID: ' . ( $workflow['id'] ?? 'unknown' ) );
		$data = json_decode( $workflow['workflow_data'], true );
		if ( ! $data || empty( $data['nodes'] ) ) {
			return $trigger_data;
		}

		$nodes       = $data['nodes'];
		$connections = isset( $data['connections'] ) ? $data['connections'] : array();

		// Find starting node (trigger) that matches the trigger that fired.
		$trigger_node = null;
		foreach ( $nodes as $node ) {
			if ( 'trigger' === $node['type'] ) {
				$node_action_id = isset( $node['data']['actionId'] ) ? $node['data']['actionId'] : '';
				$node_app_id    = isset( $node['appData']['id'] ) ? $node['appData']['id'] : '';
				
				// Try to match by full ID or action ID.
				if ( $trigger_id ) {
					// Special handling for Webhooks: if target_node_id is provided, it MUST match the node ID.
					if ( isset( $trigger_data['target_node_id'] ) ) {
						if ( $node['id'] === $trigger_data['target_node_id'] ) {
							$trigger_node = $node;
							break;
						}
						continue; // Skip other triggers in this workflow if we have a specific target.
					}

					// // error_log( sprintf( 'WPbot Automator - Comparing node: ActionID=%s, AppID=%s with TriggerID=%s', $node_action_id, $node_app_id, $trigger_id ) );
					if ( $node_action_id === $trigger_id || ( $node_app_id . ':' . $node_action_id ) === $trigger_id ) {
						$trigger_node = $node;
						break;
					}
					// Partial match for sub-id.
					$sub_id = strpos( $trigger_id, ':' ) !== false ? explode( ':', $trigger_id )[1] : $trigger_id;
					if ( $node_action_id === $sub_id ) {
						// // error_log( 'WPbot Automator - Matched by sub_id: ' . $sub_id );
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
			// // error_log( sprintf( 'WPbot Automator - Workflow %s: No matching trigger node found for %s', $workflow['id'], $trigger_id ) );
			// // error_log( 'WPbot Automator - Nodes in workflow: ' . wp_json_encode( $nodes ) );
			return $trigger_data;
		}

		// Filter by trigger configuration (e.g., Specific Product).
		if ( ! empty( $trigger_node['data']['config'] ) ) {
			$config = $trigger_node['data']['config'];
			
			// Specific Product filter for WooCommerce.
			if ( ! empty( $config['product_id'] ) ) {
				$selected_product_id = (int) $config['product_id'];
				$order_product_ids   = isset( $trigger_data['product_ids'] ) ? array_map( 'intval', $trigger_data['product_ids'] ) : array();
				
				if ( ! in_array( $selected_product_id, $order_product_ids, true ) ) {
					// // error_log( sprintf( 'WPbot Automator - Workflow %s: Product ID %d not found in order products.', $workflow['id'], $selected_product_id ) );
					return $trigger_data;
				}
			}

			// WooCommerce order status filter: only proceed when new_status matches.
			if ( ! empty( $config['new_status'] ) ) {
				$required_status = sanitize_key( $config['new_status'] );
				$actual_status   = isset( $trigger_data['new_status'] ) ? sanitize_key( $trigger_data['new_status'] ) : '';
				if ( $required_status !== $actual_status ) {
					// error_log( sprintf( 'WPbot Automator - Workflow %s: Skipped — new_status "%s" does not match required "%s".', $workflow['id'], $actual_status, $required_status ) );
					return $trigger_data;
				}
			}
		}


		// // error_log( sprintf( 'WPbot Automator - Workflow %s: Starting execution from node %s', $workflow['id'], $trigger_node['id'] ) );

		// ── Inject nested app_id.sub_id into trigger_data for token parsing ──
		$trigger_app_id = isset( $trigger_node['data']['appId'] ) ? sanitize_key( $trigger_node['data']['appId'] ) : ( strpos( (string) $trigger_id, ':' ) !== false ? explode( ':', (string) $trigger_id )[0] : $trigger_id );
		$trigger_sub_id = isset( $trigger_node['data']['actionId'] ) ? sanitize_key( $trigger_node['data']['actionId'] ) : ( strpos( (string) $trigger_id, ':' ) !== false ? explode( ':', (string) $trigger_id )[1] : $trigger_id );

		if ( ! empty( $trigger_app_id ) && ! empty( $trigger_sub_id ) ) {
			if ( ! isset( $trigger_data[ $trigger_app_id ] ) ) {
				$trigger_data[ $trigger_app_id ] = array();
			}
			$temp_data = $trigger_data; // Avoid infinite recursion
			unset( $temp_data[ $trigger_app_id ] );
			$trigger_data[ $trigger_app_id ][ $trigger_sub_id ] = $temp_data;
		}

		// Find following actions.
		$current_node_id = $trigger_node['id'];
		$executed_nodes  = array( $current_node_id );
		
		return self::run_next_nodes( $current_node_id, $nodes, $connections, $trigger_data, $executed_nodes, $workflow['id'], $trigger_id );
	}

	/**
	 * Recursively run next nodes in the flow
	 */
	private static function run_next_nodes( $current_node_id, $nodes, $connections, $trigger_data, &$executed_nodes, $workflow_id = 0, $trigger_id = '' ) {
	//	 error_log( sprintf( 'WPbot Automator - $current_node_id %s', $current_node_id ) );
		// Find connections originating from current node.
		foreach ( $connections as $connection ) {
			if ( isset( $connection['from'] ) && $connection['from'] === $current_node_id ) {
				$target_node_id = $connection['to'];

				// Prevent infinite loops.
				if ( in_array( $target_node_id, $executed_nodes ) ) {
					continue;
				}

				// Find target node.
				$target_node = null;
				foreach ( $nodes as $node ) {
					if ( $node['id'] === $target_node_id ) {
						$target_node = $node;
						break;
					}
				}

				if ( ! $target_node || ( 'action' !== $target_node['type'] && 'trigger' !== $target_node['type'] ) ) {
					continue;
				}

				$app_id = isset( $target_node['appData']['id'] ) ? $target_node['appData']['id'] : '';
				if ( ! $app_id && strpos( $target_node_id, '-' ) !== false ) {
					$app_id = explode( '-', $target_node_id )[0];
				}

				// // error_log( sprintf( 'WPbot Automator - Workflow %s: Attempting to run node %s (App: %s)', $workflow_id, $target_node_id, $app_id ) );

				// ── ITERATOR HANDLING ────────────────────────────────────────
				if ( 'iterator' === $app_id ) {
					$iterator_node_id = $target_node_id;
					$executed_nodes[] = $iterator_node_id;

					self::log_execution( $workflow_id, $trigger_id, $app_id, array( 'success' => true, 'message' => 'Iterator started.' ) );

					// Get array key from config.
					$cfg       = isset( $target_node['data']['config'] ) ? $target_node['data']['config'] : array();
					$array_key = isset( $cfg['array_key'] ) ? trim( $cfg['array_key'] ) : 'items';
					$items     = isset( $trigger_data[ $array_key ] ) ? $trigger_data[ $array_key ] : array();

					// Collect all nodes between iterator and iterator_end.
					$loop_node_ids  = array();
					$iterator_end_id = null;
					self::collect_loop_nodes( $iterator_node_id, $nodes, $connections, $loop_node_ids, $iterator_end_id );

					// Loop through each item.
					if ( ! empty( $items ) && is_array( $items ) ) {
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
					}

					// Continue from iterator_end node.
					if ( $iterator_end_id ) {
						$executed_nodes[] = $iterator_end_id;
						$trigger_data = self::run_next_nodes( $iterator_end_id, $nodes, $connections, $trigger_data, $executed_nodes, $workflow_id, $trigger_id );
					}

					return $trigger_data; // Don't continue with the standard path inside the loop.
				}

				// ── ITERATOR_END: skip (handled inside loop) ─────────────────
				if ( 'iterator_end' === $app_id ) {
					continue;
				}

				// ── STANDARD ACTION ──────────────────────────────────────────
				$action = Registry::get_action( $app_id );

				if ( $action ) {
					$node_data            = isset( $target_node['data'] ) ? $target_node['data'] : array();
					$node_data['node_id'] = $target_node_id;

					// ── Build downstream context for Delay (and similar) actions ──
					// Collect every node reachable from this node so the delay action
					// knows what to resume with.
					$downstream_nodes = self::collect_downstream_nodes( $target_node_id, $nodes, $connections );
					$node_data['__workflow_context'] = array(
						'workflow_id'     => $workflow_id,
						'trigger_id'      => $trigger_id,
						'remaining_nodes' => $downstream_nodes,
						'connections'     => $connections,
					);

					$result = $action->execute( $node_data, $trigger_data );

					// ── Halt flag: Delay action signals us to stop this branch ──
					if ( is_array( $result ) && ! empty( $result['__halt'] ) ) {
						self::log_execution( $workflow_id, $trigger_id, $app_id, $result );
						return $trigger_data; // Stop synchronous execution; WP Cron resumes later.
					}

					if ( is_array( $result ) && isset( $result['__return_data'] ) ) {
						return $result['__return_data']; // Immediate return from sub-workflow
					}

					if ( is_array( $result ) && isset( $result['success'] ) && $result['success'] ) {
							$trigger_data = array_merge( $trigger_data, $result );

							// Also store every result key prefixed with the node ID so that
							// downstream token references like {node-action-1.faq_title} resolve.
							foreach ( $result as $rkey => $rval ) {
								if ( is_string( $rval ) || is_numeric( $rval ) || is_bool( $rval ) ) {
									$trigger_data[ $target_node_id . '.' . $rkey ] = (string) $rval;
								}
							}
						}

					self::log_execution( $workflow_id, $trigger_id, $app_id, $result );

					if ( is_array( $result ) && isset( $result['success'] ) && ! $result['success'] ) {
						//error_log( sprintf( 'WPbot Automator - Workflow %s: Stopping branch execution because node %s failed.', $workflow_id, $target_node_id ) );
						continue; // Stop this branch.
					}

					$executed_nodes[] = $target_node_id;
					$trigger_data = self::run_next_nodes( $target_node_id, $nodes, $connections, $trigger_data, $executed_nodes, $workflow_id, $trigger_id );
				}
			}
		}
		return $trigger_data;
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

					if ( in_array( $next_id, $visited ) ) {
						continue;
					}
					$visited[] = $next_id;

					// Find node.
					$next_node = null;
					foreach ( $nodes as $n ) {
						if ( $n['id'] === $next_id ) {
							$next_node = $n;
							break;
						}
					}

					if ( ! $next_node ) {
						continue;
					}

					$next_app_id = isset( $next_node['appData']['id'] ) ? $next_node['appData']['id'] : '';

					if ( 'iterator_end' === $next_app_id ) {
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
		if ( in_array( $node_id, $loop_executed ) ) {
			return;
		}

		$app_id = self::get_node_app_id( $node_id, $nodes );
		if ( 'iterator_end' === $app_id ) {
			return;
		}

		$loop_executed[] = $node_id;
		$action          = Registry::get_action( $app_id );

		if ( $action ) {
			$target_node = null;
			foreach ( $nodes as $n ) {
				if ( $n['id'] === $node_id ) {
					$target_node = $n;
					break;
				}
			}
			$node_data = isset( $target_node['data'] ) ? $target_node['data'] : array();
			$result    = $action->execute( $node_data, $loop_data );

			if ( is_array( $result ) && isset( $result['success'] ) && $result['success'] ) {
				$loop_data = array_merge( $loop_data, $result );
			}

			self::log_execution( $workflow_id, $trigger_id, $app_id, $result );

			if ( is_array( $result ) && isset( $result['success'] ) && ! $result['success'] ) {
				//error_log( sprintf( 'WPbot Automator - Workflow %s: Stopping loop branch execution because node %s failed.', $workflow_id, $node_id ) );
				return; // Stop this loop iteration's branch.
			}
		}

		// Continue to next loop body node.
		foreach ( $connections as $connection ) {
			if ( isset( $connection['from'] ) && $connection['from'] === $node_id ) {
				$next_id = $connection['to'];
				if ( 'iterator_end' === self::get_node_app_id( $next_id, $nodes ) ) {
					return; // Reached end of loop body.
				}
				self::run_loop_body_node( $next_id, $loop_node_ids, $iterator_end_id, $nodes, $connections, $loop_data, $loop_executed, $workflow_id, $trigger_id );
			}
		}
	}

	/**
	 * Helper: get the app ID for a given node ID.
	 */
	private static function get_node_app_id( $node_id, $nodes ) {
		foreach ( $nodes as $node ) {
			if ( $node['id'] === $node_id ) {
				$app_id = isset( $node['appData']['id'] ) ? $node['appData']['id'] : '';
				if ( ! $app_id && strpos( $node_id, '-' ) !== false ) {
					$app_id = explode( '-', $node_id )[0];
				}
				return $app_id;
			}
		}
		return '';
	}

	/**
	 * Collect all downstream node objects reachable from $start_node_id via connections.
	 *
	 * Used by the Delay action to persist what needs to be resumed after the
	 * delay fires. Returns an ordered array of node objects (not just IDs).
	 *
	 * @param string $start_node_id  The node immediately *after* the delay node.
	 * @param array  $nodes          All nodes in the workflow.
	 * @param array  $connections    All connections in the workflow.
	 * @return array  Ordered list of node objects downstream of $start_node_id.
	 */
	private static function collect_downstream_nodes( $start_node_id, $nodes, $connections ) {
		$visited      = array();
		$queue        = array();
		$result_nodes = array();

		// Seed: find immediate successors of the start node.
		foreach ( $connections as $conn ) {
			if ( isset( $conn['from'] ) && $conn['from'] === $start_node_id ) {
				if ( ! in_array( $conn['to'], $visited, true ) ) {
					$queue[]   = $conn['to'];
					$visited[] = $conn['to'];
				}
			}
		}

		while ( ! empty( $queue ) ) {
			$current_id = array_shift( $queue );

			// Find the node object.
			foreach ( $nodes as $node ) {
				if ( $node['id'] === $current_id ) {
					$result_nodes[] = $node;
					break;
				}
			}

			// Traverse outward.
			foreach ( $connections as $conn ) {
				if ( isset( $conn['from'] ) && $conn['from'] === $current_id ) {
					if ( ! in_array( $conn['to'], $visited, true ) ) {
						$queue[]   = $conn['to'];
						$visited[] = $conn['to'];
					}
				}
			}
		}

		return $result_nodes;
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
			$error_message = isset( $result['message'] ) ? $result['message'] : '';
			$log_data      = wp_json_encode( $result );
		} elseif ( false === $result ) {
			$status        = 'failed';
			$error_message = 'Action returned false';
		} else {
			$log_data = (string) $result;
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

	public static function debug_dump_all_workflows() {
		global $wpdb;
		$table = \WPbot_Automator\Core\Database::get_workflows_table();
		$workflows = $wpdb->get_results( "SELECT * FROM {$table}" );
		//error_log( 'WPbot Automator - DEBUG DUMP ALL WORKFLOWS' );
		foreach ( $workflows as $workflow ) {
			//error_log( sprintf( 'Workflow ID: %d, Name: %s, Status: %s', $workflow->id, $workflow->name, $workflow->status ) );
			//error_log( 'Data: ' . $workflow->workflow_data );
		}
	}
}
