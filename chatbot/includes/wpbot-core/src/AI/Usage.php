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
use QuantumCloud\WPBotCore\Spend\Spend;
use QuantumCloud\WPBotCore\Spend\Usage_Reader;
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
	 * Store one call (through Spend, which adds the group, cached and reasoning tokens, the
	 * daily totals and the alert check). The response's cost becomes the recorded estimate.
	 *
	 * @param string   $module  Module id.
	 * @param string   $feature Feature id (for example "outline").
	 * @param Response $response Response.
	 * @param int      $job_id  Job id or 0.
	 * @return void
	 */
	public static function record( $module, $feature, Response $response, $job_id = 0 ) {
		$result         = Spend::record( $module, $feature, self::usage_of( $response ), self::meta_of( $response, $job_id ) );
		$response->cost = $result['cost'];
	}

	/**
	 * The usage counts of a response, in Spend's shape.
	 *
	 * @param Response $response Response.
	 * @return array
	 */
	public static function usage_of( Response $response ) {
		$usage                       = Usage_Reader::blank();
		$usage['tokens_in']          = (int) $response->tokens_in;
		$usage['tokens_out']         = (int) $response->tokens_out;
		$usage['tokens_cached']      = (int) $response->tokens_cached;
		$usage['tokens_cache_write'] = (int) $response->tokens_cache_write;
		$usage['tokens_reasoning']   = (int) $response->tokens_reasoning;
		$usage['web_searches']       = (int) $response->web_searches;
		$usage['provider_cost']      = $response->provider_cost;
		return $usage;
	}

	/**
	 * Spend::record() meta of a response.
	 *
	 * @param Response $response Response.
	 * @param int      $job_id   Job id.
	 * @return array
	 */
	private static function meta_of( Response $response, $job_id ) {
		$meta = array(
			'provider' => (string) $response->provider,
			'model'    => (string) $response->model,
			'job_id'   => (int) $job_id,
		);
		// A caller that priced a call without tokens (an image at a fixed price) keeps its cost.
		if ( 0 === (int) $response->tokens_in + (int) $response->tokens_out && (float) $response->cost > 0 ) {
			$meta['cost'] = (float) $response->cost;
		}
		return $meta;
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
		$value = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table, fresh value needed.
			$wpdb->prepare(
				"SELECT SUM(cost_estimate) FROM {$table} WHERE module = %s AND day >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from code.
				$module,
				current_time( 'Y-m-01' )
			)
		);
		return (float) $value;
	}

	/**
	 * Whether the module may not use AI now: its spending group's limit or the global limit is
	 * reached, or the owner paused its group (AI Settings > Spending and limits).
	 *
	 * @param string $module Module id.
	 * @return bool
	 */
	public static function budget_reached( $module ) {
		return is_wp_error( Spend::allow( $module, '' ) );
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
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$by_month = $wpdb->get_results( "SELECT DATE_FORMAT(day, '%Y-%m') AS month, COUNT(*) AS calls, SUM(tokens_in) AS tokens_in, SUM(tokens_out) AS tokens_out, SUM(cost_estimate) AS cost FROM {$table} WHERE {$where} GROUP BY month ORDER BY month DESC", ARRAY_A );
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$by_feature = $wpdb->get_results( "SELECT module, feature, COUNT(*) AS calls, SUM(cost_estimate) AS cost FROM {$table} WHERE {$where} GROUP BY module, feature ORDER BY cost DESC LIMIT 50", ARRAY_A );
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$by_model = $wpdb->get_results( "SELECT provider, model, COUNT(*) AS calls, SUM(tokens_in) AS tokens_in, SUM(tokens_out) AS tokens_out, SUM(cost_estimate) AS cost FROM {$table} WHERE {$where} GROUP BY provider, model ORDER BY cost DESC LIMIT 50", ARRAY_A );
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
