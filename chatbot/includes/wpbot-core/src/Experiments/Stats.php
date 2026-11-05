<?php
/**
 * Experiment statistics (round 3, P4): a two-proportion z-test against the
 * control, a sample-size based duration estimate, and an honest
 * assessment in plain words. Pure functions, no WordPress state.
 *
 * Several variants are each compared with the control; the significance
 * level is split between the comparisons (Bonferroni), so four versions
 * need clearer differences than two. No winner is ever named below the
 * minimum sample per variant.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Experiments;

defined( 'ABSPATH' ) || exit;

/**
 * Statistics helpers.
 */
final class Stats {

	/** Two-sided significance level for a clear result. */
	const ALPHA = 0.05;

	/** Power used for duration estimates. */
	const POWER_Z = 0.8416;

	/**
	 * Standard normal cumulative distribution (Abramowitz and Stegun 7.1.26
	 * approximation of erf; error below 1.5e-7).
	 *
	 * @param float $x Value.
	 * @return float
	 */
	public static function normal_cdf( $x ) {
		$sign = $x < 0 ? -1 : 1;
		$z    = abs( $x ) / sqrt( 2 );
		$t    = 1 / ( 1 + 0.3275911 * $z );
		$poly = ( ( ( ( 1.061405429 * $t - 1.453152027 ) * $t ) + 1.421413741 ) * $t - 0.284496736 ) * $t + 0.254829592;
		$erf  = 1 - $poly * $t * exp( -$z * $z );
		return 0.5 * ( 1 + $sign * $erf );
	}

	/**
	 * Inverse of the standard normal distribution for the common levels
	 * used here (bisection; accurate to 1e-6).
	 *
	 * @param float $p Probability (0-1).
	 * @return float
	 */
	public static function normal_quantile( $p ) {
		$p    = max( 1e-9, min( 1 - 1e-9, (float) $p ) );
		$low  = -10.0;
		$high = 10.0;
		for ( $i = 0; $i < 80; $i++ ) {
			$mid = ( $low + $high ) / 2;
			if ( self::normal_cdf( $mid ) < $p ) {
				$low = $mid;
			} else {
				$high = $mid;
			}
		}
		return ( $low + $high ) / 2;
	}

	/**
	 * Two-proportion z-test of B against A.
	 *
	 * @param int $conv_a Conversions of A.
	 * @param int $n_a    Exposures of A.
	 * @param int $conv_b Conversions of B.
	 * @param int $n_b    Exposures of B.
	 * @return array{rate_a: float, rate_b: float, lift: float, z: float, p: float}
	 */
	public static function compare( $conv_a, $n_a, $conv_b, $n_b ) {
		$n_a    = max( 0, (int) $n_a );
		$n_b    = max( 0, (int) $n_b );
		$rate_a = $n_a ? min( 1, max( 0, (int) $conv_a ) / $n_a ) : 0.0;
		$rate_b = $n_b ? min( 1, max( 0, (int) $conv_b ) / $n_b ) : 0.0;
		$pool   = $n_a + $n_b ? ( $rate_a * $n_a + $rate_b * $n_b ) / ( $n_a + $n_b ) : 0.0;
		$se     = $n_a && $n_b ? sqrt( $pool * ( 1 - $pool ) * ( 1 / $n_a + 1 / $n_b ) ) : 0.0;
		$z      = $se > 0 ? ( $rate_b - $rate_a ) / $se : 0.0;
		return array(
			'rate_a' => $rate_a,
			'rate_b' => $rate_b,
			'lift'   => $rate_a > 0 ? ( $rate_b - $rate_a ) / $rate_a : 0.0,
			'z'      => $z,
			'p'      => $se > 0 ? 2 * ( 1 - self::normal_cdf( abs( $z ) ) ) : 1.0,
		);
	}

	/**
	 * Visitors needed per variant to see a relative lift with 80% power.
	 *
	 * @param float $base_rate Expected rate of the control (0-1).
	 * @param float $lift      Relative lift to detect (0.2 = 20%).
	 * @param int   $variants  Number of variants (splits alpha between comparisons).
	 * @return int
	 */
	public static function sample_size( $base_rate, $lift = 0.2, $variants = 2 ) {
		$p1    = max( 0.001, min( 0.95, (float) $base_rate ) );
		$p2    = min( 0.999, $p1 * ( 1 + max( 0.01, (float) $lift ) ) );
		$alpha = self::ALPHA / max( 1, (int) $variants - 1 );
		$za    = self::normal_quantile( 1 - $alpha / 2 );
		$n     = pow( $za + self::POWER_Z, 2 ) * ( $p1 * ( 1 - $p1 ) + $p2 * ( 1 - $p2 ) ) / pow( $p2 - $p1, 2 );
		return (int) ceil( $n );
	}

