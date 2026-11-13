<?php
/**
 * Error log REST routes.
 *
 * - POST /errors/js: browser errors of WPBot's scripts (public; see Diagnostics\Js_Reporter).
 * - GET /errors: the error log (administrators).
 * - DELETE /errors: clear the error log (administrators).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Rest;

use QuantumCloud\WPBotCore\Diagnostics\Error_Log;
use QuantumCloud\WPBotCore\Diagnostics\Js_Reporter;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Error log routes.
 */
final class Diagnostics_Controller extends Rest_Controller {

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/errors/js',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( Js_Reporter::class, 'receive' ),
				'permission_callback' => '__return_true', // Visitors report widget errors; Js_Reporter validates and rate-limits.
				'args'                => array(
					'message' => array(
						'type'     => 'string',
						'required' => true,
					),
					'source'  => array( 'type' => 'string' ),
					'stack'   => array( 'type' => 'string' ),
					'line'    => array( 'type' => 'integer' ),
					'col'     => array( 'type' => 'integer' ),
					'page'    => array( 'type' => 'string' ),
					'where'   => array(
						'type' => 'string',
						'enum' => array( 'widget', 'admin' ),
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/errors',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'admin' ),
					'args'                => array(
						'source'   => array(
							'type' => 'string',
							'enum' => array_merge( array( '' ), Error_Log::SOURCES ),
						),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 200,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_items' ),
					'permission_callback' => array( $this, 'admin' ),
				),
			)
		);
	}

	/**
	 * Administrators only.
	 *
	 * @return bool
	 */
	public function admin() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /errors.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ) {
		return new WP_REST_Response(
			Error_Log::items(
				array(
					'source'   => (string) $request->get_param( 'source' ),
					'page'     => (int) $request->get_param( 'page' ),
					'per_page' => (int) $request->get_param( 'per_page' ),
				)
			)
		);
	}

	/**
	 * DELETE /errors.
	 *
	 * @return WP_REST_Response
	 */
	public function delete_items() {
		Error_Log::clear();
		return new WP_REST_Response( array( 'cleared' => true ) );
	}
}
