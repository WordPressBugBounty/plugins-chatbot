<?php
/**
 * WooCommerce product data for the knowledge base: one text builder for every path.
 *
 * The AI Setup Wizard and the knowledge base's own embed buttons (Qcld_Bot_Rag) and WPBot Core's
 * "Sync now" / Auto Sync (filter qcld_wpbot_core/kb_post_text) all add the same lines:
 * price in the store currency (sale and regular price, price range of variable products),
 * stock status, SKU and, where the post has no text of its own, the short description.
 * Before, only the wizard added a price, so after a product was saved (Auto Sync) or synced
 * the knowledge base lost the price.
 *
 * @package Chatbot
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Qcld_Kb_Product_Text' ) ) {

	/**
	 * Product lines for the knowledge base.
	 */
	class Qcld_Kb_Product_Text {

		/**
		 * Hook into WPBot Core's knowledge base.
		 *
		 * @return void
		 */
		public static function init() {
			// Late, so a product builder added by another WPBot plugin runs first and is not repeated.
			add_filter( 'qcld_wpbot_core/kb_post_text', array( __CLASS__, 'filter_post_text' ), 20, 2 );
		}

		/**
		 * Product lines for WPBot Core's knowledge base item of a product.
		 *
		 * WPBot Core already puts the short description (excerpt) before the text, so it is not added again.
		 *
		 * @param string       $text Text of the post.
		 * @param WP_Post|null $post Post.
		 * @return string
		 */
		public static function filter_post_text( $text, $post = null ) {
			$text = (string) $text;
			if ( ! is_object( $post ) || ! isset( $post->post_type ) || 'product' !== $post->post_type ) {
				return $text;
			}
			// WPBot Pro's own product builder (kb-pro) adds the same data: never twice.
			if ( class_exists( 'Qcld_Rag_Product_Context' ) || self::has_product_lines( $text ) ) {
				return $text;
			}
			/**
			 * Whether WPBot Free adds product data (price, stock, SKU) to the knowledge base.
			 *
			 * @param bool    $on   On.
			 * @param WP_Post $post Product post.
			 */
			if ( ! apply_filters( 'qcld_wpbot_free_kb_product_text', true, $post ) ) {
				return $text;
			}
			$lines = self::lines( (int) $post->ID, array( 'description' => false ) );
			if ( '' === $lines ) {
				return $text;
			}
			return '' === trim( $text ) ? $lines : rtrim( $text ) . "\n\n" . $lines;
		}

		/**
		 * True when the text already has product lines (another builder ran).
		 *
		 * @param string $text Text.
		 * @return bool
		 */
		public static function has_product_lines( $text ) {
			return 1 === preg_match( '/^(Price|Price range|Regular price|Sale price):/mi', (string) $text );
		}

		/**
		 * Product lines: price, stock, SKU, optional short description and link.
		 *
		 * @param int|object $product Product id or WC_Product.
		 * @param array      $args    description (bool, add the short description; default true),
		 *                            content (string, the post's own text: the description is only added when
		 *                            it is empty), link (bool, add the product link and id; default false).
		 * @return string Lines separated by "\n" ('' when WooCommerce is not active or the product is gone).
		 */
		public static function lines( $product, array $args = array() ) {
			$args = array_merge(
				array(
					'description' => true,
					'content'     => '',
					'link'        => false,
				),
				$args
			);
			if ( ! is_object( $product ) ) {
				if ( ! function_exists( 'wc_get_product' ) ) {
					return '';
				}
				$product = wc_get_product( (int) $product );
			}
			if ( ! is_object( $product ) || ! method_exists( $product, 'get_price' ) ) {
				return '';
			}
			$lines = self::price_lines( $product );
			$stock = self::stock_line( $product );
			if ( '' !== $stock ) {
				$lines[] = $stock;
			}
			$sku = method_exists( $product, 'get_sku' ) ? trim( (string) $product->get_sku() ) : '';
			if ( '' !== $sku ) {
				$lines[] = 'SKU: ' . $sku;
			}
			if ( $args['description'] && '' === trim( (string) $args['content'] ) && method_exists( $product, 'get_short_description' ) ) {
				$short = trim( self::plain( (string) $product->get_short_description() ) );
				if ( '' !== $short ) {
					$lines[] = 'Description: ' . $short;
				}
			}
			if ( $args['link'] && method_exists( $product, 'get_id' ) ) {
				$link = function_exists( 'get_permalink' ) ? (string) get_permalink( $product->get_id() ) : '';
				if ( '' !== $link ) {
					$lines[] = 'Product Link: ' . $link;
				}
				$lines[] = 'Product ID: ' . (int) $product->get_id();
			}
			/**
			 * Product lines in the knowledge base.
			 *
			 * @param string[] $lines   Lines.
			 * @param object   $product WC_Product.
			 */
			$lines = (array) apply_filters( 'qcld_wpbot_free_kb_product_lines', $lines, $product );
			return implode( "\n", array_filter( array_map( 'strval', $lines ), 'strlen' ) );
		}

		/**
		 * Price lines: "Price: ৳32", a sale ("Price: ৳25 (on sale, regular price ৳32)") or the range of a
		 * variable product ("Price range: ৳10 – ৳20").
		 *
		 * @param object $product WC_Product.
		 * @return string[]
		 */
		public static function price_lines( $product ) {
			$is_variable = method_exists( $product, 'is_type' ) && $product->is_type( 'variable' ) && method_exists( $product, 'get_variation_price' );
			if ( $is_variable ) {
				$min = $product->get_variation_price( 'min', true );
				$max = $product->get_variation_price( 'max', true );
				if ( '' === (string) $min && '' === (string) $max ) {
					return array();
				}
				if ( (string) $min === (string) $max || '' === (string) $max ) {
					$line    = 'Price: ' . self::format_price( $min );
					$reg_min = method_exists( $product, 'get_variation_regular_price' ) ? $product->get_variation_regular_price( 'min', true ) : '';
					$on_sale = method_exists( $product, 'is_on_sale' ) && $product->is_on_sale();
					if ( $on_sale && '' !== (string) $reg_min && (float) $reg_min > (float) $min ) {
						$line .= ' (on sale, regular price ' . self::format_price( $reg_min ) . ')';
					}
					return array( $line );
				}
				$line = 'Price range: ' . self::format_price( $min ) . ' – ' . self::format_price( $max );
				if ( method_exists( $product, 'is_on_sale' ) && $product->is_on_sale() ) {
					$line .= ' (some options on sale)';
				}
				return array( $line );
			}

			$price = (string) $product->get_price();
			if ( '' === $price ) {
				// Grouped products and the like: WooCommerce's own price text, when it has one.
				$html = method_exists( $product, 'get_price_html' ) ? trim( self::plain( (string) $product->get_price_html() ) ) : '';
				return '' !== $html ? array( 'Price: ' . $html ) : array();
			}
			$line    = 'Price: ' . self::format_price( self::display_price( $product, $price ) );
			$regular = method_exists( $product, 'get_regular_price' ) ? (string) $product->get_regular_price() : '';
			$on_sale = method_exists( $product, 'is_on_sale' ) && $product->is_on_sale();
			if ( $on_sale && '' !== $regular && (float) $regular > (float) $price ) {
				$line .= ' (on sale, regular price ' . self::format_price( self::display_price( $product, $regular ) ) . ')';
			}
			return array( $line );
		}

		/**
		 * Stock line: "Stock: In stock (12 available)", "Stock: Out of stock", "Stock: Available on backorder".
		 *
		 * @param object $product WC_Product.
		 * @return string
		 */
		public static function stock_line( $product ) {
			if ( ! method_exists( $product, 'get_stock_status' ) ) {
				return '';
			}
			$status = (string) $product->get_stock_status();
			$labels = array(
				'instock'     => 'In stock',
				'outofstock'  => 'Out of stock',
				'onbackorder' => 'Available on backorder',
			);
			if ( ! isset( $labels[ $status ] ) ) {
				return '';
			}
			$line = 'Stock: ' . $labels[ $status ];
			if ( 'instock' === $status && method_exists( $product, 'managing_stock' ) && $product->managing_stock() && method_exists( $product, 'get_stock_quantity' ) ) {
				$quantity = $product->get_stock_quantity();
				if ( null !== $quantity && (int) $quantity > 0 ) {
					$line .= ' (' . (int) $quantity . ' available)';
				}
			}
			return $line;
		}

		/**
		 * A price as the store shows it: currency symbol and format from WooCommerce, as plain text
		 * (entities decoded: "&#2547;32" becomes "৳32").
		 *
		 * @param mixed $amount Amount.
		 * @return string
		 */
		public static function format_price( $amount ) {
			if ( '' === (string) $amount ) {
				return '';
			}
			if ( function_exists( 'wc_price' ) ) {
				$text = self::plain( (string) wc_price( (float) $amount ) );
			} else {
				$symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? (string) get_woocommerce_currency_symbol() : '';
				$text   = self::plain( $symbol ) . (string) $amount;
			}
			return trim( $text );
		}

		/**
		 * Price with or without tax, as the shop shows it.
		 *
		 * @param object $product WC_Product.
		 * @param string $price   Price.
		 * @return string|float
		 */
		private static function display_price( $product, $price ) {
			if ( function_exists( 'wc_get_price_to_display' ) ) {
				return wc_get_price_to_display( $product, array( 'price' => $price ) );
			}
			return $price;
		}

		/**
		 * HTML to plain text with entities decoded and non-breaking spaces as spaces.
		 *
		 * @param string $html HTML.
		 * @return string
		 */
		private static function plain( $html ) {
			$text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $html ) : strip_tags( $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Fallback without WordPress (tests).
			// Up to three rounds: prices can arrive encoded more than once (&amp;#2547;).
			$rounds = 0;
			while ( $rounds < 3 && false !== strpos( $text, '&' ) ) {
				$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				++$rounds;
			}
			$text = str_replace( "\xC2\xA0", ' ', $text );
			return trim( (string) preg_replace( '/[ \t]+/', ' ', $text ) );
		}
	}

	Qcld_Kb_Product_Text::init();
}
