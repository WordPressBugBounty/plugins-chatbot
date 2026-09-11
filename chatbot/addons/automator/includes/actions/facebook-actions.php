<?php
/**
 * Facebook Graph API Actions
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Actions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Facebook_Actions
 */
class Facebook_Actions extends Action {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'facebook';
		$this->group = __( 'Facebook', 'wpbot-automator' );
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
			case 'fb_create_post':
				return $this->create_page_post( $action_data, $trigger_data );
			default:
				return false;
		}
	}

	/**
	 * Publish a post to a Facebook Page via Graph API.
	 */
	private function create_page_post( $action_data, $trigger_data ) {
		$cfg = isset( $action_data['config'] ) ? $action_data['config'] : array();
		
		$cred_id     = isset( $cfg['credential'] ) ? $cfg['credential'] : '';
		$credentials = get_option( 'wpbot_automator_credentials', array() );
		$cred_data   = isset( $credentials[ $cred_id ]['data'] ) ? $credentials[ $cred_id ]['data'] : array();

		$page_access_token = trim( $this->parse_tokens( isset( $cred_data['page_access_token'] ) ? $cred_data['page_access_token'] : '', $trigger_data ) );
		$page_id           = trim( $this->parse_tokens( isset( $cfg['page_id'] ) ? $cfg['page_id'] : '', $trigger_data ) );
		$message           = $this->parse_tokens( isset( $cfg['message'] ) ? $cfg['message'] : '', $trigger_data );
		$link              = trim( $this->parse_tokens( isset( $cfg['link'] ) ? $cfg['link'] : '', $trigger_data ) );

		if ( empty( $page_access_token ) || empty( $page_id ) || empty( $message ) ) {
			return array(
				'success' => false,
				'message' => 'Facebook Create Post: page_access_token (from credential), page_id, and message are required.',
			);
		}

		$url  = "https://graph.facebook.com/v25.0/{$page_id}/feed";
		
		$body = array(
			'message' => $message,
		);

		if ( ! empty( $link ) ) {
			$body['link'] = $link;
		}

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array( 
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $page_access_token,
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 45,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$code          = wp_remote_retrieve_response_code( $response );
		$body_response = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && isset( $body_response['id'] ) ) {
			return array(
				'success' => true,
				'message' => "Facebook Post published successfully. Post ID: {$body_response['id']}",
				'post_id' => $body_response['id'],
			);
		}

		$error       = isset( $body_response['error']['message'] ) ? $body_response['error']['message'] : 'Unknown API error.';
		return array( 'success' => false, 'message' => "Facebook Create Post failed (HTTP {$code}): {$error}" );
	}
}
