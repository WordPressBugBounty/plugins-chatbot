<?php
/**
 * Reads the usage block of an AI provider's JSON answer, whatever the provider.
 *
 * Used by the shared client (cached and reasoning tokens), by add-ons that keep their own
 * HTTP calls (Spend::wrap) and by the safety net. Only the counts are read: never the text.
 *
 * Normalised counts: tokens_in is every input token (cached ones included); tokens_cached and
 * tokens_cache_write are parts of it; tokens_reasoning is part of tokens_out.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Spend;

defined( 'ABSPATH' ) || exit;

/**
 * Usage parser (pure).
 */
final class Usage_Reader {

	/**
	 * Empty usage.
	 *
	 * @return array
	 */
	public static function blank() {
		return array(
			'kind'               => 'text',
			'model'              => '',
			'tokens_in'          => 0,
			'tokens_out'         => 0,
			'tokens_cached'      => 0,
			'tokens_cache_write' => 0,
			'tokens_reasoning'   => 0,
			'images'             => 0,
			'web_searches'       => 0,
			'audio_seconds'      => 0,
			'characters'         => 0,
			'provider_cost'      => null,
		);
	}

	/**
	 * Usage from a decoded JSON body.
	 *
	 * @param mixed $data Decoded body.
	 * @return array|null Normalised usage, or null when the body has no usage at all.
	 */
	public static function from_body( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}
		$usage = self::blank();
		$found = false;

		if ( isset( $data['model'] ) && is_string( $data['model'] ) ) {
			$usage['model'] = substr( $data['model'], 0, 120 );
		}

		if ( isset( $data['usage'] ) && is_array( $data['usage'] ) ) {
			$u     = $data['usage'];
			$found = true;
			if ( isset( $u['prompt_tokens'] ) || isset( $u['completion_tokens'] ) ) {
				// Chat completions (OpenAI and compatible), embeddings.
				$usage['tokens_in']        = self::int( $u, 'prompt_tokens' );
				$usage['tokens_out']       = self::int( $u, 'completion_tokens' );
				$usage['tokens_cached']    = isset( $u['prompt_tokens_details'] ) ? self::int( $u['prompt_tokens_details'], 'cached_tokens' ) : self::int( $u, 'prompt_cache_hit_tokens' );
				$usage['tokens_reasoning'] = isset( $u['completion_tokens_details'] ) ? self::int( $u['completion_tokens_details'], 'reasoning_tokens' ) : 0;
			} elseif ( isset( $u['cache_read_input_tokens'] ) || isset( $u['cache_creation_input_tokens'] ) || isset( $u['server_tool_use'] ) ) {
				// Anthropic: input_tokens leaves out the cache reads and writes.
				$usage['tokens_cached']      = self::int( $u, 'cache_read_input_tokens' );
				$usage['tokens_cache_write'] = self::int( $u, 'cache_creation_input_tokens' );
				$usage['tokens_in']          = self::int( $u, 'input_tokens' ) + $usage['tokens_cached'] + $usage['tokens_cache_write'];
				$usage['tokens_out']         = self::int( $u, 'output_tokens' );
				$usage['web_searches']       = isset( $u['server_tool_use'] ) ? self::int( $u['server_tool_use'], 'web_search_requests' ) : 0;
			} else {
				// Responses API and image API (gpt-image models count tokens too).
				$usage['tokens_in']        = self::int( $u, 'input_tokens' );
				$usage['tokens_out']       = self::int( $u, 'output_tokens' );
				$usage['tokens_cached']    = isset( $u['input_tokens_details'] ) ? self::int( $u['input_tokens_details'], 'cached_tokens' ) : 0;
				$usage['tokens_reasoning'] = isset( $u['output_tokens_details'] ) ? self::int( $u['output_tokens_details'], 'reasoning_tokens' ) : 0;
				if ( 0 === $usage['tokens_in'] && isset( $u['total_tokens'] ) ) {
					$usage['tokens_in'] = self::int( $u, 'total_tokens' );
				}
			}
			// OpenRouter reports what it charged.
			if ( isset( $u['cost'] ) && is_numeric( $u['cost'] ) ) {
				$usage['provider_cost'] = max( 0.0, (float) $u['cost'] );
			}
		}

