<?php
/**
 * Rule-based check for AI-sounding writing. Costs nothing: no AI call.
 *
 * Flags clichés (editable list), same-length sentences, repeated sentence
 * openings, too many lists or headings, passive voice and stacked hedges.
 * Each flag names the sentence and the reason so targeted rewrites can fix
 * only that sentence.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Checks;

defined( 'ABSPATH' ) || exit;

/**
 * AI-pattern check.
 */
final class Ai_Pattern_Check {

	/**
	 * Default cliché list (owner-editable in settings).
	 *
	 * @return string[]
	 */
	public static function default_cliches() {
		return array(
			"in today's fast-paced world",
			'in today’s fast-paced world',
			'delve',
			"it's important to note",
			'it’s important to note',
			'unlock',
			'game-changer',
			'game changer',
			'in conclusion',
			'navigate the',
			'ever-evolving',
			'look no further',
			'embark on',
			'a testament to',
			'when it comes to',
			'tapestry',
			'seamless',
			'elevate your',
		);
	}

	/**
	 * Hedge words that read as AI when stacked.
	 *
	 * @var string[]
	 */
	const HEDGES = array( 'may', 'might', 'could', 'potentially', 'possibly', 'perhaps', 'generally', 'often', 'typically', 'arguably', 'somewhat' );

	/**
	 * Analyse plain text paragraphs and structural counts.
	 *
	 * @param string[] $paragraphs Plain-text paragraphs.
	 * @param array    $structure  Counts: headings, lists, words.
	 * @param string[] $cliches    Cliché list (defaults when empty).
	 * @return array{flags: array, score: int, stats: array}
	 */
	public static function analyse( array $paragraphs, array $structure = array(), array $cliches = array() ) {
		$cliches   = array() === $cliches ? self::default_cliches() : $cliches;
		$flags     = array();
		$sentences = array();
		foreach ( $paragraphs as $paragraph ) {
			foreach ( self::sentences( $paragraph ) as $sentence ) {
				$sentences[] = $sentence;
			}
		}

		foreach ( $sentences as $sentence ) {
			$lower = self::lower( $sentence );
			foreach ( $cliches as $cliche ) {
				$cliche = self::lower( trim( (string) $cliche ) );
				if ( '' !== $cliche && false !== strpos( $lower, $cliche ) ) {
					$flags[] = self::flag( 'cliche', $sentence, sprintf( 'Uses the phrase "%s".', $cliche ) );
					break;
				}
			}
			if ( preg_match( '/\b(is|are|was|were|be|been|being)\s+(\w+ed|\w+en)\b/i', $sentence ) && ! preg_match( '/\b(is|are|was|were)\s+(used|based|located|interested|excited|tired|bored|married|done|open)\b/i', $sentence ) ) {
				$flags[] = self::flag( 'passive', $sentence, 'Passive voice: say who does what.' );
			}
			$hedges = 0;
			foreach ( self::HEDGES as $hedge ) {
				$hedges += preg_match_all( '/\b' . $hedge . '\b/i', $sentence );
			}
			if ( $hedges >= 2 ) {
				$flags[] = self::flag( 'hedges', $sentence, 'Stacked hedges: say it plainly.' );
			}
		}

		// Uniform rhythm: five or more sentences in a row within 3 words of each other.
		$lengths = array_map( array( __CLASS__, 'words' ), $sentences );
		$run     = 1;
		$count   = count( $lengths );
		for ( $i = 1; $i < $count; $i++ ) {
			$run = abs( $lengths[ $i ] - $lengths[ $i - 1 ] ) <= 3 && $lengths[ $i ] > 6 ? $run + 1 : 1;
			if ( 5 === $run ) {
				$flags[] = self::flag( 'rhythm', $sentences[ $i ], 'Several sentences in a row have the same length. Mix short and long.' );
			}
		}

		// Repeated openings: three sentences starting with the same word.
		$openings = array();
		foreach ( $sentences as $sentence ) {
			$first = self::lower( (string) strtok( $sentence, ' ' ) );
			if ( '' === $first ) {
				continue;
			}
			$openings[ $first ][] = $sentence;
		}
		foreach ( $openings as $word => $list ) {
			if ( count( $list ) >= 3 && count( $list ) / max( 1, count( $sentences ) ) > 0.2 && ! in_array( $word, array( 'the', 'a', 'an' ), true ) ) {
				$flags[] = self::flag( 'openings', $list[2], sprintf( 'Many sentences start with "%s".', $word ) );
			}
		}

		// Structure: lists and headings per 300 words.
		$words = isset( $structure['words'] ) ? max( 1, (int) $structure['words'] ) : max( 1, array_sum( $lengths ) );
		if ( isset( $structure['lists'] ) && $structure['lists'] / ( $words / 300 ) > 1 && $structure['lists'] > 2 ) {
			$flags[] = self::flag( 'lists', '', 'A lot of bullet lists for the length. Turn some into paragraphs.' );
		}
		if ( isset( $structure['headings'] ) && $structure['headings'] / ( $words / 300 ) > 1.5 && $structure['headings'] > 3 ) {
			$flags[] = self::flag( 'headings', '', 'Many headings for the length. Merge short sections.' );
		}

		// Uniform paragraphs: every paragraph within 15% of the average length.
		$plens = array_map( array( __CLASS__, 'words' ), $paragraphs );
		if ( count( $plens ) >= 5 ) {
			$avg     = array_sum( $plens ) / count( $plens );
			$uniform = array_filter(
				$plens,
				static function ( $len ) use ( $avg ) {
					return abs( $len - $avg ) <= $avg * 0.15;
				}
			);
			if ( count( $uniform ) === count( $plens ) ) {
				$flags[] = self::flag( 'paragraphs', '', 'Paragraphs are all about the same length.' );
			}
		}

		$score = max( 0, 100 - 6 * count( $flags ) );
		return array(
			'flags' => $flags,
			'score' => $score,
			'stats' => array(
				'sentences' => count( $sentences ),
				'words'     => array_sum( $lengths ),
			),
		);
	}

	/**
	 * Split a paragraph into sentences.
	 *
	 * @param string $text Plain text.
	 * @return string[]
	 */
	public static function sentences( $text ) {
		$text  = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
		$parts = preg_split( '/(?<=[.!?。！？])\s+(?=[\p{Lu}\p{Lo}"“(])/u', $text );
		return array_values( array_filter( array_map( 'trim', (array) $parts ) ) );
	}

	/**
	 * Word count of a sentence.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	public static function words( $text ) {
		return (int) preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}\p{M}\'’-]*/u', (string) $text );
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

	/**
	 * Build a flag.
	 *
	 * @param string $type     Type.
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
