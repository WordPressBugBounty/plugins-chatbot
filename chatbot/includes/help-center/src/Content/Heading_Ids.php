<?php
/**
 * Heading ids for links to a section.
 *
 * WPBot Core's chunker uses a heading's own id as the section anchor, so the
 * article page and the HTML sent to the knowledge base get the same ids from
 * this one function: h1 to h4 without an id get an ASCII slug of their text
 * (repeats get -2, -3); headings that already have an id keep it.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Adds ids to headings.
 */
final class Heading_Ids {

	/**
	 * Add ids to h1 to h4 that have none.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function add( $html ) {
		$html = (string) $html;
		if ( '' === $html || 1 !== preg_match( '/<h[1-4][\s>]/i', $html ) ) {
			return $html;
		}
		$used = array();
		if ( preg_match_all( '/<h[1-4]\b[^>]*\bid\s*=\s*["\']([^"\']+)["\']/i', $html, $found ) ) {
			foreach ( $found[1] as $id ) {
				$used[ $id ] = true;
			}
		}
		return (string) preg_replace_callback(
			'#<h([1-4])(\s[^>]*)?>(.*?)</h\1>#is',
			static function ( $found_heading ) use ( &$used ) {
				$attrs = isset( $found_heading[2] ) ? (string) $found_heading[2] : '';
				if ( 1 === preg_match( '/\bid\s*=/i', $attrs ) ) {
					return $found_heading[0];
				}
				$base = self::slug( $found_heading[3] );
				$id   = $base;
				$n    = 2;
				while ( isset( $used[ $id ] ) ) {
					$id = $base . '-' . $n;
					++$n;
				}
				$used[ $id ] = true;
				return '<h' . $found_heading[1] . ' id="' . $id . '"' . $attrs . '>' . $found_heading[3] . '</h' . $found_heading[1] . '>';
			},
			$html
		);
	}

	/**
	 * ASCII slug of a heading's text ("section" when nothing is left).
	 *
	 * @param string $inner Heading HTML.
	 * @return string
	 */
	public static function slug( $inner ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $inner ), ENT_QUOTES, 'UTF-8' );
		if ( function_exists( 'remove_accents' ) ) {
			$text = remove_accents( $text );
		}
		$text = strtolower( $text );
		$text = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', $text ), '-' );
		return '' === $text ? 'section' : substr( $text, 0, 80 );
	}
}
