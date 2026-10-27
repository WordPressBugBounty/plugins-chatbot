<?php
/**
 * Index of trigger ID → active workflow IDs.
 *
 * Replaces a LIKE scan over every workflow's JSON on each event (login, comment, form submit).
 * Rebuilt whenever workflows are saved, imported, deleted or migrated.
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Trigger_Index
 */
class Trigger_Index {

	const OPTION = 'wpbot_automator_trigger_index';

	/**
	 * Workflow IDs whose trigger node uses one of the given trigger IDs.
	 *
	 * @param string[] $trigger_ids e.g. array( 'wpforms:wpforms_submit', 'wpforms_submit' ).
	 * @return int[]
	 */
	public static function workflow_ids_for( array $trigger_ids ) {
		$index = get_option( self::OPTION, false );
		if ( ! is_array( $index ) ) {
			$index = self::rebuild();
		}

		$ids = array();
		foreach ( $trigger_ids as $trigger_id ) {
			if ( isset( $index[ $trigger_id ] ) ) {
				$ids = array_merge( $ids, (array) $index[ $trigger_id ] );
			}
		}
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Rebuild the index from active workflows.
	 *
	 * @return array
	 */
	public static function rebuild() {
		global $wpdb;
		$table = Database::get_workflows_table();
		$rows  = $wpdb->get_results( "SELECT id, workflow_data FROM {$table} WHERE status = 'active'", ARRAY_A );

		$index = array();
		foreach ( (array) $rows as $row ) {
			$data = json_decode( $row['workflow_data'], true );
			foreach ( isset( $data['nodes'] ) && is_array( $data['nodes'] ) ? $data['nodes'] : array() as $node ) {
				if ( 'trigger' !== ( $node['type'] ?? '' ) || empty( $node['data']['actionId'] ) || ! is_string( $node['data']['actionId'] ) ) {
					continue;
				}
				$action_id = $node['data']['actionId'];
				$app_id    = isset( $node['data']['appId'] ) && is_string( $node['data']['appId'] ) ? $node['data']['appId'] : '';

				$index[ $action_id ][] = (int) $row['id'];
				if ( '' !== $app_id ) {
					$index[ $app_id . ':' . $action_id ][] = (int) $row['id'];
				}
			}
		}
		foreach ( $index as $key => $ids ) {
			$index[ $key ] = array_values( array_unique( $ids ) );
		}

		update_option( self::OPTION, $index, true );
		return $index;
	}
}
