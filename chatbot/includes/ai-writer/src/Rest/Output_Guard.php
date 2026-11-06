<?php
/**
 * Keeps AI Writer's REST answers clean JSON.
 *
 * Anything printed while one of AI Writer's routes runs (a PHP notice from
 * any plugin when display_errors is on, stray echo) would land in front of
 * the JSON and the screen would only say "The response is not a valid JSON
 * response". The guard catches that output, writes it to the debug log
 * when logging is on, and drops it from the answer. It also gives AI calls
 * more time on hosts where PHP's time limit counts waiting.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Rest;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Output guard for wpbot-writer/v1 routes (Free and Pro).
 */
final class Output_Guard {

	/**
	 * Output-buffer level opened by the guard (0 when none).
	 *
	 * @var int
	 */
	private static $level = 0;

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'start' ), 0, 3 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'finish' ), PHP_INT_MAX, 3 );
	}

	/**
	 * Whether a request is one of AI Writer's routes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private static function ours( $request ) {
		return $request instanceof WP_REST_Request && 0 === strpos( ltrim( (string) $request->get_route(), '/' ), 'wpbot-writer/' );
	}

	/**
	 * Start catching output before the route runs.
	 *
	 * @param mixed           $result  Earlier result (passed through).
	 * @param mixed           $server  REST server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function start( $result, $server, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- Filter signature.
		if ( null !== $result || ! self::ours( $request ) || self::$level ) {
			return $result;
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Disabled on some hosts; failure is fine.
		}
		ob_start();
		self::$level = ob_get_level();
		return $result;
	}

	/**
	 * Drop caught output (logging it) before the answer is sent.
	 *
	 * @param mixed           $response Response (passed through).
	 * @param mixed           $server   REST server.
	 * @param WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public static function finish( $response, $server, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- Filter signature.
		if ( ! self::$level || ! self::ours( $request ) ) {
			return $response;
		}
		$printed = '';
		while ( ob_get_level() >= self::$level ) {
			$printed .= (string) ob_get_clean();
		}
		self::$level = 0;
		if ( '' !== trim( $printed ) && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logged only when the site owner turned on WP_DEBUG_LOG.
			error_log( 'AI Writer: output printed during ' . $request->get_route() . ' was removed from the reply: ' . substr( wp_strip_all_tags( $printed ), 0, 500 ) );
		}
		return $response;
	}
}
