<?php
/**
 * Image search across sources: Media Library, Openverse (no key), Pexels and
 * Pixabay (owner's keys). Returns candidates with licence and credit.
 *
 * Pixabay results are downloaded to the site, not hotlinked; Openverse
 * returns each image's licence and attribution. Unsplash (hotlink only) is
 * handled by the image panel, not by automatic filling.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Images;

use QuantumCloud\WPBotCore\Connections\Stock_Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Image search.
 */
final class Image_Search {

	/**
	 * Stock sources usable for automatic filling, in order of preference.
	 *
	 * @return string[]
	 */
	public static function stock_sources() {
		$sources = array();
		if ( '' !== Stock_Keys::get( 'pexels' ) ) {
			$sources[] = 'pexels';
		}
		if ( '' !== Stock_Keys::get( 'pixabay' ) ) {
			$sources[] = 'pixabay';
		}
		$sources[] = 'openverse';
		return $sources;
	}

	/**
	 * Search one source.
	 *
	 * @param string $source Source id (media, openverse, pexels, pixabay).
	 * @param string $query  Search terms.
	 * @param int    $count  Results wanted.
	 * @return array[] Candidates: source, attachment_id, url, thumb, credit, license, license_url, source_url.
	 */
	public static function search( $source, $query, $count = 3 ) {
		$query = trim( sanitize_text_field( $query ) );
		if ( '' === $query ) {
			return array();
		}
		switch ( $source ) {
			case 'media':
				return self::media( $query, $count );
			case 'openverse':
				$data = self::get_json(
					'openverse',
					add_query_arg(
						array(
							'q'         => rawurlencode( $query ),
							'page_size' => $count,
							'mature'    => 'false',
						),
						'https://api.openverse.org/v1/images/'
					)
				);
				return array_map(
					static function ( $item ) {
						return array(
							'source'      => 'openverse',
							'url'         => isset( $item['url'] ) ? (string) $item['url'] : '',
							'thumb'       => isset( $item['thumbnail'] ) ? (string) $item['thumbnail'] : '',
							'credit'      => isset( $item['creator'] ) ? (string) $item['creator'] : '',
							'license'     => strtoupper( trim( ( isset( $item['license'] ) ? $item['license'] : '' ) . ' ' . ( isset( $item['license_version'] ) ? $item['license_version'] : '' ) ) ),
							'license_url' => isset( $item['license_url'] ) ? (string) $item['license_url'] : '',
							'source_url'  => isset( $item['foreign_landing_url'] ) ? (string) $item['foreign_landing_url'] : '',
						);
					},
					isset( $data['results'] ) ? array_slice( (array) $data['results'], 0, $count ) : array()
				);
			case 'pexels':
				$data = self::get_json(
					'pexels',
					add_query_arg(
						array(
							'query'    => rawurlencode( $query ),
							'per_page' => $count,
						),
						'https://api.pexels.com/v1/search'
					),
					array( 'Authorization' => Stock_Keys::get( 'pexels' ) )
				);
				return array_map(
					static function ( $item ) {
						return array(
							'source'      => 'pexels',
							'url'         => isset( $item['src']['large2x'] ) ? (string) $item['src']['large2x'] : '',
							'thumb'       => isset( $item['src']['medium'] ) ? (string) $item['src']['medium'] : '',
							'credit'      => isset( $item['photographer'] ) ? (string) $item['photographer'] : '',
							'license'     => 'Pexels License',
							'license_url' => 'https://www.pexels.com/license/',
							'source_url'  => isset( $item['url'] ) ? (string) $item['url'] : '',
						);
					},
					isset( $data['photos'] ) ? array_slice( (array) $data['photos'], 0, $count ) : array()
				);
			case 'pixabay':
				$data = self::get_json(
					'pixabay',
					add_query_arg(
						array(
							'key'        => rawurlencode( Stock_Keys::get( 'pixabay' ) ),
							'q'          => rawurlencode( $query ),
							'per_page'   => max( 3, $count ),
							'image_type' => 'photo',
							'safesearch' => 'true',
						),
						'https://pixabay.com/api/'
					)
				);
				return array_map(
					static function ( $item ) {
						return array(
							'source'      => 'pixabay',
							'url'         => isset( $item['largeImageURL'] ) ? (string) $item['largeImageURL'] : '',
							'thumb'       => isset( $item['previewURL'] ) ? (string) $item['previewURL'] : '',
							'credit'      => isset( $item['user'] ) ? (string) $item['user'] . ' (Pixabay)' : 'Pixabay',
							'license'     => 'Pixabay Content License',
							'license_url' => 'https://pixabay.com/service/license-summary/',
							'source_url'  => isset( $item['pageURL'] ) ? (string) $item['pageURL'] : '',
						);
					},
					isset( $data['hits'] ) ? array_slice( (array) $data['hits'], 0, $count ) : array()
				);
		}
		return array();
	}

