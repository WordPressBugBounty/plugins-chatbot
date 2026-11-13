<?php
/**
 * Images from the Media Library, prepared per network: public URL, MIME
 * allowlist, JPEG copies for networks that need JPEG (Instagram), and the
 * aspect-ratio checks networks enforce.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Media;

defined( 'ABSPATH' ) || exit;

/**
 * Media helper.
 */
final class Media {

	/**
	 * Image types WPBot Social accepts from the Media Library.
	 *
	 * @var string[]
	 */
	const MIMES = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );

	/**
	 * Aspect ratio ranges (width / height) per network.
	 *
	 * @var array<string,array{0:float,1:float}>
	 */
	const RATIOS = array(
		'instagram' => array( 0.8, 1.91 ),
		'threads'   => array( 0.5, 1.91 ),
	);

	/**
	 * An attachment as a draft image, converted when the network needs it.
	 *
	 * @param int    $attachment_id Attachment id.
	 * @param string $network       Network id.
	 * @return array{url:string, mime:string, width:int, height:int}|null
	 */
	public static function image( $attachment_id, $network ) {
		$attachment_id = absint( $attachment_id );
		$mime          = (string) get_post_mime_type( $attachment_id );
		if ( ! $attachment_id || ! in_array( $mime, self::MIMES, true ) ) {
			return null;
		}
		$url  = (string) wp_get_attachment_url( $attachment_id );
		$meta = wp_get_attachment_metadata( $attachment_id );
		$out  = array(
			'url'    => $url,
			'mime'   => $mime,
			'width'  => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
			'height' => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
		);
		$crop = self::crop_box( $network, $out['width'], $out['height'] );
		if ( $crop && \QuantumCloud\Social\Settings::get( 'resize_per_network' ) ) {
			// Outside the network's allowed shape: a centred copy at the nearest allowed ratio.
			$copy = self::cropped_copy( $attachment_id, $network, $crop );
			if ( $copy ) {
				return array(
					'url'     => $copy,
					'mime'    => 'image/jpeg',
					'width'   => $crop[2],
					'height'  => $crop[3],
					'cropped' => true,
				);
			}
		}
		if ( 'instagram' === $network && 'image/jpeg' !== $mime ) {
			$jpeg = self::jpeg_copy( $attachment_id );
			if ( $jpeg ) {
				$out['url']  = $jpeg;
				$out['mime'] = 'image/jpeg';
			}
		}
		return $out;
	}

	/**
	 * The centred crop that brings an image inside a network's allowed shape.
	 *
	 * @param string $network Network id.
	 * @param int    $width   Width.
	 * @param int    $height  Height.
	 * @return array{0:int,1:int,2:int,3:int}|null x, y, width, height; null when no crop is needed.
	 */
	public static function crop_box( $network, $width, $height ) {
		if ( ! isset( self::RATIOS[ $network ] ) || $width <= 0 || $height <= 0 ) {
			return null;
		}
		list( $min, $max ) = self::RATIOS[ $network ];
		$ratio             = $width / $height;
		if ( $ratio >= $min && $ratio <= $max ) {
			return null;
		}
		if ( $ratio > $max ) {
			$new_w = (int) floor( $height * $max );
			return array( (int) floor( ( $width - $new_w ) / 2 ), 0, $new_w, (int) $height );
		}
		$new_h = (int) floor( $width / $min );
		return array( 0, (int) floor( ( $height - $new_h ) / 2 ), (int) $width, $new_h );
	}

	/**
	 * A cropped JPEG copy for one network (made once, reused; the original is kept).
	 *
	 * @param int    $attachment_id Attachment id.
	 * @param string $network       Network id.
	 * @param array  $box           x, y, width, height.
	 * @return string URL, or '' when the server cannot crop.
	 */
	private static function cropped_copy( $attachment_id, $network, array $box ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return '';
		}
		$target = preg_replace( '/\.[A-Za-z0-9]+$/', '', $file ) . '-wpbot-social-' . sanitize_key( $network ) . '.jpg';
		if ( ! file_exists( $target ) ) {
			$editor = wp_get_image_editor( $file );
			if ( is_wp_error( $editor ) || is_wp_error( $editor->crop( $box[0], $box[1], $box[2], $box[3] ) ) || is_wp_error( $editor->save( $target, 'image/jpeg' ) ) ) {
				return '';
			}
		}
		$uploads = wp_get_upload_dir();
		return str_replace( wp_normalize_path( $uploads['basedir'] ), $uploads['baseurl'], wp_normalize_path( $target ) );
	}

	/**
	 * Images that belong to a post or product: featured, gallery, then images in the content.
	 *
	 * @param int $post_id Post id.
	 * @return array<int,array{id:int, thumb:string, alt:string}>
	 */
	public static function from_post( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return array();
		}
		$ids = array( (int) get_post_thumbnail_id( $post ) );
		if ( 'product' === $post->post_type ) {
			$ids = array_merge( $ids, array_map( 'intval', array_filter( explode( ',', (string) get_post_meta( $post->ID, '_product_image_gallery', true ) ) ) ) );
		}
		if ( preg_match_all( '/wp-image-(\d+)/', (string) $post->post_content, $m ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $m[1] ) );
		}
		$out = array();
		foreach ( array_slice( array_values( array_unique( array_filter( $ids ) ) ), 0, 20 ) as $id ) {
			$thumb = wp_get_attachment_image_url( $id, 'thumbnail' );
			if ( $thumb && in_array( (string) get_post_mime_type( $id ), self::MIMES, true ) ) {
				$out[] = array(
					'id'    => $id,
					'thumb' => $thumb,
					'alt'   => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
				);
			}
		}
		return $out;
	}

	/**
	 * A JPEG copy next to the original (made once, reused).
	 *
	 * @param int $attachment_id Attachment id.
	 * @return string URL, or '' when the server cannot convert.
	 */
	public static function jpeg_copy( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return '';
		}
		$target = preg_replace( '/\.[A-Za-z0-9]+$/', '', $file ) . '-wpbot-social.jpg';
		if ( ! file_exists( $target ) ) {
			$editor = wp_get_image_editor( $file );
			if ( is_wp_error( $editor ) ) {
				return '';
			}
			$saved = $editor->save( $target, 'image/jpeg' );
			if ( is_wp_error( $saved ) ) {
				return '';
			}
		}
		$uploads = wp_get_upload_dir();
		return str_replace( wp_normalize_path( $uploads['basedir'] ), $uploads['baseurl'], wp_normalize_path( $target ) );
	}

	/**
	 * Problem with an image's shape for a network, or ''.
	 *
	 * @param string $network Network id.
	 * @param int    $width   Width.
	 * @param int    $height  Height.
	 * @return string
	 */
	public static function ratio_problem( $network, $width, $height ) {
		if ( ! isset( self::RATIOS[ $network ] ) || $width <= 0 || $height <= 0 ) {
			return '';
		}
		$ratio             = $width / $height;
		list( $min, $max ) = self::RATIOS[ $network ];
		if ( $ratio < $min || $ratio > $max ) {
			/* translators: 1: network, 2: lowest ratio, 3: highest ratio. */
			return sprintf( __( '%1$s needs an image between %2$s and %3$s (width to height). Crop it in the Media Library first.', 'chatbot' ), ucfirst( $network ), '4:5', '1.91:1' );
		}
		return '';
	}
}
