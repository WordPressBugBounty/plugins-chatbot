<?php
/**
 * The finished article on the Write page (CR-10): read it, edit it in the
 * page, and fill its images (featured and every image spot) without
 * opening the editor.
 *
 * Image spots are the article's image blocks and its "[Image: …]"
 * placeholders, in order. Choosing an image for a spot replaces that block;
 * the featured image is set on the post. Only people who may edit the post
 * can read or change it here.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Writing;

use QuantumCloud\AiWriter\Images\Ai_Image;
use QuantumCloud\AiWriter\Images\Image_Search;
use QuantumCloud\AiWriter\Output\Block_Builder;
use QuantumCloud\AiWriter\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Draft view.
 */
final class Draft_View {

	/**
	 * The article for the Write page.
	 *
	 * @param int $post_id Post.
	 * @return array|WP_Error id, title, status, edit_url, html, featured, spots, images_note.
	 */
	public static function get( $post_id ) {
		$post = self::post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$featured = (int) get_post_thumbnail_id( $post );
		$spots    = array();
		foreach ( self::spots( parse_blocks( (string) $post->post_content ) ) as $index => $spot ) {
			$spots[] = array(
				'index' => $index,
				'kind'  => $spot['kind'],
				'id'    => $spot['id'],
				'url'   => $spot['id'] ? (string) wp_get_attachment_image_url( $spot['id'], 'medium' ) : '',
				'label' => $spot['label'],
			);
		}
		return array(
			'id'          => (int) $post->ID,
			'title'       => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'status'      => (string) $post->post_status,
			'edit_url'    => (string) get_edit_post_link( $post->ID, 'raw' ),
			// Rendered for reading; the same HTML is what "Edit here" starts from.
			'html'        => wp_kses_post( do_blocks( (string) $post->post_content ) ),
			'featured'    => $featured ? array(
				'id'  => $featured,
				'url' => (string) wp_get_attachment_image_url( $featured, 'medium' ),
			) : null,
			'spots'       => $spots,
			'images_note' => self::images_note( $featured, $spots ),
		);
	}

	/**
	 * Save an edit made on the Write page.
	 *
	 * @param int    $post_id Post.
	 * @param string $title   Title.
	 * @param string $content Content (block markup).
	 * @return array|WP_Error The article (get()).
	 */
	public static function save( $post_id, $title, $content ) {
		$post = self::post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		// wp_update_post() applies WordPress's own content filters (kses for users without unfiltered_html).
		$result = wp_update_post(
			wp_slash(
				array(
					'ID'           => (int) $post->ID,
					'post_title'   => '' !== trim( (string) $title ) ? sanitize_text_field( (string) $title ) : $post->post_title,
					'post_content' => (string) $content,
				)
			),
			true
		);
		return is_wp_error( $result ) ? $result : self::get( $post->ID );
	}

