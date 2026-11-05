<?php
/**
 * Basic structured data when no SEO plugin prints any: Article for posts,
 * WebPage for pages, BreadcrumbList, and WebSite on the front page. Never
 * printed next to an SEO plugin's schema, so nothing is duplicated.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Technical;

use QuantumCloud\SeoAiSearch\Seo\Seo_Plugins;

defined( 'ABSPATH' ) || exit;

/**
 * Schema output.
 */
final class Schema_Output {

	/**
	 * Hook the head output.
	 *
	 * @return void
	 */
	public static function hook() {
		if ( ! is_admin() ) {
			add_action( 'wp_head', array( __CLASS__, 'render' ), 98 );
		}
	}

	/**
	 * Print the graph.
	 *
	 * @return void
	 */
	public static function render() {
		if ( Seo_Plugins::handles_head() || is_feed() ) {
			return;
		}
		/**
		 * Whether WPBot prints basic schema when no SEO plugin does.
		 *
		 * @param bool $print Default true.
		 */
		if ( ! apply_filters( 'qcld_wpbot_seo/schema_output', true ) ) {
			return;
		}
		$graph = self::graph();
		if ( array() === $graph ) {
			return;
		}
		echo '<script type="application/ld+json">' . wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
		) . '</script>' . "\n";
	}

	/**
	 * Schema nodes for the current view.
	 *
	 * @return array
	 */
	public static function graph() {
		$site = array(
			'@type' => 'Organization',
			'@id'   => home_url( '/#organization' ),
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);
		$logo = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' ) : '';
		if ( $logo ) {
			$site['logo'] = $logo;
		}
		$nodes = array();
		if ( is_front_page() ) {
			$nodes[] = $site;
			$nodes[] = array(
				'@type'     => 'WebSite',
				'@id'       => home_url( '/#website' ),
				'name'      => get_bloginfo( 'name' ),
				'url'       => home_url( '/' ),
				'publisher' => array( '@id' => home_url( '/#organization' ) ),
			);
		} elseif ( is_singular() ) {
			$post = get_queried_object();
			if ( ! $post instanceof \WP_Post ) {
				return array();
			}
			$url     = (string) get_permalink( $post );
			$image   = get_the_post_thumbnail_url( $post, 'full' );
			$article = 'post' === $post->post_type;
			$node    = array(
				'@type'         => $article ? 'BlogPosting' : 'WebPage',
				'@id'           => $url . '#main',
				'headline'      => wp_strip_all_tags( get_the_title( $post ) ),
				'url'           => $url,
				'datePublished' => get_post_time( 'c', true, $post ),
				'dateModified'  => get_post_modified_time( 'c', true, $post ),
				'publisher'     => $site,
			);
			if ( $article ) {
				$node['author'] = array(
					'@type' => 'Person',
					'name'  => get_the_author_meta( 'display_name', (int) $post->post_author ),
				);
			}
			if ( $image ) {
				$node['image'] = $image;
			}
			$nodes[] = $node;
			$nodes[] = self::breadcrumbs( $post );
		}
		/**
		 * Schema nodes WPBot prints (S5 adds LocalBusiness).
		 *
		 * @param array $nodes Nodes.
		 */
		return array_values( array_filter( (array) apply_filters( 'qcld_wpbot_seo/schema_graph', $nodes ) ) );
	}

	/**
	 * Breadcrumb trail: home, first category (posts) or parents (pages), the page.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	private static function breadcrumbs( \WP_Post $post ) {
		$trail = array( array( get_bloginfo( 'name' ), home_url( '/' ) ) );
		if ( 'post' === $post->post_type ) {
			$cats = get_the_category( $post->ID );
			if ( $cats ) {
				$trail[] = array( $cats[0]->name, (string) get_category_link( $cats[0] ) );
			}
		} else {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $parent ) {
				$trail[] = array( get_the_title( $parent ), (string) get_permalink( $parent ) );
			}
		}
		$trail[] = array( wp_strip_all_tags( get_the_title( $post ) ), (string) get_permalink( $post ) );
		$items   = array();
		foreach ( $trail as $index => $crumb ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $crumb[0],
				'item'     => $crumb[1],
			);
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}
}
