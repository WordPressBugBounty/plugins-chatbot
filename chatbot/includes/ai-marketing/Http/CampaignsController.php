<?php
/**
 * REST: campaigns.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Http;

use QuantumCloud\WPBot\CRM\Domain\CampaignService;

defined( 'ABSPATH' ) || exit;

/**
 * Contract #2–9: list/create/get/update/delete, duplicate, send, process.
 */
class CampaignsController extends RestController {

	/**
	 * Service.
	 *
	 * @var CampaignService
	 */
	private $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new CampaignService();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$admin = array( $this, 'admin_permission' );

		register_rest_route(
			self::NS,
			'/campaigns',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			self::NS,
			'/campaigns/(?P<id>\d+)',
			array(
				'args' => $this->id_arg(),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $admin,
				),
			)
		);
		foreach ( array( 'duplicate', 'send', 'process' ) as $action ) {
			register_rest_route(
				self::NS,
				'/campaigns/(?P<id>\d+)/' . $action,
				array(
					'args'                => $this->id_arg(),
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, $action . '_item' ),
					'permission_callback' => $admin,
				)
			);
		}
	}

	/**
	 * GET /campaigns → {items,total,pages}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		list( $page, $per_page ) = $this->pagination( $request, 0 );
		$result                  = $this->service->search(
			array(
				'page'     => $page,
				'per_page' => $per_page,
				'search'   => sanitize_text_field( (string) $request->get_param( 'search' ) ),
				'status'   => sanitize_key( (string) $request->get_param( 'status' ) ),
			)
		);
		return $this->items_response( $result['items'], $result['total'], $per_page );
	}

	/**
	 * POST /campaigns → full campaign object (with id).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$result = $this->service->create( $this->body( $request ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = rest_ensure_response( $result );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * GET /campaigns/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		return $this->respond( $this->service->get( (int) $request['id'] ) );
	}

	/**
	 * PUT /campaigns/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		return $this->respond( $this->service->update( (int) $request['id'], $this->body( $request ) ) );
	}

	/**
	 * DELETE /campaigns/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$id     = (int) $request['id'];
		$result = $this->service->delete( $id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response(
			array(
				'success' => true,
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	/**
	 * POST /campaigns/{id}/duplicate.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function duplicate_item( $request ) {
		return $this->respond( $this->service->duplicate( (int) $request['id'] ) );
	}

	/**
	 * POST /campaigns/{id}/send → {success, queued, status, message}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function send_item( $request ) {
		return $this->respond( $this->service->start( (int) $request['id'] ) );
	}

	/**
	 * POST /campaigns/{id}/process → {success, processed, remaining}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function process_item( $request ) {
		return $this->respond( $this->service->process( (int) $request['id'] ) );
	}

	/**
	 * JSON body, falling back to all params.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	private function body( $request ) {
		$json = $request->get_json_params();
		if ( is_array( $json ) && ! empty( $json ) ) {
			return $json;
		}
		$params = $request->get_body_params();
		return is_array( $params ) && ! empty( $params ) ? $params : (array) $request->get_params();
	}

	/**
	 * Pass errors through, wrap arrays.
	 *
	 * @param array|\WP_Error $result Result.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function respond( $result ) {
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
}
