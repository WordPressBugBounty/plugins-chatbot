<?php
/**
 * Sends one request to one provider and returns a Response or a WP_Error.
 *
 * Speaks three wire formats: OpenAI-compatible chat completions (OpenAI,
 * OpenRouter, xAI, Mistral, custom endpoints), Anthropic Messages and Google
 * Gemini generateContent. Keys travel only in request headers and never
 * appear in errors or logs.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\AI;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Stateless HTTP client.
 */
final class Provider_Client {

	/**
	 * Call a provider.
	 *
	 * @param string  $provider Provider id.
	 * @param string  $model    Model id.
	 * @param string  $key      Plain API key ('' for keyless endpoints).
	 * @param Request $request  Request.
	 * @param string  $base_url Base URL override (custom provider).
	 * @return Response|WP_Error Error data carries 'cause' and 'status'.
	 */
	public static function send( $provider, $model, $key, Request $request, $base_url = '' ) {
		$definition = Providers::get( $provider );
		if ( ! $definition ) {
			return self::error( Error_Classifier::BAD_REQUEST, 0, $provider );
		}
		$base = '' !== $base_url ? untrailingslashit( $base_url ) : $definition['base_url'];
		if ( '' === $base ) {
			return self::error( Error_Classifier::BAD_REQUEST, 0, $definition['label'] );
		}
		if ( ! empty( $definition['needs_key'] ) && '' === $key ) {
			return self::error( Error_Classifier::NO_KEY, 0, $definition['label'] );
		}

		switch ( $definition['format'] ) {
			case Providers::FORMAT_ANTHROPIC:
				list( $url, $headers, $body ) = self::anthropic_payload( $base, $model, $key, $request );
				break;
			case Providers::FORMAT_GEMINI:
				list( $url, $headers, $body ) = self::gemini_payload( $base, $model, $key, $request );
				break;
			default:
				list( $url, $headers, $body ) = self::openai_payload( $base, $model, $key, $request, $provider );
		}

		/**
		 * Change the endpoint URL (used by tests to point at a mock server).
		 *
		 * @param string $url      Endpoint.
		 * @param string $provider Provider id.
		 */
		$url = (string) apply_filters( 'qcld_wpbot_core/ai_endpoint', $url, $provider );

		$raw = self::post( $url, $headers, $body, $request->timeout );
		if ( is_wp_error( $raw ) ) {
			return self::error( Error_Classifier::NETWORK, 0, $definition['label'] );
		}

		$status = (int) wp_remote_retrieve_response_code( $raw );
		$text   = (string) wp_remote_retrieve_body( $raw );

		if ( $status < 200 || $status >= 300 ) {
			return self::error( Error_Classifier::classify( $status, $text ), $status, $definition['label'] );
		}

		$data = json_decode( $text, true );
		if ( ! is_array( $data ) ) {
			return self::error( Error_Classifier::PROVIDER_DOWN, $status, $definition['label'] );
		}

		switch ( $definition['format'] ) {
			case Providers::FORMAT_ANTHROPIC:
				$response = self::parse_anthropic( $data );
				break;
			case Providers::FORMAT_GEMINI:
				$response = self::parse_gemini( $data );
				break;
			default:
				$response = $request->web_search && 'openai' === $provider ? self::parse_openai_responses( $data ) : self::parse_openai( $data );
		}
		if ( is_wp_error( $response ) ) {
			return self::error( (string) $response->get_error_code(), $status, $definition['label'] );
		}

		$response->provider = $provider;
		$response->model    = $model;
		return $response;
	}

