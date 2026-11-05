<?php
/**
 * Builds llms.txt from what is on the site now (round 2, R4), in the
 * llms.txt format: an H1 with the site name, a blockquote summary, optional
 * notes, then H2 sections of "- [name](url): notes" lists, with secondary
 * links in a final "Optional" section.
 *
 * Sections: pillar pages, then each Topic Map topic's pages; key pages
 * (front page, menu pages, pinned pages); WooCommerce products (best
 * sellers first); then Optional (other pages and posts). Every page passes
 * Llms_Rules first; the owner can pin pages in or keep them out, and set the
 * most links (default 100).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Llms;

use QuantumCloud\SeoAiSearch\Local\Locations;
use QuantumCloud\SeoAiSearch\Opportunities\Page_Importance;
use QuantumCloud\SeoAiSearch\Seo\Seo_Bridge;
use QuantumCloud\SeoAiSearch\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Builder of llms.txt.
 */
final class Llms_Builder {

	/**
	 * Build the file.
	 *
	 * @return array text, ids (listed post ids), links (url => section), left_out (post_id => reason), count.
	 */
	public static function build() {
		Llms_Rules::reset();
		$max      = max( 5, min( 1000, (int) Settings::get( 'llms_max' ) ) );
		$excluded = array_map( 'intval', (array) Settings::get( 'llms_excluded' ) );
		$pinned   = array_map( 'intval', (array) Settings::get( 'llms_pinned' ) );
		$used     = array();
		$left_out = array();
		$sections = array();
		$count    = 0;

		$take = static function ( $post_id ) use ( &$used, &$left_out, &$count, $excluded, $max ) {
			$post_id = (int) $post_id;
			if ( isset( $used[ $post_id ] ) || isset( $left_out[ $post_id ] ) || $count >= $max ) {
				return false;
			}
			if ( in_array( $post_id, $excluded, true ) ) {
				$left_out[ $post_id ] = __( 'You keep it out.', 'chatbot' );
				return false;
			}
			$why = Llms_Rules::refusal( $post_id );
			if ( '' !== $why ) {
				$left_out[ $post_id ] = $why;
				return false;
			}
			$used[ $post_id ] = true;
			++$count;
			return true;
		};

		// Key pages: front page, pinned, menu.
		$key   = array();
		$front = (int) get_option( 'page_on_front' );
		foreach ( array_merge( $front ? array( $front ) : array(), $pinned, Page_Importance::menu_posts() ) as $post_id ) {
			if ( $take( $post_id ) ) {
				$key[] = $post_id;
			}
		}

		// Topic Map: pillar first, then the topic's pages.
		if ( class_exists( '\QuantumCloud\WPBotCore\Map\Topic_Map' ) ) {
			foreach ( \QuantumCloud\WPBotCore\Map\Topic_Map::topics() as $topic ) {
				$ids = array();
				if ( ! empty( $topic['pillar_post_id'] ) && $take( $topic['pillar_post_id'] ) ) {
					$ids[] = (int) $topic['pillar_post_id'];
				}
				foreach ( \QuantumCloud\WPBotCore\Map\Topic_Map::pages( (int) $topic['id'] ) as $page ) {
					if ( ! empty( $page['post_id'] ) && $take( $page['post_id'] ) ) {
						$ids[] = (int) $page['post_id'];
					}
				}
				if ( array() !== $ids ) {
					$sections[ (string) $topic['name'] ] = $ids;
				}
			}
		}

		// Products, best sellers first.
		$products = array();
		if ( in_array( 'product', Llms_Rules::types(), true ) && post_type_exists( 'product' ) ) {
			foreach ( get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 50,
					'fields'         => 'ids',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sort by sales; bounded.
					'meta_key'       => 'total_sales',
					'orderby'        => 'meta_value_num',
					'order'          => 'DESC',
				)
			) as $post_id ) {
				if ( $take( $post_id ) ) {
					$products[] = (int) $post_id;
				}
			}
		}

		// Optional: other pages and posts, most recently updated first.
		$optional = array();
		foreach ( get_posts(
			array(
				'post_type'      => array_values( array_diff( Llms_Rules::types(), array( 'product' ) ) ),
				'post_status'    => 'publish',
				'has_password'   => false,
				'posts_per_page' => 300, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Ids only; bounded by the link limit.
				'fields'         => 'ids',
				'orderby'        => 'modified',
			)
		) as $post_id ) {
			if ( $take( $post_id ) ) {
				$optional[] = (int) $post_id;
			}
		}

		$lines   = array( '# ' . self::clean( wp_strip_all_tags( get_bloginfo( 'name' ) ) ), '' );
		$lines[] = '> ' . self::clean( self::summary() );
		$details = trim( (string) Settings::get( 'llms_details' ) );
		if ( '' !== $details ) {
			$lines[] = '';
			foreach ( preg_split( '/\r\n|\r|\n/', $details ) as $line ) {
				// Notes may not contain headings (format rule).
				$lines[] = ltrim( wp_strip_all_tags( $line ), '# ' );
			}
		}
		$links = array();
		$add   = static function ( $title, array $ids ) use ( &$lines, &$links ) {
			if ( array() === $ids ) {
				return;
			}
			$lines[] = '';
			$lines[] = '## ' . self::clean( $title );
			$lines[] = '';
			foreach ( $ids as $post_id ) {
				$url           = self::url( $post_id );
				$links[ $url ] = $title;
				$description   = self::description( $post_id );
				$lines[]       = '- [' . self::clean( get_the_title( $post_id ), true ) . '](' . $url . ')' . ( '' !== $description ? ': ' . $description : '' );
			}
		};
		$add( __( 'Key pages', 'chatbot' ), $key );
		foreach ( $sections as $title => $ids ) {
			$add( $title, $ids );
		}
		$add( __( 'Products', 'chatbot' ), $products );
		// The format's "Optional" section keeps its English name: agents look for it.
		$add( 'Optional', $optional );

		return array(
			'text'     => implode( "\n", $lines ) . "\n",
			'ids'      => array_keys( $used ),
			'links'    => $links,
			'left_out' => $left_out,
			'count'    => $count,
		);
	}

	/**
	 * The listed address of a page (Pro may swap in the target of a redirect it found).
	 *
	 * @param int $post_id Post.
	 * @return string
	 */
	public static function url( $post_id ) {
		/**
		 * Address listed for a page.
		 *
		 * @param string $url     Permalink.
		 * @param int    $post_id Post.
		 */
		return esc_url_raw( (string) apply_filters( 'qcld_wpbot_seo/llms_txt_url', (string) get_permalink( $post_id ), (int) $post_id ) );
	}

	/**
	 * The summary line: the owner's text, else the tagline, else the main business.
	 *
	 * @return string
	 */
	public static function summary() {
		$summary = trim( (string) Settings::get( 'llms_summary' ) );
		if ( '' !== $summary ) {
			return $summary;
		}
		$tagline = trim( wp_strip_all_tags( get_bloginfo( 'description' ) ) );
		if ( '' !== $tagline ) {
			return $tagline;
		}
		$main  = Locations::main();
		$types = Locations::types();
		if ( is_array( $main ) && '' !== (string) $main['city'] ) {
			/* translators: 1: business type, 2: town. */
			return sprintf( __( '%1$s in %2$s.', 'chatbot' ), isset( $types[ $main['type'] ] ) ? $types[ $main['type'] ] : $main['type'], $main['city'] );
		}
		return wp_strip_all_tags( get_bloginfo( 'name' ) );
	}

	/**
	 * One line about a page: the reviewed description (Pro), the search description, else the excerpt.
	 *
	 * @param int $post_id Post.
	 * @return string
	 */
	public static function description( $post_id ) {
		$text = trim( (string) Seo_Bridge::read( $post_id )['description'] );
		if ( '' === $text ) {
			$post = get_post( $post_id );
			$text = $post ? trim( wp_strip_all_tags( '' !== $post->post_excerpt ? $post->post_excerpt : '' ) ) : '';
		}
		/**
		 * Description of a page in llms.txt (Pro uses AI-written descriptions the owner reviewed).
		 *
		 * @param string $text    Description.
		 * @param int    $post_id Post.
		 */
		$text = (string) apply_filters( 'qcld_wpbot_seo/llms_txt_description', $text, (int) $post_id );
		$text = self::clean( $text );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 200 ) : substr( $text, 0, 200 );
	}

	/**
	 * One clean line of Markdown text (no line breaks; no link brackets in link names).
	 *
	 * @param string $text Text.
	 * @param bool   $name For a link name.
	 * @return string
	 */
	public static function clean( $text, $name = false ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
		$text = trim( (string) preg_replace( '/\s+/', ' ', $text ) );
		return $name ? str_replace( array( '[', ']' ), array( '(', ')' ), $text ) : $text;
	}
}
