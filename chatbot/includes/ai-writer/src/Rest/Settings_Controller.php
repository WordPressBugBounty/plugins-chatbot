<?php
/**
 * REST routes for AI Writer settings.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Rest;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Settings;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * /wpbot-writer/v1/settings
 */
final class Settings_Controller {

	const NAMESPACE_V1 = 'wpbot-writer/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'settings' => array(
							'type'              => 'object',
							'required'          => true,
							'validate_callback' => static function ( $value ) {
								return is_array( $value );
							},
						),
					),
				),
			)
		);
	}

	/**
	 * View level.
	 *
	 * @return bool
	 */
	public function can_view() {
		return current_user_can( Module::cap( 'view' ) );
	}

	/**
	 * Settings level.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( Module::cap( 'settings' ) );
	}

	/**
	 * GET settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response( Settings::all() );
	}

	/**
	 * POST settings (sanitised field by field in Settings::update()).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ) {
		return rest_ensure_response( Settings::update( (array) $request->get_param( 'settings' ) ) );
	}
}
