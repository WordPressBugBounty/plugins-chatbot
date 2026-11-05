<?php
/**
 * REST routes that let a screen drive and control a job (the browser runner).
 *
 * POST /jobs/{id}/tick runs at most one step, so each request stays short.
 * The screen calls it every few seconds while it is open.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Rest;

use QuantumCloud\WPBotCore\Jobs\Job_Runner;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * /wpbot-core/v1/jobs
 */
final class Jobs_Controller extends Rest_Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$id = array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
				'validate_callback' => static function ( $value ) {
					return is_numeric( $value ) && (int) $value > 0;
				},
			),
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/jobs/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_job' ),
				'permission_callback' => array( $this, 'can_access_job' ),
				'args'                => $id,
			)
		);

		foreach ( array( 'tick', 'stop', 'resume', 'cancel' ) as $action ) {
			register_rest_route(
				self::NAMESPACE_V1,
				'/jobs/(?P<id>\d+)/' . $action,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'action_' . $action ),
					'permission_callback' => array( $this, 'can_access_job' ),
					'args'                => $id,
				)
			);
		}

		register_rest_route(
			self::NAMESPACE_V1,
			'/jobs/(?P<id>\d+)/budget',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'action_budget' ),
				'permission_callback' => array( $this, 'can_access_job' ),
				'args'                => array_merge(
					$id,
					array(
						'budget' => array(
							'type'     => 'number',
							'required' => true,
							'minimum'  => 0,
						),
					)
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/jobs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_jobs' ),
				'permission_callback' => array( $this, 'can_list' ),
				'args'                => array( 'module' => $this->module_arg() ),
			)
		);
	}

	/**
	 * The job's owner with the Use level, or anyone with the Settings level.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_access_job( WP_REST_Request $request ) {
		$job = Job_Store::get( (int) $request['id'] );
		if ( ! $job ) {
			return false;
		}
		if ( $this->can( $job['module'], 'settings' ) ) {
			return true;
		}
		return get_current_user_id() === (int) $job['user_id'] && $this->can( $job['module'], 'use' );
	}

	/**
	 * Use level of the module.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_list( WP_REST_Request $request ) {
		return $this->can( (string) $request['module'], 'use' );
	}

	/**
	 * GET progress.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function get_job( WP_REST_Request $request ) {
		return $this->progress( (int) $request['id'] );
	}

	/**
	 * POST run one step, then report progress.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function action_tick( WP_REST_Request $request ) {
		$job_id = (int) $request['id'];
		Job_Store::touch_browser( $job_id );
		Job_Runner::run_one( $job_id );
		return $this->progress( $job_id );
	}

	/**
	 * POST pause.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function action_stop( WP_REST_Request $request ) {
		Job_Store::pause( (int) $request['id'] );
		return $this->progress( (int) $request['id'] );
	}

	/**
	 * POST resume or retry.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function action_resume( WP_REST_Request $request ) {
		Job_Store::resume( (int) $request['id'] );
		return $this->progress( (int) $request['id'] );
	}

	/**
	 * POST budget: set (raise) a job's budget and resume it if it was
	 * paused for its budget.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function action_budget( WP_REST_Request $request ) {
		Job_Store::set_budget( (int) $request['id'], (float) $request['budget'] );
		return $this->progress( (int) $request['id'] );
	}

	/**
	 * POST cancel.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function action_cancel( WP_REST_Request $request ) {
		Job_Store::cancel( (int) $request['id'] );
		return $this->progress( (int) $request['id'] );
	}

	/**
	 * GET recent jobs of a module (own jobs; all jobs for settings level).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_jobs( WP_REST_Request $request ) {
		$module = (string) $request['module'];
		$user   = $this->can( $module, 'settings' ) ? 0 : get_current_user_id();
		return rest_ensure_response( Job_Store::recent( $module, $user ) );
	}

	/**
	 * Progress response.
	 *
	 * @param int $job_id Job id.
	 * @return \WP_REST_Response|WP_Error
	 */
	private function progress( $job_id ) {
		$progress = Job_Runner::progress( $job_id );
		if ( null === $progress ) {
			return new WP_Error( 'qcld_wpbot_core_no_job', __( 'Job not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $progress );
	}
}
