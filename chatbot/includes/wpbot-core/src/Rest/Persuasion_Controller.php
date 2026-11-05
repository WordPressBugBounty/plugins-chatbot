<?php
/**
 * REST routes of the shared persuasion pieces (round 3, CR-13): Persuasion
 * Kit, technique library, reader stages and the voice of customer bank.
 *
 * Every route names the module asking (the permissions grid): View reads,
 * Settings changes the kit or removes phrases, Use starts a refresh (its
 * AI grouping is paid from that module's key).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Rest;

use QuantumCloud\WPBotCore\Persuasion\Kit;
use QuantumCloud\WPBotCore\Persuasion\Stages;
use QuantumCloud\WPBotCore\Persuasion\Techniques;
use QuantumCloud\WPBotCore\Persuasion\Voice_Of_Customer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * /wpbot-core/v1/kit, /techniques, /voc
 */
final class Persuasion_Controller extends Rest_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$view     = function ( WP_REST_Request $request ) {
			return $this->can( (string) $request['module'], 'view' );
		};
		$use      = function ( WP_REST_Request $request ) {
			return $this->can( (string) $request['module'], 'use' );
		};
		$settings = function ( WP_REST_Request $request ) {
			return $this->can( (string) $request['module'], 'settings' );
		};
		$entry    = array(
			'module'     => $this->module_arg(),
			'type'       => array(
				'type'     => 'string',
				'required' => true,
				'enum'     => array_keys( Kit::types() ),
			),
			'label'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'body'       => array(
				'type'    => 'string',
				'default' => '',
			),
			'value'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'source'     => array(
				'type'    => 'string',
				'default' => '',
			),
			'url'        => array(
				'type'    => 'string',
				'default' => '',
			),
			'permission' => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'expires_on' => array(
				'type'    => 'string',
				'default' => '',
			),
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/kit',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'kit' ),
					'permission_callback' => $view,
					'args'                => array( 'module' => $this->module_arg() ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'kit_save' ),
					'permission_callback' => $settings,
					'args'                => $entry,
				),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/kit/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'kit_save' ),
					'permission_callback' => $settings,
					'args'                => $entry,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'kit_delete' ),
					'permission_callback' => $settings,
					'args'                => array( 'module' => $this->module_arg() ),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/techniques',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'techniques' ),
				'permission_callback' => $view,
				'args'                => array( 'module' => $this->module_arg() ),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/voc',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'voc' ),
				'permission_callback' => $view,
				'args'                => array(
					'module' => $this->module_arg(),
					'topic'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'search' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/voc/refresh',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'voc_refresh' ),
				'permission_callback' => $use,
				'args'                => array(
					'module' => $this->module_arg(),
					'group'  => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/voc/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'voc_delete' ),
				'permission_callback' => $settings,
				'args'                => array( 'module' => $this->module_arg() ),
			)
		);
	}

	/**
	 * GET the kit (expired entries included and marked, for the list).
	 *
	 * @return \WP_REST_Response
	 */
	public function kit() {
		return rest_ensure_response(
			array(
				'entries' => Kit::query( array( 'include_expired' => true ) )['entries'],
				'types'   => Kit::types(),
				'store'   => function_exists( 'wc_get_product' ),
			)
		);
	}

	/**
	 * POST an entry (new, or update with an id).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function kit_save( WP_REST_Request $request ) {
		$raw = array();
		foreach ( array( 'type', 'label', 'body', 'value', 'source', 'url', 'permission', 'expires_on' ) as $key ) {
			$raw[ $key ] = $request[ $key ];
		}
		if ( $request['id'] ) {
			$raw['id'] = (int) $request['id'];
		}
		$saved = Kit::save( $raw );
		return is_wp_error( $saved ) ? $saved : $this->kit();
	}

	/**
	 * DELETE an entry.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function kit_delete( WP_REST_Request $request ) {
		if ( ! Kit::delete( (int) $request['id'] ) ) {
			return new WP_Error( 'qcld_wpbot_core_kit_missing', __( 'That entry was not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		return $this->kit();
	}

	/**
	 * GET the technique library (labels and notes, no AI instructions) and the reader stages.
	 *
	 * @return \WP_REST_Response
	 */
	public function techniques() {
		return rest_ensure_response(
			array(
				'techniques' => array_map( array( Techniques::class, 'public_view' ), array_values( Techniques::all() ) ),
				'stages'     => array_values( Stages::all() ),
				'jobs'       => Techniques::JOBS,
			)
		);
	}

	/**
	 * GET customer words: topics, phrases, sources, refresh state and grouping estimate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function voc( WP_REST_Request $request ) {
		$sources = array();
		$state   = Voice_Of_Customer::state();
		foreach ( Voice_Of_Customer::sources() as $id => $source ) {
			$sources[] = array(
				'id'       => $id,
				'label'    => $source['label'],
				'edition'  => $source['edition'],
				'last_run' => isset( $state['last_run'][ $id ] ) ? $state['last_run'][ $id ] : '',
			);
		}
		return rest_ensure_response(
			array(
				'topics'   => Voice_Of_Customer::topics(),
				'phrases'  => Voice_Of_Customer::query(
					array(
						'topic'  => (string) $request['topic'],
						'search' => (string) $request['search'],
						'limit'  => 200,
					)
				),
				'sources'  => $sources,
				'job_id'   => self::active_job( (int) $state['job_id'] ),
				'estimate' => Voice_Of_Customer::estimate( (string) $request['module'] ),
			)
		);
	}

	/**
	 * The refresh job id while it is still running (0 once it ended).
	 *
	 * @param int $job_id Job id.
	 * @return int
	 */
	private static function active_job( $job_id ) {
		$job = $job_id ? \QuantumCloud\WPBotCore\Jobs\Job_Store::get( $job_id ) : null;
		return $job && in_array( $job['status'], array( 'queued', 'running', 'paused' ), true ) ? $job_id : 0;
	}

	/**
	 * POST start a refresh.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function voc_refresh( WP_REST_Request $request ) {
		$job = Voice_Of_Customer::refresh( (string) $request['module'], (bool) $request['group'] );
		return is_wp_error( $job ) ? $job : rest_ensure_response( array( 'job_id' => $job ) );
	}

	/**
	 * DELETE a phrase.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function voc_delete( WP_REST_Request $request ) {
		if ( ! Voice_Of_Customer::delete( (int) $request['id'] ) ) {
			return new WP_Error( 'qcld_wpbot_core_voc_missing', __( 'That phrase was not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'deleted' => true ) );
	}
}
