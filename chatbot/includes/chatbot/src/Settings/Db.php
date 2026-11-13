<?php
/**
 * Small database helpers shared by the steps and stores.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Database helpers.
 */
final class Db {

	/**
	 * Columns per table, cached per request.
	 *
	 * @var array<string,string[]>
	 */
	private static $columns = array();

	/**
	 * Column names of a table ([] when the table is missing).
	 *
	 * @param string $table Full table name.
	 * @return string[]
	 */
	public static function columns( $table ) {
		global $wpdb;
		if ( ! isset( self::$columns[ $table ] ) ) {
			$rows                    = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::$columns[ $table ] = is_array( $rows ) ? array_map( 'strtolower', array_column( $rows, 'Field' ) ) : array();
		}
		return self::$columns[ $table ];
	}

	/**
	 * Whether a table has a column.
	 *
	 * @param string $table  Table.
	 * @param string $column Column.
	 * @return bool
	 */
	public static function has( $table, $column ) {
		return in_array( strtolower( $column ), self::columns( $table ), true );
	}

	/**
	 * Forget cached columns.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$columns = array();
	}
}
