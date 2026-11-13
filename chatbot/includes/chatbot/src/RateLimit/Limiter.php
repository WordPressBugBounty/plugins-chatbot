<?php
/**
 * Counts visitor actions per IP address in fixed windows (transients).
 *
 * Default: 60 AI replies per guest IP per day (qcld_wpbot_chat_ai_day_limit,
 * 0 turns it off). Logged-in users are not limited here; the legacy per-user
 * limit (is_rate_limiting_enabled) keeps working on top.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\RateLimit;

use QuantumCloud\Chatbot\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Rate limiter.
 */
final class Limiter {

	/**
	 * Buckets this request already counted for the visitor.
	 *
	 * @var array<string,bool>
	 */
	private static $counted = array();


	/**
	 * Built-in limits: bucket => [ requests, window seconds ].
	 */
	const LIMITS = array(
		'ai'       => array( 60, DAY_IN_SECONDS ),
		'message'  => array( 300, HOUR_IN_SECONDS ),
		'session'  => array( 60, HOUR_IN_SECONDS ),
		'lead'     => array( 10, HOUR_IN_SECONDS ),
		'feedback' => array( 30, HOUR_IN_SECONDS ),
		'report'   => array( 5, HOUR_IN_SECONDS ),
		'token'    => array( 120, HOUR_IN_SECONDS ),
		'action'   => array( 10, HOUR_IN_SECONDS ),
		'ability'  => array( 30, HOUR_IN_SECONDS ),
		'lookup'   => array( 120, HOUR_IN_SECONDS ),
	);

	/**
	 * Limit for a bucket: [ max, window ]. max 0 = unlimited.
	 *
	 * @param string $bucket Bucket.
	 * @return int[]
	 */
	public static function limit( $bucket ) {
		$limit = isset( self::LIMITS[ $bucket ] ) ? self::LIMITS[ $bucket ] : array( 60, HOUR_IN_SECONDS );
		if ( 'ai' === $bucket ) {
			$limit[0] = max( 0, (int) Options::get( 'qcld_wpbot_chat_ai_day_limit', 60 ) );
		}
		/**
		 * Rate limit of a visitor action.
		 *
		 * @param int[]  $limit  [ requests, window seconds ]; 0 requests = unlimited.
		 * @param string $bucket ai, message, session, lead, feedback, report, token.
		 */
		$limit = apply_filters( 'qcld_wpbot_chat/rate_limit', $limit, $bucket );
		return array( max( 0, (int) $limit[0] ), max( 60, (int) $limit[1] ) );
	}

	/**
	 * Count one hit; false when the limit was already reached.
	 *
	 * @param string      $bucket Bucket.
	 * @param string|null $who    Key (default: the visitor's IP; logged-in users are not limited).
	 * @return bool
	 */
	public static function hit( $bucket, $who = null ) {
		$allowed = self::count( $bucket, $who );
		if ( $allowed && null === $who ) {
			self::$counted[ $bucket ] = true;
		}
		return $allowed;
	}

	/**
	 * Whether this request's visitor hit was already counted for a bucket (the legacy AJAX guard
	 * counts the question before the engine runs; the engine then must not count it again).
	 *
	 * @param string $bucket Bucket.
	 * @return bool
	 */
	public static function counted( $bucket ) {
		return ! empty( self::$counted[ $bucket ] );
	}

	/**
	 * Forget what this request counted (tests, long-running processes).
	 *
	 * @return void
	 */
	public static function reset_counted() {
		self::$counted = array();
	}

	/**
	 * Count one hit; false when the limit was already reached.
	 *
	 * @param string      $bucket Bucket.
	 * @param string|null $who    Key (default: the visitor's IP; logged-in users are not limited).
	 * @return bool
	 */
	private static function count( $bucket, $who ) {
		if ( 'ai' === $bucket && null === $who && Role_Limits::enabled() && ! Role_Limits::hit( self::ip() ) ) {
			// The owner's ChatBot rate limits per role (AI Settings > Limits and cost) come on top of
			// the built-in visitor limit below; a role left at 0 never removes that limit.
			return false;
		}
		if ( null === $who && function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			return true;
		}
		list( $max, $window ) = self::limit( $bucket );
		if ( 0 === $max ) {
			return true;
		}
		$key   = self::key( $bucket, null === $who ? self::ip() : (string) $who, $window );
		$count = (int) get_transient( $key );
		if ( $count >= $max ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}

	/**
	 * Hits left in the current window (for the old "useage" field).
	 *
	 * @param string      $bucket Bucket.
	 * @param string|null $who    Key (default: the visitor's IP).
	 * @return int|null Null when unlimited.
	 */
	public static function left( $bucket, $who = null ) {
		list( $max, $window ) = self::limit( $bucket );
		if ( 0 === $max ) {
			return null;
		}
		return max( 0, $max - (int) get_transient( self::key( $bucket, null === $who ? self::ip() : (string) $who, $window ) ) );
	}

	/**
	 * Transient key: bucket, hashed visitor, window number.
	 *
	 * @param string $bucket Bucket.
	 * @param string $who    Visitor.
	 * @param int    $window Window seconds.
	 * @return string
	 */
	public static function key( $bucket, $who, $window ) {
		return 'qcld_wpbot_chat_rl_' . substr( md5( $bucket . '|' . $who ), 0, 16 ) . '_' . (int) floor( time() / $window );
	}

	/**
	 * Visitor IP (REMOTE_ADDR; proxies can change it through the filter).
	 *
	 * @return string
	 */
	public static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		/**
		 * Visitor IP used for rate limits (for sites behind a trusted proxy).
		 *
		 * @param string $ip REMOTE_ADDR.
		 */
		return (string) apply_filters( 'qcld_wpbot_chat/visitor_ip', $ip );
	}
}