	/**
	 * A Media Library image for an automatic spot (CR-10): it must be wide
	 * enough and not used anywhere else. The owner's own picks are not
	 * limited this way.
	 *
	 * @param string $query     Search words.
	 * @param int    $min_width Minimum width in pixels.
	 * @return array[] One candidate or none.
	 */
	public static function media_for_spot( $query, $min_width = 600 ) {
		$out = array();
		foreach ( self::media( $query, 12 ) as $candidate ) {
			$id   = (int) $candidate['attachment_id'];
			$meta = wp_get_attachment_metadata( $id );
			if ( ! is_array( $meta ) || empty( $meta['width'] ) || (int) $meta['width'] < $min_width || self::used( $id ) ) {
				continue;
			}
			$out[] = $candidate;
			break;
		}
		return $out;
	}

	/**
	 * Whether an image is already used: attached to a post, a post's
	 * featured image, or in a post's content.
	 *
	 * @param int $id Attachment id.
	 * @return bool
	 */
	public static function used( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( (int) wp_get_post_parent_id( $id ) > 0 ) {
			return true;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One cheap lookup per candidate; not cacheable across requests.
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s LIMIT 1", (string) $id ) ) ) {
			return true;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Same.
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ( 'attachment', 'revision' ) AND post_status <> 'trash' AND post_content LIKE %s LIMIT 1", '%' . $wpdb->esc_like( 'wp-image-' . $id . '"' ) . '%' ) );
	}

	/**
	 * Media Library images matching the terms.
	 *
	 * @param string $query Terms.
	 * @param int    $count Results.
	 * @return array[]
	 */
	private static function media( $query, $count ) {
		$posts = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				's'              => $query,
				'posts_per_page' => $count,
				'fields'         => 'ids',
			)
		);
		$out   = array();
		foreach ( $posts as $id ) {
			$out[] = array(
				'source'        => 'media',
				'attachment_id' => (int) $id,
				'url'           => (string) wp_get_attachment_image_url( $id, 'large' ),
				'thumb'         => (string) wp_get_attachment_image_url( $id, 'medium' ),
				'credit'        => '',
				'license'       => '',
				'license_url'   => '',
				'source_url'    => '',
			);
		}
		return $out;
	}

	/**
	 * GET JSON with a timeout; empty array on any failure.
	 *
	 * @param string $service Service id (for the endpoint filter).
	 * @param string $url     URL.
	 * @param array  $headers Headers.
	 * @return array
	 */
	private static function get_json( $service, $url, array $headers = array() ) {
		/**
		 * Change an image service URL (tests point it at a mock).
		 *
		 * @param string $url     URL.
		 * @param string $service Service id.
		 */
		$url      = (string) apply_filters( 'qcld_wpbot_writer/image_endpoint', $url, $service );
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array_merge( array( 'User-Agent' => 'WPBot AI Writer; ' . home_url( '/' ) ), $headers ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : array();
	}
}
