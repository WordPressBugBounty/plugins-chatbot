<?php
/**
 * REST routes of the Experiments service (round 3, P4).
 *
 * The beacon is public (visitors' browsers send it). It only counts events
 * for running tests and known variants, limits each visitor, and stores
 * daily totals only. Admin routes name the module asking (permissions
 * grid): View reads, Settings stops tests and applies winners.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Rest;

defined( 'ABSPATH' ) || exit;

use QuantumCloud\WPBotCore\Experiments\Experiments;
use QuantumCloud\WPBotCore\Experiments\Front;
use QuantumCloud\WPBotCore\Experiments\Learning;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Experiments controller.
 */
final class Experiments_Controller extends Rest_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$view     = function ( WP_REST_Request $request ) {
			return $this->can( (string) $request['module'], 'view' ) && $this->owns( $request );
		};
		$settings = function ( WP_REST_Request $request ) {
			return $this->can( (string) $request['module'], 'settings' ) && $this->owns( $request );
		};
		register_rest_route(
			self::NAMESPACE_V1,
			'/experiments/beacon',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'beacon' ),
				'permission_callback' => array( $this, 'can_beacon' ),
				'args'                => array(
					'events' => array(
						'type'     => 'array',
						'required' => true,
						'maxItems' => 10,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/experiments',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'index' ),
				'permission_callback' => $view,
				'args'                => array(
					'module'    => $this->module_arg(),
					'status'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'object_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/experiments/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'show' ),
				'permission_callback' => $view,
				'args'                => array( 'module' => $this->module_arg() ),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/experiments/(?P<id>\d+)/stop',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'stop' ),
				'permission_callback' => $settings,
				'args'                => array( 'module' => $this->module_arg() ),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/experiments/(?P<id>\d+)/apply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply' ),
				'permission_callback' => $settings,
				'args'                => array(
					'module'  => $this->module_arg(),
					'variant' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/experiments/learning',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'learning' ),
				'permission_callback' => function ( WP_REST_Request $request ) {
					return $this->can( (string) $request['module'], 'view' );
				},
				'args'                => array( 'module' => $this->module_arg() ),
			)
		);
	}

	/**
	 * The beacon is public, but only accepts events for running tests.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_beacon( WP_REST_Request $request ) {
		$events = $request['events'];
		return is_array( $events ) && array() !== $events && array() !== Experiments::front()['tests'];
	}

	/**
	 * POST beacon.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function beacon( WP_REST_Request $request ) {
		return rest_ensure_response( array( 'counted' => Front::beacon( (array) $request['events'] ) ) );
	}

	/**
	 * GET tests of a module.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( WP_REST_Request $request ) {
		$args = array( 'module' => sanitize_key( (string) $request['module'] ) );
		if ( '' !== (string) $request['status'] ) {
			$args['status'] = array_filter( array_map( 'sanitize_key', explode( ',', (string) $request['status'] ) ) );
		}
		if ( $request['object_id'] ) {
			$args['object_id'] = absint( $request['object_id'] );
		}
		$out = array();
		foreach ( Experiments::query( $args ) as $exp ) {
			$out[] = Experiments::view( $exp['id'] );
		}
		return rest_ensure_response( array( 'experiments' => $out ) );
	}

	/**
	 * GET one test.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function show( WP_REST_Request $request ) {
		$view = Experiments::view( (int) $request['id'] );
		return $view ? rest_ensure_response( $view ) : new WP_Error( 'qcld_wpbot_core_experiment_missing', __( 'That test does not exist.', 'chatbot' ), array( 'status' => 404 ) );
	}

	/**
	 * POST stop.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function stop( WP_REST_Request $request ) {
		$view = Experiments::stop( (int) $request['id'] );
		return is_wp_error( $view ) ? $view : rest_ensure_response( $view );
	}

	/**
	 * POST apply a winner.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function apply( WP_REST_Request $request ) {
		$view = Experiments::apply( (int) $request['id'], (int) $request['variant'], 'owner' );
		return is_wp_error( $view ) ? $view : rest_ensure_response( $view );
	}

	/**
	 * GET per-technique results of a module.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function learning( WP_REST_Request $request ) {
		$rows = Learning::query( array( 'module' => sanitize_key( (string) $request['module'] ) ) );
		foreach ( $rows as &$row ) {
			$row['sentence'] = Learning::sentence( $row );
		}
		return rest_ensure_response( array( 'results' => $rows ) );
	}

	/**
	 * A test route only reaches tests of the module asking.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private function owns( WP_REST_Request $request ) {
		if ( ! isset( $request['id'] ) ) {
			return true;
		}
		$exp = Experiments::get( (int) $request['id'] );
		return ! $exp || sanitize_key( (string) $request['module'] ) === $exp['module'];
	}
}
