<?php
/**
 * The chat button and floating box theme (owner, 17 November 2026: new chat button design).
 *
 * One theme object holds the whole look, so a theme generator (from a prompt, or from a site's
 * branding) can write a theme later without code changes: build an array with the keys below,
 * pass it to Launcher_Theme::save(), and every value is checked and given its default.
 *
 *     design  modern | classic   Classic keeps the chat button WPBot always had.
 *     finish  smoked | flat | clear | sphere | marble | bubble | frosted | liquid | ring | aurora
 *     style   solid | white | bare   Button in the finish, a white button with the icon in the theme
 *                                color, or no button (the icon floats on the page).
 *     icon    site | logo | molecule   "site" (the default): the icon chosen in the host's Icons list,
 *                                so what the site owner selected or uploaded is what the button
 *                                shows (the list's "WPBot Logo" is shown as the animated logo without
 *                                its white background, its molecule in the theme color). "logo" and
 *                                "molecule" force those icons (for a theme generator). Uploaded icons
 *                                and icon videos are always kept, whatever this says.
 *     color   #rrggbb            Theme color (the logo blue by default).
 *     size    48..72             Button size in pixels.
 *     motion  enabled (bool); wait: none | liquid | glow | float | orbit | sweep | shine | heartbeat |
 *             bounce | label | typing | wave | hi | alive; enter: rise | pop | drop | spin | roll |
 *             fade | none; open: turn | zoom | flip | slide | squircle
 *     box     layout (preview | card | pills | ask | side | speech | compact | off), typing (bool)
 *     icon    also any Launcher_Icons id (forces that vector icon).
 *     window  The chat window theme (Window\Window_Theme), checked by its own schema.
 *
 * Version 2 (owner, 18 November 2026): new defaults (animated logo, Rise, Turn, Chat preview) and
 * the clear and sphere finishes, the logo icon, Rise, Turn, Flip and three box layouts. A site still
 * on version 1's untouched defaults moves to the new ones; a site that changed them keeps its look.
 *
 * Version 4 (owner, 18 November 2026: "include all preview page options"): the button style and
 * every finish, motion, box layout and vector icon of the preview page.
 *
 * Version 5 (owner, 21 November 2026: "Select Clear Glass as button finish as Icon default when users
 * upgrade"): the default finish is clear glass. A site still on the old default (smoked glass) moves
 * to it once; a site that picked another finish keeps it.
 *
 * Version 3 (owner, 18 November 2026: "the selected chatbot icon changed"): the button follows the
 * Icons list again. Version 2 showed its own icon setting instead, so a picture chosen in the list
 * was replaced by the animated logo or the molecule; every theme moves to "site" once.
 *
 * "box.layout = off" is the existing "Disable Floating Notification Box" setting, so sites that
 * turned the box off stay off, and either screen changes both.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Widget;

use QuantumCloud\Chatbot\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Launcher theme.
 */
final class Launcher_Theme {

	/**
	 * Stored theme.
	 */
	const OPTION = 'qcld_wpbot_chat_launcher_theme';

	/**
	 * The host's "Disable Floating Notification Box" switch.
	 */
	const BOX_OFF_OPTION = 'disable_wp_chatbot_notification';

	/**
	 * The built-in vector icon (drawn by CSS in the theme color; the file is for the classic design).
	 */
	const VECTOR_ICON = 'icon-molecule.svg';

	/**
	 * The host's animated WPBot logo (shown with the module's transparent copy on the new design).
	 */
	const LOGO_ICON = 'wpbot-logo.gif';

	/**
	 * The host's icon names for an uploaded picture (kept whatever the theme says).
	 */
	const UPLOADED_ICONS = array( 'custom.png' );

	/**
	 * Defaults: the design the owner chose.
	 */
	const DEFAULTS = array(
		'version' => 5,
		'design'  => 'modern',
		'finish'  => 'clear',
		'style'   => 'solid',
		'icon'    => 'site',
		'color'   => '#3d8bd8',
		'size'    => 60,
		'motion'  => array(
			'enabled' => true,
			'wait'    => 'none',
			'enter'   => 'rise',
			'open'    => 'turn',
		),
		'box'     => array(
			'layout' => 'preview',
			'typing' => true,
		),
		'window'  => array(),
	);

	/**
	 * The default finish before version 5.
	 */
	const OLD_DEFAULT_FINISH = 'smoked';

