<?php
/**
 * REST: capture sources (the ChatBot Subscribers list first).
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Rest;

use QuantumCloud\AiMarketing\Capture\Chatbot;
use QuantumCloud\AiMarketing\Capture\WPForms;
use QuantumCloud\AiMarketing\Settings;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Capture routes.
 */
final class Capture_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$view     = Rest_Controller::can( 'wpbot_marketing_view' );
		$settings = Rest_Controller::can( 'wpbot_marketing_settings' );
		register_rest_route(
			Rest_Controller::NS,
			'/capture',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'overview' ),
					'permission_callback' => $view,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save' ),
					'permission_callback' => $settings,
				),
			)
		);
		register_rest_route(
			Rest_Controller::NS,
			'/capture/backfill',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'backfill' ),
				'permission_callback' => $settings,
			)
		);
	}

	/**
	 * GET /capture.
	 *
	 * @return array
	 */
	public static function overview() {
		return array(
			'chatbot' => Chatbot::overview(),
			'wpforms' => array(
				'active'  => WPForms::active(),
				'enabled' => (bool) Settings::get( 'capture_wpforms' ),
			),
		);
	}

	/**
	 * POST /capture: chatbot (bool), wpforms (bool).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function save( WP_REST_Request $request ) {
		$body = Audience_Controller::body( $request );
		if ( array_key_exists( 'chatbot', $body ) ) {
			Settings::save( array( 'capture_chatbot' => $body['chatbot'] ) );
		}
		if ( array_key_exists( 'wpforms', $body ) ) {
			Settings::save( array( 'capture_wpforms' => $body['wpforms'] ) );
		}
		return self::overview();
	}

	/**
	 * POST /capture/backfill: import the chatbot's earlier subscribers.
	 *
	 * @return array
	 */
	public static function backfill() {
		Chatbot::backfill( 15 );
		return self::overview();
	}
}
