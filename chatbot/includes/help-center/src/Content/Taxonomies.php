<?php
/**
 * Taxonomies (plan 10.1): knowledge bases, nested categories, tags. Unlimited
 * knowledge bases in Free (decision M2).
 *
 * URLs: <base>/kb/<slug>/, <base>/category/<path>/, <base>/tag/<slug>/. They are
 * registered before the post type so their rules match before article paths.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Content;

use QuantumCloud\HelpCenter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Article taxonomies.
 */
final class Taxonomies {

	/**
	 * Knowledge base.
	 */
	const KB = 'wpbot_kb';

	/**
	 * Nested categories.
	 */
	const CATEGORY = 'wpbot_article_cat';

	/**
	 * Tags.
	 */
	const TAG = 'wpbot_article_tag';

	/**
	 * Article slugs that would be hidden by the taxonomy URLs and the glossary page.
	 *
	 * @var string[]
	 */
	const URL_WORDS = array( 'kb', 'category', 'tag', 'glossary' );

	/**
	 * Register on init, before the post type.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'register' ), 9 );
		add_filter( 'wp_unique_post_slug_is_bad_hierarchical_slug', array( self::class, 'bad_slug' ), 10, 4 );
	}

	/**
	 * Register the three taxonomies.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( self::args( Settings::base() ) as $name => $args ) {
			register_taxonomy( $name, Post_Type::NAME, $args );
		}
	}

	/**
	 * Registration arguments, keyed by taxonomy.
	 *
	 * @param string $base Public address.
	 * @return array<string,array>
	 */
	public static function args( $base ) {
		$caps = array(
			'manage_terms' => 'manage_wpbot_article_terms',
			'edit_terms'   => 'manage_wpbot_article_terms',
			'delete_terms' => 'manage_wpbot_article_terms',
			'assign_terms' => 'assign_wpbot_article_terms',
		);
		$args = array(
			self::KB       => array(
				'labels'            => array(
					'name'          => __( 'Knowledge Bases', 'chatbot' ),
					'singular_name' => __( 'Knowledge Base', 'chatbot' ),
					'add_new_item'  => __( 'Add Knowledge Base', 'chatbot' ),
					'edit_item'     => __( 'Edit Knowledge Base', 'chatbot' ),
					'search_items'  => __( 'Search Knowledge Bases', 'chatbot' ),
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_in_rest'      => true,
				'rest_base'         => 'help-knowledge-bases',
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => $base . '/kb',
					'with_front' => false,
				),
				'capabilities'      => $caps,
			),
			self::CATEGORY => array(
				'labels'            => array(
					'name'          => __( 'Categories', 'chatbot' ),
					'singular_name' => __( 'Category', 'chatbot' ),
					'add_new_item'  => __( 'Add Category', 'chatbot' ),
					'edit_item'     => __( 'Edit Category', 'chatbot' ),
					'parent_item'   => __( 'Parent Category', 'chatbot' ),
					'search_items'  => __( 'Search Categories', 'chatbot' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'rest_base'         => 'help-categories',
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'         => $base . '/category',
					'with_front'   => false,
					'hierarchical' => true,
				),
				'capabilities'      => $caps,
			),
			self::TAG      => array(
				'labels'       => array(
					'name'          => __( 'Tags', 'chatbot' ),
					'singular_name' => __( 'Tag', 'chatbot' ),
					'add_new_item'  => __( 'Add Tag', 'chatbot' ),
					'edit_item'     => __( 'Edit Tag', 'chatbot' ),
					'search_items'  => __( 'Search Tags', 'chatbot' ),
				),
				'public'       => true,
				'hierarchical' => false,
				'show_in_rest' => true,
				'rest_base'    => 'help-tags',
				'rewrite'      => array(
					'slug'       => $base . '/tag',
					'with_front' => false,
				),
				'capabilities' => $caps,
			),
		);
		/**
		 * Filters the taxonomy arguments, keyed by taxonomy.
		 *
		 * @param array  $args Arguments.
		 * @param string $base Public address.
		 */
		return (array) apply_filters( 'qcld_wpbot_help_center/taxonomy_args', $args, $base );
	}

	/**
	 * Top-level articles may not take a slug the taxonomy URLs use (kb, category, tag).
	 *
	 * @param bool   $bad         Whether the slug is bad.
	 * @param string $slug        Slug.
	 * @param string $post_type   Post type.
	 * @param int    $post_parent Parent id.
	 * @return bool
	 */
	public static function bad_slug( $bad, $slug, $post_type, $post_parent ) {
		if ( Post_Type::NAME === $post_type && 0 === (int) $post_parent && in_array( (string) $slug, self::URL_WORDS, true ) ) {
			return true;
		}
		return (bool) $bad;
	}
}
