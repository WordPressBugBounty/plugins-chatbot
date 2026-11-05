<?php
/**
 * Per-API request counters, so jobs pause before a provider's limits.
 *
 * Counts requests per API per day (site time zone for Google's daily
 * quotas, which reset at midnight Pacific; a day boundary in either zone
 * keeps us under the limit because the default limits leave headroom) and
 * per minute. Limits are filterable.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Request counters per API.
 */
final class Quota {

	/**
	 * Option holding the counters (autoload off).
	 */
	const OPTION = 'qcld_wpbot_core_quota';

	/**
	 * Default limits: requests per day and per minute, kept below the
	 * providers' published limits.
	 *
	 * @return array<string,array{day:int,minute:int}>
	 */
	public static function limits() {
		$limits = array(
			// Search Console: 1,200 queries a minute per site; no daily cap published.
			'gsc_query'   => array(
				'day'    => 50000,
				'minute' => 1000,
			),
			// URL Inspection: 2,000 a day and 600 a minute per site.
			'gsc_inspect' => array(
				'day'    => 1900,
				'minute' => 500,
			),
			// GA4 Data API: counted in requests here; tokens are watched from the response.
			'ga4'         => array(
				'day'    => 2000,
				'minute' => 50,
			),
			// PageSpeed Insights: 25,000 a day, 400 per 100 seconds.
			'pagespeed'   => array(
				'day'    => 24000,
				'minute' => 200,
			),
			'bing'        => array(
				'day'    => 9000,
				'minute' => 50,
			),
			'kwdata'      => array(
				'day'    => 5000,
				'minute' => 10,
			),
		);
		return (array) apply_filters( 'qcld_wpbot_core/quota_limits', $limits );
	}

	/**
	 * Count one request, or refuse when a limit would be passed.
	 *
	 * @param string $api   API id (see limits()).
	 * @param int    $count Requests to count.
	 * @return true|WP_Error WP_Error carries retryable = true and retry_after seconds.
	 */
	public static function take( $api, $count = 1 ) {
		$api    = sanitize_key( $api );
		$limits = self::limits();
		if ( ! isset( $limits[ $api ] ) ) {
			return true;
		}
		$state  = self::state();
		$day    = gmdate( 'Y-m-d' );
		$minute = (int) floor( time() / 60 );
		$row    = isset( $state[ $api ] ) && is_array( $state[ $api ] ) ? $state[ $api ] : array();
		if ( ! isset( $row['day'] ) || $row['day'] !== $day ) {
			$row['day']       = $day;
			$row['day_count'] = 0;
		}
		if ( ! isset( $row['minute'] ) || (int) $row['minute'] !== $minute ) {
			$row['minute']       = $minute;
			$row['minute_count'] = 0;
		}
		if ( (int) $row['day_count'] + $count > (int) $limits[ $api ]['day'] ) {
			return new WP_Error(
				'qcld_wpbot_core_quota_day',
				__( 'The daily limit for this service was reached. The job continues tomorrow.', 'chatbot' ),
				array(
					'status'      => 429,
					'retryable'   => true,
					'retry_after' => DAY_IN_SECONDS - ( time() % DAY_IN_SECONDS ),
				)
			);
		}
		if ( (int) $row['minute_count'] + $count > (int) $limits[ $api ]['minute'] ) {
			return new WP_Error(
				'qcld_wpbot_core_quota_minute',
				__( 'Too many requests this minute. The job continues shortly.', 'chatbot' ),
				array(
					'status'      => 429,
					'retryable'   => true,
					'retry_after' => 60 - ( time() % 60 ),
				)
			);
		}
		$row['day_count']    = (int) $row['day_count'] + $count;
		$row['minute_count'] = (int) $row['minute_count'] + $count;
		$state[ $api ]       = $row;
		update_option( self::OPTION, $state, false );
		return true;
	}

	/**
	 * Requests used today per API.
	 *
	 * @return array<string,int>
	 */
	public static function today() {
		$out = array();
		$day = gmdate( 'Y-m-d' );
		foreach ( self::state() as $api => $row ) {
			$out[ $api ] = is_array( $row ) && isset( $row['day'] ) && $row['day'] === $day ? (int) $row['day_count'] : 0;
		}
		return $out;
	}

	/**
	 * Stored counters.
	 *
	 * @return array
	 */
	private static function state() {
		$state = get_option( self::OPTION, array() );
		return is_array( $state ) ? $state : array();
	}
}
