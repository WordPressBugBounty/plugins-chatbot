<?php
/**
 * "Link to this article from…" (CR-6 W1, Free).
 *
 * A brief can name up to five existing pages that should link to the new
 * article (from SEO & AI Search's Topic Map). AI Writer remembers them on
 * the finished draft and, once the article is published, lists them in the
 * editor with an "Add link" button each. A page is only changed when the
 * owner clicks: a short "Read next" line with the link is added at the end
 * of that page. Nothing is changed automatically.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Writing;

use QuantumCloud\AiWriter\Module;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Link-from suggestions.
 */
final class Link_From {

	/**
	 * Pages that should link to the post (post meta).
	 */
	const META = '_qcld_wpbot_writer_link_from';

	/**
	 * Pages that already got the link (post meta).
	 */
	const DONE = '_qcld_wpbot_writer_link_from_done';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'qcld_wpbot_writer/content_finalized', array( __CLASS__, 'remember' ), 10, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Remember the brief's link_from pages on the finished draft.
	 *
	 * @param int   $post_id Post.
	 * @param array $brief   Brief.
	 * @return void
	 */
	public static function remember( $post_id, $brief ) {
		$ids = Brief::post_ids( isset( $brief['link_from'] ) ? $brief['link_from'] : array() );
		$ids = array_values( array_diff( $ids, array( (int) $post_id ) ) );
		if ( $post_id && array() !== $ids ) {
			update_post_meta( (int) $post_id, self::META, $ids );
		}
	}

	/**
	 * The suggestions for a post: pages the current user can see.
	 *
	 * @param int $post_id Post.
	 * @return array[] id, title, url, done, can_edit.
	 */
	public static function suggestions( $post_id ) {
		$ids  = Brief::post_ids( get_post_meta( (int) $post_id, self::META, true ) );
		$done = array_map( 'absint', (array) get_post_meta( (int) $post_id, self::DONE, true ) );
		$out  = array();
		foreach ( $ids as $id ) {
			$page = get_post( $id );
			if ( ! $page || 'trash' === $page->post_status || ! current_user_can( 'read_post', $id ) ) {
				continue;
			}
			$out[] = array(
				'id'       => (int) $id,
				'title'    => html_entity_decode( get_the_title( $page ), ENT_QUOTES, 'UTF-8' ),
				'url'      => (string) get_permalink( $page ),
				'edit'     => (string) get_edit_post_link( $id, 'raw' ),
				'done'     => in_array( (int) $id, $done, true ),
				'can_edit' => current_user_can( 'edit_post', $id ),
			);
		}
		return $out;
	}

	/**
	 * REST routes.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			'wpbot-writer/v1',
			'/editor/(?P<post>\d+)/link-from/(?P<from>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'add_link' ),
				'permission_callback' => static function ( WP_REST_Request $request ) {
					return current_user_can( Module::cap( 'use' ) ) && current_user_can( 'edit_post', (int) $request['from'] );
				},
				'args'                => array(
					'post' => array(
						'sanitize_callback' => 'absint',
						'validate_callback' => static function ( $value ) {
							return is_numeric( $value ) && (int) $value > 0;
						},
					),
					'from' => array(
						'sanitize_callback' => 'absint',
						'validate_callback' => static function ( $value ) {
							return is_numeric( $value ) && (int) $value > 0;
						},
					),
				),
			)
		);
	}

	/**
	 * POST: add a "Read next" link to the article at the end of one page.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function add_link( WP_REST_Request $request ) {
		$post_id = (int) $request['post'];
		$from_id = (int) $request['from'];
		$post    = get_post( $post_id );
		$from    = get_post( $from_id );
		if ( ! $post || ! $from || ! in_array( $from_id, Brief::post_ids( get_post_meta( $post_id, self::META, true ) ), true ) ) {
			return new WP_Error( 'qcld_wpbot_writer_no_link', __( 'This page is not on the article’s list.', 'chatbot' ), array( 'status' => 404 ) );
		}
		if ( 'publish' !== $post->post_status ) {
			return new WP_Error( 'qcld_wpbot_writer_not_live', __( 'Publish the article first, so the link works for visitors.', 'chatbot' ), array( 'status' => 409 ) );
		}
		$url = (string) get_permalink( $post );
		if ( false !== strpos( (string) $from->post_content, $url ) ) {
			self::mark_done( $post_id, $from_id );
			return rest_ensure_response( array( 'suggestions' => self::suggestions( $post_id ) ) );
		}
		$link = sprintf(
			/* translators: %s: link to the new article. */
			esc_html__( 'Read next: %s', 'chatbot' ),
			'<a href="' . esc_url( $url ) . '">' . esc_html( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) ) . '</a>'
		);
		$html   = has_blocks( $from->post_content ) ? "\n\n<!-- wp:paragraph -->\n<p>" . $link . "</p>\n<!-- /wp:paragraph -->" : "\n\n<p>" . $link . '</p>';
		$result = wp_update_post(
			array(
				'ID'           => $from_id,
				'post_content' => wp_slash( rtrim( (string) $from->post_content ) . $html ),
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'qcld_wpbot_writer_link_failed', $result->get_error_message(), array( 'status' => 500 ) );
		}
		self::mark_done( $post_id, $from_id );
		return rest_ensure_response( array( 'suggestions' => self::suggestions( $post_id ) ) );
	}

	/**
	 * Record that a page got the link.
	 *
	 * @param int $post_id Article.
	 * @param int $from_id Page.
	 * @return void
	 */
	private static function mark_done( $post_id, $from_id ) {
		$done   = array_map( 'absint', (array) get_post_meta( $post_id, self::DONE, true ) );
		$done[] = (int) $from_id;
		update_post_meta( $post_id, self::DONE, array_values( array_unique( array_filter( $done ) ) ) );
	}
}
