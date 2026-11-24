<?php
/**
 * Basic analytics (HC1-14, plan 5.1 "Analytics"): views, helpful rate,
 * searches with no result, questions the chatbot could not answer.
 *
 * Two small tables, one row per day:
 * - {prefix}wpbot_help_daily: day, article, views, yes, no;
 * - {prefix}wpbot_help_searches: day, search (normalised), times, last number of results.
 * Rows older than 13 months are removed once a day. The totals on articles
 * (`_wpbot_views`, `_wpbot_helpful_*`) stay the all-time numbers.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Storage and reports.
 */
final class Stats {

	/**
	 * Schema version.
	 */
	const SCHEMA = 2;

	/**
	 * Option with the installed schema version.
	 */
	const SCHEMA_OPTION = 'qcld_wpbot_help_center_stats_schema';

	/**
	 * Daily clean-up event.
	 */
	const CRON = 'qcld_wpbot_help_center_stats_purge';

	/**
	 * Months kept.
	 */
	const KEEP_MONTHS = 13;

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'maybe_install' ), 5 );
		add_action( 'qcld_wpbot_help_center/viewed', array( self::class, 'on_view' ) );
		add_action( 'qcld_wpbot_help_center/voted', array( self::class, 'on_vote' ), 10, 3 );
		add_action( 'qcld_wpbot_help_center/searched', array( self::class, 'on_search' ), 10, 2 );
		add_action( 'qcld_wpbot_help_center/docs_searched', array( self::class, 'on_ai_search' ), 10, 2 );
		add_action( 'qcld_wpbot_help_center/docs_read', array( self::class, 'on_ai_read' ) );
		add_action( self::CRON, array( self::class, 'purge' ) );
	}

	/**
	 * Daily table.
	 *
	 * @return string
	 */
	public static function daily_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpbot_help_daily';
	}

	/**
	 * Search table.
	 *
	 * @return string
	 */
	public static function search_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpbot_help_searches';
	}

	/**
	 * Searches from AI assistants through the public docs server (schema 2, 0.19.1).
	 *
	 * @return string
	 */
	public static function ai_search_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpbot_help_ai_searches';
	}

	/**
	 * Create or update the tables, and schedule the clean-up.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( self::SCHEMA !== (int) get_option( self::SCHEMA_OPTION, 0 ) ) {
			global $wpdb;
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$charset = $wpdb->get_charset_collate();
			dbDelta(
				'CREATE TABLE ' . self::daily_table() . " (
				day date NOT NULL,
				post_id bigint(20) unsigned NOT NULL,
				views int(10) unsigned NOT NULL DEFAULT 0,
				yes int(10) unsigned NOT NULL DEFAULT 0,
				no int(10) unsigned NOT NULL DEFAULT 0,
				ai_reads int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (day,post_id),
				KEY post_id (post_id)
				) $charset;"
			);
			dbDelta(
				'CREATE TABLE ' . self::search_table() . " (
				day date NOT NULL,
				search_key varchar(191) NOT NULL,
				search varchar(255) NOT NULL DEFAULT '',
				times int(10) unsigned NOT NULL DEFAULT 0,
				results int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (day,search_key)
				) $charset;"
			);
			dbDelta(
				'CREATE TABLE ' . self::ai_search_table() . " (
				day date NOT NULL,
				search_key varchar(191) NOT NULL,
				search varchar(255) NOT NULL DEFAULT '',
				times int(10) unsigned NOT NULL DEFAULT 0,
				results int(10) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (day,search_key)
				) $charset;"
			);
			update_option( self::SCHEMA_OPTION, self::SCHEMA );
		}//end if
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * A view.
	 *
	 * @param int $post_id Article id.
	 * @return void
	 */
	public static function on_view( $post_id ) {
		self::add( (int) $post_id, array( 'views' => 1 ) );
	}

	/**
	 * A vote (a changed vote today moves the count).
	 *
	 * @param int    $post_id  Article id.
	 * @param string $value    yes or no.
	 * @param string $previous Earlier vote today, or ''.
	 * @return void
	 */
	public static function on_vote( $post_id, $value, $previous = '' ) {
		$change = array( 'yes' === $value ? 'yes' : 'no' => 1 );
		if ( 'yes' === $previous || 'no' === $previous ) {
			$change[ $previous ] = -1;
		}
		self::add( (int) $post_id, $change );
	}

	/**
	 * A search on the results page.
	 *
	 * @param string $text    Search.
	 * @param int    $results Number of results.
	 * @return void
	 */
	public static function on_search( $text, $results ) {
		self::log_search( self::search_table(), $text, $results );
	}

	/**
	 * A search from an AI assistant through the docs server.
	 *
	 * @param string $text    Search.
	 * @param int    $results Number of results.
	 * @return void
	 */
	public static function on_ai_search( $text, $results ) {
		self::log_search( self::ai_search_table(), $text, $results );
	}

	/**
	 * An article an AI assistant read through the docs server.
	 *
	 * @param int $post_id Article id.
	 * @return void
	 */
	public static function on_ai_read( $post_id ) {
		self::add( (int) $post_id, array( 'ai_reads' => 1 ) );
	}

	/**
	 * Count a search in a search table.
	 *
	 * @param string $table   Table.
	 * @param string $text    Search.
	 * @param int    $results Number of results.
	 * @return void
	 */
	private static function log_search( $table, $text, $results ) {
		global $wpdb;
		$key = self::search_key( (string) $text );
		if ( '' === $key ) {
			return;
		}
		$text = function_exists( 'mb_substr' ) ? mb_substr( trim( (string) $text ), 0, 255 ) : substr( trim( (string) $text ), 0, 255 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table; one upsert per search.
		$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (day, search_key, search, times, results) VALUES (%s, %s, %s, 1, %d) ON DUPLICATE KEY UPDATE times = times + 1, results = VALUES(results)', $table, self::today(), $key, $text, max( 0, (int) $results ) ) );
	}

	/**
	 * Grouping key of a search: lower case, single spaces, without punctuation.
	 *
	 * @param string $text Search.
	 * @return string
	 */
	public static function search_key( $text ) {
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', (string) preg_replace( '/[^\p{L}\p{N}\s-]+/u', ' ', $text ) ) );
		return strlen( $text ) > 191 ? substr( $text, 0, 150 ) . ':' . md5( $text ) : $text;
	}

	/**
	 * Remove rows older than KEEP_MONTHS.
	 *
	 * @return void
	 */
	public static function purge() {
		global $wpdb;
		$cut = gmdate( 'Y-m-d', strtotime( '-' . self::KEEP_MONTHS . ' months' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Own tables.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE day < %s', self::daily_table(), $cut ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE day < %s', self::search_table(), $cut ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE day < %s', self::ai_search_table(), $cut ) );
		// phpcs:enable
	}

	/**
	 * Helpful rate in percent, or null without votes.
	 *
	 * @param int $yes Yes votes.
	 * @param int $no  No votes.
	 * @return int|null
	 */
	public static function rate( $yes, $no ) {
		$all = (int) $yes + (int) $no;
		return $all > 0 ? (int) round( 100 * (int) $yes / $all ) : null;
	}

	/**
	 * A period's first day.
	 *
	 * @param int $days Days (7, 30, 90 or 365).
	 * @return string Y-m-d.
	 */
	public static function since( $days ) {
		$days = in_array( (int) $days, array( 7, 30, 90, 365 ), true ) ? (int) $days : 30;
		return gmdate( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS );
	}

	/**
	 * The report for a period.
	 *
	 * @param int $days Days.
	 * @return array{totals:array,daily:array,top:array,unhelpful:array,searches:array,no_results:array,gaps:int,ai:array}
	 */
	public static function report( $days = 30 ) {
		global $wpdb;
		$since = self::since( $days );
		$daily = self::daily_table();
		$found = self::search_table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Own tables, reports for the analytics screen.
		$totals   = (array) $wpdb->get_row( $wpdb->prepare( 'SELECT COALESCE(SUM(views),0) AS views, COALESCE(SUM(yes),0) AS yes, COALESCE(SUM(no),0) AS no FROM %i WHERE day >= %s', $daily, $since ), ARRAY_A );
		$searches = (array) $wpdb->get_row( $wpdb->prepare( 'SELECT COALESCE(SUM(times),0) AS times, COALESCE(SUM(CASE WHEN results = 0 THEN times ELSE 0 END),0) AS empty FROM %i WHERE day >= %s', $found, $since ), ARRAY_A );
		$by_day   = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT day, SUM(views) AS views FROM %i WHERE day >= %s GROUP BY day ORDER BY day', $daily, $since ), ARRAY_A );
		$top      = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT post_id, SUM(views) AS views, SUM(yes) AS yes, SUM(no) AS no FROM %i WHERE day >= %s GROUP BY post_id HAVING views > 0 ORDER BY views DESC LIMIT 10', $daily, $since ), ARRAY_A );
		$low      = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT post_id, SUM(yes) AS yes, SUM(no) AS no FROM %i WHERE day >= %s GROUP BY post_id HAVING no > 0 AND yes + no >= 3 ORDER BY no / (yes + no) DESC, no DESC LIMIT 10', $daily, $since ), ARRAY_A );
		$popular  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT search_key, MAX(search) AS search, SUM(times) AS times, MIN(results) AS results FROM %i WHERE day >= %s GROUP BY search_key ORDER BY times DESC LIMIT 10', $found, $since ), ARRAY_A );
		$empty    = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT search_key, MAX(search) AS search, SUM(times) AS times FROM %i WHERE day >= %s AND results = 0 GROUP BY search_key ORDER BY times DESC LIMIT 10', $found, $since ), ARRAY_A );
		$ai_table = self::ai_search_table();
		$ai_sum   = (array) $wpdb->get_row( $wpdb->prepare( 'SELECT COALESCE(SUM(times),0) AS times, COALESCE(SUM(CASE WHEN results = 0 THEN times ELSE 0 END),0) AS empty FROM %i WHERE day >= %s', $ai_table, $since ), ARRAY_A );
		$ai_reads = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(ai_reads),0) FROM %i WHERE day >= %s', $daily, $since ) );
		$ai_top   = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT search_key, MAX(search) AS search, SUM(times) AS times, MIN(results) AS results FROM %i WHERE day >= %s GROUP BY search_key ORDER BY times DESC LIMIT 10', $ai_table, $since ), ARRAY_A );
		$ai_empty = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT search_key, MAX(search) AS search, SUM(times) AS times FROM %i WHERE day >= %s AND results = 0 GROUP BY search_key ORDER BY times DESC LIMIT 10', $ai_table, $since ), ARRAY_A );
		$ai_read  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT post_id, SUM(ai_reads) AS times_read FROM %i WHERE day >= %s GROUP BY post_id HAVING times_read > 0 ORDER BY times_read DESC LIMIT 10', $daily, $since ), ARRAY_A );
		// phpcs:enable
		$series = array();
		$map    = array();
		foreach ( $by_day as $row ) {
			$map[ (string) $row['day'] ] = (int) $row['views'];
		}
		$now = time();
		for ( $t = strtotime( $since . ' 00:00:00 UTC' ); $t <= $now; $t += DAY_IN_SECONDS ) {
			$day            = gmdate( 'Y-m-d', $t );
			$series[ $day ] = isset( $map[ $day ] ) ? $map[ $day ] : 0;
		}
		$gaps = class_exists( '\QuantumCloud\Chatbot\Train\Gaps' ) ? (int) \QuantumCloud\Chatbot\Train\Gaps::open_count() : 0;
		return array(
			'totals'     => array(
				'views'    => (int) $totals['views'],
				'yes'      => (int) $totals['yes'],
				'no'       => (int) $totals['no'],
				'rate'     => self::rate( (int) $totals['yes'], (int) $totals['no'] ),
				'searches' => (int) $searches['times'],
				'empty'    => (int) $searches['empty'],
			),
			'daily'      => $series,
			'top'        => $top,
			'unhelpful'  => $low,
			'searches'   => $popular,
			'no_results' => $empty,
			'gaps'       => $gaps,
			'ai'         => array(
				'searches'   => (int) ( $ai_sum['times'] ?? 0 ),
				'empty'      => (int) ( $ai_sum['empty'] ?? 0 ),
				'reads'      => $ai_reads,
				'top'        => $ai_top,
				'no_results' => $ai_empty,
				'read'       => $ai_read,
			),
		);
	}

	/**
	 * Add to today's row of an article.
	 *
	 * @param int   $post_id Article id.
	 * @param array $change  Column => step (views, yes, no, ai_reads).
	 * @return void
	 */
	private static function add( $post_id, array $change ) {
		global $wpdb;
		if ( $post_id <= 0 ) {
			return;
		}
		$views = isset( $change['views'] ) ? (int) $change['views'] : 0;
		$yes   = isset( $change['yes'] ) ? (int) $change['yes'] : 0;
		$no    = isset( $change['no'] ) ? (int) $change['no'] : 0;
		$ai    = isset( $change['ai_reads'] ) ? (int) $change['ai_reads'] : 0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table; one upsert per event. GREATEST keeps a moved vote from going below zero.
		$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (day, post_id, views, yes, no, ai_reads) VALUES (%s, %d, %d, %d, %d, %d) ON DUPLICATE KEY UPDATE views = GREATEST(0, CAST(views AS SIGNED) + %d), yes = GREATEST(0, CAST(yes AS SIGNED) + %d), no = GREATEST(0, CAST(no AS SIGNED) + %d), ai_reads = ai_reads + %d', self::daily_table(), self::today(), $post_id, max( 0, $views ), max( 0, $yes ), max( 0, $no ), max( 0, $ai ), $views, $yes, $no, max( 0, $ai ) ) );
	}

	/**
	 * Today in UTC.
	 *
	 * @return string
	 */
	private static function today() {
		return gmdate( 'Y-m-d' );
	}
}
