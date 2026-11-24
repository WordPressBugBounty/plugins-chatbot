<?php
/**
 * Page context for AI answers (chatbot features CB1a): a cleaned, size-limited text extract of
 * the page the visitor is on, built on the server from the published post, never from what the
 * browser sends (so hidden or private content is never included).
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Ai;

defined( 'ABSPATH' ) || exit;

/**
 * Page context.
 */
final class Page_Context {

	/**
	 * Default size limit of the extract, in characters.
	 */
	const MAX_CHARS = 2000;

	/**
	 * Text describing the page for the AI ('' when the URL is not a public post on this site).
	 *
	 * @param string        $url    Page URL the widget reported (the browser's Referer: anyone can set it).
	 * @param string        $title  Page title the widget reported (used only when there is no post).
	 * @param string[]|null $labels The visitor's access labels (null = public only).
	 * @return string
	 */
	public static function text( $url, $title = '', $labels = null ) {
		$url  = (string) $url;
		$post = self::post_for_url( $url );
		if ( $post && ! self::visible( $post, null === $labels ? array( 'public' ) : (array) $labels ) ) {
			// Members-only page: the visitor may not read it through the AI. Only its address is known.
			return 'The user is on this page: ' . esc_url_raw( $url );
		}
		if ( ! $post ) {
			$known = self::known_page( $url, null === $labels ? array( 'public' ) : (array) $labels );
			if ( '' !== $known ) {
				return $known;
			}
			$line = trim( (string) $title . ' ' . $url );
			return '' !== $line ? 'The user is on this page: ' . $line : '';
		}
		$parts = array( 'The user is on this page: ' . get_the_title( $post ) . ' (' . get_permalink( $post ) . ')' );
		// A WooCommerce product: its current price and stock (QA 9.1: "How much is it?" on a
		// product page got "I don't have information on the price").
		$parts   = array_merge( $parts, self::product_lines( $post ) );
		$excerpt = has_excerpt( $post ) ? self::clean( (string) $post->post_excerpt ) : '';
		if ( '' !== $excerpt ) {
			$parts[] = 'Summary: ' . $excerpt;
		}
		/**
		 * Size limit of the page text sent to the AI, in characters.
		 *
		 * @param int      $max  Characters.
		 * @param \WP_Post $post Post.
		 */
		$max  = max( 200, (int) apply_filters( 'qcld_wpbot_chat/page_context_max', self::MAX_CHARS, $post ) );
		$body = self::cut( self::clean( (string) $post->post_content ), $max );
		if ( '' !== $body ) {
			$parts[] = "Page text:\n" . $body;
		}
		/**
		 * Page context lines sent to the AI (products include their price and stock; Pro can add more).
		 *
		 * @param string[] $parts Lines.
		 * @param \WP_Post $post  Post.
		 */
		$parts = (array) apply_filters( 'qcld_wpbot_chat/page_context', $parts, $post );
		return implode( "\n", array_filter( array_map( 'strval', $parts ), 'strlen' ) );
	}

