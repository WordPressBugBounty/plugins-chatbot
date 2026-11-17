<?php
/**
 * Cutting text without splitting a character.
 *
 * PHP's substr() counts bytes. Letters outside plain English (Cyrillic, Greek, accented letters,
 * Arabic, CJK, emoji) take 2 to 4 bytes in UTF-8, so a byte cut can end in the middle of one. The
 * broken half then turns into "?" when the text is sent as JSON (wp_json_encode() converts invalid
 * UTF-8 with mb_convert_encoding()), or is rejected by the database. Support report, 14 November
 * 2026: Bulgarian replies showed "Универса??ен кр??дит".
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Text;

defined( 'ABSPATH' ) || exit;

/**
 * UTF-8 safe cuts.
 *
 * @internal Not part of the public API; may change.
 */
final class Utf8 {

	/**
	 * Moves a byte position back to the start of the character it falls in.
	 *
	 * @param string $text Text (UTF-8).
	 * @param int    $at   Byte position.
	 * @return int A position where no character is split (0 to strlen( $text )).
	 */
	public static function boundary( $text, $at ) {
		$text   = (string) $text;
		$length = strlen( $text );
		$at     = max( 0, min( (int) $at, $length ) );
		// A UTF-8 continuation byte is 10xxxxxx; at most 3 follow a lead byte.
		$back = 0;
		while ( $at > 0 && $at < $length && 0x80 === ( ord( $text[ $at ] ) & 0xC0 ) && $back < 3 ) {
			--$at;
			++$back;
		}
		return $at;
	}

	/**
	 * The first $max bytes of the text, shortened so that no character is split.
	 *
	 * For database columns and other limits counted in bytes.
	 *
	 * @param string $text Text (UTF-8).
	 * @param int    $max  Bytes.
	 * @return string
	 */
	public static function cut_bytes( $text, $max ) {
		$text = (string) $text;
		if ( strlen( $text ) <= $max ) {
			return $text;
		}
		return substr( $text, 0, self::boundary( $text, $max ) );
	}

	/**
	 * The first $max characters of the text.
	 *
	 * @param string $text Text (UTF-8).
	 * @param int    $max  Characters.
	 * @return string
	 */
	public static function cut( $text, $max ) {
		$text = (string) $text;
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, (int) $max, 'UTF-8' );
		}
		return self::cut_bytes( $text, $max );
	}

	/**
	 * Length in characters.
	 *
	 * @param string $text Text (UTF-8).
	 * @return int
	 */
	public static function length( $text ) {
		$text = (string) $text;
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}
}
