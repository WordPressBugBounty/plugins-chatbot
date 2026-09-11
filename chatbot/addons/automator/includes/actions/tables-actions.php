<?php
/**
 * Tables Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

use WPbot_Automator\Core\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tables_Actions
 *
 * Provides workflow actions that read/write rows in user-created custom tables.
 *
 * Config fields expected in workflow_data:
 *
 *   tables_insert_record:
 *     table_id    — numeric table ID (supports tokens, e.g. {table_id})
 *     record_data — JSON object mapping column slugs to values, e.g.
 *                   {"name":"{user_name}","email":"{user_email}"}
 *
 *   tables_update_record:
 *     table_id    — numeric table ID
 *     row_id      — row ID to update (supports tokens, e.g. {record_id})
 *     record_data — JSON object with columns to update
 */
class Tables_Actions extends Action {

	public function __construct() {
		$this->id    = 'tables';
		$this->group = __( 'Tables', 'wpbot-automator' );
	}

	/**
	 * Dispatch to the correct sub-action.
	 */
	public function execute( $action_data, $trigger_data ) {
		$action_id = isset( $action_data['actionId'] ) ? $action_data['actionId'] : '';

		switch ( $action_id ) {
			case 'tables_insert_record':
				return $this->insert_record( $action_data, $trigger_data );
			case 'tables_update_record':
				return $this->update_record( $action_data, $trigger_data );
			default:
				return false;
		}
	}

	// ── Sub-actions ──────────────────────────────────────────────────────────

	/**
	 * Insert a new record into a custom table.
	 *
	 * Returns array with 'record_id' on success, false on failure.
	 */
	private function insert_record( $action_data, $trigger_data ) {
		global $wpdb;

		$config   = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$table_id = absint( $this->parse_tokens( isset( $config['table_id'] ) ? $config['table_id'] : '', $trigger_data ) );

		if ( ! $table_id ) {
			error_log( 'WPbot Tables_Actions::insert_record — missing table_id.' );
			return false;
		}

		$table = Tables::get_table_meta_by_id( $table_id );
		if ( ! $table ) {
			error_log( 'WPbot Tables_Actions::insert_record — table not found: ' . $table_id );
			return false;
		}

		$raw = ! empty( $config['record_data'] ) ? $config['record_data'] : '{}';
		$raw_json = $this->parse_tokens( $raw, $trigger_data );
		$record   = json_decode( $raw_json, true );

		if ( ! is_array( $record ) ) {
			// Fallback: try to auto-map trigger data keys that match column slugs.
			$record = array();
		}

		// Auto-fill any column that still has no value from trigger data (slug must match).
		foreach ( $table['columns'] as $col ) {
			if ( ! isset( $record[ $col['slug'] ] ) && isset( $trigger_data[ $col['slug'] ] ) ) {
				$record[ $col['slug'] ] = $trigger_data[ $col['slug'] ];
			}
		}

		$clean = Tables::extract_record_data_public( $record, $table['columns'] );
		// Allow inserting with partial data — only require at least one column.
		if ( empty( $clean ) ) {
			error_log( 'WPbot Tables_Actions::insert_record — no column data resolved (record_data empty and no trigger keys matched column slugs).' );
			return false;
		}

		$clean['created_at'] = current_time( 'mysql' );
		$clean['updated_at'] = current_time( 'mysql' );

		$ut = Tables::get_user_table( $table['slug'] );
		$wpdb->insert( $ut, $clean );
		$new_id = (int) $wpdb->insert_id;

		if ( ! $new_id ) {
			error_log( 'WPbot Tables_Actions::insert_record — DB insert failed: ' . $wpdb->last_error );
			return false;
		}

		// Fire table trigger so workflows listening to "new_record" can chain.
		do_action( 'wpbot_table_new_record', $table_id, $table, $clean, $new_id );

		return array( 'record_id' => $new_id, 'table_id' => $table_id );
	}

	/**
	 * Update an existing record in a custom table.
	 *
	 * Returns array with 'success' on success, false on failure.
	 */
	private function update_record( $action_data, $trigger_data ) {
		global $wpdb;

		$config   = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$table_id = absint( $this->parse_tokens( isset( $config['table_id'] ) ? $config['table_id'] : '', $trigger_data ) );
		$row_id   = absint( $this->parse_tokens( isset( $config['row_id'] ) ? $config['row_id'] : '', $trigger_data ) );

		if ( ! $table_id || ! $row_id ) {
			error_log( 'WPbot Tables_Actions::update_record — missing table_id or row_id.' );
			return false;
		}

		$table = Tables::get_table_meta_by_id( $table_id );
		if ( ! $table ) {
			error_log( 'WPbot Tables_Actions::update_record — table not found: ' . $table_id );
			return false;
		}

		$raw = ! empty( $config['record_data'] ) ? $config['record_data'] : '{}';
		$raw_json = $this->parse_tokens( $raw, $trigger_data );
		$record   = json_decode( $raw_json, true );

		if ( ! is_array( $record ) ) {
			$record = array();
		}

		foreach ( $table['columns'] as $col ) {
			if ( ! isset( $record[ $col['slug'] ] ) && isset( $trigger_data[ $col['slug'] ] ) ) {
				$record[ $col['slug'] ] = $trigger_data[ $col['slug'] ];
			}
		}

		$clean = Tables::extract_record_data_public( $record, $table['columns'] );
		if ( empty( $clean ) ) {
			return false;
		}

		$clean['updated_at'] = current_time( 'mysql' );

		$ut         = Tables::get_user_table( $table['slug'] );
		$old_record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$ut}` WHERE id = %d", $row_id ), ARRAY_A );

		$wpdb->update( $ut, $clean, array( 'id' => $row_id ) );

		// Fire per-cell change triggers.
		if ( is_array( $old_record ) ) {
			foreach ( $clean as $col_slug => $new_val ) {
				if ( in_array( $col_slug, array( 'updated_at' ), true ) ) {
					continue;
				}
				$old_val = $old_record[ $col_slug ] ?? null;
				if ( (string) $old_val !== (string) $new_val ) {
					do_action( 'wpbot_table_updated_cell', $table_id, $table, $row_id, $col_slug, $old_val, $new_val );
				}
			}
		}

		do_action( 'wpbot_table_updated_record', $table_id, $table, $clean, $row_id, $old_record );

		return array( 'success' => true, 'record_id' => $row_id );
	}
}
