<?php
/**
 * Local SEO > Google Business Profile routes (round 3, I): state and
 * locations, turning it on, linking a location, and the health check.
 * Pro adds performance through the filter qcld_wpbot_seo/gbp_view.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Local\Area_Planner;
use QuantumCloud\SeoAiSearch\Local\Gbp;
use QuantumCloud\SeoAiSearch\Local\Reviews;
use QuantumCloud\SeoAiSearch\Research\Research;
use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\WPBotCore\Connections\Business_Profile;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Business Profile controller.
 */
final class Gbp_Controller extends Rest_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$routes = array(
			'/local/gbp'           => array( WP_REST_Server::READABLE, 'view', 'can_view' ),
			'/local/gbp/settings'  => array( WP_REST_Server::CREATABLE, 'settings', 'can_manage' ),
			'/local/gbp/link'      => array( WP_REST_Server::CREATABLE, 'link', 'can_manage' ),
			'/local/reviews'       => array( WP_REST_Server::READABLE, 'reviews', 'can_view' ),
			'/local/reviews/sync'  => array( WP_REST_Server::CREATABLE, 'reviews_sync', 'can_use' ),
			'/local/reviews/draft' => array( WP_REST_Server::CREATABLE, 'review_draft', 'can_use' ),
			'/local/reviews/reply' => array( WP_REST_Server::CREATABLE, 'review_reply', 'can_manage' ),
			'/local/areas'         => array( WP_REST_Server::READABLE, 'areas', 'can_view' ),
			'/local/areas/save'    => array( WP_REST_Server::CREATABLE, 'areas_save', 'can_manage' ),
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
	 * State, locations to choose from, the link and its health check.
	 *
	 * @return \WP_REST_Response
	 */
	public function view() {
		if ( ! Gbp::available() ) {
			return rest_ensure_response( array( 'status' => 'unavailable' ) );
		}
		$state = Business_Profile::status();
		$out   = array(
			'status'    => $state['status'],
			'message'   => $state['message'],
			'link'      => Gbp::link(),
			'locations' => array(),
			'health'    => null,
			'error'     => '',
			'can_edit'  => $this->can_manage(),
			'access'    => Business_Profile::ACCESS_URL,
		);
		if ( 'connected' === $state['status'] || 'pending' === $state['status'] ) {
			$locations = Business_Profile::locations();
			if ( is_wp_error( $locations ) ) {
				$out['error']  = $locations->get_error_message();
				$out['status'] = 'qcld_wpbot_core_gbp_pending' === $locations->get_error_code() ? 'pending' : $out['status'];
			} else {
				$out['status']    = 'connected';
				$out['locations'] = array_map(
					static function ( $loc ) {
						return array(
							'account' => $loc['account'],
							'name'    => $loc['name'],
							'title'   => $loc['title'],
							'city'    => $loc['address']['city'],
						);
					},
					$locations
				);
				if ( $out['link'] ) {
					$health        = Gbp::health();
					$out['health'] = is_wp_error( $health ) ? null : $health;
					$out['error']  = is_wp_error( $health ) ? $health->get_error_message() : '';
				}
			}
		}
		/**
		 * Business Profile view (Pro adds performance).
		 *
		 * @param array $out View.
		 */
		return rest_ensure_response( apply_filters( 'qcld_wpbot_seo/gbp_view', $out ) );
	}

	/**
	 * Reviews (read from Google when older than six hours), totals and the review link.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function reviews( WP_REST_Request $request ) {
		$link = Gbp::available() ? Gbp::link() : null;
		$out  = array(
			'linked'      => (bool) $link,
			'reviews'     => array(),
			'summary'     => Reviews::summary(),
			'error'       => '',
			'review_link' => '',
			'estimate'    => Reviews::estimate(),
			'can_reply'   => $this->can_manage(),
		);
		if ( ! $link ) {
			return rest_ensure_response( $out );
		}
		$sync = Reviews::sync( false );
		if ( is_wp_error( $sync ) ) {
			$out['error'] = $sync->get_error_message();
		}
		$cached             = get_transient( 'qcld_wpbot_core_gbp_locations' );
		$out['review_link'] = '';
		foreach ( is_array( $cached ) ? $cached : array() as $loc ) {
			if ( $loc['name'] === $link['name'] ) {
				$out['review_link'] = Business_Profile::review_link( $loc['place_id'] );
			}
		}
		$out['reviews'] = Reviews::query( array( 'filter' => sanitize_key( (string) $request->get_param( 'filter' ) ) ) );
		$out['summary'] = Reviews::summary();
		/**
		 * Reviews view (Pro adds the monthly trend and request settings).
		 *
		 * @param array $out View.
		 */
		return rest_ensure_response( apply_filters( 'qcld_wpbot_seo/reviews_view', $out ) );
	}

	/**
	 * Read reviews from Google now.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function reviews_sync( WP_REST_Request $request ) {
		$sync = Reviews::sync( true );
		return is_wp_error( $sync ) ? $this->respond( $sync ) : $this->reviews( $request );
	}

	/**
	 * Draft a reply (the screen showed the cost first).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function review_draft( WP_REST_Request $request ) {
		$ready = Research::ai_ready();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		return $this->respond( Reviews::draft( absint( $request->get_param( 'id' ) ), (string) $request->get_param( 'steer' ) ) );
	}

	/**
	 * Post the owner's reply on Google.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function review_reply( WP_REST_Request $request ) {
		return $this->respond( Reviews::post( absint( $request->get_param( 'id' ) ), (string) $request->get_param( 'reply' ) ) );
	}

	/**
	 * Turn Business Profile on or off.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function settings( WP_REST_Request $request ) {
		Settings::update( array( 'gbp_on' => rest_sanitize_boolean( $request->get_param( 'on' ) ) ) );
		return $this->view();
	}

	/**
	 * Link a Business Profile location to the main Local SEO location ('' unlinks).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function link( WP_REST_Request $request ) {
		$name = (string) $request->get_param( 'name' );
		if ( '' !== $name ) {
			$locations = Business_Profile::locations();
			$found     = null;
			foreach ( is_wp_error( $locations ) ? array() : $locations as $loc ) {
				if ( $loc['name'] === $name ) {
					$found = $loc;
				}
			}
			if ( ! $found ) {
				return new WP_Error( 'qcld_wpbot_seo_invalid', __( 'That Business Profile location was not found in your Google account.', 'chatbot' ), array( 'status' => 400 ) );
			}
			Gbp::set_link( 0, $found['account'], $found['name'], $found['title'] );
		} else {
			Gbp::set_link( 0, '', '' );
		}
		return $this->view();
	}

	/**
	 * The service and area planner (round 3, L).
	 *
	 * @return \WP_REST_Response
	 */
	public function areas() {
		$view             = Area_Planner::grid();
		$view['can_edit'] = $this->can_manage();
		/**
		 * Filter the service and area planner view (Pro adds "Write this page").
		 *
		 * @param array $view View.
		 */
		return rest_ensure_response( apply_filters( 'qcld_wpbot_seo/area_view', $view ) );
	}

	/**
	 * Save the owner's services and areas (empty list = use the suggestions).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function areas_save( WP_REST_Request $request ) {
		$split = static function ( $value ) {
			return is_array( $value ) ? $value : preg_split( '/[\r\n,]+/', (string) $value );
		};
		Area_Planner::save(
			array(
				'services' => $split( $request['services'] ),
				'areas'    => $split( $request['areas'] ),
			)
		);
		return $this->areas();
	}
}
