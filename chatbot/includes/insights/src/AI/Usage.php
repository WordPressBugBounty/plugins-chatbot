<?php
/**
 * AI usage counters (tokens and requests per day and month, by feature)
 * and the owner's cost limits. Days and months follow the site's time zone.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\AI;

defined( 'ABSPATH' ) || exit;

final class Usage {

	const OPTION = 'qcin_ai_usage';

	/**
	 * Current counters, reset when the day or month has changed.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$day   = wp_date( 'Y-m-d' );
		$month = wp_date( 'Y-m' );
		$u     = get_option( self::OPTION, array() );
		$u     = is_array( $u ) ? $u : array();

		if ( ( $u['month'] ?? '' ) !== $month ) {
			$u['month']          = $month;
			$u['month_tokens']   = 0;
			$u['month_requests'] = 0;
			$u['features']       = array();
		}
		if ( ( $u['day'] ?? '' ) !== $day ) {
			$u['day']          = $day;
			$u['day_tokens']   = 0;
			$u['day_requests'] = 0;
		}
		foreach ( array( 'month_tokens', 'month_requests', 'day_tokens', 'day_requests' ) as $k ) {
			$u[ $k ] = (int) ( $u[ $k ] ?? 0 );
		}
		$u['features'] = is_array( $u['features'] ?? null ) ? $u['features'] : array();
		return $u;
	}

	/**
	 * Add one request.
	 *
	 * @param string $feature Feature id.
	 * @param int    $input   Input tokens.
	 * @param int    $output  Output tokens.
	 */
	public static function record( $feature, $input, $output ) {
		$u      = self::get();
		$tokens = max( 0, (int) $input ) + max( 0, (int) $output );
		$u['day_tokens']     += $tokens;
		$u['day_requests']   += 1;
		$u['month_tokens']   += $tokens;
		$u['month_requests'] += 1;

		$feature = sanitize_key( $feature );
		$f       = $u['features'][ $feature ] ?? array();
		$u['features'][ $feature ] = array(
			'requests' => (int) ( $f['requests'] ?? 0 ) + 1,
			'input'    => (int) ( $f['input'] ?? 0 ) + max( 0, (int) $input ),
			'output'   => (int) ( $f['output'] ?? 0 ) + max( 0, (int) $output ),
		);
		update_option( self::OPTION, $u, false );
	}

	/**
	 * Which limit is reached, if any.
	 *
	 * @return array{code:string,message:string}|null
	 */
	public static function limit_reached() {
		$u      = self::get();
		$limits = AI_Settings::all()['limits'];

		if ( $limits['daily_requests'] > 0 && $u['day_requests'] >= $limits['daily_requests'] ) {
			return array(
				'code'    => 'daily_requests',
				'message' => __( 'The daily limit of AI requests is reached. AI features resume tomorrow, or you can raise the limit in Settings.', 'chatbot' ),
			);
		}
		if ( $limits['daily_tokens'] > 0 && $u['day_tokens'] >= $limits['daily_tokens'] ) {
			return array(
				'code'    => 'daily_tokens',
				'message' => __( 'The daily AI token limit is reached. AI features resume tomorrow, or you can raise the limit in Settings.', 'chatbot' ),
			);
		}
		if ( $limits['monthly_tokens'] > 0 && $u['month_tokens'] >= $limits['monthly_tokens'] ) {
			return array(
				'code'    => 'monthly_tokens',
				'message' => __( 'The monthly AI token limit is reached. AI features resume next month, or you can raise the limit in Settings.', 'chatbot' ),
			);
		}
		return null;
	}

	/**
	 * Usage for the admin screen.
	 *
	 * @return array<string,mixed>
	 */
	public static function report() {
		$u     = self::get();
		$limit = self::limit_reached();
		return array(
			'day'      => array(
				'tokens'   => $u['day_tokens'],
				'requests' => $u['day_requests'],
			),
			'month'    => array(
				'label'    => wp_date( 'F Y' ),
				'tokens'   => $u['month_tokens'],
				'requests' => $u['month_requests'],
			),
			'features' => $u['features'],
			'paused'   => $limit ? $limit['message'] : '',
		);
	}
}
