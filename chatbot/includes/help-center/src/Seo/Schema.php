<?php
/**
 * Structured data for help articles (HC1-12, plan 5.1 "SEO").
 *
 * TechArticle and BreadcrumbList for every article, HowTo from a Steps block,
 * FAQPage from Accordion blocks. Added through SEO & AI Search's
 * qcld_wpbot_seo/schema_graph (it then replaces its generic WebPage and
 * breadcrumb nodes for articles). Without SEO & AI Search and without another
 * SEO plugin, the Help Center prints the same graph itself.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Seo;

use QuantumCloud\HelpCenter\Access;
use QuantumCloud\HelpCenter\Content\Post_Type;
use QuantumCloud\HelpCenter\Front\Data;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument properties.

/**
 * Schema nodes.
 */
final class Schema {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'qcld_wpbot_seo/schema_graph', array( self::class, 'graph' ) );
		add_action( 'wp_head', array( self::class, 'fallback' ), 98 );
	}

	/**
	 * Replace the SEO module's generic nodes for an article with ours.
	 *
	 * @param array $nodes Nodes.
	 * @return array
	 */
	public static function graph( $nodes ) {
		$post = self::current();
		if ( ! $post ) {
			return $nodes;
		}
		$url  = (string) get_permalink( $post );
		$keep = array();
		foreach ( (array) $nodes as $node ) {
			$id   = is_array( $node ) && isset( $node['@id'] ) ? (string) $node['@id'] : '';
			$type = is_array( $node ) && isset( $node['@type'] ) ? $node['@type'] : '';
			if ( $url . '#main' === $id || 'BreadcrumbList' === $type ) {
				continue;
			}
			$keep[] = $node;
		}
		return array_merge( $keep, self::nodes( $post ) );
	}

	/**
	 * Print the graph when nothing else does (no SEO & AI Search, no other SEO plugin).
	 *
	 * @return void
	 */
	public static function fallback() {
		$post = self::current();
		if ( ! $post || self::seo_module_prints() || self::other_seo_plugin() ) {
			return;
		}
		echo '<script type="application/ld+json">' . wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => self::nodes( $post ),
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
		) . '</script>' . "\n";
	}

	/**
	 * All nodes for an article.
	 *
	 * @param \WP_Post $post Article.
	 * @return array[]
	 */
	public static function nodes( $post ) {
		$url      = (string) get_permalink( $post );
		$html     = Access::can_read( $post ) ? (string) do_blocks( (string) $post->post_content ) : '';
		$category = Data::main_category( $post->ID );
		$article  = array(
			'@type'               => 'TechArticle',
			'@id'                 => $url . '#main',
			'headline'            => wp_strip_all_tags( get_the_title( $post ) ),
			'url'                 => $url,
			'mainEntityOfPage'    => $url,
			'datePublished'       => get_post_time( 'c', true, $post ),
			'dateModified'        => get_post_modified_time( 'c', true, $post ),
			'inLanguage'          => get_bloginfo( 'language' ),
			'author'              => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', (int) $post->post_author ),
			),
			'publisher'           => array(
				'@type' => 'Organization',
				'@id'   => home_url( '/#organization' ),
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
			'isAccessibleForFree' => true,
		);
		$summary  = has_excerpt( $post ) ? wp_strip_all_tags( (string) $post->post_excerpt ) : wp_trim_words( wp_strip_all_tags( $html ), 30, '…' );
		if ( '' !== trim( $summary ) ) {
			$article['description'] = trim( $summary );
		}
		if ( $category ) {
			$article['articleSection'] = $category->name;
		}
		$version = trim( (string) get_post_meta( $post->ID, '_wpbot_product_version', true ) );
		if ( '' !== $version ) {
			$article['version'] = $version;
		}
		$image = get_the_post_thumbnail_url( $post, 'full' );
		if ( $image ) {
			$article['image'] = $image;
		}
		if ( ! Access::can_read( $post ) || array() !== array_diff( Access::labels( $post->ID ), array( 'public' ) ) ) {
			$article['isAccessibleForFree'] = false;
			$article['hasPart']             = array(
				'@type'               => 'WebPageElement',
				'isAccessibleForFree' => false,
				'cssSelector'         => '.wpbot-hc__content',
			);
		}
		$trail = Data::breadcrumbs( $post );
		$last  = count( $trail ) - 1;
		if ( $last >= 0 && '' === (string) $trail[ $last ][1] ) {
			$trail[ $last ][1] = $url;
		}
		$nodes = array( $article, self::breadcrumbs( $trail ) );
		$steps = self::steps( $html );
		if ( $steps ) {
			$howto = array(
				'@type' => 'HowTo',
				'@id'   => $url . '#howto',
				'name'  => wp_strip_all_tags( get_the_title( $post ) ),
				'step'  => array(),
			);
			foreach ( $steps as $i => $step ) {
				$howto['step'][] = array_filter(
					array(
						'@type'    => 'HowToStep',
						'position' => $i + 1,
						'name'     => $step['name'],
						'text'     => '' !== $step['text'] ? $step['text'] : $step['name'],
					)
				);
			}
			$nodes[] = $howto;
		}
		$faqs = self::faqs( $html );
		if ( $faqs ) {
			$faq = array(
				'@type'      => 'FAQPage',
				'@id'        => $url . '#faq',
				'mainEntity' => array(),
			);
			foreach ( $faqs as $item ) {
				$faq['mainEntity'][] = array(
					'@type'          => 'Question',
					'name'           => $item['q'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $item['a'],
					),
				);
			}
			$nodes[] = $faq;
		}
		return $nodes;
	}

	/**
	 * BreadcrumbList from [ name, url ] pairs.
	 *
	 * @param array[] $trail Crumbs.
	 * @return array
	 */
	public static function breadcrumbs( array $trail ) {
		$items = array();
		foreach ( array_values( $trail ) as $i => $crumb ) {
			$item = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => wp_strip_all_tags( (string) $crumb[0] ),
			);
			if ( '' !== (string) $crumb[1] ) {
				$item['item'] = (string) $crumb[1];
			}
			$items[] = $item;
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}

	/**
	 * Steps of the first Steps block: name and text.
	 *
	 * @param string $html Rendered content.
	 * @return array[]
	 */
	public static function steps( $html ) {
		$xpath = self::xpath( $html );
		if ( ! $xpath ) {
			return array();
		}
		$list = $xpath->query( '//ol[contains(concat(" ", normalize-space(@class), " "), " wpbot-help-steps ")]' );
		if ( ! $list || ! $list->length ) {
			return array();
		}
		$out = array();
		foreach ( $xpath->query( './li', $list->item( 0 ) ) as $li ) {
			$title = $xpath->query( './/*[contains(@class, "wpbot-help-step__title")]', $li );
			$body  = $xpath->query( './/*[contains(@class, "wpbot-help-step__body")]', $li );
			$name  = $title && $title->length ? self::text( $title->item( 0 )->textContent ) : '';
			$text  = $body && $body->length ? self::text( $body->item( 0 )->textContent ) : '';
			if ( '' !== $name || '' !== $text ) {
				$out[] = array(
					'name' => '' !== $name ? $name : wp_trim_words( $text, 8, '…' ),
					'text' => $text,
				);
			}
		}
		return $out;
	}

	/**
	 * Questions and answers from Accordion blocks.
	 *
	 * @param string $html Rendered content.
	 * @return array[] Each: q, a.
	 */
	public static function faqs( $html ) {
		$xpath = self::xpath( $html );
		if ( ! $xpath ) {
			return array();
		}
		$out = array();
		foreach ( $xpath->query( '//details[contains(concat(" ", normalize-space(@class), " "), " wpbot-help-accordion ")]' ) as $details ) {
			$summary = $xpath->query( './summary', $details );
			$body    = $xpath->query( './/*[contains(@class, "wpbot-help-accordion__body")]', $details );
			$q       = $summary && $summary->length ? self::text( $summary->item( 0 )->textContent ) : '';
			$a       = $body && $body->length ? self::text( $body->item( 0 )->textContent ) : '';
			if ( '' !== $q && '' !== $a ) {
				$out[] = array(
					'q' => $q,
					'a' => $a,
				);
			}
		}
		return $out;
	}

	/**
	 * Whether SEO & AI Search prints structured data on this page.
	 *
	 * @return bool
	 */
	private static function seo_module_prints() {
		return class_exists( '\QuantumCloud\SeoAiSearch\Technical\Schema_Output' )
			&& false !== has_action( 'wp_head', array( 'QuantumCloud\SeoAiSearch\Technical\Schema_Output', 'render' ) )
			&& ! ( class_exists( '\QuantumCloud\SeoAiSearch\Seo\Seo_Plugins' ) && \QuantumCloud\SeoAiSearch\Seo\Seo_Plugins::handles_head() );
	}

	/**
	 * Whether another SEO plugin prints the page's structured data.
	 *
	 * @return bool
	 */
	private static function other_seo_plugin() {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' );
	}

	/**
	 * The article being viewed, or null.
	 *
	 * @return \WP_Post|null
	 */
	private static function current() {
		if ( ! is_singular( Post_Type::NAME ) ) {
			return null;
		}
		$post = get_queried_object();
		return $post instanceof \WP_Post ? $post : null;
	}

	/**
	 * XPath over an HTML fragment.
	 *
	 * @param string $html HTML.
	 * @return \DOMXPath|null
	 */
	private static function xpath( $html ) {
		if ( '' === trim( (string) $html ) ) {
			return null;
		}
		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return new \DOMXPath( $doc );
	}

	/**
	 * Plain text on one line.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function text( $text ) {
		return trim( (string) preg_replace( '/\s+/u', ' ', (string) $text ) );
	}
}