	/**
	 * Allowed values.
	 */
	const CHOICES = array(
		'design' => array( 'modern', 'classic' ),
		'finish' => array( 'smoked', 'flat', 'clear', 'sphere', 'marble', 'bubble', 'frosted', 'liquid', 'ring', 'aurora' ),
		'style'  => array( 'solid', 'white', 'bare' ),
		'icon'   => array( 'site', 'logo', 'molecule', 'dots', 'smile', 'two', 'headset', 'bot', 'spark', 'question', 'plain', 'aichat', 'heart', 'bulb', 'mail', 'mic' ),
		'wait'   => array( 'none', 'liquid', 'glow', 'float', 'orbit', 'sweep', 'shine', 'heartbeat', 'bounce', 'label', 'typing', 'wave', 'hi', 'alive' ),
		'enter'  => array( 'rise', 'pop', 'drop', 'spin', 'roll', 'fade', 'none' ),
		'open'   => array( 'turn', 'zoom', 'flip', 'slide', 'squircle' ),
		'layout' => array( 'preview', 'card', 'pills', 'ask', 'side', 'speech', 'compact', 'off' ),
	);

	/**
	 * Version 1's defaults (a site still on all of them never changed its look).
	 */
	const V1_DEFAULTS = array(
		'finish' => 'smoked',
		'wait'   => 'liquid',
		'enter'  => 'roll',
		'open'   => 'zoom',
		'layout' => array( 'ask', 'off' ),
	);

