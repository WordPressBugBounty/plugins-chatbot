<?php
/**
 * MailboxLayer Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MailboxLayer_Actions
 */
class MailboxLayer_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'mailboxlayer';
		$this->group = __( 'MailboxLayer', 'wpbot-automator' );
	}

	/**
	 * Execute the action
	 *
	 * @param array $action_data Configuration for this action from the workflow.
	 * @param array $trigger_data Data passed from the trigger.
	 * @return bool|array
	 */
	public function execute( $action_data, $trigger_data ) {
		$action_id = isset( $action_data['actionId'] ) ? $action_data['actionId'] : '';

		if ( empty( $action_id ) ) {
			return false;
		}

		switch ( $action_id ) {
			case 'validate_email':
				return $this->validate_email( $action_data, $trigger_data );
			case 'test_connection':
				return $this->test_connection( $action_data, $trigger_data );
			default:
				return false;
		}
	}

	/**
	 * Validate Email Address via MailboxLayer
	 */
	private function validate_email( $action_data, $trigger_data ) {
		$cfg   = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$token = trim( $this->parse_tokens( $cfg['api_key'] ?? '', $trigger_data ) );
		$email = trim( $this->parse_tokens( $cfg['email'] ?? '', $trigger_data ) );

		// SMART FALLBACK: If token replacement didn't happen (still contains {{...}}) or is empty,
		// try to find an email field in the trigger data.
		if ( empty( $email ) || strpos( $email, '{{' ) !== false ) {
			$found_email = $this->find_email_in_data( $trigger_data );
			if ( $found_email ) {
				//error_log( sprintf( 'MailboxLayer Action - Smart Fallback: Found email "%s" in trigger data.', $found_email ) );
				$email = $found_email;
			}
		}

		if ( empty( $token ) || empty( $email ) || strpos( $email, '{{' ) !== false ) {
			//error_log( sprintf( 'MailboxLayer Action - FAILED: API Key or Email is empty/unresolved. Key length: %d, Email: "%s"', strlen( $token ), $email ) );
			return array(
				'success' => false,
				'message' => 'API Key and Email are required. Could not resolve email from tokens.',
			);
		}

		// Use HTTP instead of HTTPS as some free plans only support HTTP and it's what the user confirmed works.
		$endpoint = sprintf( 'http://apilayer.net/api/check?access_key=%s&email=%s&smtp=1&format=1', urlencode( $token ), urlencode( $email ) );
		//error_log( 'MailboxLayer Action - Requesting: ' . $endpoint );
		if ( PHP_SAPI === 'cli' ) { echo "MailboxLayer Action - Requesting: $endpoint\n"; }

		$response = wp_remote_get( $endpoint, array(
			'timeout' => 20,
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );
		//error_log( sprintf( 'MailboxLayer Action - HTTP Code: %d, Response Body: %s', $code, wp_json_encode( $body ) ) );
		if ( PHP_SAPI === 'cli' ) { echo sprintf( "MailboxLayer Action - HTTP Code: %d, Response Body: %s\n", $code, wp_json_encode( $body ) ); }

		if ( $code >= 200 && $code < 300 && isset( $body['format_valid'] ) ) {
			$is_valid   = ! empty( $body['format_valid'] ) && ! empty( $body['smtp_check'] );
			$valid_text = $is_valid ? 'Valid Email' : 'Invalid Email';
			$log_msg    = sprintf( 'Email validation completed: %s (%s).', $email, $valid_text );

			return array(
				'success'                    => $is_valid,
				'message'                    => $log_msg,
				'mailboxlayer_format_valid'  => $body['format_valid'],
				'mailboxlayer_mx_found'      => isset( $body['mx_found'] ) ? $body['mx_found'] : false,
				'mailboxlayer_smtp_check'    => isset( $body['smtp_check'] ) ? $body['smtp_check'] : false,
				'mailboxlayer_score'         => isset( $body['score'] ) ? $body['score'] : 0,
				'mailboxlayer_raw'           => wp_json_encode( $body )
			);
		}

		$error_msg = isset( $body['error']['info'] ) ? $body['error']['info'] : 'Unknown error from MailboxLayer API';
		return array(
			'success' => false,
			'message' => 'MailboxLayer API Error: ' . $error_msg,
		);
	}

	/**
	 * Test Connection helper to verify API Key
	 */
	private function test_connection( $action_data, $trigger_data ) {
		// A test connection typically just attempts to validate a known valid email.
		// Testing "support@apilayer.com" gives a reliable answer.
		$cfg = isset( $action_data['config'] ) ? $action_data['config'] : array();
		$cfg['email'] = 'support@apilayer.com'; 
		$action_data['config'] = $cfg;

		$result = $this->validate_email( $action_data, $trigger_data );
		
		if ( $result['success'] ) {
			return array(
				'success' => true,
				'message' => 'Connection successful! API key is valid.',
			);
		}

		return $result;
	}

	/**
	 * Recursively search for a valid email in trigger data.
	 *
	 * @param array $data Data to search.
	 * @return string|false
	 */
	private function find_email_in_data( $data ) {
		if ( ! is_array( $data ) ) {
			return false;
		}

		foreach ( $data as $key => $value ) {
			if ( is_string( $value ) && is_email( $value ) ) {
				return $value;
			}
			if ( is_array( $value ) ) {
				$found = $this->find_email_in_data( $value );
				if ( $found ) {
					return $found;
				}
			}
		}

		return false;
	}
}
