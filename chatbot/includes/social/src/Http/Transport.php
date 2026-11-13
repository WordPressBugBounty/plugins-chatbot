<?php
/**
 * How adapters talk HTTP: live through WordPress, or replayed from fixtures.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Http;

defined( 'ABSPATH' ) || exit;

/**
 * HTTP transport.
 */
interface Transport {

	/**
	 * Send one request.
	 *
	 * @param string $method  GET, POST or DELETE.
	 * @param string $url     Absolute URL. Never carries tokens.
	 * @param array  $headers Headers (the token goes in Authorization).
	 * @param array  $body    Form fields for POST, or [ '__json' => string ] for a JSON body.
	 * @return Response
	 */
	public function request( $method, $url, array $headers = array(), array $body = array() );
}
