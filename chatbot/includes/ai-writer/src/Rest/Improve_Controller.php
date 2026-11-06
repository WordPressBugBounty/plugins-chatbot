<?php
/**
 * REST routes of "Improve existing content": pick a post, run the review,
 * apply accepted proposals, publish or discard update copies, and the
 * decay task list.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Rest;

use QuantumCloud\AiWriter\Improve\Decay_Alerts;
use QuantumCloud\AiWriter\Improve\Improve_Job;
use QuantumCloud\AiWriter\Improve\Updates;
use QuantumCloud\AiWriter\Module;
use QuantumCloud\WPBotCore\Jobs\Job_Runner;
use QuantumCloud\WPBotCore\Jobs\Job_Store;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * /wpbot-writer/v1/improve/*
 */
final class Improve_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$ns = Settings_Controller::NAMESPACE_V1;
		$id = array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
		);
		register_rest_route(
			$ns,
			'/improve/posts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'posts' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'search' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/improve/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'post_id'    => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'fix'        => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					// Round 3: re-angle with another reader stage or technique.
					'persuasion' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/improve/jobs/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_job' ),
				'permission_callback' => array( $this, 'can_access_job' ),
				'args'                => $id,
			)
		);
		// CR-14: one section's proposals written again.
		register_rest_route(
			$ns,
			'/improve/jobs/(?P<id>\d+)/regenerate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'regenerate' ),
				'permission_callback' => array( $this, 'can_access_job' ),
				'args'                => array_merge(
					$id,
					array(
						'index'      => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'regenerate' => array(
							'type'    => 'object',
							'default' => array(),
						),
					)
				),
			)
		);
		register_rest_route(
			$ns,
			'/improve/jobs/(?P<id>\d+)/apply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply' ),
				'permission_callback' => array( $this, 'can_access_job' ),
				'args'                => array_merge(
					$id,
					array(
						'accepted' => array(
							'type'              => 'array',
							'required'          => true,
							'items'             => array( 'type' => 'string' ),
							'validate_callback' => static function ( $value ) {
								return is_array( $value ) && count( $value ) <= 500;
							},
						),
						// CR-7: the owner's own wording for accepted proposals (id => text; meta => title, description).
						'edits'    => array(
							'type'              => 'object',
							'default'           => array(),
							'validate_callback' => static function ( $value ) {
								return is_array( $value ) && count( $value ) <= 500;
							},
						),
					)
				),
			)
		);
		register_rest_route(
			$ns,
			'/improve/updates/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'discard_update' ),
					'permission_callback' => array( $this, 'can_use' ),
					'args'                => $id,
				),
			)
		);
		register_rest_route(
			$ns,
			'/improve/updates/(?P<id>\d+)/publish',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'publish_update' ),
				'permission_callback' => array( $this, 'can_publish_update' ),
				'args'                => $id,
			)
		);
		register_rest_route(
			$ns,
			'/improve/tasks',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'tasks' ),
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
		register_rest_route(
			$ns,
			'/improve/tasks/check',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'check_tasks' ),
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
		register_rest_route(
			$ns,
			'/improve/tasks/(?P<id>\d+)/dismiss',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'dismiss_task' ),
				'permission_callback' => array( $this, 'can_edit_post_id' ),
				'args'                => $id,
			)
		);
	}

	/**
	 * POST one section's proposals again (CR-14).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function regenerate( WP_REST_Request $request ) {
		$result = Improve_Job::regenerate_section( (int) $request['id'], (int) $request['index'], \QuantumCloud\AiWriter\Regenerate\Options::sanitise( (array) $request['regenerate'] ) );
		return is_wp_error( $result ) ? Rest_Errors::with_status( $result ) : rest_ensure_response( array( 'section' => $result ) );
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
	 * Use level plus edit rights on the post in the route.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit_post_id( WP_REST_Request $request ) {
		return $this->can_use() && current_user_can( 'edit_post', (int) $request['id'] );
	}

	/**
	 * Owner of an Improve job with Use, or anyone with Settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_access_job( WP_REST_Request $request ) {
		$job = Job_Store::get( (int) $request['id'] );
		if ( ! $job || Module::ID !== $job['module'] || Improve_Job::TYPE !== $job['type'] ) {
			return false;
		}
		return current_user_can( Module::cap( 'settings' ) ) || ( get_current_user_id() === (int) $job['user_id'] && $this->can_use() );
	}

	/**
	 * Publish level, edit and publish rights on the original.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_publish_update( WP_REST_Request $request ) {
		return Updates::can_publish( (int) $request['id'] );
	}

	/**
	 * GET posts the user can improve (posts, pages, products, any public type).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function posts( WP_REST_Request $request ) {
		$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
		$query = array(
			'post_type'      => $types,
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => 20,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Leaves out update copies.
				array(
					'key'     => Updates::META_OF,
					'compare' => 'NOT EXISTS',
				),
			),
		);
		if ( '' !== $request['search'] ) {
			$query['s'] = $request['search'];
		}
		$out = array();
		foreach ( get_posts( $query ) as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			$type  = get_post_type_object( $post->post_type );
			$out[] = array(
				'id'        => (int) $post->ID,
				'title'     => get_the_title( $post ),
				'type'      => $type ? $type->labels->singular_name : $post->post_type,
				'post_type' => $post->post_type,
				'status'    => $post->post_status,
				'live'      => Improve_Job::is_live( $post ),
				'modified'  => mysql_to_rfc3339( $post->post_modified_gmt ),
				'update'    => (int) get_post_meta( $post->ID, Updates::META_NEW, true ),
			);
		}
		return rest_ensure_response( $out );
	}

	/**
	 * POST start.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function start( WP_REST_Request $request ) {
		$job_id = Improve_Job::start( (int) $request['post_id'], (string) $request['fix'], (array) $request['persuasion'] );
		if ( is_wp_error( $job_id ) ) {
			return $job_id;
		}
		return rest_ensure_response( $this->view( $job_id ) );
	}

	/**
	 * GET job: progress, and the proposals once done.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_job( WP_REST_Request $request ) {
		return rest_ensure_response( $this->view( (int) $request['id'] ) );
	}

	/**
	 * POST apply.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function apply( WP_REST_Request $request ) {
		$result = Improve_Job::apply( (int) $request['id'], array_map( 'sanitize_text_field', (array) $request['accepted'] ), (array) $request['edits'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * POST publish an update copy into its original.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function publish_update( WP_REST_Request $request ) {
		$result = Updates::publish( (int) $request['id'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * DELETE an update copy.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function discard_update( WP_REST_Request $request ) {
		$result = Updates::discard( (int) $request['id'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * GET decay tasks.
	 *
	 * @return \WP_REST_Response
	 */
	public function tasks() {
		return rest_ensure_response(
			array(
				'tasks'   => Decay_Alerts::open_tasks(),
				'status'  => Decay_Alerts::status(),
				'reviews' => $this->waiting_reviews(),
			)
		);
	}

	/**
	 * POST run the decay check now.
	 *
	 * @return \WP_REST_Response
	 */
	public function check_tasks() {
		Decay_Alerts::check();
		return $this->tasks();
	}

	/**
	 * POST dismiss a task.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function dismiss_task( WP_REST_Request $request ) {
		Decay_Alerts::dismiss( (int) $request['id'] );
		return $this->tasks();
	}

	/**
	 * The user's finished reviews that were not applied yet (last 30 days).
	 *
	 * @return array[]
	 */
	private function waiting_reviews() {
		$out = array();
		foreach ( Job_Store::recent( Module::ID, get_current_user_id(), 40 ) as $row ) {
			if ( Improve_Job::TYPE !== $row['type'] || 'done' !== $row['status'] || strtotime( $row['updated_at'] . ' UTC' ) < time() - 30 * DAY_IN_SECONDS ) {
				continue;
			}
			$job = Job_Store::get( (int) $row['id'] );
			if ( ! $job || ! empty( $job['payload']['applied'] ) || ! current_user_can( 'edit_post', (int) $row['object_id'] ) ) {
				continue;
			}
			$out[] = array(
				'job_id'  => (int) $row['id'],
				'post_id' => (int) $row['object_id'],
				'title'   => get_the_title( (int) $row['object_id'] ),
				'date'    => mysql_to_rfc3339( $row['updated_at'] ),
			);
		}
		return array_slice( $out, 0, 10 );
	}

	/**
	 * Job progress plus the review once the job is done.
	 *
	 * @param int $job_id Job id.
	 * @return array
	 */
	private function view( $job_id ) {
		$progress = Job_Runner::progress( $job_id );
		return array(
			'job'    => $progress,
			'review' => isset( $progress['status'] ) && 'done' === $progress['status'] ? Improve_Job::review( $job_id ) : null,
		);
	}
}
