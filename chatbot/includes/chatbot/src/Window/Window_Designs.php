<?php
/**
 * The chat window designs.
 *
 * Every design is listed here so the designer can show it, but only Classic and Front desk are
 * available from this module. WPBot Pro makes the other designs available through the
 * `qcld_wpbot_chat/window_designs` filter (and ships their styles); without it they show as
 * greyed-out "Pro" thumbnails (owner, 20 November 2026) and a saved Pro design falls back to Classic.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Window;

defined( 'ABSPATH' ) || exit;

/**
 * Design registry.
 */
final class Window_Designs {

	/**
	 * The design every site can use and the fallback for one that is not available.
	 */
	const FALLBACK = 'classic';

	/**
	 * Built-in list. `brand`: tokens that follow the site's brand colour ('all' = whole palette
	 * built from it, as Classic does); `own`: the design keeps its own palette even when a brand
	 * colour exists.
	 *
	 * @return array[]
	 */
	private static function base() {
		return array(
			'classic'  => array(
				'name'        => __( 'Classic', 'chatbot' ),
				'description' => __( 'The familiar chat window: coloured header, bubbles with tails.', 'chatbot' ),
				'available'   => true,
				'pro'         => false,
				'brand'       => 'all',
			),
			'desk'     => array(
				'name'        => __( 'Front desk', 'chatbot' ),
				'description' => __( 'A name, not a logo; answers read like a note.', 'chatbot' ),
				'available'   => true,
				'pro'         => false,
				'brand'       => array( 'accent' ),
			),
			'poster'   => array(
				'name'        => __( 'Poster', 'chatbot' ),
				'description' => __( 'Ink outlines, hard shadows, a sticker.', 'chatbot' ),
				'available'   => false,
				'pro'         => true,
				'brand'       => array( 'head' ),
			),
			'receipt'  => array(
				'name'        => __( 'Till slip', 'chatbot' ),
				'description' => __( 'Prints like a receipt, with a barcode at the end.', 'chatbot' ),
				'available'   => false,
				'pro'         => true,
				'brand'       => array( 'accent' ),
			),
			'island'   => array(
				'name'        => __( 'Floating island', 'chatbot' ),
				'description' => __( 'No frame: glass pieces over the page.', 'chatbot' ),
				'available'   => false,
				'pro'         => true,
				'brand'       => array( 'accent' ),
			),
			'console'  => array(
				'name'        => __( 'Console', 'chatbot' ),
				'description' => __( 'A command line with numbered suggestions.', 'chatbot' ),
				'available'   => false,
				'pro'         => true,
				'brand'       => array( 'accent' ),
			),
			'notebook' => array(
				'name'        => __( 'Exercise book', 'chatbot' ),
				'description' => __( 'Ruled paper, a marker pen and tick boxes.', 'chatbot' ),
				'available'   => false,
				'pro'         => true,
				'brand'       => array( 'accent' ),
			),
			'holo'     => array(
				'name'        => __( 'Lightcycle', 'chatbot' ),
				'description' => __( 'A sci-fi HUD panel: angular glowing frame, dark glass, faint grid.', 'chatbot' ),
				'available'   => false,
				'pro'         => true,
				'brand'       => array(),
				'own'         => true,
			),
		);
	}

	/**
	 * All designs, after Pro (or another extension) has had its say.
	 *
	 * @return array[] Keyed by design id.
	 */
	public static function all() {
		$base = self::base();
		/**
		 * The chat window designs. Set `available` to true for designs your package ships.
		 * Ids are fixed: unknown ids are ignored, and Classic is always available.
		 *
		 * @param array[] $designs Keyed by id: name, description, available, pro, brand, own.
		 */
		$filtered = apply_filters( 'qcld_wpbot_chat/window_designs', $base );
		$out      = $base;
		if ( is_array( $filtered ) ) {
			foreach ( $base as $id => $design ) {
				if ( isset( $filtered[ $id ]['available'] ) ) {
					$out[ $id ]['available'] = (bool) $filtered[ $id ]['available'];
				}
			}
		}
		$out[ self::FALLBACK ]['available'] = true;
		return $out;
	}

	/**
	 * Every known design id.
	 *
	 * @return string[]
	 */
	public static function ids() {
		return array_keys( self::base() );
	}

	/**
	 * Whether a design can be used on this site.
	 *
	 * @param string $id Design id.
	 * @return bool
	 */
	public static function available( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) && $all[ $id ]['available'];
	}

	/**
	 * One design's metadata (Classic's when unknown).
	 *
	 * @param string $id Design id.
	 * @return array
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : $all[ self::FALLBACK ];
	}
}
