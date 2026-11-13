<?php
/**
 * Splits text into overlapping pieces for the knowledge base.
 *
 * Pieces are about 500 tokens (about 2,000 characters) with about 300 characters
 * of overlap, cut at a paragraph, sentence or word end where possible, so a
 * fact that sits on a boundary is still found whole in one piece. Multibyte safe.
 *
 * Heading mode (chatbot features CB1c): split_headings() cuts HTML (h1-h4) or Markdown (# to ####)
 * at its headings first, so each piece belongs to one section and carries that section's heading
 * path ("Shipping > Iceland") and anchor (the heading's id, else a slug of its text) for links.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Knowledge;

defined( 'ABSPATH' ) || exit;

/**
 * Text chunker.
 */
final class Chunker {

	/**
	 * Target piece size, in characters (~500 tokens).
	 */
	const SIZE = 2000;

	/**
	 * Overlap between pieces, in characters.
	 */
	const OVERLAP = 300;

	/**
	 * Most pieces from one item (a very long page keeps its first 200 pieces).
	 */
	const MAX_CHUNKS = 200;

	/**
	 * Normalize whitespace: keep paragraph breaks, collapse the rest.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function clean( $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$text = preg_replace( "/[ \t\x{00A0}]+/u", ' ', $text );
		$text = preg_replace( "/ *\n */", "\n", (string) $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", (string) $text );
		return trim( (string) $text );
	}

	/**
	 * Split text into pieces.
	 *
	 * @param string $text    Text.
	 * @param int    $size    Piece size in characters.
	 * @param int    $overlap Overlap in characters.
	 * @return string[]
	 */
	public static function split( $text, $size = self::SIZE, $overlap = self::OVERLAP ) {
		$text    = self::clean( $text );
		$size    = max( 200, (int) $size );
		$overlap = max( 0, min( (int) $overlap, (int) floor( $size / 2 ) ) );
		$length  = mb_strlen( $text, 'UTF-8' );
		if ( 0 === $length ) {
			return array();
		}
		if ( $length <= $size ) {
			return array( $text );
		}

		$pieces = array();
		$start  = 0;
		$made   = 0;
		while ( $start < $length && $made < self::MAX_CHUNKS ) {
			$end = min( $length, $start + $size );
			if ( $end < $length ) {
				$end = self::boundary( $text, $start, $end );
			}
			$piece = trim( mb_substr( $text, $start, $end - $start, 'UTF-8' ) );
			if ( '' !== $piece ) {
				$pieces[] = $piece;
				++$made;
			}
			if ( $end >= $length ) {
				break;
			}
			$next  = $end - $overlap;
			$start = $next > $start ? self::word_start( $text, $next, $end ) : $end;
		}
		return $pieces;
	}

	/**
	 * Split text that has headings: one or more pieces per section, each with its heading path
	 * and anchor. Returns [] when the text has no headings (use split() then).
	 *
	 * @param string $text HTML or Markdown.
	 * @return array[] Each: content (heading path first, then the text), heading_path, anchor.
	 */
	public static function split_headings( $text ) {
		$sections = self::sections( (string) $text );
		if ( array() === $sections ) {
			return array();
		}
		$pieces = array();
		$used   = array();
		foreach ( $sections as $section ) {
			$anchor = $section['anchor'];
			if ( '' !== $anchor ) {
				$base = $anchor;
				$n    = 2;
				while ( isset( $used[ $anchor ] ) ) {
					$anchor = $base . '-' . $n;
					++$n;
				}
				$used[ $anchor ] = true;
			}
			$prefix = '' !== $section['path'] ? $section['path'] . "\n\n" : '';
			$body   = self::split( $section['body'], self::SIZE - mb_strlen( $prefix, 'UTF-8' ) );
			if ( array() === $body && '' !== $section['path'] ) {
				continue; // A heading with nothing under it.
			}
			foreach ( $body as $piece ) {
				$pieces[] = array(
					'content'      => $prefix . $piece,
					'heading_path' => mb_substr( $section['path'], 0, 255, 'UTF-8' ),
					'anchor'       => substr( $anchor, 0, 191 ),
				);
				if ( count( $pieces ) >= self::MAX_CHUNKS ) {
					return $pieces;
				}
			}
		}
		return $pieces;
	}

	/**
	 * Sections of a text: path, anchor, body (plain text). [] when there is no heading.
	 *
	 * @param string $text HTML or Markdown.
	 * @return array[]
	 */
	private static function sections( $text ) {
		$is_html = 1 === preg_match( '/<h[1-4][\s>]/i', $text );
		if ( $is_html ) {
			$parts = preg_split( '#(<h([1-4])\b([^>]*)>(.*?)</h\2\s*>)#is', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
			$step  = 5;
		} else {
			$parts = preg_split( '/^([ ]{0,3}(#{1,4})[ \t]+(.+?)[ \t#]*)$/m', str_replace( array( "\r\n", "\r" ), "\n", $text ), -1, PREG_SPLIT_DELIM_CAPTURE );
			$step  = 4;
		}
		if ( ! is_array( $parts ) || count( $parts ) < $step ) {
			return array();
		}
		$sections = array(
			array(
				'path'   => '',
				'anchor' => '',
				'body'   => self::plain( $parts[0], $is_html ),
			),
		);
		$stack    = array();
		$total    = count( $parts );
		for ( $i = 1; $i + $step - 2 < $total; $i += $step ) {
			if ( $is_html ) {
				$level = (int) $parts[ $i + 1 ];
				$attrs = (string) $parts[ $i + 2 ];
				$title = self::plain( $parts[ $i + 3 ], true );
				$body  = isset( $parts[ $i + 4 ] ) ? $parts[ $i + 4 ] : '';
				$id    = preg_match( '/\bid\s*=\s*["\']([^"\']+)["\']/i', $attrs, $m ) ? $m[1] : '';
			} else {
				$level = strlen( $parts[ $i + 1 ] );
				$title = trim( (string) $parts[ $i + 2 ] );
				$body  = isset( $parts[ $i + 3 ] ) ? $parts[ $i + 3 ] : '';
				$id    = '';
			}
			$title = trim( (string) preg_replace( '/\s+/u', ' ', $title ) );
			if ( '' === $title ) {
				$sections[ count( $sections ) - 1 ]['body'] .= "\n\n" . self::plain( $body, $is_html );
				continue;
			}
			foreach ( array_keys( $stack ) as $deeper ) {
				if ( $deeper >= $level ) {
					unset( $stack[ $deeper ] );
				}
			}
			$stack[ $level ] = $title;
			ksort( $stack );
			$sections[] = array(
				'path'   => implode( ' > ', $stack ),
				'anchor' => '' !== $id ? self::slug( $id, false ) : self::slug( $title ),
				'body'   => self::plain( $body, $is_html ),
			);
		}
		return $sections;
	}

	/**
	 * Plain text of an HTML or Markdown fragment, keeping paragraph breaks.
	 *
	 * @param string $text    Fragment.
	 * @param bool   $is_html Whether it is HTML.
	 * @return string
	 */
	private static function plain( $text, $is_html ) {
		$text = (string) $text;
		if ( $is_html ) {
			$text = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', $text );
			$text = preg_replace( '#<br\s*/?>|</(p|div|li|tr|blockquote|pre|ul|ol|table)>#i', "\n", (string) $text );
			$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
		}
		return self::clean( $text );
	}

	/**
	 * Anchor slug: lowercase letters, digits and "-" (an id attribute keeps its own spelling).
	 *
	 * @param string $text  Text.
	 * @param bool   $lower Lowercase and slugify (false for an existing id).
	 * @return string
	 */
	private static function slug( $text, $lower = true ) {
		if ( ! $lower ) {
			return (string) preg_replace( '/[^A-Za-z0-9_\-:.]/', '', (string) $text );
		}
		$text = mb_strtolower( (string) $text, 'UTF-8' );
		$text = (string) preg_replace( '/[^\p{L}\p{N}]+/u', '-', $text );
		return trim( $text, '-' );
	}

	/**
	 * Best place to end a piece: the last paragraph break, else sentence end,
	 * else space in the second half of the window.
	 *
	 * @param string $text  Text.
	 * @param int    $start Piece start.
	 * @param int    $end   Hard end.
	 * @return int
	 */
	private static function boundary( $text, $start, $end ) {
		$window = mb_substr( $text, $start, $end - $start, 'UTF-8' );
		$half   = (int) floor( mb_strlen( $window, 'UTF-8' ) / 2 );
		foreach ( array( "\n\n", '. ', '! ', '? ', "\n", ' ' ) as $mark ) {
			$pos = mb_strrpos( $window, $mark, 0, 'UTF-8' );
			if ( false !== $pos && $pos >= $half ) {
				return $start + $pos + mb_strlen( rtrim( $mark ), 'UTF-8' );
			}
		}
		return $end;
	}

	/**
	 * Move a start position forward to the start of a word.
	 *
	 * @param string $text  Text.
	 * @param int    $pos   Position.
	 * @param int    $limit Do not move past this.
	 * @return int
	 */
	private static function word_start( $text, $pos, $limit ) {
		$scan = mb_substr( $text, $pos, max( 0, $limit - $pos ), 'UTF-8' );
		$cut  = mb_strpos( $scan, ' ', 0, 'UTF-8' );
		return false === $cut ? $pos : $pos + $cut + 1;
	}
}
