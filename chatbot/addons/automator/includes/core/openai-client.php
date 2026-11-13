<?php
/**
 * OpenAI Chat Completions client shared by Automator actions and the AI workflow generator.
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OpenAI_Client
 */
class OpenAI_Client {

	/** Model used when none is configured, or when the configured one has been retired. */
	const DEFAULT_MODEL = 'gpt-5.6-terra';

	/** OpenAI's named replacement for the GPT-4 / GPT-4 Turbo family (shut down 23 Oct 2026). */
	const GPT4_REPLACEMENT = 'gpt-5.6-sol';

	/**
	 * Map retired models to the current default so saved workflows keep running.
	 *
	 * @param string $model Configured model ID.
	 * @return string
	 */
	public static function resolve_model( $model ) {
		$model = trim( (string) $model );
		if ( '' === $model || 0 === strpos( $model, 'gpt-3.5' ) ) {
			return self::DEFAULT_MODEL;
		}
		if ( preg_match( '/^gpt-4(-turbo.*|-0\d{3}.*|-completions)?$/', $model ) || 'gpt-4o-2024-05-13' === $model ) {
			return self::GPT4_REPLACEMENT;
		}
		return $model;
	}

	/**
	 * POST to /v1/chat/completions.
	 *
	 * Sends `max_completion_tokens` (newer models reject `max_tokens`). If the model
	 * refuses `temperature` or the token parameter, the request is retried without
	 * that setting instead of failing the workflow.
	 *
	 * @param string $api_key  OpenAI API key.
	 * @param string $model    Model ID (retired models are remapped).
	 * @param array  $messages Chat messages.
	 * @param array  $options  Optional: max_tokens (int), temperature (float), response_format (array),
	 *                         spend_feature (spend meter feature id, default 'action').
	 * @return array{code:int,data:array}|\WP_Error
	 */
	public static function chat( $api_key, $model, array $messages, array $options = array() ) {
		$spend_feature = ! empty( $options['spend_feature'] ) ? (string) $options['spend_feature'] : 'action';
		$body = array(
			'model'    => self::resolve_model( $model ),
			'messages' => $messages,
		);
		if ( ! empty( $options['max_tokens'] ) ) {
			$body['max_completion_tokens'] = (int) $options['max_tokens'];
		}
		if ( isset( $options['temperature'] ) && '' !== $options['temperature'] ) {
			$body['temperature'] = (float) $options['temperature'];
		}
		if ( ! empty( $options['response_format'] ) ) {
			$body['response_format'] = $options['response_format'];
		}

		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			$response = qcld_wpbot_spend_post(
				'automator',
				$spend_feature,
				'https://api.openai.com/v1/chat/completions',
				array(
					'timeout' => 60,
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
				),
				array( 'provider' => 'openai' )
			);

			if ( is_wp_error( $response ) ) { // Includes a spending-limit refusal.
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			$data = is_array( $data ) ? $data : array();

			if ( 400 !== $code || ! self::drop_rejected_param( $body, $data ) ) {
				return array( 'code' => $code, 'data' => $data );
			}
		}

		return array( 'code' => $code, 'data' => $data );
	}

	/**
	 * If the error says a sampling/token parameter is unsupported, adjust the body.
	 *
	 * @param array $body Request body (modified in place).
	 * @param array $data Decoded error response.
	 * @return bool Whether the body changed and the request should be retried.
	 */
	private static function drop_rejected_param( array &$body, array $data ) {
		$param   = isset( $data['error']['param'] ) ? (string) $data['error']['param'] : '';
		$message = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : '';

		if ( isset( $body['temperature'] ) && ( 'temperature' === $param || false !== strpos( $message, "'temperature'" ) ) ) {
			unset( $body['temperature'] );
			return true;
		}

		// Older or OpenAI-compatible models that only know the legacy parameter name.
		if ( isset( $body['max_completion_tokens'] ) && ( 'max_completion_tokens' === $param || false !== strpos( $message, "'max_completion_tokens'" ) ) ) {
			$body['max_tokens'] = $body['max_completion_tokens'];
			unset( $body['max_completion_tokens'] );
			return true;
		}

		return false;
	}
}
