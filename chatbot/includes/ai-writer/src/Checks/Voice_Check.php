<?php
/**
 * Rule-based voice and style-guide check (no AI cost).
 *
 * Flags sentences that use words the owner avoids, misspell product names,
 * mix US and UK spelling against the chosen one, switch point of view, or
 * run long for the chosen reading level. Each flag names the sentence so a
 * targeted rewrite can fix just that sentence.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Checks;

defined( 'ABSPATH' ) || exit;

/**
 * Voice and style check.
 */
final class Voice_Check {

	/**
	 * Common US => UK spelling pairs.
	 *
	 * @var array<string,string>
	 */
	const SPELLING = array(
		'color'      => 'colour',
		'colors'     => 'colours',
		'favorite'   => 'favourite',
		'favorites'  => 'favourites',
		'behavior'   => 'behaviour',
		'center'     => 'centre',
		'centers'    => 'centres',
		'organize'   => 'organise',
		'organized'  => 'organised',
		'organizing' => 'organising',
		'analyze'    => 'analyse',
		'analyzed'   => 'analysed',
		'realize'    => 'realise',
		'realized'   => 'realised',
		'optimize'   => 'optimise',
		'optimized'  => 'optimised',
		'customize'  => 'customise',
		'customized' => 'customised',
		'apologize'  => 'apologise',
		'recognize'  => 'recognise',
		'gray'       => 'grey',
		'traveled'   => 'travelled',
		'traveling'  => 'travelling',
		'canceled'   => 'cancelled',
		'labeled'    => 'labelled',
		'defense'    => 'defence',
		'theater'    => 'theatre',
		'catalog'    => 'catalogue',
		'jewelry'    => 'jewellery',
		'neighbor'   => 'neighbour',
		'neighbors'  => 'neighbours',
		'flavor'     => 'flavour',
		'honor'      => 'honour',
		'labor'      => 'labour',
		'humor'      => 'humour',
		'fulfill'    => 'fulfil',
		'enroll'     => 'enrol',
		'aluminum'   => 'aluminium',
		'tire'       => 'tyre',
		'tires'      => 'tyres',
	);

	/**
	 * Check text blocks.
	 *
	 * @param array<string,string> $blocks  Block id => plain text.
	 * @param array                $profile Voice profile (may be empty).
	 * @param array                $guide   avoid (string[]), names (string[]), spelling (us|uk|auto), level (any|simple|standard|advanced).
	 * @return array[] Flags: id, sentence, reason, type (voice|style).
	 */
	public static function analyse( array $blocks, array $profile, array $guide ) {
		$avoid = array_merge(
			isset( $guide['avoid'] ) ? (array) $guide['avoid'] : array(),
			isset( $profile['avoid_words'] ) ? (array) $profile['avoid_words'] : array()
		);
		$avoid = array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', $avoid ) ) ) ) );
		$names = array_values( array_filter( array_map( 'trim', array_map( 'strval', isset( $guide['names'] ) ? (array) $guide['names'] : array() ) ) ) );

		$spelling = isset( $guide['spelling'] ) ? (string) $guide['spelling'] : 'auto';
		if ( 'auto' === $spelling && ! empty( $profile['spelling'] ) ) {
			$spelling = false !== stripos( (string) $profile['spelling'], 'uk' ) || false !== stripos( (string) $profile['spelling'], 'brit' ) ? 'uk' : ( false !== stripos( (string) $profile['spelling'], 'us' ) ? 'us' : 'auto' );
		}

		$pov = self::point_of_view( isset( $profile['point_of_view'] ) ? (string) $profile['point_of_view'] : '' );

		$level   = isset( $guide['level'] ) ? (string) $guide['level'] : 'any';
		$max_len = array(
			'simple'   => 20,
			'standard' => 30,
		);
		$limit   = isset( $max_len[ $level ] ) ? $max_len[ $level ] : 0;
		if ( ! $limit && ! empty( $profile['sentence_length'] ) && false !== stripos( (string) $profile['sentence_length'], 'short' ) ) {
			$limit = 25;
		}

		$flags = array();
		foreach ( $blocks as $id => $text ) {
			foreach ( Ai_Pattern_Check::sentences( (string) $text ) as $sentence ) {
				$flag = self::check_sentence( $sentence, $avoid, $names, $spelling, $pov, $limit );
				if ( $flag ) {
					$flag['id'] = (string) $id;
					$flags[]    = $flag;
				}
			}
		}
		return $flags;
	}

