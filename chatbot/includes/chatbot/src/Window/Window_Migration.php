<?php
/**
 * One-time upgrade from the old chat window settings to the window theme (owner, 20 November
 * 2026: "straight switch", sites keep their look).
 *
 * - Old theme (`qcld_wb_chatbot_theme`) -> nearest design and layout.
 * - Custom colours (only when `enable_wp_chatbot_custom_color` is on) -> the light tokens; the
 *   dark palette is built from the accent.
 * - Maximum height (`chatbot_content_max_height`, % of the screen) -> height in pixels.
 * - Old font settings in use -> "Use custom fonts" on, so the fonts keep applying.
 * The old options are left in place (rollback safe) and are no longer read once the theme is
 * stored. Retired settings (background image, bottom icons) are ignored. Running it twice gives the
 * same result.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Window;

use QuantumCloud\Chatbot\Widget\Launcher_Theme;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy settings migration.
 */
final class Window_Migration {

	/**
	 * Set (autoload off) when the window theme was built from the old settings.
	 */
	const FLAG = 'qcld_wpbot_chat_window_migrated';

	/**
	 * Old theme -> design, layout and extra values.
	 */
	const THEMES = array(
		'template-00'         => array( 'design' => 'classic' ),
		'template-01'         => array( 'design' => 'classic' ),
		'template-02'         => array( 'design' => 'classic' ),
		'template-03'         => array( 'design' => 'classic' ),
		'template-04'         => array( 'design' => 'desk' ),
		'template-05'         => array(
			'design' => 'classic',
			'layout' => 'compact',
		),
		'template-horizontal' => array(
			'design' => 'classic',
			'layout' => 'side',
		),
		'template-06'         => array( 'design' => 'classic' ),
		'template-07'         => array(
			'design' => 'classic',
			'head'   => '#111111',
		),
		'template-08'         => array(
			'design' => 'classic',
			'mode'   => 'dark',
		),
	);

	/**
	 * Whether the old settings should be used: WPBot ran on this site before (the same test the
	 * chat button uses to keep updated sites on their old button) or an old theme is saved.
	 *
	 * @return bool
	 */
	public static function needed() {
		return 'classic' === Launcher_Theme::first_design() || false !== get_option( 'qcld_wb_chatbot_theme', false );
	}

	/**
	 * The window theme built from the old settings (not yet checked; Window_Theme::sanitize does that).
	 *
	 * @return array
	 */
	public static function build() {
		$old   = strtolower( trim( (string) get_option( 'qcld_wb_chatbot_theme', 'template-00' ) ) );
		$map   = isset( self::THEMES[ $old ] ) ? self::THEMES[ $old ] : self::THEMES['template-00'];
		$theme = Window_Theme::defaults( $map['design'], self::custom( 'wp_chatbot_theme_primary_color' ) );

		if ( isset( $map['layout'] ) ) {
			$theme['layout'] = $map['layout'];
		}
		if ( isset( $map['mode'] ) ) {
			$theme['mode'] = $map['mode'];
		}
		if ( isset( $map['head'] ) ) {
			$theme['colors']['light']['head']   = $map['head'];
			$theme['colors']['light']['headFg'] = Window_Colors::best_text( $map['head'] );
		}

		$theme['colors'] = self::colors( $theme['colors'] );

		$vh = (int) get_option( 'chatbot_content_max_height', 0 );
		if ( $vh > 0 ) {
			// The old height was a share of the screen; 8.5 px per % matches a typical laptop window.
			$theme['height'] = min( 860, max( 400, (int) round( min( 100, $vh ) * 8.5 ) ) );
		}

		$theme['customFonts'] = self::fonts_in_use();
		return $theme;
	}

	/**
	 * The old custom colours on top of a design's palettes (only when custom colours were on).
	 *
	 * @param array $colors Palettes: light, dark.
	 * @return array
	 */
	private static function colors( array $colors ) {
		if ( '1' !== (string) get_option( 'enable_wp_chatbot_custom_color', '' ) ) {
			return $colors;
		}
		$light  = $colors['light'];
		$accent = self::custom( 'wp_chatbot_theme_primary_color' );
		$accent = '' !== $accent ? $accent : self::custom( 'wp_chatbot_buttons_bg_color' );
		$set    = array(
			'accent' => $accent,
			'head'   => self::custom( 'wp_chatbot_header_background_color' ),
			'bot'    => self::custom( 'wp_chatbot_bot_msg_bg_color' ),
			'botFg'  => self::custom( 'wp_chatbot_bot_msg_text_color' ),
			'me'     => self::custom( 'wp_chatbot_user_msg_bg_color' ),
			'meFg'   => self::custom( 'wp_chatbot_user_msg_text_color' ),
			'chip'   => self::custom( 'wp_chatbot_link_color' ),
			'fg'     => self::custom( 'wp_chatbot_text_color' ),
		);
		if ( '' === $set['head'] ) {
			$set['head'] = $accent;
		}
		if ( '' === $set['chip'] ) {
			$set['chip'] = self::custom( 'wp_chatbot_buttons_bg_color' );
		}
		foreach ( $set as $token => $value ) {
			if ( '' !== $value ) {
				$light[ $token ] = $value;
			}
		}
		// Text on the colours that changed must stay readable.
		$light['accentFg'] = Window_Colors::best_text( $light['accent'] );
		$light['headFg']   = Window_Colors::best_text( $light['head'] );
		foreach ( array(
			'bot' => 'botFg',
			'me'  => 'meFg',
			'bg'  => 'fg',
		) as $bg => $fg ) {
			if ( Window_Colors::contrast( $light[ $fg ], $light[ $bg ] ) < 4.5 ) {
				$light[ $fg ] = Window_Colors::ensure( $light[ $fg ], $light[ $bg ], 4.5 );
			}
		}
		$light['chip']   = Window_Colors::ensure( $light['chip'], $light['composer'], 4.5 );
		$colors['light'] = Window_Colors::ordered( $light, $colors['light'] );
		if ( '' !== $accent ) {
			$colors['dark'] = Window_Colors::classic( $accent, true );
		}
		return $colors;
	}

	/**
	 * Whether the old font settings change the look (then "Use custom fonts" starts on).
	 *
	 * @return bool
	 */
	private static function fonts_in_use() {
		$size = trim( (string) get_option( 'wp_chatbot_font_size', '' ) );
		return '' !== trim( (string) get_option( 'wp_chatbot_user_font', '' ) )
			|| '' !== trim( (string) get_option( 'wp_chatbot_bot_font', '' ) )
			|| ( '' !== $size && '16' !== $size );
	}

	/**
	 * One old colour option, as #rrggbb or ''.
	 *
	 * @param string $name Option name.
	 * @return string
	 */
	private static function custom( $name ) {
		return Window_Colors::hex( get_option( $name, '' ) );
	}
}
