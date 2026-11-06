<?php
/**
 * REST routes of the content calendar and its ideas list.
 *
 * Seeing the calendar needs the AI Writer View level; ideas and moving
 * drafts need Use; moving scheduled posts also needs Publish (checked per
 * post, with WordPress's own edit and publish rights).
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Rest;

use QuantumCloud\AiWriter\Calendar\Calendar;
use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Topics\Ideas;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * /wpbot-writer/v1/calendar/*
 */
final class Calendar_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$ns   = Settings_Controller::NAMESPACE_V1;
		$date = array(
			'type'              => 'string',
			'required'          => true,
			'validate_callback' => array( Calendar::class, 'valid_date' ),
		);
		register_rest_route(
			$ns,
			'/calendar',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'items' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'start'      => $date,
					'end'        => $date,
					'post_types' => array(
						'type'    => 'array',
						'items'   => array( 'type' => 'string' ),
						'default' => array(),
					),
					'statuses'   => array(
						'type'    => 'array',
						'items'   => array(
							'type' => 'string',
							'enum' => Calendar::STATUSES,
						),
						'default' => array(),
					),
					'mine'       => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'author'     => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/calendar/move',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'move' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'post_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'date'    => $date,
					'time'    => array(
						'type'    => 'string',
						'default' => '',
						'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$|^$',
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/calendar/ideas',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'ideas' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_idea' ),
					'permission_callback' => array( $this, 'can_use' ),
					'args'                => array(
						'title' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/calendar/ideas/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_idea' ),
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
		register_rest_route(
			$ns,
			'/calendar/ideas/(?P<id>\d+)/place',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'place_idea' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'id'        => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'date'      => $date,
					'post_type' => array(
						'type'    => 'string',
						'default' => 'post',
					),
				),
			)
		);
		// CR-16: the format of a planned piece.
		register_rest_route(
			$ns,
			'/calendar/items/(?P<id>\\d+)/format',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'set_format' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'id'     => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'format' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * AI Writer View level.
	 *
	 * @return bool
	 */
	public function can_view() {
		return current_user_can( Module::cap( 'view' ) );
	}

	/**
	 * AI Writer Use level.
	 *
	 * @return bool
	 */
	public function can_use() {
		return current_user_can( Module::cap( 'use' ) );
	}

	/**
	 * GET items and warnings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function items( WP_REST_Request $request ) {
		$start = (string) $request['start'];
		$end   = (string) $request['end'];
		if ( $end < $start || ( strtotime( $end ) - strtotime( $start ) ) > 62 * DAY_IN_SECONDS ) {
			return new WP_Error( 'qcld_wpbot_writer_bad_range', __( 'Choose a range of up to two months.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$result = Calendar::items(
			$start,
			$end,
			array(
				'post_types' => array_map( 'sanitize_key', (array) $request['post_types'] ),
				'statuses'   => (array) $request['statuses'],
				'mine'       => (bool) $request['mine'],
				'author'     => (int) $request['author'],
			)
		);
		return rest_ensure_response( $result );
	}

	/**
	 * POST move a post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function move( WP_REST_Request $request ) {
		$result = Calendar::move( (int) $request['post_id'], (string) $request['date'], (string) $request['time'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * GET open ideas.
	 *
	 * @return \WP_REST_Response
	 */
	public function ideas() {
		return rest_ensure_response( Ideas::query( 'new', 100 ) );
	}

	/**
	 * POST add an idea.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function add_idea( WP_REST_Request $request ) {
		$id = Ideas::add( (string) $request['title'], 'manual', array( 'source_ref' => 'manual-' . wp_generate_password( 12, false ) ) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		// A saved idea is a planned page in the shared keyword map.
		\QuantumCloud\AiWriter\Topics\Map_Sync::plan_idea( Ideas::get( $id ) );
		return rest_ensure_response( Ideas::get( $id ) );
	}

	/**
	 * DELETE an idea.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_idea( WP_REST_Request $request ) {
		Ideas::set_status( (int) $request['id'], 'dismissed' );
		\QuantumCloud\AiWriter\Topics\Map_Sync::unplan_idea( (int) $request['id'] );
		return rest_ensure_response( array( 'dismissed' => true ) );
	}

	/**
	 * POST place an idea on a day (creates a dated draft).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function place_idea( WP_REST_Request $request ) {
		$result = Calendar::place_idea( (int) $request['id'], (string) $request['date'], sanitize_key( (string) $request['post_type'] ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * CR-16: POST the format of a planned piece.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_format( WP_REST_Request $request ) {
		$result = Calendar::set_format( (int) $request['id'], (string) $request['format'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
}
