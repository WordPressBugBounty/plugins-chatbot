<?php
/**
 * CSV Creator Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

use WPbot_Automator\Core\Tables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CSV_Creator_Actions
 *
 * Provides three actions that build CSV files and save them to the WordPress
 * media library.  Each action returns an array merged into $trigger_data so
 * downstream nodes can reference:
 *
 *   {csv_url}           — public URL of the saved CSV file
 *   {csv_attachment_id} — WordPress attachment post ID
 *   {csv_filename}      — filename of the saved CSV file
 */
class CSV_Creator_Actions extends Action {

	public function __construct() {
		$this->id    = 'csv_creator';
		$this->group = __( 'CSV Creator', 'wpbot-automator' );
	}

	public function execute( $action_data, $trigger_data ) {
		$action_id = isset( $action_data['actionId'] ) ? $action_data['actionId'] : '';

		switch ( $action_id ) {
			case 'csv_from_table': return $this->csv_from_table( $action_data, $trigger_data );
			case 'csv_from_json':  return $this->csv_from_json( $action_data, $trigger_data );
			case 'csv_from_array': return $this->csv_from_array( $action_data, $trigger_data );
			default:               return false;
		}
	}

	// ── Sub-actions ──────────────────────────────────────────────────────────

	/**
	 * Export all (or filtered) records from a wpbot Table to CSV.
	 *
	 * Config fields: table_id, filter_col (opt), filter_val (opt), filename (opt).
	 */
	private function csv_from_table( $action_data, $trigger_data ) {
		global $wpdb;

		$config   = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$table_id = absint( $this->parse_tokens( isset( $config['table_id'] ) ? $config['table_id'] : '', $trigger_data ) );

		if ( ! $table_id ) {
			return array( 'success' => false, 'message' => 'CSV Creator: table_id is required.' );
		}

		$table = Tables::get_table_meta_by_id( $table_id );
		if ( ! $table ) {
			return array( 'success' => false, 'message' => 'CSV Creator: table not found (' . $table_id . ').' );
		}

		$ut         = Tables::get_user_table( $table['slug'] );
		$filter_col = sanitize_key( $this->parse_tokens( isset( $config['filter_col'] ) ? $config['filter_col'] : '', $trigger_data ) );
		$filter_val = sanitize_text_field( $this->parse_tokens( isset( $config['filter_val'] ) ? $config['filter_val'] : '', $trigger_data ) );

		$where        = '';
		$query_params = array();
		$valid_slugs  = wp_list_pluck( $table['columns'], 'slug' );

		if ( $filter_col && '' !== $filter_val && in_array( $filter_col, $valid_slugs, true ) ) {
			$where          = 'WHERE `' . esc_sql( $filter_col ) . '` LIKE %s';
			$query_params[] = '%' . $wpdb->esc_like( $filter_val ) . '%';
		}

		$sql     = "SELECT * FROM `{$ut}` {$where} ORDER BY id ASC";
		$records = $query_params
			? $wpdb->get_results( $wpdb->prepare( $sql, ...$query_params ), ARRAY_A )
			: $wpdb->get_results( $sql, ARRAY_A );

		// Build header row from column definitions.
		$headers = array( 'id' );
		foreach ( $table['columns'] as $col ) {
			$headers[] = $col['name'];
		}
		$headers[] = 'created_at';

		// Build CSV rows.
		$csv_rows = array( $headers );
		foreach ( $records ?: array() as $rec ) {
			$row = array( $rec['id'] ?? '' );
			foreach ( $table['columns'] as $col ) {
				$row[] = $rec[ $col['slug'] ] ?? '';
			}
			$row[]      = $rec['created_at'] ?? '';
			$csv_rows[] = $row;
		}

		$raw_filename = isset( $config['filename'] ) ? $this->parse_tokens( $config['filename'], $trigger_data ) : '';
		$filename     = sanitize_file_name( $raw_filename ) ?: ( sanitize_title( $table['name'] ) . '_' . gmdate( 'Y-m-d' ) . '.csv' );

		return $this->save_csv( $csv_rows, $filename );
	}

