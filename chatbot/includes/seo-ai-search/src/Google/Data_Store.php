<?php
/**
 * Daily Search Console and Analytics aggregates: write and read.
 *
 * Other code reads through this class or the public API, never the tables.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Google;

use QuantumCloud\SeoAiSearch\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Stores and queries daily aggregates.
 */
final class Data_Store {

	/**
	 * Rows per INSERT statement.
	 */
	const BATCH = 200;

	/**
	 * Id cache for URLs and queries within one request.
	 *
	 * @var array
	 */
	private static $ids = array(
		'url'   => array(),
		'query' => array(),
	);

	/**
	 * Id of a URL (created when new). Fragments are dropped.
	 *
	 * @param string $url URL.
	 * @return int
	 */
	public static function url_id( $url ) {
		$url = (string) strtok( (string) $url, '#' );
		if ( '' === $url ) {
			return 0;
		}
		return self::dictionary_id( 'url', $url );
	}

	/**
	 * Id of a search query (created when new).
	 *
	 * @param string $query Query text.
	 * @return int
	 */
	public static function query_id( $query ) {
		$query = trim( (string) $query );
		if ( '' === $query ) {
			return 0;
		}
		return self::dictionary_id( 'query', function_exists( 'mb_substr' ) ? mb_substr( $query, 0, 500 ) : substr( $query, 0, 500 ) );
	}

	/**
	 * Insert or replace daily Search Console rows.
	 *
	 * @param array $rows Each: day, url_id, query_id, clicks, impressions, position (average).
	 * @return int Rows written.
	 */
	public static function put_gsc( array $rows ) {
		global $wpdb;
		$table   = Schema::table( Schema::GSC_DAILY );
		$written = 0;
		foreach ( array_chunk( $rows, self::BATCH ) as $chunk ) {
			$values = array();
			$args   = array();
			foreach ( $chunk as $row ) {
				$values[] = '(%s,%d,%d,%d,%d,%f)';
				$args[]   = (string) $row['day'];
				$args[]   = (int) $row['url_id'];
				$args[]   = (int) $row['query_id'];
				$args[]   = (int) round( (float) $row['clicks'] );
				$args[]   = (int) round( (float) $row['impressions'] );
				$args[]   = (float) $row['position'] * (float) $row['impressions'];
			}
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant; one placeholder group per row.
			$sql      = "INSERT INTO {$table} (day,url_id,query_id,clicks,impressions,position_sum) VALUES " . implode( ',', $values ) . ' ON DUPLICATE KEY UPDATE clicks=VALUES(clicks), impressions=VALUES(impressions), position_sum=VALUES(position_sum)';
			$written += (int) $wpdb->query( $wpdb->prepare( $sql, $args ) );
			// phpcs:enable
		}
		return $written;
	}

	/**
	 * Insert or replace daily Analytics rows.
	 *
	 * @param array $rows Each: day, url_id, sessions, engaged_sessions, conversions, revenue.
	 * @return int Rows written.
	 */
	public static function put_ga( array $rows ) {
		global $wpdb;
		$table   = Schema::table( Schema::GA_DAILY );
		$written = 0;
		foreach ( array_chunk( $rows, self::BATCH ) as $chunk ) {
			$values = array();
			$args   = array();
			foreach ( $chunk as $row ) {
				$values[] = '(%s,%d,%d,%d,%f,%f)';
				$args[]   = (string) $row['day'];
				$args[]   = (int) $row['url_id'];
				$args[]   = (int) $row['sessions'];
				$args[]   = (int) $row['engaged_sessions'];
				$args[]   = (float) $row['conversions'];
				$args[]   = (float) $row['revenue'];
			}
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant; one placeholder group per row.
			$sql      = "INSERT INTO {$table} (day,url_id,sessions,engaged_sessions,conversions,revenue) VALUES " . implode( ',', $values ) . ' ON DUPLICATE KEY UPDATE sessions=VALUES(sessions), engaged_sessions=VALUES(engaged_sessions), conversions=VALUES(conversions), revenue=VALUES(revenue)';
			$written += (int) $wpdb->query( $wpdb->prepare( $sql, $args ) );
			// phpcs:enable
		}
		return $written;
	}