	/**
	 * POST once, retrying a single time on a transport error or 5xx/429
	 * when the provider asks for a short wait. Longer waits are left to the
	 * job queue, so a request never blocks for long.
	 *
	 * @param string $url     URL.
	 * @param array  $headers Headers.
	 * @param array  $body    Body.
	 * @param int    $timeout Seconds.
	 * @return array|WP_Error
	 */
	private static function post( $url, array $headers, array $body, $timeout ) {
		$args = array(
			'timeout'     => min( 120, max( 5, (int) $timeout ) ),
			'headers'     => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
			'body'        => wp_json_encode( $body ),
			'data_format' => 'body',
		);

		$response = wp_remote_post( $url, $args );
		$status   = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( 0 === $status || 429 === $status || $status >= 500 ) {
			$wait = is_wp_error( $response ) ? 1 : (int) wp_remote_retrieve_header( $response, 'retry-after' );
			if ( $wait >= 0 && $wait <= 3 ) {
				sleep( max( 1, $wait ) );
				$response = wp_remote_post( $url, $args );
			}
		}
		return $response;
	}

	/**
	 * OpenAI-compatible chat completions payload.
	 *
	 * @param string  $base     Base URL.
	 * @param string  $model    Model.
	 * @param string  $key      Key.
	 * @param Request $request  Request.
	 * @param string  $provider Provider id.
	 * @return array{0: string, 1: array, 2: array}
	 */
	private static function openai_payload( $base, $model, $key, Request $request, $provider ) {
		$messages = array();
		if ( '' !== $request->system ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $request->system,
			);
		}
		foreach ( $request->messages as $message ) {
			$messages[] = array(
				'role'    => $message['role'],
				'content' => $message['content'],
			);
		}
		if ( array() !== $request->images ) {
			$last  = count( $messages ) - 1;
			$parts = array(
				array(
					'type' => 'text',
					'text' => (string) $messages[ $last ]['content'],
				),
			);
			foreach ( $request->images as $image ) {
				$parts[] = 'application/pdf' === $image['mime'] ? array(
					'type' => 'file',
					'file' => array(
						'filename'  => 'document.pdf',
						'file_data' => 'data:application/pdf;base64,' . $image['data'],
					),
				) : array(
					'type'      => 'image_url',
					'image_url' => array( 'url' => 'data:' . $image['mime'] . ';base64,' . $image['data'] ),
				);
			}
			$messages[ $last ]['content'] = $parts;
		}

		if ( $request->web_search && 'openai' === $provider ) {
			return self::openai_responses_payload( $base, $model, $key, $request );
		}
		$body = array(
			'model'    => $request->web_search && 'openrouter' === $provider && false === strpos( $model, ':online' ) ? $model . ':online' : $model,
			'messages' => $messages,
		);

		// Reasoning models (o-series, GPT-5) count hidden reasoning in the
		// completion budget, take max_completion_tokens and no temperature.
		$reasoning = 'openai' === $provider && (bool) preg_match( '/^(o\d|gpt-5)/', $model );
		if ( $reasoning ) {
			$body['max_completion_tokens'] = $request->max_tokens * 4;
			$body['reasoning_effort']      = 'creative' === $request->mode ? 'medium' : 'low';
		} else {
			$body['max_tokens']  = $request->max_tokens;
			$body['temperature'] = 'creative' === $request->mode ? 0.8 : 0.2;
		}
		if ( $request->json ) {
			$body['response_format'] = array( 'type' => 'json_object' );
		}