	/**
	 * How long a test needs at the current traffic.
	 *
	 * @param float $daily     Visitors (or impressions) per day that see the test.
	 * @param int   $variants  Variants.
	 * @param float $base_rate Expected rate of the control.
	 * @param int   $max_days  Longest run allowed.
	 * @param int   $min       Minimum exposures per variant.
	 * @return array{per_variant: int, days: int|null, fits: bool}
	 */
	public static function duration( $daily, $variants, $base_rate, $max_days, $min = 100 ) {
		$variants = max( 2, (int) $variants );
		$need     = max( (int) $min, self::sample_size( $base_rate, 0.2, $variants ) );
		$daily    = (float) $daily;
		$days     = $daily > 0 ? (int) ceil( $need * $variants / $daily ) : null;
		return array(
			'per_variant' => $need,
			'days'        => $days,
			'fits'        => null !== $days && $days <= (int) $max_days,
		);
	}

	/**
	 * Assess a test.
	 *
	 * @param array[] $variants  Each: id, n (exposures), conv (conversions), control (bool). Control first is not required.
	 * @param int     $min       Minimum exposures per variant before any verdict.
	 * @param bool    $ended     The maximum run time is over.
	 * @param float   $days_run  Days the test has run (for the traffic projection).
	 * @param int     $max_days  Maximum run time.
	 * @return array state (collecting|low_traffic|clear|no_difference|not_enough_data), winner (variant id or 0),
	 *               confidence (0-1), comparisons (per variant), message.
	 */
	public static function assess( array $variants, $min, $ended, $days_run = 0.0, $max_days = 30 ) {
		$control = null;
		foreach ( $variants as $variant ) {
			if ( ! empty( $variant['control'] ) ) {
				$control = $variant;
				break;
			}
		}
		if ( ! $control && $variants ) {
			$control = reset( $variants );
		}
		$alpha       = self::ALPHA / max( 1, count( $variants ) - 1 );
		$min         = max( 1, (int) $min );
		$enough      = true;
		$comparisons = array();
		$best        = null;
		foreach ( $variants as $variant ) {
			if ( (int) $variant['n'] < $min ) {
				$enough = false;
			}
			if ( $control && (int) $variant['id'] === (int) $control['id'] ) {
				continue;
			}
			$cmp                                 = self::compare( $control['conv'], $control['n'], $variant['conv'], $variant['n'] );
			$cmp['id']                           = (int) $variant['id'];
			$comparisons[ (int) $variant['id'] ] = $cmp;
			if ( $cmp['p'] < $alpha && ( null === $best || abs( $cmp['z'] ) > abs( $best['z'] ) ) ) {
				$best = $cmp;
			}
		}
		$out = array(
			'state'       => 'collecting',
			'winner'      => 0,
			'confidence'  => 0.0,
			'comparisons' => $comparisons,
			'alpha'       => $alpha,
		);
		if ( $enough && $best ) {
			// A version that clearly beat the control wins (the best rate among those);
			// otherwise the control clearly beat the versions.
			$winner = null;
			foreach ( $comparisons as $cmp ) {
				if ( $cmp['p'] < $alpha && $cmp['z'] > 0 && ( null === $winner || $cmp['rate_b'] > $winner['rate_b'] ) ) {
					$winner = $cmp;
				}
			}
			$out['state']      = 'clear';
			$out['winner']     = $winner ? $winner['id'] : (int) $control['id'];
			$out['confidence'] = 1 - ( $winner ? $winner['p'] : $best['p'] );
			return $out;
		}
		if ( $ended ) {
			$out['state'] = $enough ? 'no_difference' : 'not_enough_data';
			return $out;
		}
		// Project the traffic: will the slowest variant reach the minimum in time?
		if ( $days_run >= 3 ) {
			$slowest = PHP_INT_MAX;
			foreach ( $variants as $variant ) {
				$slowest = min( $slowest, (int) $variant['n'] );
			}
			$per_day = $slowest / max( 0.1, (float) $days_run );
			if ( $slowest < $min && ( $per_day <= 0 || ( $min - $slowest ) / $per_day > max( 0, (float) $max_days - (float) $days_run ) ) ) {
				$out['state'] = 'low_traffic';
			}
		}
		return $out;
	}

	/**
	 * Plain-language confidence.
	 *
	 * @param float $confidence 0-1.
	 * @return string
	 */
	public static function confidence_words( $confidence ) {
		if ( $confidence >= 0.99 ) {
			return __( 'very likely (over 99% confidence)', 'chatbot' );
		}
		if ( $confidence >= 0.95 ) {
			return __( 'likely (over 95% confidence)', 'chatbot' );
		}
		return __( 'not sure yet', 'chatbot' );
	}
}
