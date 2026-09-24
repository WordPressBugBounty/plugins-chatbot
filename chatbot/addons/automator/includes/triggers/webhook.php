<?php
/**
 * Webhook Trigger
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webhook
 */
class Webhook extends Trigger {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id    = 'webhook';
		$this->group = __( 'Universal', 'wpbot-automator' );
		$this->title = __( 'Webhook Trigger', 'wpbot-automator' );
	}

	/**
	 * Register the triggers
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_webhook_route' ) );
	}

	/**
	 * Register REST API route for incoming webhooks
	 */
	public function register_webhook_route() {
		register_rest_route(
			'wpbot-automator/v1',
			'/webhook/(?P<token>[a-zA-Z0-9]+)',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true', // Webhooks are usually public or handled by token
			)
		);
	}

	/**
	 * Handle incoming webhook
	 */
	public function handle_webhook( $request ) {
		$token = $request->get_param( 'token' );
		$data  = $request->get_params();

		$this->run( array(
			'sub_id' => 'incoming_webhook',
			'data'   => array_merge( $data, array(
				'target_node_id' => 'node-' . $token,
			) ),
		) );

		return rest_ensure_response( array( 
			'success' => true,
			'message' => 'Webhook received and workflow triggered.',
		) );
	}
}