	/**
	 * Price and stock lines for a WooCommerce product ([] for other posts or without WooCommerce).
	 *
	 * @param \WP_Post|object $post Post.
	 * @return string[]
	 */
	public static function product_lines( $post ) {
		if ( ! is_object( $post ) || ! isset( $post->post_type, $post->ID ) || 'product' !== $post->post_type || ! function_exists( 'wc_get_product' ) ) {
			return array();
		}
		$product = wc_get_product( (int) $post->ID );
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_price' ) ) {
			return array();
		}
		$lines = array();
		if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_price' ) ) {
			$min = $product->get_variation_price( 'min', true );
			$max = $product->get_variation_price( 'max', true );
			if ( '' !== (string) $min ) {
				$lines[] = 'Current price: ' . ( (string) $min === (string) $max || '' === (string) $max ? self::money( $min ) : self::money( $min ) . ' – ' . self::money( $max ) ) . ' (depends on the option chosen)';
			}
		} else {
			$price = function_exists( 'wc_get_price_to_display' ) ? wc_get_price_to_display( $product ) : $product->get_price();
			if ( '' !== (string) $product->get_price() ) {
				$line = 'Current price: ' . self::money( $price );
				if ( method_exists( $product, 'is_on_sale' ) && $product->is_on_sale() && method_exists( $product, 'get_regular_price' ) && '' !== (string) $product->get_regular_price() ) {
					$regular = function_exists( 'wc_get_price_to_display' ) ? wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) ) : $product->get_regular_price();
					$line   .= ' (on sale; regular price ' . self::money( $regular ) . ')';
				}
				$lines[] = $line;
			}
		}
		if ( method_exists( $product, 'get_stock_status' ) ) {
			$labels = array(
				'instock'     => 'in stock',
				'outofstock'  => 'out of stock',
				'onbackorder' => 'available on backorder',
			);
			$status = (string) $product->get_stock_status();
			if ( isset( $labels[ $status ] ) ) {
				$line     = 'Stock: ' . $labels[ $status ];
				$quantity = method_exists( $product, 'managing_stock' ) && $product->managing_stock() && method_exists( $product, 'get_stock_quantity' ) ? $product->get_stock_quantity() : null;
				if ( 'instock' === $status && null !== $quantity && (int) $quantity > 0 ) {
					$line .= ' (' . (int) $quantity . ' left)';
				}
				$lines[] = $line;
			}
		}
		return $lines;
	}

	/**
	 * An amount as plain text in the store's currency (৳30.00, $24.00).
	 *
	 * @param float|string $amount Amount.
	 * @return string
	 */
	public static function money( $amount ) {
		if ( function_exists( 'wc_price' ) ) {
			$text = html_entity_decode( wp_strip_all_tags( (string) wc_price( (float) $amount ) ), ENT_QUOTES, 'UTF-8' );
		} else {
			$symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( (string) get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '';
			$text   = $symbol . number_format( (float) $amount, 2, '.', '' );
		}
		return trim( (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', $text ) );
	}

	/**
	 * The public, published post a URL on this site points to.
	 *
	 * @param string $url URL.
	 * @return \WP_Post|null
	 */
	public static function post_for_url( $url ) {
		if ( '' === $url || ! function_exists( 'url_to_postid' ) || ! self::same_site( $url ) ) {
			return null;
		}
		$id   = (int) url_to_postid( $url );
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
			return null;
		}
		$type = get_post_type_object( $post->post_type );
		return ( $type && ! empty( $type->public ) ) ? $post : null;
	}

	/**
	 * Whether the visitor may read a post through the AI: the same access labels as the knowledge
	 * base (the item's stored labels and the kb_post_access filter), so the page the browser
	 * claims to be on never reveals what a knowledge search would hide.
	 *
	 * @param \WP_Post $post   Post.
	 * @param string[] $labels Visitor labels.
	 * @return bool
	 */
	public static function visible( $post, array $labels ) {
		$access = '\QuantumCloud\WPBotCore\Knowledge\Kb_Access';
		/** This filter is documented in WPBot Core src/Knowledge/Kb_Sources.php */
		$filtered = (array) apply_filters( 'qcld_wpbot_core/kb_post_access', array(), $post ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPBot Core's filter.
		$stored   = function_exists( 'qcld_wpbot_core_kb_access' ) ? qcld_wpbot_core_kb_access( 'post:' . $post->post_type, (string) $post->ID ) : null;
		if ( ! class_exists( $access ) ) {
			$open = array( 'public' );
			return array() === array_diff( $filtered, $open ) && ( null === $stored || array() === array_diff( (array) $stored, $open ) );
		}
		$ok = $access::allows( '', $access::encode( $filtered ), $labels ) && ( null === $stored || $access::allows( '', $access::encode( (array) $stored ), $labels ) );
		/**
		 * Whether the visitor may read this post through the AI (membership plugins can say no).
		 *
		 * @param bool     $ok     Default from the access labels.
		 * @param \WP_Post $post   Post.
		 * @param string[] $labels Visitor labels.
		 */
		return (bool) apply_filters( 'qcld_wpbot_chat/page_context_allowed', $ok, $post, $labels );
	}

	/**
	 * Text of a page the knowledge base holds although it is not a post on this site: a page of a
	 * site connected to this one (shared knowledge base). The text comes from the knowledge base,
	 * never from the browser, and only when the visitor's labels allow it. '' when unknown.
	 *
	 * @param string   $url    Page URL.
	 * @param string[] $labels Visitor labels.
	 * @return string
	 */
	public static function known_page( $url, array $labels ) {
		if ( '' === (string) $url || ! function_exists( 'qcld_wpbot_core_kb_find_url' ) ) {
			return '';
		}
		$item = qcld_wpbot_core_kb_find_url( (string) $url, $labels, self::MAX_CHARS * 2 );
		if ( ! is_array( $item ) || '' === trim( (string) $item['content'] ) ) {
			return '';
		}
		$parts = array(
			'The user is on this page: ' . wp_strip_all_tags( (string) $item['title'] ) . ' (' . esc_url_raw( (string) $item['url'] ) . ')',
			"Page text:\n" . self::cut( self::clean( (string) $item['content'] ), self::MAX_CHARS ),
		);
		return implode( "\n", $parts );
	}

	/**
	 * Whether a URL is on this site.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function same_site( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $host ) && is_string( $home ) && strtolower( $host ) === strtolower( $home );
	}

	/**
	 * Visible text only: no shortcodes, blocks' markup, scripts, styles or HTML.
	 *
	 * @param string $text Raw post text.
	 * @return string
	 */
	public static function clean( $text ) {
		$text = preg_replace( '#<(script|style|noscript|template)\b[^>]*>.*?</\1>#is', ' ', (string) $text );
		$text = preg_replace( '/<!--.*?-->/s', ' ', (string) $text );
		$text = function_exists( 'strip_shortcodes' ) ? strip_shortcodes( (string) $text ) : $text;
		$text = wp_strip_all_tags( (string) $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Cut text at a word boundary.
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	public static function cut( $text, $max ) {
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		if ( $length <= $max ) {
			return $text;
		}
		$cut   = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
		$space = strrpos( $cut, ' ' );
		return ( false !== $space && $space > $max * 0.6 ? substr( $cut, 0, $space ) : $cut ) . '…';
	}
}
