<?php
/**
 * Topic source: the shop and the site's own traffic. Best-selling
 * WooCommerce products (buying guides, how-to-use posts) and the top
 * landing pages recorded by Site Insight (more on what already draws
 * visitors).
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Topics\Sources;

use QuantumCloud\AiWriter\Improve\Site_Insight_Source;

defined( 'ABSPATH' ) || exit;

/**
 * Store and Site Insight source.
 */
final class Store_Source {

	/**
	 * Whether WooCommerce or Site Insight is active.
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( 'WooCommerce' ) || Site_Insight_Source::available();
	}

	/**
	 * Connection state.
	 *
	 * @return array
	 */
	public static function status() {
		return self::available()
			? array(
				'status'  => 'connected',
				'message' => '',
			)
			: array(
				'status'  => 'not_available',
				'message' => __( 'Needs WooCommerce or Site Insight on this site.', 'chatbot' ),
			);
	}

	/**
	 * Ideas from best sellers and top landing pages.
	 *
	 * @param array $context Context.
	 * @return array[]
	 */
	public static function ideas( array $context ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Source callback signature.
		$ideas = array();
		if ( class_exists( 'WooCommerce' ) ) {
			$products = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 8,
					'meta_key'       => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- WooCommerce's own sales counter; eight rows.
					'orderby'        => 'meta_value_num',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				)
			);
			foreach ( $products as $product ) {
				$sold = (int) get_post_meta( $product->ID, 'total_sales', true );
				if ( $sold < 1 ) {
					continue;
				}
				$name    = html_entity_decode( get_the_title( $product ), ENT_QUOTES, 'UTF-8' );
				$ideas[] = array(
					/* translators: %s: product name. */
					'title'      => sprintf( __( 'How to get the most from %s', 'chatbot' ), $name ),
					'ref'        => 'product-' . $product->ID,
					/* translators: %d: units sold. */
					'reason'     => sprintf( __( 'One of your best sellers (%d sold). A guide helps buyers choose and use it.', 'chatbot' ), $sold ),
					'trend'      => 'steady',
					'rank_score' => min( 50, $sold ),
					'link'       => (string) get_permalink( $product ),
					'metrics'    => array( 'sold' => $sold ),
				);
			}
		}
		if ( Site_Insight_Source::available() ) {
			$class = Site_Insight_Source::REPOSITORY;
			$repo  = new $class();
			foreach ( array_slice( (array) $repo->top_pages( gmdate( 'Y-m-d H:i:s', time() - 28 * DAY_IN_SECONDS ), 20 ), 0, 20 ) as $page ) {
				$page    = (array) $page;
				$post_id = isset( $page['url'] ) ? url_to_postid( (string) $page['url'] ) : 0;
				$views   = isset( $page['views'] ) ? (int) $page['views'] : 0;
				if ( ! $post_id || $views < 5 || count( $ideas ) >= 16 ) {
					continue;
				}
				$title   = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
				$ideas[] = array(
					/* translators: %s: page title. */
					'title'      => sprintf( __( 'More on: %s', 'chatbot' ), $title ),
					'ref'        => 'page-' . $post_id,
					/* translators: %d: visits. */
					'reason'     => sprintf( __( 'A top landing page (%d visits in four weeks). A follow-up post can keep these visitors reading.', 'chatbot' ), $views ),
					'trend'      => 'steady',
					'rank_score' => min( 50, $views / 5 ),
					'link'       => (string) get_permalink( $post_id ),
					'metrics'    => array( 'visits' => $views ),
				);
			}
		}
		return $ideas;
	}
}
