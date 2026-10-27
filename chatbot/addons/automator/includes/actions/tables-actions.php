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
				return self::fail( 'Unknown Tables action.' );
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
			return self::fail( 'Table ID is required.' );
		}

		$table = Tables::get_table_meta_by_id( $table_id );
		if ( ! $table ) {
			return self::fail( 'Table not found: ' . $table_id );
		}

		$raw = ! empty( $config['record_data'] ) ? $config['record_data'] : '{}';
		$raw_json = $this->parse_tokens( $raw, $trigger_data, true ); // Escape quotes/newlines in values.
		$record   = json_decode( $raw_json, true );
		if ( '' !== trim( $raw_json ) && '{}' !== trim( $raw_json ) && ! is_array( $record ) ) {
			return self::fail( 'Record Data is not valid JSON: ' . json_last_error_msg() );
		}

		if ( ! is_array( $record ) ) {
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
			return self::fail( 'No column values resolved. Check Record Data (JSON) and the column slugs.' );
		}

		$clean['created_at'] = current_time( 'mysql' );
		$clean['updated_at'] = current_time( 'mysql' );

		$ut     = Tables::get_user_table( $table['slug'] );
		$new_id = $wpdb->insert( $ut, $clean ) ? (int) $wpdb->insert_id : 0;

		if ( ! $new_id ) {
			return self::fail( 'Insert failed: ' . $wpdb->last_error );
		}

		// Fire table trigger so workflows listening to "new_record" can chain.
		do_action( 'wpbot_table_new_record', $table_id, $table, $clean, $new_id );

		return array( 'success' => true, 'record_id' => $new_id, 'table_id' => $table_id );
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
			return self::fail( 'Table ID and Row ID are required.' );
		}

		$table = Tables::get_table_meta_by_id( $table_id );
		if ( ! $table ) {
			return self::fail( 'Table not found: ' . $table_id );
		}

		$raw = ! empty( $config['record_data'] ) ? $config['record_data'] : '{}';
		$raw_json = $this->parse_tokens( $raw, $trigger_data, true ); // Escape quotes/newlines in values.
		$record   = json_decode( $raw_json, true );
		if ( '' !== trim( $raw_json ) && '{}' !== trim( $raw_json ) && ! is_array( $record ) ) {
			return self::fail( 'Record Data is not valid JSON: ' . json_last_error_msg() );
		}

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
			return self::fail( 'No column values resolved. Check Record Data (JSON) and the column slugs.' );
		}

		$clean['updated_at'] = current_time( 'mysql' );

		$ut         = Tables::get_user_table( $table['slug'] );
		$old_record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$ut}` WHERE id = %d", $row_id ), ARRAY_A );
		if ( ! $old_record ) {
			return self::fail( 'Record not found: ' . $row_id );
		}

		if ( false === $wpdb->update( $ut, $clean, array( 'id' => $row_id ) ) ) {
			return self::fail( 'Update failed: ' . $wpdb->last_error );
		}

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

	/**
	 * Failure result that stops the branch and shows the reason in the log.
	 */
	private static function fail( $message ) {
		return array( 'success' => false, 'message' => $message );
	}
}
