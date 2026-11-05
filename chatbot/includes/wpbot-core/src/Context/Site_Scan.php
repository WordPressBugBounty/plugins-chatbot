<?php
/**
 * Site Context, layer 1: what the site itself says about the business.
 * Reads the site name and tagline, menus, the home page, the most important
 * pages and posts, categories and WooCommerce products (best sellers first).
 * Database reads only: no AI, no outside request, nothing leaves the site.
 * Sizes are capped so the scan stays fast on shared hosting.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Site scan.
 */
final class Site_Scan {

	const PAGES    = 40;
	const PRODUCTS = 30;
	const SNIPPET  = 220;

	/**
	 * Scan the site.
	 *
	 * @return array site (name, tagline, url), menus, home, pages, categories, products, hash, counts.
	 */
	public static function run() {
		$out           = array(
			'site'       => array(
				'name'    => wp_strip_all_tags( (string) get_bloginfo( 'name' ) ),
				'tagline' => wp_strip_all_tags( (string) get_bloginfo( 'description' ) ),
				'url'     => home_url( '/' ),
			),
			'menus'      => self::menus(),
			'home'       => self::home(),
			'pages'      => self::pages(),
			'categories' => self::categories(),
			'products'   => self::products(),
		);
		$out['counts'] = array(
			'pages'      => count( $out['pages'] ),
			'products'   => count( $out['products'] ),
			'categories' => count( $out['categories'] ),
			'menus'      => count( $out['menus'] ),
		);
		// Changes in titles, menus or products mark the summary as out of date.
		$out['hash'] = md5(
			(string) wp_json_encode(
				array(
					$out['site'],
					$out['menus'],
					wp_list_pluck( $out['pages'], 'title' ),
					wp_list_pluck( $out['products'], 'title' ),
					wp_list_pluck( $out['categories'], 'name' ),
				)
			)
		);
		return $out;
	}

	/**
	 * Titles of the menus shown on the site (up to 40 items).
	 *
	 * @return string[]
	 */
	private static function menus() {
		$titles = array();
		foreach ( array_unique( array_filter( (array) get_nav_menu_locations() ) ) as $menu_id ) {
			foreach ( (array) wp_get_nav_menu_items( (int) $menu_id ) as $item ) {
				$title = is_object( $item ) ? trim( wp_strip_all_tags( (string) $item->title ) ) : '';
				if ( '' !== $title && ! in_array( $title, $titles, true ) ) {
					$titles[] = $title;
				}
				if ( count( $titles ) >= 40 ) {
					break 2;
				}
			}
		}
		return $titles;
	}

	/**
	 * The home page, when it is a page.
	 *
	 * @return array title, text ('' when the home page lists posts).
	 */
	private static function home() {
		$id = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		if ( ! $id || 'publish' !== get_post_status( $id ) ) {
			return array(
				'title' => '',
				'text'  => '',
			);
		}
		return array(
			'title' => wp_strip_all_tags( get_the_title( $id ) ),
			'text'  => self::text( $id, 600 ),
		);
	}

