<?php
/**
 * The chat window theme (owner, 20 November 2026: the new chat window).
 *
 * One object holds the window's whole look and behaviour, stored in the chat button theme's
 * reserved `window` key (Launcher_Theme::OPTION) so the button and the window are one theme.
 * Every value is checked against the schema below; a missing or wrong value takes the design's
 * default. Values that need WPBot Pro are kept when saved (so switching Pro off and on keeps the
 * owner's choice) but `effective()` replaces them with the free fallback while Pro is not active.
 *
 * Keys: version, design, layout, side, push, width, height, sideWidth, radius, mode,
 * colors{light,dark} (13 tokens each, see Window_Colors::TOKENS), material{type,blur,opacity,
 * inside,header}, motion{open,feel,messages,typing,send,breathe,scan,stagger}, menu{new,email,
 * download,sound,text,lang,person}, guide{bottom,page,tryask,composer,ghost,follow,nudge,tip},
 * suggestionsStyle, startMenuStyle, customFonts, holo{header,replies,send} (Lightcycle variants).
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Window;

use QuantumCloud\Chatbot\Widget\Launcher_Theme;

defined( 'ABSPATH' ) || exit;

/**
 * Window theme.
 */
final class Window_Theme {

	/**
	 * Schema version.
	 */
	const VERSION = 1;

	/**
	 * Allowed values per key (dotted keys are nested).
	 */
	const CHOICES = array(
		'layout'           => array( 'floating', 'embedded', 'full', 'compact', 'side' ),
		'side'             => array( 'right', 'left' ),
		'mode'             => array( 'light', 'dark', 'auto' ),
		'material.type'    => array( 'solid', 'glass', 'liquid' ),
		'material.inside'  => array( 'none', 'blobs', 'aurora' ),
		'material.header'  => array( 'solid', 'gradient', 'transparent' ),
		'motion.open'      => array( 'morph', 'fade', 'genie', 'liquid', 'unfold', 'spring', 'curtain' ),
		'motion.feel'      => array( 'subtle', 'expressive', 'off' ),
		'motion.messages'  => array( 'rise', 'pop', 'blur', 'type', 'none' ),
		'motion.typing'    => array( 'dots', 'wave', 'shimmer', 'orb', 'orbit' ),
		'motion.send'      => array( 'ripple', 'fly' ),
		'suggestionsStyle' => array( 'default', 'cards', 'bubbles', 'numbered', 'links' ),
		'startMenuStyle'   => array( 'default', 'tiles', 'pills', 'tabs', 'grid' ),
		'holo.header'      => array( 'line', 'panel', 'capsule', 'split' ),
		'holo.replies'     => array( 'cut', 'glass', 'outline', 'minimal' ),
		'holo.send'        => array( 'cut', 'circle', 'arrow', 'pill' ),
	);

	/**
	 * Number ranges: [min, max].
	 */
	const RANGES = array(
		'width'            => array( 300, 640 ),
		'height'           => array( 400, 860 ),
		'sideWidth'        => array( 340, 640 ),
		'radius'           => array( 0, 28 ),
		'material.blur'    => array( 0, 40 ),
		'material.opacity' => array( 40, 100 ),
	);

	/**
	 * Yes/no keys.
	 */
	const FLAGS = array(
		'push',
		'customFonts',
		'motion.breathe',
		'motion.scan',
		'motion.stagger',
		'menu.new',
		'menu.email',
		'menu.download',
		'menu.sound',
		'menu.text',
		'menu.lang',
		'menu.person',
		'guide.bottom',
		'guide.page',
		'guide.tryask',
		'guide.composer',
		'guide.ghost',
		'guide.follow',
		'guide.nudge',
		'guide.tip',
	);

	/**
	 * Values that need WPBot Pro, with the free value used instead while Pro is not active.
	 * A key mapped to a string means "every value except that one needs Pro".
	 */
	const PRO_VALUES = array(
		'layout'           => array(
			'compact' => 'floating',
			'side'    => 'floating',
		),
		'material.type'    => array( 'liquid' => 'glass' ),
		'material.inside'  => 'none',
		'motion.open'      => array(
			'genie'   => 'morph',
			'liquid'  => 'morph',
			'unfold'  => 'morph',
			'spring'  => 'morph',
			'curtain' => 'morph',
		),
		'motion.feel'      => array( 'expressive' => 'subtle' ),
		'motion.messages'  => array(
			'pop'  => 'rise',
			'blur' => 'rise',
			'type' => 'rise',
		),
		'motion.typing'    => 'dots',
		'motion.send'      => 'ripple',
		'motion.breathe'   => false,
		'motion.scan'      => false,
		'guide.ghost'      => false,
		'guide.nudge'      => false,
		'suggestionsStyle' => 'default',
		'startMenuStyle'   => 'default',
	);

