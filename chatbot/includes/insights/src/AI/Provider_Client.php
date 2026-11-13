<?php
/**
 * AI client that calls one provider (OpenAI, Claude, Gemini, Grok,
 * OpenRouter, Mistral or Ollama) with the key, model and (for self-hosted
 * providers) server address saved in Insights' AI settings.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Sends a provider-neutral request to the chosen AI provider.
 */
final class Provider_Client implements AI_Client {

	const MAX_TRIES   = 3;
	const RETRY_UNTIL = 45; // Seconds: no new attempt starts after this.

	/**
	 * Provider id.
	 *
	 * @var string
	 */
	private $provider;

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Model id.
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Server address for self-hosted providers (Ollama).
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Module and feature of the request being sent (WPBot Core spending log).
	 *
	 * @var array<string,string>
	 */
	private $spend = array();

	/**
	 * Constructor.
	 *
	 * @param string $provider Provider id.
	 * @param string $key      API key.
	 * @param string $model    Model id.
	 * @param string $base_url Server address, for providers that need one.
	 */
	public function __construct( $provider, $key, $model, $base_url = '' ) {
		$this->provider = (string) $provider;
		$this->key      = trim( (string) $key );
		$this->model    = trim( (string) $model );
		$this->base_url = untrailingslashit( trim( (string) $base_url ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_available() {
		$p = Providers::get( $this->provider );
		if ( null === $p || '' === $this->model ) {
			return false;
		}
		if ( Providers::needs_key( $p ) && '' === $this->key ) {
			return false;
		}
		if ( Providers::needs_url( $p ) && '' === $this->base_url ) {
			return false;
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		$p = Providers::get( $this->provider );
		return $p ? $p['label'] . ' · ' . $this->model : '';
	}

	/**
	 * Provider id.
	 *
	 * @return string
	 */
	public function get_provider() {
		return $this->provider;
	}

	/**
	 * Model id.
	 *
	 * @return string
	 */
	public function get_model() {
		return $this->model;
	}

	/**
	 * Send a request and return the answer (or an error response).
	 *
	 * @param AI_Request $request Request.
	 * @return AI_Response
	 */
	public function complete( AI_Request $request ) {
		$p = Providers::get( $this->provider );
		if ( ! $p || ! $this->is_available() ) {
			return AI_Response::error( 'ai_unavailable', __( 'Choose an AI provider and add its API key (or server address) and model.', 'chatbot' ) );
		}

		// WPBot Core's AI spending limits (AI Settings > Spending and limits), when the host has them.
		$this->spend = array(
			'module'  => '' !== (string) $request->module ? sanitize_key( (string) $request->module ) : 'insights',
			'feature' => '' !== (string) $request->feature ? sanitize_key( (string) $request->feature ) : 'insight',
		);
		if ( function_exists( 'qcld_wpbot_core_spend_allow' ) ) {
			$allowed = qcld_wpbot_core_spend_allow( $this->spend['module'], $this->spend['feature'] );
			if ( is_wp_error( $allowed ) ) {
				return AI_Response::error( 'ai_spend_limit', $allowed->get_error_message() );
			}
		}

		switch ( $p['api'] ) {
			case 'anthropic':
				return $this->anthropic( $p, $request );
			case 'gemini':
				return $this->gemini( $p, $request );
			default:
				return $this->openai( $p, $request );
		}
	}

	/**
	 * OpenAI chat completions format (OpenAI, Grok, OpenRouter, Mistral, Ollama).
	 *
	 * @param array<string,mixed> $p       Provider.
	 * @param AI_Request          $request Request.
	 * @return AI_Response
	 */
	private function openai( array $p, AI_Request $request ) {
		$body = array(
			'model'    => $this->model,
			'messages' => array_values( $request->messages ),
		);
		if ( 'openai' === $this->provider ) {
			// Every OpenAI model newer than the GPT-4 family (o-series, GPT-5, GPT-6 and later)
			// spends part of max_completion_tokens on reasoning and rejects a custom temperature.
			$reasoning                     = ! preg_match( '/^(ft:)?(gpt-4|gpt-3\.5|chatgpt-4o)/', $this->model );
			$body['max_completion_tokens'] = $reasoning ? max( 2000, (int) $request->max_tokens ) : (int) $request->max_tokens;
			if ( ! $reasoning ) {
				$body['temperature'] = (float) $request->temperature;
			}
			if ( 'json' === $request->response_format ) {
				$body['response_format'] = array( 'type' => 'json_object' );
			}
		} else {
			$body['max_tokens']  = (int) $request->max_tokens;
			$body['temperature'] = (float) $request->temperature;
			if ( 'json' === $request->response_format && in_array( $this->provider, array( 'mistral', 'ollama' ), true ) ) {
				$body['response_format'] = array( 'type' => 'json_object' );
			}
		}
		$headers = array(
			'Content-Type' => 'application/json',
		);
		if ( '' !== $this->key ) {
			$headers['Authorization'] = 'Bearer ' . $this->key;
		}
		if ( 'openrouter' === $this->provider ) {
			$headers['HTTP-Referer'] = home_url( '/' );
			$headers['X-Title']      = 'WordPress Site Insight';
		}
		$data = $this->post( $this->endpoint( $p ), $headers, $body, $request->timeout );
		if ( $data instanceof AI_Response ) {
			return $data;
		}
		$text = $data['choices'][0]['message']['content'] ?? '';
		return AI_Response::success( is_string( $text ) ? $text : '', $data['model'] ?? $this->model, $data['usage']['prompt_tokens'] ?? 0, $data['usage']['completion_tokens'] ?? 0 );
	}

	/**
	 * Anthropic Messages API.
	 *
	 * @param array<string,mixed> $p       Provider.
	 * @param AI_Request          $request Request.
	 * @return AI_Response
	 */
	private function anthropic( array $p, AI_Request $request ) {
		$system   = '';
		$messages = array();
		foreach ( $request->messages as $m ) {
			if ( 'system' === $m['role'] ) {
				$system .= $m['content'] . "\n";
			} else {
				$messages[] = array(
					'role'    => 'assistant' === $m['role'] ? 'assistant' : 'user',
					'content' => $m['content'],
				);
			}
		}
		$body = array(
			'model'       => $this->model,
			'max_tokens'  => (int) $request->max_tokens,
			'temperature' => (float) $request->temperature,
			'messages'    => $messages,
		);
		if ( '' !== $system ) {
			$body['system'] = trim( $system );
		}
		$data = $this->post(
			$this->endpoint( $p ),
			array(
				'x-api-key'         => $this->key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			),
			$body,
			$request->timeout
		);
		if ( $data instanceof AI_Response ) {
			return $data;
		}
		$text = '';
		foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
			if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
				$text .= $block['text'];
			}
		}
		return AI_Response::success( $text, $data['model'] ?? $this->model, $data['usage']['input_tokens'] ?? 0, $data['usage']['output_tokens'] ?? 0 );
	}

	/**
	 * Google Gemini generateContent.
	 *
	 * @param array<string,mixed> $p       Provider.
	 * @param AI_Request          $request Request.
	 * @return AI_Response
	 */
	private function gemini( array $p, AI_Request $request ) {
		$system   = '';
		$contents = array();
		foreach ( $request->messages as $m ) {
			if ( 'system' === $m['role'] ) {
				$system .= $m['content'] . "\n";
				continue;
			}
			$contents[] = array(
				'role'  => 'assistant' === $m['role'] ? 'model' : 'user',
				'parts' => array( array( 'text' => $m['content'] ) ),
			);
		}
		$config = array(
			'maxOutputTokens' => (int) $request->max_tokens,
			'temperature'     => (float) $request->temperature,
		);
		if ( 'json' === $request->response_format ) {
			$config['responseMimeType'] = 'application/json';
		}
		if ( preg_match( '/^gemini-2\.5-(flash|flash-lite)/', $this->model ) ) {
			// Thinking would use up the small output budget.
			$config['thinkingConfig'] = array( 'thinkingBudget' => 0 );
		}
		$body = array(
			'contents'         => $contents,
			'generationConfig' => $config,
		);
		if ( '' !== $system ) {
			$body['systemInstruction'] = array( 'parts' => array( array( 'text' => trim( $system ) ) ) );
		}
		$data = $this->post(
			$this->endpoint( $p ),
			array(
				'x-goog-api-key' => $this->key,
				'Content-Type'   => 'application/json',
			),
			$body,
			$request->timeout
		);
		if ( $data instanceof AI_Response ) {
			return $data;
		}
		$text = '';
		foreach ( (array) ( $data['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
			if ( isset( $part['text'] ) && empty( $part['thought'] ) ) {
				$text .= $part['text'];
			}
		}
		return AI_Response::success( $text, $data['modelVersion'] ?? $this->model, $data['usageMetadata']['promptTokenCount'] ?? 0, $data['usageMetadata']['candidatesTokenCount'] ?? 0 );
	}

	/**
	 * Endpoint URL for this provider and model.
	 *
	 * @param array<string,mixed> $p Provider.
	 * @return string
	 */
	private function endpoint( array $p ) {
		$url = str_replace(
			array( '{model}', '{base}' ),
			array( rawurlencode( $this->model ), $this->base_url ),
			(string) $p['endpoint']
		);
		/**
		 * Change the URL an AI request is sent to (proxies, tests).
		 *
		 * @param string $url      URL.
		 * @param string $provider Provider id.
		 * @param string $model    Model id.
		 */
		return (string) apply_filters( 'qcin/ai_endpoint', $url, $this->provider, $this->model );
	}

	/**
	 * POST JSON and decode the reply. Retries rate-limit, server and
	 * network errors up to three times with backoff.
	 *
	 * @param string               $url     URL.
	 * @param array<string,string> $headers Headers.
	 * @param array<string,mixed>  $body    Body.
	 * @param int                  $timeout Seconds per attempt.
	 * @return array<string,mixed>|AI_Response Decoded body, or an error response.
	 */
	private function post( $url, array $headers, array $body, $timeout ) {
		$started = microtime( true );
		$error   = null;
		for ( $try = 1; $try <= self::MAX_TRIES; $try++ ) {
			$response    = wp_remote_post(
				$url,
				array(
					'headers'          => $headers,
					'body'             => wp_json_encode( $body ),
					'timeout'          => max( 5, min( 60, (int) $timeout ) ),
					// Recorded below; WPBot Core's spend safety net leaves it alone.
					'qcld_wpbot_spend' => 'metered',
				)
			);
			$retry_after = 0;
			if ( is_wp_error( $response ) ) {
				$error = AI_Response::error( 'ai_network', $response->get_error_message() );
				$retry = true;
			} else {
				$code = (int) wp_remote_retrieve_response_code( $response );
				$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
				if ( $code >= 200 && $code < 300 && is_array( $data ) ) {
					$this->record_spend( $data );
					return $data;
				}
				$error       = AI_Response::error( 'ai_http_' . $code, self::error_message( $code, $data ) );
				$retry       = 429 === $code || $code >= 500;
				$retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
			}
			$wait = $retry_after > 0 ? min( 5, $retry_after ) : ( 1 === $try ? 1 : 3 );
			if ( ! $retry || self::MAX_TRIES === $try || ( microtime( true ) - $started + $wait ) > self::RETRY_UNTIL ) {
				break;
			}
			sleep( $wait );
		}
		return $error;
	}

	/**
	 * Readable message from a provider error body.
	 *
	 * @param int   $code HTTP status.
	 * @param mixed $data Decoded body.
	 * @return string
	 */
	private static function error_message( $code, $data ) {
		$message = '';
		if ( is_array( $data ) ) {
			if ( isset( $data['error']['message'] ) ) {
				$message = (string) $data['error']['message'];
			} elseif ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
				$message = $data['error'];
			} elseif ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
				$message = $data['message'];
			} elseif ( isset( $data[0]['error']['message'] ) ) {
				$message = (string) $data[0]['error']['message'];
			}
		}
		if ( 401 === $code || 403 === $code ) {
			$prefix = __( 'The provider did not accept the API key.', 'chatbot' );
		} elseif ( 404 === $code ) {
			$prefix = __( 'The provider does not know this model.', 'chatbot' );
		} elseif ( 429 === $code ) {
			$prefix = __( 'The provider’s rate limit or quota was reached.', 'chatbot' );
		} else {
			/* translators: %d: HTTP status code */
			$prefix = sprintf( __( 'The provider returned an error (HTTP %d).', 'chatbot' ), $code );
		}
		$message = trim( wp_strip_all_tags( $message ) );
		return '' === $message ? $prefix : $prefix . ' ' . mb_substr( $message, 0, 300 );
	}

	/**
	 * Record a successful answer in WPBot Core's AI spending log, when the host has it.
	 *
	 * @param array<string,mixed> $data Decoded answer.
	 * @return void
	 */
	private function record_spend( array $data ) {
		if ( ! function_exists( 'qcld_wpbot_core_spend_record' ) || ! class_exists( '\\QuantumCloud\\WPBotCore\\Spend\\Usage_Reader' ) ) {
			return;
		}
		$usage = \QuantumCloud\WPBotCore\Spend\Usage_Reader::from_body( $data );
		qcld_wpbot_core_spend_record(
			isset( $this->spend['module'] ) ? $this->spend['module'] : 'insights',
			isset( $this->spend['feature'] ) ? $this->spend['feature'] : 'insight',
			is_array( $usage ) ? $usage : array(),
			array(
				'provider' => $this->provider,
				'model'    => $this->model,
			)
		);
	}
}