	/**
	 * First problem in one sentence, or null.
	 *
	 * @param string   $sentence Sentence.
	 * @param string[] $avoid    Words to avoid.
	 * @param string[] $names    Product names.
	 * @param string   $spelling us, uk or auto.
	 * @param string   $pov      we, i or ''.
	 * @param int      $limit    Max words (0 = none).
	 * @return array|null
	 */
	private static function check_sentence( $sentence, array $avoid, array $names, $spelling, $pov, $limit ) {
		foreach ( $avoid as $word ) {
			if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $word, '/' ) . '(?![\p{L}\p{N}])/iu', $sentence ) ) {
				/* translators: %s: a word or phrase. */
				return self::flag( 'style', $sentence, sprintf( __( 'Uses "%s", which your style guide avoids.', 'chatbot' ), $word ) );
			}
		}
		foreach ( $names as $name ) {
			if ( preg_match_all( '/(?<![\p{L}\p{N}])' . preg_quote( $name, '/' ) . '(?![\p{L}\p{N}])/iu', $sentence, $found ) ) {
				foreach ( $found[0] as $match ) {
					if ( $match !== $name ) {
						/* translators: %s: product or brand name. */
						return self::flag( 'style', $sentence, sprintf( __( 'Write the name as "%s".', 'chatbot' ), $name ) );
					}
				}
			}
		}
		if ( 'us' === $spelling || 'uk' === $spelling ) {
			foreach ( self::SPELLING as $us => $uk ) {
				$wrong = 'uk' === $spelling ? $us : $uk;
				$right = 'uk' === $spelling ? $uk : $us;
				if ( preg_match( '/\b' . $wrong . '\b/i', $sentence ) ) {
					return self::flag(
						'style',
						$sentence,
						/* translators: 1: word as written, 2: preferred spelling, 3: US or UK. */
						sprintf( __( '"%1$s" is spelled "%2$s" in %3$s English.', 'chatbot' ), $wrong, $right, strtoupper( $spelling ) )
					);
				}
			}
		}
		if ( 'we' === $pov && preg_match( '/(^|[\s"“(])I(\s|’|\')/u', $sentence ) ) {
			return self::flag( 'voice', $sentence, __( 'Your voice writes as "we", this sentence says "I".', 'chatbot' ) );
		}
		if ( 'i' === $pov && preg_match( '/\b(we|our|us)\b/i', $sentence ) ) {
			return self::flag( 'voice', $sentence, __( 'Your voice writes as "I", this sentence says "we".', 'chatbot' ) );
		}
		$words = Ai_Pattern_Check::words( $sentence );
		if ( $limit && $words > $limit ) {
			/* translators: %d: number of words. */
			return self::flag( 'voice', $sentence, sprintf( __( 'Long sentence (%d words) for your reading level. Split it.', 'chatbot' ), $words ) );
		}
		return null;
	}

	/**
	 * Point of view the voice asks for.
	 *
	 * @param string $text Voice field.
	 * @return string we, i or ''.
	 */
	private static function point_of_view( $text ) {
		$has_we = (bool) preg_match( '/\bwe\b/i', $text );
		$has_i  = (bool) preg_match( '/(^|[\s"“(])I\b/u', $text ) || (bool) preg_match( '/\bfirst person singular\b/i', $text );
		if ( $has_we && ! $has_i ) {
			return 'we';
		}
		if ( $has_i && ! $has_we ) {
			return 'i';
		}
		return '';
	}

	/**
	 * Build a flag.
	 *
	 * @param string $type     voice or style.
	 * @param string $sentence Sentence.
	 * @param string $reason   Reason.
	 * @return array
	 */
	private static function flag( $type, $sentence, $reason ) {
		return array(
			'type'     => $type,
			'sentence' => $sentence,
			'reason'   => $reason,
		);
	}
}
