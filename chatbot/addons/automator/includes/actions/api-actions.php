<?php
/**
 * API Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class API_Actions
 */
class API_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'api';
		$this->group = __( 'API', 'wpbot-automator' );
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
			case 'api_request':
				return $this->api_request( $action_data, $trigger_data );
			default:
				return false;
		}
	}

	/**
	 * Make an API request
	 */
	private function api_request( $config, $trigger_data ) {
		$config_values = isset( $config['config'] ) ? $config['config'] : array();
		
		$url    = isset( $config_values['url'] ) ? $this->parse_tokens( $config_values['url'], $trigger_data ) : '';
		$method = isset( $config_values['method'] ) ? $config_values['method'] : 'GET';
		$body   = isset( $config_values['body'] ) ? $this->parse_tokens( $config_values['body'], $trigger_data ) : '';
		
		if ( empty( $url ) ) {
			return array(
				'success' => false,
				'message' => 'API URL is required.',
			);
		}

		// Prepare headers
		$headers = array();
		if ( ! empty( $config_values['headers'] ) ) {
			$headers_data = $config_values['headers'];
			if ( is_string( $headers_data ) ) {
				$headers_data = json_decode( $headers_data, true );
			}
			if ( is_array( $headers_data ) ) {
				foreach ( $headers_data as $pair ) {
					if ( ! empty( $pair['key'] ) ) {
						$headers[ $pair['key'] ] = $this->parse_tokens( $pair['value'], $trigger_data );
					}
				}
			}
		}

		// Prepare Query Params
		if ( ! empty( $config_values['params'] ) ) {
			$params_data = $config_values['params'];
			if ( is_string( $params_data ) ) {
				$params_data = json_decode( $params_data, true );
			}
			if ( is_array( $params_data ) ) {
				$query_args = array();
				foreach ( $params_data as $pair ) {
					if ( ! empty( $pair['key'] ) ) {
						$query_args[ $pair['key'] ] = $this->parse_tokens( $pair['value'], $trigger_data );
					}
				}
				if ( ! empty( $query_args ) ) {
					$url = add_query_arg( $query_args, $url );
				}
			}
		}

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'body'    => $body,
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
		$response_body = wp_remote_retrieve_body( $response );

		return array(
			'success' => $response_code >= 200 && $response_code < 300,
			'code'    => $response_code,
			'body'    => $response_body,
			'message' => sprintf( 'Response Code: %d', $response_code ),
		);
	}
}
