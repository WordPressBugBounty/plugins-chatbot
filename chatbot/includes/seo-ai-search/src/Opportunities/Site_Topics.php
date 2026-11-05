<?php
/**
 * A first Topic Map from the site's own structure (round 2, R3): each post
 * category and WooCommerce product category with two or more items becomes
 * a topic, and its pages join it. No AI and no cost. Pages that already have
 * a topic, and the owner's own (manual or locked) entries, are never moved;
 * a page with no main search yet gets its title as a placeholder (source
 * "title", the weakest, replaced by Search Console or the owner later).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Opportunities;

use QuantumCloud\WPBotCore\Map\Keyword_Map;
use QuantumCloud\WPBotCore\Map\Topic_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Topics from categories.
 */
final class Site_Topics {

	const MAX_PAGES = 1000;

	/**
	 * Build the topics.
	 *
	 * @return array topics, pages (placed).
	 */
	public static function build() {
		if ( ! class_exists( '\QuantumCloud\WPBotCore\Map\Topic_Map' ) ) {
			return array(
				'topics' => 0,
				'pages'  => 0,
			);
		}
		$topics = 0;
		$placed = 0;
		$taxes  = array_filter(
			array(
				'category'    => 'post',
				'product_cat' => 'product',
			),
			static function ( $type, $tax ) {
				return taxonomy_exists( $tax ) && post_type_exists( $type );
			},
			ARRAY_FILTER_USE_BOTH
		);
		foreach ( $taxes as $tax => $type ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $tax,
					'hide_empty' => true,
					'number'     => 100,
					'orderby'    => 'count',
					'order'      => 'DESC',
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				if ( (int) $term->count < 2 || in_array( $term->slug, array( 'uncategorized', 'uncategorised' ), true ) ) {
					continue;
				}
				$topic_id = Topic_Map::ensure( html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ), 0, 'site' );
				if ( ! $topic_id ) {
					continue;
				}
				++$topics;
				$posts = get_posts(
					array(
						'post_type'      => $type,
						'post_status'    => 'publish',
						'has_password'   => false,
						'posts_per_page' => 100,
						'fields'         => 'ids',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One term at a time; bounded.
						'tax_query'      => array(
							array(
								'taxonomy'         => $tax,
								'terms'            => (int) $term->term_id,
								'include_children' => false,
							),
						),
					)
				);
				foreach ( $posts as $post_id ) {
					if ( $placed >= self::MAX_PAGES ) {
						break 3;
					}
					if ( self::place( (int) $post_id, $topic_id ) ) {
						++$placed;
					}
				}
			}
		}
		return array(
			'topics' => $topics,
			'pages'  => $placed,
		);
	}

	/**
	 * Put one page in a topic, unless it already has one or the owner chose.
	 *
	 * @param int $post_id  Post.
	 * @param int $topic_id Topic.
	 * @return bool
	 */
	private static function place( $post_id, $topic_id ) {
		$main = Keyword_Map::main_query( $post_id );
		if ( $main ) {
			if ( ! empty( $main['topic_id'] ) || ! empty( $main['locked'] ) || 'manual' === $main['source'] ) {
				return false;
			}
			return Keyword_Map::set_topic( $post_id, $topic_id );
		}
		return Keyword_Map::set_post(
			$post_id,
			wp_strip_all_tags( get_the_title( $post_id ) ),
			array(
				'source'   => 'title',
				'topic_id' => $topic_id,
				'module'   => 'seo',
			)
		);
	}
}
