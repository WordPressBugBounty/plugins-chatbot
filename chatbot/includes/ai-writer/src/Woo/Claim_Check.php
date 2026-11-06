<?php
/**
 * The facts rule, checked: claims in product copy that the product data
 * does not support (guarantees, compatibility, materials, certifications,
 * availability, delivery, superlatives) are flagged for the owner, never
 * written silently. Rule based, no AI cost.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Woo;

use QuantumCloud\AiWriter\Checks\Ai_Pattern_Check;

defined( 'ABSPATH' ) || exit;

/**
 * Claim check.
 */
final class Claim_Check {

	/**
	 * Claim words by kind (lowercase; matched as whole words or phrases).
	 *
	 * @return array<string,string[]>
	 */
	public static function words() {
		$words = array(
			'guarantee'     => array( 'guarantee', 'guaranteed', 'warranty', 'lifetime', 'money-back', 'risk-free' ),
			'compatibility' => array( 'compatible with', 'works with', 'fits all', 'universal fit' ),
			'material'      => array( 'cotton', 'leather', 'wool', 'silk', 'linen', 'polyester', 'nylon', 'stainless steel', 'steel', 'aluminium', 'aluminum', 'titanium', 'bamboo', 'oak', 'walnut', 'ceramic', 'glass', 'gold', 'silver', 'cashmere', 'merino', 'organic' ),
			'certification' => array( 'certified', 'certification', 'approved', 'fda', 'ce marked', 'iso', 'bpa-free', 'non-toxic', 'hypoallergenic', 'vegan', 'cruelty-free', 'fair trade', 'eco-friendly', 'sustainable', 'recycled', 'biodegradable', 'handmade', 'made in' ),
			'performance'   => array( 'waterproof', 'water-resistant', 'shockproof', 'scratch-resistant', 'dishwasher safe', 'machine washable', 'unbreakable', 'clinically proven' ),
			'availability'  => array( 'in stock', 'limited stock', 'free shipping', 'free delivery', 'next-day', 'next day', 'ships within', 'ships in', 'delivered in' ),
			'superlative'   => array( 'best-selling', 'bestseller', 'best in class', 'number one', '#1', 'award-winning', 'world\'s best' ),
		);

		/**
		 * Change the words treated as product claims.
		 *
		 * @param array $words kind => list of words or phrases.
		 */
		return (array) apply_filters( 'qcld_wpbot_writer/product_claim_words', $words );
	}

	/**
	 * Sentences with claims the facts do not support.
	 *
	 * @param string $html  Product copy.
	 * @param string $facts Facts text (Product_Facts::text()).
	 * @return array[] sentence, claim, kind, reason.
	 */
	public static function flags( $html, $facts ) {
		$facts = self::lower( $facts );
		$flags = array();
		$text  = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( str_replace( array( '</p>', '</li>', '<br>', '<br />' ), array( '</p> ', '</li>. ', ' ', ' ' ), (string) $html ) ), ENT_QUOTES, 'UTF-8' ) ) );
		foreach ( Ai_Pattern_Check::sentences( $text ) as $sentence ) {
			$lower  = self::lower( $sentence );
			$claims = array();
			$kind   = '';
			foreach ( self::words() as $type => $list ) {
				foreach ( (array) $list as $word ) {
					$word = self::lower( (string) $word );
					if ( '' === $word || false !== strpos( $facts, $word ) || in_array( $word, $claims, true ) ) {
						continue;
					}
					if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $word, '/' ) . '(?![\p{L}\p{N}])/u', $lower ) ) {
						$claims[] = $word;
						$kind     = '' === $kind ? $type : $kind;
					}
				}
			}
			if ( array() === $claims ) {
				continue;
			}
			$flags[] = array(
				'sentence' => $sentence,
				'claim'    => $claims[0],
				'claims'   => $claims,
				'kind'     => $kind,
				/* translators: %s: words such as "waterproof". */
				'reason'   => sprintf( __( 'Not in the product data: %s. Check it, or remove it.', 'chatbot' ), '"' . implode( '", "', $claims ) . '"' ),
			);
		}
		return $flags;
	}

	/**
	 * Lowercase, multibyte-safe.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text ) : strtolower( (string) $text );
	}
}
