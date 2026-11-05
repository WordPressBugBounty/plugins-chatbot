<?php
/**
 * Front-end output taken over from the retired SEO Analyzer.
 *
 * The old module printed, on single posts and pages: a description and
 * Open Graph tags from its own SEO fields (only with no SEO plugin), FAQ
 * schema from its FAQ field, and styles for its Quick Answer and FAQ boxes.
 * Sites keep that output after the old module stops loading. Written fresh
 * for this module; the old code was read for behaviour only.
 *
 * Two fixes over the old output: no second description tag where AI
 * Writer already prints one, and the box styles no longer restyle every
 * Details block on the site.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the old analyzer's head output for posts that still carry its data.
 */
final class Legacy_Output {

	const META_TITLE       = '_qcld_wpbot_ai_seo_seo_meta_title';
	const META_DESCRIPTION = '_qcld_wpbot_ai_seo_seo_meta_description';
	const META_FAQ         = '_qcld_wpbot_ai_seo_faq_data';

	/**
	 * Hook the three outputs on the front end.
	 *
	 * @return void
	 */
	public static function hook() {
		if ( is_admin() ) {
			return;
		}
		add_action( 'wp_head', array( __CLASS__, 'meta' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'styles' ), 50 );
		add_action( 'wp_head', array( __CLASS__, 'faq_schema' ), 99 );
	}

	/**
	 * The post being viewed, when output applies.
	 *
	 * @return int Post id or 0.
	 */
	private static function current_post() {
		if ( is_feed() || ! is_singular() ) {
			return 0;
		}
		return (int) get_queried_object_id();
	}

	/**
	 * Description and Open Graph tags (no SEO plugin; legacy fields set).
	 *
	 * @return void
	 */
	public static function meta() {
		$post_id = self::current_post();
		if ( ! $post_id || Seo_Plugins::handles_head() ) {
			return;
		}
		$tags = self::meta_tags( $post_id );
		foreach ( $tags as $tag ) {
			if ( 'name' === $tag[0] ) {
				printf( '<meta name="%s" content="%s" />' . "\n", esc_attr( $tag[1] ), esc_attr( $tag[2] ) );
			} else {
				printf( '<meta property="%s" content="%s" />' . "\n", esc_attr( $tag[1] ), esc_attr( $tag[2] ) );
			}
		}
	}

	/**
	 * Tags to print for a post: list of [ name|property, key, value ].
	 *
	 * Same as the old module: its own SEO fields first, else the post title
	 * and excerpt, on every single post or page while no SEO plugin is active.
	 *
	 * @param int $post_id Post id.
	 * @return array
	 */
	public static function meta_tags( $post_id ) {
		$title       = sanitize_text_field( (string) get_post_meta( $post_id, self::META_TITLE, true ) );
		$description = sanitize_textarea_field( (string) get_post_meta( $post_id, self::META_DESCRIPTION, true ) );
		if ( '' === $title ) {
			$title = sanitize_text_field( (string) get_the_title( $post_id ) );
		}
		if ( '' === $description ) {
			$description = sanitize_textarea_field( (string) get_post_field( 'post_excerpt', $post_id ) );
		}
		if ( '' === $title && '' === $description ) {
			return array();
		}
		$title       = self::cut( $title, 60 );
		$description = self::cut( $description, 160 );

		$tags = array();
		// AI Writer prints its own description for posts it wrote meta for; never two.
		if ( '' !== $description && ! self::writer_prints_description( $post_id ) ) {
			$tags[] = array( 'name', 'description', $description );
		}
		$tags[] = array( 'property', 'og:type', 'article' );
		$tags[] = array( 'property', 'og:title', $title );
		if ( '' !== $description ) {
			$tags[] = array( 'property', 'og:description', $description );
		}
		$tags[] = array( 'property', 'og:url', (string) get_permalink( $post_id ) );
		$tags[] = array( 'name', 'twitter:card', 'summary_large_image' );
		return $tags;
	}

