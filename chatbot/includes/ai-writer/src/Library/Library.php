<?php
/**
 * Library: what AI Writer made, ready to reuse.
 *
 * - Finished pieces: posts, pages and products written with AI Writer
 *   (they carry the writing job's id), with their content package from the
 *   WPBot Core Content Library (summary, key points). Only pieces the user
 *   can edit are listed.
 * - Quick write items: the user's own saved Quick write texts (private
 *   Content Library items).
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Library;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Quick\Quick_Write;
use QuantumCloud\AiWriter\Quick\Templates;
use QuantumCloud\WPBotCore\Library\Items;
use QuantumCloud\WPBotCore\Library\Packages;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Library service.
 */
final class Library {

	const PER_PAGE = 20;

	/**
	 * Statuses a piece can have in the list.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'draft', 'pending', 'future', 'publish', 'private' );

	/**
	 * Finished pieces, newest first.
	 *
	 * @param string $search Search text.
	 * @param string $status One status or '' for all.
	 * @param int    $page   Page (1-based).
	 * @param array  $extra  Extra filters from extensions (sanitised strings).
	 * @return array{items: array[], total: int, pages: int}
	 */
	public static function pieces( $search = '', $status = '', $page = 1, array $extra = array() ) {
		$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
		$query = array(
			'post_type'      => $types,
			'post_status'    => in_array( $status, self::STATUSES, true ) ? $status : self::STATUSES,
			'posts_per_page' => self::PER_PAGE,
			'paged'          => max( 1, (int) $page ),
			'orderby'        => 'modified',
			'order'          => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One EXISTS clause on AI Writer's own meta key.
			'meta_query'     => array(
				array(
					'key'     => '_qcld_wpbot_writer_job_id',
					'compare' => 'EXISTS',
				),
			),
		);
		if ( '' !== $search ) {
			$query['s'] = $search;
		}
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$query['author'] = get_current_user_id();
		}

		/**
		 * The finished-pieces query (extensions add filters, for example the
		 * review status).
		 *
		 * @param array $query WP_Query arguments.
		 * @param array $extra Extra request values (sanitised strings).
		 */
		$query = (array) apply_filters( 'qcld_wpbot_writer/library_query', $query, $extra );
		$found = new \WP_Query( $query );
		$items = array();
		foreach ( $found->posts as $post ) {
			if ( current_user_can( 'edit_post', $post->ID ) ) {
				$items[] = self::piece( $post );
			}
		}
		return array(
			'items' => $items,
			'total' => (int) $found->found_posts,
			'pages' => (int) $found->max_num_pages,
		);
	}

	/**
	 * One piece.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	public static function piece( $post ) {
		$type    = get_post_type_object( $post->post_type );
		$package = class_exists( Packages::class ) ? Packages::get( $post->ID ) : null;
		$points  = $package && is_array( $package['key_points'] ) ? array_slice( array_map( 'strval', $package['key_points'] ), 0, 5 ) : array();
		$piece   = array(
			'id'           => (int) $post->ID,
			'title'        => '' !== $post->post_title ? html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) : __( '(no title)', 'chatbot' ),
			'type'         => $type ? $type->labels->singular_name : $post->post_type,
			'status'       => $post->post_status,
			'status_label' => (string) get_post_status_object( $post->post_status )->label,
			// Drafts may have no GMT time yet: send the site's own formatted date.
			'modified'     => (string) get_the_modified_date( '', $post ),
			'words'        => \QuantumCloud\AiWriter\Writing\Prompts::word_count( wp_strip_all_tags( excerpt_remove_blocks( $post->post_content ) ) ),
			'summary'      => $package ? (string) $package['summary'] : '',
			'key_points'   => $points,
			'question'     => (string) get_post_meta( $post->ID, '_qcld_wpbot_writer_answer', true ),
			'edit_url'     => (string) get_edit_post_link( $post->ID, 'raw' ),
			'view_url'     => 'publish' === $post->post_status ? (string) get_permalink( $post ) : '',
			'improve_url'  => admin_url( 'admin.php?page=wpbot-ai-writer&improve=' . (int) $post->ID ),
		);

		/**
		 * One finished piece in the Library (extensions add fields).
		 *
		 * @param array    $piece Piece.
		 * @param \WP_Post $post  Post.
		 */
		return (array) apply_filters( 'qcld_wpbot_writer/library_piece', $piece, $post );
	}

	/**
	 * The current user's Quick write items, newest first.
	 *
	 * @param string $search Search text.
	 * @return array[]
	 */
	public static function items( $search = '' ) {
		$labels = array();
		foreach ( Templates::all() as $id => $template ) {
			$labels[ $id ] = $template['label'];
		}
		$out = array();
		foreach ( Items::query(
			array(
				'module' => Module::ID,
				'type'   => Quick_Write::ITEM_TYPE,
				'author' => get_current_user_id(),
				'limit'  => 100,
			)
		) as $item ) {
			if ( '' !== $search && false === stripos( $item['title'] . ' ' . $item['content'], $search ) ) {
				continue;
			}
			$template = isset( $item['data']['template'] ) ? (string) $item['data']['template'] : '';
			$source   = $item['source_post'] && current_user_can( 'read_post', $item['source_post'] ) ? $item['source_post'] : 0;
			$out[]    = array(
				'id'           => $item['id'],
				'title'        => $item['title'],
				'text'         => $item['content'],
				'template'     => isset( $labels[ $template ] ) ? $labels[ $template ] : $template,
				'modified'     => $item['modified'],
				'source_title' => $source ? html_entity_decode( get_the_title( $source ), ENT_QUOTES, 'UTF-8' ) : '',
				'source_url'   => $source ? (string) get_edit_post_link( $source, 'raw' ) : '',
			);
		}
		return $out;
	}

	/**
	 * Delete one of the user's Quick write items.
	 *
	 * @param int $id Item id.
	 * @return true|WP_Error
	 */
	public static function delete_item( $id ) {
		$item = Items::get( $id );
		if ( ! $item || Module::ID !== $item['module'] || Quick_Write::ITEM_TYPE !== $item['type'] ) {
			return new WP_Error( 'qcld_wpbot_writer_no_item', __( 'This item no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		if ( get_current_user_id() !== $item['author'] && ! current_user_can( Module::cap( 'settings' ) ) ) {
			return new WP_Error( 'qcld_wpbot_writer_not_yours', __( 'You can only delete your own items.', 'chatbot' ), array( 'status' => 403 ) );
		}
		return Items::delete( $id ) ? true : new WP_Error( 'qcld_wpbot_writer_delete_failed', __( 'The item could not be deleted.', 'chatbot' ), array( 'status' => 500 ) );
	}
}
