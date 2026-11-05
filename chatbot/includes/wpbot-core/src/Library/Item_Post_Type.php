<?php
/**
 * Private post type for Content Library items (Quick write pieces, channel
 * versions). Never public; revisions on; readable by every module through
 * the Content Library API.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Library;

defined( 'ABSPATH' ) || exit;

/**
 * Registers wpbot_content_item.
 */
final class Item_Post_Type {

	const POST_TYPE = 'wpbot_content_item';

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Content items', 'chatbot' ),
					'singular_name' => __( 'Content item', 'chatbot' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => true,
				'supports'            => array( 'title', 'editor', 'author', 'revisions', 'custom-fields' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}
}
