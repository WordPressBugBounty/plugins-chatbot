<?php
/**
 * One AI text request, independent of the provider.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Value object describing what to ask and how.
 */
final class Request {

	/**
	 * System instructions.
	 *
	 * @var string
	 */
	public $system = '';

	/**
	 * Conversation: list of array( 'role' => 'user'|'assistant', 'content' => string ).
	 *
	 * @var array
	 */
	public $messages = array();

	/**
	 * Output token ceiling for the visible answer.
	 *
	 * @var int
	 */
	public $max_tokens = 1200;

	/**
	 * Style of the step: "creative" (drafting) or "strict" (facts, metadata, product data).
	 *
	 * @var string
	 */
	public $mode = 'strict';

	/**
	 * Sampling temperature (0–2). Null keeps the default for the mode
	 * (0.8 creative, 0.2 strict). Models that reject it ignore it.
	 *
	 * @var float|null
	 */
	public $temperature = null;

	/**
	 * Ask the model to answer with a single JSON object.
	 *
	 * @var bool
	 */
	public $json = false;

	/**
	 * HTTP timeout in seconds.
	 *
	 * @var int
	 */
	public $timeout = 45;

	/**
	 * Let the model search the web (providers that support it: OpenAI,
	 * Anthropic, Gemini, OpenRouter). Sources come back on the Response.
	 *
	 * @var bool
	 */
	public $web_search = false;

	/**
	 * Tools the model may call: [ { name, description, parameters } ] (see AI\Tool_Calls).
	 * Messages may then also hold assistant tool calls and tool results.
	 *
	 * @var array[]
	 */
	public $tools = array();

	/**
	 * Tool choice: 'auto' (default), 'none', 'required' or a tool name.
	 *
	 * @var string
	 */
	public $tool_choice = 'auto';

	/**
	 * Images sent with the last user message, for models that read images
	 * (OpenAI, Anthropic, Gemini, OpenRouter). Each: mime (image/png,
	 * image/jpeg, image/webp or image/gif) and data (base64, no prefix).
	 *
	 * @var array[]
	 */
	public $images = array();

	/** Image types a request may carry. */
	const IMAGE_TYPES = array( 'image/png', 'image/jpeg', 'image/webp', 'image/gif' );

	/** Largest image a request may carry, in bytes (decoded). */
	const MAX_IMAGE_BYTES = 5242880;

	/**
	 * Documents that can be sent like images, to models that read them
	 * (see Providers::reads_pdf()).
	 */
	const FILE_TYPES = array( 'application/pdf' );

	/**
	 * Largest document sent (10 MB).
	 */
	const MAX_FILE_BYTES = 10485760;

	/**
	 * Temperature to send: the explicit one, else the mode's default.
	 *
	 * @return float
	 */
	public function temperature() {
		if ( null !== $this->temperature ) {
			return (float) $this->temperature;
		}
		return 'creative' === $this->mode ? 0.8 : 0.2;
	}

	/**
	 * Build a request from one user prompt.
	 *
	 * @param string $system System instructions.
	 * @param string $prompt User prompt.
	 * @param array  $args   Optional: max_tokens, mode, temperature, json, timeout,
	 *                       web_search, images (array of mime + base64 data;
	 *                       unknown types and oversized images are left out).
	 * @return Request
	 */
	public static function make( $system, $prompt, array $args = array() ) {
		$request           = new self();
		$request->system   = (string) $system;
		$request->messages = array(
			array(
				'role'    => 'user',
				'content' => (string) $prompt,
			),
		);
		foreach ( array( 'max_tokens', 'timeout' ) as $int_key ) {
			if ( isset( $args[ $int_key ] ) ) {
				$request->$int_key = max( 1, (int) $args[ $int_key ] );
			}
		}
		if ( isset( $args['mode'] ) && in_array( $args['mode'], array( 'creative', 'strict' ), true ) ) {
			$request->mode = $args['mode'];
		}
		if ( isset( $args['temperature'] ) && is_numeric( $args['temperature'] ) ) {
			$request->temperature = max( 0.0, min( 2.0, (float) $args['temperature'] ) );
		}
		if ( isset( $args['json'] ) ) {
			$request->json = (bool) $args['json'];
		}
		if ( isset( $args['web_search'] ) ) {
			$request->web_search = (bool) $args['web_search'];
		}
		if ( ! empty( $args['images'] ) && is_array( $args['images'] ) ) {
			$request->images = self::clean_images( $args['images'] );
		}
		return $request;
	}

	/**
	 * Keep only well-formed images of an allowed type and size (at most 4).
	 *
	 * @param array $images Images: mime, data (base64).
	 * @return array[]
	 */
	public static function clean_images( array $images ) {
		$out = array();
		foreach ( $images as $image ) {
			if ( ! is_array( $image ) || empty( $image['mime'] ) || empty( $image['data'] ) ) {
				continue;
			}
			$mime    = strtolower( (string) $image['mime'] );
			$data    = preg_replace( '/\s+/', '', (string) $image['data'] );
			$is_file = in_array( $mime, self::FILE_TYPES, true );
			if ( ( ! $is_file && ! in_array( $mime, self::IMAGE_TYPES, true ) ) || ! preg_match( '/^[A-Za-z0-9+\/]+={0,2}$/', $data ) ) {
				continue;
			}
			if ( (int) floor( strlen( $data ) * 3 / 4 ) > ( $is_file ? self::MAX_FILE_BYTES : self::MAX_IMAGE_BYTES ) ) {
				continue;
			}
			$out[] = array(
				'mime' => $mime,
				'data' => $data,
			);
			if ( count( $out ) >= 4 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Stable fingerprint of the request content, for caching.
	 *
	 * @return string
	 */
	public function fingerprint() {
		$parts = array( $this->system, $this->messages, $this->max_tokens, $this->mode, $this->json, $this->web_search );
		if ( array() !== $this->images ) {
			// Only with images, so cached text answers keep their fingerprint.
			$parts[] = array_map( 'md5', array_column( $this->images, 'data' ) );
		}
		if ( null !== $this->temperature ) {
			// Only when set, so existing cached answers keep their fingerprint.
			$parts[] = 'temperature:' . $this->temperature;
		}
		return hash( 'sha256', wp_json_encode( $parts ) );
	}
}