	/**
	 * FAQPage JSON-LD from the old module's FAQ field.
	 *
	 * Printed like before, with or without an SEO plugin, except on posts
	 * that use an SEO plugin's own FAQ block (that plugin prints FAQ schema).
	 *
	 * @return void
	 */
	public static function faq_schema() {
		$post_id = self::current_post();
		if ( ! $post_id ) {
			return;
		}
		$schema = self::faq_data( $post_id );
		if ( null === $schema ) {
			return;
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>' . "\n";
	}

	/**
	 * FAQPage schema for a post, or null.
	 *
	 * @param int $post_id Post id.
	 * @return array|null
	 */
	public static function faq_data( $post_id ) {
		$items = get_post_meta( $post_id, self::META_FAQ, true );
		if ( ! is_array( $items ) || array() === $items ) {
			return null;
		}
		if ( preg_match( '#<!-- wp:(?:yoast/faq-block|rank-math/faq-block|aioseo/faq)#', (string) get_post_field( 'post_content', $post_id ) ) ) {
			return null;
		}
		/**
		 * Whether to print the FAQ schema kept from the old SEO Analyzer.
		 *
		 * @param bool  $print   Default true.
		 * @param int   $post_id Post id.
		 * @param array $items   FAQ items.
		 */
		if ( ! apply_filters( 'qcld_wpbot_seo/legacy_faq_schema', true, $post_id, $items ) ) {
			return null;
		}
		$questions = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$question = isset( $item['question'] ) ? $item['question'] : ( isset( $item['q'] ) ? $item['q'] : '' );
			$answer   = isset( $item['answer'] ) ? $item['answer'] : ( isset( $item['a'] ) ? $item['a'] : '' );
			$question = trim( wp_strip_all_tags( (string) $question ) );
			$answer   = trim( wp_strip_all_tags( (string) $answer ) );
			if ( '' === $question || '' === $answer ) {
				continue;
			}
			$questions[] = array(
				'@type'          => 'Question',
				'name'           => $question,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer,
				),
			);
		}
		if ( array() === $questions ) {
			return null;
		}
		return array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $questions,
		);
	}

	/**
	 * Styles for the old Quick Answer and FAQ boxes, only where they appear.
	 *
	 * @return void
	 */
	public static function styles() {
		$post_id = self::current_post();
		if ( ! $post_id ) {
			return;
		}
		$css = self::box_css( (string) get_post_field( 'post_content', $post_id ) );
		if ( '' !== $css ) {
			echo '<style id="qcld-wpbot-seo-legacy-boxes">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed CSS built in box_css(); no input.
		}
	}

	/**
	 * CSS for the boxes a post contains (same look as before).
	 *
	 * The Details-block rules apply only in posts that contain the old FAQ
	 * markup, instead of every Details block on the site.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function box_css( $content ) {
		$has_answer = (bool) preg_match( '/qcld_(?:wpbot_ai_seo|ai_wpbot)-quick-answer/', $content );
		$has_faq    = (bool) preg_match( '/qcld_(?:wpbot_ai_seo|ai_wpbot)-faq/', $content );
		$css        = '';
		if ( $has_answer ) {
			$box  = '.qcld_wpbot_ai_seo-quick-answer';
			$css .= $box . '{background-color:#f8fafc;border-left:4px solid #2563eb;padding:16px 20px;margin:24px 0;border-radius:6px;color:#1e293b !important;font-size:1.02em;line-height:1.6;box-shadow:0 1px 2px rgba(0,0,0,.04)}'
				. $box . ' strong{color:#0f172a !important}'
				. $box . ' p{color:#1e293b !important;margin:0 0 8px}'
				. $box . ' ul{margin:8px 0 0 20px;padding:0}'
				. $box . ' li{color:#334155 !important;margin-bottom:4px}';
		}
		if ( $has_faq ) {
			$item  = 'details.qcld_wpbot_ai_seo-faq-item';
			$block = '.qcld_wpbot_ai_seo-faq details.wp-block-details';
			$both  = static function ( $suffix ) use ( $item, $block ) {
				return $item . $suffix . ',' . $block . $suffix;
			};
			$css  .= $both( '' ) . '{background-color:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:12px;padding:14px 18px;box-shadow:0 1px 2px rgba(0,0,0,.03);transition:background-color .2s ease,border-color .2s ease}'
				. $both( '[open]' ) . '{background-color:#f8fafc;border-color:#cbd5e1}'
				. $both( ' summary' ) . '{font-weight:600;font-size:1.05em;color:#1e293b !important;cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center}'
				. $both( ' summary::-webkit-details-marker' ) . '{display:none}'
				. $both( ' summary::after' ) . "{content:'+';font-size:1.25em;font-weight:600;color:#64748b;line-height:1;margin-left:12px}"
				. $both( '[open] summary::after' ) . "{content:'\\2212';color:#2563eb}"
				. $both( '[open] > :not(summary)' ) . '{margin-top:12px;margin-bottom:4px;color:#334155 !important;line-height:1.65}'
				. '.qcld_wpbot_ai_seo-faq-q{color:#1e293b !important;font-weight:600;font-size:1.15em;margin-top:20px;margin-bottom:8px}'
				. '.qcld_wpbot_ai_seo-faq-a{color:#334155 !important;line-height:1.65;margin-bottom:16px}';
		}
		return $css;
	}

	/**
	 * Whether AI Writer prints a description for this post itself.
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	private static function writer_prints_description( $post_id ) {
		// AI Writer or Head_Output prints WPBot's own description when one is saved.
		return '' !== trim( (string) get_post_meta( $post_id, '_qcld_wpbot_writer_meta_description', true ) );
	}

	/**
	 * First characters of a text (as the old module cut them, no ellipsis).
	 *
	 * @param string $text  Text.
	 * @param int    $chars Most characters.
	 * @return string
	 */
	private static function cut( $text, $chars ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $chars ) : substr( $text, 0, $chars );
	}
}
