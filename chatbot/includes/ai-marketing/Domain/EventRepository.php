<?php
/**
 * Append-only activity log.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * SQL for wpbot_crm_events. Types: open|click|unsubscribe|bounce|subscribe|confirm|note|import|…
 */
class EventRepository extends Repository {

	/**
	 * Table suffix.
	 *
	 * @var string
	 */
	protected $table_name = 'events';

	/**
	 * Writable columns.
	 *
	 * @var string[]
	 */
	protected $columns = array( 'contact_id', 'message_id', 'type', 'data', 'url_hash' );

	/**
	 * JSON columns.
	 *
	 * @var string[]
	 */
	protected $json = array( 'data' );

	/**
	 * Integer columns.
	 *
	 * @var string[]
	 */
	protected $ints = array( 'contact_id', 'message_id' );

	/**
	 * Only created_at.
	 *
	 * @var string[]
	 */
	protected $timestamps = array( 'created_at' );

	/**
	 * Log an event. When $data['url'] is set, url_hash = sha1( url ).
	 *
	 * @param string $type       Event type.
	 * @param int    $contact_id Contact id (0 if unknown).
	 * @param array  $data       Payload.
	 * @param int    $message_id Message id (0 = none).
	 * @return int Event id.
	 */
	public function log( $type, $contact_id, array $data = array(), $message_id = 0 ) {
		$url = isset( $data['url'] ) ? (string) $data['url'] : '';
		return $this->insert(
			array(
				'type'       => sanitize_key( $type ),
				'contact_id' => (int) $contact_id,
				'message_id' => (int) $message_id > 0 ? (int) $message_id : null,
				'data'       => $data,
				'url_hash'   => '' !== $url ? sha1( $url ) : null,
			)
		);
	}

	/**
	 * Event counts by type within [from, to).
	 *
	 * @param string   $from_utc From (UTC).
	 * @param string   $to_utc   To (UTC).
	 * @param string[] $types    Restrict to these types (empty = all).
	 * @param bool     $unique   Count distinct contacts instead of rows.
	 * @return array<string,int>
	 */
	public function counts_by_type( $from_utc, $to_utc, array $types = array(), $unique = false ) {
		global $wpdb;
		$table  = $this->table();
		$count  = $unique ? 'COUNT(DISTINCT contact_id)' : 'COUNT(*)';
		$sql    = "SELECT type, {$count} AS n FROM {$table} WHERE created_at >= %s AND created_at < %s";
		$params = array( (string) $from_utc, (string) $to_utc );
		if ( ! empty( $types ) ) {
			$sql   .= ' AND type IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ')';
			$params = array_merge( $params, array_values( $types ) );
		}
		$sql .= ' GROUP BY type';
		$out  = array_fill_keys( $types, 0 );
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$out[ $row['type'] ] = (int) $row['n'];
		}
		return $out;
	}

	/**
	 * Event counts by weekday and hour in the site's timezone, for send-time suggestions.
	 *
	 * @param string[] $types    Event types (e.g. open, click).
	 * @param string   $from_utc Only events at or after this time (UTC).
	 * @return array[] Each: weekday (0 = Sunday … 6 = Saturday), hour (0-23), events (int).
	 */
	public function activity_by_hour( array $types, $from_utc ) {
		global $wpdb;
		if ( empty( $types ) ) {
			return array();
		}
		$table  = $this->table();
		$offset = (int) round( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS );
		$in     = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$sql    = "SELECT DAYOFWEEK( local_time ) - 1 AS weekday, HOUR( local_time ) AS hour, COUNT(*) AS events
			FROM ( SELECT DATE_ADD( created_at, INTERVAL %d SECOND ) AS local_time FROM {$table} WHERE created_at >= %s AND type IN ({$in}) ) AS t
			GROUP BY weekday, hour ORDER BY events DESC";
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( $offset, (string) $from_utc ), array_values( $types ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array_map(
			static function ( $row ) {
				return array(
					'weekday' => (int) $row['weekday'],
					'hour'    => (int) $row['hour'],
					'events'  => (int) $row['events'],
				);
			},
			(array) $rows
		);
	}

	/**
	 * Click counts grouped by URL.
	 *
	 * @param array $args campaign_id (0 = all), from/to (UTC, optional), limit (20).
	 * @return array[] Each: url, url_hash, clicks, contacts.
	 */
	public function clicks_by_url( array $args = array() ) {
		global $wpdb;
		$args     = wp_parse_args(
			$args,
			array(
				'campaign_id' => 0,
				'from'        => '',
				'to'          => '',
				'limit'       => 20,
			)
		);
		$table    = $this->table();
		$messages = Schema::table( 'messages' );
		$join     = '';
		$where    = array( "e.type = 'click'", 'e.url_hash IS NOT NULL' );
		$params   = array();
		if ( (int) $args['campaign_id'] > 0 ) {
			$join     = "INNER JOIN {$messages} m ON m.id = e.message_id";
			$where[]  = 'm.campaign_id = %d';
			$params[] = (int) $args['campaign_id'];
		}
		if ( '' !== $args['from'] ) {
			$where[]  = 'e.created_at >= %s';
			$params[] = (string) $args['from'];
		}
		if ( '' !== $args['to'] ) {
			$where[]  = 'e.created_at < %s';
			$params[] = (string) $args['to'];
		}
		$params[] = max( 1, (int) $args['limit'] );
		$sql      = "SELECT e.url_hash, MAX(e.data) AS data, COUNT(*) AS clicks, COUNT(DISTINCT e.contact_id) AS contacts
			FROM {$table} e {$join} WHERE " . implode( ' AND ', $where ) . ' GROUP BY e.url_hash ORDER BY clicks DESC LIMIT %d';
		$out      = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$data  = self::decode( $row['data'] );
			$out[] = array(
				'url'      => isset( $data['url'] ) ? (string) $data['url'] : '',
				'url_hash' => $row['url_hash'],
				'clicks'   => (int) $row['clicks'],
				'contacts' => (int) $row['contacts'],
			);
		}
		return $out;
	}

	/**
	 * Latest events of a contact.
	 *
	 * @param int $contact_id Contact id.
	 * @param int $limit      Max rows.
	 * @return array[]
	 */
	public function for_contact( $contact_id, $limit = 50 ) {
		global $wpdb;
		$table = $this->table();
		return $this->hydrate_all( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE contact_id = %d ORDER BY id DESC LIMIT %d", (int) $contact_id, max( 1, (int) $limit ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Delete events older than a UTC datetime.
	 *
	 * @param string $before_utc Cut-off (UTC).
	 * @return int Rows deleted.
	 */
	public function purge_older_than( $before_utc ) {
		global $wpdb;
		$table = $this->table();
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", (string) $before_utc ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
