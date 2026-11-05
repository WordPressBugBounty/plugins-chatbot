<?php
/**
 * Safe CSS minifier (original code).
 *
 * Removes comments (keeping /*! and licence comments) and whitespace, the
 * last semicolon of each block, and spaces around { } ; , > and after a
 * colon. Strings, url() contents and the inside of calc(), min(), max() and
 * clamp() are copied as they are (only runs of whitespace there become one
 * space). Relative url() and @import paths are rewritten to absolute URLs of
 * the original folder, because the copy is served from the uploads folder.
 *
 * It never reorders or merges rules, so the cascade is unchanged.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * One-pass CSS minifier.
 */
class Css_Minifier {

	/**
	 * Functions whose spacing is kept.
	 */
	const SPACED_FUNCTIONS = array( 'calc', 'min', 'max', 'clamp', '-webkit-calc', '-moz-calc' );

	/**
	 * Source being read.
	 *
	 * @var string
	 */
	private $css = '';

	/**
	 * Read position.
	 *
	 * @var int
	 */
	private $pos = 0;

	/**
	 * Source length.
	 *
	 * @var int
	 */
	private $len = 0;

	/**
	 * Output so far.
	 *
	 * @var string
	 */
	private $out = '';

	/**
	 * URL of the folder the original file lives in (with trailing slash).
	 *
	 * @var string
	 */
	private $base_url = '';

	/**
	 * Whether a whitespace run was skipped since the last character written.
	 *
	 * @var bool
	 */
	private $pending_space = false;

	/**
	 * Minify a stylesheet.
	 *
	 * @param string $css      Source.
	 * @param string $base_url URL of the original file's folder, for url() rewriting ('' = no rewriting).
	 * @return string
	 */
	public function minify( $css, $base_url = '' ) {
		$this->css           = (string) $css;
		$this->len           = strlen( $this->css );
		$this->pos           = 0;
		$this->out           = '';
		$this->base_url      = '' === $base_url ? '' : trailingslashit( $base_url );
		$this->pending_space = false;

		// A UTF-8 byte order mark would end up in the middle of a combined file.
		if ( 0 === strpos( $this->css, "\xEF\xBB\xBF" ) ) {
			$this->pos = 3;
		}

		while ( $this->pos < $this->len ) {
			$char = $this->css[ $this->pos ];

			if ( '/' === $char && $this->peek( 1 ) === '*' ) {
				$this->comment();
				continue;
			}
			if ( '"' === $char || "'" === $char ) {
				$this->emit_pending();
				$string = $this->read_string();
				$this->out .= $this->after_import() ? $this->quoted_url( $string ) : $string;
				continue;
			}
			if ( $this->is_space( $char ) ) {
				$this->pending_space = true;
				++$this->pos;
				continue;
			}
			if ( '(' === $char ) {
				$name = strtolower( $this->function_name() );
				if ( 'url' === $name ) {
					$this->out .= '(' . $this->read_url();
					continue;
				}
				if ( in_array( $name, self::SPACED_FUNCTIONS, true ) ) {
					$this->out .= $this->read_spaced_function();
					continue;
				}
			}
			if ( false !== strpos( '{};,>', $char ) ) {
				// No space before these.
				$this->pending_space = false;
				if ( '}' === $char && '' !== $this->out && ';' === substr( $this->out, -1 ) ) {
					$this->out = substr( $this->out, 0, -1 );
				}
				$this->out .= $char;
				++$this->pos;
				$this->skip_space();
				continue;
			}
			if ( ':' === $char ) {
				// Space before a colon can matter in selectors ("a :hover"), so it stays; space after never does.
				$this->emit_pending();
				$this->out .= ':';
				++$this->pos;
				$this->skip_space();
				continue;
			}

			$this->emit_pending();
			$this->out .= $char;
			++$this->pos;
		}

		return trim( $this->out );
	}

	/**
	 * Character at an offset from the read position.
	 *
	 * @param int $offset Offset.
	 * @return string
	 */
	private function peek( $offset ) {
		$at = $this->pos + $offset;
		return $at < $this->len ? $this->css[ $at ] : '';
	}

	/**
	 * CSS whitespace.
	 *
	 * @param string $char Character.
	 * @return bool
	 */
	private function is_space( $char ) {
		return ' ' === $char || "\n" === $char || "\t" === $char || "\r" === $char || "\f" === $char;
	}

	/**
	 * Skip whitespace and comments that follow (they are not needed after { } ; , > :).
	 *
	 * @return void
	 */
	private function skip_space() {
		while ( $this->pos < $this->len ) {
			$char = $this->css[ $this->pos ];
			if ( $this->is_space( $char ) ) {
				++$this->pos;
			} elseif ( '/' === $char && $this->peek( 1 ) === '*' && $this->peek( 2 ) !== '!' && ! $this->is_licence_comment() ) {
				$end       = strpos( $this->css, '*/', $this->pos + 2 );
				$this->pos = false === $end ? $this->len : $end + 2;
			} else {
				break;
			}
		}
		$this->pending_space = false;
	}

