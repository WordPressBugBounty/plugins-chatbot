<?php
/**
 * What the writer may use to persuade (round 3): the Persuasion Kit
 * (plus live WooCommerce data for a product) and customers' own words for
 * a topic. Both come from WPBot Core; customer words are already redacted
 * and are given as wording only, never as quotes.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Persuasion;

use QuantumCloud\WPBotCore\Persuasion\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Prompt context from the kit and the voice of customer bank.
 */
final class Context {

	/** Customer phrases given per piece at most. */
	const PHRASES = 12;

	/**
	 * Kit entries and Woo data usable now.
	 *
	 * @param int $product_id Product (0 for none).
	 * @return array{entries: array[], woo: array|null}
	 */
	public static function kit( $product_id = 0 ) {
		if ( ! Persuasion::available() ) {
			return array(
				'entries' => array(),
				'woo'     => null,
			);
		}
		return qcld_wpbot_core_persuasion_kit( $product_id ? array( 'product_id' => (int) $product_id ) : array() );
	}

	/**
	 * Expired kit offers (for the honesty check).
	 *
	 * @return array[]
	 */
	public static function expired() {
		if ( ! Persuasion::available() ) {
			return array();
		}
		$all = qcld_wpbot_core_persuasion_kit( array( 'include_expired' => true ) );
		return array_values(
			array_filter(
				$all['entries'],
				static function ( $entry ) {
					return ! empty( $entry['expired'] );
				}
			)
		);
	}

	/**
	 * The kit as prompt text ('' when empty).
	 *
	 * @param int $product_id Product.
	 * @return string
	 */
	public static function kit_prompt( $product_id = 0 ) {
		if ( ! Persuasion::available() ) {
			return '';
		}
		$kit = self::kit( $product_id );
		return Kit::prompt( $kit['entries'], $kit['woo'] );
	}

	/**
	 * Customer phrases that share words with a topic (most used first).
	 *
	 * @param string $topic Topic, main search or product name.
	 * @return string[]
	 */
	public static function phrases( $topic ) {
		if ( ! Persuasion::available() || ! function_exists( 'qcld_wpbot_core_voice_of_customer' ) ) {
			return array();
		}
		$words = self::words( $topic );
		$out   = array();
		foreach ( qcld_wpbot_core_voice_of_customer( array( 'limit' => 300 ) ) as $row ) {
			$overlap = array_intersect( $words, self::words( $row['phrase'] . ' ' . $row['topic'] ) );
			if ( array() !== $words && array() === $overlap ) {
				continue;
			}
			$out[] = $row['phrase'];
			if ( count( $out ) >= self::PHRASES ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Customer phrases as prompt text ('' when none).
	 *
	 * @param string $topic Topic.
	 * @return string
	 */
	public static function phrases_prompt( $topic ) {
		$phrases = self::phrases( $topic );
		return array() === $phrases ? '' : "Customers' own words (use their wording for problems and headlines; never present them as quotes):\n- " . implode( "\n- ", $phrases );
	}

	/**
	 * Everything the writer may use, as one block ('' when nothing).
	 *
	 * @param array  $choices    Resolved choices.
	 * @param string $topic      Topic.
	 * @param int    $product_id Product.
	 * @return string
	 */
	public static function prompt( array $choices, $topic, $product_id = 0 ) {
		$parts = array_filter(
			array(
				Choices::prompt( $choices ),
				self::kit_prompt( $product_id ),
				! empty( $choices['none'] ) ? '' : self::phrases_prompt( $topic ),
			)
		);
		return implode( "\n\n", $parts );
	}

	/**
	 * Honesty check context.
	 *
	 * @param string $facts      Facts the writer was given.
	 * @param int    $product_id Product.
	 * @return array
	 */
	public static function honesty( $facts = '', $product_id = 0 ) {
		$kit = self::kit( $product_id );
		return array(
			'kit'     => $kit['entries'],
			'expired' => self::expired(),
			'woo'     => $kit['woo'],
			'facts'   => (string) $facts,
		);
	}

	/**
	 * Lowercase words of 4+ letters.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	private static function words( $text ) {
		preg_match_all( '/[\p{L}\p{N}]{4,}/u', function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text ), $m );
		return array_values( array_unique( $m[0] ) );
	}
}
