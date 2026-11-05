<?php
/**
 * Keyword data in SEO & AI Search: status, cost estimates, volume lookups
 * (job queue), keyword ideas and their hand-offs to AI Writer.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Keywords\Keyword_Ideas;
use QuantumCloud\SeoAiSearch\Keywords\Volumes;
use QuantumCloud\SeoAiSearch\Writer\Writer_Bridge;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under /keywords.
 */
final class Keywords_Controller extends Rest_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$routes = array(
			'/keywords/status'      => array( WP_REST_Server::READABLE, 'status', 'can_view' ),
			'/keywords/estimate'    => array( WP_REST_Server::CREATABLE, 'estimate', 'can_use' ),
			'/keywords/volumes'     => array( WP_REST_Server::CREATABLE, 'volumes', 'can_use' ),
			'/keywords/ideas'       => array( WP_REST_Server::CREATABLE, 'ideas', 'can_use' ),
			'/keywords/ideas/add'   => array( WP_REST_Server::CREATABLE, 'add_idea', 'can_use' ),
			'/keywords/ideas/write' => array( WP_REST_Server::CREATABLE, 'write', 'can_use' ),
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
	 * Provider status (no key details).
	 *
	 * @return \WP_REST_Response
	 */
	public function status() {
		$provider = Volumes::available() ? qcld_wpbot_core_keyword_provider() : null;
		return rest_ensure_response(
			array(
				'available' => Volumes::available(),
				'provider'  => $provider,
				'place'     => Volumes::place(),
				'writer'    => Writer_Bridge::available(),
				'topics'    => class_exists( '\QuantumCloud\AiWriter\Topics\Ideas' ),
			)
		);
	}

	/**
	 * Estimate for volumes (scope) or ideas (seed).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function estimate( WP_REST_Request $request ) {
		if ( null !== $request['seed'] || $request['post_id'] ) {
			return $this->respond( Keyword_Ideas::estimate( Keyword_Ideas::seed( (string) $request['seed'], absint( $request['post_id'] ) ) ) );
		}
		return $this->respond( Volumes::estimate( $this->scope( $request ), $this->limit( $request ) ) );
	}

	/**
	 * Look volumes up (job queue).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function volumes( WP_REST_Request $request ) {
		return $this->respond( Volumes::start( $this->scope( $request ), $this->limit( $request ) ) );
	}

	/**
	 * Keyword ideas.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function ideas( WP_REST_Request $request ) {
		$seed = Keyword_Ideas::seed( (string) $request['seed'], absint( $request['post_id'] ) );
		if ( '' === trim( $seed ) ) {
			return $this->invalid( __( 'keyword', 'chatbot' ) );
		}
		$result = Keyword_Ideas::ideas( $seed );
		if ( ! is_wp_error( $result ) ) {
			$result['seed'] = $seed;
		}
		return $this->respond( $result );
	}

	/**
	 * Add an idea to Topic Finder.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function add_idea( WP_REST_Request $request ) {
		$keyword = sanitize_text_field( (string) $request['keyword'] );
		if ( '' === $keyword ) {
			return $this->invalid( __( 'keyword', 'chatbot' ) );
		}
		return $this->respond( Keyword_Ideas::add_to_topic_finder( $keyword, null === $request['volume'] ? null : (int) $request['volume'] ) );
	}

	/**
	 * Write a page for an idea in AI Writer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function write( WP_REST_Request $request ) {
		$keyword = sanitize_text_field( (string) $request['keyword'] );
		if ( '' === $keyword ) {
			return $this->invalid( __( 'keyword', 'chatbot' ) );
		}
		$url = Writer_Bridge::new_content_url( $keyword, Volumes::brief_text( $keyword ) );
		return $this->respond( is_wp_error( $url ) ? $url : array( 'url' => $url ) );
	}

	/**
	 * Lookup scope.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private function scope( WP_REST_Request $request ) {
		return in_array( $request['scope'], array( 'top', 'areas' ), true ) ? $request['scope'] : 'opportunities';
	}

	/**
	 * Most searches per lookup (Free: 200).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return int
	 */
	private function limit( WP_REST_Request $request ) {
		/**
		 * Most searches in one volume lookup (the Pro module raises it for bulk lookups).
		 *
		 * @param int $max Max.
		 */
		$max = (int) apply_filters( 'qcld_wpbot_seo/keyword_lookup_max', 200 );
		return max( 1, min( $max, $request['limit'] ? (int) $request['limit'] : 200 ) );
	}
}
