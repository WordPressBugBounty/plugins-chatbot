<?php
/**
 * Provider-neutral AI request.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Messages plus options for one AI call.
 */
final class AI_Request {

	/**
	 * Messages: list of array( 'role' => system|user|assistant, 'content' => string ).
	 *
	 * @var array<int,array<string,string>>
	 */
	public $messages = array();

	/**
	 * Maximum output tokens.
	 *
	 * @var int
	 */
	public $max_tokens = 600;

	/**
	 * Sampling temperature.
	 *
	 * @var float
	 */
	public $temperature = 0.2;

	/**
	 * 'text' or 'json'.
	 *
	 * @var string
	 */
	public $response_format = 'text';

	/**
	 * Timeout in seconds.
	 *
	 * @var int
	 */
	public $timeout = 30;

	/**
	 * Feature id that made the request (for filters and usage logs).
	 *
	 * @var string
	 */
	public $feature = '';

	/**
	 * Module id for WPBot Core's AI spending log ('' = insights). AI Marketing passes ai_marketing.
	 *
	 * @var string
	 */
	public $module = '';

	/**
	 * Build a request.
	 *
	 * @param array<int,array<string,string>> $messages Messages.
	 * @param array<string,mixed>             $args     Optional overrides.
	 */
	public function __construct( array $messages, array $args = array() ) {
		$this->messages = $messages;
		foreach ( array( 'max_tokens', 'temperature', 'response_format', 'timeout', 'feature', 'module' ) as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$this->$key = $args[ $key ];
			}
		}
	}
}
