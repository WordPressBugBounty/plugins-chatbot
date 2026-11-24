<?php
/**
 * Markdown to blocks for articles an AI assistant writes (HC3-A, plan 6.1).
 *
 * Headings, paragraphs, lists (two levels), ordered lists, fenced and indented code,
 * block quotes, GitHub alerts ("> [!NOTE]" becomes the Help Center callout), tables,
 * images, horizontal rules. Inline: bold, italic, code, links, images, strike-through.
 * Raw HTML in the Markdown is not kept (only text is), so an assistant cannot add scripts.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Markdown converter.
 */
final class Markdown_Import {

	/**
	 * GitHub alert types => Help Center callout types.
	 */
	const ALERTS = array(
		'NOTE'      => 'note',
		'TIP'       => 'tip',
		'IMPORTANT' => 'important',
		'WARNING'   => 'warning',
		'CAUTION'   => 'warning',
	);

	/**
	 * Convert Markdown to block markup.
	 *
	 * @param string $markdown Markdown.
	 * @return string Block markup.
	 */
	public static function to_blocks( $markdown ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $markdown );
		$out   = array();
		$count = count( $lines );
		$i     = 0;
		while ( $i < $count ) {
			$line = $lines[ $i ];
			if ( '' === trim( $line ) ) {
				++$i;
				continue;
			}
			// Fenced code.
			if ( preg_match( '/^\s*(```|~~~)\s*([\w+-]*)/', $line, $m ) ) {
				$fence = $m[1];
				$code  = array();
				++$i;
				while ( $i < $count && 0 !== strpos( ltrim( $lines[ $i ] ), $fence ) ) {
					$code[] = $lines[ $i ];
					++$i;
				}
				++$i;
				$out[] = self::code( implode( "\n", $code ), $m[2] );
				continue;
			}
			// Heading.
			if ( preg_match( '/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $m ) ) {
				$out[] = self::heading( strlen( $m[1] ), $m[2] );
				++$i;
				continue;
			}
			// Horizontal rule.
			if ( preg_match( '/^\s{0,3}([-*_])(\s*\1){2,}\s*$/', $line ) ) {
				$out[] = "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->";
				++$i;
				continue;
			}
			// Block quote (and GitHub alerts).
			if ( preg_match( '/^\s{0,3}>/', $line ) ) {
				$quote = array();
				while ( $i < $count && preg_match( '/^\s{0,3}>\s?(.*)$/', $lines[ $i ], $m ) ) {
					$quote[] = $m[1];
					++$i;
				}
				$out[] = self::quote( $quote );
				continue;
			}
			// Table.
			if ( false !== strpos( $line, '|' ) && $i + 1 < $count && preg_match( '/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?\s*$/', $lines[ $i + 1 ] ) ) {
				$rows = array( $line );
				$i   += 2;
				while ( $i < $count && false !== strpos( $lines[ $i ], '|' ) && '' !== trim( $lines[ $i ] ) ) {
					$rows[] = $lines[ $i ];
					++$i;
				}
				$out[] = self::table( $rows );
				continue;
			}
			// List.
			if ( preg_match( '/^(\s*)([-*+]|\d+[.)])\s+/', $line ) ) {
				$items = array();
				while ( $i < $count && ( preg_match( '/^(\s*)([-*+]|\d+[.)])\s+(.*)$/', $lines[ $i ], $m ) || ( '' !== trim( $lines[ $i ] ) && preg_match( '/^\s{2,}\S/', $lines[ $i ] ) && $items ) ) ) {
					if ( isset( $m[3] ) && preg_match( '/^(\s*)([-*+]|\d+[.)])\s+(.*)$/', $lines[ $i ], $m ) ) {
						$items[] = array(
							'depth'   => strlen( str_replace( "\t", '    ', $m[1] ) ) >= 2 ? 1 : 0,
							'ordered' => (bool) preg_match( '/^\d/', $m[2] ),
							'text'    => $m[3],
						);
					} else {
						$items[ count( $items ) - 1 ]['text'] .= ' ' . trim( $lines[ $i ] );
					}
					$m = array();
					++$i;
				}
				$out[] = self::lists( $items );
				continue;
			}
			// Image on its own line.
			if ( preg_match( '/^\s*!\[([^\]]*)\]\(\s*<?([^)\s>]+)>?(?:\s+"[^"]*")?\s*\)\s*$/', $line, $m ) ) {
				$out[] = self::image( $m[2], $m[1] );
				++$i;
				continue;
			}
			// Indented code.
			if ( preg_match( '/^( {4}|\t)/', $line ) ) {
				$code = array();
				while ( $i < $count && ( preg_match( '/^( {4}|\t)(.*)$/', $lines[ $i ], $m ) || '' === trim( $lines[ $i ] ) ) ) {
					$code[] = isset( $m[2] ) ? $m[2] : '';
					$m      = array();
					++$i;
				}
				$out[] = self::code( rtrim( implode( "\n", $code ) ), '' );
				continue;
			}
			// Paragraph: until a blank line or another block.
			$para = array( trim( $line ) );
			++$i;
			while ( $i < $count && '' !== trim( $lines[ $i ] ) && ! self::starts_block( $lines[ $i ] ) ) {
				$para[] = trim( $lines[ $i ] );
				++$i;
			}
			$out[] = "<!-- wp:paragraph -->\n<p>" . self::inline( implode( ' ', $para ) ) . "</p>\n<!-- /wp:paragraph -->";
		}//end while
		return implode( "\n\n", $out );
	}

