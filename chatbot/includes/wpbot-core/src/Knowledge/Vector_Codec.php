<?php
/**
 * Stores knowledge vectors compactly and compares them.
 *
 * Vectors are kept as 8-bit integers with one scale per vector (base64 text),
 * a quarter of the size of 32-bit floats. Cosine similarity on the decoded
 * vectors stays within about 0.01 of the float result, which does not change
 * the ranking of a search in practice (tested in VectorCodecTest).
 *
 * Format: "q8:" + base64( float32 scale + int8 values ).
 * Older WPBot rows (base64 float32, or a JSON list) are read too.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Vector encoding and similarity.
 */
final class Vector_Codec {

	/**
	 * Prefix of the 8-bit format.
	 */
	const PREFIX = 'q8:';

	/**
	 * Encode a vector.
	 *
	 * @param float[] $vector Vector.
	 * @return string
	 */
	public static function encode( array $vector ) {
		$max = 0.0;
		foreach ( $vector as $value ) {
			$max = max( $max, abs( (float) $value ) );
		}
		$scale = $max > 0 ? $max / 127 : 1.0;
		$bytes = '';
		foreach ( $vector as $value ) {
			$bytes .= pack( 'c', (int) max( -127, min( 127, round( (float) $value / $scale ) ) ) );
		}
		return self::PREFIX . base64_encode( pack( 'g', $scale ) . $bytes ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Compact storage of numbers, not obfuscation.
	}

	/**
	 * Decode a stored vector (8-bit, legacy float32 base64, or JSON list).
	 *
	 * @param string $data Stored text.
	 * @return float[] Empty when unreadable.
	 */
	public static function decode( $data ) {
		$data = (string) $data;
		if ( '' === $data ) {
			return array();
		}
		if ( 0 === strpos( $data, self::PREFIX ) ) {
			$raw = base64_decode( substr( $data, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- See encode().
			if ( false === $raw || strlen( $raw ) < 5 ) {
				return array();
			}
			$scale  = unpack( 'g', substr( $raw, 0, 4 ) )[1];
			$values = unpack( 'c*', substr( $raw, 4 ) );
			$out    = array();
			foreach ( $values as $value ) {
				$out[] = $value * $scale;
			}
			return $out;
		}
		if ( '[' === $data[0] ) {
			$list = json_decode( $data, true );
			return is_array( $list ) ? array_map( 'floatval', $list ) : array();
		}
		$raw = base64_decode( $data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Legacy WPBot rows (float32).
		if ( false === $raw || 0 === strlen( $raw ) || 0 !== strlen( $raw ) % 4 ) {
			return array();
		}
		$values = unpack( 'f*', $raw );
		return $values ? array_values( $values ) : array();
	}

	/**
	 * Euclidean length.
	 *
	 * @param float[] $vector Vector.
	 * @return float
	 */
	public static function norm( array $vector ) {
		$sum = 0.0;
		foreach ( $vector as $value ) {
			$sum += $value * $value;
		}
		return sqrt( $sum );
	}

	/**
	 * Cosine similarity; 0 when sizes differ or a vector is empty.
	 *
	 * @param float[]    $a      First vector.
	 * @param float[]    $b      Second vector.
	 * @param float|null $norm_a Length of $a, when already known.
	 * @return float
	 */
	public static function cosine( array $a, array $b, $norm_a = null ) {
		$count = count( $a );
		if ( 0 === $count || count( $b ) !== $count ) {
			return 0.0;
		}
		$dot    = 0.0;
		$norm_b = 0.0;
		for ( $i = 0; $i < $count; $i++ ) {
			$dot    += $a[ $i ] * $b[ $i ];
			$norm_b += $b[ $i ] * $b[ $i ];
		}
		$norm_a = null === $norm_a ? self::norm( $a ) : (float) $norm_a;
		if ( $norm_a <= 0 || $norm_b <= 0 ) {
			return 0.0;
		}
		return $dot / ( $norm_a * sqrt( $norm_b ) );
	}
}
