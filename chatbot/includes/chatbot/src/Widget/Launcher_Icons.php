<?php
/**
 * The chat button's built-in vector icons (owner, 18 November 2026: every icon on the preview page).
 *
 * The new design draws these with a CSS mask, so they take the theme color and the button style
 * (white button: the icon in the theme color). Four of the hosts' Icons list pictures are the same
 * drawings (icon-3, 9, 10 and 11 were replaced by them), and the molecule ships as an SVG: picking
 * one of those in the Icons list shows the vector here. The others are chosen in the designer.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Vector icon catalogue.
 */
final class Launcher_Icons {

	/**
	 * A speech bubble outline (24 x 24).
	 */
	const BUBBLE = 'M7 4h10a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3h-6l-4 3.5V16a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3z';

	/**
	 * The hosts' Icons list pictures that are these drawings.
	 */
	const HOST_ALIASES = array(
		'icon-molecule.svg' => 'molecule',
		'icon-3.png'        => 'dots',
		'icon-9.png'        => 'smile',
		'icon-10.png'       => 'two',
		'icon-11.png'       => 'headset',
	);

	/**
	 * Icon ids in the designer's order.
	 *
	 * @return string[]
	 */
	public static function ids() {
		return array_keys( self::drawings() );
	}

	/**
	 * Names for the designer.
	 *
	 * @return array<string,string>
	 */
	public static function names() {
		return array(
			'molecule' => __( 'Molecule (from the logo)', 'chatbot' ),
			'dots'     => __( 'Chat bubble with dots', 'chatbot' ),
			'smile'    => __( 'Smiling chat bubble', 'chatbot' ),
			'two'      => __( 'Two chat bubbles', 'chatbot' ),
			'headset'  => __( 'Headset (support)', 'chatbot' ),
			'bot'      => __( 'Robot', 'chatbot' ),
			'spark'    => __( 'AI spark', 'chatbot' ),
			'question' => __( 'Question bubble', 'chatbot' ),
			'plain'    => __( 'Plain chat bubble', 'chatbot' ),
			'aichat'   => __( 'AI chat bubble', 'chatbot' ),
			'heart'    => __( 'Heart bubble', 'chatbot' ),
			'bulb'     => __( 'Light bulb', 'chatbot' ),
			'mail'     => __( 'Envelope', 'chatbot' ),
			'mic'      => __( 'Microphone', 'chatbot' ),
		);
	}

