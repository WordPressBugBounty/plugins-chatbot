<?php
/**
 * Fills the image spots planned in the outline. Runs after the text, so an
 * image failure never loses any writing: a spot that cannot be filled gets
 * a visible placeholder the owner replaces.
 *
 * Fill order comes from settings: "auto" (Media Library match, then stock:
 * Pexels / Pixabay / Openverse), "ai" (owner's AI key, then auto),
 * "placeholder" or "none".
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Images;

use QuantumCloud\AiWriter\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Image step.
 */
final class Image_Filler {

	/**
	 * Step handler.
	 *
	 * @param array $step Step.
	 * @param array $job  Job.
	 * @return array
	 */
	public static function step( array $step, array $job ) {
		$outline = $job['payload']['outline'];
		$mode    = (string) Settings::get( 'image_fill' );
		// The header image may have its own choice (CR-10).
		$header = (string) Settings::get( 'image_fill_featured' );
		$header = '' === $header || 'same' === $header ? $mode : $header;
		$out    = array(
			'sections'       => array(),
			'featured_id'    => 0,
			'attachment_ids' => array(),
			'package'        => array(),
			'summary'        => array(
				'filled'       => 0,
				'placeholders' => 0,
				'failed'       => array(),
			),
		);
		if ( 'none' === $mode && 'none' === $header ) {
			return $out;
		}

		$spots = array();
		if ( ! empty( $outline['featured_image'] ) ) {
			$spots['featured'] = $outline['featured_image'];
		}
		foreach ( $outline['sections'] as $index => $section ) {
			if ( ! empty( $section['image'] ) ) {
				$spots[ $index ] = $section['image'];
			}
		}

		foreach ( $spots as $key => $spot ) {
			$spot_mode = 'featured' === $key ? $header : $mode;
			if ( 'none' === $spot_mode ) {
				continue;
			}
			$alt = '' !== $spot['alt'] ? $spot['alt'] : $spot['query'];
			$id  = 'placeholder' === $spot_mode ? 0 : self::fill( $spot, $alt, $spot_mode, (int) $job['id'], $out['summary']['failed'], 'featured' === $key );
			if ( $id ) {
				++$out['summary']['filled'];
				$out['attachment_ids'][] = $id;
				$out['package'][]        = array(
					'id'     => $id,
					'url'    => (string) wp_get_attachment_url( $id ),
					'alt'    => $alt,
					'credit' => (string) get_post_field( 'post_excerpt', $id ),
				);
				if ( 'featured' === $key ) {
					$out['featured_id'] = $id;
				} else {
					$out['sections'][ $key ] = array(
						'type'    => 'image',
						'id'      => $id,
						'url'     => (string) wp_get_attachment_image_url( $id, 'large' ),
						'alt'     => $alt,
						'caption' => (string) get_post_field( 'post_excerpt', $id ),
					);
				}
			} elseif ( 'featured' !== $key ) {
				++$out['summary']['placeholders'];
				$out['sections'][ $key ] = array(
					'type'  => 'placeholder_image',
					/* translators: %s: what the image should show. */
					'label' => sprintf( __( 'Image: %s', 'chatbot' ), $alt ),
				);
			}
		}
		return $out;
	}

	/**
	 * Try the sources in order for one spot.
	 *
	 * @param array  $spot   Spot.
	 * @param string $alt    Alt text.
	 * @param string $mode   Fill mode.
	 * @param int    $job_id Job id.
	 * @param array  $failed Collected failure notes (by reference).
	 * @param bool   $featured The header (featured) image.
	 * @return int Attachment id or 0.
	 */
	private static function fill( array $spot, $alt, $mode, $job_id, array &$failed, $featured = false ) {
		if ( 'ai' === $mode ) {
			$id = self::ai( $spot, $alt, $job_id, $failed );
			if ( $id ) {
				return $id;
			}
		}
		$query = '' !== $spot['query'] ? $spot['query'] : $alt;
		foreach ( array_merge( array( 'media' ), Image_Search::stock_sources() ) as $source ) {
			$candidates = 'media' === $source
				// Automatic picks skip images used elsewhere and ones too small for the spot.
				? Image_Search::media_for_spot( $query, $featured ? 1200 : 600 )
				: Image_Search::search( $source, $query, 1 );
			if ( array() === $candidates || '' === $candidates[0]['url'] ) {
				continue;
			}
			$id = Image_Importer::from_url( $candidates[0], $alt );
			if ( ! is_wp_error( $id ) ) {
				return (int) $id;
			}
			$failed[] = $id->get_error_message();
		}
		// Free sources found nothing: AI, when chosen as the last resort (CR-10).
		if ( 'library_stock_ai' === $mode ) {
			return self::ai( $spot, $alt, $job_id, $failed );
		}
		return 0;
	}

	/**
	 * Create one image with AI (0 when no prompt or no image provider).
	 *
	 * @param array  $spot   Spot.
	 * @param string $alt    Alt text.
	 * @param int    $job_id Job.
	 * @param array  $failed Error messages (by reference).
	 * @return int Attachment id.
	 */
	private static function ai( array $spot, $alt, $job_id, array &$failed ) {
		$prompt = '' !== $spot['prompt'] ? $spot['prompt'] : ( '' !== $alt ? 'A photo: ' . $alt : '' );
		if ( '' === $prompt || ! Ai_Image::provider() ) {
			return 0;
		}
		$id = Ai_Image::generate( $prompt, $alt, 0, $job_id );
		if ( is_wp_error( $id ) ) {
			$failed[] = $id->get_error_message();
			return 0;
		}
		return (int) $id;
	}
}
