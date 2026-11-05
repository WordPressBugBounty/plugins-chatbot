<?php
/**
 * Date windows over Search Console's final data (which lags ~3 days).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Api;

use QuantumCloud\SeoAiSearch\Google\Gsc_Pull;

defined( 'ABSPATH' ) || exit;

/**
 * Current and previous periods.
 */
final class Period {

	/**
	 * The last N days of final data and the N days before.
	 *
	 * @param int    $days  Length.
	 * @param string $today Y-m-d (site time); default today.
	 * @return array{start:string,end:string,prev_start:string,prev_end:string}
	 */
	public static function last( $days = 28, $today = '' ) {
		$days  = max( 1, min( 480, (int) $days ) );
		$today = '' !== $today ? $today : current_time( 'Y-m-d' );
		$end   = new \DateTimeImmutable( $today . ' -' . Gsc_Pull::LAG . ' days' );
		$start = $end->modify( '-' . ( $days - 1 ) . ' days' );
		return array(
			'start'      => $start->format( 'Y-m-d' ),
			'end'        => $end->format( 'Y-m-d' ),
			'prev_start' => $start->modify( '-' . $days . ' days' )->format( 'Y-m-d' ),
			'prev_end'   => $start->modify( '-1 day' )->format( 'Y-m-d' ),
		);
	}
}
