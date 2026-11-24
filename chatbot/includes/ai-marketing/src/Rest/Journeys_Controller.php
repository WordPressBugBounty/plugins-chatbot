<?php
/**
 * REST: journeys. Reading needs wpbot_marketing_view; changing needs
 * wpbot_marketing_use; switching a journey on needs wpbot_marketing_publish.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Rest;

use QuantumCloud\AiMarketing\Journeys\Engine;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Journey routes.
 */
final class Journeys_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$view   = Rest_Controller::can( 'wpbot_marketing_view' );
		$use    = Rest_Controller::can( 'wpbot_marketing_use' );
		$id     = '(?P<id>\d+)';
		$routes = array(
			array( '/journeys', WP_REST_Server::READABLE, 'index', $view ),
			array( '/journeys', WP_REST_Server::CREATABLE, 'create', $use ),
			array( '/journeys/' . $id, WP_REST_Server::READABLE, 'show', $view ),
			array( '/journeys/' . $id, 'PATCH', 'update', $use ),
			array( '/journeys/' . $id, WP_REST_Server::DELETABLE, 'delete', $use ),
		);
		foreach ( $routes as $route ) {
			register_rest_route(
				Rest_Controller::NS,
				$route[0],
				array(
					'methods'             => $route[1],
					'callback'            => array( __CLASS__, $route[2] ),
					'permission_callback' => $route[3],
				)
			);
		}
	}

	/**
	 * GET /journeys.
	 *
	 * @return array
	 */
	public static function index() {
		return Engine::all();
	}

	/**
	 * GET /journeys/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function show( WP_REST_Request $request ) {
		$journey = Engine::get( (int) $request['id'] );
		return $journey ? $journey : new WP_Error( 'qcld_mk_not_found', __( 'Journey not found.', 'chatbot' ), array( 'status' => 404 ) );
	}

	/**
	 * POST /journeys.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create( WP_REST_Request $request ) {
		$body = Audience_Controller::body( $request );
		unset( $body['status'] );
		$saved = Engine::save( 0, $body );
		return is_wp_error( $saved ) ? $saved : new WP_REST_Response( $saved, 201 );
	}

	/**
	 * PATCH /journeys/{id}. Switching it on needs the publish capability.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function update( WP_REST_Request $request ) {
		$body = Audience_Controller::body( $request );
		if ( isset( $body['status'] ) && 'active' === $body['status'] && ! current_user_can( 'wpbot_marketing_publish' ) ) {
			return new WP_Error( 'qcld_mk_cap', __( 'You may not switch journeys on.', 'chatbot' ), array( 'status' => 403 ) );
		}
		return Engine::save( (int) $request['id'], $body );
	}

	/**
	 * DELETE /journeys/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function delete( WP_REST_Request $request ) {
		return array( 'deleted' => Engine::delete( (int) $request['id'] ) );
	}
}
