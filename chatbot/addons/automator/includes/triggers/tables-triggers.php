<?php
/**
 * Tables Triggers
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tables_Triggers
 *
 * Fires workflows on table record events (new, updated, deleted, cell-changed,
 * and the manual "Trigger Workflow" button click from the shortcode).
 *
 * PHP actions that fire these events:
 *   do_action( 'wpbot_table_new_record',     $table_id, $table_meta, $record_data, $record_id )
 *   do_action( 'wpbot_table_updated_record', $table_id, $table_meta, $new_data,    $row_id, $old_record )
 *   do_action( 'wpbot_table_deleted_record', $table_id, $table_meta, $row_id,      $old_record )
 *   do_action( 'wpbot_table_updated_cell',   $table_id, $table_meta, $row_id,      $col_slug, $old_val, $new_val )
 *   do_action( 'wpbot_table_trigger_button', $table_id, $table_meta, $row_id,      $row_data )
 */
class Tables_Triggers extends Trigger {

	public function __construct() {
		$this->id    = 'tables';
		$this->group = __( 'Tables', 'wpbot-automator' );
	}

	/**
	 * Register WordPress action hooks.
	 */
	public function register() {
		add_action( 'wpbot_table_new_record',     array( $this, 'on_new_record' ),     10, 4 );
		add_action( 'wpbot_table_updated_record', array( $this, 'on_updated_record' ), 10, 5 );
		add_action( 'wpbot_table_deleted_record', array( $this, 'on_deleted_record' ), 10, 4 );
		add_action( 'wpbot_table_updated_cell',   array( $this, 'on_updated_cell' ),   10, 6 );
		add_action( 'wpbot_table_trigger_button', array( $this, 'on_trigger_button' ), 10, 4 );
	}

	/**
	 * New record added to a table.
	 *
	 * Available tokens: {table_id}, {table_name}, {record_id}, {<column_slug>}, …
	 */
	public function on_new_record( $table_id, $table_meta, $record_data, $record_id ) {
		$data = array_merge(
			array(
				'table_id'   => $table_id,
				'table_name' => $table_meta['name'] ?? '',
				'record_id'  => $record_id,
			),
			is_array( $record_data ) ? $record_data : array()
		);

		$this->run( array( 'sub_id' => 'new_record', 'data' => $data ) );
	}

	/**
	 * Existing record updated in a table.
	 *
	 * Available tokens: {table_id}, {table_name}, {record_id}, {<column_slug>} (new value),
	 *                   {old_<column_slug>} (previous value).
	 */
	public function on_updated_record( $table_id, $table_meta, $new_data, $row_id, $old_record ) {
		$data = array_merge(
			array(
				'table_id'   => $table_id,
				'table_name' => $table_meta['name'] ?? '',
				'record_id'  => $row_id,
			),
			is_array( $new_data ) ? $new_data : array()
		);

		// Expose old values as {old_<slug>} tokens.
		if ( is_array( $old_record ) ) {
			foreach ( $old_record as $k => $v ) {
				$data[ 'old_' . $k ] = $v;
			}
		}

		$this->run( array( 'sub_id' => 'updated_record', 'data' => $data ) );
	}

	/**
	 * Record deleted from a table.
	 *
	 * Available tokens: {table_id}, {table_name}, {record_id}, {<column_slug>} (last known values).
	 */
	public function on_deleted_record( $table_id, $table_meta, $row_id, $old_record ) {
		$data = array_merge(
			array(
				'table_id'   => $table_id,
				'table_name' => $table_meta['name'] ?? '',
				'record_id'  => $row_id,
			),
			is_array( $old_record ) ? $old_record : array()
		);

		$this->run( array( 'sub_id' => 'deleted_record', 'data' => $data ) );
	}

	/**
	 * A specific cell value changed.
	 *
	 * Available tokens: {table_id}, {table_name}, {record_id}, {column_slug}, {old_value}, {new_value}.
	 */
	public function on_updated_cell( $table_id, $table_meta, $row_id, $col_slug, $old_val, $new_val ) {
		$this->run( array(
			'sub_id' => 'updated_cell',
			'data'   => array(
				'table_id'    => $table_id,
				'table_name'  => $table_meta['name'] ?? '',
				'record_id'   => $row_id,
				'column_slug' => $col_slug,
				'old_value'   => $old_val,
				'new_value'   => $new_val,
			),
		) );
	}

	/**
	 * "Trigger Workflow" button clicked from the shortcode frontend.
	 *
	 * Available tokens: {table_id}, {table_name}, {record_id}, {<column_slug>}, …
	 */
	public function on_trigger_button( $table_id, $table_meta, $row_id, $row_data ) {
		$data = array_merge(
			array(
				'table_id'   => $table_id,
				'table_name' => $table_meta['name'] ?? '',
				'record_id'  => $row_id,
			),
			is_array( $row_data ) ? $row_data : array()
		);

		$this->run( array( 'sub_id' => 'trigger_button', 'data' => $data ) );
	}
}
