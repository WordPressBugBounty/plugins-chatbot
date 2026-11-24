<?php
/**
 * Colour helpers for the chat window: contrast checks and palettes built from one brand colour.
 *
 * The window has 13 colour tokens for light and 13 for dark. A design's own palette is kept, and
 * only the tokens that follow the brand colour are replaced, with text colours chosen so they stay
 * readable (WCAG contrast). Classic builds its whole palette from the brand colour.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Window;

defined( 'ABSPATH' ) || exit;

/**
 * Colour helpers.
 */
final class Window_Colors {

	/**
	 * The 13 tokens, in display order.
	 */
	const TOKENS = array( 'accent', 'accentFg', 'head', 'headFg', 'bg', 'fg', 'bot', 'botFg', 'me', 'meFg', 'chip', 'composer', 'border' );

	/**
	 * Text token for each background token.
	 */
	const TEXT_FOR = array(
		'accent' => 'accentFg',
		'head'   => 'headFg',
		'bg'     => 'fg',
		'bot'    => 'botFg',
		'me'     => 'meFg',
	);

	/**
	 * A colour as lowercase #rrggbb, or '' when it is not a hex colour.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function hex( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( preg_match( '/^#([0-9a-f]{3})$/', $value, $m ) ) {
			$value = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
		}
		return preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : '';
	}

	/**
	 * Mix two colours: $t = 0 gives $a, 1 gives $b.
	 *
	 * @param string $a Colour.
	 * @param string $b Colour.
	 * @param float  $t Amount of $b.
	 * @return string
	 */
	public static function mix( $a, $b, $t ) {
		$x   = self::rgb( $a );
		$y   = self::rgb( $b );
		$out = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$out .= sprintf( '%02x', (int) round( max( 0, min( 255, $x[ $i ] + ( $y[ $i ] - $x[ $i ] ) * $t ) ) ) );
		}
		return $out;
	}

	/**
	 * Contrast ratio between two colours (1 to 21).
	 *
	 * @param string $a Colour.
	 * @param string $b Colour.
	 * @return float
	 */
	public static function contrast( $a, $b ) {
		$x = self::luminance( $a );
		$y = self::luminance( $b );
		return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
	}

	/**
	 * The more readable of white and a near-black on a background.
	 *
	 * @param string $bg Background.
	 * @return string
	 */
	public static function best_text( $bg ) {
		$dark = self::mix( '#111318', $bg, 0.08 );
		return self::contrast( '#ffffff', $bg ) >= self::contrast( $dark, $bg ) ? '#ffffff' : $dark;
	}

	/**
	 * Move a colour towards black or white until it reaches a contrast ratio on a background.
	 *
	 * @param string $fg  Colour.
	 * @param string $bg  Background.
	 * @param float  $min Ratio.
	 * @return string
	 */
	public static function ensure( $fg, $bg, $min ) {
		// Move towards whichever end (black or white) can give more contrast on this background.
		$target = self::contrast( '#ffffff', $bg ) >= self::contrast( '#000000', $bg ) ? '#ffffff' : '#000000';
		$steps  = 0;
		while ( $steps < 20 && self::contrast( $fg, $bg ) < $min ) {
			$fg = self::mix( $fg, $target, 0.12 );
			++$steps;
		}
		return self::contrast( $fg, $bg ) < $min ? $target : $fg;
	}

	/**
	 * Classic's palette from one brand colour (light or dark).
	 *
	 * @param string $seed Brand colour.
	 * @param bool   $dark Dark palette.
	 * @return array
	 */
	public static function classic( $seed, $dark ) {
		$seed = self::hex( $seed );
		$seed = '' !== $seed ? $seed : '#3d8bd8';
		if ( ! $dark ) {
			$c = array(
				'accent'   => $seed,
				'head'     => $seed,
				'bg'       => self::mix( '#f4f5f7', $seed, 0.035 ),
				'fg'       => self::mix( '#121419', $seed, 0.1 ),
				'bot'      => '#ffffff',
				'me'       => $seed,
				'composer' => '#ffffff',
				'border'   => self::mix( '#e4e6ea', $seed, 0.08 ),
			);
		} else {
			$base   = '#0f1014';
			$accent = $seed;
			$steps  = 0;
			while ( $steps < 12 && self::contrast( $accent, $base ) < 4.2 ) {
				$accent = self::mix( $accent, '#ffffff', 0.1 );
				++$steps;
			}
			$c = array(
				'accent'   => $accent,
				'head'     => self::mix( $base, $seed, 0.42 ),
				'bg'       => self::mix( '#121317', $seed, 0.05 ),
				'fg'       => self::mix( '#f3f4f7', $seed, 0.05 ),
				'bot'      => self::mix( '#1f2127', $seed, 0.07 ),
				'me'       => $accent,
				'composer' => self::mix( '#1a1c21', $seed, 0.05 ),
				'border'   => self::mix( $base, $seed, 0.24 ),
			);
		}//end if
		$c['accentFg'] = self::best_text( $c['accent'] );
		$c['headFg']   = self::best_text( $c['head'] );
		$c['botFg']    = $c['fg'];
		$c['meFg']     = self::best_text( $c['me'] );
		$c['chip']     = self::ensure( $c['accent'], $c['composer'], 4.5 );
		return self::ordered( $c );
	}

	/**
	 * Put a brand colour into some tokens of a palette, with readable text on each.
	 *
	 * @param array  $palette Full palette.
	 * @param string $seed    Brand colour.
	 * @param array  $tokens  Tokens that follow the brand colour.
	 * @param bool   $dark    Dark palette (the colour is lightened until it reads on the background).
	 * @return array
	 */
	public static function brand( array $palette, $seed, array $tokens, $dark ) {
		$seed = self::hex( $seed );
		if ( '' === $seed ) {
			return $palette;
		}
		foreach ( $tokens as $token ) {
			$value = $seed;
			if ( $dark && isset( $palette['bg'] ) ) {
				$steps = 0;
				while ( $steps < 12 && self::contrast( $value, $palette['bg'] ) < 4.2 ) {
					$value = self::mix( $value, '#ffffff', 0.1 );
					++$steps;
				}
			}
			$palette[ $token ] = $value;
			if ( isset( self::TEXT_FOR[ $token ] ) ) {
				$palette[ self::TEXT_FOR[ $token ] ] = self::best_text( $value );
			}
		}
		return $palette;
	}

	/**
	 * A palette with every token, in order; missing or invalid tokens come from $fallback.
	 *
	 * @param array $palette  Palette.
	 * @param array $fallback Palette to fill gaps from.
	 * @return array
	 */
	public static function ordered( array $palette, array $fallback = array() ) {
		$out = array();
		foreach ( self::TOKENS as $token ) {
			$value         = isset( $palette[ $token ] ) ? self::hex( $palette[ $token ] ) : '';
			$out[ $token ] = '' !== $value ? $value : ( isset( $fallback[ $token ] ) ? self::hex( $fallback[ $token ] ) : '#000000' );
		}
		return $out;
	}

	/**
	 * Red, green, blue (0-255).
	 *
	 * @param string $hex Colour.
	 * @return int[]
	 */
	private static function rgb( $hex ) {
		$hex = self::hex( $hex );
		$hex = '' !== $hex ? $hex : '#000000';
		return array( hexdec( substr( $hex, 1, 2 ) ), hexdec( substr( $hex, 3, 2 ) ), hexdec( substr( $hex, 5, 2 ) ) );
	}

	/**
	 * Relative luminance.
	 *
	 * @param string $hex Colour.
	 * @return float
	 */
	private static function luminance( $hex ) {
		$sum     = 0.0;
		$weights = array( 0.2126, 0.7152, 0.0722 );
		foreach ( self::rgb( $hex ) as $i => $v ) {
			$v   /= 255;
			$v    = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
			$sum += $v * $weights[ $i ];
		}
		return $sum;
	}
}
