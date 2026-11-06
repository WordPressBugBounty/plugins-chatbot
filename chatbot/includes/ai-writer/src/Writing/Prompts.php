<?php
/**
 * Prompt building blocks: writing rules, language, voice and facts.
 *
 * Every prompt starts with "Task: <name>" so logs and tests can tell the
 * steps apart. Rules follow the brief: specific over general, real input
 * first, no filler, no overused patterns, never invent facts.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Writing;

use QuantumCloud\AiWriter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Static prompt helpers.
 */
final class Prompts {

	/**
	 * Most characters of the Site Context summary sent with a prompt.
	 */
	const CONTEXT_CHARS = 1500;

	/**
	 * Writing rules sent with every drafting call.
	 *
	 * @return string
	 */
	public static function writing_rules() {
		$rules = array(
			'Be specific: prefer concrete examples, numbers and steps the reader can use over general statements.',
			'Use the facts, answers and sources given. Never invent statistics, quotes, prices, guarantees, studies or customer stories.',
			'Where a personal detail would help but none was given, write a short placeholder in square brackets, for example [your own example] or [a photo from your shop].',
			'Vary sentence length. Use contractions and the active voice.',
			'No filler openings or closings (for example "In today\'s fast-paced world", "In conclusion").',
			'Do not overuse three-item lists, dashes, hedging, headings or bullet points.',
			'Avoid these words and phrases: delve, unlock, game-changer, it\'s important to note, navigate the landscape, in today\'s world.',
		);

		foreach ( self::style_guide() as $line ) {
			$rules[] = $line;
		}

		/**
		 * Add or change writing rules (Pro: deep humanize).
		 *
		 * @param string[] $rules Rules.
		 */
		$rules = (array) apply_filters( 'qcld_wpbot_writer/prompt_rules', $rules );
		return "Writing rules:\n- " . implode( "\n- ", array_map( 'strval', $rules ) );
	}

	/**
	 * Style guide lines from settings (basic style guide).
	 *
	 * @return string[]
	 */
	public static function style_guide() {
		$lines = array();
		$use   = (array) Settings::get( 'style_use_words' );
		$avoid = (array) Settings::get( 'style_avoid_words' );
		$names = (array) Settings::get( 'style_product_names' );
		if ( array() !== $use ) {
			$lines[] = 'Prefer these words where they fit: ' . implode( ', ', array_map( 'strval', $use ) ) . '.';
		}
		if ( array() !== $avoid ) {
			$lines[] = 'Never use these words: ' . implode( ', ', array_map( 'strval', $avoid ) ) . '.';
		}
		if ( array() !== $names ) {
			$lines[] = 'Write these names exactly like this: ' . implode( ', ', array_map( 'strval', $names ) ) . '.';
		}
		$levels = array(
			'simple'   => 'Reading level: simple. Short sentences and everyday words a 12-year-old understands.',
			'standard' => 'Reading level: standard. Plain words; explain any technical term the first time.',
			'advanced' => 'Reading level: advanced. Expert readers; technical terms are fine.',
		);
		$level  = (string) Settings::get( 'reading_level' );
		if ( isset( $levels[ $level ] ) ) {
			$lines[] = $levels[ $level ];
		}
		return $lines;
	}

	/**
	 * Language instruction from the site language (and spelling setting).
	 *
	 * @return string
	 */
	public static function language() {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$name   = $locale;
		if ( class_exists( '\Locale' ) ) {
			$display = \Locale::getDisplayLanguage( $locale, 'en' );
			$region  = \Locale::getDisplayRegion( $locale, 'en' );
			$name    = '' !== $display ? $display . ( '' !== $region ? ' (' . $region . ')' : '' ) : $locale;
		}
		$line     = 'Write in ' . $name . '.';
		$spelling = Settings::get( 'spelling' );
		if ( 'us' === $spelling ) {
			$line .= ' Use US English spelling.';
		} elseif ( 'uk' === $spelling ) {
			$line .= ' Use UK English spelling.';
		}
		return $line;
	}

