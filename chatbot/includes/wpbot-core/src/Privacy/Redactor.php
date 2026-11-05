<?php
/**
 * Removes personal data from visitor text before it is stored or sent to
 * an AI provider: email addresses, phone numbers, street addresses, URLs
 * that carry personal paths or query values, card-like numbers, and the
 * names a chat session recorded for the visitor.
 *
 * Each removal leaves a neutral marker ([email], [phone]…) so the meaning
 * of the question survives. Other modules can add patterns with the
 * qcld_wpbot_core/redact_patterns filter.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Privacy;

defined( 'ABSPATH' ) || exit;

/**
 * Text redaction.
 */
final class Redactor {

	/**
	 * Built-in patterns: marker => regular expression. Order matters (emails
	 * and URLs before phone numbers, so digits inside them are not split).
	 *
	 * @return array<string,string>
	 */
	public static function patterns() {
		$patterns = array(
			'[email]'    => '/[A-Z0-9._%+\-]+(?:@|\s?\(at\)\s?|\s?\[at\]\s?)[A-Z0-9.\-]+(?:\.|\s?\(dot\)\s?)[A-Z]{2,}/i',
			'[link]'     => '#\b(?:https?://|www\.)[^\s<>"\']+#i',
			'[card]'     => '/\b(?:\d[ \-]?){13,19}\b/',
			'[phone]'    => '/(?<![\w])(?:\+|00)?\d[\d\s().\-]{6,}\d(?![\w])/',
			'[address]'  => '/\b\d{1,5}\s+(?:[A-Z][\w\'\-]*\s+){1,4}(?:street|st|road|rd|avenue|ave|lane|ln|drive|dr|boulevard|blvd|court|ct|way|place|pl|square|sq|terrace|close|crescent)\b\.?/i',
			'[postcode]' => '/\b[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}\b/',
		);

		/**
		 * Redaction patterns, marker => PCRE pattern.
		 *
		 * @param array<string,string> $patterns Patterns.
		 */
		return (array) apply_filters( 'qcld_wpbot_core/redact_patterns', $patterns );
	}

	/**
	 * Redact one text.
	 *
	 * Links keep their host when they have no path or query (a shop's own
	 * domain is not personal); any path or query is removed.
	 *
	 * @param string   $text  Text.
	 * @param string[] $names Names known for this visitor (from chat metadata).
	 * @return string
	 */
	public static function redact( $text, array $names = array() ) {
		$text = (string) $text;
		if ( '' === trim( $text ) ) {
			return '';
		}
		foreach ( self::patterns() as $marker => $pattern ) {
			if ( '[link]' === $marker ) {
				$text = (string) preg_replace_callback( $pattern, array( __CLASS__, 'link' ), $text );
				continue;
			}
			$result = @preg_replace( $pattern, $marker, $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A bad filtered pattern must not break the run.
			$text   = is_string( $result ) ? $result : $text;
		}
		foreach ( self::name_parts( $names ) as $part ) {
			$text = (string) preg_replace( '/(?<![\p{L}\p{N}])' . preg_quote( $part, '/' ) . '(?![\p{L}\p{N}])/iu', '[name]', $text );
		}
		// "My name is X" / "I am X" / "this is X" introductions.
		$text = (string) preg_replace( '/\b(my name is|my name\'s|i am called|call me)\s+[A-Z][\p{L}\'\-]+(?:\s+[A-Z][\p{L}\'\-]+)?/u', '$1 [name]', $text );
		return trim( (string) preg_replace( '/\s{2,}/', ' ', $text ) );
	}

	/**
	 * Whether a text still contains something the patterns would remove.
	 *
	 * @param string   $text  Text.
	 * @param string[] $names Names.
	 * @return bool
	 */
	public static function has_personal_data( $text, array $names = array() ) {
		return self::redact( $text, $names ) !== trim( (string) preg_replace( '/\s{2,}/', ' ', (string) $text ) );
	}

	/**
	 * Replace a link, keeping a bare host.
	 *
	 * @param array $found Match.
	 * @return string
	 */
	private static function link( $found ) {
		$url   = rtrim( $found[0], '.,;:!?)' );
		$tail  = substr( $found[0], strlen( $url ) );
		$parts = wp_parse_url( 0 === stripos( $url, 'www.' ) ? 'http://' . $url : $url );
		if ( is_array( $parts ) && ! empty( $parts['host'] ) && empty( $parts['query'] ) && empty( $parts['fragment'] ) && ( empty( $parts['path'] ) || '/' === $parts['path'] ) && empty( $parts['user'] ) ) {
			return strtolower( $parts['host'] ) . $tail;
		}
		return '[link]' . $tail;
	}

	/**
	 * Name parts worth replacing (two letters or more).
	 *
	 * @param string[] $names Names.
	 * @return string[]
	 */
	private static function name_parts( array $names ) {
		$parts = array();
		foreach ( $names as $name ) {
			$name = trim( (string) $name );
			if ( '' === $name || false !== strpos( $name, '@' ) ) {
				continue;
			}
			$parts[] = $name;
			foreach ( preg_split( '/[\s,]+/u', $name ) as $word ) {
				if ( function_exists( 'mb_strlen' ) ? mb_strlen( $word ) >= 2 : strlen( $word ) >= 2 ) {
					$parts[] = $word;
				}
			}
		}
		$parts = array_unique( $parts );
		// Longest first, so a full name is replaced before its parts.
		usort(
			$parts,
			static function ( $a, $b ) {
				return strlen( $b ) <=> strlen( $a );
			}
		);
		return $parts;
	}
}
