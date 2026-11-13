<?php
/**
 * Database side of spending: one row per call, daily totals, and period sums.
 *
 * Nothing about the conversation is stored: only the feature, task, model, token and unit
 * counts, the estimated cost and ids (job, admin user).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Spend;

use QuantumCloud\WPBotCore\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Usage rows and totals.
 */
final class Ledger {

	/**
	 * Period sums already read in this request: start day => totals.
	 *
	 * @var array
	 */
	private static $sums = array();

	/**
	 * Store one call and add it to the daily totals.
	 *
	 * @param array $row Normalised row: module, feature, grp, task, provider, model, channel, source,
	 *                   job_id, user_id, tokens_*, images, web_searches, audio_seconds, characters,
	 *                   cost, price_known.
	 * @return int Row id (0 on failure).
	 */
	public static function insert( array $row ) {
		global $wpdb;
		$day = substr( current_time( 'mysql' ), 0, 10 );
		$ok  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table, write.
			Schema::table( Schema::USAGE ),
			array(
				'day'                => $day,
				'module'             => $row['module'],
				'feature'            => $row['feature'],
				'provider'           => $row['provider'],
				'model'              => $row['model'],
				'job_id'             => (int) $row['job_id'],
				'user_id'            => (int) $row['user_id'],
				'tokens_in'          => self::u32( $row['tokens_in'] ),
				'tokens_out'         => self::u32( $row['tokens_out'] ),
				'images'             => min( 65535, (int) $row['images'] ),
				'web_searches'       => min( 65535, (int) $row['web_searches'] ),
				'cost_estimate'      => (float) $row['cost'],
				'created_at'         => current_time( 'mysql', true ),
				'grp'                => $row['grp'],
				'task'               => $row['task'],
				'source'             => $row['source'],
				'channel'            => $row['channel'],
				'tokens_cached'      => self::u32( $row['tokens_cached'] ),
				'tokens_cache_write' => self::u32( $row['tokens_cache_write'] ),
				'tokens_reasoning'   => self::u32( $row['tokens_reasoning'] ),
				'audio_seconds'      => self::u32( $row['audio_seconds'] ),
				'characters'         => self::u32( $row['characters'] ),
				'price_known'        => $row['price_known'] ? 1 : 0,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d' )
		);
		$id  = $ok ? (int) $wpdb->insert_id : 0;

		$row['day'] = $day;
		self::add_daily( $row, 1, $row['price_known'] ? 0 : 1 );

		// Keep this request's period sums current.
		foreach ( self::$sums as $start => $sums ) {
			if ( $day >= $start ) {
				self::$sums[ $start ]['global']        += (float) $row['cost'];
				$grp                                    = $row['grp'];
				self::$sums[ $start ]['groups'][ $grp ] = ( isset( $sums['groups'][ $grp ] ) ? $sums['groups'][ $grp ] : 0.0 ) + (float) $row['cost'];
			}
		}
		return $id;
	}

	/**
	 * Add calls to a daily total row (creating it).
	 *
	 * @param array $row     day, grp, module, feature, provider, model, channel, source, tokens_in,
	 *                       tokens_out, images, cost.
	 * @param int   $calls   Calls to add.
	 * @param int   $unknown Calls without a known price.
	 * @return void
	 */
	public static function add_daily( array $row, $calls, $unknown ) {
		global $wpdb;
		$fields = array();
		foreach ( array( 'day', 'grp', 'module', 'feature', 'provider', 'model', 'channel', 'source' ) as $key ) {
			$fields[ $key ] = isset( $row[ $key ] ) ? (string) $row[ $key ] : '';
		}
		if ( '' === $fields['source'] ) {
			$fields['source'] = 'routed';
		}
		$hash  = md5( implode( '|', $fields ) );
		$table = Schema::table( Spend_Schema::DAILY );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own table; an atomic upsert keeps concurrent calls exact.
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (row_hash, day, grp, module, feature, provider, model, channel, source, calls, tokens_in, tokens_out, images, unknown_price, cost)
				VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %d, %d, %d, %d, %f)
				ON DUPLICATE KEY UPDATE calls = calls + VALUES(calls), tokens_in = tokens_in + VALUES(tokens_in), tokens_out = tokens_out + VALUES(tokens_out), images = images + VALUES(images), unknown_price = unknown_price + VALUES(unknown_price), cost = cost + VALUES(cost)",
				$hash,
				$fields['day'],
				$fields['grp'],
				$fields['module'],
				$fields['feature'],
				$fields['provider'],
				$fields['model'],
				$fields['channel'],
				$fields['source'],
				max( 0, (int) $calls ),
				max( 0, isset( $row['tokens_in'] ) ? (int) $row['tokens_in'] : 0 ),
				max( 0, isset( $row['tokens_out'] ) ? (int) $row['tokens_out'] : 0 ),
				max( 0, isset( $row['images'] ) ? (int) $row['images'] : 0 ),
				max( 0, (int) $unknown ),
				max( 0.0, isset( $row['cost'] ) ? (float) $row['cost'] : 0.0 )
			)
		);
		// phpcs:enable
	}

	/**
	 * Spend since a day: total and per group.
	 *
	 * @param string $start First day (Y-m-d, site time).
	 * @param string $end   Day after the last (Y-m-d), '' for open.
	 * @return array{global: float, groups: array<string,float>}
	 */
	public static function totals( $start, $end = '' ) {
		$cache = '' === $end;
		if ( $cache && isset( self::$sums[ $start ] ) ) {
			return self::$sums[ $start ];
		}
		global $wpdb;
		$table = Schema::table( Spend_Schema::DAILY );
		$where = '' === $end ? $wpdb->prepare( 'day >= %s', $start ) : $wpdb->prepare( 'day >= %s AND day < %s', $start, $end );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; $where built with prepare(); fresh value needed.
		$rows   = (array) $wpdb->get_results( "SELECT grp, SUM(cost) AS cost FROM {$table} WHERE {$where} GROUP BY grp", ARRAY_A );
		$totals = array(
			'global' => 0.0,
			'groups' => array(),
		);
		foreach ( $rows as $row ) {
			$totals['groups'][ (string) $row['grp'] ] = (float) $row['cost'];
			$totals['global']                        += (float) $row['cost'];
		}
		if ( $cache ) {
			self::$sums[ $start ] = $totals;
		}
		return $totals;
	}

	/**
	 * Forget this request's sums (tests; long-running jobs between batches).
	 *
	 * @return void
	 */
	public static function flush() {
		self::$sums = array();
	}

	/**
	 * Clamp to an unsigned 32-bit column.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	private static function u32( $value ) {
		return min( 4294967295, max( 0, (int) $value ) );
	}
}
