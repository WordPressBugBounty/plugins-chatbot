<?php
/**
 * HTML to Markdown for the Markdown copy of each article (HC1-12, plan 5.1 "SEO").
 *
 * Covers what articles contain: headings, paragraphs, emphasis, links, images,
 * lists (nested), code and code blocks, quotes, tables, rules, the Help Center
 * blocks (callouts become quotes, accordions a bold question with its answer,
 * tabs their headings). Buttons, scripts, styles, forms and SVG are left out.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Seo;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument properties.

/**
 * Converter.
 */
final class Markdown {

	/**
	 * Elements left out with their content.
	 *
	 * @var string[]
	 */
	const DROP = array( 'script', 'style', 'noscript', 'button', 'svg', 'form', 'input', 'select', 'textarea', 'template', 'iframe', 'nav' );

	/**
	 * Convert HTML.
	 *
	 * @param string $html HTML.
	 * @return string Markdown.
	 */
	public static function from_html( $html ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return '';
		}
		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div id="wpbot-md-root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$root = $doc->getElementById( 'wpbot-md-root' );
		if ( ! $root ) {
			return '';
		}
		$out = self::blocks( $root, 0 );
		$out = preg_replace( "/[ \t]+\n/", "\n", $out );
		$out = preg_replace( "/\n{3,}/", "\n\n", (string) $out );
		return trim( (string) $out ) . "\n";
	}

	/**
	 * Children of a block container, as Markdown blocks.
	 *
	 * @param \DOMNode $node  Container.
	 * @param int      $depth List depth.
	 * @return string
	 */
	private static function blocks( \DOMNode $node, $depth ) {
		$out    = '';
		$inline = '';
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof \DOMElement && self::is_block( $child ) ) {
				if ( '' !== trim( $inline ) ) {
					$out .= trim( $inline ) . "\n\n";
				}
				$inline = '';
				$out   .= self::block( $child, $depth );
			} else {
				$inline .= self::inline( $child );
			}
		}
		if ( '' !== trim( $inline ) ) {
			$out .= trim( $inline ) . "\n\n";
		}
		return $out;
	}

	/**
	 * Whether an element starts a block.
	 *
	 * @param \DOMElement $el Element.
	 * @return bool
	 */
	private static function is_block( \DOMElement $el ) {
		return in_array( strtolower( $el->nodeName ), array( 'p', 'div', 'section', 'article', 'aside', 'header', 'footer', 'main', 'figure', 'figcaption', 'blockquote', 'pre', 'ul', 'ol', 'li', 'table', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'details', 'summary', 'dl', 'dt', 'dd' ), true ) || in_array( strtolower( $el->nodeName ), self::DROP, true );
	}

	/**
	 * One block element.
	 *
	 * @param \DOMElement $el    Element.
	 * @param int         $depth List depth.
	 * @return string
	 */
	private static function block( \DOMElement $el, $depth ) {
		$name  = strtolower( $el->nodeName );
		$class = ' ' . (string) $el->getAttribute( 'class' ) . ' ';
		if ( in_array( $name, self::DROP, true ) || false !== strpos( $class, ' wpbot-hc-term-defs ' ) || ( $el->hasAttribute( 'hidden' ) && false === strpos( $class, 'wpbot-help-tab' ) ) ) {
			return '';
		}
		switch ( $name ) {
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$text = self::clean_inline( self::inline_children( $el ) );
				if ( '' === $text ) {
					return '';
				}
				// Inside a list (step titles): bold text instead of a heading.
				return ( $depth > 0 ? '**' . $text . '**' : str_repeat( '#', (int) substr( $name, 1 ) ) . ' ' . $text ) . "\n\n";
			case 'p':
			case 'figcaption':
			case 'summary':
			case 'dt':
				$text = self::clean_inline( self::inline_children( $el ) );
				if ( '' === $text ) {
					return '';
				}
				return ( 'summary' === $name || 'dt' === $name ? '**' . $text . '**' : $text ) . "\n\n";
			case 'hr':
				return "---\n\n";
			case 'pre':
				$code = $el->getElementsByTagName( 'code' )->item( 0 );
				$lang = '';
				if ( $code instanceof \DOMElement && preg_match( '/(?:language|lang)-([a-z0-9+#-]+)/i', (string) $code->getAttribute( 'class' ), $m ) ) {
					$lang = strtolower( $m[1] );
				}
				$text  = rtrim( (string) $el->textContent );
				$fence = false !== strpos( $text, '```' ) ? '~~~' : '```';
				return $fence . $lang . "\n" . $text . "\n" . $fence . "\n\n";
			case 'blockquote':
			case 'aside':
				$inner = trim( self::blocks( $el, $depth ) );
				if ( '' === $inner ) {
					return '';
				}
				return '> ' . str_replace( "\n", "\n> ", $inner ) . "\n\n";
			case 'ul':
			case 'ol':
				return self::list_block( $el, $depth ) . ( 0 === $depth ? "\n" : '' );
			case 'table':
				return self::table( $el );
			default:
				// Containers (div, section, figure, details, li outside a list, dl, dd).
				return self::blocks( $el, $depth );
		}//end switch
	}

	/**
	 * A list.
	 *
	 * @param \DOMElement $items_el ul or ol.
	 * @param int         $depth Depth.
	 * @return string
	 */
	private static function list_block( \DOMElement $items_el, $depth ) {
		$out     = '';
		$ordered = 'ol' === strtolower( $items_el->nodeName );
		$n       = $items_el->hasAttribute( 'start' ) ? max( 1, (int) $items_el->getAttribute( 'start' ) ) : 1;
		$indent  = str_repeat( '   ', $depth );
		foreach ( $items_el->childNodes as $item ) {
			if ( ! $item instanceof \DOMElement || 'li' !== strtolower( $item->nodeName ) ) {
				continue;
			}
			$marker = $ordered ? $n . '. ' : '- ';
			++$n;
			// Tight lists: no blank lines inside an item.
			$body  = trim( (string) preg_replace( "/\n{2,}/", "\n", self::blocks( $item, $depth + 1 ) ) );
			$lines = explode( "\n", $body );
			$first = array_shift( $lines );
			$out  .= $indent . $marker . $first . "\n";
			foreach ( $lines as $line ) {
				$inner = $indent . '   ';
				if ( '' === trim( $line ) ) {
					$out .= "\n";
				} else {
					// Nested lists come indented already; other lines continue the item.
					$out .= ( 0 === strpos( $line, $inner ) ? $line : $inner . $line ) . "\n";
				}
			}
		}//end foreach
		return $out;
	}

	/**
	 * A table as a GitHub-style table (first row as the header).
	 *
	 * @param \DOMElement $table Table.
	 * @return string
	 */
	private static function table( \DOMElement $table ) {
		$rows = array();
		foreach ( $table->getElementsByTagName( 'tr' ) as $tr ) {
			$cells = array();
			foreach ( $tr->childNodes as $cell ) {
				if ( $cell instanceof \DOMElement && in_array( strtolower( $cell->nodeName ), array( 'td', 'th' ), true ) ) {
					$cells[] = str_replace( '|', '\|', self::clean_inline( self::inline_children( $cell ) ) );
				}
			}
			if ( $cells ) {
				$rows[] = $cells;
			}
		}
		if ( ! $rows ) {
			return '';
		}
		$width = max( array_map( 'count', $rows ) );
		$out   = '';
		foreach ( $rows as $i => $cells ) {
			$cells = array_pad( $cells, $width, '' );
			$out  .= '| ' . implode( ' | ', $cells ) . " |\n";
			if ( 0 === $i ) {
				$out .= '|' . str_repeat( ' --- |', $width ) . "\n";
			}
		}
		return $out . "\n";
	}

	/**
	 * Inline content of an element's children.
	 *
	 * @param \DOMNode $node Node.
	 * @return string
	 */
	private static function inline_children( \DOMNode $node ) {
		$out = '';
		foreach ( $node->childNodes as $child ) {
			$out .= self::inline( $child );
		}
		return $out;
	}

	/**
	 * One inline node.
	 *
	 * @param \DOMNode $node Node.
	 * @return string
	 */
	private static function inline( \DOMNode $node ) {
		if ( $node instanceof \DOMText ) {
			return self::escape( (string) preg_replace( '/\s+/u', ' ', $node->nodeValue ) );
		}
		if ( ! $node instanceof \DOMElement ) {
			return '';
		}
		$name = strtolower( $node->nodeName );
		if ( in_array( $name, self::DROP, true ) ) {
			return '';
		}
		switch ( $name ) {
			case 'br':
				return "  \n";
			case 'strong':
			case 'b':
				$text = trim( self::inline_children( $node ) );
				return '' === $text ? '' : '**' . $text . '** ';
			case 'em':
			case 'i':
				$text = trim( self::inline_children( $node ) );
				return '' === $text ? '' : '_' . $text . '_ ';
			case 'code':
			case 'kbd':
				$text = (string) $node->textContent;
				$tick = false !== strpos( $text, '`' ) ? '``' : '`';
				return $tick . $text . $tick;
			case 'a':
				$text = trim( self::inline_children( $node ) );
				$href = (string) $node->getAttribute( 'href' );
				if ( '' === $href || 0 === strpos( $href, 'javascript:' ) ) {
					return $text;
				}
				return '[' . ( '' !== $text ? $text : $href ) . '](' . str_replace( array( ' ', ')' ), array( '%20', '%29' ), $href ) . ')';
			case 'img':
				$src = (string) $node->getAttribute( 'src' );
				return '' === $src ? '' : '![' . self::escape( (string) $node->getAttribute( 'alt' ) ) . '](' . str_replace( ' ', '%20', $src ) . ')';
			default:
				return self::inline_children( $node );
		}//end switch
	}

	/**
	 * Tidy inline text: single spaces, no space before punctuation left by emphasis.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function clean_inline( $text ) {
		$text = (string) preg_replace( '/[ \t]+/', ' ', $text );
		$text = (string) preg_replace( '/(\*\*|_) ([.,;:!?)])/', '$1$2', $text );
		return trim( $text );
	}

	/**
	 * Escape characters that would start Markdown syntax.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function escape( $text ) {
		return str_replace( array( '\\', '*', '_', '`', '[', ']' ), array( '\\\\', '\*', '\_', '\`', '\[', '\]' ), $text );
	}
}
