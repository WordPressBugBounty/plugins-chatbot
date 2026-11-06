<?php
/**
 * Brings an image into the Media Library: download, resize to a sensible
 * width, WebP where the server supports it, alt text, credit caption, and
 * the licence stored with the image.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Images;

use QuantumCloud\AiWriter\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Media Library import.
 */
final class Image_Importer {

	/**
	 * Import a candidate from a URL.
	 *
	 * @param array  $candidate From Image_Search.
	 * @param string $alt       Alt text.
	 * @param int    $post_id   Post to attach to (0 = none).
	 * @return int|WP_Error Attachment id.
	 */
	public static function from_url( array $candidate, $alt, $post_id = 0 ) {
		if ( ! empty( $candidate['attachment_id'] ) ) {
			return (int) $candidate['attachment_id'];
		}
		self::load_admin_files();
		$url = isset( $candidate['url'] ) ? esc_url_raw( $candidate['url'] ) : '';
		if ( '' === $url ) {
			return new WP_Error( 'qcld_wpbot_writer_no_image', __( 'The image has no address.', 'chatbot' ) );
		}
		/** This filter is documented in Image_Search::get_json(). */
		$download_url = (string) apply_filters( 'qcld_wpbot_writer/image_endpoint', $url, 'download' );
		$tmp          = download_url( $download_url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		$name = sanitize_file_name( wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		if ( ! preg_match( '/\.(jpe?g|png|gif|webp)$/i', $name ) ) {
			$name = 'image-' . substr( md5( $url ), 0, 8 ) . '.jpg';
		}
		return self::sideload( $tmp, $name, $candidate, $alt, $post_id );
	}

	/**
	 * Import raw image bytes (AI images).
	 *
	 * @param string $bytes     Image data.
	 * @param string $extension png, jpg or webp.
	 * @param array  $candidate Credit and licence fields.
	 * @param string $alt       Alt text.
	 * @param int    $post_id   Post id.
	 * @return int|WP_Error
	 */
	public static function from_bytes( $bytes, $extension, array $candidate, $alt, $post_id = 0 ) {
		self::load_admin_files();
		$tmp = wp_tempnam( 'qcwr-ai-image' );
		if ( ! $tmp || false === file_put_contents( $tmp, $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temp file for sideload.
			return new WP_Error( 'qcld_wpbot_writer_tmp', __( 'The image could not be saved on this server.', 'chatbot' ) );
		}
		return self::sideload( $tmp, 'ai-image-' . wp_generate_password( 6, false ) . '.' . $extension, $candidate, $alt, $post_id );
	}

	/**
	 * Sideload a temp file, then resize, convert and label it.
	 *
	 * @param string $tmp       Temp path.
	 * @param string $name      File name.
	 * @param array  $candidate Credit and licence.
	 * @param string $alt       Alt text.
	 * @param int    $post_id   Post id.
	 * @return int|WP_Error
	 */
	private static function sideload( $tmp, $name, array $candidate, $alt, $post_id ) {
		$check = wp_check_filetype_and_ext(
			$tmp,
			$name,
			array(
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'gif'      => 'image/gif',
				'webp'     => 'image/webp',
			)
		);
		if ( empty( $check['type'] ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'qcld_wpbot_writer_not_image', __( 'The downloaded file is not an image.', 'chatbot' ) );
		}
		self::optimise( $tmp, $check['type'] );

		$id = media_handle_sideload(
			array(
				'name'     => $name,
				'tmp_name' => $tmp,
			),
			(int) $post_id,
			sanitize_text_field( $alt )
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return $id;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( sanitize_text_field( $alt ) ) );
		$credit = self::credit_text( $candidate );
		if ( '' !== $credit ) {
			wp_update_post(
				wp_slash(
					array(
						'ID'           => $id,
						'post_excerpt' => $credit,
					)
				)
			);
		}
		update_post_meta(
			$id,
			'_qcld_wpbot_writer_image_license',
			wp_slash(
				array(
					'source'      => isset( $candidate['source'] ) ? sanitize_key( $candidate['source'] ) : '',
					'credit'      => isset( $candidate['credit'] ) ? sanitize_text_field( $candidate['credit'] ) : '',
					'license'     => isset( $candidate['license'] ) ? sanitize_text_field( $candidate['license'] ) : '',
					'license_url' => isset( $candidate['license_url'] ) ? esc_url_raw( $candidate['license_url'] ) : '',
					'source_url'  => isset( $candidate['source_url'] ) ? esc_url_raw( $candidate['source_url'] ) : '',
				)
			)
		);
		return (int) $id;
	}

	/**
	 * Caption text crediting the creator where the licence asks for it.
	 *
	 * @param array $candidate Candidate.
	 * @return string
	 */
	public static function credit_text( array $candidate ) {
		$source = isset( $candidate['source'] ) ? $candidate['source'] : '';
		$credit = isset( $candidate['credit'] ) ? trim( (string) $candidate['credit'] ) : '';
		if ( 'openverse' === $source && '' !== $credit ) {
			/* translators: 1: creator, 2: licence. */
			return sprintf( __( 'Photo: %1$s (%2$s)', 'chatbot' ), $credit, isset( $candidate['license'] ) ? $candidate['license'] : '' );
		}
		if ( 'pexels' === $source && '' !== $credit ) {
			/* translators: %s: photographer. */
			return sprintf( __( 'Photo: %s on Pexels', 'chatbot' ), $credit );
		}
		if ( 'pixabay' === $source ) {
			/* translators: %s: creator. */
			return sprintf( __( 'Image: %s', 'chatbot' ), $credit );
		}
		return '';
	}

	/**
	 * Resize to the configured width and convert to WebP where supported.
	 *
	 * @param string $path File.
	 * @param string $mime MIME type.
	 * @return void
	 */
	private static function optimise( $path, $mime ) {
		if ( 'image/gif' === $mime ) {
			return;
		}
		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return;
		}
		$size  = $editor->get_size();
		$width = (int) Settings::get( 'image_max_width' );
		if ( $width > 0 && isset( $size['width'] ) && $size['width'] > $width ) {
			$editor->resize( $width, null, false );
		}
		$format = Settings::get( 'image_webp' ) && wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ? 'image/webp' : $mime;
		$saved  = $editor->save( $path, $format );
		if ( ! is_wp_error( $saved ) && isset( $saved['path'] ) && $saved['path'] !== $path ) {
			// The editor wrote a new file name (new extension): move it back over the temp path.
			rename( $saved['path'], $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Temp file in uploads/tmp.
		}
	}

	/**
	 * Admin includes needed for sideloading outside wp-admin (REST, cron).
	 *
	 * @return void
	 */
	private static function load_admin_files() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
}
