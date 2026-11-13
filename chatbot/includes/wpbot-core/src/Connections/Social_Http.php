<?php
/**
 * Small HTTP helper for the social connections: JSON decoding, errors with
 * secrets scrubbed, tokens kept out of URLs where the API allows.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Connections;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Social HTTP helper.
 */
final class Social_Http {

	/**
	 * Timeout in seconds.
	 */
	const TIMEOUT = 20;

	/**
	 * Send a request and decode JSON.
	 *
	 * @param string $method  GET or POST.
	 * @param string $url     URL (no tokens).
	 * @param array  $headers Headers.
	 * @param array  $body    Form fields for POST.
	 * @return array|WP_Error Decoded body, or an error with data.status and data.code (the network's error code).
	 */
	public static function send( $method, $url, array $headers = array(), array $body = array() ) {
		$args = array(
			'timeout' => self::TIMEOUT,
			'headers' => $headers,
		);
		if ( 'POST' === $method ) {
			$args['body'] = $body;
			$response     = wp_remote_post( $url, $args );
		} else {
			$response = wp_remote_get( $url, $args );
		}
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'qcld_wpbot_core_social_http',
				self::scrub( $response->get_error_message() ),
				array(
					'status' => 0,
					'code'   => 0,
				)
			);
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data   = is_array( $data ) ? $data : array();
		if ( $status < 200 || $status >= 300 || isset( $data['error'] ) ) {
			$error = isset( $data['error'] ) && is_array( $data['error'] ) ? $data['error'] : array();
			$text  = isset( $error['message'] ) ? (string) $error['message'] : ( isset( $data['error_description'] ) ? (string) $data['error_description'] : (string) $status );
			return new WP_Error(
				'qcld_wpbot_core_social_api',
				self::scrub( $text ),
				array(
					'status' => $status,
					'code'   => isset( $error['code'] ) ? (int) $error['code'] : 0,
				)
			);
		}
		return $data;
	}

	/**
	 * Remove tokens and secrets from text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function scrub( $text ) {
		return (string) preg_replace(
			array(
				'/(access_token|appsecret_proof|client_secret|code|code_verifier|refresh_token|fb_exchange_token|input_token)=([^&\s"\']+)/i',
				'/\b(Bearer|OAuth|Basic)\s+[A-Za-z0-9\-._~+\/]+=*/i',
				'/\bEAA[A-Za-z0-9]{20,}/',
			),
			array( '$1=[redacted]', '$1 [redacted]', '[redacted]' ),
			(string) $text
		);
	}
}
