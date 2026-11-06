<?php
/**
 * REST routes of the Library tab. Listing needs the AI Writer View level
 * (and pieces are limited to what the user can edit); Quick write items
 * and deleting them need Use (own items only, or the Settings level).
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Rest;

use QuantumCloud\AiWriter\Library\Library;
use QuantumCloud\AiWriter\Module;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * /wpbot-writer/v1/library/*
 */
final class Library_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$ns     = Settings_Controller::NAMESPACE_V1;
		$search = array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		);
		register_rest_route(
			$ns,
			'/library/pieces',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'pieces' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'search' => $search,
					'status' => array(
						'type'    => 'string',
						'default' => '',
						'enum'    => array_merge( array( '' ), Library::STATUSES ),
					),
					'page'   => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/library/items',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'items' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array( 'search' => $search ),
			)
		);
		register_rest_route(
			$ns,
			'/library/items/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
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
	 * Use level.
	 *
	 * @return bool
	 */
	public function can_use() {
		return current_user_can( Module::cap( 'use' ) );
	}

	/**
	 * GET finished pieces.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function pieces( WP_REST_Request $request ) {
		$extra = array();
		foreach ( (array) $request->get_query_params() as $key => $value ) {
			if ( ! in_array( $key, array( 'search', 'status', 'page', 'rest_route', '_locale' ), true ) && is_scalar( $value ) ) {
				$extra[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
			}
		}
		return rest_ensure_response( Library::pieces( (string) $request['search'], (string) $request['status'], (int) $request['page'], $extra ) );
	}

	/**
	 * GET Quick write items.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function items( WP_REST_Request $request ) {
		return rest_ensure_response( array( 'items' => Library::items( (string) $request['search'] ) ) );
	}

	/**
	 * DELETE a Quick write item.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( WP_REST_Request $request ) {
		$result = Library::delete_item( (int) $request['id'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'deleted' => true ) );
	}
}
