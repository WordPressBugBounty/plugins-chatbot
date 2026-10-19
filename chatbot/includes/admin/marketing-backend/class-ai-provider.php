<?php
/**
 * AI Provider for WPBot Email Marketing.
 *
 * Multi-provider AI generation supporting OpenAI, Google Gemini, Anthropic Claude,
 * OpenRouter, and xAI Grok using configured API keys from Email Marketing or Chatbot.
 *
 * @package WPBot\EmailMarketing
 */

namespace WPBot\EmailMarketing;

use WPBot\EmailMarketing\Controllers\ThirdPartyController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiProvider {

	/**
	 * Get the currently active AI provider.
	 *
	 * @return string 'openai'|'gemini'|'claude'|'openrouter'|'grok'
	 */
	public static function get_active_provider(): string {
		$provider = get_option( 'qcwpbot_ai_provider', '' );
		if ( ! empty( $provider ) ) {
			return strtolower( trim( (string) $provider ) );
		}
		// Smart fallbacks based on existing configured keys
		if ( ! empty( get_option( 'qcld_gemini_api_key' ) ) ) {
			return 'gemini';
		}
		if ( ! empty( get_option( 'qcld_claude_api_key' ) ) ) {
			return 'claude';
		}
		if ( ! empty( get_option( 'qcld_openrouter_api_key' ) ) ) {
			return 'openrouter';
		}
		if ( ! empty( get_option( 'qcld_grok_api_key' ) ) ) {
			return 'grok';
		}
		return 'openai';
	}

	/**
	 * Get the API key for the given provider.
	 *
	 * @param string $provider
	 * @return string
	 */
	public static function get_api_key( string $provider = '' ): string {
		if ( empty( $provider ) ) {
			$provider = self::get_active_provider();
		}
		switch ( $provider ) {
			case 'gemini':
				$key = get_option( 'qcwpbot_gemini_api_key', '' );
				return ! empty( $key ) ? trim( (string) $key ) : trim( (string) get_option( 'qcld_gemini_api_key', '' ) );

			case 'claude':
				$key = get_option( 'qcwpbot_claude_api_key', '' );
				return ! empty( $key ) ? trim( (string) $key ) : trim( (string) get_option( 'qcld_claude_api_key', '' ) );

			case 'openrouter':
				$key = get_option( 'qcwpbot_openrouter_api_key', '' );
				return ! empty( $key ) ? trim( (string) $key ) : trim( (string) get_option( 'qcld_openrouter_api_key', '' ) );

			case 'grok':
				$key = get_option( 'qcwpbot_grok_api_key', '' );
				return ! empty( $key ) ? trim( (string) $key ) : trim( (string) get_option( 'qcld_grok_api_key', '' ) );

			case 'openai':
			default:
				$key = get_option( 'qcwpbot_openai_api_key', '' );
				if ( ! empty( $key ) ) {
					return trim( (string) $key );
				}
				$lf_settings = get_option( 'qcwpbot_leadfinder_settings', array() );
				if ( ! empty( $lf_settings['openai_key'] ) ) {
					return trim( (string) $lf_settings['openai_key'] );
				}
				if ( class_exists( '\WPBot\EmailMarketing\Controllers\ThirdPartyController' ) ) {
					$tp_key = ThirdPartyController::get_openai_api_key();
					if ( ! empty( $tp_key ) ) {
						return $tp_key;
					}
				}
				return trim( (string) get_option( 'open_ai_api_key', '' ) );
		}
	}

	/**
	 * Generate AI text response.
	 *
	 * @param string $prompt
	 * @param string $mode
	 * @param int    $max_tokens
	 * @param string $override_provider
	 * @param string $override_key
	 * @param string $override_model
	 * @return array
	 */
	public static function generate( string $prompt, string $mode = 'text', int $max_tokens = 2048, string $override_provider = '', string $override_key = '', string $override_model = '' ): array {
		$provider     = ! empty( $override_provider ) ? strtolower( trim( $override_provider ) ) : self::get_active_provider();
		$api_key      = ! empty( $override_key ) ? trim( $override_key ) : self::get_api_key( $provider );
		$custom_model = ! empty( $override_model ) ? trim( $override_model ) : trim( (string) get_option( 'qcwpbot_ai_model', '' ) );

		if ( empty( $api_key ) ) {
			$labels = array(
				'openai'     => 'OpenAI',
				'gemini'     => 'Google Gemini',
				'claude'     => 'Anthropic Claude',
				'openrouter' => 'OpenRouter',
				'grok'       => 'xAI Grok',
			);
			$name = $labels[ $provider ] ?? 'AI';
			return array(
				'success' => false,
				'content' => sprintf(
					/* translators: %s: provider name */
					__( '%s API key is not configured. Please enter your API key in General Settings -> AI Provider & API.', 'chatbot' ),
					$name
				),
			);
		}

		switch ( $provider ) {
			case 'gemini':
				return self::generate_gemini( $prompt, $api_key, $custom_model, $max_tokens );

			case 'claude':
				return self::generate_claude( $prompt, $api_key, $custom_model, $max_tokens );

			case 'openrouter':
				return self::generate_openrouter( $prompt, $api_key, $custom_model, $max_tokens );

			case 'grok':
				return self::generate_grok( $prompt, $api_key, $custom_model, $max_tokens );

			case 'openai':
			default:
				return self::generate_openai( $prompt, $api_key, $custom_model, $max_tokens );
		}
	}

	/**
	 * OpenAI generator.
	 */
	private static function generate_openai( string $prompt, string $api_key, string $model, int $max_tokens ): array {
		$model = ! empty( $model ) ? $model : 'gpt-4o-mini';
		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'timeout' => 45,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'       => $model,
				'messages'    => array(
					array( 'role' => 'user', 'content' => $prompt ),
				),
				'max_tokens'  => $max_tokens,
				'temperature' => 0.7,
			) ),
		) );

		return self::parse_openai_like_response( $response, 'OpenAI', $model );
	}

	/**
	 * Google Gemini generator.
	 */
	private static function generate_gemini( string $prompt, string $api_key, string $model, int $max_tokens ): array {
		$model = ! empty( $model ) ? $model : 'gemini-1.5-flash';
		$url   = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

		$response = wp_remote_post( $url, array(
			'timeout' => 45,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'contents' => array(
					array(
						'role'  => 'user',
						'parts' => array(
							array( 'text' => $prompt ),
						),
					),
				),
				'generationConfig' => array(
					'maxOutputTokens' => $max_tokens,
					'temperature'     => 0.7,
				),
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'content' => $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['error']['message'] ) ) {
			return array( 'success' => false, 'content' => $body['error']['message'] );
		}

		$text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
		if ( empty( $text ) ) {
			return array( 'success' => false, 'content' => __( 'Empty response from Gemini API.', 'chatbot' ) );
		}

		return array(
			'success'  => true,
			'content'  => $text,
			'provider' => 'Google Gemini',
			'model'    => $model,
		);
	}

	/**
	 * Anthropic Claude generator.
	 */
	private static function generate_claude( string $prompt, string $api_key, string $model, int $max_tokens ): array {
		$model = ! empty( $model ) ? $model : 'claude-3-5-sonnet-20241022';
		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'timeout' => 45,
			'headers' => array(
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'      => $model,
				'max_tokens' => $max_tokens,
				'messages'   => array(
					array( 'role' => 'user', 'content' => $prompt ),
				),
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'content' => $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['error']['message'] ) ) {
			return array( 'success' => false, 'content' => $body['error']['message'] );
		}

		$text = '';
		if ( ! empty( $body['content'] ) && is_array( $body['content'] ) ) {
			foreach ( $body['content'] as $block ) {
				if ( ( $block['type'] ?? '' ) === 'text' ) {
					$text .= $block['text'];
				}
			}
		}

		if ( empty( $text ) ) {
			return array( 'success' => false, 'content' => __( 'Empty response from Claude API.', 'chatbot' ) );
		}

		return array(
			'success'  => true,
			'content'  => $text,
			'provider' => 'Anthropic Claude',
			'model'    => $model,
		);
	}

	/**
	 * OpenRouter generator.
	 */
	private static function generate_openrouter( string $prompt, string $api_key, string $model, int $max_tokens ): array {
		$model = ! empty( $model ) ? $model : 'openai/gpt-4o-mini';
		$response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', array(
			'timeout' => 45,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
				'HTTP-Referer'  => home_url(),
				'X-Title'       => get_bloginfo( 'name' ),
			),
			'body'    => wp_json_encode( array(
				'model'       => $model,
				'messages'    => array(
					array( 'role' => 'user', 'content' => $prompt ),
				),
				'max_tokens'  => $max_tokens,
				'temperature' => 0.7,
			) ),
		) );

		return self::parse_openai_like_response( $response, 'OpenRouter', $model );
	}

	/**
	 * xAI Grok generator.
	 */
	private static function generate_grok( string $prompt, string $api_key, string $model, int $max_tokens ): array {
		$model = ! empty( $model ) ? $model : 'grok-2';
		$response = wp_remote_post( 'https://api.x.ai/v1/chat/completions', array(
			'timeout' => 45,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'       => $model,
				'messages'    => array(
					array( 'role' => 'user', 'content' => $prompt ),
				),
				'max_tokens'  => $max_tokens,
				'temperature' => 0.7,
			) ),
		) );

		return self::parse_openai_like_response( $response, 'xAI Grok', $model );
	}

	/**
	 * Helper for OpenAI-compatible chat completion JSON responses.
	 */
	private static function parse_openai_like_response( $response, string $provider_name, string $model ): array {
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'content' => $response->get_error_message(),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['error']['message'] ) ) {
			return array(
				'success' => false,
				'content' => $body['error']['message'],
			);
		}

		$content = $body['choices'][0]['message']['content'] ?? '';
		if ( empty( $content ) ) {
			return array(
				'success' => false,
				'content' => sprintf(
					/* translators: %s: provider name */
					__( 'Empty response from %s service.', 'chatbot' ),
					$provider_name
				),
			);
		}

		return array(
			'success'  => true,
			'content'  => $content,
			'provider' => $provider_name,
			'model'    => $model,
		);
	}
}
