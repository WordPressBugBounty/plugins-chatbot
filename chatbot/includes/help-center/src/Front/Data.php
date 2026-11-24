<?php
/**
 * Queries for the Help Center pages: categories, articles, most read, recently
 * updated, breadcrumbs, previous and next, reading time and the table of contents.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Front;

use QuantumCloud\HelpCenter\Admin\Order;
use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only data for the templates.
 */
final class Data {

	/**
	 * Categories under a parent, in their order.
	 *
	 * @param int $parent_id Parent term id (0 = top level).
	 * @return \WP_Term[]
	 */
	public static function categories( $parent_id = 0 ) {
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomies::CATEGORY,
				'hide_empty' => false,
				'parent'     => (int) $parent_id,
			)
		);
		return is_array( $terms ) ? Order::sort_terms( $terms ) : array();
	}

	/**
	 * Knowledge bases, in their order.
	 *
	 * @return \WP_Term[]
	 */
	public static function knowledge_bases() {
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomies::KB,
				'hide_empty' => true,
			)
		);
		return is_array( $terms ) ? Order::sort_terms( $terms ) : array();
	}

	/**
	 * Published articles in a category (children included or not), in their order.
	 *
	 * @param int  $term_id  Category id.
	 * @param int  $limit    Most articles (-1 = all).
	 * @param bool $children Include child categories.
	 * @return \WP_Post[]
	 */
	public static function articles_in( $term_id, $limit = -1, $children = true ) {
		return get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $limit,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Articles by category.
					array(
						'taxonomy'         => Taxonomies::CATEGORY,
						'terms'            => array( (int) $term_id ),
						'include_children' => (bool) $children,
					),
				),
			)
		);
	}

	/**
	 * Published articles in a category, counted with its children.
	 *
	 * @param \WP_Term $term Category.
	 * @return int
	 */
	public static function count_in( $term ) {
		return count(
			get_posts(
				array(
					'post_type'      => Post_Type::NAME,
					'post_status'    => 'publish',
					'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Ids only, for a count.
					'fields'         => 'ids',
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Articles by category.
						array(
							'taxonomy' => Taxonomies::CATEGORY,
							'terms'    => array( (int) $term->term_id ),
						),
					),
				)
			)
		);
	}

	/**
	 * Most read articles (only those with views).
	 *
	 * @param int $limit Most articles.
	 * @return \WP_Post[]
	 */
	public static function most_read( $limit = 5 ) {
		return get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $limit,
				'meta_key'       => '_wpbot_views', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sorted by views.
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Only articles with views.
					array(
						'key'     => '_wpbot_views',
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
	}

	/**
	 * Recently updated articles.
	 *
	 * @param int $limit Most articles.
	 * @return \WP_Post[]
	 */
	public static function recent( $limit = 5 ) {
		return get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $limit,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * The article's main category (the first, deepest one).
	 *
	 * @param int $post_id Article id.
	 * @return \WP_Term|null
	 */
	public static function main_category( $post_id ) {
		$terms = get_the_terms( $post_id, Taxonomies::CATEGORY );
		if ( ! is_array( $terms ) || array() === $terms ) {
			return null;
		}
		usort(
			$terms,
			static function ( $a, $b ) {
				return count( get_ancestors( $b->term_id, Taxonomies::CATEGORY, 'taxonomy' ) ) <=> count( get_ancestors( $a->term_id, Taxonomies::CATEGORY, 'taxonomy' ) );
			}
		);
		return $terms[0];
	}

	/**
	 * Breadcrumb trail: [ [ label, url ], … ], the current page last with an empty url.
	 *
	 * @param \WP_Post|\WP_Term|null $current Current article or term (null = home).
	 * @return array[]
	 */
	public static function breadcrumbs( $current ) {
		$trail = array( array( __( 'Help Center', 'chatbot' ), Urls::home() ) );
		if ( $current instanceof \WP_Post ) {
			$term = self::main_category( $current->ID );
			if ( $term ) {
				foreach ( self::term_path( $term ) as $t ) {
					$trail[] = array( $t->name, (string) get_term_link( $t ) );
				}
			}
			foreach ( array_reverse( get_post_ancestors( $current ) ) as $ancestor ) {
				$trail[] = array( get_the_title( $ancestor ), (string) get_permalink( $ancestor ) );
			}
			$trail[] = array( get_the_title( $current ), '' );
		} elseif ( $current instanceof \WP_Term ) {
			$path = self::term_path( $current );
			$last = array_pop( $path );
			foreach ( $path as $t ) {
				$trail[] = array( $t->name, (string) get_term_link( $t ) );
			}
			$trail[] = array( $last->name, '' );
		} else {
			$trail[0][1] = '';
		}//end if
		return $trail;
	}

	/**
	 * A term and its ancestors, top first.
	 *
	 * @param \WP_Term $term Term.
	 * @return \WP_Term[]
	 */
	public static function term_path( $term ) {
		$path = array();
		foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $id ) {
			$t = get_term( $id, $term->taxonomy );
			if ( $t instanceof \WP_Term ) {
				$path[] = $t;
			}
		}
		$path[] = $term;
		return $path;
	}

	/**
	 * Previous and next article in the same category.
	 *
	 * @param \WP_Post $post Article.
	 * @return array{0:\WP_Post|null,1:\WP_Post|null}
	 */
	public static function prev_next( $post ) {
		$term = self::main_category( $post->ID );
		if ( ! $term ) {
			return array( null, null );
		}
		$list = self::articles_in( $term->term_id, 200, false );
		$ids  = wp_list_pluck( $list, 'ID' );
		$at   = array_search( $post->ID, $ids, true );
		if ( false === $at ) {
			return array( null, null );
		}
		return array( $at > 0 ? $list[ $at - 1 ] : null, isset( $list[ $at + 1 ] ) ? $list[ $at + 1 ] : null );
	}

	/**
	 * Minutes to read (220 words a minute, at least 1).
	 *
	 * @param string $content Content.
	 * @return int
	 */
	public static function reading_minutes( $content ) {
		$words = str_word_count( wp_strip_all_tags( (string) $content ) );
		return max( 1, (int) ceil( $words / 220 ) );
	}

	/**
	 * Table of contents from rendered content: h2 and h3 with ids.
	 *
	 * @param string $html Rendered content.
	 * @return array[] Each: id, text, level.
	 */
	public static function toc( $html ) {
		$out = array();
		if ( preg_match_all( '#<h([23])\b[^>]*\bid="([^"]+)"[^>]*>(.*?)</h\1>#is', (string) $html, $found, PREG_SET_ORDER ) ) {
			foreach ( $found as $m ) {
				$text = trim( html_entity_decode( wp_strip_all_tags( $m[3] ), ENT_QUOTES, 'UTF-8' ) );
				if ( '' !== $text ) {
					$out[] = array(
						'id'    => $m[2],
						'text'  => $text,
						'level' => (int) $m[1],
					);
				}
			}
		}
		return $out;
	}
}
