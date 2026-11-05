<?php
/**
 * Safe JavaScript minifier (original code).
 *
 * A small streaming tokenizer that knows strings, template literals
 * (including nested ${}), regular-expression literals versus division, and
 * line and block comments. It removes comments (keeping /*! and licence
 * comments) and collapses whitespace. It never renames, reorders or rewrites
 * code: every token is copied exactly as written, so eval() keeps working.
 *
 * A line break is kept wherever removing it could change the meaning through
 * automatic semicolon insertion: it is dropped only when the token before it
 * cannot end a statement (an operator, "(", "," …) or the token after it
 * cannot start one (")", ".", "?", "=" …), and never after return, break,
 * continue, throw, yield or async, or before ++ and --.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Streaming JS minifier.
 */
class Js_Minifier {

	/**
	 * Punctuators, longest first.
	 */
	const PUNCTUATORS = array( '>>>=', '...', '===', '!==', '**=', '<<=', '>>=', '>>>', '&&=', '||=', '??=', '=>', '==', '!=', '<=', '>=', '&&', '||', '??', '?.', '++', '--', '+=', '-=', '*=', '/=', '%=', '&=', '|=', '^=', '**', '<<', '>>' );

	/**
	 * After these keywords a "/" starts a regular expression.
	 */
	const EXPRESSION_KEYWORDS = array( 'return', 'typeof', 'instanceof', 'in', 'of', 'new', 'delete', 'void', 'throw', 'case', 'do', 'else', 'yield', 'await', 'extends' );

	/**
	 * A line break after these is always kept (restricted productions).
	 */
	const RESTRICTED = array( 'return', 'break', 'continue', 'throw', 'yield', 'async', 'let' );

	/**
	 * Tokens after which a statement cannot end: a line break after them can go.
	 */
	const CANNOT_END = array( '(', '[', '{', ',', ';', ':', '?', '=', '==', '===', '!=', '!==', '<', '>', '<=', '>=', '+', '-', '*', '/', '%', '**', '&', '|', '^', '!', '~', '&&', '||', '??', '<<', '>>', '>>>', '+=', '-=', '*=', '/=', '%=', '**=', '&=', '|=', '^=', '<<=', '>>=', '>>>=', '&&=', '||=', '??=', '=>', '.', '?.', '...' );

	/**
	 * Tokens that cannot start a statement: a line break before them can go.
	 */
	const CANNOT_START = array( ')', ']', '}', ',', ';', ':', '?', '.', '?.', '=', '==', '===', '!=', '!==', '<', '>', '<=', '>=', '*', '%', '**', '&', '|', '^', '&&', '||', '??', '<<', '>>', '>>>', '+=', '-=', '*=', '/=', '%=', '**=', '&=', '|=', '^=', '<<=', '>>=', '>>>=', '&&=', '||=', '??=', '=>' );

	/**
	 * Characters of identifiers, keywords and numbers (ASCII part).
	 */
	const WORD = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_$\\';

	/**
	 * Source.
	 *
	 * @var string
	 */
	private $js = '';

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
	 * Previous significant token: [type, text] or null.
	 *
	 * @var array{0: string, 1: string}|null
	 */
	private $prev = null;

	/**
	 * Whether a line break / whitespace preceded the token just read.
	 *
	 * @var bool
	 */
	private $newline = false;

	/**
	 * Whether whitespace preceded the token just read.
	 *
	 * @var bool
	 */
	private $space = false;

	/**
	 * Open-bracket depth (for the balance check).
	 *
	 * @var int
	 */
	private $depth = 0;

	/**
	 * Token statistics of the last run: str, tpl, re, brackets.
	 *
	 * @var array<string,mixed>
	 */
	private $stats = array();

