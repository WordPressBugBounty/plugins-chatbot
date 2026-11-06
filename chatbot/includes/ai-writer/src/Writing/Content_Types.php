<?php
/**
 * Content types for "Write something new", extendable by other code
 * (WooCommerce writing adds buying guides and product comparisons).
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Writing;

defined( 'ABSPATH' ) || exit;

/**
 * Content type registry.
 */
final class Content_Types {

	/**
	 * Every type: label (owner-facing), prompt (English name for the AI),
	 * instructions (extra rules for the AI), products (minimum products to
	 * choose as sources, 0 for none).
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		$types = array(
			'blog_post'    => array(
				'label'  => __( 'Blog post', 'chatbot' ),
				'prompt' => 'blog post',
			),
			'help_article' => array(
				'label'  => __( 'Help article', 'chatbot' ),
				'prompt' => 'help article',
			),
			'faq'          => array(
				'label'  => __( 'FAQ page', 'chatbot' ),
				'prompt' => 'FAQ page',
			),
			'page_copy'    => array(
				'label'  => __( 'Website page', 'chatbot' ),
				'prompt' => 'website page',
			),
			'product'      => array(
				'label'  => __( 'Product description', 'chatbot' ),
				'prompt' => 'product description',
			),
			'page_section' => array(
				'label'        => __( 'Page section', 'chatbot' ),
				'prompt'       => 'section to add to an existing page',
				'instructions' => 'One focused section: a heading that states the question, then two to five short paragraphs or a list. No introduction about the business and no separate conclusion.',
			),
		);

		/**
		 * Add content types.
		 *
		 * @param array $types id => label, prompt, instructions, products.
		 */
		$types = (array) apply_filters( 'qcld_wpbot_writer/content_types', $types );
		foreach ( $types as $id => $type ) {
			$types[ $id ] = wp_parse_args(
				(array) $type,
				array(
					'label'        => $id,
					'prompt'       => 'blog post',
					'instructions' => '',
					'products'     => 0,
				)
			);
		}
		return $types;
	}

	/**
	 * Type ids.
	 *
	 * @return string[]
	 */
	public static function ids() {
		return array_map( 'strval', array_keys( self::all() ) );
	}

	/**
	 * One type, falling back to the blog post.
	 *
	 * @param string $id Type id.
	 * @return array
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : $all['blog_post'];
	}

	/**
	 * Public list for the screens.
	 *
	 * @return array[] value, label, products.
	 */
	public static function public_view() {
		$out = array();
		foreach ( self::all() as $id => $type ) {
			$out[] = array(
				'value'    => (string) $id,
				'label'    => (string) $type['label'],
				'products' => (int) $type['products'],
			);
		}
		return $out;
	}
}
