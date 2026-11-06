<?php
/**
 * Splits a post's content into sections and text units that can be safely
 * rewritten, and puts rewritten units back without touching anything else.
 *
 * A unit is the inner text of a paragraph, heading or list item (block
 * editor), or a plain paragraph (Classic Editor). Every unit records its
 * exact byte position in the original content, so changes are spliced in
 * place: block comments, attributes, custom blocks, shortcodes, embeds and
 * all other markup stay byte for byte as they were.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Improve;

use QuantumCloud\AiWriter\Output\Kses_Policy;

defined( 'ABSPATH' ) || exit;

/**
 * Content units.
 */
final class Content_Units {

	/**
	 * Block types whose inner text is a unit.
	 *
	 * @var array<string,string> block name => wrapper tag pattern.
	 */
	const TEXT_BLOCKS = array(
		'core/paragraph' => 'p',
		'core/heading'   => 'h[1-6]',
		'core/list-item' => 'li',
	);

	/**
	 * Sections and units of some content.
	 *
	 * @param string $content Post content.
	 * @return array{sections: array[], units: array[]}
	 */
	public static function extract( $content ) {
		$content = (string) $content;
		$units   = has_blocks( $content ) ? self::block_units( $content ) : self::classic_units( $content );

		$sections = array(
			array(
				'index'   => 0,
				'heading' => '',
				'units'   => array(),
			),
		);
		foreach ( $units as $i => $unit ) {
			if ( 'heading' === $unit['type'] && 2 === $unit['level'] ) {
				$sections[] = array(
					'index'   => count( $sections ),
					'heading' => wp_strip_all_tags( $unit['html'] ),
					'units'   => array(),
				);
			}
			$units[ $i ]['section']                        = count( $sections ) - 1;
			$sections[ count( $sections ) - 1 ]['units'][] = $unit['id'];
		}
		if ( array() === $sections[0]['units'] ) {
			array_shift( $sections );
			foreach ( $sections as $i => $section ) {
				$sections[ $i ]['index'] = $i;
			}
			foreach ( $units as $i => $unit ) {
				--$units[ $i ]['section'];
			}
		}
		return array(
			'sections' => $sections,
			'units'    => $units,
		);
	}

	/**
	 * Units of block content, in document order.
	 *
	 * @param string $content Content.
	 * @return array[]
	 */
	private static function block_units( $content ) {
		$units  = array();
		$cursor = 0;
		self::walk( parse_blocks( $content ), $content, $cursor, $units );
		return $units;
	}

