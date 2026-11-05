<?php
/**
 * Caches AI answers for identical requests, so repeats cost nothing.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

use QuantumCloud\WPBotCore\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Result cache in the ai_cache table.
 */
final class Result_Cache {

	/**
	 * How long a cached answer is reused.
	 */
	const TTL_DAYS = 30;

	/**
	 * Cache key for a request on a model.
	 *
	 * @param string  $module   Module id.
	 * @param string  $feature  Feature id.
	 * @param string  $provider Provider id.
	 * @param string  $model    Model id.
	 * @param Request $request  Request.
	 * @return string
	 */
	public static function key( $module, $feature, $provider, $model, Request $request ) {
		return hash( 'sha256', implode( '|', array( $module, $feature, $provider, $model, $request->fingerprint() ) ) );
	}

	/**
	 * Cached response or null.
	 *
	 * @param string $hash Key.
	 * @return Response|null
	 */
	public static function get( $hash ) {
		global $wpdb;
		$table = Schema::table( Schema::CACHE );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			$wpdb->prepare(
				"SELECT result, model FROM {$table} WHERE hash = %s AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from code.
				$hash,
				gmdate( 'Y-m-d H:i:s', time() - self::TTL_DAYS * DAY_IN_SECONDS )
			),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		$data = json_decode( (string) $row['result'], true );
		if ( ! is_array( $data ) || ! isset( $data['text'] ) ) {
			return null;
		}
		$response           = new Response();
		$response->text     = (string) $data['text'];
		$response->provider = isset( $data['provider'] ) ? (string) $data['provider'] : '';
		$response->sources  = isset( $data['sources'] ) && is_array( $data['sources'] ) ? $data['sources'] : array();
		$response->model    = (string) $row['model'];
		$response->cached   = true;
		return $response;
	}

	/**
	 * Store a response.
	 *
	 * @param string   $hash     Key.
	 * @param string   $module   Module id.
	 * @param string   $feature  Feature id.
	 * @param Response $response Response.
	 * @return void
	 */
	public static function put( $hash, $module, $feature, Response $response ) {
		global $wpdb;
		$table = Schema::table( Schema::CACHE );
		// REPLACE keeps one row per hash.
		$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table, write.
			$table,
			array(
				'hash'       => $hash,
				'module'     => substr( $module, 0, 40 ),
				'feature'    => substr( $feature, 0, 60 ),
				'model'      => substr( $response->model, 0, 120 ),
				'result'     => wp_json_encode(
					array(
						'text'     => $response->text,
						'provider' => $response->provider,
						'sources'  => $response->sources,
					)
				),
				'tokens'     => (int) ( $response->tokens_in + $response->tokens_out ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Delete expired rows (called from the daily job sweep).
	 *
	 * @return void
	 */
	public static function prune() {
		global $wpdb;
		$table = Schema::table( Schema::CACHE );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table maintenance.
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from code.
				gmdate( 'Y-m-d H:i:s', time() - self::TTL_DAYS * DAY_IN_SECONDS )
			)
		);
	}
}
