<?php
/**
 * The HTML allowlist for AI-written text.
 *
 * AI output is untrusted. Inline text keeps only strong, em, code and links
 * (http/https/mailto, no javascript:), with no style or event attributes.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Output;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitising for AI text.
 */
final class Kses_Policy {

	/**
	 * Allowed inline tags.
	 *
	 * @return array
	 */
	public static function inline_tags() {
		return array(
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'code'   => array(),
			'br'     => array(),
			'a'      => array(
				'href'  => true,
				'title' => true,
				'rel'   => true,
			),
		);
	}

	/**
	 * Clean one piece of inline text.
	 *
	 * @param string $text Text from the AI.
	 * @return string Safe HTML.
	 */
	public static function inline( $text ) {
		$text = (string) $text;
		// Markdown emphasis the model may still use.
		$text = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text );
		$text = preg_replace( '/(?<![\*\w])\*(?!\s)(.+?)(?<!\s)\*(?![\*\w])/s', '<em>$1</em>', $text );
		$text = preg_replace( '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2">$1</a>', $text );
		return trim( wp_kses( $text, self::inline_tags(), array( 'http', 'https', 'mailto' ) ) );
	}

	/**
	 * Plain text (no tags at all).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function plain( $text ) {
		return trim( sanitize_text_field( wp_strip_all_tags( (string) $text ) ) );
	}
}
