<?php
/**
 * Diagnostics: a small test job that proves the queue works on this host.
 *
 * The job has three short waiting steps and, when asked, one tiny AI call.
 * Owners can watch it progress, reload the page, stop and resume it.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Rest;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * /wpbot-writer/v1/diagnostics/test-job
 */
final class Diagnostics_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			Settings_Controller::NAMESPACE_V1,
			'/diagnostics/test-job',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_test_job' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'with_ai' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
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
	 * POST create the test job.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function create_test_job( WP_REST_Request $request ) {
		$steps = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$steps[] = array(
				'kind'  => 'writer_test_wait',
				/* translators: %d: step number. */
				'label' => sprintf( __( 'Test step %d', 'chatbot' ), $i ),
				'input' => array( 'n' => $i ),
			);
		}
		if ( (bool) $request->get_param( 'with_ai' ) ) {
			$steps[] = array(
				'kind'  => 'writer_test_ai',
				'label' => __( 'Ask the AI provider for a one-word answer', 'chatbot' ),
				'input' => array(),
			);
		}

		$job_id = Job_Store::create(
			Module::ID,
			'test',
			array( 'title' => __( 'Test job', 'chatbot' ) ),
			$steps
		);
		if ( ! $job_id ) {
			return new WP_Error( 'qcld_wpbot_writer_job_failed', __( 'The test job could not be created. Check that the database tables exist.', 'chatbot' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'job_id' => $job_id ) );
	}
}
