<?php
/**
 * The activity log: what every automation did, and why.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Autopilot;

use QuantumCloud\AiMarketing\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Activity log.
 */
final class Activity {

	const KEEP_DAYS = 180;

	/**
	 * Log an action.
	 *
	 * @param string $actor       Who: autopilot, sorter, scan, owner, agent ...
	 * @param string $action      What: list_created, contact_sorted, journey_started ...
	 * @param string $object_type list, contact, journey, campaign, suggestion.
	 * @param int    $object_id   Object id.
	 * @param string $reason      Why (shown to the owner).
	 * @param array  $data        Extra data.
	 * @param int    $contact_id  Contact involved.
	 * @return int Id.
	 */
	public static function log( $actor, $action, $object_type = '', $object_id = 0, $reason = '', array $data = array(), $contact_id = 0 ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			Schema::table( 'activity' ),
			array(
				'actor'       => substr( sanitize_key( (string) $actor ), 0, 50 ),
				'action'      => substr( sanitize_key( (string) $action ), 0, 50 ),
				'object_type' => substr( sanitize_key( (string) $object_type ), 0, 30 ),
				'object_id'   => (int) $object_id,
				'contact_id'  => (int) $contact_id,
				'reason'      => sanitize_textarea_field( (string) $reason ),
				'data'        => $data ? wp_json_encode( $data ) : null,
				'created_at'  => current_time( 'mysql', true ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Recent entries, newest first.
	 *
	 * @param array $args limit, actor, object_type, object_id, contact_id, before_id.
	 * @return array[]
	 */
	public static function recent( array $args = array() ) {
		global $wpdb;
		$table  = Schema::table( 'activity' );
		$where  = array( '1=1' );
		$params = array();
		foreach ( array( 'actor', 'object_type' ) as $key ) {
			if ( ! empty( $args[ $key ] ) ) {
				$where[]  = "{$key} = %s";
				$params[] = sanitize_key( (string) $args[ $key ] );
			}
		}
		foreach ( array( 'object_id', 'contact_id' ) as $key ) {
			if ( ! empty( $args[ $key ] ) ) {
				$where[]  = "{$key} = %d";
				$params[] = (int) $args[ $key ];
			}
		}
		if ( ! empty( $args['before_id'] ) ) {
			$where[]  = 'id < %d';
			$params[] = (int) $args['before_id'];
		}
		$params[] = isset( $args['limit'] ) ? max( 1, min( 200, (int) $args['limit'] ) ) : 50;
		$sql      = implode( ' AND ', $where );
		$rows     = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE {$sql} ORDER BY id DESC LIMIT %d", $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders -- Own table; columns from code.
		$out      = array();
		foreach ( $rows as $row ) {
			$data  = json_decode( (string) $row['data'], true );
			$out[] = array(
				'id'          => (int) $row['id'],
				'actor'       => (string) $row['actor'],
				'action'      => (string) $row['action'],
				'object_type' => (string) $row['object_type'],
				'object_id'   => (int) $row['object_id'],
				'contact_id'  => (int) $row['contact_id'],
				'reason'      => (string) $row['reason'],
				'data'        => is_array( $data ) ? $data : array(),
				'created_at'  => (string) $row['created_at'],
			);
		}
		return $out;
	}

	/**
	 * Delete entries older than KEEP_DAYS (daily).
	 *
	 * @return void
	 */
	public static function prune() {
		global $wpdb;
		$table = Schema::table( 'activity' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - self::KEEP_DAYS * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}
}