	/**
	 * Site totals per day between two dates.
	 *
	 * @param string $start Y-m-d.
	 * @param string $end   Y-m-d.
	 * @return array List of day, clicks, impressions, ctr, position.
	 */
	public static function site_series( $start, $end ) {
		global $wpdb;
		$table = Schema::table( Schema::GSC_DAILY );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT day, clicks, impressions, position_sum FROM {$table} WHERE url_id = 0 AND query_id = 0 AND day BETWEEN %s AND %s ORDER BY day", $start, $end ), ARRAY_A );
		return array_map( array( __CLASS__, 'shape' ), (array) $rows );
	}

	/**
	 * Totals for a period: site, one URL, one query, or one URL x query.
	 *
	 * @param string $start    Y-m-d.
	 * @param string $end      Y-m-d.
	 * @param int    $url_id   URL id (0 = any/site).
	 * @param int    $query_id Query id (0 = any/site).
	 * @return array clicks, impressions, ctr, position.
	 */
	public static function totals( $start, $end, $url_id = 0, $query_id = 0 ) {
		global $wpdb;
		$table = Schema::table( Schema::GSC_DAILY );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT SUM(clicks) AS clicks, SUM(impressions) AS impressions, SUM(position_sum) AS position_sum FROM {$table} WHERE url_id = %d AND query_id = %d AND day BETWEEN %s AND %s", (int) $url_id, (int) $query_id, $start, $end ), ARRAY_A );
		return self::shape( is_array( $row ) ? $row : array() );
	}

