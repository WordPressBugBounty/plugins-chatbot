<?php
/**
 * Update copies for live content. Accepted changes to a published page
 * never overwrite it silently: they go into a private draft copy the owner
 * opens, checks and then publishes into the original (WordPress keeps a
 * revision of the original, so the change can be rolled back).
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Improve;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Seo\Seo_Adapter;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Update copies.
 */
final class Updates {

	const META_OF  = '_qcld_wpbot_writer_update_of';
	const META_SEO = '_qcld_wpbot_writer_update_seo';
	const META_NEW = '_qcld_wpbot_writer_pending_update';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_filter( 'display_post_states', array( __CLASS__, 'post_states' ), 10, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'forget' ) );
		add_action( 'admin_notices', array( __CLASS__, 'classic_notice' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'keep_draft' ), 10, 2 );
	}

	/**
	 * An update copy is never published as a post of its own (that would
	 * duplicate the live page): it stays a draft until "Publish the update"
	 * copies it into the original.
	 *
	 * @param array $data    Post data to save.
	 * @param array $postarr Raw post array (with ID).
	 * @return array
	 */
	public static function keep_draft( $data, $postarr ) {
		$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( $id && in_array( $data['post_status'], array( 'publish', 'future', 'private' ), true ) && self::original_of( $id ) ) {
			$data['post_status'] = 'draft';
		}
		return $data;
	}

	/**
	 * Create the update copy of a live post.
	 *
	 * @param \WP_Post   $post    Original.
	 * @param string     $content New content.
	 * @param array|null $meta    title, description for the SEO plugin.
	 * @return int|WP_Error Copy id.
	 */
	public static function create( $post, $content, $meta ) {
		$existing = (int) get_post_meta( $post->ID, self::META_NEW, true );
		if ( $existing && get_post( $existing ) ) {
			wp_delete_post( $existing, true );
		}
		$copy_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => $post->post_type,
					'post_status'  => 'draft',
					'post_author'  => get_current_user_id(),
					'post_title'   => $post->post_title,
					'post_excerpt' => $post->post_excerpt,
					'post_content' => $content,
					'menu_order'   => $post->menu_order,
				)
			),
			true
		);
		if ( is_wp_error( $copy_id ) ) {
			return $copy_id;
		}
		update_post_meta( $copy_id, self::META_OF, (int) $post->ID );
		if ( $meta ) {
			update_post_meta( $copy_id, self::META_SEO, wp_slash( $meta ) );
		}
		$thumbnail = get_post_thumbnail_id( $post );
		if ( $thumbnail ) {
			set_post_thumbnail( $copy_id, $thumbnail );
		}
		update_post_meta( $post->ID, self::META_NEW, (int) $copy_id );
		return (int) $copy_id;
	}

	/**
	 * The original a copy updates (0 when it is not a copy).
	 *
	 * @param int $copy_id Post id.
	 * @return int
	 */
	public static function original_of( $copy_id ) {
		return (int) get_post_meta( (int) $copy_id, self::META_OF, true );
	}

	/**
	 * Whether the current user may publish a copy into its original.
	 *
	 * @param int $copy_id Copy.
	 * @return bool
	 */
	public static function can_publish( $copy_id ) {
		$original = get_post( self::original_of( $copy_id ) );
		if ( ! $original ) {
			return false;
		}
		$type = get_post_type_object( $original->post_type );
		return $type && current_user_can( Module::cap( 'publish' ) ) && current_user_can( 'edit_post', $original->ID ) && current_user_can( $type->cap->publish_posts );
	}

	/**
	 * Publish a copy into its original, then delete the copy.
	 *
	 * @param int $copy_id Copy.
	 * @return array|WP_Error original_id, view_url.
	 */
	public static function publish( $copy_id ) {
		$copy     = get_post( (int) $copy_id );
		$original = get_post( self::original_of( $copy_id ) );
		if ( ! $copy || ! $original ) {
			return new WP_Error( 'qcld_wpbot_writer_no_update', __( 'This update no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		if ( ! self::can_publish( $copy->ID ) ) {
			return new WP_Error( 'qcld_wpbot_writer_cannot_publish', __( 'You are not allowed to publish this content.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$saved = wp_update_post(
			wp_slash(
				array(
					'ID'           => $original->ID,
					'post_title'   => $copy->post_title,
					'post_excerpt' => $copy->post_excerpt,
					'post_content' => $copy->post_content,
				)
			),
			true
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$seo = get_post_meta( $copy->ID, self::META_SEO, true );
		if ( is_array( $seo ) ) {
			Seo_Adapter::write( $original->ID, isset( $seo['title'] ) ? $seo['title'] : '', isset( $seo['description'] ) ? $seo['description'] : '' );
		}
		$thumbnail = get_post_thumbnail_id( $copy );
		if ( $thumbnail && get_post_thumbnail_id( $original ) !== $thumbnail ) {
			set_post_thumbnail( $original->ID, $thumbnail );
		}
		delete_post_meta( $original->ID, self::META_NEW );
		wp_delete_post( $copy->ID, true );

		/**
		 * An AI Writer update went live (Site Insight and others may record it).
		 *
		 * @param int $post_id Original post.
		 */
		do_action( 'qcld_wpbot_writer/update_published', (int) $original->ID );
		return array(
			'original_id' => (int) $original->ID,
			'view_url'    => (string) get_permalink( $original->ID ),
			'edit_url'    => (string) get_edit_post_link( $original->ID, 'raw' ),
		);
	}

	/**
	 * Delete a copy without publishing it.
	 *
	 * @param int $copy_id Copy.
	 * @return true|WP_Error
	 */
	public static function discard( $copy_id ) {
		$original = self::original_of( $copy_id );
		if ( ! $original || ! current_user_can( 'delete_post', (int) $copy_id ) ) {
			return new WP_Error( 'qcld_wpbot_writer_no_update', __( 'This update no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		wp_delete_post( (int) $copy_id, true );
		delete_post_meta( $original, self::META_NEW );
		return true;
	}

	/**
	 * Label copies in post lists.
	 *
	 * @param string[] $states States.
	 * @param \WP_Post $post   Post.
	 * @return string[]
	 */
	public static function post_states( $states, $post ) {
		if ( self::original_of( $post->ID ) ) {
			$states['qcwr_update'] = __( 'AI Writer update', 'chatbot' );
		} elseif ( (int) get_post_meta( $post->ID, self::META_NEW, true ) ) {
			$states['qcwr_update_waiting'] = __( 'Update waiting', 'chatbot' );
		}
		return $states;
	}

	/**
	 * Keep the links tidy when either post is deleted.
	 *
	 * @param int $post_id Post being deleted.
	 * @return void
	 */
	public static function forget( $post_id ) {
		$original = self::original_of( $post_id );
		if ( $original ) {
			delete_post_meta( $original, self::META_NEW );
			return;
		}
		$copy = (int) get_post_meta( $post_id, self::META_NEW, true );
		if ( $copy && get_post( $copy ) ) {
			wp_delete_post( $copy, true );
		}
	}

	/**
	 * Classic Editor: say what this screen is (the block editor shows the
	 * same through the AI Writer script).
	 *
	 * @return void
	 */
	public static function classic_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post   = get_post();
		if ( ! $screen || 'post' !== $screen->base || ! $post || use_block_editor_for_post( $post ) ) {
			return;
		}
		$original = self::original_of( $post->ID );
		$copy     = (int) get_post_meta( $post->ID, self::META_NEW, true );
		if ( $original ) {
			printf(
				'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'This is an AI Writer update of a live page. Check it, then publish it from AI Writer > Write > Improve, or from the AI Writer box.', 'chatbot' ),
				esc_url( (string) get_edit_post_link( $original ) ),
				esc_html__( 'Open the live version', 'chatbot' )
			);
		} elseif ( $copy && get_post( $copy ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'An AI Writer update for this page is waiting for review.', 'chatbot' ),
				esc_url( (string) get_edit_post_link( $copy ) ),
				esc_html__( 'Review the update', 'chatbot' )
			);
		}
	}
}
