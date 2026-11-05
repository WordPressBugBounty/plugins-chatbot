<?php
/**
 * How important a page is to the site, without traffic data (round 2, R3):
 * the front page, pages in a navigation menu, pages other pages link to,
 * products that sell, and topic pillars count most. Search Console traffic,
 * when it is there, is added on top by the opportunity types.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Opportunities;

use QuantumCloud\SeoAiSearch\Onpage\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Page importance, 0–100.
 */
final class Page_Importance {

	/**
	 * Posts in a navigation menu (cached per request).
	 *
	 * @var int[]|null
	 */
	private static $menu = null;

	/**
	 * Pillar posts of the Topic Map (cached per request).
	 *
	 * @var int[]|null
	 */
	private static $pillars = null;

	/**
	 * Scores already worked out.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Internal links pointing at each post (cached per request).
	 *
	 * @var array|null
	 */
	private static $inbound = null;

	/**
	 * Importance and why.
	 *
	 * @param int $post_id Post.
	 * @return array score (0–100), why (string[]).
	 */
	public static function of( $post_id ) {
		$post_id = (int) $post_id;
		if ( isset( self::$cache[ $post_id ] ) ) {
			return self::$cache[ $post_id ];
		}
		$score = 10;
		$why   = array();
		if ( (int) get_option( 'page_on_front' ) === $post_id ) {
			$score += 40;
			$why[]  = __( 'your home page', 'chatbot' );
		}
		if ( in_array( $post_id, self::menu_posts(), true ) ) {
			$score += 25;
			$why[]  = __( 'in your menu', 'chatbot' );
		}
		$post = get_post( $post_id );
		if ( $post instanceof \WP_Post ) {
			$counts  = self::inbound_counts();
			$inbound = isset( $counts[ $post_id ] ) ? (int) $counts[ $post_id ] : 0;
			if ( $inbound > 0 ) {
				$score += min( 20, $inbound * 2 );
				/* translators: %d: pages linking here. */
				$why[] = sprintf( _n( '%d page links here', '%d pages link here', $inbound, 'chatbot' ), $inbound );
			}
			if ( 'product' === $post->post_type ) {
				$sales = (int) get_post_meta( $post_id, 'total_sales', true );
				if ( $sales > 0 ) {
					$score += min( 20, 5 + (int) round( log( $sales + 1, 2 ) * 2 ) );
					$why[]  = __( 'a product that sells', 'chatbot' );
				}
			}
			if ( 'page' === $post->post_type ) {
				$score += 5;
			}
		}
		if ( in_array( $post_id, self::pillars(), true ) ) {
			$score += 10;
			$why[]  = __( 'a topic pillar', 'chatbot' );
		}
		self::$cache[ $post_id ] = array(
			'score' => min( 100, $score ),
			'why'   => $why,
		);
		return self::$cache[ $post_id ];
	}

	/**
	 * Potential for a content opportunity: the type's base value scaled by importance, plus Search Console impressions when known.
	 *
	 * @param float $base        Base value of the type.
	 * @param int   $post_id     Post.
	 * @param array $impressions url_id => impressions (last 28 days), from the opportunity context.
	 * @return float
	 */
	public static function potential( $base, $post_id, array $impressions = array() ) {
		$importance = self::of( $post_id )['score'];
		$potential  = (float) $base * ( 0.5 + $importance / 100 );
		if ( array() !== $impressions ) {
			$url_id = \QuantumCloud\SeoAiSearch\Google\Data_Store::url_for_post( $post_id );
			if ( $url_id && isset( $impressions[ $url_id ] ) ) {
				$potential += min( 50, $impressions[ $url_id ] / 100 );
			}
		}
		return round( $potential, 1 );
	}

	/**
	 * Impressions per url_id from the opportunity context's page rows.
	 *
	 * @param array $context Opportunity context.
	 * @return array
	 */
	public static function impressions( array $context ) {
		$out = array();
		foreach ( isset( $context['pages'] ) ? (array) $context['pages'] : array() as $row ) {
			if ( ! empty( $row['url_id'] ) ) {
				$out[ (int) $row['url_id'] ] = (int) ( isset( $row['impressions'] ) ? $row['impressions'] : 0 );
			}
		}
		return $out;
	}

	/**
	 * Posts linked from any navigation menu.
	 *
	 * @return int[]
	 */
	public static function menu_posts() {
		if ( null !== self::$menu ) {
			return self::$menu;
		}
		self::$menu = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
				if ( isset( $item->type, $item->object_id ) && 'post_type' === $item->type ) {
					self::$menu[] = (int) $item->object_id;
				}
			}
		}
		self::$menu = array_values( array_unique( self::$menu ) );
		return self::$menu;
	}

	/**
	 * Pillar posts of the Topic Map.
	 *
	 * @return int[]
	 */
	public static function pillars() {
		if ( null !== self::$pillars ) {
			return self::$pillars;
		}
		self::$pillars = array();
		if ( class_exists( '\QuantumCloud\WPBotCore\Map\Topic_Map' ) ) {
			foreach ( \QuantumCloud\WPBotCore\Map\Topic_Map::topics() as $topic ) {
				if ( ! empty( $topic['pillar_post_id'] ) ) {
					self::$pillars[] = (int) $topic['pillar_post_id'];
				}
			}
		}
		return self::$pillars;
	}

	/**
	 * Forget cached values (tests, long runs).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$menu    = null;
		self::$pillars = null;
		self::$inbound = null;
		self::$cache   = array();
	}

	/**
	 * How many other published pages link to each post: one read of the
	 * newest 1,500 pages' content, links matched by path or ?p= id.
	 *
	 * @return array post_id => links in.
	 */
	public static function inbound_counts() {
		if ( null !== self::$inbound ) {
			return self::$inbound;
		}
		global $wpdb;
		self::$inbound = array();
		$types         = Audit::post_types();
		$in            = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- One placeholder per post type; bounded read.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$in}) ORDER BY post_modified DESC LIMIT 1500", $types ), ARRAY_A );
		// phpcs:enable
		$paths = array();
		foreach ( $rows as $row ) {
			$path = untrailingslashit( (string) wp_parse_url( (string) get_permalink( (int) $row['ID'] ), PHP_URL_PATH ) );
			if ( '' !== $path ) {
				$paths[ $path ] = (int) $row['ID'];
			}
		}
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		foreach ( $rows as $row ) {
			if ( ! preg_match_all( '/href\s*=\s*["\']([^"\']+)["\']/i', (string) $row['post_content'], $found ) ) {
				continue;
			}
			$targets = array();
			foreach ( $found[1] as $href ) {
				$href      = html_entity_decode( $href, ENT_QUOTES, 'UTF-8' );
				$link_host = (string) wp_parse_url( $href, PHP_URL_HOST );
				if ( '' !== $link_host && strtolower( $link_host ) !== strtolower( $host ) ) {
					continue;
				}
				if ( preg_match( '/[?&](?:p|page_id)=(\d+)/', $href, $id ) ) {
					$targets[ (int) $id[1] ] = true;
					continue;
				}
				$path = untrailingslashit( (string) wp_parse_url( $href, PHP_URL_PATH ) );
				if ( isset( $paths[ $path ] ) ) {
					$targets[ $paths[ $path ] ] = true;
				}
			}
			unset( $targets[ (int) $row['ID'] ] );
			foreach ( array_keys( $targets ) as $target ) {
				self::$inbound[ $target ] = ( isset( self::$inbound[ $target ] ) ? self::$inbound[ $target ] : 0 ) + 1;
			}
		}
		return self::$inbound;
	}
}
