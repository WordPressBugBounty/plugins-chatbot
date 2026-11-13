<?php
/**
 * Live transport through the WordPress HTTP API.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Sends requests with wp_remote_request().
 */
final class Wp_Transport implements Transport {

	/**
	 * Request timeout in seconds. Kept short so a publish step stays well
	 * inside WPBot Core's job lock.
	 */
	const TIMEOUT = 30;

	/**
	 * Send one request.
	 *
	 * @param string $method  Method.
	 * @param string $url     URL.
	 * @param array  $headers Headers.
	 * @param array  $body    Form fields.
	 * @return Response
	 */
	public function request( $method, $url, array $headers = array(), array $body = array() ) {
		$args = array(
			'method'  => $method,
			'timeout' => self::TIMEOUT,
			'headers' => $headers,
		);
		if ( 'GET' !== $method && array() !== $body ) {
			// A "__json" entry is sent as a raw JSON body (Google APIs); otherwise form fields.
			$args['body'] = isset( $body['__json'] ) ? (string) $body['__json'] : $body;
		}
		$raw = wp_remote_request( $url, $args );
		if ( is_wp_error( $raw ) ) {
			$message = strtolower( $raw->get_error_message() );
			$error   = ( false !== strpos( $message, 'timed out' ) || false !== strpos( $message, 'timeout' ) ) ? 'timeout' : 'connection';
			return new Response( 0, array(), $error );
		}
		$decoded = json_decode( (string) wp_remote_retrieve_body( $raw ), true );
		$headers = array();
		$all     = wp_remote_retrieve_headers( $raw );
		$all     = is_object( $all ) && method_exists( $all, 'getAll' ) ? $all->getAll() : (array) $all; // A case-insensitive dictionary object.
		foreach ( $all as $name => $value ) {
			$headers[ (string) $name ] = is_array( $value ) ? (string) end( $value ) : (string) $value;
		}
		return new Response( (int) wp_remote_retrieve_response_code( $raw ), is_array( $decoded ) ? $decoded : array(), '', $headers );
	}
}
