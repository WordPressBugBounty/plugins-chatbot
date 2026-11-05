<?php
/**
 * Content Library items: short pieces (Quick write, channel versions) kept
 * as private posts of wpbot_content_item, with revisions.
 *
 * Modules create and read items only through this class, so storage can
 * change later without touching them. Items are never public.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Library;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Item storage.
 */
final class Items {

	const META_TYPE   = '_wpbot_item_type';
	const META_MODULE = '_wpbot_item_module';
	const META_DATA   = '_wpbot_item_data';
	const META_SOURCE = '_wpbot_item_source_post';

	/**
	 * Create an item owned by the current user.
	 *
	 * @param array $fields module, type, title, content, data (array), source_post (int).
	 * @return int|WP_Error Item id.
	 */
	public static function create( array $fields ) {
		// wp_insert_post() unslashes its input: slash it so backslashes survive.
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => Item_Post_Type::POST_TYPE,
					'post_status'  => 'private',
					'post_author'  => get_current_user_id(),
					'post_title'   => isset( $fields['title'] ) ? sanitize_text_field( $fields['title'] ) : '',
					'post_content' => isset( $fields['content'] ) ? sanitize_textarea_field( $fields['content'] ) : '',
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, self::META_MODULE, isset( $fields['module'] ) ? sanitize_key( $fields['module'] ) : '' );
		update_post_meta( $id, self::META_TYPE, isset( $fields['type'] ) ? sanitize_key( $fields['type'] ) : '' );
		if ( ! empty( $fields['source_post'] ) ) {
			update_post_meta( $id, self::META_SOURCE, absint( $fields['source_post'] ) );
		}
		if ( isset( $fields['data'] ) && is_array( $fields['data'] ) ) {
			update_post_meta( $id, self::META_DATA, wp_slash( wp_json_encode( $fields['data'] ) ) );
		}
		return (int) $id;
	}

	/**
	 * Update an item's title, content or data (a revision is kept).
	 *
	 * @param int   $id     Item id.
	 * @param array $fields title, content, data.
	 * @return true|WP_Error
	 */
	public static function update( $id, array $fields ) {
		if ( ! self::get( $id ) ) {
			return new WP_Error( 'qcld_wpbot_core_no_item', __( 'This item no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$post = array( 'ID' => (int) $id );
		if ( isset( $fields['title'] ) ) {
			$post['post_title'] = sanitize_text_field( $fields['title'] );
		}
		if ( isset( $fields['content'] ) ) {
			$post['post_content'] = sanitize_textarea_field( $fields['content'] );
		}
		$result = wp_update_post( wp_slash( $post ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( isset( $fields['data'] ) && is_array( $fields['data'] ) ) {
			update_post_meta( $id, self::META_DATA, wp_slash( wp_json_encode( $fields['data'] ) ) );
		}
		return true;
	}

	/**
	 * One item, or null.
	 *
	 * @param int $id Item id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || Item_Post_Type::POST_TYPE !== $post->post_type ) {
			return null;
		}
		return self::to_array( $post );
	}

	/**
	 * Recent items, newest first.
	 *
	 * @param array $args module, type, author (0 = any), limit.
	 * @return array[]
	 */
	public static function query( array $args = array() ) {
		$meta = array();
		foreach ( array(
			'module' => self::META_MODULE,
			'type'   => self::META_TYPE,
		) as $field => $key ) {
			if ( ! empty( $args[ $field ] ) ) {
				$meta[] = array(
					'key'   => $key,
					'value' => sanitize_key( $args[ $field ] ),
				);
			}
		}
		$query = array(
			'post_type'      => Item_Post_Type::POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => isset( $args['limit'] ) ? min( 100, max( 1, (int) $args['limit'] ) ) : 20,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);
		if ( array() !== $meta ) {
			$query['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small private post type.
		}
		if ( ! empty( $args['author'] ) ) {
			$query['author'] = (int) $args['author'];
		}
		return array_map( array( __CLASS__, 'to_array' ), get_posts( $query ) );
	}

	/**
	 * Delete an item for good.
	 *
	 * @param int $id Item id.
	 * @return bool
	 */
	public static function delete( $id ) {
		return self::get( $id ) ? (bool) wp_delete_post( (int) $id, true ) : false;
	}

	/**
	 * Public shape of an item.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	private static function to_array( $post ) {
		$data = json_decode( (string) get_post_meta( $post->ID, self::META_DATA, true ), true );
		return array(
			'id'          => (int) $post->ID,
			'module'      => (string) get_post_meta( $post->ID, self::META_MODULE, true ),
			'type'        => (string) get_post_meta( $post->ID, self::META_TYPE, true ),
			'title'       => $post->post_title,
			'content'     => $post->post_content,
			'data'        => is_array( $data ) ? $data : array(),
			'source_post' => (int) get_post_meta( $post->ID, self::META_SOURCE, true ),
			'author'      => (int) $post->post_author,
			'modified'    => mysql_to_rfc3339( $post->post_modified_gmt ),
		);
	}
}
