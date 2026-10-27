<?php
/**
 * Claude Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

use WPbot_Automator\Core\Claude_Client;

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

		return Claude_Client::test_key( $api_key );
	}

	/**
	 * Chat Completion
	 */
	private function chat_completion( $config, $trigger_data ) {
		$config_values = isset( $config['config'] ) ? $config['config'] : array();

		$api_key     = isset( $config_values['api_key'] ) ? $this->parse_tokens( $config_values['api_key'], $trigger_data ) : '';
		$model       = isset( $config_values['model'] ) ? $config_values['model'] : Claude_Client::DEFAULT_MODEL;
		$prompt      = isset( $config_values['prompt'] ) ? $this->parse_tokens( $config_values['prompt'], $trigger_data ) : '';
		$max_tokens  = isset( $config_values['max_tokens'] ) ? intval( $config_values['max_tokens'] ) : 4096;
		$temperature = isset( $config_values['temperature'] ) ? $config_values['temperature'] : '';

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

		$result = Claude_Client::messages(
			$api_key,
			$model,
			array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			array(
				'max_tokens'  => $max_tokens,
				'temperature' => $temperature,
			)
		);

		if ( $result['success'] ) {
			$result['response'] = $result['content'];
		}

		return $result;
	}
}
