<?php
/**
 * REST routes for shared connections (stock image keys, Google, YouTube).
 * Administrators only.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Rest;

use QuantumCloud\WPBotCore\Connections\Google;
use QuantumCloud\WPBotCore\Connections\Search_Console;
use QuantumCloud\WPBotCore\Connections\Stock_Keys;
use QuantumCloud\WPBotCore\Connections\YouTube_Key;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * /wpbot-core/v1/connections/*
 */
final class Connections_Controller extends Rest_Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/connections/stock',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stock' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_stock' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'services' => array(
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
		register_rest_route(
			self::NAMESPACE_V1,
			'/connections/google',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_google' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_google' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'client_id'     => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'client_secret' => array(
							'type' => 'string',
						),
						'property'      => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'clear'         => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/connections/google/connect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'connect_google' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'return_url' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/connections/google/disconnect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'disconnect_google' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/connections/google/sites',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'google_sites' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/connections/youtube',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_youtube' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_youtube' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'key'       => array(
							'type' => 'string',
						),
						'clear_key' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Keys are for administrators.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET masked keys.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_stock() {
		return rest_ensure_response( Stock_Keys::public_view() );
	}

	/**
	 * POST keys.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_stock( WP_REST_Request $request ) {
		return rest_ensure_response( Stock_Keys::update( (array) $request->get_param( 'services' ) ) );
	}

	/**
	 * GET the Google connection (masked).
	 *
	 * @return \WP_REST_Response
	 */
	public function get_google() {
		$view           = Google::public_view();
		$view['notice'] = Google::take_notice();
		return rest_ensure_response( $view );
	}

	/**
	 * POST the OAuth client or property.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_google( WP_REST_Request $request ) {
		$changes = array_intersect_key( (array) $request->get_params(), array_flip( array( 'client_id', 'client_secret', 'property', 'clear' ) ) );
		$view    = Google::update( $changes );
		Search_Console::flush();
		return rest_ensure_response( $view );
	}

	/**
	 * POST start connecting: returns Google's consent URL.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function connect_google( WP_REST_Request $request ) {
		$url = Google::auth_url( (string) $request['return_url'] );
		return is_wp_error( $url ) ? $url : rest_ensure_response( array( 'url' => $url ) );
	}

	/**
	 * POST disconnect Google.
	 *
	 * @return \WP_REST_Response
	 */
	public function disconnect_google() {
		Search_Console::flush();
		return rest_ensure_response( Google::disconnect() );
	}

	/**
	 * GET Search Console properties.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function google_sites() {
		$sites = Search_Console::sites();
		return is_wp_error( $sites ) ? $sites : rest_ensure_response(
			array(
				'sites'     => $sites,
				'suggested' => Search_Console::match( $sites, home_url( '/' ) ),
			)
		);
	}

	/**
	 * GET the YouTube key (masked).
	 *
	 * @return \WP_REST_Response
	 */
	public function get_youtube() {
		return rest_ensure_response( YouTube_Key::public_view() );
	}

	/**
	 * POST the YouTube key.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_youtube( WP_REST_Request $request ) {
		return rest_ensure_response(
			YouTube_Key::update(
				array(
					'key'       => (string) $request['key'],
					'clear_key' => (bool) $request['clear_key'],
				)
			)
		);
	}
}
