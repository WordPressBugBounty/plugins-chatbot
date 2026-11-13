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
		$tools = Tool_Calls::wanted( $request ) && ! $request->web_search;
		if ( $tools ) {
			$body = Tool_Calls::apply( (string) $definition['format'], $body, $request );
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
			return self::error( Error_Classifier::classify( $status, $text ), $status, $definition['label'], $text );
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
		if ( $tools ) {
			$response = Tool_Calls::read( (string) $definition['format'], $data, $response );
		}

		$response->provider = $provider;
		$response->model    = $model;
		self::usage_details( $response, $data );
		return $response;
	}

	/**
	 * Add the usage details the parsers leave out: cached input, cache writes, reasoning,
	 * web searches and a provider-reported cost (OpenRouter). Anthropic's input count is raised
	 * to include its cache reads and writes, so tokens_in always means every input token.
	 *
	 * @param Response $response Response.
	 * @param array    $data     Decoded body.
	 * @return void
	 */
	public static function usage_details( Response $response, array $data ) {
		$usage = \QuantumCloud\WPBotCore\Spend\Usage_Reader::from_body( $data );
		if ( null === $usage ) {
			return;
		}
		$response->tokens_in          = max( (int) $response->tokens_in, (int) $usage['tokens_in'] );
		$response->tokens_cached      = (int) $usage['tokens_cached'];
		$response->tokens_cache_write = (int) $usage['tokens_cache_write'];
		$response->tokens_reasoning   = (int) $usage['tokens_reasoning'];
		$response->web_searches       = (int) $usage['web_searches'];
		$response->provider_cost      = $usage['provider_cost'];
	}

	/**
	 * Call a provider and receive the answer as it is written (server-sent events).
	 *
	 * $on_text is called with each new piece of text. Requests that cannot stream
	 * (OpenAI web search) are answered in one piece through send(). Errors before
	 * the first piece are classified like send(); a stream that breaks later
	 * returns what arrived, marked truncated.
	 *
	 * @param string   $provider Provider id.
	 * @param string   $model    Model id.
	 * @param string   $key      Plain API key ('' for keyless endpoints).
	 * @param Request  $request  Request.
	 * @param callable $on_text  Called with each new piece of text (string).
	 * @param string   $base_url Base URL override (Ollama, custom provider).
	 * @return Response|WP_Error
	 */
	public static function stream( $provider, $model, $key, Request $request, callable $on_text, $base_url = '' ) {
		$definition = Providers::get( $provider );
		if ( ! $definition ) {
			return self::error( Error_Classifier::BAD_REQUEST, 0, $provider );
		}
		if ( $request->web_search && 'openai' === $provider ) {
			$response = self::send( $provider, $model, $key, $request, $base_url );
			if ( ! is_wp_error( $response ) && '' !== $response->text ) {
				call_user_func( $on_text, $response->text );
			}
			return $response;
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
				$body['stream']               = true;
				break;
			case Providers::FORMAT_GEMINI:
				list( $url, $headers, $body ) = self::gemini_payload( $base, $model, $key, $request );
				$url                          = str_replace( ':generateContent', ':streamGenerateContent', $url ) . '?alt=sse';
				break;
			default:
				list( $url, $headers, $body ) = self::openai_payload( $base, $model, $key, $request, $provider );
				$body['stream']               = true;
				if ( in_array( $provider, array( 'openai', 'openrouter', 'deepseek', 'grok' ), true ) ) {
					$body['stream_options'] = array( 'include_usage' => true );
				}
		}

		/** This filter is documented in send(). */
		$url = (string) apply_filters( 'qcld_wpbot_core/ai_endpoint', $url, $provider );

		$parser = new Stream_Parser( $definition['format'] );
		// WordPress passes only the chunk to this action (not the URL); the listener is
		// attached just for this one blocking request, so every chunk belongs to it.
		$listener = static function ( $data ) use ( $parser, $on_text ) {
			$text = $parser->feed( (string) $data );
			if ( '' !== $text ) {
				call_user_func( $on_text, $text );
			}
		};
		add_action( 'requests-request.progress', $listener, 10, 1 );
		$raw = wp_remote_post(
			$url,
			array(
				'timeout'          => min( 300, max( 30, (int) $request->timeout ) ),
				'headers'          => array_merge(
					array(
						'Content-Type' => 'application/json',
						'Accept'       => 'text/event-stream',
					),
					$headers
				),
				'body'             => wp_json_encode( $body ),
				'data_format'      => 'body',
				// Counted by the caller; the spend safety net leaves it alone.
				'qcld_wpbot_spend' => 'metered',
			)
		);
		remove_action( 'requests-request.progress', $listener, 10 );

		$tail = $parser->finish();
		if ( '' !== $tail ) {
			call_user_func( $on_text, $tail );
		}

		if ( is_wp_error( $raw ) ) {
			if ( '' === $parser->text ) {
				return self::error( Error_Classifier::NETWORK, 0, $definition['label'] );
			}
			$response            = self::stream_response( $parser, $provider, $model );
			$response->truncated = true;
			return $response;
		}
		$status = (int) wp_remote_retrieve_response_code( $raw );
		if ( $status < 200 || $status >= 300 ) {
			return self::error( Error_Classifier::classify( $status, (string) wp_remote_retrieve_body( $raw ) ), $status, $definition['label'], (string) wp_remote_retrieve_body( $raw ) );
		}
		if ( '' === $parser->text && '' !== $parser->error ) {
			return self::error( Error_Classifier::classify( 400, $parser->error ), $status, $definition['label'], $parser->error );
		}
		if ( $parser->refused() ) {
			return self::error( Error_Classifier::REFUSED, $status, $definition['label'] );
		}
		return self::stream_response( $parser, $provider, $model );
	}

	/**
	 * Response built from a finished stream.
	 *
	 * @param Stream_Parser $parser   Parser.
	 * @param string        $provider Provider id.
	 * @param string        $model    Model id.
	 * @return Response
	 */
	private static function stream_response( Stream_Parser $parser, $provider, $model ) {
		$response             = new Response();
		$response->text       = $parser->text;
		$response->truncated  = $parser->truncated();
		$response->tokens_in  = $parser->tokens_in;
		$response->tokens_out = $parser->tokens_out;
		$response->provider   = $provider;
		$response->model      = $model;

		$response->tokens_cached    = $parser->tokens_cached;
		$response->tokens_reasoning = $parser->tokens_reasoning;
		return $response;
	}

	/**
	 * Turn texts into vectors (knowledge base).
	 *
	 * @param string   $provider Embedding provider id (Embeddings::all()).
	 * @param string   $model    Model id.
	 * @param string   $key      Plain API key ('' for keyless endpoints).
	 * @param string[] $texts    Texts, at most 96 per call.
	 * @param string   $base_url Base URL override (Ollama).
	 * @return array|WP_Error { vectors: float[][], tokens: int, model: string, dimensions: int }
	 */
	public static function embed( $provider, $model, $key, array $texts, $base_url = '' ) {
		$model      = Embeddings::current_model( $provider, $model ); // A retired model's replacement (R10).
		$definition = Embeddings::get( $provider );
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
		$texts = array_values( array_map( 'strval', array_slice( $texts, 0, 96 ) ) );
		if ( array() === $texts ) {
			return array(
				'vectors'    => array(),
				'tokens'     => 0,
				'model'      => $model,
				'dimensions' => Embeddings::dimensions( $provider, $model ),
			);
		}

		if ( 'gemini' === $definition['format'] ) {
			$requests = array();
			foreach ( $texts as $text ) {
				$requests[] = array(
					'model'                => 'models/' . $model,
					'content'              => array( 'parts' => array( array( 'text' => $text ) ) ),
					'outputDimensionality' => Embeddings::dimensions( $provider, $model ) ? Embeddings::dimensions( $provider, $model ) : 768,
				);
			}
			$url     = $base . '/models/' . rawurlencode( $model ) . ':batchEmbedContents';
			$headers = array( 'x-goog-api-key' => $key );
			$body    = array( 'requests' => $requests );
		} else {
			$url     = $base . '/embeddings';
			$headers = '' !== $key ? array( 'Authorization' => 'Bearer ' . $key ) : array();
			$body    = array(
				'model' => $model,
				'input' => $texts,
			);
		}

		/** This filter is documented in send(). */
		$url = (string) apply_filters( 'qcld_wpbot_core/ai_endpoint', $url, $provider );
		$raw = self::post( $url, $headers, $body, 60 );
		if ( is_wp_error( $raw ) ) {
			return self::error( Error_Classifier::NETWORK, 0, $definition['label'] );
		}
		$status = (int) wp_remote_retrieve_response_code( $raw );
		$text   = (string) wp_remote_retrieve_body( $raw );
		if ( $status < 200 || $status >= 300 ) {
			return self::error( Error_Classifier::classify( $status, $text ), $status, $definition['label'], $text );
		}
		$data = json_decode( $text, true );
		if ( ! is_array( $data ) ) {
			return self::error( Error_Classifier::PROVIDER_DOWN, $status, $definition['label'] );
		}

		$vectors = array();
		if ( 'gemini' === $definition['format'] ) {
			foreach ( isset( $data['embeddings'] ) ? (array) $data['embeddings'] : array() as $item ) {
				$vectors[] = array_map( 'floatval', isset( $item['values'] ) ? (array) $item['values'] : array() );
			}
			$tokens = 0;
		} else {
			$items = isset( $data['data'] ) ? (array) $data['data'] : array();
			usort(
				$items,
				static function ( $a, $b ) {
					return ( isset( $a['index'] ) ? (int) $a['index'] : 0 ) <=> ( isset( $b['index'] ) ? (int) $b['index'] : 0 );
				}
			);
			foreach ( $items as $item ) {
				$vectors[] = array_map( 'floatval', isset( $item['embedding'] ) ? (array) $item['embedding'] : array() );
			}
			$tokens = isset( $data['usage']['total_tokens'] ) ? (int) $data['usage']['total_tokens'] : ( isset( $data['usage']['prompt_tokens'] ) ? (int) $data['usage']['prompt_tokens'] : 0 );
		}
		if ( count( $vectors ) !== count( $texts ) || ( isset( $vectors[0] ) && array() === $vectors[0] ) ) {
			return self::error( Error_Classifier::PROVIDER_DOWN, $status, $definition['label'] );
		}
		return array(
			'vectors'    => $vectors,
			'tokens'     => $tokens,
			'model'      => $model,
			'dimensions' => count( $vectors[0] ),
		);
	}

	/**
	 * Models the account can use, straight from the provider (settings screens).
	 *
	 * @param string $provider Provider id.
	 * @param string $key      Plain API key ('' for keyless endpoints).
	 * @param string $base_url Base URL override (Ollama, custom provider).
	 * @return string[]|WP_Error Model ids, sorted.
	 */
	public static function list_models( $provider, $key, $base_url = '' ) {
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
				$headers = array(
					'x-api-key'         => $key,
					'anthropic-version' => '2023-06-01',
				);
				$url     = $base . '/models?limit=100';
				break;
			case Providers::FORMAT_GEMINI:
				$headers = array( 'x-goog-api-key' => $key );
				$url     = $base . '/models?pageSize=200';
				break;
			default:
				$headers = '' !== $key ? array( 'Authorization' => 'Bearer ' . $key ) : array();
				$url     = $base . '/models';
		}
		/** This filter is documented in send(). */
		$url = (string) apply_filters( 'qcld_wpbot_core/ai_endpoint', $url, $provider );
		$raw = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => $headers,
			)
		);
		if ( is_wp_error( $raw ) ) {
			return self::error( Error_Classifier::NETWORK, 0, $definition['label'] );
		}
		$status = (int) wp_remote_retrieve_response_code( $raw );
		$text   = (string) wp_remote_retrieve_body( $raw );
		if ( $status < 200 || $status >= 300 ) {
			return self::error( Error_Classifier::classify( $status, $text ), $status, $definition['label'], $text );
		}
		$data = json_decode( $text, true );
		$ids  = array();
		foreach ( isset( $data['data'] ) ? (array) $data['data'] : ( isset( $data['models'] ) ? (array) $data['models'] : array() ) as $item ) {
			if ( isset( $item['id'] ) ) {
				$ids[] = (string) $item['id'];
			} elseif ( isset( $item['name'] ) ) {
				$ids[] = preg_replace( '#^models/#', '', (string) $item['name'] );
			}
		}
		$ids = array_values( array_unique( $ids ) );
		sort( $ids );
		return $ids;
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
			'timeout'          => min( 120, max( 5, (int) $timeout ) ),
			'headers'          => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
			'body'             => wp_json_encode( $body ),
			'data_format'      => 'body',
			// Counted by the caller; the spend safety net leaves it alone.
			'qcld_wpbot_spend' => 'metered',
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

		// OpenAI: only the older GPT-4 and GPT-3.5 families take max_tokens and a temperature.
		// Every newer model (o-series, GPT-5, GPT-6 and later, chat-latest) counts hidden
		// reasoning in the completion budget, takes max_completion_tokens and no temperature.
		if ( 'openai' === $provider && ! self::openai_legacy_params( $model ) ) {
			$body['max_completion_tokens'] = $request->max_tokens * 4;
			if ( self::openai_reasoning_effort( $model ) ) {
				$body['reasoning_effort'] = 'creative' === $request->mode ? 'medium' : 'low';
			}
		} else {
			$body['max_tokens']  = $request->max_tokens;
			$body['temperature'] = $request->temperature();
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
			$headers['X-Title']      = \QuantumCloud\WPBotCore\Host\Brand::product();
		}
		return array( $base . '/chat/completions', $headers, $body );
	}

	/**
	 * Whether an OpenAI model still takes max_tokens and temperature (GPT-4, GPT-4o, GPT-4.1,
	 * GPT-3.5 and fine-tunes of them). Newer models reject both.
	 *
	 * @param string $model Model id.
	 * @return bool
	 */
	public static function openai_legacy_params( $model ) {
		$model = (string) preg_replace( '/^ft:/', '', (string) $model );
		return (bool) preg_match( '/^(gpt-4|gpt-3\.5|chatgpt-4o)/', $model );
	}

	/**
	 * Whether an OpenAI model takes reasoning_effort low/medium (o-series, GPT-5 and later).
	 * chat-latest and other aliases do not.
	 *
	 * @param string $model Model id.
	 * @return bool
	 */
	public static function openai_reasoning_effort( $model ) {
		return (bool) preg_match( '/^(o\d|gpt-([5-9]|\d{2}))/', (string) $model );
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
			'temperature'     => $request->temperature(),
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
	 * @param string $body     The provider's answer, when it sent one (kept as `detail` for site admins).
	 * @return WP_Error
	 */
	private static function error( $cause, $status, $provider, $body = '' ) {
		$explained = Error_Classifier::explain( $cause, $provider );
		$data      = array(
			'cause'  => $cause,
			'status' => (int) $status,
			'fix'    => $explained['fix'],
		);
		$detail    = self::detail( $body );
		if ( '' !== $detail ) {
			$data['detail'] = $detail;
		}
		return new WP_Error( 'qcld_wpbot_core_ai_' . $cause, $explained['cause'] . ' ' . $explained['fix'], $data );
	}

	/**
	 * The provider's own error message from its answer (error.message, message, or the text),
	 * one line, at most 300 characters, keys masked. For site admins only; never for visitors.
	 *
	 * @param string $body Answer body.
	 * @return string
	 */
	public static function detail( $body ) {
		$body = (string) $body;
		if ( '' === trim( $body ) ) {
			return '';
		}
		$data = json_decode( $body, true );
		if ( isset( $data[0] ) && is_array( $data[0] ) ) {
			$data = $data[0]; // Gemini sometimes wraps the error in a list.
		}
		if ( isset( $data['error']['message'] ) ) {
			$text = (string) $data['error']['message'];
		} elseif ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
			$text = $data['error'];
		} elseif ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
			$text = $data['message'];
		} else {
			$text = $body;
		}
		$text = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
		$text = (string) preg_replace( '/\b(sk|xai|gsk|AIza|pk)[-_A-Za-z0-9]{8,}/', '$1-…', $text );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 300 ) : substr( $text, 0, 300 );
	}
}
