<?php
/**
 * Bing Webmaster Tools API (owner's free key): inbound link counts per page
 * (GetLinkCounts) and linking pages with anchors for the most linked pages
 * (GetUrlLinks). Read-only; the key never leaves the server.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Links;

use QuantumCloud\SeoAiSearch\Secrets;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Bing link data.
 */
final class Bing_Client {

	/**
	 * Option with link counts per page.
	 */
	const OPTION = 'qcld_wpbot_seo_bing_links';

	/**
	 * Pull counts and the linking pages of the top pages into the monitor.
	 *
	 * @param int $top Pages whose linking pages are read.
	 * @return array|WP_Error pages, links_added.
	 */
	public static function pull( $top = 10 ) {
		$counts = self::link_counts();
		if ( is_wp_error( $counts ) ) {
			return $counts;
		}
		update_option(
			self::OPTION,
			array(
				'pages'  => array_slice( $counts, 0, 500, true ),
				'pulled' => current_time( 'mysql', true ),
			),
			false
		);
		$added = 0;
		foreach ( array_slice( array_keys( $counts ), 0, max( 1, (int) $top ) ) as $page ) {
			$links = self::url_links( $page );
			if ( is_wp_error( $links ) ) {
				continue;
			}
			foreach ( $links as $link ) {
				$id = Link_Store::add(
					array(
						'source_url' => $link['url'],
						'target_url' => $page,
						'anchor'     => $link['anchor'],
						'origin'     => 'bing',
					)
				);
				if ( ! is_wp_error( $id ) ) {
					++$added;
				}
			}
		}
		return array(
			'pages'       => count( $counts ),
			'links_added' => $added,
		);
	}

	/**
	 * Saved counts.
	 *
	 * @return array pages (url => count), pulled.
	 */
	public static function saved() {
		$saved = get_option( self::OPTION, array() );
		return array(
			'pages'  => isset( $saved['pages'] ) && is_array( $saved['pages'] ) ? $saved['pages'] : array(),
			'pulled' => isset( $saved['pulled'] ) ? (string) $saved['pulled'] : '',
		);
	}

	/**
	 * Inbound link count per page of the site, most linked first.
	 *
	 * @return array|WP_Error url => count.
	 */
	public static function link_counts() {
		$out = array();
		for ( $page = 0; $page < 20; $page++ ) {
			$data = self::call( 'GetLinkCounts', array( 'page' => $page ) );
			if ( is_wp_error( $data ) ) {
				return $page ? $out : $data;
			}
			foreach ( isset( $data['Links'] ) ? (array) $data['Links'] : array() as $row ) {
				if ( isset( $row['Url'] ) ) {
					$out[ esc_url_raw( (string) $row['Url'] ) ] = isset( $row['Count'] ) ? (int) $row['Count'] : 0;
				}
			}
			if ( empty( $data['TotalPages'] ) || $page + 1 >= (int) $data['TotalPages'] ) {
				break;
			}
		}
		arsort( $out );
		return $out;
	}

	/**
	 * Linking pages and anchors for one page of the site.
	 *
	 * @param string $url Page of this site.
	 * @return array|WP_Error list of url, anchor.
	 */
	public static function url_links( $url ) {
		$out = array();
		for ( $page = 0; $page < 5; $page++ ) {
			$data = self::call(
				'GetUrlLinks',
				array(
					'link' => $url,
					'page' => $page,
				)
			);
			if ( is_wp_error( $data ) ) {
				return $page ? $out : $data;
			}
			foreach ( isset( $data['Details'] ) ? (array) $data['Details'] : array() as $row ) {
				if ( ! empty( $row['Url'] ) ) {
					$out[] = array(
						'url'    => (string) $row['Url'],
						'anchor' => isset( $row['AnchorText'] ) ? (string) $row['AnchorText'] : '',
					);
				}
			}
			if ( empty( $data['TotalPages'] ) || $page + 1 >= (int) $data['TotalPages'] ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * One API call (JSON endpoint); errors explain how to fix them.
	 *
	 * @param string $method API method.
	 * @param array  $args   Query arguments.
	 * @return array|WP_Error The "d" payload.
	 */
	public static function call( $method, array $args ) {
		$key = Secrets::get( 'bing' );
		if ( '' === $key ) {
			return new WP_Error( 'qcld_wpbot_seo_no_bing_key', __( 'Add your Bing Webmaster Tools API key in Settings > Connections first.', 'chatbot' ), array( 'status' => 400 ) );
		}
		/** This filter is documented in src/Technical/Page_Speed.php */
		$base     = (string) apply_filters( 'qcld_wpbot_seo/endpoint', 'https://ssl.bing.com/webmaster/api.svc/json', 'bing' );
		$url      = add_query_arg(
			array_map(
				'rawurlencode',
				array_merge(
					array( 'siteUrl' => home_url( '/' ) ),
					$args,
					array( 'apikey' => $key )
				)
			),
			trailingslashit( $base ) . $method
		);
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'qcld_wpbot_seo_bing_unreachable', __( 'Bing Webmaster Tools could not be reached. Try again later.', 'chatbot' ), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 401 === $code || 403 === $code || ( 400 === $code && false !== stripos( (string) wp_remote_retrieve_body( $response ), 'key' ) ) ) {
			return new WP_Error( 'qcld_wpbot_seo_bing_key', __( 'Bing refused the API key. Copy it again from Bing Webmaster Tools > Settings > API access, and check that this site is verified there.', 'chatbot' ), array( 'status' => 400 ) );
		}
		if ( 200 !== $code || ! is_array( $json ) ) {
			/* translators: %d: HTTP status. */
			return new WP_Error( 'qcld_wpbot_seo_bing_error', sprintf( __( 'Bing Webmaster Tools returned an error (HTTP %d). Check that this site is verified in Bing Webmaster Tools.', 'chatbot' ), $code ), array( 'status' => 502 ) );
		}
		return isset( $json['d'] ) && is_array( $json['d'] ) ? $json['d'] : array();
	}
}