		$headers = array();
		if ( '' !== $key ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}
		if ( 'openrouter' === $provider ) {
			$headers['HTTP-Referer'] = home_url( '/' );
			$headers['X-Title']      = 'WPBot';
		}
		return array( $base . '/chat/completions', $headers, $body );
	}

	/**
	 * OpenAI Responses payload with the web search tool.
	 *
	 * @param string  $base    Base URL.
	 * @param string  $model   Model.
	 * @param string  $key     Key.
	 * @param Request $request Request.
	 * @return array{0: string, 1: array, 2: array}
	 */
	private static function openai_responses_payload( $base, $model, $key, Request $request ) {
		$instructions = $request->system . ( $request->json ? "\n\nAnswer with one JSON object only, no other text." : '' );
		$body         = array(
			'model'             => $model,
			'input'             => self::responses_input( $request ),
			'tools'             => array( array( 'type' => 'web_search' ) ),
			'max_output_tokens' => $request->max_tokens * 4,
		);
		if ( '' !== trim( $instructions ) ) {
			$body['instructions'] = trim( $instructions );
		}
		return array( $base . '/responses', array( 'Authorization' => 'Bearer ' . $key ), $body );
	}

	/**
	 * Responses API input: the messages, images added to the last one.
	 *
	 * @param Request $request Request.
	 * @return array[]
	 */
	private static function responses_input( Request $request ) {
		$input = array_values( $request->messages );
		if ( array() === $request->images || array() === $input ) {
			return $input;
		}
		$last  = count( $input ) - 1;
		$parts = array(
			array(
				'type' => 'input_text',
				'text' => (string) $input[ $last ]['content'],
			),
		);
		foreach ( $request->images as $image ) {
			$parts[] = 'application/pdf' === $image['mime'] ? array(
				'type'      => 'input_file',
				'filename'  => 'document.pdf',
				'file_data' => 'data:application/pdf;base64,' . $image['data'],
			) : array(
				'type'      => 'input_image',
				'image_url' => 'data:' . $image['mime'] . ';base64,' . $image['data'],
			);
		}
		$input[ $last ]['content'] = $parts;
		return $input;
	}

	/**
	 * Read an OpenAI Responses answer (text and URL citations).
	 *
	 * @param array $data Decoded body.
	 * @return Response|WP_Error
	 */
	private static function parse_openai_responses( array $data ) {
		if ( ! isset( $data['output'] ) || ! is_array( $data['output'] ) ) {
			return new WP_Error( Error_Classifier::PROVIDER_DOWN );
		}
		$response = new Response();
		foreach ( $data['output'] as $item ) {
			if ( ! isset( $item['type'] ) || 'message' !== $item['type'] ) {
				continue;
			}
			foreach ( isset( $item['content'] ) ? (array) $item['content'] : array() as $part ) {
				if ( isset( $part['type'] ) && 'output_text' === $part['type'] ) {
					$response->text .= (string) $part['text'];
					foreach ( isset( $part['annotations'] ) ? (array) $part['annotations'] : array() as $note ) {
						if ( isset( $note['url'] ) ) {
							$response->sources[] = array(
								'url'   => (string) $note['url'],
								'title' => isset( $note['title'] ) ? (string) $note['title'] : '',
							);
						}
					}
				}
			}
		}
		$response->truncated  = isset( $data['status'] ) && 'incomplete' === $data['status'];
		$response->tokens_in  = isset( $data['usage']['input_tokens'] ) ? (int) $data['usage']['input_tokens'] : 0;
		$response->tokens_out = isset( $data['usage']['output_tokens'] ) ? (int) $data['usage']['output_tokens'] : 0;
		return $response;
	}

	/**
	 * Anthropic Messages payload.
	 *
	 * Current Claude models think by default and reject sampling parameters,
	 * so depth is set with output effort and the token ceiling leaves room for
	 * thinking. Only text blocks are read back.
	 *
	 * @param string  $base    Base URL.
	 * @param string  $model   Model.
	 * @param string  $key     Key.
	 * @param Request $request Request.
	 * @return array{0: string, 1: array, 2: array}
	 */
	private static function anthropic_payload( $base, $model, $key, Request $request ) {
		$system = $request->system;
		if ( $request->json ) {
			$system .= "\n\nAnswer with one JSON object only, no other text.";
		}
		$body = array(
			'model'         => $model,
			'max_tokens'    => max( 4000, $request->max_tokens * 4 ),
			'messages'      => self::anthropic_messages( $request ),
			'output_config' => array( 'effort' => 'creative' === $request->mode ? 'medium' : 'low' ),
		);
		if ( '' !== trim( $system ) ) {
			$body['system'] = trim( $system );
		}
		if ( $request->web_search ) {
			$body['tools'] = array(
				array(
					'type'     => 'web_search_20250305',
					'name'     => 'web_search',
					'max_uses' => 5,
				),
			);
		}
		$headers = array(
			'x-api-key'         => $key,
			'anthropic-version' => '2023-06-01',
		);
		return array( $base . '/messages', $headers, $body );
	}

	/**
	 * Anthropic messages: images go before the text of the last message.
	 *
	 * @param Request $request Request.
	 * @return array[]
	 */
	private static function anthropic_messages( Request $request ) {
		$messages = array_values( $request->messages );
		if ( array() === $request->images || array() === $messages ) {
			return $messages;
		}
		$last  = count( $messages ) - 1;
		$parts = array();
		foreach ( $request->images as $image ) {
			$parts[] = array(
				'type'   => 'application/pdf' === $image['mime'] ? 'document' : 'image',
				'source' => array(
					'type'       => 'base64',
					'media_type' => $image['mime'],
					'data'       => $image['data'],
				),
			);
		}
		$parts[]                      = array(
			'type' => 'text',
			'text' => (string) $messages[ $last ]['content'],
		);
		$messages[ $last ]['content'] = $parts;
		return $messages;
	}

	/**
	 * Gemini generateContent payload.
	 *
	 * @param string  $base    Base URL.
	 * @param string  $model   Model.
	 * @param string  $key     Key.
	 * @param Request $request Request.
	 * @return array{0: string, 1: array, 2: array}
	 */
	private static function gemini_payload( $base, $model, $key, Request $request ) {
		$contents = array();
		foreach ( $request->messages as $message ) {
			$contents[] = array(
				'role'  => 'assistant' === $message['role'] ? 'model' : 'user',
				'parts' => array( array( 'text' => $message['content'] ) ),
			);
		}
		if ( array() !== $request->images && array() !== $contents ) {
			$last = count( $contents ) - 1;
			foreach ( $request->images as $image ) {
				$contents[ $last ]['parts'][] = array(
					'inline_data' => array(
						'mime_type' => $image['mime'],
						'data'      => $image['data'],
					),
				);
			}
		}
		$config = array(
			'temperature'     => 'creative' === $request->mode ? 0.8 : 0.2,
			'maxOutputTokens' => $request->max_tokens * 4,
		);
		if ( $request->json && ! $request->web_search ) {
			$config['responseMimeType'] = 'application/json';
		}
		$body = array(
			'contents'         => $contents,
			'generationConfig' => $config,
		);
		if ( $request->web_search ) {
			$body['tools'] = array( array( 'google_search' => new \stdClass() ) );
		}
		if ( '' !== $request->system ) {
			$body['systemInstruction'] = array( 'parts' => array( array( 'text' => $request->system ) ) );
		}
		$url = $base . '/models/' . rawurlencode( $model ) . ':generateContent';
		return array( $url, array( 'x-goog-api-key' => $key ), $body );
	}

	/**
	 * Read an OpenAI-compatible answer.
	 *
	 * @param array $data Decoded body.
	 * @return Response|WP_Error
	 */
	private static function parse_openai( array $data ) {
		$choice = isset( $data['choices'][0] ) ? $data['choices'][0] : null;
		if ( ! is_array( $choice ) ) {
			return new WP_Error( Error_Classifier::PROVIDER_DOWN );
		}
		$response            = new Response();
		$response->text      = isset( $choice['message']['content'] ) ? (string) $choice['message']['content'] : '';
		$response->truncated = isset( $choice['finish_reason'] ) && 'length' === $choice['finish_reason'];
		if ( isset( $choice['finish_reason'] ) && 'content_filter' === $choice['finish_reason'] && '' === $response->text ) {
			return new WP_Error( Error_Classifier::REFUSED );
		}
		foreach ( isset( $choice['message']['annotations'] ) ? (array) $choice['message']['annotations'] : array() as $note ) {
			if ( isset( $note['url_citation']['url'] ) ) {
				$response->sources[] = array(
					'url'   => (string) $note['url_citation']['url'],
					'title' => isset( $note['url_citation']['title'] ) ? (string) $note['url_citation']['title'] : '',
				);
			}
		}
		$response->tokens_in  = isset( $data['usage']['prompt_tokens'] ) ? (int) $data['usage']['prompt_tokens'] : 0;
		$response->tokens_out = isset( $data['usage']['completion_tokens'] ) ? (int) $data['usage']['completion_tokens'] : 0;
		return $response;
	}

	/**
	 * Read an Anthropic answer (text blocks only).
	 *
	 * @param array $data Decoded body.
	 * @return Response|WP_Error
	 */
	private static function parse_anthropic( array $data ) {
		if ( isset( $data['stop_reason'] ) && 'refusal' === $data['stop_reason'] ) {
			return new WP_Error( Error_Classifier::REFUSED );
		}
		$text    = '';
		$sources = array();
		foreach ( isset( $data['content'] ) && is_array( $data['content'] ) ? $data['content'] : array() as $block ) {
			if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
				$text .= $block['text'];
				foreach ( isset( $block['citations'] ) ? (array) $block['citations'] : array() as $cite ) {
					if ( isset( $cite['url'] ) ) {
						$sources[] = array(
							'url'   => (string) $cite['url'],
							'title' => isset( $cite['title'] ) ? (string) $cite['title'] : '',
						);
					}
				}
			}
		}
		$response             = new Response();
		$response->sources    = $sources;
		$response->text       = $text;
		$response->truncated  = isset( $data['stop_reason'] ) && 'max_tokens' === $data['stop_reason'];
		$response->tokens_in  = isset( $data['usage']['input_tokens'] ) ? (int) $data['usage']['input_tokens'] : 0;
		$response->tokens_out = isset( $data['usage']['output_tokens'] ) ? (int) $data['usage']['output_tokens'] : 0;
		return $response;
	}

	/**
	 * Read a Gemini answer.
	 *
	 * @param array $data Decoded body.
	 * @return Response|WP_Error
	 */
	private static function parse_gemini( array $data ) {
		$candidate = isset( $data['candidates'][0] ) ? $data['candidates'][0] : null;
		if ( ! is_array( $candidate ) ) {
			return new WP_Error( isset( $data['promptFeedback']['blockReason'] ) ? Error_Classifier::REFUSED : Error_Classifier::PROVIDER_DOWN );
		}
		$text = '';
		foreach ( isset( $candidate['content']['parts'] ) ? (array) $candidate['content']['parts'] : array() as $part ) {
			if ( isset( $part['text'] ) && empty( $part['thought'] ) ) {
				$text .= $part['text'];
			}
		}
		if ( '' === $text && isset( $candidate['finishReason'] ) && 'SAFETY' === $candidate['finishReason'] ) {
			return new WP_Error( Error_Classifier::REFUSED );
		}
		$response       = new Response();
		$response->text = $text;
		foreach ( isset( $candidate['groundingMetadata']['groundingChunks'] ) ? (array) $candidate['groundingMetadata']['groundingChunks'] : array() as $chunk ) {
			if ( isset( $chunk['web']['uri'] ) ) {
				$response->sources[] = array(
					'url'   => (string) $chunk['web']['uri'],
					'title' => isset( $chunk['web']['title'] ) ? (string) $chunk['web']['title'] : '',
				);
			}
		}
		$response->truncated  = isset( $candidate['finishReason'] ) && 'MAX_TOKENS' === $candidate['finishReason'];
		$response->tokens_in  = isset( $data['usageMetadata']['promptTokenCount'] ) ? (int) $data['usageMetadata']['promptTokenCount'] : 0;
		$out                  = isset( $data['usageMetadata']['candidatesTokenCount'] ) ? (int) $data['usageMetadata']['candidatesTokenCount'] : 0;
		$out                 += isset( $data['usageMetadata']['thoughtsTokenCount'] ) ? (int) $data['usageMetadata']['thoughtsTokenCount'] : 0;
		$response->tokens_out = $out;
		return $response;
	}

	/**
	 * Build a classified error with the owner-facing explanation.
	 *
	 * @param string $cause    Cause constant.
	 * @param int    $status   HTTP status.
	 * @param string $provider Provider label.
	 * @return WP_Error
	 */
	private static function error( $cause, $status, $provider ) {
		$explained = Error_Classifier::explain( $cause, $provider );
		return new WP_Error(
			'qcld_wpbot_core_ai_' . $cause,
			$explained['cause'] . ' ' . $explained['fix'],
			array(
				'cause'  => $cause,
				'status' => (int) $status,
				'fix'    => $explained['fix'],
			)
		);
	}
}
