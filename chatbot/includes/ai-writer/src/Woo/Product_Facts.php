<?php
/**
 * The facts AI Writer may use about a product: name, type, prices, SKU,
 * categories, tags, attributes, variations, size, stock and the owner's
 * notes. Product writing is limited to these facts (the facts rule).
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Woo;

use QuantumCloud\AiWriter\Writing\Prompts;

defined( 'ABSPATH' ) || exit;

/**
 * Product facts.
 */
final class Product_Facts {

	/**
	 * Facts of a product as structured data, or null.
	 *
	 * @param int $product_id Product id.
	 * @return array|null
	 */
	public static function get( $product_id ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			return null;
		}
		$facts = array(
			'id'          => (int) $product->get_id(),
			'name'        => $product->get_name(),
			'type'        => $product->get_type(),
			'url'         => (string) get_permalink( $product->get_id() ),
			'sku'         => (string) $product->get_sku(),
			'price'       => self::price( $product ),
			'stock'       => (string) $product->get_stock_status(),
			'categories'  => self::terms( $product->get_id(), 'product_cat' ),
			'tags'        => self::terms( $product->get_id(), 'product_tag' ),
			'attributes'  => array(),
			'variations'  => array(),
			'weight'      => (string) $product->get_weight(),
			'dimensions'  => function_exists( 'wc_format_dimensions' ) ? wp_strip_all_tags( wc_format_dimensions( $product->get_dimensions( false ) ) ) : '',
			'description' => Prompts::clip( wp_strip_all_tags( (string) $product->get_description() ), 2500 ),
			'short'       => Prompts::clip( wp_strip_all_tags( (string) $product->get_short_description() ), 800 ),
		);
		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_name' ) ) {
				continue;
			}
			$label = wc_attribute_label( $attribute->get_name(), $product );
			if ( $attribute->is_taxonomy() ) {
				$values = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
			} else {
				$values = $attribute->get_options();
			}
			$facts['attributes'][ $label ] = array_values( array_map( 'strval', (array) $values ) );
		}
		if ( $product->is_type( 'variable' ) ) {
			foreach ( array_slice( $product->get_children(), 0, 40 ) as $child_id ) {
				$variation = wc_get_product( $child_id );
				if ( ! $variation ) {
					continue;
				}
				$facts['variations'][] = array(
					'id'          => (int) $child_id,
					'attributes'  => wc_get_formatted_variation( $variation, true, false, false ),
					'price'       => self::price( $variation ),
					'sku'         => (string) $variation->get_sku(),
					'stock'       => (string) $variation->get_stock_status(),
					'description' => wp_strip_all_tags( (string) $variation->get_description() ),
				);
			}
		}
		/**
		 * Add or change the facts AI Writer may use about a product.
		 *
		 * @param array       $facts   Facts.
		 * @param \WC_Product $product Product.
		 */
		return (array) apply_filters( 'qcld_wpbot_writer/product_facts', $facts, $product );
	}

	/**
	 * Facts as text for prompts (and for the claim check).
	 *
	 * @param array  $facts Facts from get().
	 * @param string $notes Owner's notes.
	 * @return string
	 */
	public static function text( array $facts, $notes = '' ) {
		$lines = array(
			'Product: ' . $facts['name'],
			'URL: ' . $facts['url'],
		);
		foreach ( array(
			'type'       => 'Type',
			'price'      => 'Price',
			'sku'        => 'SKU',
			'stock'      => 'Stock status',
			'weight'     => 'Weight',
			'dimensions' => 'Dimensions',
		) as $key => $label ) {
			if ( '' !== (string) $facts[ $key ] ) {
				$lines[] = $label . ': ' . $facts[ $key ];
			}
		}
		if ( $facts['categories'] ) {
			$lines[] = 'Categories: ' . implode( ', ', $facts['categories'] );
		}
		if ( $facts['tags'] ) {
			$lines[] = 'Tags: ' . implode( ', ', $facts['tags'] );
		}
		foreach ( $facts['attributes'] as $name => $values ) {
			$lines[] = $name . ': ' . implode( ', ', $values );
		}
		foreach ( $facts['variations'] as $variation ) {
			$lines[] = 'Variation: ' . $variation['attributes'] . ( '' !== $variation['price'] ? ' — ' . $variation['price'] : '' ) . ( 'outofstock' === $variation['stock'] ? ' (out of stock)' : '' );
		}
		if ( '' !== $facts['short'] ) {
			$lines[] = 'Current short description: ' . $facts['short'];
		}
		if ( '' !== $facts['description'] ) {
			$lines[] = 'Current description: ' . $facts['description'];
		}
		$notes = trim( (string) $notes );
		if ( '' !== $notes ) {
			$lines[] = "Owner's notes (true): " . $notes;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Price as plain text (with sale price).
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	private static function price( $product ) {
		$html = $product->get_price_html();
		return trim( html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Term names of a product.
	 *
	 * @param int    $product_id Product.
	 * @param string $taxonomy   Taxonomy.
	 * @return string[]
	 */
	private static function terms( $product_id, $taxonomy ) {
		$terms = get_the_terms( $product_id, $taxonomy );
		return is_array( $terms ) ? array_values( wp_list_pluck( $terms, 'name' ) ) : array();
	}
}
