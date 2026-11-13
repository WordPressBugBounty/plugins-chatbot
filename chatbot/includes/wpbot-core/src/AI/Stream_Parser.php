<?php
/**
 * Turns a provider's server-sent events into text pieces.
 *
 * Feed it the raw bytes as they arrive (chunks may split lines anywhere);
 * it returns the new text and keeps the usage, finish reason and any error
 * the stream reported. Formats: OpenAI-compatible chat completions,
 * Anthropic Messages and Gemini streamGenerateContent (alt=sse).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Incremental SSE parser.
 */
final class Stream_Parser {

	/**
	 * Wire format (Providers::FORMAT_*).
	 *
	 * @var string
	 */
	private $format;

	/**
	 * Bytes not yet ending in a newline.
	 *
	 * @var string
	 */
	private $buffer = '';

	/**
	 * Whole text so far.
	 *
	 * @var string
	 */
	public $text = '';

	/**
	 * Input tokens reported by the stream.
	 *
	 * @var int
	 */
	public $tokens_in = 0;

	/**
	 * Output tokens reported by the stream.
	 *
	 * @var int
	 */
	public $tokens_out = 0;

	/**
	 * Cached input tokens reported by the stream (part of tokens_in).
	 *
	 * @var int
	 */
	public $tokens_cached = 0;

	/**
	 * Reasoning tokens reported by the stream (part of tokens_out).
	 *
	 * @var int
	 */
	public $tokens_reasoning = 0;

	/**
	 * Raw finish reason ("stop", "length", "max_tokens", "MAX_TOKENS", "content_filter", "refusal", "SAFETY").
	 *
	 * @var string
	 */
	public $finish = '';

	/**
	 * Error message the stream carried, if any.
	 *
	 * @var string
	 */
	public $error = '';

	/**
	 * Constructor.
	 *
	 * @param string $format Providers::FORMAT_OPENAI, FORMAT_ANTHROPIC or FORMAT_GEMINI.
	 */
	public function __construct( $format ) {
		$this->format = (string) $format;
	}