	/**
	 * Minify a script.
	 *
	 * @param string $js Source.
	 * @return string|null Null when the source could not be read safely (keep the original).
	 */
	public function minify( $js ) {
		$this->reset( (string) $js );
		$out = '';

		if ( 0 === strpos( $this->js, '#!' ) ) {
			$end       = strcspn( $this->js, "\n" );
			$out       = substr( $this->js, 0, $end ) . "\n";
			$this->pos = $end;
		}

		try {
			$prev = null;
			while ( null !== ( $token = $this->next() ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Streaming loop.
				if ( 'keep' === $token[0] ) {
					$out .= ( $this->newline && '' !== $out ? "\n" : ( $this->space && '' !== $out ? ' ' : '' ) ) . $token[1];
					continue;
				}
				if ( null === $prev ) {
					$out .= '' !== $out && $this->newline && "\n" !== substr( $out, -1 ) ? "\n" : '';
				} else {
					if ( $this->newline && ! $this->can_join( $prev, $token ) ) {
						$out .= "\n";
					} elseif ( ( $this->newline || $this->space ) && $this->needs_space( $prev, $token ) ) {
						$out .= ' ';
					}
				}
				$out .= $token[1];
				$prev = $token;
			}
		} catch ( \UnexpectedValueException $e ) {
			return null;
		}
		return $out;
	}

	/**
	 * Count strings, templates, regexes and brackets of a script (for the
	 * server check). Null when it cannot be tokenized.
	 *
	 * @param string $js Script.
	 * @return array<string,mixed>|null
	 */
	public function profile( $js ) {
		$this->reset( (string) $js );
		try {
			while ( null !== $this->next() ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition, Squiz.PHP.EmptyStatement.DetectedWhile -- Counting only.
				// Counting happens in next().
			}
		} catch ( \UnexpectedValueException $e ) {
			return null;
		}
		$this->stats['balanced'] = $this->stats['balanced'] && 0 === $this->depth;
		return $this->stats;
	}

	/**
	 * Server check before writing: the output tokenizes, has the same number
	 * of string, template and regex tokens, balanced brackets, and is smaller.
	 *
	 * @param string      $source Original.
	 * @param string|null $output Minified.
	 * @return string Empty when fine, otherwise the reason.
	 */
	public function check( $source, $output ) {
		if ( null === $output ) {
			return 'the script could not be read safely';
		}
		$a = $this->profile( $source );
		$b = $this->profile( $output );
		if ( null === $a || null === $b ) {
			return 'the script could not be read safely';
		}
		foreach ( array( 'str' => 'strings', 'tpl' => 'template literals', 're' => 'regular expressions' ) as $key => $label ) {
			if ( $a[ $key ] !== $b[ $key ] ) {
				return 'the number of ' . $label . ' changed';
			}
		}
		if ( $a['brackets'] !== $b['brackets'] || ! $b['balanced'] ) {
			return 'brackets do not balance';
		}
		if ( strlen( $output ) >= strlen( $source ) ) {
			return 'no smaller than the original';
		}
		return '';
	}

	/**
	 * Whether a script already looks minified (very long lines), so it is
	 * passed through unchanged.
	 *
	 * @param string $js   Script.
	 * @param string $name File name.
	 * @return bool
	 */
	public static function looks_minified( $js, $name = '' ) {
		if ( '' !== $name && preg_match( '/[.-]min\.js$/i', $name ) ) {
			return true;
		}
		$length = strlen( (string) $js );
		if ( $length < 2000 ) {
			return false;
		}
		$lines = substr_count( (string) $js, "\n" ) + 1;
		return ( $length / $lines ) > 500;
	}

	/**
	 * Start reading a new script.
	 *
	 * @param string $js Script.
	 * @return void
	 */
	private function reset( $js ) {
		$this->js      = $js;
		$this->len     = strlen( $js );
		$this->pos     = 0;
		$this->prev    = null;
		$this->newline = false;
		$this->space   = false;
		$this->stats   = array(
			'str'      => 0,
			'tpl'      => 0,
			're'       => 0,
			'brackets' => 0,
			'balanced' => true,
		);
		$this->depth   = 0;
		if ( 0 === strpos( $this->js, "\xEF\xBB\xBF" ) ) {
			$this->pos = 3;
		}
	}

	/**
	 * Read the next token, skipping whitespace and comments. Sets
	 * $newline/$space for what was skipped.
	 *
	 * @return array{0: string, 1: string}|null [type, text]; null at the end.
	 * @throws \UnexpectedValueException On an unterminated string, template or comment.
	 */
	private function next() {
		$this->newline = false;
		$this->space   = false;

		while ( $this->pos < $this->len ) {
			$char = $this->js[ $this->pos ];

			// Whitespace.
			$run = strspn( $this->js, " \t\x0B\x0C", $this->pos );
			if ( $run > 0 ) {
				$this->space = true;
				$this->pos  += $run;
				continue;
			}
			if ( "\n" === $char || "\r" === $char ) {
				$this->newline = true;
				++$this->pos;
				continue;
			}
			if ( "\xC2" === $char && "\xA0" === $this->at( 1 ) ) {
				$this->space = true;
				$this->pos  += 2;
				continue;
			}
			if ( "\xE2" === $char && "\x80" === $this->at( 1 ) && ( "\xA8" === $this->at( 2 ) || "\xA9" === $this->at( 2 ) ) ) {
				$this->newline = true;
				$this->pos    += 3;
				continue;
			}
			if ( "\xEF" === $char && "\xBB" === $this->at( 1 ) && "\xBF" === $this->at( 2 ) ) {
				$this->space = true;
				$this->pos  += 3;
				continue;
			}

			// Comments.
			if ( '/' === $char && '/' === $this->at( 1 ) ) {
				$this->pos    += strcspn( $this->js, "\n\r", $this->pos );
				$this->newline = true;
				continue;
			}
			if ( '/' === $char && '*' === $this->at( 1 ) ) {
				$end = strpos( $this->js, '*/', $this->pos + 2 );
				if ( false === $end ) {
					throw new \UnexpectedValueException( 'unterminated comment' );
				}
				$text      = substr( $this->js, $this->pos, $end + 2 - $this->pos );
				$this->pos = $end + 2;
				if ( '!' === $text[2] || false !== stripos( $text, '@license' ) || false !== stripos( $text, '@preserve' ) ) {
					return array( 'keep', $text );
				}
				if ( false !== strpos( $text, "\n" ) || false !== strpos( $text, "\r" ) ) {
					$this->newline = true;
				} else {
					$this->space = true;
				}
				continue;
			}

			$token      = $this->read_token( $char );
			$this->prev = $token;
			return $token;
		}
		return null;
	}

	/**
	 * Byte at an offset from the read position.
	 *
	 * @param int $offset Offset.
	 * @return string
	 */
	private function at( $offset ) {
		$i = $this->pos + $offset;
		return $i < $this->len ? $this->js[ $i ] : '';
	}

	/**
	 * Read one significant token starting at the read position.
	 *
	 * @param string $char First byte.
	 * @return array{0: string, 1: string}
	 * @throws \UnexpectedValueException On unterminated literals.
	 */
	private function read_token( $char ) {
		$start = $this->pos;

		if ( '"' === $char || "'" === $char ) {
			$this->skip_string( $char );
			++$this->stats['str'];
			return array( 'str', substr( $this->js, $start, $this->pos - $start ) );
		}
		if ( '`' === $char ) {
			$this->skip_template();
			++$this->stats['tpl'];
			return array( 'tpl', substr( $this->js, $start, $this->pos - $start ) );
		}
		if ( ctype_digit( $char ) || ( '.' === $char && ctype_digit( $this->at( 1 ) ) ) ) {
			$this->skip_number();
			return array( 'num', substr( $this->js, $start, $this->pos - $start ) );
		}
		if ( $this->is_word_byte( $char ) || '#' === $char ) {
			++$this->pos;
			$this->skip_word();
			return array( 'id', substr( $this->js, $start, $this->pos - $start ) );
		}
		if ( '/' === $char && $this->regex_allowed() && $this->skip_regex() ) {
			++$this->stats['re'];
			return array( 're', substr( $this->js, $start, $this->pos - $start ) );
		}

		foreach ( self::PUNCTUATORS as $punctuator ) {
			if ( 0 === substr_compare( $this->js, $punctuator, $this->pos, strlen( $punctuator ) ) ) {
				// "a?.5:b" is a conditional, not optional chaining.
				if ( '?.' === $punctuator && ctype_digit( $this->at( 2 ) ) ) {
					break;
				}
				$this->pos += strlen( $punctuator );
				return array( 'punc', $punctuator );
			}
		}
		++$this->pos;
		if ( false !== strpos( '([{', $char ) ) {
			++$this->stats['brackets'];
			++$this->depth;
		} elseif ( false !== strpos( ')]}', $char ) ) {
			--$this->depth;
			if ( $this->depth < 0 ) {
				$this->stats['balanced'] = false;
			}
		}
		return array( 'punc', $char );
	}

	/**
	 * Whether a byte belongs to an identifier (ASCII word bytes and any
	 * non-ASCII byte, which is copied as it is).
	 *
	 * @param string $char Byte.
	 * @return bool
	 */
	private function is_word_byte( $char ) {
		return '' !== $char && ( false !== strpos( self::WORD, $char ) || ord( $char ) >= 0x80 );
	}

	/**
	 * Skip the rest of an identifier.
	 *
	 * @return void
	 */
	private function skip_word() {
		while ( $this->pos < $this->len ) {
			$run = strspn( $this->js, self::WORD, $this->pos );
			if ( $run > 0 ) {
				$this->pos += $run;
				continue;
			}
			$byte = $this->js[ $this->pos ];
			// Non-ASCII identifier bytes, but not the line/paragraph separators or NBSP.
			if ( ord( $byte ) >= 0x80 && ! ( "\xE2" === $byte && "\x80" === $this->at( 1 ) && ( "\xA8" === $this->at( 2 ) || "\xA9" === $this->at( 2 ) ) ) && ! ( "\xC2" === $byte && "\xA0" === $this->at( 1 ) ) ) {
				++$this->pos;
				continue;
			}
			break;
		}
	}

	/**
	 * Skip a number literal (decimal, hex, octal, binary, BigInt, separators, exponents).
	 *
	 * @return void
	 */
	private function skip_number() {
		$start = $this->pos;
		while ( $this->pos < $this->len ) {
			$char = $this->js[ $this->pos ];
			if ( ctype_alnum( $char ) || '_' === $char || '.' === $char ) {
				++$this->pos;
				continue;
			}
			// Exponent sign: 1e+5 (decimal literals only).
			if ( ( '+' === $char || '-' === $char ) && $this->pos > $start && false !== strpos( 'eE', $this->js[ $this->pos - 1 ] ) && ! preg_match( '/^0[xXbBoO]/', substr( $this->js, $start, 2 ) ) ) {
				++$this->pos;
				continue;
			}
			break;
		}
	}

	/**
	 * Skip a quoted string (escapes and line continuations included).
	 *
	 * @param string $quote Quote character.
	 * @return void
	 * @throws \UnexpectedValueException On an unterminated string.
	 */
	private function skip_string( $quote ) {
		++$this->pos;
		while ( $this->pos < $this->len ) {
			$this->pos += strcspn( $this->js, $quote . "\\\n\r", $this->pos );
			if ( $this->pos >= $this->len ) {
				break;
			}
			$char = $this->js[ $this->pos ];
			if ( '\\' === $char ) {
				$this->pos += 2;
				// \r\n line continuation.
				if ( "\r" === $this->js[ $this->pos - 1 ] && "\n" === $this->at( 0 ) ) {
					++$this->pos;
				}
				continue;
			}
			if ( $char === $quote ) {
				++$this->pos;
				return;
			}
			break; // A raw line break ends a string: invalid source.
		}
		throw new \UnexpectedValueException( 'unterminated string' );
	}

	/**
	 * Skip a template literal, including nested ${ … } expressions.
	 *
	 * @return void
	 * @throws \UnexpectedValueException On an unterminated template.
	 */
	private function skip_template() {
		++$this->pos;
		while ( $this->pos < $this->len ) {
			$this->pos += strcspn( $this->js, "`\\$", $this->pos );
			if ( $this->pos >= $this->len ) {
				break;
			}
			$char = $this->js[ $this->pos ];
			if ( '\\' === $char ) {
				$this->pos += 2;
				continue;
			}
			if ( '`' === $char ) {
				++$this->pos;
				return;
			}
			if ( '{' === $this->at( 1 ) ) {
				$this->pos += 2;
				$this->skip_expression();
				continue;
			}
			++$this->pos;
		}
		throw new \UnexpectedValueException( 'unterminated template literal' );
	}

	/**
	 * Skip a ${ … } expression up to its closing brace: strings, nested
	 * templates, comments and braces are followed.
	 *
	 * @return void
	 * @throws \UnexpectedValueException When the expression never closes.
	 */
	private function skip_expression() {
		$depth = 1;
		while ( $this->pos < $this->len ) {
			$this->pos += strcspn( $this->js, "{}'\"`/", $this->pos );
			if ( $this->pos >= $this->len ) {
				break;
			}
			$char = $this->js[ $this->pos ];
			if ( '{' === $char ) {
				++$depth;
				++$this->pos;
			} elseif ( '}' === $char ) {
				++$this->pos;
				if ( 0 === --$depth ) {
					return;
				}
			} elseif ( '"' === $char || "'" === $char ) {
				$this->skip_string( $char );
			} elseif ( '`' === $char ) {
				$this->skip_template();
			} elseif ( $this->regex_in_expression() && $this->skip_regex() ) {
				continue;
			} elseif ( '/' === $this->at( 1 ) ) {
				$this->pos += strcspn( $this->js, "\n\r", $this->pos );
			} elseif ( '*' === $this->at( 1 ) ) {
				$end = strpos( $this->js, '*/', $this->pos + 2 );
				if ( false === $end ) {
					break;
				}
				$this->pos = $end + 2;
			} else {
				++$this->pos;
			}
		}
		throw new \UnexpectedValueException( 'unterminated template expression' );
	}

	/**
	 * Inside ${ … }: whether the "/" at the read position starts a regular
	 * expression, judged by the character before it.
	 *
	 * @return bool
	 */
	private function regex_in_expression() {
		$next = $this->at( 1 );
		if ( '/' === $next || '*' === $next ) {
			return false;
		}
		$i = $this->pos - 1;
		while ( $i >= 0 && false !== strpos( " \t\n\r", $this->js[ $i ] ) ) {
			--$i;
		}
		return $i < 0 || false !== strpos( '(,=:[!&|?{};+-*%<>~^', $this->js[ $i ] );
	}

	/**
	 * Whether a "/" here starts a regular expression (from the previous token).
	 *
	 * @return bool
	 */
	private function regex_allowed() {
		if ( null === $this->prev ) {
			return true;
		}
		list( $type, $text ) = $this->prev;
		if ( 'punc' === $type ) {
			// After ")" or "]" it is division; after "}" it may be either, so a regex is tried (it falls back to division at a line break).
			return ! in_array( $text, array( ')', ']', '++', '--' ), true );
		}
		if ( 'id' === $type ) {
			return in_array( $text, self::EXPRESSION_KEYWORDS, true );
		}
		return false;
	}

	/**
	 * Skip a regular-expression literal and its flags. Returns false (and
	 * leaves the position) when it does not close on this line.
	 *
	 * @return bool
	 */
	private function skip_regex() {
		$i        = $this->pos + 1;
		$in_class = false;
		while ( $i < $this->len ) {
			$char = $this->js[ $i ];
			if ( "\n" === $char || "\r" === $char ) {
				return false;
			}
			if ( '\\' === $char ) {
				$i += 2;
				continue;
			}
			if ( '[' === $char ) {
				$in_class = true;
			} elseif ( ']' === $char ) {
				$in_class = false;
			} elseif ( '/' === $char && ! $in_class ) {
				break;
			}
			++$i;
		}
		if ( $i >= $this->len || '*' === $this->at( 1 ) ) {
			return false;
		}
		++$i;
		$i        += strspn( $this->js, self::WORD, $i );
		$this->pos = $i;
		return true;
	}

	/**
	 * Whether a line break between two tokens can be dropped.
	 *
	 * @param array{0: string, 1: string} $prev  Token before.
	 * @param array{0: string, 1: string} $token Token after.
	 * @return bool
	 */
	private function can_join( array $prev, array $token ) {
		if ( 'id' === $prev[0] && in_array( $prev[1], self::RESTRICTED, true ) ) {
			return false;
		}
		if ( 'punc' === $token[0] && ( '++' === $token[1] || '--' === $token[1] ) ) {
			return false;
		}
		if ( 'punc' === $prev[0] && in_array( $prev[1], self::CANNOT_END, true ) ) {
			return true;
		}
		return 'punc' === $token[0] && in_array( $token[1], self::CANNOT_START, true );
	}

	/**
	 * Whether two tokens need a space between them to stay two tokens.
	 *
	 * @param array{0: string, 1: string} $prev  Token before.
	 * @param array{0: string, 1: string} $token Token after.
	 * @return bool
	 */
	private function needs_space( array $prev, array $token ) {
		$a = substr( $prev[1], -1 );
		$b = $token[1][0];
		$a_word = 're' === $prev[0] || $this->is_word_byte( $a );
		if ( $a_word && ( $this->is_word_byte( $b ) || '#' === $b ) ) {
			return true;
		}
		if ( ( '+' === $a && '+' === $b ) || ( '-' === $a && '-' === $b ) || ( '/' === $a && ( '/' === $b || '*' === $b ) ) ) {
			return true;
		}
		if ( 'num' === $prev[0] && '.' === $b ) {
			return true;
		}
		return ( '<' === $a && '!' === $b ) || ( '-' === $a && '>' === $b );
	}
}
