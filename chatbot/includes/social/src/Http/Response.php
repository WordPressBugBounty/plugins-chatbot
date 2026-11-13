<?php
/**
 * A network HTTP response, or a transport failure.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Http;

defined( 'ABSPATH' ) || exit;

/**
 * HTTP response value object.
 */
final class Response {

	/**
	 * HTTP status code; 0 when no response arrived.
	 *
	 * @var int
	 */
	public $code;

	/**
	 * Decoded JSON body, or array() when the body was not JSON.
	 *
	 * @var array<string,mixed>
	 */
	public $body;

	/**
	 * Transport error ("timeout", "connection"), or '' when a response arrived.
	 *
	 * @var string
	 */
	public $error;

	/**
	 * Response headers, lower-case names (some APIs return ids only in a header, such as LinkedIn's x-restli-id).
	 *
	 * @var array<string,string>
	 */
	public $headers = array();

	/**
	 * Build.
	 *
	 * @param int    $code    Status code.
	 * @param array  $body    Decoded body.
	 * @param string $error   Transport error.
	 * @param array  $headers Headers.
	 */
	public function __construct( $code, array $body = array(), $error = '', array $headers = array() ) {
		$this->code    = (int) $code;
		$this->body    = $body;
		$this->error   = (string) $error;
		$this->headers = array_change_key_case( array_map( 'strval', $headers ), CASE_LOWER );
	}

	/**
	 * One header ('' when absent).
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public function header( $name ) {
		$name = strtolower( (string) $name );
		return isset( $this->headers[ $name ] ) ? $this->headers[ $name ] : '';
	}

	/**
	 * Whether no response arrived (timeout, DNS, connection reset).
	 *
	 * @return bool
	 */
	public function no_response() {
		return '' !== $this->error || 0 === $this->code;
	}

	/**
	 * Whether the status is 2xx.
	 *
	 * @return bool
	 */
	public function ok() {
		return $this->code >= 200 && $this->code < 300;
	}
}
