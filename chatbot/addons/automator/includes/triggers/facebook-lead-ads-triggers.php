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
			'/facebook-lead-ads/(?P<token>[a-zA-Z0-9\-_]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'handle_webhook_verification' ),
					'permission_callback' => '__return_true', // Verify token checked in callback.
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_webhook_payload' ),
					'permission_callback' => '__return_true', // X-Hub-Signature-256 checked in callback.
				),
			)
		);
	}

	/**
	 * Config of the active Facebook Lead Ads trigger node whose ID matches the URL token.
	 *
	 * @param string $token URL token (node ID without "node-").
	 * @return array|null
	 */
	private function get_node_config( $token ) {
		$node_id = 'node-' . $token;
		if ( ! \WPbot_Automator\Core\Webhook_Secrets::is_strong( $node_id ) ) {
			return null;
		}

		global $wpdb;
		$table = \WPbot_Automator\Core\Database::get_workflows_table();
		$rows  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT workflow_data FROM {$table} WHERE status = 'active' AND workflow_data LIKE %s",
				'%' . $wpdb->esc_like( '"' . $node_id . '"' ) . '%'
			)
		);

		foreach ( (array) $rows as $json ) {
			$data = json_decode( $json, true );
			foreach ( isset( $data['nodes'] ) ? (array) $data['nodes'] : array() as $node ) {
				if ( isset( $node['id'], $node['data']['actionId'] ) && hash_equals( $node['id'], $node_id ) && 'facebook_lead_ads' === $node['data']['actionId'] ) {
					return isset( $node['data']['config'] ) && is_array( $node['data']['config'] ) ? $node['data']['config'] : array();
				}
			}
		}
		return null;
	}

	/**
	 * Handle Facebook Webhook Verification (GET request)
	 */
	public function handle_webhook_verification( $request ) {
		$params = $request->get_query_params();

		$hub_mode         = isset( $params['hub_mode'] ) ? (string) $params['hub_mode'] : '';
		$hub_challenge    = isset( $params['hub_challenge'] ) ? (string) $params['hub_challenge'] : '';
		$hub_verify_token = isset( $params['hub_verify_token'] ) ? (string) $params['hub_verify_token'] : '';

		$config   = $this->get_node_config( (string) $request->get_param( 'token' ) );
		$expected = isset( $config['hub_verify_token'] ) ? (string) $config['hub_verify_token'] : '';

		// Facebook must echo back the Verify Token configured on this node (save the workflow first).
		if ( 'subscribe' === $hub_mode && '' !== $hub_challenge && '' !== $expected && hash_equals( $expected, $hub_verify_token ) ) {
			// Facebook expects the raw challenge, not JSON.
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo esc_html( $hub_challenge );
			exit;
		}

		return new \WP_Error( 'forbidden', 'Invalid verification request.', array( 'status' => 403 ) );
	}

	/**
	 * Handle incoming webhook payload from Facebook (POST request)
	 */
	public function handle_webhook_payload( $request ) {
		$token  = (string) $request->get_param( 'token' );
		$config = $this->get_node_config( $token );
		if ( null === $config ) {
			return new \WP_Error( 'not_found', 'Unknown webhook.', array( 'status' => 404 ) );
		}

		// Only accept payloads signed by Facebook with this app's secret.
		$secret    = isset( $config['app_secret'] ) ? trim( (string) $config['app_secret'] ) : '';
		$signature = (string) $request->get_header( 'x_hub_signature_256' );
		if ( '' === $secret ) {
			return new \WP_Error( 'forbidden', 'Set the App Secret on the Facebook Lead Ads trigger to accept leads.', array( 'status' => 403 ) );
		}
		if ( ! hash_equals( 'sha256=' . hash_hmac( 'sha256', $request->get_body(), $secret ), $signature ) ) {
			return new \WP_Error( 'forbidden', 'Invalid signature.', array( 'status' => 403 ) );
		}

		$body         = json_decode( $request->get_body(), true );
		$body         = is_array( $body ) ? $body : array();
		$access_token = isset( $config['page_access_token'] ) ? trim( (string) $config['page_access_token'] ) : '';
		$leads        = 0;

		if ( 'page' === ( $body['object'] ?? '' ) && ! empty( $body['entry'] ) && is_array( $body['entry'] ) ) {
			foreach ( $body['entry'] as $entry ) {
				foreach ( ( isset( $entry['changes'] ) && is_array( $entry['changes'] ) ) ? $entry['changes'] : array() as $change ) {
					if ( 'leadgen' !== ( $change['field'] ?? '' ) || empty( $change['value']['leadgen_id'] ) ) {
						continue;
					}

					$data = array(
						'target_node_id' => 'node-' . $token,
						'leadgen_id'     => (string) $change['value']['leadgen_id'],
						'page_id'        => (string) ( $change['value']['page_id'] ?? '' ),
						'form_id'        => (string) ( $change['value']['form_id'] ?? '' ),
						'ad_id'          => (string) ( $change['value']['ad_id'] ?? '' ),
					);

					// Fetch the lead's answers (name, email, ...) so later steps can use {email} etc.
					if ( '' !== $access_token ) {
						$details = self::get_lead_details( $data['leadgen_id'], $access_token );
						if ( is_wp_error( $details ) ) {
							$data['lead_error'] = $details->get_error_message();
						} else {
							$data['fields'] = $details['fields'];
							foreach ( $details['fields'] as $name => $value ) {
								$key = trim( preg_replace( '/[^a-z0-9]+/', '_', strtolower( (string) $name ) ), '_' );
								if ( '' !== $key && ! isset( $data[ $key ] ) ) {
									$data[ $key ] = $value;
								}
							}
						}
					} else {
						$data['lead_error'] = 'No Page Access Token set on the trigger; lead details were not fetched.';
					}

					$this->run( array(
						'sub_id' => 'facebook_lead_ads',
						'data'   => $data,
					) );
					++$leads;
				}
			}
		}

		return rest_ensure_response( array(
			'success' => true,
			'leads'   => $leads,
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
		$url = 'https://graph.facebook.com/v20.0/' . rawurlencode( $leadgen_id );

		$response = wp_remote_get( $url, array(
			'timeout' => 20,
			'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
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