	/**
	 * Write one space if whitespace was skipped and the output needs it.
	 *
	 * @return void
	 */
	private function emit_pending() {
		if ( $this->pending_space ) {
			$last = '' === $this->out ? '' : substr( $this->out, -1 );
			if ( '' !== $last && false === strpos( '{};,>', $last ) ) {
				$this->out .= ' ';
			}
			$this->pending_space = false;
		}
	}

	/**
	 * Whether the comment at the read position is a licence comment.
	 *
	 * @return bool
	 */
	private function is_licence_comment() {
		$end  = strpos( $this->css, '*/', $this->pos + 2 );
		$body = false === $end ? substr( $this->css, $this->pos ) : substr( $this->css, $this->pos, $end - $this->pos );
		return false !== stripos( $body, '@license' ) || false !== stripos( $body, '@preserve' );
	}

	/**
	 * Handle a comment: keep /*! and licence comments, drop the rest.
	 * A dropped comment counts as whitespace ("a/**\/b" stays two tokens).
	 *
	 * @return void
	 */
	private function comment() {
		$end  = strpos( $this->css, '*/', $this->pos + 2 );
		$stop = false === $end ? $this->len : $end + 2;
		if ( $this->peek( 2 ) === '!' || $this->is_licence_comment() ) {
			$this->emit_pending();
			$this->out .= substr( $this->css, $this->pos, $stop - $this->pos ) . "\n";
		} else {
			$this->pending_space = true;
		}
		$this->pos = $stop;
	}

	/**
	 * Read a quoted string, escapes included, as it is.
	 *
	 * @return string
	 */
	private function read_string() {
		$quote = $this->css[ $this->pos ];
		$start = $this->pos;
		++$this->pos;
		while ( $this->pos < $this->len ) {
			$char = $this->css[ $this->pos ];
			if ( '\\' === $char ) {
				$this->pos += 2;
				continue;
			}
			++$this->pos;
			if ( $char === $quote || "\n" === $char ) {
				break;
			}
		}
		return substr( $this->css, $start, $this->pos - $start );
	}

	/**
	 * The identifier written just before the read position ("url" in "url(").
	 *
	 * @return string
	 */
	private function function_name() {
		if ( ! preg_match( '/(-?[A-Za-z][A-Za-z0-9_-]*)$/', substr( $this->out, -40 ), $match ) ) {
			return '';
		}
		return $match[1];
	}

	/**
	 * Whether the output ends with "@import" (the next string is a URL).
	 *
	 * @return bool
	 */
	private function after_import() {
		return (bool) preg_match( '/@import\s?$/i', substr( $this->out, -8 ) );
	}

	/**
	 * Read url( … ) after the opening parenthesis and return its rewritten rest.
	 *
	 * @return string Contents and closing parenthesis.
	 */
	private function read_url() {
		++$this->pos;
		while ( $this->pos < $this->len && $this->is_space( $this->css[ $this->pos ] ) ) {
			++$this->pos;
		}
		if ( $this->pos < $this->len && ( '"' === $this->css[ $this->pos ] || "'" === $this->css[ $this->pos ] ) ) {
			$value = $this->quoted_url( $this->read_string() );
			$end   = strpos( $this->css, ')', $this->pos );
			$this->pos = false === $end ? $this->len : $end + 1;
			return $value . ')';
		}
		$end       = strpos( $this->css, ')', $this->pos );
		$stop      = false === $end ? $this->len : $end;
		$raw       = trim( substr( $this->css, $this->pos, $stop - $this->pos ) );
		$this->pos = false === $end ? $this->len : $end + 1;
		return $this->rewrite( $raw ) . ')';
	}

	/**
	 * Rewrite the URL inside a quoted string, keeping its quotes.
	 *
	 * @param string $string Quoted string.
	 * @return string
	 */
	private function quoted_url( $string ) {
		$quote = $string[0];
		$inner = substr( $string, 1, -1 );
		if ( substr( $string, -1 ) !== $quote ) {
			return $string;
		}
		return $quote . $this->rewrite( $inner ) . $quote;
	}

	/**
	 * Make a relative URL absolute against the original file's folder.
	 *
	 * @param string $url URL as written.
	 * @return string
	 */
	public function rewrite( $url ) {
		if ( '' === $this->base_url || '' === $url || false !== strpos( $url, '\\' ) || preg_match( '#^([a-z][a-z0-9+.-]*:|/|\#|%23)#i', $url ) ) {
			// Absolute, root-relative, data:, fragment-only or escaped: leave alone.
			return $url;
		}
		return self::join( $this->base_url, $url );
	}