	/**
	 * Walk blocks in order, locating each leaf's HTML from the cursor on.
	 *
	 * @param array[] $blocks  Parsed blocks.
	 * @param string  $content Content.
	 * @param int     $cursor  Byte position reached.
	 * @param array[] $units   Units found.
	 * @return void
	 */
	private static function walk( array $blocks, $content, &$cursor, array &$units ) {
		foreach ( $blocks as $block ) {
			$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::walk( $block['innerBlocks'], $content, $cursor, $units );
				continue;
			}
			$html = isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '';
			if ( '' === trim( $html ) ) {
				continue;
			}
			$pos = strpos( $content, $html, $cursor );
			if ( false === $pos ) {
				continue;
			}
			$cursor = $pos + strlen( $html );
			if ( ! isset( self::TEXT_BLOCKS[ $name ] ) ) {
				continue;
			}
			if ( ! preg_match( '#^(\s*<(' . self::TEXT_BLOCKS[ $name ] . ')\b[^>]*>)(.*)</\2>\s*$#s', $html, $match, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			$inner = $match[3][0];
			if ( '' === trim( wp_strip_all_tags( $inner ) ) ) {
				continue;
			}
			$level   = 'core/heading' === $name ? (int) substr( $match[2][0], 1 ) : 0;
			$units[] = array(
				'id'     => 'u' . count( $units ),
				'type'   => 'core/heading' === $name ? 'heading' : ( 'core/list-item' === $name ? 'item' : 'paragraph' ),
				'level'  => $level,
				'html'   => $inner,
				'start'  => $pos + $match[3][1],
				'length' => strlen( $inner ),
				'locked' => ! self::simple( $inner ),
			);
		}
	}

	/**
	 * Units of Classic Editor content: headings and plain paragraphs
	 * (separated by blank lines). Lists, tables, shortcode-only lines and
	 * other markup are left alone.
	 *
	 * @param string $content Content.
	 * @return array[]
	 */
	private static function classic_units( $content ) {
		$units = array();
		if ( ! preg_match_all( '/(?:^|\n\s*\n)((?:(?!\n\s*\n).)+)/s', $content, $chunks, PREG_OFFSET_CAPTURE ) ) {
			return $units;
		}
		foreach ( $chunks[1] as $chunk ) {
			list( $text, $offset ) = $chunk;
			$lead                  = strlen( $text ) - strlen( ltrim( $text ) );
			$text                  = trim( $text );
			$offset               += $lead;
			if ( preg_match( '#^<(h[1-6]|p)\b[^>]*>(.*)</\1>$#s', $text, $match, PREG_OFFSET_CAPTURE ) ) {
				$inner = $match[2][0];
				$type  = 'p' === $match[1][0] ? 'paragraph' : 'heading';
				$start = $offset + $match[2][1];
				$level = 'heading' === $type ? (int) substr( $match[1][0], 1 ) : 0;
			} elseif ( ! preg_match( '#^\s*<(?!(?:a|strong|b|em|i|span|code|abbr)\b)[a-z!]#i', $text ) && ! preg_match( '/^\[[^\]]+\]$/', $text ) ) {
				$inner = $text;
				$type  = 'paragraph';
				$start = $offset;
				$level = 0;
			} else {
				continue;
			}
			if ( '' === trim( wp_strip_all_tags( $inner ) ) ) {
				continue;
			}
			$units[] = array(
				'id'     => 'u' . count( $units ),
				'type'   => $type,
				'level'  => $level,
				'html'   => $inner,
				'start'  => $start,
				'length' => strlen( $inner ),
				'locked' => ! self::simple( $inner ),
			);
		}
		return $units;
	}

	/**
	 * Whether a unit uses only formatting a rewrite can reproduce (bold,
	 * italics, code, line breaks, links with title and rel). Anything else
	 * (images, spans with styles, link targets, comments) locks the unit so
	 * it is never rewritten.
	 *
	 * @param string $html Inner HTML.
	 * @return bool
	 */
	public static function simple( $html ) {
		if ( false !== strpos( $html, '<!--' ) ) {
			return false;
		}
		$normal = static function ( $text ) {
			return trim( html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		};
		return $normal( wp_kses( $html, Kses_Policy::inline_tags(), array( 'http', 'https', 'mailto' ) ) ) === $normal( $html );
	}

	/**
	 * Put new inner HTML into the content at the units' positions.
	 *
	 * @param string $content  Original content.
	 * @param array  $units    Units from extract().
	 * @param array  $changes  Unit id => new inner HTML.
	 * @return string
	 */
	public static function splice( $content, array $units, array $changes ) {
		$by_id = array();
		foreach ( $units as $unit ) {
			$by_id[ $unit['id'] ] = $unit;
		}
		$edits = array();
		foreach ( $changes as $id => $html ) {
			if ( isset( $by_id[ $id ] ) && substr( $content, $by_id[ $id ]['start'], $by_id[ $id ]['length'] ) === $by_id[ $id ]['html'] ) {
				$edits[] = array( $by_id[ $id ]['start'], $by_id[ $id ]['length'], (string) $html );
			}
		}
		// From the end backwards, so earlier positions stay valid.
		usort(
			$edits,
			static function ( $a, $b ) {
				return $b[0] - $a[0];
			}
		);
		foreach ( $edits as $edit ) {
			$content = substr_replace( $content, $edit[2], $edit[0], $edit[1] );
		}
		return $content;
	}

	/**
	 * Why a rewrite of a unit cannot be used ('' when it is safe): every
	 * link and shortcode must survive, numbers must stay, no new links.
	 *
	 * @param string $original Original inner HTML.
	 * @param string $rewrite  Rewrite.
	 * @return string
	 */
	public static function unsafe( $original, $rewrite ) {
		if ( '' === trim( wp_strip_all_tags( $rewrite ) ) ) {
			return __( 'The rewrite was empty.', 'chatbot' );
		}
		preg_match_all( '/href\s*=\s*["\']([^"\']+)["\']/i', $original, $original_links );
		preg_match_all( '/href\s*=\s*["\']([^"\']+)["\']/i', $rewrite, $rewrite_links );
		if ( array_diff( $original_links[1], $rewrite_links[1] ) ) {
			return __( 'A link would have been lost.', 'chatbot' );
		}
		if ( array_diff( $rewrite_links[1], $original_links[1] ) ) {
			return __( 'The rewrite added a link that was not there.', 'chatbot' );
		}
		preg_match_all( '/' . get_shortcode_regex() . '/s', $original, $codes );
		foreach ( $codes[0] as $code ) {
			if ( false === strpos( $rewrite, $code ) ) {
				return __( 'A shortcode would have been changed.', 'chatbot' );
			}
		}
		preg_match_all( '/\d+(?:[.,]\d+)*/u', wp_strip_all_tags( $original ), $numbers );
		$plain = wp_strip_all_tags( $rewrite );
		foreach ( $numbers[0] as $number ) {
			if ( false === strpos( $plain, $number ) ) {
				/* translators: %s: a number. */
				return sprintf( __( 'The number %s would have been lost.', 'chatbot' ), $number );
			}
		}
		return '';
	}

	/**
	 * Clean a rewrite: allowed inline HTML only, shortcodes kept as text.
	 *
	 * @param string $html Rewrite from the AI.
	 * @return string
	 */
	public static function clean( $html ) {
		return Kses_Policy::inline( (string) $html );
	}
}
