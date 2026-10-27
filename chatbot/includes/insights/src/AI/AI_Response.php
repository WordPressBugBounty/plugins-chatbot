<?php
/**
 * Provider-neutral AI response.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\AI;

defined( 'ABSPATH' ) || exit;

final class AI_Response {

	/**
	 * Output text.
	 *
	 * @var string
	 */
	public $text = '';

	/**
	 * Input tokens used, when the provider reports it.
	 *
	 * @var int
	 */
	public $input_tokens = 0;

	/**
	 * Output tokens used, when the provider reports it.
	 *
	 * @var int
	 */
	public $output_tokens = 0;

	/**
	 * Model that answered.
	 *
	 * @var string
	 */
	public $model = '';

	/**
	 * Error code, empty on success.
	 *
	 * @var string
	 */
	public $error_code = '';

	/**
	 * Error message, empty on success.
	 *
	 * @var string
	 */
	public $error_message = '';

	/**
	 * Successful response.
	 *
	 * @param string $text   Output text.
	 * @param string $model  Model.
	 * @param int    $input  Input tokens.
	 * @param int    $output Output tokens.
	 * @return self
	 */
	public static function success( $text, $model = '', $input = 0, $output = 0 ) {
		$r                = new self();
		$r->text          = (string) $text;
		$r->model         = (string) $model;
		$r->input_tokens  = (int) $input;
		$r->output_tokens = (int) $output;
		return $r;
	}

	/**
	 * Failed response.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @return self
	 */
	public static function error( $code, $message ) {
		$r                = new self();
		$r->error_code    = (string) $code;
		$r->error_message = (string) $message;
		return $r;
	}

	/**
	 * Whether the call succeeded.
	 *
	 * @return bool
	 */
	public function ok() {
		return '' === $this->error_code;
	}
}