	/**
	 * Voice section of a prompt.
	 *
	 * @param array|null $profile Voice profile (see Voice\Voices).
	 * @return string
	 */
	public static function voice( $profile ) {
		if ( ! is_array( $profile ) || array() === $profile ) {
			/** This filter is documented below. */
			return (string) apply_filters( 'qcld_wpbot_writer/voice_prompt', 'Voice: clear, friendly and plain-spoken.', array() );
		}
		$parts = array();
		foreach ( array( 'summary', 'point_of_view', 'sentence_length', 'openings', 'formatting', 'reading_level', 'cta_style' ) as $key ) {
			if ( ! empty( $profile[ $key ] ) && is_string( $profile[ $key ] ) ) {
				$parts[] = ucfirst( str_replace( '_', ' ', $key ) ) . ': ' . $profile[ $key ];
			}
		}
		if ( ! empty( $profile['sliders'] ) && is_array( $profile['sliders'] ) ) {
			$labels = array(
				'formal'    => array( 'formal', 'casual' ),
				'serious'   => array( 'serious', 'playful' ),
				'technical' => array( 'technical', 'simple' ),
				'reserved'  => array( 'reserved', 'enthusiastic' ),
			);
			foreach ( $labels as $key => $ends ) {
				if ( isset( $profile['sliders'][ $key ] ) ) {
					$value   = max( 0, min( 100, (int) $profile['sliders'][ $key ] ) );
					$parts[] = sprintf( 'Tone %s(0)-%s(100): %d', $ends[0], $ends[1], $value );
				}
			}
		}
		foreach ( array(
			'signature_phrases' => 'Phrases the owner uses',
			'avoid_words'       => 'Never use',
		) as $key => $label ) {
			if ( ! empty( $profile[ $key ] ) && is_array( $profile[ $key ] ) ) {
				$parts[] = $label . ': ' . implode( ', ', array_map( 'strval', $profile[ $key ] ) );
			}
		}
		$text = "Voice profile:\n- " . implode( "\n- ", $parts );
		if ( ! empty( $profile['excerpts'] ) && is_array( $profile['excerpts'] ) ) {
			$text .= "\nStyle examples from the owner's own writing (match the style, not the content):";
			foreach ( array_slice( $profile['excerpts'], 0, 3 ) as $excerpt ) {
				$text .= "\n\"\"\"" . self::clip( (string) $excerpt, 600 ) . '"""';
			}
		}
		/**
		 * Change the voice part of prompts (Pro: per-voice style guide).
		 *
		 * @param string $text    Voice text.
		 * @param array  $profile Voice profile.
		 */
		return (string) apply_filters( 'qcld_wpbot_writer/voice_prompt', $text, $profile );
	}

	/**
	 * What the business is: its name and tagline, plus WPBot Core's shared
	 * Site Context summary when one has been built (issue #35). Brand Voice
	 * stays separate: it is how the business sounds, this is what it is.
	 * Without Site Context the line is exactly as before.
	 *
	 * @return string
	 */
	public static function business() {
		$tagline = (string) get_bloginfo( 'description' );
		$text    = 'Business: ' . get_bloginfo( 'name' ) . ( '' !== $tagline ? ' — ' . $tagline : '' );
		$context = function_exists( 'qcld_wpbot_core_context_text' ) ? trim( (string) qcld_wpbot_core_context_text() ) : '';
		if ( '' !== $context ) {
			$text .= "\nAbout the business (Site Context; background to fit the writing to this business. Do not state more than it says, and do not repeat it word for word):\n" . self::clip( $context, self::CONTEXT_CHARS );
		}
		/**
		 * The "about the business" part of AI Writer's prompts.
		 *
		 * @param string $text    Prompt text.
		 * @param string $context Site Context summary ('' when none).
		 */
		return (string) apply_filters( 'qcld_wpbot_writer/business_prompt', $text, $context );
	}

	/**
	 * Facts block: answers and facts the draft must use.
	 *
	 * @param array $facts List of strings.
	 * @return string
	 */
	public static function facts( array $facts ) {
		$facts = array_values( array_filter( array_map( 'trim', array_map( 'strval', $facts ) ) ) );
		if ( array() === $facts ) {
			return 'Facts from the owner: none given. Use placeholders where a personal detail is needed.';
		}
		return "Facts from the owner (use them, they are true):\n- " . implode( "\n- ", $facts );
	}

	/**
	 * Shorten text to a character budget at a word boundary.
	 *
	 * @param string $text  Text.
	 * @param int    $chars Max characters.
	 * @return string
	 */
	public static function clip( $text, $chars ) {
		$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $text ) <= $chars : strlen( $text ) <= $chars ) {
			return $text;
		}
		$cut = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $chars ) : substr( $text, 0, $chars );
		$pos = strrpos( $cut, ' ' );
		return ( false !== $pos ? substr( $cut, 0, $pos ) : $cut ) . '…';
	}

	/**
	 * Cut text to a length without breaking multibyte characters.
	 *
	 * @param string $text  Text.
	 * @param int    $chars Max characters.
	 * @return string
	 */
	public static function cut( $text, $chars ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $text, 0, $chars ) : substr( (string) $text, 0, $chars );
	}

	/**
	 * Count words (works for scripts without spaces too, roughly).
	 *
	 * @param string $text Plain text.
	 * @return int
	 */
	public static function word_count( $text ) {
		$text = trim( wp_strip_all_tags( (string) $text ) );
		if ( '' === $text ) {
			return 0;
		}
		return (int) preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}\p{M}\'’-]*/u', $text );
	}

	/**
	 * English name of a content type for prompts.
	 *
	 * @param string $type Type id.
	 * @return string
	 */
	public static function type_label( $type ) {
		$entry = Content_Types::get( (string) $type );
		return (string) $entry['prompt'];
	}
}
