<?php
/**
 * Server half of the pre-publish image check: facts the editor cannot see
 * on its own (file sizes, use in other posts, required credits).
 *
 * The editor combines these with what it can see (missing featured image,
 * alt text, placeholders, Unsplash-only) and offers a fix for each. The
 * check never blocks publishing.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Images;

use QuantumCloud\AiWriter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Image facts for the checklist.
 */
final class Image_Check {

	/**
	 * Files above this size are flagged as oversized (bytes).
	 */
	const MAX_BYTES = 512000;

	/**
	 * Facts about the images of a post.
	 *
	 * @param int   $post_id Post being edited.
	 * @param int[] $ids     Attachment ids used in the content and as featured image.
	 * @return array[] id => bytes, width, oversized, reused_in, credit, needs_credit.
	 */
	public static function facts( $post_id, array $ids ) {
		$out       = array();
		$max_width = (int) Settings::get( 'image_max_width' );
		foreach ( array_slice( array_unique( array_map( 'absint', $ids ) ), 0, 50 ) as $id ) {
			if ( ! $id || ! wp_attachment_is_image( $id ) ) {
				continue;
			}
			$file    = get_attached_file( $id );
			$bytes   = $file && file_exists( $file ) ? (int) filesize( $file ) : 0;
			$meta    = wp_get_attachment_metadata( $id );
			$width   = is_array( $meta ) && isset( $meta['width'] ) ? (int) $meta['width'] : 0;
			$license = get_post_meta( $id, '_qcld_wpbot_writer_image_license', true );
			$credit  = is_array( $license ) && ! empty( $license['credit'] ) ? Image_Importer::credit_text( $license ) : '';

			$out[ $id ] = array(
				'id'           => $id,
				'bytes'        => $bytes,
				'width'        => $width,
				'oversized'    => $bytes > self::MAX_BYTES || ( $max_width && $width > $max_width * 1.5 ),
				'reused_in'    => self::reused_in( $id, (int) $post_id ),
				'credit'       => $credit,
				// Openverse licences (except CC0 and public domain) require the credit to be shown.
				'needs_credit' => '' !== $credit && isset( $license['source'] ) && 'openverse' === $license['source'] && ! preg_match( '/^(CC0|PDM)/i', isset( $license['license'] ) ? (string) $license['license'] : '' ),
				'alt'          => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			);
		}
		return $out;
	}

	/**
	 * Other posts (any status but trash) that use the same image.
	 *
	 * @param int $id      Attachment id.
	 * @param int $post_id Post to ignore.
	 * @return array[] id, title (at most 3).
	 */
	private static function reused_in( $id, $post_id ) {
		global $wpdb;
		$like  = '%' . $wpdb->esc_like( 'wp-image-' . $id . '"' ) . '%';
		$like2 = '%' . $wpdb->esc_like( 'wp-image-' . $id . ' ' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live check while editing; small result.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_thumbnail_id'
				WHERE p.ID <> %d AND p.post_status NOT IN ('trash','auto-draft','inherit') AND p.post_type NOT IN ('revision','attachment')
				AND ( p.post_content LIKE %s OR p.post_content LIKE %s OR m.meta_value = %s )
				LIMIT 3",
				$post_id,
				$like,
				$like2,
				(string) $id
			)
		);
		$out = array();
		foreach ( (array) $ids as $other ) {
			if ( current_user_can( 'read_post', (int) $other ) ) {
				$out[] = array(
					'id'    => (int) $other,
					'title' => get_the_title( (int) $other ),
				);
			}
		}
		return $out;
	}
}
