<?php
/**
 * Read side of spending: a group's details (the (i) panel) and the AI Usage report.
 *
 * Totals come from the daily table (kept 13 months); single runs and last-run times come from
 * the per-call rows (kept 90 days).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Spend;

use QuantumCloud\WPBotCore\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Report queries.
 */
final class Report {

	/**
	 * Optional sections Pro adds (per channel, per provider, CSV).
	 *
	 * @return string[]
	 */
	public static function sections() {
		/**
		 * Extra report sections: 'channels', 'providers', 'csv' (WPBot Pro registers them).
		 *
		 * @param string[] $sections Sections.
		 */
		return array_values( array_intersect( array( 'channels', 'providers', 'csv' ), (array) apply_filters( 'qcld_wpbot_core/spend_report_sections', array() ) ) );
	}

	/**
	 * One group this period: tasks, the 10 most expensive runs and a 14-day trend.
	 *
	 * @param string $group Group id.
	 * @return array{group: array, tasks: array, top_runs: array, trend: array, channels?: array, providers?: array}
	 */
	public static function group( $group ) {
		global $wpdb;
		$status = Spend::status();
		$period = $status['period'];
		$entry  = isset( $status['groups'][ $group ] ) ? $status['groups'][ $group ] : Spend::entry( $group, $group, 0, 0, false );
		$daily  = Schema::table( Spend_Schema::DAILY );
		$usage  = Schema::table( Schema::USAGE );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own tables; values prepared; fresh values needed.
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$tasks = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT module, feature, SUM(calls) AS calls, SUM(tokens_in) AS tokens_in, SUM(tokens_out) AS tokens_out, SUM(images) AS images, SUM(unknown_price) AS unknown_price, SUM(cost) AS cost FROM {$daily} WHERE grp = %s AND day >= %s GROUP BY module, feature ORDER BY cost DESC LIMIT 50",
				$group,
				$period['start']
			),
			ARRAY_A
		);
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$last = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT module, feature, MAX(created_at) AS last_run, SUBSTRING_INDEX(GROUP_CONCAT(model ORDER BY id DESC), ',', 1) AS model FROM {$usage} WHERE grp = %s AND day >= %s GROUP BY module, feature",
				$group,
				$period['start']
			),
			ARRAY_A
		);
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$runs = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, created_at, module, feature, task, provider, model, channel, source, job_id, tokens_in, tokens_out, images, cost_estimate AS cost, price_known FROM {$usage} WHERE grp = %s AND day >= %s ORDER BY cost_estimate DESC, id DESC LIMIT 10",
				$group,
				$period['start']
			),
			ARRAY_A
		);
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$trend = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT day, SUM(calls) AS calls, SUM(cost) AS cost FROM {$daily} WHERE grp = %s AND day >= %s GROUP BY day ORDER BY day",
				$group,
				gmdate( 'Y-m-d', strtotime( substr( current_time( 'mysql' ), 0, 10 ) . ' -13 days' ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		$by_task = array();
		foreach ( $last as $row ) {
			$by_task[ $row['module'] . '/' . $row['feature'] ] = $row;
		}
		foreach ( $tasks as &$task ) {
			$key              = $task['module'] . '/' . $task['feature'];
			$task['label']    = self::task_label( $task['module'], $task['feature'] );
			$task['last_run'] = isset( $by_task[ $key ] ) ? (string) $by_task[ $key ]['last_run'] : '';
			$task['model']    = isset( $by_task[ $key ] ) ? (string) $by_task[ $key ]['model'] : '';
		}
		unset( $task );
		foreach ( $runs as &$run ) {
			$run['label'] = self::task_label( $run['module'], $run['feature'] );
		}
		unset( $run );

		$out = array(
			'group'    => $entry,
			'period'   => $period,
			'tasks'    => self::numbers( $tasks ),
			'top_runs' => self::numbers( $runs ),
			'trend'    => self::fill_days( self::numbers( $trend ), 14 ),
		);
		foreach ( array_intersect( array( 'channels', 'providers' ), self::sections() ) as $section ) {
			$out[ $section ] = self::breakdown( 'channels' === $section ? 'channel' : 'provider', $period['start'], '', $group );
		}
		return $out;
	}

	/**
	 * The AI Usage report for a date range.
	 *
	 * @param string $from First day (Y-m-d).
	 * @param string $to   Last day (Y-m-d), inclusive.
	 * @return array days, groups, features, models, totals, net (calls counted by the safety net),
	 *               unknown_price; channels and providers when Pro adds them.
	 */
	public static function report( $from, $to ) {
		global $wpdb;
		$daily = Schema::table( Spend_Schema::DAILY );
		$end   = gmdate( 'Y-m-d', strtotime( $to . ' +1 day' ) );
		$where = $wpdb->prepare( 'day >= %s AND day < %s', $from, $end );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own table; $where built with prepare().
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$days = (array) $wpdb->get_results( "SELECT day, SUM(calls) AS calls, SUM(cost) AS cost FROM {$daily} WHERE {$where} GROUP BY day ORDER BY day", ARRAY_A );
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$groups = (array) $wpdb->get_results( "SELECT grp, SUM(calls) AS calls, SUM(tokens_in) AS tokens_in, SUM(tokens_out) AS tokens_out, SUM(images) AS images, SUM(cost) AS cost FROM {$daily} WHERE {$where} GROUP BY grp ORDER BY cost DESC", ARRAY_A );
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$features = (array) $wpdb->get_results( "SELECT grp, module, feature, SUM(calls) AS calls, SUM(cost) AS cost FROM {$daily} WHERE {$where} GROUP BY grp, module, feature ORDER BY cost DESC LIMIT 100", ARRAY_A );
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$models = (array) $wpdb->get_results( "SELECT provider, model, SUM(calls) AS calls, SUM(tokens_in) AS tokens_in, SUM(tokens_out) AS tokens_out, SUM(unknown_price) AS unknown_price, SUM(cost) AS cost FROM {$daily} WHERE {$where} GROUP BY provider, model ORDER BY cost DESC LIMIT 100", ARRAY_A );
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$net = $wpdb->get_row( "SELECT SUM(calls) AS calls, SUM(cost) AS cost FROM {$daily} WHERE {$where} AND source = 'net'", ARRAY_A );
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$unknown = (int) $wpdb->get_var( "SELECT SUM(unknown_price) FROM {$daily} WHERE {$where}" );
		// phpcs:enable

		$labels = Features::all();
		foreach ( $groups as &$row ) {
			$row['label'] = isset( $labels[ $row['grp'] ] ) ? $labels[ $row['grp'] ] : $row['grp'];
		}
		unset( $row );
		foreach ( $features as &$row ) {
			$row['label'] = self::task_label( $row['module'], $row['feature'] );
		}
		unset( $row );

		$total = 0.0;
		$calls = 0;
		foreach ( $days as $day ) {
			$total += (float) $day['cost'];
			$calls += (int) $day['calls'];
		}
		$out = array(
			'from'          => $from,
			'to'            => $to,
			'days'          => self::numbers( $days ),
			'groups'        => self::numbers( $groups ),
			'features'      => self::numbers( $features ),
			'models'        => self::numbers( $models ),
			'totals'        => array(
				'cost'  => round( $total, 4 ),
				'calls' => $calls,
			),
			'net'           => array(
				'calls' => is_array( $net ) ? (int) $net['calls'] : 0,
				'cost'  => is_array( $net ) ? round( (float) $net['cost'], 4 ) : 0.0,
			),
			'unknown_price' => $unknown,
			'billing'       => Prices::billing_urls(),
		);
		foreach ( array_intersect( array( 'channels', 'providers' ), self::sections() ) as $section ) {
			$out[ $section ] = self::breakdown( 'channels' === $section ? 'channel' : 'provider', $from, $end, '' );
		}
		return $out;
	}

	/**
	 * Per-call rows for CSV export (no text of any conversation is stored, so none is exported).
	 *
	 * @param string $from First day.
	 * @param string $to   Last day, inclusive.
	 * @param int    $max  Row limit.
	 * @return array[]
	 */
	public static function rows( $from, $to, $max = 50000 ) {
		global $wpdb;
		$usage = Schema::table( Schema::USAGE );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; values prepared.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT created_at, grp, module, feature, provider, model, channel, source, job_id, tokens_in, tokens_out, tokens_cached, tokens_reasoning, images, web_searches, audio_seconds, characters, cost_estimate AS cost, price_known FROM {$usage} WHERE day >= %s AND day <= %s ORDER BY id LIMIT %d", $from, $to, min( 200000, max( 1, (int) $max ) ) ), ARRAY_A );
	}

	/**
	 * Spend per channel or provider.
	 *
	 * @param string $column channel or provider.
	 * @param string $from   First day.
	 * @param string $end    Day after the last ('' = open).
	 * @param string $group  Group ('' = all).
	 * @return array[]
	 */
	private static function breakdown( $column, $from, $end, $group ) {
		global $wpdb;
		$column = 'channel' === $column ? 'channel' : 'provider';
		$daily  = Schema::table( Spend_Schema::DAILY );
		$where  = $wpdb->prepare( 'day >= %s', $from );
		if ( '' !== $end ) {
			$where .= $wpdb->prepare( ' AND day < %s', $end );
		}
		if ( '' !== $group ) {
			$where .= $wpdb->prepare( ' AND grp = %s', $group );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; column from a fixed list; $where prepared.
		$rows = (array) $wpdb->get_results( "SELECT {$column} AS name, SUM(calls) AS calls, SUM(cost) AS cost FROM {$daily} WHERE {$where} AND {$column} <> '' GROUP BY {$column} ORDER BY cost DESC", ARRAY_A );
		return self::numbers( $rows );
	}

	/**
	 * Owner-facing name of a task.
	 *
	 * @param string $module  Module id.
	 * @param string $feature Feature id.
	 * @return string
	 */
	public static function task_label( $module, $feature ) {
		$label = ucfirst( str_replace( array( '_', '-' ), ' ', (string) $feature ) );
		if ( Net::MODULE === $module ) {
			/* translators: %s: folder of the code that made the call. */
			$label = sprintf( __( 'Not yet routed: %s', 'chatbot' ), substr( str_replace( '_', '/', (string) $feature ), 4 ) );
		}

		/**
		 * Owner-facing name of a task in the spending screens.
		 *
		 * @param string $label   Label.
		 * @param string $module  Module id.
		 * @param string $feature Feature id.
		 */
		return (string) apply_filters( 'qcld_wpbot_core/spend_task_label', $label, $module, $feature );
	}

	/**
	 * Fill missing days with zero (pure).
	 *
	 * @param array $rows Rows with day, calls, cost.
	 * @param int   $days How many days, ending today.
	 * @return array
	 */
	public static function fill_days( array $rows, $days ) {
		$by_day = array();
		foreach ( $rows as $row ) {
			$by_day[ substr( (string) $row['day'], 0, 10 ) ] = $row;
		}
		$today = strtotime( substr( current_time( 'mysql' ), 0, 10 ) . ' 00:00:00 UTC' );
		$out   = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day   = gmdate( 'Y-m-d', $today - $i * DAY_IN_SECONDS );
			$out[] = isset( $by_day[ $day ] ) ? $by_day[ $day ] : array(
				'day'   => $day,
				'calls' => 0,
				'cost'  => 0.0,
			);
		}
		return $out;
	}

	/**
	 * Cast numeric columns (pure).
	 *
	 * @param array $rows Rows.
	 * @return array
	 */
	private static function numbers( array $rows ) {
		foreach ( $rows as &$row ) {
			foreach ( array( 'calls', 'tokens_in', 'tokens_out', 'images', 'unknown_price', 'job_id', 'id', 'price_known' ) as $key ) {
				if ( isset( $row[ $key ] ) ) {
					$row[ $key ] = (int) $row[ $key ];
				}
			}
			if ( isset( $row['cost'] ) ) {
				$row['cost'] = round( (float) $row['cost'], 6 );
			}
		}
		unset( $row );
		return $rows;
	}
}
