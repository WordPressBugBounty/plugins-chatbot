<?php
/**
 * REST: the Approval inbox and the activity log (Master).
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Rest;

use QuantumCloud\AiMarketing\Autopilot\Activity;
use QuantumCloud\AiMarketing\Autopilot\Suggestions;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Autopilot routes.
 */
final class Autopilot_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$view   = Rest_Controller::can_master( 'wpbot_marketing_view' );
		$use    = Rest_Controller::can_master( 'wpbot_marketing_use' );
		$routes = array(
			array( '/suggestions', WP_REST_Server::READABLE, 'suggestions', $view ),
			array( '/suggestions/(?P<id>\d+)', WP_REST_Server::CREATABLE, 'decide', $use ),
			array( '/activity', WP_REST_Server::READABLE, 'activity', $view ),
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
	 * GET /suggestions?status=pending&kind=.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function suggestions( WP_REST_Request $request ) {
		$status = null !== $request['status'] ? sanitize_key( (string) $request['status'] ) : 'pending';
		return array(
			'items'   => Suggestions::all( $status, 200, null !== $request['kind'] ? (string) $request['kind'] : '' ),
			'pending' => Suggestions::pending_count(),
		);
	}

	/**
	 * POST /suggestions/{id} { decision: approve|dismiss }.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function decide( WP_REST_Request $request ) {
		$body = Audience_Controller::body( $request );
		return Suggestions::decide( (int) $request['id'], isset( $body['decision'] ) ? (string) $body['decision'] : '', get_current_user_id() );
	}

	/**
	 * GET /activity.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function activity( WP_REST_Request $request ) {
		return Activity::recent( (array) $request->get_query_params() );
	}
}
