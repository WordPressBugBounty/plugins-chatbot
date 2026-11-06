<?php
/**
 * REST routes of Regenerate (CR-14): the cost estimate shown on the button
 * (no AI call) and a piece's version history. Generating itself goes
 * through each feature's own route with again=true and the options.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Rest;

defined( 'ABSPATH' ) || exit;

use QuantumCloud\AiWriter\Module;
use QuantumCloud\AiWriter\Regenerate\Article;
use QuantumCloud\AiWriter\Regenerate\History;
use QuantumCloud\AiWriter\Regenerate\Options;
use QuantumCloud\AiWriter\Regenerate\Sections;
use QuantumCloud\AiWriter\Regenerate\Regenerate;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Regenerate controller.
 */
final class Regenerate_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$ns = Settings_Controller::NAMESPACE_V1;
		register_rest_route(
			$ns,
			'/regenerate/estimate',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'estimate' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'kind'  => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'count' => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/regenerate/versions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'versions' ),
				'permission_callback' => array( $this, 'can_read_versions' ),
				'args'                => array(
					'post_id' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'slot'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		// R2: sections of a post, one section again, the whole article (proposed update).
		$post = array(
			'post_id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
			'content' => array(
				'type'    => 'string',
				'default' => '',
			),
			'title'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
		register_rest_route(
			$ns,
			'/regenerate/sections',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sections' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
				'args'                => $post,
			)
		);
		register_rest_route(
			$ns,
			'/regenerate/section',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'section' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
				'args'                => $post + array(
					'index'      => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'regenerate' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);
		register_rest_route(
			$ns,
			'/regenerate/article',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'article' ),
				'permission_callback' => array( $this, 'can_edit_post' ),
				'args'                => array(
					'post_id'    => $post['post_id'],
					'mode'       => array(
						'type'     => 'string',
						'enum'     => Article::MODES,
						'required' => true,
					),
					'step'       => array(
						'type'    => 'string',
						'enum'    => array( 'estimate', 'start' ),
						'default' => 'estimate',
					),
					'regenerate' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);
	}

	/**
	 * Use level and edit rights on the post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit_post( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		return $this->can_use() && $post_id && get_post( $post_id ) && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * POST the sections of a post (from the editor's unsaved content when given).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function sections( WP_REST_Request $request ) {
		$content = '' !== (string) $request['content'] ? (string) $request['content'] : (string) get_post_field( 'post_content', (int) $request['post_id'] );
		$out     = array();
		foreach ( Sections::split( $content ) as $section ) {
			$out[] = array(
				'index'    => $section['index'],
				'heading'  => $section['heading'],
				'words'    => $section['words'],
				'slot'     => $section['slot'],
				'versions' => count( History::get( (int) $request['post_id'], $section['slot'] ) ),
			);
		}
		return rest_ensure_response( array( 'sections' => $out ) );
	}

	/**
	 * POST one section again.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function section( WP_REST_Request $request ) {
		$result = Sections::regenerate( (int) $request['post_id'], (int) $request['index'], (string) $request['title'], (string) $request['content'], Options::sanitise( (array) $request['regenerate'] ) );
		return is_wp_error( $result ) ? Rest_Errors::with_status( $result ) : rest_ensure_response( $result );
	}

	/**
	 * POST the whole article: the estimate (always first), or start the job.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function article( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		$mode    = (string) $request['mode'];
		if ( 'start' !== $request['step'] ) {
			$estimate = Article::estimate( $post_id, $mode );
			return is_wp_error( $estimate ) ? $estimate : rest_ensure_response( array( 'estimate' => $estimate ) );
		}
		$job_id = Article::start( $post_id, $mode, Options::sanitise( (array) $request['regenerate'] ) );
		return is_wp_error( $job_id ) ? $job_id : rest_ensure_response( array( 'job_id' => (int) $job_id ) );
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
	 * Use level, and edit rights on the post the versions belong to.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_read_versions( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		return $this->can_use() && ( ! $post_id || current_user_can( 'edit_post', $post_id ) );
	}

	/**
	 * GET the estimate of a regeneration.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function estimate( WP_REST_Request $request ) {
		return rest_ensure_response( Regenerate::estimate( (string) $request['kind'], max( 1, (int) $request['count'] ) ) );
	}

	/**
	 * GET a piece's versions (newest last).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function versions( WP_REST_Request $request ) {
		return rest_ensure_response( array( 'versions' => History::get( (int) $request['post_id'], (string) $request['slot'] ) ) );
	}
}
