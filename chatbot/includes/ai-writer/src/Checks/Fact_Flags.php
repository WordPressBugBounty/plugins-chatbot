<?php
/**
 * Flags claims to verify: sentences with numbers, prices, guarantees,
 * certifications or superlatives that the owner's facts, answers and
 * sources do not support. Rule-based, no AI cost. Flags are shown to the
 * owner; nothing is removed silently.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Checks;

defined( 'ABSPATH' ) || exit;

/**
 * Fact flagging.
 */
final class Fact_Flags {

	/**
	 * Words that make a sentence a claim.
	 *
	 * @var string[]
	 */
	const CLAIM_WORDS = array( 'guarantee', 'guaranteed', 'certified', 'certification', 'award', 'proven', 'clinically', 'best', 'only', 'fastest', 'cheapest', 'number one', '#1', 'always', 'never fails', 'warranty', 'compatible with', 'approved', 'study', 'studies', 'research shows', 'percent', 'in stock', 'free shipping' );

	/**
	 * Find claims to verify.
	 *
	 * @param string[] $paragraphs Plain-text paragraphs of the draft.
	 * @param string   $evidence   Everything the owner supplied (facts, answers, sources, notes).
	 * @return array[] Each: sentence, reason.
	 */
	public static function find( array $paragraphs, $evidence ) {
		$evidence = self::lower( (string) $evidence );
		$numbers  = array();
		if ( preg_match_all( '/\d[\d.,]*/', $evidence, $matches ) ) {
			$numbers = array_map( array( __CLASS__, 'normalise_number' ), $matches[0] );
		}

		$flags = array();
		foreach ( $paragraphs as $paragraph ) {
			foreach ( Ai_Pattern_Check::sentences( $paragraph ) as $sentence ) {
				$lower = self::lower( $sentence );

				// Placeholders are the owner's to fill, not claims.
				if ( preg_match( '/^\[.*\]$/u', trim( $sentence ) ) ) {
					continue;
				}
				if ( preg_match_all( '/\d[\d.,]*%?/', $sentence, $found ) ) {
					foreach ( $found[0] as $number ) {
						$plain = self::normalise_number( rtrim( $number, '%' ) );
						if ( '' !== $plain && ! in_array( $plain, $numbers, true ) && ! preg_match( '/^(1|2|3|4|5|6|7|8|9|10)$/', $plain ) && ! preg_match( '/^(19|20)\d\d$/', $plain ) ) {
							$flags[] = array(
								'sentence' => $sentence,
								/* translators: %s: a number from the draft. */
								'reason'   => sprintf( __( 'The number %s is not in your facts or sources. Check it.', 'chatbot' ), $number ),
							);
							continue 2;
						}
					}
				}
				foreach ( self::CLAIM_WORDS as $word ) {
					if ( false !== strpos( $lower, $word ) && false === strpos( $evidence, $word ) ) {
						$flags[] = array(
							'sentence' => $sentence,
							/* translators: %s: a claim word such as "guaranteed". */
							'reason'   => sprintf( __( 'This makes a claim ("%s") your facts do not support. Check it or remove it.', 'chatbot' ), $word ),
						);
						continue 2;
					}
				}
			}
		}
		return $flags;
	}

	/**
	 * Normalise a number for comparison ("1,200.00" → "1200").
	 *
	 * @param string $number Number text.
	 * @return string
	 */
	public static function normalise_number( $number ) {
		$number = str_replace( ',', '', trim( (string) $number, ' .,' ) );
		if ( false !== strpos( $number, '.' ) ) {
			$number = rtrim( rtrim( $number, '0' ), '.' );
		}
		return $number;
	}

	/**
	 * Lowercase, multibyte-safe.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
	}
}
