<?php
/**
 * Post type wpbot_article (plan 10.1): hierarchical (owner decision H13), REST
 * enabled, capabilities from the Help Center permission section (Caps).
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Content;

use QuantumCloud\HelpCenter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Article post type.
 */
final class Post_Type {

	/**
	 * Post type name. Never "docs" (BetterDocs, weDocs and EazyDocs use it).
	 */
	const NAME = 'wpbot_article';

	/**
	 * Register on init.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( self::class, 'register' ), 10 );
	}

	/**
	 * Register the post type (after the taxonomies, so their URL rules come first).
	 *
	 * @return void
	 */
	public static function register() {
		register_post_type( self::NAME, self::args( Settings::base() ) );
	}

	/**
	 * Registration arguments.
	 *
	 * @param string $base Public address.
	 * @return array
	 */
	public static function args( $base ) {
		/**
		 * Filters the article post type arguments.
		 *
		 * @param array  $args Arguments.
		 * @param string $base Public address.
		 */
		return (array) apply_filters(
			'qcld_wpbot_help_center/post_type_args',
			array(
				'labels'          => array(
					'name'               => __( 'Articles', 'chatbot' ),
					'singular_name'      => __( 'Article', 'chatbot' ),
					'menu_name'          => __( 'Help Center', 'chatbot' ),
					'all_items'          => __( 'All Articles', 'chatbot' ),
					'add_new_item'       => __( 'Add Article', 'chatbot' ),
					'add_new'            => __( 'Add Article', 'chatbot' ),
					'edit_item'          => __( 'Edit Article', 'chatbot' ),
					'new_item'           => __( 'New Article', 'chatbot' ),
					'view_item'          => __( 'View Article', 'chatbot' ),
					'search_items'       => __( 'Search Articles', 'chatbot' ),
					'not_found'          => __( 'No articles found.', 'chatbot' ),
					'not_found_in_trash' => __( 'No articles found in Trash.', 'chatbot' ),
					'parent_item_colon'  => __( 'Parent article:', 'chatbot' ),
				),
				'public'          => true,
				'hierarchical'    => true,
				'show_in_rest'    => true,
				'rest_base'       => 'help-articles',
				'menu_icon'       => 'dashicons-sos',
				'menu_position'   => 26,
				'supports'        => array( 'title', 'editor', 'excerpt', 'revisions', 'author', 'custom-fields', 'page-attributes', 'thumbnail' ),
				'has_archive'     => $base,
				'rewrite'         => array(
					'slug'       => $base,
					'with_front' => false,
					'feeds'      => false,
				),
				'capability_type' => array( 'wpbot_article', 'wpbot_articles' ),
				'map_meta_cap'    => true,
				'taxonomies'      => array( Taxonomies::KB, Taxonomies::CATEGORY, Taxonomies::TAG ),
			),
			$base
		);
	}
}
