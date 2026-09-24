<?php
/**
 * Telegram Bot API Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Telegram_Actions
 */
class Telegram_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'telegram';
		$this->group = __( 'Telegram', 'wpbot-automator' );
	}

	/**
	 * Execute the action.
	 *
	 * @param array $action_data Configuration for this action.
	 * @param array $trigger_data Data passed from the trigger.
	 * @return array
	 */
	public function execute( $action_data, $trigger_data ) {
		$action_id = isset( $action_data['actionId'] ) ? $action_data['actionId'] : '';

		if ( empty( $action_id ) ) {
			return false;
		}

		switch ( $action_id ) {
			case 'tg_send_message':
				return $this->send_message( $action_data, $trigger_data );
			default:
				return false;
		}
	}

	/**
	 * Send a message via Telegram Bot API.
	 */
	private function send_message( $action_data, $trigger_data ) {
		$cfg = isset( $action_data['config'] ) ? $action_data['config'] : array();
		
		$cred_id     = isset( $cfg['credential'] ) ? $cfg['credential'] : '';
		$credentials = get_option( 'wpbot_automator_credentials', array() );
		$cred_data   = isset( $credentials[ $cred_id ]['data'] ) ? $credentials[ $cred_id ]['data'] : array();

		$bot_token = trim( $this->parse_tokens( isset( $cred_data['bot_token'] ) ? $cred_data['bot_token'] : '', $trigger_data ) );
		$chat_id   = trim( $this->parse_tokens( isset( $cfg['chat_id'] ) ? $cfg['chat_id'] : '', $trigger_data ) );
		$text      = $this->parse_tokens( isset( $cfg['text'] ) ? $cfg['text'] : '', $trigger_data );

		if ( empty( $bot_token ) || empty( $chat_id ) || empty( $text ) ) {
			return array(
				'success' => false,
				'message' => 'Telegram Send Message: bot_token (from credential), chat_id, and text are required.',
			);
		}

		$url  = "https://api.telegram.org/bot{$bot_token}/sendMessage";
		$body = array(
			'chat_id' => $chat_id,
			'text'    => $text,
			'parse_mode' => 'HTML',
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body_response = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && isset($body_response['ok']) && $body_response['ok'] === true ) {
			$message_id = isset( $body_response['result']['message_id'] ) ? $body_response['result']['message_id'] : 'N/A';
			return array(
				'success'    => true,
				'message'    => "Telegram Message sent successfully. Message ID: {$message_id}",
				'message_id' => $message_id,
			);
		}

		$error = isset( $body_response['description'] ) ? $body_response['description'] : 'Unknown API error.';
		return array( 'success' => false, 'message' => "Telegram Send Message failed (HTTP {$code}): {$error}" );
	}
}
