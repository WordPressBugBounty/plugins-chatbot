<?php
/**
 * Versioned prompt templates. Each one builds the messages and checks the
 * model's answer against the shape it asked for before anything is saved.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\AI;

defined( 'ABSPATH' ) || exit;

final class Prompt_Library {

	const SUMMARY = 'summary.v1';

	/**
	 * Messages for a session summary.
	 *
	 * @param string $digest   Session digest.
	 * @param string $language Answer language, e.g. "English".
	 * @return array<int,array<string,string>>
	 */
	public static function summary_messages( $digest, $language ) {
		$system = implode(
			"\n",
			array(
				'You are a UX analyst. You read a text log of one visitor\'s visit to a website and explain what happened.',
				'Lines are "mm:ss action". "view" opens a page, "click" names the element, "filled in" means the visitor typed into a field (values are never shown), "rage-click" means fast repeated clicks out of frustration, "dead click" means a click that did nothing, "js-error" is a JavaScript error, "quick back" means the visitor left the page within 5 seconds.',
				'Only use facts from the log. Do not guess personal details. If the goal is unclear, say so.',
				'Answer with JSON only, no markdown: {"summary": string, "goal": string, "struggles": string[], "outcome": string, "frustration": "none" | "low" | "high"}.',
				'"summary": 2 or 3 plain sentences: what the visitor wanted, where they struggled (if anywhere), and how the visit ended.',
				'"goal": what the visitor was trying to do, at most 8 words. "struggles": 0 to 3 short items, at most 12 words each. "outcome": how the visit ended, at most 8 words.',
				sprintf( 'Write all text in %s.', $language ),
			)
		);
		return array(
			array(
				'role'    => 'system',
				'content' => $system,
			),
			array(
				'role'    => 'user',
				'content' => "Visit log:\n" . $digest,
			),
		);
	}

	/**
	 * Parse and validate a summary answer.
	 *
	 * @param string $text Model output.
	 * @return array<string,mixed>|null Clean result, or null when unusable.
	 */
	public static function parse_summary( $text ) {
		$data = self::json( $text );
		if ( is_array( $data ) && isset( $data['summary'] ) && is_string( $data['summary'] ) && '' !== trim( $data['summary'] ) ) {
			$struggles = array();
			foreach ( array_slice( (array) ( $data['struggles'] ?? array() ), 0, 3 ) as $s ) {
				if ( is_string( $s ) && '' !== trim( $s ) ) {
					$struggles[] = self::clean( $s, 160 );
				}
			}
			$frustration = isset( $data['frustration'] ) && in_array( $data['frustration'], array( 'none', 'low', 'high' ), true ) ? $data['frustration'] : '';
			return array(
				'summary'     => self::clean( $data['summary'], 1200 ),
				'goal'        => isset( $data['goal'] ) && is_string( $data['goal'] ) ? self::clean( $data['goal'], 120 ) : '',
				'struggles'   => $struggles,
				'outcome'     => isset( $data['outcome'] ) && is_string( $data['outcome'] ) ? self::clean( $data['outcome'], 120 ) : '',
				'frustration' => $frustration,
			);
		}
		// Some models ignore the JSON instruction; plain prose is still useful.
		$plain = trim( (string) $text );
		if ( '' !== $plain && '{' !== $plain[0] && strlen( $plain ) < 2000 ) {
			return array(
				'summary'     => self::clean( $plain, 1200 ),
				'goal'        => '',
				'struggles'   => array(),
				'outcome'     => '',
				'frustration' => '',
			);
		}
		return null;
	}

	/**
	 * Decode a JSON object from a model answer (for features using Insight_Service::run()).
	 *
	 * @param string $text Answer text.
	 * @return array<string,mixed>|null
	 */
	public static function decode_json( $text ) {
		$data = self::json( $text );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Decode JSON from a model answer, tolerating code fences and extra text.
	 *
	 * @param string $text Text.
	 * @return mixed
	 */
	private static function json( $text ) {
		$text = trim( (string) $text );
		$text = (string) preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $text );
		$data = json_decode( $text, true );
		if ( null === $data ) {
			$start = strpos( $text, '{' );
			$end   = strrpos( $text, '}' );
			if ( false !== $start && false !== $end && $end > $start ) {
				$data = json_decode( substr( $text, $start, $end - $start + 1 ), true );
			}
		}
		return $data;
	}

	/**
	 * Plain text, trimmed and shortened.
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	private static function clean( $text, $max ) {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
		return mb_strlen( $text ) > $max ? rtrim( mb_substr( $text, 0, $max - 1 ) ) . '…' : $text;
	}
}