	/**
	 * Use an image for the featured image or one image spot.
	 *
	 * @param int        $post_id       Post.
	 * @param string|int $spot          'featured' or a spot index.
	 * @param int        $attachment_id Image.
	 * @return array|WP_Error The article (get()).
	 */
	public static function set_image( $post_id, $spot, $attachment_id ) {
		$post = self::post( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$attachment_id = (int) $attachment_id;
		if ( ! wp_attachment_is_image( $attachment_id ) || ! current_user_can( 'read_post', $attachment_id ) ) {
			return new WP_Error( 'qcld_wpbot_writer_bad_image', __( 'That image could not be used.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( 'featured' === $spot ) {
			set_post_thumbnail( $post, $attachment_id );
			return self::get( $post->ID );
		}
		$target = (int) $spot;
		$blocks = parse_blocks( (string) $post->post_content );
		$found  = false;
		$count  = -1;
		foreach ( $blocks as $i => $block ) {
			if ( null === self::spot( $block ) ) {
				continue;
			}
			++$count;
			if ( $count !== $target ) {
				continue;
			}
			$alt   = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			$label = self::spot( $block )['label'];
			$new   = parse_blocks(
				Block_Builder::to_blocks(
					array(
						array(
							'type'    => 'image',
							'id'      => $attachment_id,
							'url'     => (string) wp_get_attachment_image_url( $attachment_id, 'large' ),
							'alt'     => '' !== $alt ? $alt : $label,
							'caption' => (string) wp_get_attachment_caption( $attachment_id ),
						),
					)
				)
			);
			if ( $new ) {
				$blocks[ $i ] = $new[0];
				$found        = true;
			}
			break;
		}
		if ( ! $found ) {
			return new WP_Error( 'qcld_wpbot_writer_no_spot', __( 'That image place is no longer in the article.', 'chatbot' ), array( 'status' => 404 ) );
		}
		wp_update_post(
			wp_slash(
				array(
					'ID'           => (int) $post->ID,
					'post_content' => serialize_blocks( $blocks ),
				)
			)
		);
		return self::get( $post->ID );
	}

	/**
	 * Image spots in order: image blocks and "[Image: …]" placeholders.
	 *
	 * @param array[] $blocks Parsed blocks (top level).
	 * @return array[] kind (image|placeholder), id, label.
	 */
	private static function spots( array $blocks ) {
		$out = array();
		foreach ( $blocks as $block ) {
			$spot = self::spot( $block );
			if ( null !== $spot ) {
				$out[] = $spot;
			}
		}
		return $out;
	}

	/**
	 * One block as an image spot, or null.
	 *
	 * @param array $block Parsed block.
	 * @return array|null
	 */
	private static function spot( array $block ) {
		if ( 'core/image' === $block['blockName'] ) {
			$id = isset( $block['attrs']['id'] ) ? (int) $block['attrs']['id'] : 0;
			return array(
				'kind'  => 'image',
				'id'    => $id,
				'label' => preg_match( '/alt="([^"]*)"/', (string) $block['innerHTML'], $m ) ? html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) : '',
			);
		}
		$text  = trim( wp_strip_all_tags( (string) $block['innerHTML'] ) );
		$class = isset( $block['attrs']['className'] ) && false !== strpos( (string) $block['attrs']['className'], 'qcwr-image-placeholder' );
		// After an edit in the page the class may be gone; the "[Image: …]" text stays.
		$text_spot = preg_match( '/^\[[^\]]+\]$/u', $text ) && 0 === stripos( $text, '[' . __( 'Image:', 'chatbot' ) );
		if ( 'core/paragraph' !== $block['blockName'] || ( ! $class && ! $text_spot ) ) {
			return null;
		}
		return array(
			'kind'  => 'placeholder',
			'id'    => 0,
			'label' => trim( preg_replace( '/^\[' . preg_quote( __( 'Image:', 'chatbot' ), '/' ) . '\s*|\]$/u', '', $text ) ),
		);
	}

	/**
	 * Why images are missing, and what to do (shown on the Write page).
	 *
	 * @param int     $featured Featured image id.
	 * @param array[] $spots    Spots.
	 * @return string ('' when nothing is missing).
	 */
	public static function images_note( $featured, array $spots ) {
		$missing = ! $featured || array() !== array_filter(
			$spots,
			static function ( $spot ) {
				return 'placeholder' === $spot['kind'];
			}
		);
		if ( ! $missing ) {
			return '';
		}
		$mode = (string) Settings::get( 'image_fill' );
		if ( 'none' === $mode ) {
			return __( 'Images are turned off in the image settings.', 'chatbot' );
		}
		if ( 'placeholder' === $mode ) {
			return __( 'The image settings ask for placeholders you replace yourself.', 'chatbot' );
		}
		$stock = array_diff( Image_Search::stock_sources(), array( 'openverse' ) );
		if ( array() === $stock && ! Ai_Image::provider() ) {
			return __( 'Only Openverse and your Media Library were searched and they had nothing that fits. Add a free Pexels or Pixabay key, or an OpenAI or Gemini key for AI images, in the image settings.', 'chatbot' );
		}
		if ( ! Ai_Image::provider() ) {
			return __( 'No matching photo was found. Pick one below, or add an OpenAI or Gemini key to create images with AI.', 'chatbot' );
		}
		return __( 'Some images could not be found or created. Pick or create them below.', 'chatbot' );
	}

	/**
	 * The post, if the user may edit it.
	 *
	 * @param int $post_id Post.
	 * @return \WP_Post|WP_Error
	 */
	private static function post( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return new WP_Error( 'qcld_wpbot_writer_no_post', __( 'That draft no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'qcld_wpbot_writer_cannot_edit', __( 'You are not allowed to edit this content.', 'chatbot' ), array( 'status' => 403 ) );
		}
		return $post;
	}
}