	/**
	 * Top pages or queries for a period.
	 *
	 * @param string $kind  page|query.
	 * @param string $start Y-m-d.
	 * @param string $end   Y-m-d.
	 * @param int    $limit Rows.
	 * @param string $order clicks|impressions.
	 * @return array List of id, label, post_id, clicks, impressions, ctr, position.
	 */
	public static function top( $kind, $start, $end, $limit = 10, $order = 'clicks' ) {
		global $wpdb;
		$daily = Schema::table( Schema::GSC_DAILY );
		$order = 'impressions' === $order ? 'impressions' : 'clicks';
		$limit = max( 1, min( 1000, (int) $limit ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table names from constants; $order from a fixed list.
		if ( 'query' === $kind ) {
			$dict = Schema::table( Schema::QUERIES );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT d.query_id AS id, q.query AS label, 0 AS post_id, SUM(d.clicks) AS clicks, SUM(d.impressions) AS impressions, SUM(d.position_sum) AS position_sum FROM {$daily} d JOIN {$dict} q ON q.id = d.query_id WHERE d.url_id = 0 AND d.query_id > 0 AND d.day BETWEEN %s AND %s GROUP BY d.query_id ORDER BY {$order} DESC LIMIT %d", $start, $end, $limit ), ARRAY_A );
		} else {
			$dict = Schema::table( Schema::URLS );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT d.url_id AS id, u.url AS label, u.post_id AS post_id, SUM(d.clicks) AS clicks, SUM(d.impressions) AS impressions, SUM(d.position_sum) AS position_sum FROM {$daily} d JOIN {$dict} u ON u.id = d.url_id WHERE d.query_id = 0 AND d.url_id > 0 AND d.day BETWEEN %s AND %s GROUP BY d.url_id ORDER BY {$order} DESC LIMIT %d", $start, $end, $limit ), ARRAY_A );
		}
		// phpcs:enable
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array_merge(
				array(
					'id'      => (int) $row['id'],
					'label'   => (string) $row['label'],
					'post_id' => (int) $row['post_id'],
				),
				self::shape( $row )
			);
		}
		return $out;
	}

	/**
	 * Oldest and newest day stored for the site.
	 *
	 * @return array{first:string,last:string,days:int}
	 */
	public static function coverage() {
		global $wpdb;
		$table = Schema::table( Schema::GSC_DAILY );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$row = $wpdb->get_row( "SELECT MIN(day) AS first, MAX(day) AS last, COUNT(*) AS days FROM {$table} WHERE url_id = 0 AND query_id = 0", ARRAY_A );
		return array(
			'first' => isset( $row['first'] ) ? (string) $row['first'] : '',
			'last'  => isset( $row['last'] ) ? (string) $row['last'] : '',
			'days'  => isset( $row['days'] ) ? (int) $row['days'] : 0,
		);
	}

	/**
	 * Delete rows older than a day (storage limit).
	 *
	 * @param string $before Y-m-d; rows before it are removed.
	 * @return int Rows deleted.
	 */
	public static function prune( $before ) {
		global $wpdb;
		$gsc = Schema::table( Schema::GSC_DAILY );
		$ga  = Schema::table( Schema::GA_DAILY );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table names from constants.
		$done  = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$gsc} WHERE day < %s LIMIT 50000", $before ) );
		$done += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$ga} WHERE day < %s LIMIT 50000", $before ) );
		// phpcs:enable
		return $done;
	}

	/**
	 * Post id for a stored URL, cached in the table (re-resolved when the
	 * cached post is gone, or daily for addresses without a post).
	 *
	 * @param int $url_id URL id.
	 * @return int
	 */
	public static function post_for_url( $url_id ) {
		global $wpdb;
		$table = Schema::table( Schema::URLS );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT url, post_id, resolved_at FROM {$table} WHERE id = %d", (int) $url_id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return 0;
		}
		if ( ! empty( $row['resolved_at'] ) ) {
			$cached = (int) $row['post_id'];
			// Re-resolve when the cached post was deleted, or after a day for addresses
			// without a post (a page may have been published at that address since).
			$stale = $cached ? false === get_post_status( $cached ) : strtotime( (string) $row['resolved_at'] . ' UTC' ) < time() - DAY_IN_SECONDS;
			if ( ! $stale ) {
				return $cached;
			}
		}
		$post_id = (int) url_to_postid( (string) $row['url'] );
		if ( ! $post_id && untrailingslashit( (string) $row['url'] ) === untrailingslashit( home_url() ) ) {
			$post_id = (int) get_option( 'page_on_front' );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		$wpdb->update(
			$table,
			array(
				'post_id'     => $post_id,
				'resolved_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $url_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		return $post_id;
	}

	/**
	 * URL id for a post's permalink (0 when Search Console never saw it).
	 *
	 * @param int $post_id Post id.
	 * @return int
	 */
	public static function url_for_post( $post_id ) {
		global $wpdb;
		$table = Schema::table( Schema::URLS );
		$url   = (string) get_permalink( (int) $post_id );
		if ( '' === $url ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE url_hash = %s", md5( $url ) ) );
	}

	/**
	 * Aggregated rows for a period, for the opportunity rules.
	 *
	 * @param string $kind  page | query | page_query.
	 * @param string $start Y-m-d.
	 * @param string $end   Y-m-d.
	 * @param int    $min   Minimum impressions.
	 * @param int    $limit Most rows (highest impressions first).
	 * @return array List of url_id, query_id, url, query, clicks, impressions, ctr, position.
	 */
	public static function period_rows( $kind, $start, $end, $min = 1, $limit = 2000 ) {
		global $wpdb;
		$daily   = Schema::table( Schema::GSC_DAILY );
		$urls    = Schema::table( Schema::URLS );
		$queries = Schema::table( Schema::QUERIES );
		$where   = array(
			'page'       => 'd.url_id > 0 AND d.query_id = 0',
			'query'      => 'd.url_id = 0 AND d.query_id > 0',
			'page_query' => 'd.url_id > 0 AND d.query_id > 0',
		);
		$cond    = isset( $where[ $kind ] ) ? $where[ $kind ] : $where['page'];
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table names from constants; $cond from a fixed list.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.url_id, d.query_id, u.url, q.query, SUM(d.clicks) AS clicks, SUM(d.impressions) AS impressions, SUM(d.position_sum) AS position_sum
				FROM {$daily} d LEFT JOIN {$urls} u ON u.id = d.url_id LEFT JOIN {$queries} q ON q.id = d.query_id
				WHERE {$cond} AND d.day BETWEEN %s AND %s
				GROUP BY d.url_id, d.query_id HAVING impressions >= %d ORDER BY impressions DESC LIMIT %d",
				$start,
				$end,
				max( 0, (int) $min ),
				max( 1, min( 20000, (int) $limit ) )
			),
			ARRAY_A
		);
		// phpcs:enable
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array_merge(
				array(
					'url_id'   => (int) $row['url_id'],
					'query_id' => (int) $row['query_id'],
					'url'      => (string) $row['url'],
					'query'    => (string) $row['query'],
				),
				self::shape( $row )
			);
		}
		return $out;
	}

	/**
	 * Analytics totals per landing page for a period.
	 *
	 * @param string $start Y-m-d.
	 * @param string $end   Y-m-d.
	 * @return array url_id => sessions, engaged_sessions, conversions, revenue.
	 */
	public static function ga_period( $start, $end ) {
		global $wpdb;
		$table = Schema::table( Schema::GA_DAILY );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from a constant.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT url_id, SUM(sessions) AS sessions, SUM(engaged_sessions) AS engaged_sessions, SUM(conversions) AS conversions, SUM(revenue) AS revenue FROM {$table} WHERE day BETWEEN %s AND %s GROUP BY url_id", $start, $end ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['url_id'] ] = array(
				'sessions'         => (int) $row['sessions'],
				'engaged_sessions' => (int) $row['engaged_sessions'],
				'conversions'      => (float) $row['conversions'],
				'revenue'          => (float) $row['revenue'],
			);
		}
		return $out;
	}

	/**
	 * Text of a stored URL or query.
	 *
	 * @param string $kind url|query.
	 * @param int    $id   Id.
	 * @return string
	 */
	public static function label( $kind, $id ) {
		global $wpdb;
		$table  = Schema::table( 'url' === $kind ? Schema::URLS : Schema::QUERIES );
		$column = 'url' === $kind ? 'url' : 'query';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Names from fixed values.
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT {$column} FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/**
	 * Clicks, impressions, CTR and average position from summed fields.
	 *
	 * @param array $row clicks, impressions, position_sum.
	 * @return array
	 */
	public static function shape( array $row ) {
		$clicks      = isset( $row['clicks'] ) ? (int) $row['clicks'] : 0;
		$impressions = isset( $row['impressions'] ) ? (int) $row['impressions'] : 0;
		$sum         = isset( $row['position_sum'] ) ? (float) $row['position_sum'] : 0.0;
		$out         = array(
			'clicks'      => $clicks,
			'impressions' => $impressions,
			'ctr'         => $impressions > 0 ? round( $clicks / $impressions, 4 ) : 0,
			'position'    => $impressions > 0 ? round( $sum / $impressions, 1 ) : 0,
		);
		if ( isset( $row['day'] ) ) {
			$out = array( 'day' => (string) $row['day'] ) + $out;
		}
		return $out;
	}

	/**
	 * Find or create a dictionary row.
	 *
	 * @param string $kind  url|query.
	 * @param string $value Text.
	 * @return int
	 */
	private static function dictionary_id( $kind, $value ) {
		global $wpdb;
		$hash = md5( $value );
		if ( isset( self::$ids[ $kind ][ $hash ] ) ) {
			return self::$ids[ $kind ][ $hash ];
		}
		$table  = Schema::table( 'url' === $kind ? Schema::URLS : Schema::QUERIES );
		$column = 'url' === $kind ? 'url' : 'query';
		$hcol   = 'url' === $kind ? 'url_hash' : 'query_hash';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table and column names from fixed values.
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE {$hcol} = %s", $hash ) );
		if ( ! $id ) {
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} ({$hcol},{$column}) VALUES (%s,%s)", $hash, $value ) );
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE {$hcol} = %s", $hash ) );
		}
		// phpcs:enable
		if ( count( self::$ids[ $kind ] ) > 5000 ) {
			self::$ids[ $kind ] = array();
		}
		self::$ids[ $kind ][ $hash ] = $id;
		return $id;
	}
}
