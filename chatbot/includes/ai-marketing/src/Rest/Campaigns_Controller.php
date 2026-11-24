<?php
/**
 * REST: campaigns and templates.
 *
 * Reading needs wpbot_marketing_view, writing wpbot_marketing_use, and
 * sending (send, schedule, resume) wpbot_marketing_publish.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Rest;

use QuantumCloud\AiMarketing\Content\Campaigns;
use QuantumCloud\AiMarketing\Content\Templates;
use QuantumCloud\AiMarketing\Sending\Queue;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Campaign routes.
 */
final class Campaigns_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$view    = Rest_Controller::can( 'wpbot_marketing_view' );
		$use     = Rest_Controller::can( 'wpbot_marketing_use' );
		$publish = Rest_Controller::can( 'wpbot_marketing_publish' );
		$id      = '(?P<id>\d+)';
		$routes  = array(
			array( '/campaigns', WP_REST_Server::READABLE, 'index', $view ),
			array( '/campaigns', WP_REST_Server::CREATABLE, 'create', $use ),
			array( '/campaigns/recipients', WP_REST_Server::CREATABLE, 'recipients', $view ),
			array( '/campaigns/' . $id, WP_REST_Server::READABLE, 'show', $view ),
			array( '/campaigns/' . $id, 'PATCH', 'update', $use ),
			array( '/campaigns/' . $id, WP_REST_Server::DELETABLE, 'delete', $use ),
			array( '/campaigns/' . $id . '/duplicate', WP_REST_Server::CREATABLE, 'duplicate', $use ),
			array( '/campaigns/' . $id . '/test', WP_REST_Server::CREATABLE, 'test', $use ),
			array( '/campaigns/' . $id . '/send', WP_REST_Server::CREATABLE, 'send', $publish ),
			array( '/campaigns/' . $id . '/(?P<action>pause|resume|cancel)', WP_REST_Server::CREATABLE, 'control', $publish ),
			array( '/templates', WP_REST_Server::READABLE, 'templates', $view ),
			array( '/templates', WP_REST_Server::CREATABLE, 'create_template', $use ),
			array( '/templates/' . $id, WP_REST_Server::DELETABLE, 'delete_template', $use ),
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
	 * GET /campaigns.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function index( WP_REST_Request $request ) {
		return Campaigns::all( (string) $request->get_param( 'status' ) );
	}

	/**
	 * GET /campaigns/{id} with delivery numbers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function show( WP_REST_Request $request ) {
		$campaign = Campaigns::get( (int) $request['id'] );
		if ( ! $campaign ) {
			return new WP_Error( 'qcld_mk_not_found', __( 'Campaign not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$campaign['stats']      = Campaigns::stats( (int) $request['id'] );
		$campaign['would_send'] = Campaigns::count_recipients( $campaign['audience'] );
		return $campaign;
	}

	/**
	 * POST /campaigns.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create( WP_REST_Request $request ) {
		$saved = Campaigns::save( 0, Audience_Controller::body( $request ) );
		return is_wp_error( $saved ) ? $saved : new WP_REST_Response( $saved, 201 );
	}

	/**
	 * PATCH /campaigns/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function update( WP_REST_Request $request ) {
		return Campaigns::save( (int) $request['id'], Audience_Controller::body( $request ) );
	}

	/**
	 * DELETE /campaigns/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function delete( WP_REST_Request $request ) {
		$done = Campaigns::delete( (int) $request['id'] );
		return is_wp_error( $done ) ? $done : array( 'deleted' => true );
	}

	/**
	 * POST /campaigns/{id}/duplicate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function duplicate( WP_REST_Request $request ) {
		return Campaigns::duplicate( (int) $request['id'] );
	}

	/**
	 * POST /campaigns/recipients: count for an audience (before saving).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function recipients( WP_REST_Request $request ) {
		$body = Audience_Controller::body( $request );
		return array( 'count' => Campaigns::count_recipients( isset( $body['audience'] ) && is_array( $body['audience'] ) ? $body['audience'] : array() ) );
	}

	/**
	 * POST /campaigns/{id}/test: email.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function test( WP_REST_Request $request ) {
		$body = Audience_Controller::body( $request );
		$done = Campaigns::test( (int) $request['id'], isset( $body['email'] ) ? (string) $body['email'] : '' );
		return is_wp_error( $done ) ? $done : array( 'sent' => true );
	}

	/**
	 * POST /campaigns/{id}/send: scheduled_at (UTC) optional. Sends a first batch right away.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function send( WP_REST_Request $request ) {
		$body     = Audience_Controller::body( $request );
		$campaign = Campaigns::start( (int) $request['id'], isset( $body['scheduled_at'] ) ? sanitize_text_field( (string) $body['scheduled_at'] ) : '' );
		if ( is_wp_error( $campaign ) ) {
			return $campaign;
		}
		if ( 'sending' === $campaign['status'] ) {
			Queue::process( 5 );
		}
		return Campaigns::get( (int) $request['id'] );
	}

	/**
	 * POST /campaigns/{id}/pause|resume|cancel.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function control( WP_REST_Request $request ) {
		return Campaigns::control( (int) $request['id'], (string) $request['action'] );
	}

	/**
	 * GET /templates.
	 *
	 * @return array
	 */
	public static function templates() {
		return Templates::all();
	}

	/**
	 * POST /templates: name, subject, content_html.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function create_template( WP_REST_Request $request ) {
		$body = Audience_Controller::body( $request );
		$id   = Templates::create( isset( $body['name'] ) ? $body['name'] : '', isset( $body['subject'] ) ? $body['subject'] : '', isset( $body['content_html'] ) ? $body['content_html'] : '' );
		return is_wp_error( $id ) ? $id : array( 'id' => $id );
	}

	/**
	 * DELETE /templates/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function delete_template( WP_REST_Request $request ) {
		return array( 'deleted' => Templates::delete( (int) $request['id'] ) );
	}
}
