<?php
/**
 * Post routes under wpbot-social/v1: check, save, list, delete, retry,
 * calendar. Drafts need wpbot_social_use; scheduling needs wpbot_social_publish.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Rest;

use QuantumCloud\Social\Accounts\Account_Store;
use QuantumCloud\Social\Calendar\Calendar_Items;
use QuantumCloud\Social\Media\Public_Media_Check;
use QuantumCloud\Social\Posts\Composer;
use QuantumCloud\Social\Posts\Post_Store;
use QuantumCloud\Social\Posts\Scheduler;
use QuantumCloud\Social\Posts\Target_Store;
use QuantumCloud\Social\Support\Brand_Access;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Posts controller.
 */
final class Posts_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns      = Rest_Controller::NS;
		$view    = Rest_Controller::can( 'wpbot_social_view' );
		$use     = Rest_Controller::can( 'wpbot_social_use' );
		$publish = Rest_Controller::can( 'wpbot_social_publish' );
		$id      = array(
			'id' => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);
		register_rest_route(
			$ns,
			'/images/source',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function ( WP_REST_Request $request ) {
					return \QuantumCloud\Social\Media\Media::from_post( absint( $request->get_param( 'post_id' ) ) );
				},
				'permission_callback' => $use,
			)
		);
		register_rest_route(
			$ns,
			'/images/ai',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( \QuantumCloud\Social\Media\Ai_Images::class, 'view' ),
					'permission_callback' => $use,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => static function ( WP_REST_Request $request ) {
						return \QuantumCloud\Social\Media\Ai_Images::create( (string) $request->get_param( 'prompt' ), (string) $request->get_param( 'style' ), (string) $request->get_param( 'size' ) );
					},
					'permission_callback' => $use,
				),
			)
		);
		register_rest_route(
			$ns,
			'/missed/dismiss',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => static function () {
					delete_option( 'qcld_wpbot_social_missed_notice' );
					return array( 'ok' => true );
				},
				'permission_callback' => $publish,
			)
		);
		register_rest_route(
			$ns,
			'/posts/check',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'check' ),
				'permission_callback' => $use,
			)
		);
		register_rest_route(
			$ns,
			'/posts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_posts' ),
					'permission_callback' => $view,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save' ),
					'permission_callback' => $use,
				),
			)
		);
		register_rest_route(
			$ns,
			'/posts/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_post' ),
					'permission_callback' => $view,
					'args'                => $id,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_post' ),
					'permission_callback' => $publish,
					'args'                => $id,
				),
			)
		);
		register_rest_route(
			$ns,
			'/targets/(?P<id>\d+)/action',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'target_action' ),
				'permission_callback' => $publish,
				'args'                => $id,
			)
		);
		register_rest_route(
			$ns,
			'/calendar',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'calendar' ),
				'permission_callback' => $view,
			)
		);
		register_rest_route(
			$ns,
			'/media-check',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'media_check' ),
				'permission_callback' => $use,
			)
		);
	}

	/**
	 * Checks for a form without saving.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function check( WP_REST_Request $request ) {
		$built = Composer::build( (array) $request->get_json_params() );
		$out   = array();
		foreach ( $built['targets'] as $target ) {
			$out[ $target['account_id'] ] = $target['draft']->to_array();
		}
		return array(
			'checks'   => $built['checks'],
			'problems' => $built['problems'],
			'drafts'   => $out,
		);
	}

	/**
	 * Save a draft or schedule a post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function save( WP_REST_Request $request ) {
		return Composer::save_as_user( (array) $request->get_json_params() );
	}

	/**
	 * Posts list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function list_posts( WP_REST_Request $request ) {
		$result          = Post_Store::query(
			array(
				'status'   => sanitize_key( (string) $request->get_param( 'status' ) ),
				'brand_id' => absint( $request->get_param( 'brand_id' ) ),
				'brands'   => Brand_Access::allowed(),
				'search'   => sanitize_text_field( (string) $request->get_param( 'search' ) ),
				'limit'    => absint( $request->get_param( 'per_page' ) ) ? absint( $request->get_param( 'per_page' ) ) : 20,
				'offset'   => absint( $request->get_param( 'offset' ) ),
			)
		);
		$result['items'] = array_values( array_map( array( __CLASS__, 'post_view' ), $result['items'] ) );
		return $result;
	}

	/**
	 * One post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function get_post( WP_REST_Request $request ) {
		$post = Post_Store::get( (int) $request['id'] );
		if ( $post && ! Brand_Access::can( (int) $post['brand_id'] ) ) {
			return self::forbidden();
		}
		return $post ? self::post_view( $post ) : new WP_Error( 'qcld_wpbot_social_not_found', __( 'Post not found.', 'chatbot' ), array( 'status' => 404 ) );
	}

	/**
	 * Delete a post that is not being sent.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function delete_post( WP_REST_Request $request ) {
		$post = Post_Store::get( (int) $request['id'] );
		if ( $post && ! Brand_Access::can( (int) $post['brand_id'] ) ) {
			return self::forbidden();
		}
		$result = Post_Store::delete( (int) $request['id'] );
		return is_wp_error( $result ) ? $result : array( 'deleted' => (bool) $result );
	}

	/**
	 * Fix & retry, or confirm a post went out.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function target_action( WP_REST_Request $request ) {
		$action = sanitize_key( (string) $request->get_param( 'action' ) );
		$target = Target_Store::get( (int) $request['id'] );
		if ( ! $target || ! in_array( $action, array( 'retry', 'posted', 'skip', 'post_now', 'reschedule' ), true ) ) {
			return new WP_Error( 'qcld_wpbot_social_target', __( 'Nothing to do for this account.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$owner = Post_Store::get( (int) $target['post_id'] );
		if ( $owner && ! Brand_Access::can( (int) $owner['brand_id'] ) ) {
			return self::forbidden();
		}
		$extra = 'reschedule' === $action ? get_gmt_from_date( str_replace( 'T', ' ', (string) $request->get_param( 'when' ) ) . ':00' ) : (string) $request->get_param( 'url' );
		$ok    = Target_Store::owner_action( $target['id'], $action, $extra );
		if ( $ok && 'post_now' === $action ) {
			Scheduler::kick();
		}
		Post_Store::refresh_status( $target['post_id'] );
		return array( 'ok' => $ok );
	}

	/**
	 * Refusal for another brand's post (QA #25).
	 *
	 * @return WP_Error
	 */
	private static function forbidden() {
		return new WP_Error( 'qcld_wpbot_social_brand', __( 'This post belongs to a brand you are not a member of.', 'chatbot' ), array( 'status' => 403 ) );
	}

	/**
	 * Calendar items between two site dates.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function calendar( WP_REST_Request $request ) {
		$from = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $request->get_param( 'from' ) ) ? (string) $request->get_param( 'from' ) : gmdate( 'Y-m-01' );
		$to   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $request->get_param( 'to' ) ) ? (string) $request->get_param( 'to' ) : gmdate( 'Y-m-t' );
		return Calendar_Items::between( get_gmt_from_date( $from . ' 00:00:00' ), get_gmt_from_date( $to . ' 23:59:59' ), absint( $request->get_param( 'brand_id' ) ) );
	}

	/**
	 * Public media check.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function media_check( WP_REST_Request $request ) {
		return Public_Media_Check::result( (bool) $request->get_param( 'fresh' ) );
	}

	/**
	 * Post as the screens show it.
	 *
	 * @param array $post Post with targets.
	 * @return array
	 */
	public static function post_view( array $post ) {
		$targets = array();
		foreach ( $post['targets'] as $target ) {
			$account   = Account_Store::get( $target['account_id'] );
			$targets[] = array(
				'id'            => $target['id'],
				'network'       => $target['network'],
				'account'       => $account ? $account['name'] : __( '(removed account)', 'chatbot' ),
				'state'         => $target['state'],
				'text'          => isset( $target['draft']['text'] ) ? (string) $target['draft']['text'] : '',
				'remote_url'    => $target['remote_url'],
				'error_message' => $target['error_message'],
				'next_attempt'  => $target['next_attempt_at'] ? get_date_from_gmt( $target['next_attempt_at'], 'Y-m-d H:i' ) : '',
			);
		}
		return array(
			'id'          => $post['id'],
			'title'       => $post['title'],
			'text'        => isset( $post['meta']['text'] ) ? $post['meta']['text'] : '',
			'status'      => $post['status'],
			'schedule'    => get_date_from_gmt( (string) $post['schedule_at'], 'Y-m-d H:i' ),
			'brand_id'    => $post['brand_id'],
			'source_type' => (string) $post['source_type'],
			'source_id'   => (int) $post['source_id'],
			'targets'     => $targets,
		);
	}
}
