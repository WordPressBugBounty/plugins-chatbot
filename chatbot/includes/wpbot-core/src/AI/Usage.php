<?php
/**
 * Records AI usage per call and checks monthly budgets.
 *
 * Costs are estimates from the price list in Providers; the usage page says so.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

use QuantumCloud\WPBotCore\Settings;
use QuantumCloud\WPBotCore\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Usage log and budget checks.
 */
final class Usage {

	/**
	 * Estimated cost of a number of tokens on a model.
	 *
	 * @param string $provider   Provider id.
	 * @param string $model      Model id.
	 * @param int    $tokens_in  Input tokens.
	 * @param int    $tokens_out Output tokens.
	 * @return float USD.
	 */
	public static function estimate( $provider, $model, $tokens_in, $tokens_out ) {
		$info = Providers::model_info( $provider, $model );
		return ( $tokens_in * $info['price_in'] + $tokens_out * $info['price_out'] ) / 1000000;
	}

	/**
	 * Store one call.
	 *
	 * @param string   $module  Module id.
	 * @param string   $feature Feature id (for example "outline").
	 * @param Response $response Response.
	 * @param int      $job_id  Job id or 0.
	 * @return void
	 */
	public static function record( $module, $feature, Response $response, $job_id = 0 ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own table, write.
			Schema::table( Schema::USAGE ),
			array(
				'day'           => current_time( 'Y-m-d' ),
				'module'        => substr( sanitize_key( $module ), 0, 40 ),
				'feature'       => substr( sanitize_key( $feature ), 0, 60 ),
				'provider'      => substr( $response->provider, 0, 40 ),
				'model'         => substr( $response->model, 0, 120 ),
				'job_id'        => (int) $job_id,
				'user_id'       => get_current_user_id(),
				'tokens_in'     => (int) $response->tokens_in,
				'tokens_out'    => (int) $response->tokens_out,
				'cost_estimate' => (float) $response->cost,
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%f', '%s' )
		);
	}

	/**
	 * Estimated spend of a module in the current month.
	 *
	 * @param string $module Module id.
	 * @return float USD.
	 */
	public static function month_cost( $module ) {
		global $wpdb;
		$table = Schema::table( Schema::USAGE );
		$value = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, fresh value needed.
			$wpdb->prepare(
				"SELECT SUM(cost_estimate) FROM {$table} WHERE module = %s AND day >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from code.
				$module,
				current_time( 'Y-m-01' )
			)
		);
		return (float) $value;
	}

	/**
	 * Whether the module's monthly budget is used up.
	 *
	 * @param string $module Module id.
	 * @return bool
	 */
	public static function budget_reached( $module ) {
		$budget = Settings::budget( $module );
		return $budget > 0 && self::month_cost( $module ) >= $budget;
	}

	/**
	 * Usage summary for the usage page.
	 *
	 * @param string $module Module id ('' for all).
	 * @param int    $months How many months back, including this one.
	 * @return array{months: array, features: array, models: array, month_cost: float, budget: float}
	 */
	public static function summary( $module = '', $months = 6 ) {
		global $wpdb;
		$table = Schema::table( Schema::USAGE );
		$since = gmdate( 'Y-m-01', strtotime( '-' . max( 0, (int) $months - 1 ) . ' months', strtotime( current_time( 'Y-m-01' ) ) ) );
		$where = $wpdb->prepare( 'day >= %s', $since );
		if ( '' !== $module ) {
			$where .= $wpdb->prepare( ' AND module = %s', $module );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table from code; $where built with prepare().
		$by_month   = $wpdb->get_results( "SELECT DATE_FORMAT(day, '%Y-%m') AS month, COUNT(*) AS calls, SUM(tokens_in) AS tokens_in, SUM(tokens_out) AS tokens_out, SUM(cost_estimate) AS cost FROM {$table} WHERE {$where} GROUP BY month ORDER BY month DESC", ARRAY_A );
		$by_feature = $wpdb->get_results( "SELECT module, feature, COUNT(*) AS calls, SUM(cost_estimate) AS cost FROM {$table} WHERE {$where} GROUP BY module, feature ORDER BY cost DESC LIMIT 50", ARRAY_A );
		$by_model   = $wpdb->get_results( "SELECT provider, model, COUNT(*) AS calls, SUM(tokens_in) AS tokens_in, SUM(tokens_out) AS tokens_out, SUM(cost_estimate) AS cost FROM {$table} WHERE {$where} GROUP BY provider, model ORDER BY cost DESC LIMIT 50", ARRAY_A );
		// phpcs:enable

		return array(
			'months'     => self::numbers( (array) $by_month ),
			'features'   => self::numbers( (array) $by_feature ),
			'models'     => self::numbers( (array) $by_model ),
			'month_cost' => '' !== $module ? self::month_cost( $module ) : 0.0,
			'budget'     => '' !== $module ? Settings::budget( $module ) : 0.0,
		);
	}

	/**
	 * Cast numeric columns so JSON has numbers, not strings.
	 *
	 * @param array $rows Rows.
	 * @return array
	 */
	private static function numbers( array $rows ) {
		foreach ( $rows as &$row ) {
			foreach ( array( 'calls', 'tokens_in', 'tokens_out' ) as $int_key ) {
				if ( isset( $row[ $int_key ] ) ) {
					$row[ $int_key ] = (int) $row[ $int_key ];
				}
			}
			if ( isset( $row['cost'] ) ) {
				$row['cost'] = round( (float) $row['cost'], 4 );
			}
		}
		return $rows;
	}
}