	/**
	 * Cached theme for this request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * The theme, checked, with the floating box switch from the host setting.
	 *
	 * @return array
	 */
	public static function get() {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION, null );
			if ( ! is_array( $stored ) ) {
				$stored = array( 'design' => self::first_design() );
			}
			$theme = self::sanitize( self::upgrade( $stored ) );
			if ( Options::on( self::BOX_OFF_OPTION ) ) {
				$theme['box']['layout'] = 'off';
			} elseif ( 'off' === $theme['box']['layout'] ) {
				$theme['box']['layout'] = self::DEFAULTS['box']['layout'];
			}
			/**
			 * The chat button theme (see Launcher_Theme for the keys).
			 *
			 * @param array $theme Theme.
			 */
			$filtered    = apply_filters( 'qcld_wpbot_chat/launcher_theme', $theme );
			self::$cache = is_array( $filtered ) ? self::sanitize( $filtered ) : $theme;
		}
		return self::$cache;
	}

	/**
	 * Save a theme (any subset of the keys; the rest keeps its saved value).
	 *
	 * @param array $theme Theme.
	 * @return array The saved theme.
	 */
	public static function save( array $theme ) {
		$current = get_option( self::OPTION, null );
		$current = self::sanitize( self::upgrade( is_array( $current ) ? $current : array( 'design' => self::first_design() ) ) );
		if ( Options::on( self::BOX_OFF_OPTION ) ) {
			// Saving other keys must not turn the floating box back on.
			$current['box']['layout'] = 'off';
		}
		$merged = self::sanitize( self::merge( $current, $theme ) );
		update_option( self::OPTION, $merged );
		update_option( self::BOX_OFF_OPTION, 'off' === $merged['box']['layout'] ? '1' : '' );
		self::$cache = null;
		/**
		 * A chat button theme was saved.
		 *
		 * @param array $merged Theme.
		 */
		do_action( 'qcld_wpbot_chat/launcher_theme_saved', $merged );
		return $merged;
	}

	/**
	 * Check every value; unknown keys are dropped, missing or wrong ones use the default.
	 *
	 * @param array $theme Theme.
	 * @return array
	 */
	public static function sanitize( array $theme ) {
		$d   = self::DEFAULTS;
		$out = $d;

		$out['design'] = self::choice( $theme, 'design', 'design' );
		$out['finish'] = self::choice( $theme, 'finish', 'finish' );
		$out['style']  = self::choice( $theme, 'style', 'style' );
		$out['icon']   = self::choice( $theme, 'icon', 'icon' );
		$color         = isset( $theme['color'] ) ? strtolower( trim( (string) $theme['color'] ) ) : '';
		$out['color']  = preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $color ) ? $color : $d['color'];
		$size          = isset( $theme['size'] ) ? (int) $theme['size'] : $d['size'];
		$out['size']   = min( 72, max( 48, $size ) );

		$motion                   = isset( $theme['motion'] ) && is_array( $theme['motion'] ) ? $theme['motion'] : array();
		$out['motion']['enabled'] = array_key_exists( 'enabled', $motion ) ? self::flag( $motion['enabled'] ) : $d['motion']['enabled'];
		$out['motion']['wait']    = self::choice( $motion, 'wait', 'wait', $d['motion']['wait'] );
		$out['motion']['enter']   = self::choice( $motion, 'enter', 'enter', $d['motion']['enter'] );
		$out['motion']['open']    = self::choice( $motion, 'open', 'open', $d['motion']['open'] );

		$box                  = isset( $theme['box'] ) && is_array( $theme['box'] ) ? $theme['box'] : array();
		$out['box']['layout'] = self::choice( $box, 'layout', 'layout', $d['box']['layout'] );
		$out['box']['typing'] = array_key_exists( 'typing', $box ) ? self::flag( $box['typing'] ) : $d['box']['typing'];

		// The window theme (Window_Theme): checked by its own schema; empty until it is first stored.
		$out['window'] = isset( $theme['window'] ) && is_array( $theme['window'] ) && $theme['window'] ? \QuantumCloud\Chatbot\Window\Window_Theme::sanitize( $theme['window'] ) : array();
		return $out;
	}

	/**
	 * Whether the new design is on.
	 *
	 * @return bool
	 */
	public static function modern() {
		return 'modern' === self::get()['design'];
	}

	/**
	 * Classes for the html element (the design's CSS hangs off these).
	 *
	 * @return string[]
	 */
	public static function classes() {
		$t   = self::get();
		$out = array( 'qcld-wpbot-d2', 'qcld-wpbot-f-' . $t['finish'], 'qcld-wpbot-box-' . $t['box']['layout'] );
		if ( 'solid' !== $t['style'] ) {
			$out[] = 'qcld-wpbot-st-' . $t['style'];
		}
		if ( $t['motion']['enabled'] ) {
			$out[] = 'qcld-wpbot-m';
			if ( 'none' !== $t['motion']['wait'] ) {
				$out[] = 'qcld-wpbot-w-' . $t['motion']['wait'];
			}
			if ( 'none' !== $t['motion']['enter'] ) {
				$out[] = 'qcld-wpbot-e-' . $t['motion']['enter'];
			}
		}
		if ( $t['motion']['enabled'] ) {
			$out[] = 'qcld-wpbot-o-' . $t['motion']['open'];
		}
		$icon = self::icon_shown();
		if ( Launcher_Icons::exists( $icon ) ) {
			$out[] = 'qcld-wpbot-vec';
		} elseif ( 'logo' === $icon ) {
			$out[] = 'qcld-wpbot-logo';
		}
		return $out;
	}

	/**
	 * CSS custom properties for the theme.
	 *
	 * @return string
	 */
	public static function css_vars() {
		$t    = self::get();
		$logo = 'logo' === self::icon_shown() ? ';--qcld-wpbot-logo:url("' . esc_url_raw( self::logo_url() ) . '")' : '';
		return ':root{--qcld-wpbot-c:' . $t['color'] . ';--qcld-wpbot-s:' . (int) $t['size'] . 'px' . $logo . '}';
	}

	/**
	 * Whether the button shows the built-in vector icon. Uploaded images, icon videos and the
	 * older built-in pictures are shown as they are.
	 *
	 * @return bool
	 */
	public static function vector_icon() {
		return Launcher_Icons::exists( self::icon_shown() );
	}

	/**
	 * What the button shows: "upload" (an uploaded icon or icon video, always kept), "logo" (the
	 * animated WPBot logo, when the Icons list has "WPBot Logo" or the theme forces it), "molecule"
	 * (drawn in the theme color, when the list has the molecule or the theme forces it) or "site"
	 * (the picture chosen in the host's Icons list, as it is).
	 *
	 * @return string
	 */
	public static function icon_shown() {
		$host = (string) get_option( 'wp_chatbot_icon', '' );
		if ( '' !== trim( (string) get_option( 'wp_chatbot_icon_video', '' ) ) || in_array( $host, self::UPLOADED_ICONS, true ) ) {
			return 'upload';
		}
		$icon = self::get()['icon'];
		if ( 'site' !== $icon ) {
			return $icon;
		}
		$vector = Launcher_Icons::for_host( $host );
		if ( '' !== $vector ) {
			return $vector;
		}
		return self::LOGO_ICON === $host ? 'logo' : 'site';
	}

	/**
	 * An older theme as the current version. Version 1: untouched defaults become the new defaults
	 * (box "off" stays off); a theme that was changed keeps its look. Versions 1 and 2: the button
	 * follows the host's Icons list ("site"), so the icon the site owner selected or uploaded shows.
	 *
	 * @param array $stored Stored theme.
	 * @return array
	 */
	public static function upgrade( array $stored ) {
		$version = isset( $stored['version'] ) ? (int) $stored['version'] : 1;
		if ( $version >= self::DEFAULTS['version'] ) {
			return $stored;
		}
		if ( 4 === $version ) {
			$stored            = self::to_clear_glass( $stored );
			$stored['version'] = self::DEFAULTS['version'];
			return $stored;
		}
		if ( $version >= 2 ) {
			if ( $version < 3 ) {
				// Version 3: the button follows the Icons list (what the site owner selected or uploaded).
				$stored['icon'] = 'site';
			}
			// Version 4 only adds keys; their defaults keep the look.
			$stored            = self::to_clear_glass( $stored );
			$stored['version'] = self::DEFAULTS['version'];
			return $stored;
		}
		$motion            = isset( $stored['motion'] ) && is_array( $stored['motion'] ) ? $stored['motion'] : array();
		$box               = isset( $stored['box'] ) && is_array( $stored['box'] ) ? $stored['box'] : array();
		$v1                = self::V1_DEFAULTS;
		$value             = function ( $from, $key, $fallback ) {
			return isset( $from[ $key ] ) ? strtolower( (string) $from[ $key ] ) : $fallback;
		};
		$layout            = $value( $box, 'layout', 'ask' );
		$default           = $value( $stored, 'finish', $v1['finish'] ) === $v1['finish']
			&& $value( $motion, 'wait', $v1['wait'] ) === $v1['wait']
			&& $value( $motion, 'enter', $v1['enter'] ) === $v1['enter']
			&& $value( $motion, 'open', $v1['open'] ) === $v1['open']
			&& in_array( $layout, $v1['layout'], true );
		$stored['version'] = self::DEFAULTS['version'];
		$stored['icon']    = 'site';
		if ( $default ) {
			$stored['finish']        = self::DEFAULTS['finish'];
			$stored['motion']        = array_merge( $motion, array_intersect_key( self::DEFAULTS['motion'], array_flip( array( 'wait', 'enter', 'open' ) ) ) );
			$stored['box']           = $box;
			$stored['box']['layout'] = 'off' === $layout ? 'off' : self::DEFAULTS['box']['layout'];
		}
		return self::to_clear_glass( $stored );
	}

	/**
	 * Version 5: the old default finish (smoked glass, or none saved) becomes the new default.
	 *
	 * @param array $stored Stored theme.
	 * @return array
	 */
	private static function to_clear_glass( array $stored ) {
		if ( ! isset( $stored['finish'] ) || self::OLD_DEFAULT_FINISH === strtolower( (string) $stored['finish'] ) ) {
			$stored['finish'] = self::DEFAULTS['finish'];
		}
		return $stored;
	}

	/**
	 * The animated WPBot logo (transparent, for the button).
	 *
	 * @return string
	 */
	public static function logo_url() {
		$file = defined( 'QCLD_WPBOT_CHAT_FILE' ) ? QCLD_WPBOT_CHAT_FILE : dirname( __DIR__, 2 ) . '/chatbot.php';
		return plugins_url( 'assets/img/wpbot-logo.webp', $file );
	}

	/**
	 * First design on a site with no saved theme: classic when WPBot ran here before (the
	 * module's schema exists), so updated sites keep their look; new installs get the new design.
	 *
	 * @return string
	 */
	public static function first_design() {
		return false !== get_option( \QuantumCloud\Chatbot\Sessions\Schema::OPTION, false ) ? 'classic' : 'modern';
	}

	/**
	 * Store the first design once, so later schema installs do not change it.
	 *
	 * @return void
	 */
	public static function remember_first_design() {
		$stored = get_option( self::OPTION, null );
		if ( ! is_array( $stored ) ) {
			update_option( self::OPTION, self::sanitize( array( 'design' => self::first_design() ) ) );
			self::$cache = null;
		} elseif ( empty( $stored['version'] ) || (int) $stored['version'] < self::DEFAULTS['version'] ) {
			update_option( self::OPTION, self::sanitize( self::upgrade( $stored ) ) );
			self::$cache = null;
		}
	}

	/**
	 * Forget the cached theme (tests, after options change).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$cache = null;
	}

	/**
	 * One allowed value.
	 *
	 * @param array  $from     Source.
	 * @param string $key      Key in the source.
	 * @param string $choices  CHOICES list.
	 * @param string $fallback Default (the top-level default when empty).
	 * @return string
	 */
	private static function choice( array $from, $key, $choices, $fallback = '' ) {
		$value = isset( $from[ $key ] ) ? strtolower( trim( (string) $from[ $key ] ) ) : '';
		if ( in_array( $value, self::CHOICES[ $choices ], true ) ) {
			return $value;
		}
		return '' !== $fallback ? $fallback : self::DEFAULTS[ $key ];
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

	/**
	 * Merge a partial theme into a full one (nested motion and box).
	 *
	 * @param array $base  Full theme.
	 * @param array $patch Partial theme.
	 * @return array
	 */
	private static function merge( array $base, array $patch ) {
		foreach ( $patch as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && 'window' !== $key ) {
				$base[ $key ] = array_merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}
}
