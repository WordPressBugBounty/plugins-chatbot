<?php
/**
 * Mistral Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mistral_Actions
 */
class Mistral_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'mistral';
		$this->group = __( 'Mistral AI', 'wpbot-automator' );
	}

	/**
	 * Execute the action
	 */
	public function execute( $action_data, $trigger_data ) {
		$action_id = isset( $action_data['actionId'] ) ? $action_data['actionId'] : '';

		if ( empty( $action_id ) ) {
			return false;
		}

		switch ( $action_id ) {
			case 'chat_completion':
				return $this->chat_completion( $action_data, $trigger_data );
			case 'test_connection':
				return $this->test_connection( $action_data, $trigger_data );
			default:
				return false;
		}
	}

	/**
	 * Test Connection
	 */
	private function test_connection( $config, $trigger_data ) {
		$config_values = isset( $config['config'] ) ? $config['config'] : array();
		$api_key       = isset( $config_values['api_key'] ) ? $this->parse_tokens( $config_values['api_key'], $trigger_data ) : '';

		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => 'API Key is required.',
			);
		}

		$url = 'https://api.mistral.ai/v1/models';

		$args = array(
			'method'  => 'GET',
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
			),
			'timeout' => 30,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code >= 200 && $response_code < 300 ) {
			return array(
				'success' => true,
				'message' => 'Connection successful! Your API key is valid.',
			);
		} else {
			$response_body = wp_remote_retrieve_body( $response );
			$data          = json_decode( $response_body, true );
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Connection failed. Please check your API key.';
			return array(
				'success' => false,
				'message' => $error_message,
				'code'    => $response_code,
			);
		}
	}

	/**
	 * Chat Completion
	 */
	private function chat_completion( $config, $trigger_data ) {
		$config_values = isset( $config['config'] ) ? $config['config'] : array();
		
		$api_key     = isset( $config_values['api_key'] ) ? $this->parse_tokens( $config_values['api_key'], $trigger_data ) : '';
		$model       = isset( $config_values['model'] ) ? $config_values['model'] : 'mistral-tiny';
		$prompt      = isset( $config_values['prompt'] ) ? $this->parse_tokens( $config_values['prompt'], $trigger_data ) : '';
		$max_tokens  = isset( $config_values['max_tokens'] ) ? intval( $config_values['max_tokens'] ) : 1000;
		$temperature = isset( $config_values['temperature'] ) ? floatval( $config_values['temperature'] ) : 0.7;

		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => 'API Key is required.',
			);
		}

		if ( empty( $prompt ) ) {
			return array(
				'success' => false,
				'message' => 'Prompt is required.',
			);
		}

		$url = 'https://api.mistral.ai/v1/chat/completions';

		$body = array(
			'model'       => $model,
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'max_tokens'  => $max_tokens,
			'temperature' => $temperature,
		);

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 60,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( $response_code >= 200 && $response_code < 300 ) {
			$content = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';
			return array(
				'success'  => true,
				'content'  => $content,
				'response' => $content,
				'data'     => $data,
			);
		} else {
			$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error';
			return array(
				'success' => false,
				'message' => $error_message,
				'code'    => $response_code,
			);
		}
	}
}
