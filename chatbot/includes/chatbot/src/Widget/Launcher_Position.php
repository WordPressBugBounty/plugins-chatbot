<?php
/**
 * Where the chat button sits: the side of the screen (right or left) and its distance from that side
 * and from the bottom (owner, 24 November 2026: "missing the option to fine tune the icon position
 * from right with pixels; this should work with previously saved settings; also, need option to show
 * the chatbot icon on the left").
 *
 * The distances are the hosts' own options, so what a site saved before keeps working:
 *   wp_chatbot_position_x / _y    From the side / from the bottom (both hosts).
 *   wp_chatbot_position_in        px or % (WPBot Pro; WPBot Free is always px).
 *   wp_chatbot_position_mp_x / _y / _mp_in   Phones up to 480 px wide (WPBot Pro only).
 * The side is new (qcld_wpbot_chat_launcher_side): right, as before, unless the owner picks left.
 *
 * The hosts' scripts place the button on the right with inline styles. On the left, this stylesheet
 * moves it (and, in the preview, follows the designer live); it stays out of the way while the hosts
 * show the chat full screen on a phone (they pin the container to left 0 then).
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Widget;

use QuantumCloud\Chatbot\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Chat button position.
 */
final class Launcher_Position {

	/**
	 * Side of the screen: right or left.
	 */
	const SIDE_OPTION = 'qcld_wpbot_chat_launcher_side';

	/**
	 * Default distances (the launcher's until now).
	 */
	const DEFAULT_OFFSET = 50;

	/**
	 * Largest distance accepted, in pixels.
	 */
	const MAX_OFFSET = 2000;

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		if ( is_admin() ) {
			return;
		}
		add_action( 'wp_head', array( __CLASS__, 'print_head' ), 3 );
	}

	/**
	 * Side of the screen.
	 *
	 * @return string right or left.
	 */
	public static function side() {
		/**
		 * Side of the screen the chat button sits on.
		 *
		 * @param string $side right or left.
		 */
		$side = (string) apply_filters( 'qcld_wpbot_chat/launcher_side', (string) get_option( self::SIDE_OPTION, 'right' ) );
		return 'left' === $side ? 'left' : 'right';
	}

	/**
	 * Save the side (from the Icons designer).
	 *
	 * @param string $side right or left.
	 * @return void
	 */
	public static function save_side( $side ) {
		update_option( self::SIDE_OPTION, 'left' === $side ? 'left' : 'right', false );
	}

	/**
	 * The distances, read from the hosts' options.
	 *
	 * @return array { x: int, y: int, unit: px|%, mobile: array|null { x, y, unit } }
	 */
	public static function offsets() {
		$out = array(
			'x'      => self::number( Options::get( 'wp_chatbot_position_x', '' ) ),
			'y'      => self::number( Options::get( 'wp_chatbot_position_y', '' ) ),
			'unit'   => self::unit( get_option( 'wp_chatbot_position_in' ) ),
			'mobile' => null,
		);
		$mx  = get_option( 'wp_chatbot_position_mp_x', '' );
		$my  = get_option( 'wp_chatbot_position_mp_y', '' );
		if ( '' !== (string) $mx || '' !== (string) $my ) {
			$out['mobile'] = array(
				'x'    => self::number( $mx ),
				'y'    => self::number( $my ),
				'unit' => self::unit( get_option( 'wp_chatbot_position_mp_in' ) ),
			);
		}
		return $out;
	}

	/**
	 * A saved distance as a whole number (empty or not a number: the default).
	 *
	 * @param mixed $value Saved value.
	 * @return int
	 */
	private static function number( $value ) {
		if ( ! is_scalar( $value ) || ! is_numeric( trim( (string) $value ) ) ) {
			return self::DEFAULT_OFFSET;
		}
		return max( 0, min( self::MAX_OFFSET, (int) round( (float) $value ) ) );
	}

	/**
	 * The unit: % only when saved as such.
	 *
	 * @param mixed $value Saved unit.
	 * @return string px or %.
	 */
	private static function unit( $value ) {
		return '%' === (string) $value ? '%' : 'px';
	}

	/**
	 * The position CSS: the distances as variables, and the rules that use them.
	 *
	 * @return string
	 */
	public static function css() {
		$o   = self::offsets();
		$css = ':root{--qcld-wpbot-px:' . $o['x'] . $o['unit'] . ';--qcld-wpbot-py:' . $o['y'] . $o['unit'] . '}';
		if ( $o['mobile'] ) {
			$css .= '@media (max-width:480px){:root{--qcld-wpbot-px:' . $o['mobile']['x'] . $o['mobile']['unit'] . ';--qcld-wpbot-py:' . $o['mobile']['y'] . $o['mobile']['unit'] . '}}';
		}
		// The container the hosts place (not while they show the chat full screen on a phone).
		$box  = 'html.%s #wp-chatbot-chat-container:not(.wp-chatbot-mobile-fs-open):not([style*="left: 0"])';
		$ball = 'html.qcld-wpbot-lazy.%s #wp-chatbot-ball';
		$css .= sprintf( $box, 'qcld-wpbot-pos-left' ) . ',' . sprintf( $ball, 'qcld-wpbot-pos-left' ) . '{left:var(--qcld-wpbot-px)!important;right:auto!important}';
		// The hosts float the icon right inside its container (full width on phones in WPBot Pro).
		$css .= 'html.qcld-wpbot-pos-left #wp-chatbot-ball .wp-chatbot-ball{float:left!important}';
		// Live in the designer's preview: the distances follow the fields at once, on either side.
		$css .= sprintf( $box, 'qcld-wpbot-pos-live:not(.qcld-wpbot-pos-left)' ) . ',' . sprintf( $ball, 'qcld-wpbot-pos-live:not(.qcld-wpbot-pos-left)' ) . '{right:var(--qcld-wpbot-px)!important;left:auto!important}';
		$css .= sprintf( $box, 'qcld-wpbot-pos-live' ) . ',' . sprintf( $ball, 'qcld-wpbot-pos-live' ) . '{bottom:var(--qcld-wpbot-py)!important;top:auto!important}';
		// The classic floating box opens toward the page, not off the screen.
		$css .= 'html.qcld-wpbot-pos-left:not(.qcld-wpbot-d2) #wp-chatbot-notification-container{left:0!important;right:auto!important}';
		return $css;
	}

	/**
	 * Head: the position CSS and, on the left, the class that turns it on.
	 *
	 * @return void
	 */
	public static function print_head() {
		if ( Options::on( 'disable_wp_chatbot' ) || wp_is_json_request() ) {
			return;
		}
		echo '<style id="qcld-wpbot-position-css">' . self::css() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whole numbers and fixed text.
		if ( 'left' === self::side() ) {
			echo '<script>document.documentElement.classList.add("qcld-wpbot-pos-left");</script>' . "\n";
		}
	}
}
