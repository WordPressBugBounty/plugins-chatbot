<?php
/**
 * "Create with AI" images (screen 06). Uses AI Studio when it is active,
 * through the same contract AI Writer uses:
 * apply_filters( 'qcld_ai_studio/available', false ) and
 * qcld_ai_studio_generate_image( prompt, style, size, source_module ).
 * Without AI Studio the tab explains what is needed; the other image
 * sources (Media Library, From the post, Upload) always work.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Media;

use QuantumCloud\Social\Writing\Writing_Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * AI images.
 */
final class Ai_Images {

	/**
	 * Whether "Create with AI" can run.
	 *
	 * @return bool
	 */
	public static function available() {
		if ( 'off' === Writing_Settings::get( 'image_model' ) ) {
			return false;
		}
		return (bool) apply_filters( 'qcld_ai_studio/available', false ) && function_exists( 'qcld_ai_studio_generate_image' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- AI Studio's own filter.
	}

	/**
	 * Styles and sizes AI Studio offers.
	 *
	 * @return array{available:bool, styles:array, sizes:array, reason:string}
	 */
	public static function view() {
		if ( ! self::available() ) {
			return array(
				'available' => false,
				'styles'    => array(),
				'sizes'     => array(),
				'reason'    => 'off' === Writing_Settings::get( 'image_model' )
					? __( 'Create with AI is turned off in Settings › Writing.', 'chatbot' )
					: __( 'Create with AI needs AI Studio. Until then, use the Media Library, an image from the post, or upload one.', 'chatbot' ),
			);
		}
		$options = (array) apply_filters( 'qcld_ai_studio/image_options', array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- AI Studio's own filter.
		return array(
			'available' => true,
			'styles'    => isset( $options['styles'] ) ? array_values( (array) $options['styles'] ) : array(),
			'sizes'     => isset( $options['sizes'] ) ? array_values( (array) $options['sizes'] ) : array(),
			'reason'    => '',
		);
	}

	/**
	 * Create images; AI Studio saves them to the Media Library.
	 *
	 * @param string $prompt Description.
	 * @param string $style  Style id.
	 * @param string $size   Size id.
	 * @return array<int,array{id:int, thumb:string, alt:string}>|WP_Error
	 */
	public static function create( $prompt, $style = '', $size = '' ) {
		if ( ! self::available() ) {
			return new WP_Error( 'qcld_wpbot_social_no_studio', self::view()['reason'], array( 'status' => 400 ) );
		}
		$prompt = trim( sanitize_textarea_field( (string) $prompt ) );
		if ( '' === $prompt ) {
			return new WP_Error( 'qcld_wpbot_social_image_prompt', __( 'Describe the image.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$result = qcld_ai_studio_generate_image(
			array(
				'prompt'        => $prompt,
				'style'         => sanitize_key( (string) $style ),
				'size'          => sanitize_key( (string) $size ),
				'source_module' => 'social',
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$ids = array();
		if ( is_numeric( $result ) ) {
			$ids[] = (int) $result;
		} elseif ( is_array( $result ) ) {
			foreach ( array( 'attachment_id', 'id' ) as $key ) {
				if ( isset( $result[ $key ] ) ) {
					$ids[] = (int) $result[ $key ];
				}
			}
			foreach ( array( 'attachments', 'attachment_ids', 'images' ) as $key ) {
				foreach ( isset( $result[ $key ] ) ? (array) $result[ $key ] : array() as $item ) {
					$ids[] = (int) ( is_array( $item ) ? ( isset( $item['id'] ) ? $item['id'] : ( isset( $item['attachment_id'] ) ? $item['attachment_id'] : 0 ) ) : $item );
				}
			}
		}
		$out = array();
		foreach ( array_unique( array_filter( $ids ) ) as $id ) {
			$thumb = wp_get_attachment_image_url( $id, 'thumbnail' );
			if ( $thumb ) {
				$out[] = array(
					'id'    => $id,
					'thumb' => $thumb,
					'alt'   => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
				);
			}
		}
		return array() === $out ? new WP_Error( 'qcld_wpbot_social_image_none', __( 'AI Studio did not return an image. Try again.', 'chatbot' ), array( 'status' => 502 ) ) : $out;
	}
}
