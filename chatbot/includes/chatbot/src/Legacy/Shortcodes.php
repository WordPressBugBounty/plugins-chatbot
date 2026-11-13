<?php
/**
 * Guards for the legacy chat shortcodes.
 *
 * [wpwbot_products] and [wpwbot_cart] read WooCommerce's cart and products; on a
 * site without WooCommerce (or where the cart is not loaded, such as the block
 * editor's REST previews) they ended in a fatal error (B1). They now render
 * nothing there and work as before otherwise.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Legacy;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode guards.
 */
final class Shortcodes {

	/**
	 * Shortcodes that need WooCommerce, and whether they need the cart.
	 */
	const WOO = array(
		'wpwbot_products' => false,
		'wpwbot_cart'     => true,
	);

	/**
	 * Hook after the host registered its shortcodes.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'wrap' ), 99 );
	}

	/**
	 * Wrap the WooCommerce shortcodes.
	 *
	 * @return void
	 */
	public static function wrap() {
		global $shortcode_tags;
		foreach ( self::WOO as $tag => $needs_cart ) {
			if ( ! isset( $shortcode_tags[ $tag ] ) || ! is_callable( $shortcode_tags[ $tag ] ) ) {
				continue;
			}
			$original               = $shortcode_tags[ $tag ];
			$shortcode_tags[ $tag ] = static function ( $atts = array(), $content = '', $name = '' ) use ( $original, $needs_cart ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- wraps the host's callback in place.
				if ( ! self::woo_ready( $needs_cart ) ) {
					return '';
				}
				return call_user_func( $original, $atts, $content, $name );
			};
		}
	}

	/**
	 * Whether WooCommerce (and its cart, when needed) is available.
	 *
	 * @param bool $needs_cart Needs the cart.
	 * @return bool
	 */
	public static function woo_ready( $needs_cart ) {
		if ( ! function_exists( 'WC' ) || ! class_exists( 'WooCommerce' ) ) {
			return false;
		}
		return ! $needs_cart || ( isset( WC()->cart ) && is_object( WC()->cart ) );
	}
}
