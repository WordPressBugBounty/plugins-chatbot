<?php
/**
 * REST route for the usage and cost page.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Rest;

use QuantumCloud\WPBotCore\AI\Usage;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * /wpbot-core/v1/usage/{module}
 */
final class Usage_Controller extends Rest_Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/usage/(?P<module>[a-z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_usage' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'module' => $this->module_arg(),
					'months' => array(
						'type'              => 'integer',
						'default'           => 6,
						'minimum'           => 1,
						'maximum'           => 24,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * View level of the module.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->can( (string) $request['module'], 'view' );
	}

	/**
	 * GET summary.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_usage( WP_REST_Request $request ) {
		return rest_ensure_response( Usage::summary( (string) $request['module'], (int) $request['months'] ) );
	}
}