	/**
	 * Cached theme for this request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * The saved window theme, checked (Pro values kept).
	 *
	 * @return array
	 */
	public static function get() {
		if ( null === self::$cache ) {
			$stored = Launcher_Theme::get()['window'];
			$theme  = empty( $stored ) ? self::initial() : self::sanitize( $stored );
			/**
			 * The chat window theme (see Window_Theme for the keys).
			 *
			 * @param array $theme Theme.
			 */
			$filtered    = apply_filters( 'qcld_wpbot_chat/window_theme', $theme );
			self::$cache = is_array( $filtered ) ? self::sanitize( $filtered ) : $theme;
		}
		return self::$cache;
	}

	/**
	 * The theme the front end and the preview use: Pro values replaced while Pro is not active,
	 * and a design that is not available replaced with Classic (keeping layout, size and options).
	 *
	 * @return array
	 */
	public static function effective() {
		return self::effective_for( self::get() );
	}

	/**
	 * `effective()` for any theme.
	 *
	 * @param array $theme Checked theme.
	 * @return array
	 */
	public static function effective_for( array $theme ) {
		if ( ! Window_Designs::available( $theme['design'] ) ) {
			$fallback          = self::defaults( Window_Designs::FALLBACK );
			$theme['design']   = Window_Designs::FALLBACK;
			$theme['colors']   = $fallback['colors'];
			$theme['material'] = $fallback['material'];
		}
		if ( ! self::pro() ) {
			foreach ( self::PRO_VALUES as $key => $rule ) {
				$value = self::read( $theme, $key );
				if ( is_array( $rule ) ) {
					if ( is_string( $value ) && isset( $rule[ $value ] ) ) {
						$theme = self::write( $theme, $key, $rule[ $value ] );
					}
				} elseif ( $value !== $rule ) {
					$theme = self::write( $theme, $key, $rule );
				}
			}
		}
		return $theme;
	}

