<?php
/**
 * Keyword research runs (S14): a 30-day cache per kind, seed, language,
 * country and data source, and the history the owner can reopen.
 *
 * Results are stored only after Schema_Guard cleaned them.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Research;

use QuantumCloud\SeoAiSearch\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Research rows.
 */
final class Research_Store {

	/**
	 * Days a run is reused without cost.
	 */
	const DAYS = 30;

	/**
	 * Cache key.
	 *
	 * @param string $kind     keywords, niche or gap.
	 * @param string $seed     Seed (or the competitor list for a gap).
	 * @param string $language Language.
	 * @param string $country  Country.
	 * @param string $source   Data source ('ai' or the provider id).
	 * @return string
	 */
	public static function cache_key( $kind, $seed, $language, $country, $source ) {
		return md5( implode( '|', array( $kind, strtolower( trim( (string) $seed ) ), $language, $country, $source ) ) );
	}

	/**
	 * A finished, unexpired run with this key.
	 *
	 * @param string $key Cache key.
	 * @return array|null
	 */
	public static function cached( $key ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from code.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE cache_key = %s AND status = 'done' AND expires_at > %s ORDER BY id DESC LIMIT 1", $table, $key, current_time( 'mysql', true ) ), ARRAY_A );
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Save a run.
	 *
	 * @param array $run kind, seed, language, country, cache_key, status, job_id, result, sources, cost.
	 * @return int Id.
	 */
	public static function save( array $run ) {
		global $wpdb;
		$now = time();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table, write.
		$wpdb->insert(
			self::table(),
			array(
				'kind'       => sanitize_key( $run['kind'] ),
				'seed'       => substr( sanitize_text_field( (string) $run['seed'] ), 0, 255 ),
				'language'   => substr( sanitize_key( (string) $run['language'] ), 0, 8 ),
				'country'    => substr( sanitize_key( (string) $run['country'] ), 0, 8 ),
				'cache_key'  => (string) $run['cache_key'],
				'status'     => isset( $run['status'] ) ? sanitize_key( $run['status'] ) : 'done',
				'job_id'     => isset( $run['job_id'] ) ? absint( $run['job_id'] ) : 0,
				'result'     => wp_json_encode( isset( $run['result'] ) ? (array) $run['result'] : array() ),
				'sources'    => substr( sanitize_text_field( isset( $run['sources'] ) ? implode( ',', (array) $run['sources'] ) : '' ), 0, 255 ),
				'cost'       => isset( $run['cost'] ) ? (float) $run['cost'] : 0,
				'created_by' => get_current_user_id(),
				'created_at' => gmdate( 'Y-m-d H:i:s', $now ),
				'expires_at' => gmdate( 'Y-m-d H:i:s', $now + self::DAYS * DAY_IN_SECONDS ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Finish a running run (competitor gap job).
	 *
	 * @param int    $id      Id.
	 * @param string $status  done or failed.
	 * @param array  $result  Clean result.
	 * @param float  $cost    Cost.
	 * @param array  $sources Sources.
	 * @return void
	 */
	public static function finish( $id, $status, array $result, $cost = 0.0, array $sources = array() ) {
		global $wpdb;
		$fields = array(
			'status' => 'done' === $status ? 'done' : 'failed',
			'result' => wp_json_encode( $result ),
			'cost'   => (float) $cost,
		);
		if ( $sources ) {
			$fields['sources'] = substr( sanitize_text_field( implode( ',', $sources ) ), 0, 255 );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Own table, write.
		$wpdb->update( self::table(), $fields, array( 'id' => (int) $id ) );
	}

	/**
	 * One run.
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from code.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, (int) $id ), ARRAY_A );
		return $row ? self::shape( $row ) : null;
	}

	/**
	 * Latest runs, without their results (cheap).
	 *
	 * @param int    $limit Max rows.
	 * @param string $kind  Only this kind ('' for all).
	 * @return array[]
	 */
	public static function history( $limit = 30, $kind = '' ) {
		global $wpdb;
		$table = self::table();
		$limit = max( 1, min( 200, (int) $limit ) );
		if ( '' !== $kind ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from code.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, kind, seed, language, country, status, job_id, sources, cost, created_at, expires_at FROM %i WHERE kind = %s ORDER BY id DESC LIMIT %d', $table, sanitize_key( $kind ), $limit ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from code.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, kind, seed, language, country, status, job_id, sources, cost, created_at, expires_at FROM %i ORDER BY id DESC LIMIT %d', $table, $limit ), ARRAY_A );
		}
		$out = array();
		foreach ( (array) $rows as $row ) {
			$row['result'] = '[]';
			$item          = self::shape( $row );
			unset( $item['result'] );
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * Delete runs older than a year (history stays useful, the table small).
	 *
	 * @return void
	 */
	public static function prune() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Table name from code.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', $table, gmdate( 'Y-m-d H:i:s', time() - YEAR_IN_SECONDS ) ) );
	}

	/**
	 * Row for callers.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	private static function shape( array $row ) {
		$result = json_decode( (string) $row['result'], true );
		return array(
			'id'         => (int) $row['id'],
			'kind'       => (string) $row['kind'],
			'seed'       => (string) $row['seed'],
			'language'   => (string) $row['language'],
			'country'    => (string) $row['country'],
			'status'     => (string) $row['status'],
			'job_id'     => (int) $row['job_id'],
			'result'     => is_array( $result ) ? $result : array(),
			'sources'    => array_values( array_filter( explode( ',', (string) $row['sources'] ) ) ),
			'cost'       => (float) $row['cost'],
			'created_at' => (string) $row['created_at'],
			'expires_at' => (string) $row['expires_at'],
		);
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	private static function table() {
		return Schema::table( Schema::RESEARCH );
	}
}
