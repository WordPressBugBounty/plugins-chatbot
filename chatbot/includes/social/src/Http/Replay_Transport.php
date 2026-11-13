<?php
/**
 * Recorded-response transport for tests and for sites without a developer app.
 *
 * A fixture is a JSON file: { "source": doc URL, "graph_version": ...,
 * "steps": [ { "method", "path" (regex on the URL path), "response":
 * { "code", "body" } | { "error": "timeout" } } ] }. Steps are consumed in
 * order; every request is recorded so tests can assert what was sent.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Replays recorded responses in order.
 */
final class Replay_Transport implements Transport {

	/**
	 * Remaining steps.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $steps;

	/**
	 * Requests made, in order: method, url, headers, body.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	public $sent = array();

	/**
	 * Build from fixture steps.
	 *
	 * @param array $steps Steps.
	 */
	public function __construct( array $steps ) {
		$this->steps = array_values( $steps );
	}

	/**
	 * Build from a fixture file.
	 *
	 * @param string $file Absolute path to the JSON fixture.
	 * @return Replay_Transport
	 */
	public static function from_file( $file ) {
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture file.
		return new self( is_array( $data ) && isset( $data['steps'] ) ? (array) $data['steps'] : array() );
	}

	/**
	 * Replay the next step.
	 *
	 * @param string $method  Method.
	 * @param string $url     URL.
	 * @param array  $headers Headers.
	 * @param array  $body    Form fields.
	 * @return Response
	 */
	public function request( $method, $url, array $headers = array(), array $body = array() ) {
		$this->sent[] = compact( 'method', 'url', 'headers', 'body' );
		$step         = array_shift( $this->steps );
		if ( null === $step ) {
			return new Response( 0, array(), 'replay_exhausted' );
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( strtoupper( (string) $step['method'] ) !== $method || ! preg_match( '#' . $step['path'] . '#', $path ) ) {
			return new Response( 0, array(), 'replay_mismatch: expected ' . $step['method'] . ' ' . $step['path'] . ', got ' . $method . ' ' . $path );
		}
		$response = (array) $step['response'];
		if ( isset( $response['error'] ) ) {
			return new Response( 0, array(), (string) $response['error'] );
		}
		return new Response( (int) $response['code'], isset( $response['body'] ) ? (array) $response['body'] : array(), '', isset( $response['headers'] ) ? (array) $response['headers'] : array() );
	}

	/**
	 * Steps not yet used (a test can assert the scenario was fully played).
	 *
	 * @return int
	 */
	public function remaining() {
		return count( $this->steps );
	}
}
