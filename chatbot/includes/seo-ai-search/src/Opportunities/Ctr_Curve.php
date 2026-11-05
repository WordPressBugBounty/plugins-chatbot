<?php
/**
 * Expected click-through rate by Google position.
 *
 * A conservative curve for organic results with rich features on the page
 * (our own round numbers, below most published studies on purpose so the
 * "low click-through" rule only flags clear gaps). Filterable.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Opportunities;

defined( 'ABSPATH' ) || exit;

/**
 * Expected CTR lookups.
 */
final class Ctr_Curve {

	/**
	 * Expected CTR for positions 1–10; beyond 10 it falls to 0.5%.
	 *
	 * @return float[]
	 */
	public static function points() {
		$points = array(
			1  => 0.25,
			2  => 0.14,
			3  => 0.09,
			4  => 0.065,
			5  => 0.05,
			6  => 0.038,
			7  => 0.03,
			8  => 0.024,
			9  => 0.019,
			10 => 0.016,
		);
		return (array) apply_filters( 'qcld_wpbot_seo/ctr_curve', $points );
	}

	/**
	 * Expected CTR for an average position (interpolated).
	 *
	 * @param float $position Average position.
	 * @return float
	 */
	public static function expected( $position ) {
		$points   = self::points();
		$position = max( 1.0, (float) $position );
		if ( $position >= 20 ) {
			return 0.005;
		}
		if ( $position > 10 ) {
			// Page two: from position 10's value down to 0.5% at 20.
			$ten = isset( $points[10] ) ? (float) $points[10] : 0.016;
			return round( $ten + ( 0.005 - $ten ) * ( ( $position - 10 ) / 10 ), 4 );
		}
		$low  = (int) floor( $position );
		$high = min( 10, $low + 1 );
		$a    = isset( $points[ $low ] ) ? (float) $points[ $low ] : 0.016;
		$b    = isset( $points[ $high ] ) ? (float) $points[ $high ] : $a;
		return round( $a + ( $b - $a ) * ( $position - $low ), 4 );
	}
}