	/**
	 * Feed raw bytes; returns the text that became complete with them.
	 *
	 * @param string $chunk Raw bytes.
	 * @return string New text.
	 */
	public function feed( $chunk ) {
		$this->buffer .= (string) $chunk;
		$new           = '';
		while ( false !== ( $pos = strpos( $this->buffer, "\n" ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$line         = rtrim( substr( $this->buffer, 0, $pos ), "\r" );
			$this->buffer = (string) substr( $this->buffer, $pos + 1 );
			$new         .= $this->line( $line );
		}
		return $new;
	}

	/**
	 * Flush a last line without a newline.
	 *
	 * @return string New text.
	 */
	public function finish() {
		$line         = trim( $this->buffer );
		$this->buffer = '';
		return '' === $line ? '' : $this->line( $line );
	}

	/**
	 * Whether the answer was cut by the token ceiling.
	 *
	 * @return bool
	 */
	public function truncated() {
		return in_array( $this->finish, array( 'length', 'max_tokens', 'MAX_TOKENS' ), true );
	}

	/**
	 * Whether the provider refused (and wrote nothing).
	 *
	 * @return bool
	 */
	public function refused() {
		return '' === $this->text && in_array( $this->finish, array( 'content_filter', 'refusal', 'SAFETY' ), true );
	}

	/**
	 * Handle one line.
	 *
	 * @param string $line Line without its newline.
	 * @return string New text.
	 */
	private function line( $line ) {
		if ( 0 !== strpos( $line, 'data:' ) ) {
			// "event:" lines, comments (":") and blank separators carry nothing we need;
			// a bare JSON error body (no SSE) is caught here.
			if ( '' !== $line && '{' === $line[0] ) {
				$this->read_error( json_decode( $line, true ) );
			}
			return '';
		}
		$payload = trim( substr( $line, 5 ) );
		if ( '' === $payload || '[DONE]' === $payload ) {
			return '';
		}
		$data = json_decode( $payload, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		if ( $this->read_error( $data ) ) {
			return '';
		}
		switch ( $this->format ) {
			case Providers::FORMAT_ANTHROPIC:
				$text = $this->anthropic( $data );
				break;
			case Providers::FORMAT_GEMINI:
				$text = $this->gemini( $data );
				break;
			default:
				$text = $this->openai( $data );
		}
		$this->text .= $text;
		return $text;
	}

	/**
	 * OpenAI-compatible chunk.
	 *
	 * @param array $data Event data.
	 * @return string
	 */
	private function openai( array $data ) {
		if ( isset( $data['usage'] ) && is_array( $data['usage'] ) ) {
			$this->tokens_in  = isset( $data['usage']['prompt_tokens'] ) ? (int) $data['usage']['prompt_tokens'] : $this->tokens_in;
			$this->tokens_out = isset( $data['usage']['completion_tokens'] ) ? (int) $data['usage']['completion_tokens'] : $this->tokens_out;
			if ( isset( $data['usage']['prompt_tokens_details']['cached_tokens'] ) ) {
				$this->tokens_cached = (int) $data['usage']['prompt_tokens_details']['cached_tokens'];
			}
			if ( isset( $data['usage']['completion_tokens_details']['reasoning_tokens'] ) ) {
				$this->tokens_reasoning = (int) $data['usage']['completion_tokens_details']['reasoning_tokens'];
			}
		}
		$choice = isset( $data['choices'][0] ) && is_array( $data['choices'][0] ) ? $data['choices'][0] : array();
		if ( ! empty( $choice['finish_reason'] ) ) {
			$this->finish = (string) $choice['finish_reason'];
		}
		return isset( $choice['delta']['content'] ) && is_string( $choice['delta']['content'] ) ? $choice['delta']['content'] : '';
	}

	/**
	 * Anthropic event (content_block_delta, message_start, message_delta).
	 *
	 * @param array $data Event data.
	 * @return string
	 */
	private function anthropic( array $data ) {
		$type = isset( $data['type'] ) ? (string) $data['type'] : '';
		if ( 'message_start' === $type && isset( $data['message']['usage']['input_tokens'] ) ) {
			$start               = $data['message']['usage'];
			$this->tokens_cached = isset( $start['cache_read_input_tokens'] ) ? (int) $start['cache_read_input_tokens'] : 0;
			$this->tokens_in     = (int) $start['input_tokens'] + $this->tokens_cached + ( isset( $start['cache_creation_input_tokens'] ) ? (int) $start['cache_creation_input_tokens'] : 0 );
		}
		if ( 'message_delta' === $type ) {
			if ( isset( $data['delta']['stop_reason'] ) ) {
				$this->finish = (string) $data['delta']['stop_reason'];
			}
			if ( isset( $data['usage']['output_tokens'] ) ) {
				$this->tokens_out = (int) $data['usage']['output_tokens'];
			}
		}
		if ( 'content_block_delta' === $type && isset( $data['delta']['type'], $data['delta']['text'] ) && 'text_delta' === $data['delta']['type'] ) {
			return (string) $data['delta']['text'];
		}
		return '';
	}

	/**
	 * Gemini chunk (a partial GenerateContentResponse).
	 *
	 * @param array $data Event data.
	 * @return string
	 */
	private function gemini( array $data ) {
		if ( isset( $data['usageMetadata'] ) ) {
			$meta             = $data['usageMetadata'];
			$this->tokens_in  = isset( $meta['promptTokenCount'] ) ? (int) $meta['promptTokenCount'] : $this->tokens_in;
			$this->tokens_out = ( isset( $meta['candidatesTokenCount'] ) ? (int) $meta['candidatesTokenCount'] : 0 ) + ( isset( $meta['thoughtsTokenCount'] ) ? (int) $meta['thoughtsTokenCount'] : 0 );

			$this->tokens_cached    = isset( $meta['cachedContentTokenCount'] ) ? (int) $meta['cachedContentTokenCount'] : $this->tokens_cached;
			$this->tokens_reasoning = isset( $meta['thoughtsTokenCount'] ) ? (int) $meta['thoughtsTokenCount'] : $this->tokens_reasoning;
		}
		$candidate = isset( $data['candidates'][0] ) && is_array( $data['candidates'][0] ) ? $data['candidates'][0] : array();
		if ( ! empty( $candidate['finishReason'] ) ) {
			$this->finish = (string) $candidate['finishReason'];
		}
		$text = '';
		foreach ( isset( $candidate['content']['parts'] ) ? (array) $candidate['content']['parts'] : array() as $part ) {
			if ( isset( $part['text'] ) && empty( $part['thought'] ) ) {
				$text .= (string) $part['text'];
			}
		}
		return $text;
	}

	/**
	 * Remember an error object ({"error": ...} in any format).
	 *
	 * @param mixed $data Decoded JSON.
	 * @return bool Whether it was an error.
	 */
	private function read_error( $data ) {
		if ( ! is_array( $data ) || ! isset( $data['error'] ) ) {
			return false;
		}
		$error       = $data['error'];
		$this->error = is_array( $error ) ? (string) ( isset( $error['message'] ) ? $error['message'] : wp_json_encode( $error ) ) : (string) $error;
		return true;
	}
}
