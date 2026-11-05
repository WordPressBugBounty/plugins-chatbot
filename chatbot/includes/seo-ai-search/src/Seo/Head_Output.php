<?php
/**
 * Prints WPBot's own search fields when no SEO plugin does: title,
 * description (only when AI Writer is not loaded, since AI Writer prints
 * the same fields), noindex and canonical.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Own head output.
 */
final class Head_Output {

	/**
	 * Hook on the front end.
	 *
	 * @return void
	 */
	public static function hook() {
		if ( is_admin() ) {
			return;
		}
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_filter( 'get_canonical_url', array( __CLASS__, 'canonical' ), 10, 2 );
		if ( ! class_exists( '\QuantumCloud\AiWriter\Seo\Seo_Adapter' ) ) {
			add_filter( 'pre_get_document_title', array( __CLASS__, 'title' ), 20 );
			add_action( 'wp_head', array( __CLASS__, 'description' ), 1 );
		}
	}

	/**
	 * Post being viewed when no SEO plugin handles the head.
	 *
	 * @return int
	 */
	private static function post() {
		return ! Seo_Plugins::handles_head() && is_singular() ? (int) get_queried_object_id() : 0;
	}

	/**
	 * Noindex from WPBot's own field.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		$post_id = self::post();
		if ( $post_id && '1' === (string) get_post_meta( $post_id, Seo_Bridge::OWN_NOINDEX, true ) ) {
			$robots['noindex'] = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}

	/**
	 * Canonical from WPBot's own field.
	 *
	 * @param string   $url  Canonical URL.
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public static function canonical( $url, $post ) {
		if ( Seo_Plugins::handles_head() || ! $post instanceof \WP_Post ) {
			return $url;
		}
		$own = (string) get_post_meta( $post->ID, Seo_Bridge::OWN_CANONICAL, true );
		return '' !== $own ? esc_url_raw( $own ) : $url;
	}

	/**
	 * Title from WPBot's own field.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function title( $title ) {
		$post_id = self::post();
		$own     = $post_id ? trim( (string) get_post_meta( $post_id, Seo_Bridge::OWN_TITLE, true ) ) : '';
		return '' !== $own ? esc_html( $own ) : $title;
	}

	/**
	 * Description from WPBot's own field (the legacy output skips its own
	 * description when this one exists).
	 *
	 * @return void
	 */
	public static function description() {
		$post_id = self::post();
		$own     = $post_id ? trim( (string) get_post_meta( $post_id, Seo_Bridge::OWN_DESCRIPTION, true ) ) : '';
		if ( '' !== $own ) {
			echo '<meta name="description" content="' . esc_attr( $own ) . '" />' . "\n";
		}
	}
}
