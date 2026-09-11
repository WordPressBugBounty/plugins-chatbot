<?php
/**
 * Claude Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Claude_Actions
 */
class Claude_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'claude';
		$this->group = __( 'Claude', 'wpbot-automator' );
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

		$url = 'https://api.anthropic.com/v1/messages'; // We can't do a simple GET, so we'll do a minimal POST

		$body = array(
			'model'      => 'claude-3-haiku-20240307',
			'max_tokens' => 1,
			'messages'   => array(
				array( 'role' => 'user', 'content' => 'Hi' )
			)
		);

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			),
			'body'    => wp_json_encode( $body ),
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
		$model       = isset( $config_values['model'] ) ? $config_values['model'] : 'claude-3-5-sonnet-20240620';
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

		$url = 'https://api.anthropic.com/v1/messages';

		$body = array(
			'model'      => $model,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'max_tokens' => $max_tokens,
			'temperature' => $temperature,
		);

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
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
			$content = isset( $data['content'][0]['text'] ) ? $data['content'][0]['text'] : '';
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