	/**
	 * Whether a line starts another block (ends a paragraph).
	 *
	 * @param string $line Line.
	 * @return bool
	 */
	private static function starts_block( $line ) {
		return (bool) preg_match( '/^(#{1,6}\s|\s*(```|~~~)|\s{0,3}>|\s*([-*+]|\d+[.)])\s+|\s{0,3}([-*_])(\s*\4){2,}\s*$)/', $line );
	}

	/**
	 * Inline Markdown to HTML (text escaped first).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function inline( $text ) {
		$codes = array();
		// Code spans first, kept out of the other rules.
		$text = preg_replace_callback(
			'/`([^`]+)`/',
			static function ( $m ) use ( &$codes ) {
				$codes[] = '<code>' . esc_html( $m[1] ) . '</code>';
				return "\x01" . ( count( $codes ) - 1 ) . "\x01";
			},
			(string) $text
		);
		$text = wp_strip_all_tags( $text );
		$text = esc_html( $text );
		// Images, then links.
		$text = preg_replace_callback(
			'/!\[([^\]]*)\]\(\s*([^)\s]+)(?:\s+&quot;[^&]*&quot;)?\s*\)/',
			static function ( $m ) {
				$url = self::safe_url( html_entity_decode( $m[2] ) );
				return '' === $url ? '' : '<img src="' . $url . '" alt="' . $m[1] . '"/>';
			},
			$text
		);
		$text = preg_replace_callback(
			'/\[([^\]]+)\]\(\s*([^)\s]+)(?:\s+&quot;[^&]*&quot;)?\s*\)/',
			static function ( $m ) {
				$url = self::safe_url( html_entity_decode( $m[2] ) );
				return '' === $url ? $m[1] : '<a href="' . $url . '">' . $m[1] . '</a>';
			},
			$text
		);
		$text = preg_replace( '/(\*\*|__)(?=\S)(.+?)(?<=\S)\1/', '<strong>$2</strong>', $text );
		$text = preg_replace( '/(?<![\w*])\*(?=\S)(.+?)(?<=\S)\*(?![\w*])/', '<em>$1</em>', $text );
		$text = preg_replace( '/(?<![\w_])_(?=\S)(.+?)(?<=\S)_(?![\w_])/', '<em>$1</em>', $text );
		$text = preg_replace( '/~~(?=\S)(.+?)(?<=\S)~~/', '<s>$1</s>', $text );
		return preg_replace_callback(
			"/\x01(\d+)\x01/",
			static function ( $m ) use ( $codes ) {
				return $codes[ (int) $m[1] ];
			},
			$text
		);
	}

	/**
	 * An address that is safe in an article: http(s), mailto, or relative ("/", "#", "./").
	 *
	 * @param string $url Address.
	 * @return string Escaped address, or '' when not allowed.
	 */
	public static function safe_url( $url ) {
		$url = trim( (string) $url );
		if ( ! preg_match( '#^(https?://|mailto:|/|\#|\./|\.\./)#i', $url ) ) {
			return '';
		}
		return esc_url( $url );
	}

	/**
	 * Heading block.
	 *
	 * @param int    $level Level (an article's title is the h1, so # becomes h2).
	 * @param string $text  Text.
	 * @return string
	 */
	private static function heading( $level, $text ) {
		$level = max( 2, min( 6, (int) $level ) );
		$attrs = 2 === $level ? '' : ' {"level":' . $level . '}';
		return '<!-- wp:heading' . $attrs . " -->\n<h" . $level . ' class="wp-block-heading">' . self::inline( $text ) . '</h' . $level . ">\n<!-- /wp:heading -->";
	}