	/**
	 * Join a folder URL and a relative path, resolving ./ and ../ segments.
	 *
	 * @param string $base Folder URL with trailing slash.
	 * @param string $path Relative path (may carry ?query or #hash).
	 * @return string
	 */
	public static function join( $base, $path ) {
		$suffix = '';
		$cut    = strcspn( $path, '?#' );
		if ( $cut < strlen( $path ) ) {
			$suffix = substr( $path, $cut );
			$path   = substr( $path, 0, $cut );
		}
		if ( ! preg_match( '#^([a-z][a-z0-9+.-]*://[^/]+|//[^/]+)?(/.*)$#i', $base, $m ) ) {
			return $base . $path . $suffix;
		}
		$origin   = $m[1];
		$segments = explode( '/', $m[2] . $path );
		$result   = array();
		foreach ( $segments as $i => $segment ) {
			if ( '..' === $segment ) {
				if ( count( $result ) > 1 ) {
					array_pop( $result );
				}
				if ( count( $segments ) - 1 === $i ) {
					$result[] = '';
				}
			} elseif ( '.' === $segment ) {
				if ( count( $segments ) - 1 === $i ) {
					$result[] = '';
				}
			} else {
				$result[] = $segment;
			}
		}
		return $origin . implode( '/', $result ) . $suffix;
	}

	/**
	 * Copy calc()/min()/max()/clamp() as it is (nested parentheses and strings
	 * included); only runs of whitespace become one space.
	 *
	 * @return string From the opening to the closing parenthesis.
	 */
	private function read_spaced_function() {
		$depth = 0;
		$out   = '';
		while ( $this->pos < $this->len ) {
			$char = $this->css[ $this->pos ];
			if ( '"' === $char || "'" === $char ) {
				$out .= $this->read_string();
				continue;
			}
			if ( '/' === $char && $this->peek( 1 ) === '*' ) {
				$end       = strpos( $this->css, '*/', $this->pos + 2 );
				$this->pos = false === $end ? $this->len : $end + 2;
				$out      .= ' ';
				continue;
			}
			if ( $this->is_space( $char ) ) {
				if ( '' !== $out && ' ' !== substr( $out, -1 ) ) {
					$out .= ' ';
				}
				++$this->pos;
				continue;
			}
			$out .= $char;
			++$this->pos;
			if ( '(' === $char ) {
				++$depth;
			} elseif ( ')' === $char ) {
				--$depth;
				if ( 0 === $depth ) {
					break;
				}
			}
		}
		// "calc( 1px + 2px )" keeps its inner spaces; only the edge spaces go.
		return (string) preg_replace( array( '/\(\s+/', '/\s+\)/' ), array( '(', ')' ), $out );
	}

	/**
	 * Server check: braces, parentheses and strings are balanced the same way
	 * in source and output, and nothing was lost.
	 *
	 * @param string $source Original CSS.
	 * @param string $output Minified CSS.
	 * @return string Empty when fine, otherwise the reason.
	 */
	public static function check( $source, $output ) {
		$a = self::structure( $source );
		$b = self::structure( $output );
		if ( 0 !== $a['open'] ) {
			return 'unbalanced braces in the source';
		}
		if ( $a['braces'] !== $b['braces'] || 0 !== $b['open'] ) {
			return 'unbalanced braces after minifying';
		}
		if ( $a['strings'] !== $b['strings'] ) {
			return 'string count changed';
		}
		if ( strlen( trim( $source ) ) > 0 && '' === trim( $output ) && $a['braces'] > 0 ) {
			return 'empty output';
		}
		return '';
	}

	/**
	 * Count braces and strings outside comments.
	 *
	 * @param string $css CSS.
	 * @return array{braces: int, strings: int, open: int}
	 */
	private static function structure( $css ) {
		$css     = (string) $css;
		$len     = strlen( $css );
		$braces  = 0;
		$strings = 0;
		$open    = 0;
		for ( $i = 0; $i < $len; $i++ ) {
			$char = $css[ $i ];
			if ( '/' === $char && $i + 1 < $len && '*' === $css[ $i + 1 ] ) {
				$end = strpos( $css, '*/', $i + 2 );
				$i   = false === $end ? $len : $end + 1;
				continue;
			}
			if ( '"' === $char || "'" === $char ) {
				++$strings;
				for ( ++$i; $i < $len; $i++ ) {
					if ( '\\' === $css[ $i ] ) {
						++$i;
						continue;
					}
					if ( $css[ $i ] === $char || "\n" === $css[ $i ] ) {
						break;
					}
				}
				continue;
			}
			if ( '{' === $char ) {
				++$braces;
				++$open;
			} elseif ( '}' === $char ) {
				--$open;
			}
		}
		return array(
			'braces'  => $braces,
			'strings' => $strings,
			'open'    => $open,
		);
	}
}