	/**
	 * The most important pages and posts: ones a module ranks first
	 * (SEO adds those with the most clicks), then menu links, then pages,
	 * then recent posts.
	 *
	 * @return array[] id, type, title, url, text.
	 */
	private static function pages() {
		/**
		 * Post ids that matter most, best first (for example by search clicks).
		 *
		 * @param int[] $ids Post ids.
		 */
		$ids = array_map( 'absint', (array) apply_filters( 'qcld_wpbot_core/context_important_posts', array() ) );
		foreach ( array_filter( (array) get_nav_menu_locations() ) as $menu_id ) {
			foreach ( (array) wp_get_nav_menu_items( (int) $menu_id ) as $item ) {
				if ( is_object( $item ) && 'post_type' === $item->type ) {
					$ids[] = (int) $item->object_id;
				}
			}
		}
		$query = array(
			'post_status'      => 'publish',
			'has_password'     => false,
			'fields'           => 'ids',
			'suppress_filters' => false,
			'no_found_rows'    => true,
		);
		$ids   = array_merge(
			$ids,
			get_posts(
				$query + array(
					'post_type'      => 'page',
					'posts_per_page' => self::PAGES,
					'orderby'        => 'menu_order modified',
					'order'          => 'ASC',
				)
			),
			get_posts(
				$query + array(
					'post_type'      => 'post',
					'posts_per_page' => 15,
					'orderby'        => 'modified',
					'order'          => 'DESC',
				)
			)
		);
		$out   = array();
		$front = (int) get_option( 'page_on_front' );
		foreach ( array_unique( array_filter( $ids ) ) as $id ) {
			$post = get_post( $id );
			if ( ! $post || 'publish' !== $post->post_status || '' !== $post->post_password || $id === $front || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
				continue;
			}
			$out[] = array(
				'id'    => (int) $id,
				'type'  => $post->post_type,
				'title' => wp_strip_all_tags( get_the_title( $id ) ),
				'url'   => (string) get_permalink( $id ),
				'text'  => self::text( $id, self::SNIPPET ),
			);
			if ( count( $out ) >= self::PAGES ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * The biggest post and product categories (up to 20).
	 *
	 * @return array[] name, count, taxonomy.
	 */
	private static function categories() {
		$out = array();
		foreach ( array( 'product_cat', 'category' ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
					'orderby'    => 'count',
					'order'      => 'DESC',
					'number'     => 12,
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				if ( 'uncategorized' === $term->slug ) {
					continue;
				}
				$out[] = array(
					'name'     => wp_strip_all_tags( $term->name ),
					'count'    => (int) $term->count,
					'taxonomy' => $taxonomy,
				);
			}
		}
		return array_slice( $out, 0, 20 );
	}

	/**
	 * WooCommerce products, best sellers first.
	 *
	 * @return array[] id, title, price, text, sales.
	 */
	private static function products() {
		if ( ! post_type_exists( 'product' ) ) {
			return array();
		}
		$ids = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => 'publish',
				'has_password'     => false,
				'posts_per_page'   => self::PRODUCTS,
				'fields'           => 'ids',
				'meta_key'         => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One capped read.
				'orderby'          => array(
					'meta_value_num' => 'DESC',
					'date'           => 'DESC',
				),
				'suppress_filters' => false,
				'no_found_rows'    => true,
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$post  = get_post( $id );
			$short = $post ? wp_strip_all_tags( strip_shortcodes( (string) $post->post_excerpt ) ) : '';
			$out[] = array(
				'id'    => (int) $id,
				'title' => wp_strip_all_tags( get_the_title( $id ) ),
				'price' => (string) get_post_meta( $id, '_price', true ),
				'text'  => '' !== trim( $short ) ? self::cut( $short, 160 ) : self::text( $id, 160 ),
				'sales' => (int) get_post_meta( $id, 'total_sales', true ),
			);
		}
		return $out;
	}

	/**
	 * Plain text of a post, shortened.
	 *
	 * @param int $id  Post id.
	 * @param int $max Characters.
	 * @return string
	 */
	private static function text( $id, $max ) {
		$post = get_post( $id );
		if ( ! $post ) {
			return '';
		}
		// No the_content filters: page builders and shortcodes can be slow or print output.
		$text = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
		return self::cut( $text, $max );
	}

	/**
	 * Collapse whitespace and cut at a word boundary.
	 *
	 * @param string $text Text.
	 * @param int    $max  Characters.
	 * @return string
	 */
	public static function cut( $text, $max ) {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) ) );
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $text ) <= $max : strlen( $text ) <= $max ) {
			return $text;
		}
		$cut   = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
		$space = strrpos( $cut, ' ' );
		return rtrim( false !== $space && $space > $max / 2 ? substr( $cut, 0, $space ) : $cut, ' ,.;:-' ) . '…';
	}
}