	/**
	 * Whether the Pro values are available (WPBot Pro says so through the filter).
	 *
	 * @return bool
	 */
	public static function pro() {
		/**
		 * Whether the chat window's Pro options (layouts, materials, motion, guidance extras) can be used.
		 *
		 * @param bool $on Default false.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/window_pro_options', false );
	}

	/**
	 * Whether one value needs Pro.
	 *
	 * @param string $key   Dotted key.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	public static function needs_pro( $key, $value ) {
		if ( ! isset( self::PRO_VALUES[ $key ] ) ) {
			return false;
		}
		$rule = self::PRO_VALUES[ $key ];
		return is_array( $rule ) ? ( is_string( $value ) && isset( $rule[ $value ] ) ) : $value !== $rule;
	}

	/**
	 * Save a theme (any subset of the keys; the rest keeps its saved value).
	 *
	 * @param array $patch Partial theme.
	 * @return array The saved window theme.
	 */
	public static function save( array $patch ) {
		$merged = self::sanitize( array_replace_recursive( self::get(), $patch ) );
		Launcher_Theme::save( array( 'window' => $merged ) );
		self::reset();
		/**
		 * A chat window theme was saved.
		 *
		 * @param array $merged Theme.
		 */
		do_action( 'qcld_wpbot_chat/window_theme_saved', $merged );
		return $merged;
	}

	/**
	 * A design's starting values: the owner's saved defaults, with the site's brand colour in
	 * the tokens that follow it (Classic builds its whole palette from it).
	 *
	 * @param string $design Design id.
	 * @param string $brand  Brand colour; empty = the chat button's colour.
	 * @return array
	 */
	public static function defaults( $design, $brand = '' ) {
		$design = in_array( $design, Window_Designs::ids(), true ) ? $design : Window_Designs::FALLBACK;
		$d      = Window_Defaults::for_design( $design );
		$brand  = Window_Colors::hex( '' !== $brand ? $brand : Launcher_Theme::get()['color'] );
		$meta   = Window_Designs::get( $design );
		if ( '' !== $brand && empty( $meta['own'] ) ) {
			if ( 'all' === $meta['brand'] ) {
				$d['colors']['light'] = Window_Colors::classic( $brand, false );
				$d['colors']['dark']  = Window_Colors::classic( $brand, true );
			} elseif ( is_array( $meta['brand'] ) && $meta['brand'] ) {
				$d['colors']['light'] = Window_Colors::brand( $d['colors']['light'], $brand, $meta['brand'], false );
				$d['colors']['dark']  = Window_Colors::brand( $d['colors']['dark'], $brand, $meta['brand'], true );
			}
		}
		$d['version'] = self::VERSION;
		return $d;
	}

	/**
	 * The first window theme of a site: built from the old settings when WPBot ran here before,
	 * otherwise Classic's defaults.
	 *
	 * @return array
	 */
	public static function initial() {
		return self::sanitize( Window_Migration::needed() ? Window_Migration::build() : self::defaults( Window_Designs::FALLBACK ) );
	}

	/**
	 * Store the first window theme once (init), so it is not rebuilt on every request and later
	 * changes to the old settings do not change it.
	 *
	 * @return void
	 */
	public static function ensure_stored() {
		$stored = Launcher_Theme::get()['window'];
		if ( ! empty( $stored ) && ! empty( $stored['version'] ) ) {
			return;
		}
		$migrating = Window_Migration::needed();
		Launcher_Theme::save( array( 'window' => self::initial() ) );
		if ( $migrating ) {
			update_option( Window_Migration::FLAG, gmdate( 'c' ), false );
		}
		self::reset();
	}

	/**
	 * Check every value; unknown keys are dropped, missing or wrong ones take the design's default.
	 *
	 * @param array $theme Theme.
	 * @return array
	 */
	public static function sanitize( array $theme ) {
		$design = isset( $theme['design'] ) ? strtolower( trim( (string) $theme['design'] ) ) : '';
		$design = in_array( $design, Window_Designs::ids(), true ) ? $design : Window_Designs::FALLBACK;
		$base   = Window_Defaults::for_design( $design );
		$out    = $base;

		$out['version'] = self::VERSION;
		$out['design']  = $design;
		foreach ( self::CHOICES as $key => $choices ) {
			$value = self::read( $theme, $key );
			$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
			$out   = self::write( $out, $key, in_array( $value, $choices, true ) ? $value : self::read( $base, $key ) );
		}
		foreach ( self::RANGES as $key => $range ) {
			$value = self::read( $theme, $key );
			$value = is_numeric( $value ) ? (int) round( (float) $value ) : (int) self::read( $base, $key );
			$out   = self::write( $out, $key, min( $range[1], max( $range[0], $value ) ) );
		}
		foreach ( self::FLAGS as $key ) {
			$value = self::read( $theme, $key );
			$out   = self::write( $out, $key, null === $value ? (bool) self::read( $base, $key ) : self::flag( $value ) );
		}
		foreach ( array( 'light', 'dark' ) as $mode ) {
			$given                  = isset( $theme['colors'][ $mode ] ) && is_array( $theme['colors'][ $mode ] ) ? $theme['colors'][ $mode ] : array();
			$out['colors'][ $mode ] = Window_Colors::ordered( $given, $base['colors'][ $mode ] );
		}
		return $out;
	}

	/**
	 * Forget the cached theme (tests, after a save).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$cache = null;
		Launcher_Theme::reset();
	}

	/**
	 * A nested value by dotted key (null when missing).
	 *
	 * @param array  $from Source.
	 * @param string $key  Dotted key.
	 * @return mixed
	 */
	private static function read( array $from, $key ) {
		foreach ( explode( '.', $key ) as $part ) {
			if ( ! is_array( $from ) || ! array_key_exists( $part, $from ) ) {
				return null;
			}
			$from = $from[ $part ];
		}
		return $from;
	}

	/**
	 * Set a nested value by dotted key.
	 *
	 * @param array  $to    Target.
	 * @param string $key   Dotted key.
	 * @param mixed  $value Value.
	 * @return array
	 */
	private static function write( array $to, $key, $value ) {
		$parts = explode( '.', $key );
		if ( 1 === count( $parts ) ) {
			$to[ $key ] = $value;
			return $to;
		}
		if ( ! isset( $to[ $parts[0] ] ) || ! is_array( $to[ $parts[0] ] ) ) {
			$to[ $parts[0] ] = array();
		}
		$to[ $parts[0] ][ $parts[1] ] = $value;
		return $to;
	}

	/**
	 * A yes/no value from a form or JSON.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function flag( $value ) {
		return true === $value || 1 === $value || in_array( strtolower( (string) $value ), array( '1', 'on', 'yes', 'true' ), true );
	}
}
