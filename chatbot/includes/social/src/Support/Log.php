<?php
/**
 * The Social log (Settings › Advanced › Log). Every message and context
 * goes through Redact first, so no token is ever stored.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Support;

use QuantumCloud\Social\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Log writer and reader.
 */
final class Log {

	/**
	 * Add an entry.
	 *
	 * @param string $level   ok, info, error or paused.
	 * @param string $event   Machine event, for example "account_connected".
	 * @param string $message Plain-language message.
	 * @param array  $context Extra details (redacted).
	 * @return void
	 */
	public static function add( $level, $event, $message, array $context = array() ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table.
			Schema::table( 'log' ),
			array(
				'created_at' => current_time( 'mysql', true ),
				'level'      => in_array( $level, array( 'ok', 'info', 'error', 'paused' ), true ) ? $level : 'info',
				'event'      => substr( sanitize_key( $event ), 0, 40 ),
				'message'    => substr( Redact::secrets( sanitize_text_field( $message ) ), 0, 255 ),
				'context'    => array() === $context ? null : Redact::secrets( (string) wp_json_encode( $context ) ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Latest entries.
	 *
	 * @param int $limit Max rows.
	 * @return array[]
	 */
	public static function latest( $limit = 50 ) {
		global $wpdb;
		$table = Schema::table( 'log' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, created_at, level, event, message FROM {$table} ORDER BY id DESC LIMIT %d", max( 1, min( 200, (int) $limit ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Delete entries older than the retention setting.
	 *
	 * @param int $days Days to keep.
	 * @return void
	 */
	public static function prune( $days ) {
		global $wpdb;
		$table = Schema::table( 'log' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Own table.
	}
}