	/**
	 * Code block.
	 *
	 * @param string $code     Code.
	 * @param string $language Language (kept as a class).
	 * @return string
	 */
	private static function code( $code, $language ) {
		$class = '' !== $language ? ' lang-' . sanitize_html_class( $language ) : '';
		return "<!-- wp:code -->\n<pre class=\"wp-block-code" . esc_attr( $class ) . '"><code>' . esc_html( $code ) . "</code></pre>\n<!-- /wp:code -->";
	}

	/**
	 * Quote, or a Help Center callout for a GitHub alert.
	 *
	 * @param string[] $lines Lines without the ">".
	 * @return string
	 */
	private static function quote( array $lines ) {
		$first = isset( $lines[0] ) ? trim( $lines[0] ) : '';
		if ( preg_match( '/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*(.*)$/i', $first, $m ) ) {
			$type = self::ALERTS[ strtoupper( $m[1] ) ];
			$rest = array_slice( $lines, 1 );
			if ( '' !== trim( $m[2] ) ) {
				array_unshift( $rest, $m[2] );
			}
			$inner = self::to_blocks( implode( "\n", $rest ) );
			$attrs = 'note' === $type ? '' : ' {"type":"' . $type . '"}';
			// The callout saves only its inner blocks; PHP draws the box and its label.
			return '<!-- wp:wpbot-help/callout' . $attrs . " -->\n" . $inner . "\n<!-- /wp:wpbot-help/callout -->";
		}
		return "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">\n" . self::to_blocks( implode( "\n", $lines ) ) . "\n</blockquote>\n<!-- /wp:quote -->";
	}

	/**
	 * Table block.
	 *
	 * @param string[] $rows Rows (the first is the header).
	 * @return string
	 */
	private static function table( array $rows ) {
		$cells = static function ( $row ) {
			$row = trim( trim( $row ), '|' );
			return array_map( 'trim', explode( '|', $row ) );
		};
		$head  = '<thead><tr>';
		foreach ( $cells( array_shift( $rows ) ) as $cell ) {
			$head .= '<th>' . self::inline( $cell ) . '</th>';
		}
		$head .= '</tr></thead>';
		$body  = '<tbody>';
		foreach ( $rows as $row ) {
			$body .= '<tr>';
			foreach ( $cells( $row ) as $cell ) {
				$body .= '<td>' . self::inline( $cell ) . '</td>';
			}
			$body .= '</tr>';
		}
		$body .= '</tbody>';
		return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table>" . $head . $body . "</table></figure>\n<!-- /wp:table -->";
	}

	/**
	 * List block with list items (a second level nests under the item before it).
	 *
	 * @param array $items depth, ordered, text.
	 * @return string
	 */
	private static function lists( array $items ) {
		if ( ! $items ) {
			return '';
		}
		$ordered = $items[0]['ordered'];
		$groups  = array();
		foreach ( $items as $item ) {
			if ( 1 === $item['depth'] && $groups ) {
				$groups[ count( $groups ) - 1 ]['children'][] = $item;
			} else {
				$groups[] = array(
					'item'     => $item,
					'children' => array(),
				);
			}
		}
		$tag   = $ordered ? 'ol' : 'ul';
		$attrs = $ordered ? ' {"ordered":true}' : '';
		$html  = '<!-- wp:list' . $attrs . " -->\n<" . $tag . ' class="wp-block-list">';
		foreach ( $groups as $group ) {
			$html .= "\n<!-- wp:list-item -->\n<li>" . self::inline( $group['item']['text'] );
			if ( $group['children'] ) {
				$sub = $group['children'];
				foreach ( $sub as &$child ) {
					$child['depth'] = 0;
				}
				unset( $child );
				$html .= "\n" . self::lists( $sub );
			}
			$html .= "</li>\n<!-- /wp:list-item -->";
		}
		return $html . "\n</" . $tag . ">\n<!-- /wp:list -->";
	}

	/**
	 * Image block (an address; the image is not copied into the media library).
	 *
	 * @param string $url Address.
	 * @param string $alt Alternative text.
	 * @return string
	 */
	private static function image( $url, $alt ) {
		$url = self::safe_url( $url );
		if ( '' === $url ) {
			return '';
		}
		return "<!-- wp:image -->\n<figure class=\"wp-block-image\"><img src=\"" . $url . '" alt="' . esc_attr( $alt ) . "\"/></figure>\n<!-- /wp:image -->";
	}
}