		if ( isset( $data['usageMetadata'] ) && is_array( $data['usageMetadata'] ) ) {
			$m                         = $data['usageMetadata'];
			$found                     = true;
			$usage['tokens_in']        = self::int( $m, 'promptTokenCount' );
			$usage['tokens_reasoning'] = self::int( $m, 'thoughtsTokenCount' );
			$usage['tokens_out']       = self::int( $m, 'candidatesTokenCount' ) + $usage['tokens_reasoning'];
			$usage['tokens_cached']    = self::int( $m, 'cachedContentTokenCount' );
			if ( isset( $data['modelVersion'] ) && is_string( $data['modelVersion'] ) ) {
				$usage['model'] = substr( $data['modelVersion'], 0, 120 );
			}
		}

		// Embeddings answers: a list of vectors.
		if ( ( isset( $data['object'] ) && 'list' === $data['object'] && isset( $data['data'][0]['embedding'] ) ) || isset( $data['embeddings'] ) || isset( $data['embedding']['values'] ) ) {
			$usage['kind'] = 'embedding';
			$found         = true;
		}

		$images = self::count_images( $data );
		if ( $images > 0 ) {
			$usage['images'] = $images;
			$usage['kind']   = 'image';
			$found           = true;
		}
		$searches = self::count_web_searches( $data );
		if ( $searches > $usage['web_searches'] ) {
			$usage['web_searches'] = $searches;
		}

		$usage['tokens_cached']      = min( $usage['tokens_cached'], $usage['tokens_in'] );
		$usage['tokens_cache_write'] = min( $usage['tokens_cache_write'], $usage['tokens_in'] - $usage['tokens_cached'] );
		$usage['tokens_reasoning']   = min( $usage['tokens_reasoning'], $usage['tokens_out'] );
		return $found ? $usage : null;
	}

	/**
	 * Images in an answer: image API data[], Responses image_generation_call items, Gemini inline images.
	 *
	 * @param array $data Body.
	 * @return int
	 */
	private static function count_images( array $data ) {
		$count = 0;
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			foreach ( $data['data'] as $item ) {
				if ( is_array( $item ) && ( isset( $item['b64_json'] ) || isset( $item['url'] ) ) && ! isset( $item['embedding'] ) ) {
					++$count;
				}
			}
		}
		foreach ( isset( $data['output'] ) && is_array( $data['output'] ) ? $data['output'] : array() as $item ) {
			if ( is_array( $item ) && isset( $item['type'] ) && 'image_generation_call' === $item['type'] ) {
				++$count;
			}
		}
		foreach ( isset( $data['candidates'] ) && is_array( $data['candidates'] ) ? $data['candidates'] : array() as $candidate ) {
			foreach ( isset( $candidate['content']['parts'] ) ? (array) $candidate['content']['parts'] : array() as $part ) {
				$mime = isset( $part['inlineData']['mimeType'] ) ? (string) $part['inlineData']['mimeType'] : '';
				if ( 0 === strpos( $mime, 'image/' ) ) {
					++$count;
				}
			}
		}
		return $count;
	}

	/**
	 * Web searches in a Responses API answer.
	 *
	 * @param array $data Body.
	 * @return int
	 */
	private static function count_web_searches( array $data ) {
		$count = 0;
		foreach ( isset( $data['output'] ) && is_array( $data['output'] ) ? $data['output'] : array() as $item ) {
			if ( is_array( $item ) && isset( $item['type'] ) && 'web_search_call' === $item['type'] ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Non-negative integer field.
	 *
	 * @param mixed  $values Array.
	 * @param string $key    Key.
	 * @return int
	 */
	private static function int( $values, $key ) {
		return is_array( $values ) && isset( $values[ $key ] ) && is_numeric( $values[ $key ] ) ? max( 0, (int) $values[ $key ] ) : 0;
	}
}
