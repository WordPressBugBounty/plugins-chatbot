<?php
/**
 * REST routes for a module's AI keys (masked) and key health.
 *
 * Keys are managed only by users who have the section's settings level and
 * manage_options. Keys are never returned, only their last four characters.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Rest;

use QuantumCloud\WPBotCore\AI\AI_Service;
use QuantumCloud\WPBotCore\AI\AI_Readiness;
use QuantumCloud\WPBotCore\AI\Key_Health;
use QuantumCloud\WPBotCore\AI\Keys;
use QuantumCloud\WPBotCore\AI\Providers;
use QuantumCloud\WPBotCore\AI\Request;
use QuantumCloud\WPBotCore\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * /wpbot-core/v1/keys/{module}
 */
final class Keys_Controller extends Rest_Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$module = array( 'module' => $this->module_arg() );

		register_rest_route(
			self::NAMESPACE_V1,
			'/keys/(?P<module>[a-z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_keys' ),
					'permission_callback' => array( $this, 'can_manage_keys' ),
					'args'                => $module,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_keys' ),
					'permission_callback' => array( $this, 'can_manage_keys' ),
					'args'                => array_merge(
						$module,
						array(
							'active'    => array(
								'type'              => 'string',
								'sanitize_callback' => 'sanitize_key',
								'validate_callback' => static function ( $value ) {
									return '' === $value || Providers::exists( sanitize_key( $value ) );
								},
							),
							'secondary' => array(
								'type'              => 'string',
								'sanitize_callback' => 'sanitize_key',
								'validate_callback' => static function ( $value ) {
									return '' === $value || Providers::exists( sanitize_key( $value ) );
								},
							),
							'fallback'  => array( 'type' => 'boolean' ),
							'providers' => array(
								'type'              => 'object',
								'validate_callback' => static function ( $value ) {
									return is_array( $value );
								},
							),
							'budget'    => array(
								'type'    => 'number',
								'minimum' => 0,
							),
						)
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/keys/(?P<module>[a-z0-9_-]+)/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import_keys' ),
				'permission_callback' => array( $this, 'can_manage_keys' ),
				'args'                => $module,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/keys/(?P<module>[a-z0-9_-]+)/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_key' ),
				'permission_callback' => array( $this, 'can_manage_keys' ),
				'args'                => array_merge(
					$module,
					array(
						'provider' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => static function ( $value ) {
								return Providers::exists( sanitize_key( $value ) );
							},
						),
					)
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/health/(?P<module>[a-z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_health' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => $module,
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/ai-readiness/(?P<module>[a-z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_readiness' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => $module,
			)
		);
	}

	/**
	 * GET whether AI is ready for a module, with the fix.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_readiness( WP_REST_Request $request ) {
		return rest_ensure_response( AI_Readiness::check( (string) $request['module'] ) );
	}

	/**
	 * Settings level of the section plus manage_options.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_manage_keys( WP_REST_Request $request ) {
		return current_user_can( 'manage_options' ) && $this->can( (string) $request['module'], 'settings' );
	}

	/**
	 * View level of the section.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_view( WP_REST_Request $request ) {
		return $this->can( (string) $request['module'], 'view' );
	}

	/**
	 * GET masked keys.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_keys( WP_REST_Request $request ) {
		return rest_ensure_response( $this->view( (string) $request['module'] ) );
	}

	/**
	 * POST changes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_keys( WP_REST_Request $request ) {
		$module  = (string) $request['module'];
		$changes = array();
		foreach ( array( 'active', 'secondary', 'fallback', 'providers' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$changes[ $field ] = $request->get_param( $field );
			}
		}
		$result = Keys::update( $module, $changes );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( null !== $request->get_param( 'budget' ) ) {
			Settings::update( array( 'budgets' => array( $module => (float) $request->get_param( 'budget' ) ) ) );
		}
		return rest_ensure_response( $this->view( $module ) );
	}

	/**
	 * POST copy keys from the host again.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function import_keys( WP_REST_Request $request ) {
		Keys::reimport( (string) $request['module'] );
		return rest_ensure_response( $this->view( (string) $request['module'] ) );
	}

	/**
	 * POST send a tiny request to check a provider.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function test_key( WP_REST_Request $request ) {
		$module   = (string) $request['module'];
		$provider = (string) $request['provider'];
		$result   = AI_Service::generate(
			$module,
			'key_test',
			Request::make( '', 'Reply with the single word OK.', array( 'max_tokens' => 20 ) ),
			array(
				'provider' => $provider,
				'cache'    => false,
				'key_test' => true,
			)
		);
		if ( is_wp_error( $result ) ) {
			return rest_ensure_response(
				array(
					'ok'      => false,
					'message' => $result->get_error_message(),
					'health'  => Key_Health::notices( $module ),
				)
			);
		}
		return rest_ensure_response(
			array(
				'ok'      => true,
				/* translators: 1: provider name, 2: model id. */
				'message' => sprintf( __( '%1$s answered using %2$s.', 'chatbot' ), Providers::get( $provider )['label'], $result->model ),
				'health'  => Key_Health::notices( $module ),
			)
		);
	}

	/**
	 * GET key health notices.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_health( WP_REST_Request $request ) {
		return rest_ensure_response( Key_Health::notices( (string) $request['module'] ) );
	}

	/**
	 * Masked keys, health and budget for the settings screen.
	 *
	 * @param string $module Module id.
	 * @return array
	 */
	private function view( $module ) {
		$view              = Keys::public_view( $module );
		$view['health']    = Key_Health::notices( $module );
		$view['budget']    = Settings::budget( $module );
		$view['readiness'] = AI_Readiness::check( $module );
		return $view;
	}
}