	/**
	 * Inner SVG markup per icon: strokes and fills in black (a mask; the color comes from CSS).
	 *
	 * @return array<string,string>
	 */
	public static function drawings() {
		$b = self::BUBBLE;
		$f = ' fill="#000" stroke="none"';
		return array(
			'molecule' => '<path d="M11.5 12.5L10 4.2M11.5 12.5L19.4 11M11.5 12.5L4.6 16M11.5 12.5L15 18.6" stroke-width="1.7"/><circle cx="11.5" cy="12.5" r="3.1"' . $f . '/><circle cx="10" cy="4.1" r="2.1"' . $f . '/><circle cx="19.5" cy="11" r="2.3"' . $f . '/><circle cx="4.5" cy="16" r="2.2"' . $f . '/><circle cx="15.1" cy="18.8" r="1.5"' . $f . '/>',
			'dots'     => '<path d="' . $b . '"/><circle cx="8.5" cy="10" r="1.15"' . $f . '/><circle cx="12" cy="10" r="1.15"' . $f . '/><circle cx="15.5" cy="10" r="1.15"' . $f . '/>',
			'smile'    => '<path d="' . $b . '"/><circle cx="9.4" cy="8.7" r="1"' . $f . '/><circle cx="14.6" cy="8.7" r="1"' . $f . '/><path d="M9 11.6c.8 1 1.8 1.5 3 1.5s2.2-.5 3-1.5"/>',
			'two'      => '<path d="M7 3.5h5a3 3 0 0 1 3 3v3a3 3 0 0 1-3 3H8.5L5 15V12.1a3 3 0 0 1-1-2.2V6.5a3 3 0 0 1 3-3z"/><path d="M18 9.6a3 3 0 0 1 2 2.8v3.1a3 3 0 0 1-1 2.2V20l-2.6-1.6H14a3 3 0 0 1-2.6-1.5"/>',
			'headset'  => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="13" width="4" height="6" rx="1.8"/><rect x="17" y="13" width="4" height="6" rx="1.8"/><path d="M19 19c0 1.6-1.5 2.5-4 2.5h-2"/>',
			'bot'      => '<rect x="4.5" y="8" width="15" height="11" rx="3.5"/><path d="M12 8V5"/><circle cx="12" cy="4" r="1.2"' . $f . '/><circle cx="9.3" cy="13.2" r="1.35"' . $f . '/><circle cx="14.7" cy="13.2" r="1.35"' . $f . '/><path d="M2.5 12v3M21.5 12v3"/>',
			'spark'    => '<path d="M11 3.5l1.9 5.1L18 10.5l-5.1 1.9L11 17.5l-1.9-5.1L4 10.5l5.1-1.9z"/><path d="M18.5 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z"' . $f . '/>',
			'question' => '<path d="' . $b . '"/><path d="M10.1 8.2a2 2 0 1 1 2.8 1.8c-.6.3-.9.7-.9 1.3v.3"/><circle cx="12" cy="13.6" r="1"' . $f . '/>',
			'plain'    => '<path d="' . $b . '"/>',
			'aichat'   => '<path d="' . $b . '"/><path d="M12 6.6l.95 2.35 2.35.95-2.35.95L12 13.2l-.95-2.35-2.35-.95 2.35-.95z"' . $f . '/>',
			'heart'    => '<path d="' . $b . '"/><path d="M12 13.3l-2.7-2.6a1.65 1.65 0 0 1 2.7-1.95 1.65 1.65 0 0 1 2.7 1.95z"' . $f . '/>',
			'bulb'     => '<path d="M9.5 17.5h5M10.5 20.5h3M12 3.5a5.5 5.5 0 0 0-3.2 10c.7.5 1.2 1.3 1.2 2.2v.3h4v-.3c0-.9.5-1.7 1.2-2.2A5.5 5.5 0 0 0 12 3.5z"/>',
			'mail'     => '<rect x="3.5" y="6" width="17" height="12" rx="2.5"/><path d="M4.5 7.5l7.5 5.5 7.5-5.5"/>',
			'mic'      => '<rect x="9" y="3.5" width="6" height="10.5" rx="3"/><path d="M5.5 11.5a6.5 6.5 0 0 0 13 0M12 18v2.5"/>',
		);
	}

	/**
	 * A waving hand (the "Hello wave" motion).
	 */
	const HAND = '<path d="M8 13V6.6a1.3 1.3 0 0 1 2.6 0V11.5M10.6 11V5a1.3 1.3 0 0 1 2.6 0v6M13.2 11V5.8a1.3 1.3 0 0 1 2.6 0V12M15.8 12V8.6a1.3 1.3 0 0 1 2.6 0V14c0 3.6-2.6 6.5-6.2 6.5-2.3 0-3.8-.9-5-2.6L4.9 15a1.4 1.4 0 0 1 2.2-1.7L8 14.4"/>';

	/**
	 * Whether an id is a vector icon.
	 *
	 * @param string $id Icon id.
	 * @return bool
	 */
	public static function exists( $id ) {
		return array_key_exists( (string) $id, self::drawings() );
	}

	/**
	 * The vector for a host Icons list value, or '' when it is a picture.
	 *
	 * @param string $host Host icon file name.
	 * @return string
	 */
	public static function for_host( $host ) {
		return isset( self::HOST_ALIASES[ (string) $host ] ) ? self::HOST_ALIASES[ (string) $host ] : '';
	}

	/**
	 * A complete SVG for an icon (or the hand).
	 *
	 * @param string $id Icon id, or "hand".
	 * @return string
	 */
	public static function svg( $id ) {
		$all   = self::drawings();
		$inner = 'hand' === $id ? self::HAND : ( isset( $all[ $id ] ) ? $all[ $id ] : $all['molecule'] );
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</svg>';
	}

	/**
	 * The icon as a CSS url() for a mask.
	 *
	 * @param string $id Icon id, or "hand".
	 * @return string
	 */
	public static function mask( $id ) {
		return 'url("data:image/svg+xml,' . rawurlencode( self::svg( $id ) ) . '")';
	}
}
