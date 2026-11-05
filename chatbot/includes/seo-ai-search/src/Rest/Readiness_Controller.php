<?php
/**
 * AI search readiness, AI crawler rules, and Local SEO.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Local\Local_Checks;
use QuantumCloud\SeoAiSearch\Local\Locations;
use QuantumCloud\SeoAiSearch\Readiness\Ai_Crawlers;
use QuantumCloud\SeoAiSearch\Readiness\Readiness;
use QuantumCloud\SeoAiSearch\Seo\Seo_Plugins;
use QuantumCloud\SeoAiSearch\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under /readiness, /crawlers, /local.
 */
final class Readiness_Controller extends Rest_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$routes = array(
			'/readiness'                      => array( WP_REST_Server::READABLE, 'pages', 'can_view' ),
			'/readiness/(?P<post_id>\d+)'     => array( WP_REST_Server::READABLE, 'page', 'can_view' ),
			'/readiness/(?P<post_id>\d+)/fix' => array( WP_REST_Server::CREATABLE, 'fix', 'can_use' ),
			'/crawlers'                       => array( WP_REST_Server::READABLE, 'crawlers', 'can_view' ),
			'/crawlers/save'                  => array( WP_REST_Server::CREATABLE, 'save_crawlers', 'can_manage' ),
			'/local'                          => array( WP_REST_Server::READABLE, 'local', 'can_view' ),
			'/local/save'                     => array( WP_REST_Server::CREATABLE, 'save_local', 'can_manage' ),
		);
		foreach ( $routes as $route => $def ) {
			register_rest_route(
				self::NS,
				$route,
				array(
					'methods'             => $def[0],
					'callback'            => array( $this, $def[1] ),
					'permission_callback' => array( $this, $def[2] ),
				)
			);
		}
	}

	/**
	 * Readiness of the main pages.
	 *
	 * @return \WP_REST_Response
	 */
	public function pages() {
		return rest_ensure_response(
			array(
				'pages'  => Readiness::pages( 30 ),
				'writer' => \QuantumCloud\SeoAiSearch\Writer\Writer_Bridge::available(),
			)
		);
	}

	/**
	 * Readiness of one page.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function page( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		$post    = get_post( $post_id );
		if ( ! $post || ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post_id ) ) ) {
			return new WP_Error( 'qcld_wpbot_seo_not_found', __( 'This page was not found.', 'chatbot' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( Readiness::for_post( $post_id ) );
	}

	/**
	 * Fix a content item in AI Writer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function fix( WP_REST_Request $request ) {
		$post_id = (int) $request['post_id'];
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'qcld_wpbot_seo_forbidden', __( 'You cannot edit this page.', 'chatbot' ), array( 'status' => 403 ) );
		}
		$url = Readiness::fix_url( $post_id, sanitize_key( (string) $request->get_param( 'item' ) ) );
		return $this->respond( is_wp_error( $url ) ? $url : array( 'url' => $url ) );
	}

	/**
	 * AI crawlers: rules, what robots.txt blocks now, and text to paste.
	 *
	 * @return \WP_REST_Response
	 */
	public function crawlers() {
		$bots  = array();
		$rules = Ai_Crawlers::rules();
		foreach ( Ai_Crawlers::bots() as $id => $bot ) {
			$bots[] = array(
				'id'      => $id,
				'agent'   => $bot[0],
				'company' => $bot[1],
				'purpose' => $bot[2],
				'rule'    => $rules[ $id ],
			);
		}
		return rest_ensure_response(
			array(
				'bots'     => $bots,
				'blocked'  => Readiness::blocked_bots(),
				'physical' => Ai_Crawlers::physical_file(),
				'lines'    => Ai_Crawlers::lines(),
				'public'   => (bool) get_option( 'blog_public' ),
			)
		);
	}

	/**
	 * Save crawler rules.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_crawlers( WP_REST_Request $request ) {
		Settings::update( array( 'ai_crawlers' => (array) $request->get_param( 'rules' ) ) );
		delete_transient( 'qcld_wpbot_seo_blocked_bots' );
		return $this->crawlers();
	}

	/**
	 * Main location, types, checks.
	 *
	 * @return \WP_REST_Response
	 */
	public function local() {
		$main = Locations::main();
		return rest_ensure_response(
			array(
				'location'  => $main ? $main : Locations::blank(),
				'types'     => Locations::types(),
				'checks'    => $main ? Local_Checks::run( $main ) : null,
				'seoPlugin' => Seo_Plugins::label(),
				'pages'     => array_map(
					static function ( $page ) {
						return array(
							'id'    => $page->ID,
							'title' => $page->post_title,
						);
					},
					get_pages(
						array(
							'post_status' => 'publish',
							'number'      => 200,
						)
					)
				),
			)
		);
	}

	/**
	 * Save the main location.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function save_local( WP_REST_Request $request ) {
		$saved = Locations::save( (array) $request->get_param( 'location' ) );
		return is_wp_error( $saved ) ? $this->respond( $saved ) : $this->local();
	}
}
