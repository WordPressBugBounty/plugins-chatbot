<?php
/**
 * Unsplash search for the image panel. Off unless the admin turns it on
 * with their own key (Connections).
 *
 * Unsplash's API guidelines: hotlink the returned image URLs (never copy
 * them to the Media Library), call the download endpoint when an image is
 * inserted, and credit the photographer and Unsplash with links.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Images;

use QuantumCloud\WPBotCore\Connections\Stock_Keys;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Unsplash client.
 */
final class Unsplash {

	const API = 'https://api.unsplash.com/';

	/**
	 * Whether the admin turned Unsplash on with a key.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return Stock_Keys::unsplash_enabled();
	}

	/**
	 * Search photos.
	 *
	 * @param string $query Terms.
	 * @param int    $count Results.
	 * @return array[]|WP_Error Candidates (hotlink only).
	 */
	public static function search( $query, $count = 9 ) {
		if ( ! self::enabled() ) {
			return new WP_Error( 'qcld_wpbot_writer_unsplash_off', __( 'Unsplash is turned off. An administrator can turn it on under AI Writer > Settings > Images.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$url  = add_query_arg(
			array(
				'query'          => rawurlencode( sanitize_text_field( $query ) ),
				'per_page'       => min( 30, max( 1, (int) $count ) ),
				'content_filter' => 'high',
			),
			self::API . 'search/photos'
		);
		$data = self::get( $url );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$ref = 'utm_source=' . rawurlencode( sanitize_title( get_bloginfo( 'name' ) ) ) . '&utm_medium=referral';
		$out = array();
		foreach ( isset( $data['results'] ) ? (array) $data['results'] : array() as $photo ) {
			if ( empty( $photo['urls']['regular'] ) ) {
				continue;
			}
			$name  = isset( $photo['user']['name'] ) ? (string) $photo['user']['name'] : '';
			$out[] = array(
				'source'       => 'unsplash',
				'url'          => (string) $photo['urls']['regular'],
				'thumb'        => isset( $photo['urls']['small'] ) ? (string) $photo['urls']['small'] : (string) $photo['urls']['regular'],
				'alt'          => isset( $photo['alt_description'] ) ? (string) $photo['alt_description'] : '',
				'credit'       => $name,
				'credit_url'   => isset( $photo['user']['links']['html'] ) ? $photo['user']['links']['html'] . '?' . $ref : '',
				'unsplash_url' => 'https://unsplash.com/?' . $ref,
				'license'      => 'Unsplash License',
				'license_url'  => 'https://unsplash.com/license',
				'source_url'   => isset( $photo['links']['html'] ) ? (string) $photo['links']['html'] : '',
				'download'     => isset( $photo['links']['download_location'] ) ? (string) $photo['links']['download_location'] : '',
				'hotlink'      => true,
			);
		}
		return $out;
	}

	/**
	 * Tell Unsplash an image was used (required by their guidelines).
	 *
	 * @param string $download_location URL from the search result.
	 * @return true|WP_Error
	 */
	public static function track_download( $download_location ) {
		$url = esc_url_raw( (string) $download_location );
		if ( 0 !== strpos( $url, self::API ) ) {
			return new WP_Error( 'qcld_wpbot_writer_bad_url', __( 'That is not an Unsplash download address.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$data = self::get( $url );
		return is_wp_error( $data ) ? $data : true;
	}

	/**
	 * Authenticated GET.
	 *
	 * @param string $url URL.
	 * @return array|WP_Error
	 */
	private static function get( $url ) {
		/** This filter is documented in Image_Search::get_json(). */
		$url      = (string) apply_filters( 'qcld_wpbot_writer/image_endpoint', $url, 'unsplash' );
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization'  => 'Client-ID ' . Stock_Keys::get( 'unsplash' ),
					'Accept-Version' => 'v1',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'qcld_wpbot_writer_unsplash_down', __( 'Unsplash could not be reached. Try again in a minute.', 'chatbot' ), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 401 === $code ) {
			return new WP_Error( 'qcld_wpbot_writer_unsplash_key', __( 'Unsplash did not accept the key. Check it under AI Writer > Settings > Images.', 'chatbot' ), array( 'status' => 502 ) );
		}
		if ( 200 !== $code ) {
			return new WP_Error( 'qcld_wpbot_writer_unsplash_down', __( 'Unsplash could not be reached. Try again in a minute.', 'chatbot' ), array( 'status' => 502 ) );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : array();
	}
}
