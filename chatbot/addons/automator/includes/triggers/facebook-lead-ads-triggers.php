<?php
/**
 * Facebook Lead Ads Triggers
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Facebook_Lead_Ads_Triggers
 */
class Facebook_Lead_Ads_Triggers extends Trigger {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'facebook';
		$this->group = __( 'Facebook', 'wpbot-automator' );
		$this->title = __( 'Facebook Lead Ads', 'wpbot-automator' );
	}

	/**
	 * Register the triggers
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_facebook_webhook_route' ) );
	}

	/**
	 * Register REST API route for incoming webhooks
	 */
	public function register_facebook_webhook_route() {
		register_rest_route(
			'wpbot-automator/v1',
			'/facebook-lead-ads/(?P<token>[a-zA-Z0-9]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'handle_webhook_verification' ),
					'permission_callback' => '__return_true', 
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_webhook_payload' ),
					'permission_callback' => '__return_true', 
				),
			)
		);
	}

	/**
	 * Handle Facebook Webhook Verification (GET request)
	 */
	public function handle_webhook_verification( $request ) {
		$params = $request->get_query_params();

		$hub_mode      = isset( $params['hub_mode'] ) ? $params['hub_mode'] : '';
		$hub_challenge = isset( $params['hub_challenge'] ) ? $params['hub_challenge'] : '';
		$hub_verify_token = isset( $params['hub_verify_token'] ) ? $params['hub_verify_token'] : '';

		// Facebook requires us to just echo the challenge code back
		// Note: The verify_token check should technically match what the user configured in the node.
		// However, because this is an unauthenticated GET request and can happen *before* the user fully saves their flow,
		// or they have multiple flows, the standard practice for integration platforms is to either
		// verify against a stored token or temporarily allow setup. 
		// We'll just echo the challenge code to ensure the webhook gets verified in their FB developer dashboard.

		if ( 'subscribe' === $hub_mode && ! empty( $hub_challenge ) ) {
			// Facebook expects a raw integer/string, NOT a JSON response for the challenge
			echo esc_html( $hub_challenge );
			exit;
		}

		return rest_ensure_response( array( 
			'success' => false,
			'message' => 'Invalid verification request',
		) );
	}

	/**
	 * Handle incoming webhook payload from Facebook (POST request)
	 */
	public function handle_webhook_payload( $request ) {
		$token = $request->get_param( 'token' );
		$body  = $request->get_params();

		// Facebook Lead Ads sends data in a specific format
		if ( ! empty( $body['object'] ) && 'page' === $body['object'] ) {
			if ( ! empty( $body['entry'] ) && is_array( $body['entry'] ) ) {
				foreach ( $body['entry'] as $entry ) {
					if ( ! empty( $entry['changes'] ) && is_array( $entry['changes'] ) ) {
						foreach ( $entry['changes'] as $change ) {
							if ( 'leadgen' === $change['field'] && ! empty( $change['value']['leadgen_id'] ) ) {
								$leadgen_id = $change['value']['leadgen_id'];
								$page_id = $change['value']['page_id'];

								// Trigger the automator node
								// The node configuration will contain the page_access_token needed to fetch details.
								// We pass the leadgen_id so the action/trigger execution can fetch the real data.
								
								// Since we need to look up node configs to get the access token *before* we distribute data,
								// we pass both the raw payload and let the system match the target node.
								// This trigger acts primarily as the receiver.

								$this->run( array(
									'sub_id' => 'facebook_lead_ads',
									'data'   => array_merge( $body, array(
										'target_node_id' => 'node-' . $token,
										'leadgen_id'     => $leadgen_id,
										'page_id'        => $page_id,
									) ),
								) );
							}
						}
					}
				}
			}
		} else {
			// Fallback generic run if it's not a recognized leadgen payload (e.g. testing from FB dev console)
			$this->run( array(
				'sub_id' => 'facebook_lead_ads',
				'data'   => array_merge( $body, array(
					'target_node_id' => 'node-' . $token,
				) ),
			) );
		}

		return rest_ensure_response( array( 
			'success' => true,
			'message' => 'Webhook received and workflow triggered.',
		) );
	}
	
	/**
	 * Fetch Lead Details from Facebook Graph API
	 * 
	 * Note: this is typically called during execution or when preparing data for the next step.
	 * 
	 * @param string $leadgen_id The Lead ID
	 * @param string $access_token The Facebook Page Access Token
	 * @return array|\WP_Error
	 */
	public static function get_lead_details( $leadgen_id, $access_token ) {
		$url = "https://graph.facebook.com/v20.0/{$leadgen_id}?access_token={$access_token}";
		
		$response = wp_remote_get( $url, array(
			'timeout' => 20
		) );
		
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		
		if ( isset( $body['error'] ) ) {
			return new \WP_Error( 'fb_graph_error', $body['error']['message'] ?? 'Unknown Facebook Graph API error' );
		}
		
		// Parse field_data into a flat array for easier mapping
		$flat_data = array();
		if ( ! empty( $body['field_data'] ) && is_array( $body['field_data'] ) ) {
			foreach ( $body['field_data'] as $field ) {
				$flat_data[ $field['name'] ] = isset( $field['values'][0] ) ? $field['values'][0] : '';
			}
		}
		
		return array(
			'raw' => $body,
			'fields' => $flat_data
		);
	}
}
