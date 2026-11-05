<?php
/**
 * Opportunities: list, rebuild, dismiss, act (AI Writer brief or calendar).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Opportunities\Opportunities;
use QuantumCloud\SeoAiSearch\Writer\Writer_Bridge;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes: /opportunities, /opportunities/refresh, /opportunities/{id}/dismiss, /opportunities/{id}/act.
 */
final class Opportunities_Controller extends Rest_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/opportunities',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_items' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'type'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'status' => array(
						'type'    => 'string',
						'enum'    => array( 'open', 'dismissed', 'done' ),
						'default' => 'open',
					),
					'limit'  => array(
						'type'    => 'integer',
						'default' => 50,
						'minimum' => 1,
						'maximum' => 200,
					),
					'offset' => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/opportunities/refresh',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => function () {
					return rest_ensure_response( array( 'open' => Opportunities::rebuild() ) );
				},
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
		register_rest_route(
			self::NS,
			'/opportunities/topic-gaps',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => function () {
					return $this->respond( \QuantumCloud\SeoAiSearch\Opportunities\Content_Types::find_gaps() );
				},
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
		register_rest_route(
			self::NS,
			'/opportunities/(?P<id>\d+)/(?P<what>dismiss|restore)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'set_status' ),
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
		register_rest_route(
			self::NS,
			'/opportunities/(?P<id>\d+)/act',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'act' ),
				'permission_callback' => array( $this, 'can_use' ),
				'args'                => array(
					'action' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'improve', 'write', 'refresh', 'rewrite_meta', 'calendar' ),
					),
				),
			)
		);
	}

	/**
	 * List.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_items( WP_REST_Request $request ) {
		// Without Search Console nothing triggers a rebuild after an import: keep the list fresh (cheap without Google data).
		$built = (string) get_option( 'qcld_wpbot_seo_opps_built', '' );
		if ( ! \QuantumCloud\WPBotCore\Connections\Google::connected() && ( '' === $built || strtotime( $built ) < time() - HOUR_IN_SECONDS ) ) {
			Opportunities::rebuild();
		}
		$result          = Opportunities::query(
			array(
				'type'   => (string) $request->get_param( 'type' ),
				'status' => (string) $request->get_param( 'status' ),
				'limit'  => (int) $request->get_param( 'limit' ),
				'offset' => (int) $request->get_param( 'offset' ),
			)
		);
		$result['types'] = array();
		foreach ( Opportunities::types() as $id => $type ) {
			$result['types'][] = array(
				'id'    => $id,
				'label' => isset( $type['label'] ) ? (string) $type['label'] : $id,
			);
		}
		$result['writer'] = Writer_Bridge::available();
		$result['built']  = (string) get_option( 'qcld_wpbot_seo_opps_built', '' );
		// R3: works right away; Search Console makes it better (bold note until connected).
		$result['search_console'] = \QuantumCloud\WPBotCore\Connections\Google::connected();
		$result['first_scan']     = \QuantumCloud\SeoAiSearch\Opportunities\First_Scan::status();
		$gaps                     = get_option( \QuantumCloud\SeoAiSearch\Opportunities\Content_Types::GAPS, array() );
		$result['topic_gaps']     = array(
			'generated_at' => is_array( $gaps ) && isset( $gaps['generated_at'] ) ? (string) $gaps['generated_at'] : '',
			'count'        => is_array( $gaps ) && isset( $gaps['gaps'] ) ? count( (array) $gaps['gaps'] ) : 0,
		);
		foreach ( $result['items'] as $index => $item ) {
			$result['items'][ $index ]['can_edit'] = $item['post_id'] && current_user_can( 'edit_post', $item['post_id'] );
		}
		$result['items']    = \QuantumCloud\SeoAiSearch\Keywords\Volumes::attach( $result['items'] );
		$result['keywords'] = \QuantumCloud\SeoAiSearch\Keywords\Volumes::connected();
		return rest_ensure_response( $result );
	}

	/**
	 * Dismiss or restore.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function set_status( WP_REST_Request $request ) {
		$opp = Opportunities::get( (int) $request['id'] );
		if ( ! $opp ) {
			return new WP_Error( 'qcld_wpbot_seo_not_found', __( 'This opportunity no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		Opportunities::set_status( $opp['id'], 'dismiss' === $request['what'] ? 'dismissed' : 'open' );
		return rest_ensure_response( Opportunities::get( $opp['id'] ) );
	}

	/**
	 * Act on an opportunity: a brief URL in AI Writer, or a calendar idea.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function act( WP_REST_Request $request ) {
		$opp = Opportunities::get( (int) $request['id'] );
		if ( ! $opp ) {
			return new WP_Error( 'qcld_wpbot_seo_not_found', __( 'This opportunity no longer exists.', 'chatbot' ), array( 'status' => 404 ) );
		}
		$action = (string) $request->get_param( 'action' );
		if ( 'calendar' === $action ) {
			return $this->respond( Writer_Bridge::add_to_calendar( $opp ) );
		}
		$url = Writer_Bridge::brief_url( $opp, $action );
		return $this->respond( is_wp_error( $url ) ? $url : array( 'url' => $url ) );
	}
}