	/**
	 * Convert a JSON array-of-objects string to CSV.
	 *
	 * Config fields: json_data (supports tokens like {response}), filename (opt).
	 *
	 * Expected input: '[{"col1":"val","col2":"val"}, ...]'
	 */
	private function csv_from_json( $action_data, $trigger_data ) {
		$config    = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$json_raw  = $this->parse_tokens( isset( $config['json_data'] ) ? $config['json_data'] : '', $trigger_data );

		// Strip markdown code-fences in case the data came from an AI action.
		$json_raw = preg_replace( '/^```(?:json)?\s*/i', '', trim( $json_raw ) );
		$json_raw = preg_replace( '/\s*```$/', '', $json_raw );

		$data = json_decode( $json_raw, true );

		if ( ! is_array( $data ) || empty( $data ) ) {
			return array( 'success' => false, 'message' => 'CSV Creator: json_data is not a valid JSON array.' );
		}

		// Flatten any nested objects one level deep.
		$first_item = reset( $data );
		if ( ! is_array( $first_item ) ) {
			// Scalar array — wrap each value.
			$data = array_map( function( $v ) { return array( 'value' => $v ); }, $data );
			$first_item = reset( $data );
		}

		$headers  = array_keys( $first_item );
		$csv_rows = array( $headers );

		foreach ( $data as $row ) {
			$csv_rows[] = array_map( function( $h ) use ( $row ) {
				$v = isset( $row[ $h ] ) ? $row[ $h ] : '';
				return is_array( $v ) ? wp_json_encode( $v ) : (string) $v;
			}, $headers );
		}

		$raw_filename = isset( $config['filename'] ) ? $this->parse_tokens( $config['filename'], $trigger_data ) : '';
		$filename     = sanitize_file_name( $raw_filename ) ?: ( 'export_' . gmdate( 'Y-m-d_His' ) . '.csv' );

		return $this->save_csv( $csv_rows, $filename );
	}

	/**
	 * Convert a JSON array-of-arrays string to CSV.
	 *
	 * Config fields: array_data, headers (comma-separated, opt), filename (opt).
	 *
	 * Expected input: '[["Alice","alice@x.com"],["Bob","bob@x.com"]]'
	 */
	private function csv_from_array( $action_data, $trigger_data ) {
		$config    = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$raw       = $this->parse_tokens( isset( $config['array_data'] ) ? $config['array_data'] : '', $trigger_data );

		// Strip markdown fences.
		$raw = preg_replace( '/^```(?:json)?\s*/i', '', trim( $raw ) );
		$raw = preg_replace( '/\s*```$/', '', $raw );

		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) || empty( $data ) ) {
			return array( 'success' => false, 'message' => 'CSV Creator: array_data is not a valid JSON array.' );
		}

		$csv_rows = array();

		// Optional custom headers.
		$raw_headers = isset( $config['headers'] ) ? $this->parse_tokens( $config['headers'], $trigger_data ) : '';
		if ( ! empty( $raw_headers ) ) {
			$csv_rows[] = array_map( 'trim', explode( ',', $raw_headers ) );
		}

		foreach ( $data as $row ) {
			if ( is_array( $row ) ) {
				$csv_rows[] = array_values( $row );
			} elseif ( is_scalar( $row ) ) {
				$csv_rows[] = array( $row );
			}
		}

		$raw_filename = isset( $config['filename'] ) ? $this->parse_tokens( $config['filename'], $trigger_data ) : '';
		$filename     = sanitize_file_name( $raw_filename ) ?: ( 'export_' . gmdate( 'Y-m-d_His' ) . '.csv' );

		return $this->save_csv( $csv_rows, $filename );
	}

	// ── Shared helper ────────────────────────────────────────────────────────

	/**
	 * Build a CSV string from a 2D array and save it to the media library.
	 *
	 * @param array  $rows     Two-dimensional array; first row is the header.
	 * @param string $filename Target filename including .csv extension.
	 * @return array Action result array with success, csv_url, csv_attachment_id, csv_filename.
	 */
	private function save_csv( array $rows, $filename ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Render CSV content in memory.
		ob_start();
		$fh = fopen( 'php://output', 'w' );
		foreach ( $rows as $row ) {
			fputcsv( $fh, $row );
		}
		fclose( $fh );
		$csv_content = ob_get_clean();

		// Upload to WordPress uploads directory.
		$upload = wp_upload_bits( $filename, null, $csv_content );
		if ( ! empty( $upload['error'] ) ) {
			error_log( 'WPbot CSV_Creator: upload failed — ' . $upload['error'] );
			return array( 'success' => false, 'message' => 'CSV upload failed: ' . $upload['error'] );
		}

		// Register as a media library attachment.
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'text/csv',
				'post_title'     => sanitize_file_name( $filename ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);

		if ( is_wp_error( $attachment_id ) ) {
			error_log( 'WPbot CSV_Creator: attachment insert failed — ' . $attachment_id->get_error_message() );
			return array( 'success' => false, 'message' => 'Could not add CSV to media library.' );
		}

		return array(
			'success'           => true,
			'csv_url'           => $upload['url'],
			'csv_attachment_id' => $attachment_id,
			'csv_filename'      => $filename,
			'csv_path'          => $upload['file'],
			'csv_row_count'     => max( 0, count( $rows ) - 1 ), // exclude header
		);
	}
}
