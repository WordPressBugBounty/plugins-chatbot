<?php
/**
 * Result of one successful AI call.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Text plus usage, provider-neutral.
 */
final class Response {

	/**
	 * Answer text.
	 *
	 * @var string
	 */
	public $text = '';

	/**
	 * Input tokens billed.
	 *
	 * @var int
	 */
	public $tokens_in = 0;

	/**
	 * Output tokens billed (includes hidden reasoning where the provider bills it).
	 *
	 * @var int
	 */
	public $tokens_out = 0;

	/**
	 * Provider id that answered.
	 *
	 * @var string
	 */
	public $provider = '';

	/**
	 * Model that answered.
	 *
	 * @var string
	 */
	public $model = '';

	/**
	 * Whether the answer came from the result cache.
	 *
	 * @var bool
	 */
	public $cached = false;

	/**
	 * Whether the provider stopped because of the token ceiling.
	 *
	 * @var bool
	 */
	public $truncated = false;

	/**
	 * Estimated cost in USD.
	 *
	 * @var float
	 */
	public $cost = 0.0;

	/**
	 * Web pages the answer used (web search only): [{url, title}].
	 *
	 * @var array[]
	 */
	public $sources = array();

	/**
	 * Tools the model asked to run: [ { id, name, arguments (array) } ] (see AI\Tool_Calls).
	 *
	 * @var array[]
	 */
	public $tool_calls = array();

	/**
	 * Why the answer ended: 'tool_calls', 'stop', 'length', … ('' when the provider did not say).
	 *
	 * @var string
	 */
	public $finish_reason = '';

	/**
	 * Input tokens served from the provider's cache (part of tokens_in, billed at a discount).
	 *
	 * @var int
	 */
	public $tokens_cached = 0;

	/**
	 * Input tokens written to the provider's cache (part of tokens_in; Anthropic bills extra).
	 *
	 * @var int
	 */
	public $tokens_cache_write = 0;

	/**
	 * Reasoning tokens (part of tokens_out).
	 *
	 * @var int
	 */
	public $tokens_reasoning = 0;

	/**
	 * Web searches the provider ran for the answer.
	 *
	 * @var int
	 */
	public $web_searches = 0;

	/**
	 * Cost the provider reported itself (OpenRouter), USD, or null.
	 *
	 * @var float|null
	 */
	public $provider_cost = null;

	/**
	 * Decode the answer as JSON (tolerates code fences around it).
	 *
	 * @return array|null
	 */
	public function json() {
		$text = trim( $this->text );
		if ( preg_match( '/^```(?:json)?\s*(.*?)\s*```$/s', $text, $match ) ) {
			$text = $match[1];
		}
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		if ( false === $start || false === $end || $end < $start ) {
			return null;
		}
		$data = json_decode( substr( $text, $start, $end - $start + 1 ), true );
		return is_array( $data ) ? $data : null;
	}
}
