<?php
/**
 * Anthropic Messages API client shared by Automator actions and the AI workflow generator.
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Claude_Client
 */
class Claude_Client {

	/** Model used when none is configured, or when the configured one has been retired. */
	const DEFAULT_MODEL = 'claude-opus-5-5';

	/** Models that reject sampling parameters (temperature/top_p/top_k). */
	const NO_SAMPLING_PATTERN = '/^claude-(fable|mythos|opus-5|sonnet-5|haiku-5|opus-4-[78])/';

	/** Models that accept the server-side refusal fallback. */
	const FALLBACK_MODELS = array( 'claude-fable-5-1', 'claude-opus-5-5', 'claude-opus-5', 'claude-sonnet-5-5' );

	/**
	 * Map retired models (Claude 3.x and older) to the current default so saved workflows keep running.
	 *
	 * @param string $model Configured model ID.
	 * @return string
	 */
	public static function resolve_model( $model ) {
		$model = trim( (string) $model );
		if ( '' === $model || preg_match( '/^claude-(3|2|instant)/', $model ) ) {
			return self::DEFAULT_MODEL;
		}
		return $model;
	}

	/**
	 * POST to /v1/messages and return the text answer.
	 *
	 * @param string $api_key  Anthropic API key.
	 * @param string $model    Model ID (retired models are remapped).
	 * @param array  $messages Messages (role/content).
	 * @param array  $options  Optional: system (string), max_tokens (int), temperature (float).
	 * @return array{success:bool,content?:string,message?:string,code?:int,data?:array}
	 */
	public static function messages( $api_key, $model, array $messages, array $options = array() ) {
		$model      = self::resolve_model( $model );
		$max_tokens = ! empty( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 4096;

		$body = array(
			'model'      => $model,
			'max_tokens' => $max_tokens,
			'messages'   => $messages,
		);
		if ( ! empty( $options['system'] ) ) {
			$body['system'] = (string) $options['system'];
		}
		if ( isset( $options['temperature'] ) && '' !== $options['temperature'] && ! preg_match( self::NO_SAMPLING_PATTERN, $model ) ) {
			$body['temperature'] = (float) $options['temperature'];
		}

		$headers = array(
			'Content-Type'      => 'application/json',
			'x-api-key'         => $api_key,
			'anthropic-version' => '2023-06-01',
		);
		if ( in_array( $model, self::FALLBACK_MODELS, true ) ) {
			// If the model declines for policy reasons, the API retries on a suitable fallback model.
			$headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
			$body['fallbacks']         = 'default';
		}

		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			$response = wp_remote_post(
				'https://api.anthropic.com/v1/messages',
				array(
					'timeout' => 120,
					'headers' => $headers,
					'body'    => wp_json_encode( $body ),
				)
			);

			if ( is_wp_error( $response ) ) {
				return array( 'success' => false, 'message' => $response->get_error_message() );
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			$data = is_array( $data ) ? $data : array();

			if ( 400 !== $code || ! self::drop_rejected_param( $body, $headers, $data ) ) {
				break;
			}
		}

		if ( $code < 200 || $code >= 300 ) {
			return array(
				'success' => false,
				'message' => isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown Claude error',
				'code'    => $code,
			);
		}

		// Current models return thinking blocks before the answer, so collect every text block.
		$text = '';
		if ( ! empty( $data['content'] ) && is_array( $data['content'] ) ) {
			foreach ( $data['content'] as $block ) {
				if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
					$text .= $block['text'];
				}
			}
		}

		$stop_reason = isset( $data['stop_reason'] ) ? $data['stop_reason'] : '';
		if ( 'refusal' === $stop_reason ) {
			$why = isset( $data['stop_details']['explanation'] ) ? ' ' . $data['stop_details']['explanation'] : '';
			return array( 'success' => false, 'message' => 'Claude declined this request.' . $why, 'data' => $data );
		}
		if ( '' === trim( $text ) && 'max_tokens' === $stop_reason ) {
			return array( 'success' => false, 'message' => 'Claude reached the Max Tokens limit before answering. Increase Max Tokens.', 'data' => $data );
		}

		return array( 'success' => true, 'content' => $text, 'data' => $data );
	}

	/**
	 * Verify an API key without generating any tokens.
	 *
	 * @param string $api_key Anthropic API key.
	 * @return array{success:bool,message:string,code?:int}
	 */
	public static function test_key( $api_key ) {
		$response = wp_remote_get(
			'https://api.anthropic.com/v1/models?limit=1',
			array(
				'timeout' => 30,
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return array( 'success' => true, 'message' => 'Connection successful! Your API key is valid.' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return array(
			'success' => false,
			'message' => isset( $data['error']['message'] ) ? $data['error']['message'] : 'Connection failed. Please check your API key.',
			'code'    => $code,
		);
	}

	/**
	 * If the error names a parameter this model or account does not accept, remove it.
	 *
	 * @return bool Whether the request changed and should be retried.
	 */
	private static function drop_rejected_param( array &$body, array &$headers, array $data ) {
		$message = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : '';

		if ( isset( $body['temperature'] ) && false !== stripos( $message, 'temperature' ) ) {
			unset( $body['temperature'] );
			return true;
		}
		if ( isset( $body['fallbacks'] ) && ( false !== stripos( $message, 'fallback' ) || false !== stripos( $message, 'anthropic-beta' ) ) ) {
			unset( $body['fallbacks'], $headers['anthropic-beta'] );
			return true;
		}
		return false;
	}
}
