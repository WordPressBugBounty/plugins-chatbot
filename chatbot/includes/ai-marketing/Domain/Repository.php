<?php
/**
 * Base repository.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

use QuantumCloud\WPBot\CRM\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Shared CRUD for one table. Rows are returned as arrays with integer columns cast
 * to int, flag columns cast to bool and JSON columns decoded to arrays.
 */
abstract class Repository {

	/**
	 * Table suffix (see Schema::table()).
	 *
	 * @var string
	 */
	protected $table_name = '';

	/**
	 * Writable columns (excluding id).
	 *
	 * @var string[]
	 */
	protected $columns = array();

	/**
	 * JSON columns (array in PHP, JSON text in DB).
	 *
	 * @var string[]
	 */
	protected $json = array();

	/**
	 * Integer columns (id is always cast).
	 *
	 * @var string[]
	 */
	protected $ints = array();

	/**
	 * Boolean flag columns.
	 *
	 * @var string[]
	 */
	protected $bools = array();

	/**
	 * Timestamp columns filled automatically: 'created_at' on insert, 'updated_at' on insert/update.
	 *
	 * @var string[]
	 */
	protected $timestamps = array( 'created_at', 'updated_at' );

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public function table() {
		return Schema::table( $this->table_name );
	}

	/**
	 * One row by id.
	 *
	 * @param int $id Row id.
	 * @return array|null
	 */
	public function find( $id ) {
		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Several rows by id, keyed by id.
	 *
	 * @param int[] $ids Ids.
	 * @return array<int,array>
	 */
	public function find_many( array $ids ) {
		global $wpdb;
		$ids = self::ids( $ids );
		if ( empty( $ids ) ) {
			return array();
		}
		$table = $this->table();
		$in    = implode( ',', $ids );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} WHERE id IN ({$in})", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids are cast to int.
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$row               = $this->hydrate( $row );
			$out[ $row['id'] ] = $row;
		}
		return $out;
	}

	/**
	 * Insert a row. Unknown keys are ignored; JSON columns are encoded; timestamps filled.
	 *
	 * @param array $data Column => value.
	 * @return int New id, 0 on failure.
	 */
	public function insert( array $data ) {
		global $wpdb;
		$now = Dates::now();
		foreach ( $this->timestamps as $column ) {
			if ( empty( $data[ $column ] ) ) {
				$data[ $column ] = $now;
			}
		}
		$ok = $wpdb->insert( $this->table(), $this->dehydrate( $data ) );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update a row. Unknown keys are ignored; updated_at is refreshed.
	 *
	 * @param int   $id   Row id.
	 * @param array $data Column => value.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		global $wpdb;
		if ( in_array( 'updated_at', $this->timestamps, true ) && ! isset( $data['updated_at'] ) ) {
			$data['updated_at'] = Dates::now();
		}
		unset( $data['id'], $data['created_at'] );
		$data = $this->dehydrate( $data );
		if ( empty( $data ) ) {
			return true;
		}
		return false !== $wpdb->update( $this->table(), $data, array( 'id' => (int) $id ) );
	}

	/**
	 * Delete a row.
	 *
	 * @param int $id Row id.
	 * @return bool True when a row was deleted.
	 */
	public function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Add $by to an integer column.
	 *
	 * @param int    $id     Row id.
	 * @param string $column Integer column.
	 * @param int    $by     Amount (may be negative; never goes below zero).
	 * @return bool
	 */
	public function increment( $id, $column, $by = 1 ) {
		global $wpdb;
		if ( ! in_array( $column, $this->ints, true ) ) {
			return false;
		}
		$table = $this->table();
		$sql   = "UPDATE {$table} SET {$column} = GREATEST( 0, CAST( {$column} AS SIGNED ) + %d ) WHERE id = %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column is whitelisted.
		return false !== $wpdb->query( $wpdb->prepare( $sql, (int) $by, (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Cast a DB row to PHP types.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public function hydrate( array $row ) {
		if ( isset( $row['id'] ) ) {
			$row['id'] = (int) $row['id'];
		}
		foreach ( $this->ints as $column ) {
			if ( array_key_exists( $column, $row ) && null !== $row[ $column ] ) {
				$row[ $column ] = (int) $row[ $column ];
			}
		}
		foreach ( $this->bools as $column ) {
			if ( array_key_exists( $column, $row ) ) {
				$row[ $column ] = (bool) (int) $row[ $column ];
			}
		}
		foreach ( $this->json as $column ) {
			if ( array_key_exists( $column, $row ) ) {
				$row[ $column ] = self::decode( $row[ $column ] );
			}
		}
		return $row;
	}

	/**
	 * Hydrate a list of rows.
	 *
	 * @param array|null $rows Raw rows.
	 * @return array[]
	 */
	protected function hydrate_all( $rows ) {
		return array_map( array( $this, 'hydrate' ), (array) $rows );
	}

	/**
	 * Keep writable columns and encode JSON ones.
	 *
	 * @param array $data Input.
	 * @return array
	 */
	protected function dehydrate( array $data ) {
		$allowed = array_merge( $this->columns, $this->timestamps );
		$out     = array();
		foreach ( $data as $column => $value ) {
			if ( ! in_array( $column, $allowed, true ) ) {
				continue;
			}
			if ( in_array( $column, $this->json, true ) ) {
				$value = null === $value ? null : wp_json_encode( is_array( $value ) || is_object( $value ) ? $value : array() );
			} elseif ( in_array( $column, $this->bools, true ) ) {
				$value = $value ? 1 : 0;
			} elseif ( is_bool( $value ) ) {
				$value = (int) $value;
			}
			$out[ $column ] = $value;
		}
		return $out;
	}

	/**
	 * Run a paginated SELECT * on this table.
	 *
	 * @param string $where    SQL after FROM table (e.g. "WHERE status = %s"), may be ''.
	 * @param array  $params   Values for the placeholders in $where.
	 * @param string $order    ORDER BY clause body (trusted).
	 * @param int    $page     1-based page.
	 * @param int    $per_page Rows per page; 0 = all.
	 * @return array{items:array[],total:int}
	 */
	protected function paginate( $where, array $params, $order, $page, $per_page ) {
		global $wpdb;
		$table    = $this->table();
		$per_page = max( 0, (int) $per_page );
		$limit    = '';
		if ( $per_page > 0 ) {
			$limit = sprintf( ' LIMIT %d OFFSET %d', $per_page, ( max( 1, (int) $page ) - 1 ) * $per_page );
		}
		$items_sql = "SELECT * FROM {$table} {$where} ORDER BY {$order}{$limit}";
		$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		if ( ! empty( $params ) ) {
			$items_sql = $wpdb->prepare( $items_sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count_sql = $wpdb->prepare( $count_sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		return array(
			'items' => $this->hydrate_all( $wpdb->get_results( $items_sql, ARRAY_A ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'total' => (int) $wpdb->get_var( $count_sql ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Decode a JSON column to an array (invalid/empty → array()).
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	public static function decode( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( null === $value || '' === $value ) {
			return array();
		}
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Unique positive integer ids.
	 *
	 * @param mixed $ids Array of ids.
	 * @return int[]
	 */
	public static function ids( $ids ) {
		$ids = array_map( 'intval', (array) $ids );
		return array_values( array_unique( array_filter( $ids, static function ( $id ) {
			return $id > 0;
		} ) ) );
	}
}
