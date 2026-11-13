<?php
/**
 * Honesty check: flags claims in a version that are not in the source or in
 * Business Context. Two layers, both free:
 * - a local check for the things AI invents most often: numbers, prices,
 *   percentages, codes, dates and web addresses that appear nowhere in the
 *   source or Business Context;
 * - the writer's own list of claims it could not support (asked for in the
 *   same call, so it costs nothing extra).
 * Flags are shown on the version; nothing is changed or blocked silently.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Writing;

defined( 'ABSPATH' ) || exit;

/**
 * Honesty check.
 */
final class Honesty {

	/**
	 * Unsupported specifics in a text.
	 *
	 * @param string $text    Version text.
	 * @param string $support Source plus Business Context plus the link.
	 * @return string[] Plain-language flags.
	 */
	public static function check( $text, $support ) {
		$flags   = array();
		$support = self::normal( $support );
		foreach ( self::specifics( $text ) as $token ) {
			if ( false === strpos( $support, self::normal( $token ) ) ) {
				/* translators: %s: a number, price, code or address from the text. */
				$flags[] = sprintf( __( '"%s" is not in the source or Business Context. Check it.', 'chatbot' ), $token );
			}
		}
		return array_values( array_unique( $flags ) );
	}

	/**
	 * Whether a version shares at least one distinctive word with its source
	 * (a cheap guard against a version that drifted to another topic).
	 *
	 * @param string $text   Version text.
	 * @param string $source Source text.
	 * @return bool True when on topic, or when the source is too short to tell.
	 */
	public static function on_topic( $text, $source ) {
		$words  = static function ( $value ) {
			preg_match_all( '/[\p{L}\p{N}]{5,}/u', mb_strtolower( (string) $value ), $m );
			return array_unique( $m[0] );
		};
		$wanted = array_diff( $words( $source ), array( 'about', 'their', 'there', 'which', 'would', 'these', 'those', 'title', 'summary', 'content', 'notes', 'owner', 'product', 'description', 'price', 'event', 'offer', 'where', 'when' ) );
		if ( count( $wanted ) < 3 ) {
			return true;
		}
		return array() !== array_intersect( $wanted, $words( $text ) );
	}

	/**
	 * Numbers, prices, percentages, codes, years and addresses in a text.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function specifics( $text ) {
		$text   = (string) $text;
		$tokens = array();
		// Web addresses (without the tracking query WPBot adds).
		if ( preg_match_all( '#https?://[^\s?\#]+|\b[a-z0-9-]+\.(?:com|net|org|co|io|coffee|shop|store|uk|de)\b[^\s?\#]*#i', $text, $m ) ) {
			foreach ( $m[0] as $url ) {
				$tokens[] = rtrim( $url, '.,!)' );
			}
			$text = (string) preg_replace( '#https?://\S+#i', ' ', $text );
		}
		// Codes such as AUTUMN10 (letters and digits together, 4+ characters).
		if ( preg_match_all( '/\b(?=[A-Z0-9]*\d)(?=[A-Z0-9]*[A-Z])[A-Z0-9]{4,}\b/', $text, $m ) ) {
			$tokens = array_merge( $tokens, $m[0] );
		}
		// Prices, percentages and other numbers (one-digit counts like "3 tips" are ignored).
		if ( preg_match_all( '/[$€£¥]\s?\d[\d,.]*|\d[\d,.]*\s?(?:%|percent|€|£|\$)|\b\d{2,}(?:[.,]\d+)?\b/u', $text, $m ) ) {
			$tokens = array_merge( $tokens, $m[0] );
		}
		return array_values( array_unique( array_map( 'trim', $tokens ) ) );
	}

	/**
	 * Normalize for comparison: lower case, no spaces or thousands separators.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function normal( $text ) {
		$text = mb_strtolower( (string) $text );
		$text = str_replace( array( 'https://', 'http://', 'www.' ), '', $text );
		return (string) preg_replace( '/[\s,]+|percent/u', '', str_replace( 'percent', '%', $text ) );
	}
}
