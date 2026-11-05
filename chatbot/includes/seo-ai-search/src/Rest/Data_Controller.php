<?php
/**
 * Search data for the screens: status, overview, pulls.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Api\Dashboard_Data;
use QuantumCloud\SeoAiSearch\Api\Period;
use QuantumCloud\SeoAiSearch\Google\Data_Store;
use QuantumCloud\SeoAiSearch\Google\Gsc_Pull;
use QuantumCloud\WPBotCore\Connections\Analytics;
use QuantumCloud\WPBotCore\Connections\Google;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes: /status, /overview, /pull, /dashboard.
 */
final class Data_Controller extends Rest_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);
		register_rest_route(
			self::NS,
			'/overview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'overview' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array( 'days' => $this->days_arg() ),
			)
		);
		register_rest_route(
			self::NS,
			'/pull',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'pull' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'mode' => array(
						'type'    => 'string',
						'enum'    => array( 'backfill', 'daily' ),
						'default' => 'backfill',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function ( WP_REST_Request $request ) {
					return rest_ensure_response( Dashboard_Data::get( array( 'days' => (int) $request->get_param( 'days' ) ) ) );
				},
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array( 'days' => $this->days_arg() ),
			)
		);
	}

	/**
	 * Connections and data status (no secrets).
	 *
	 * @return \WP_REST_Response
	 */
	public function status() {
		$google = Google::status();
		$view   = Google::public_view();
		return rest_ensure_response(
			array(
				'google'    => array(
					'status'     => $google['status'],
					'message'    => $google['message'],
					'property'   => $view['property'],
					'missing'    => $view['missing'],
					'has_client' => Google::has_client(),
				),
				'analytics' => Analytics::status() + array( 'property' => $view['ga_property'] ),
				'pull'      => Gsc_Pull::status(),
			)
		);
	}

	/**
	 * Overview numbers: totals with trend, daily series, top pages and searches.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function overview( WP_REST_Request $request ) {
		$days   = (int) $request->get_param( 'days' );
		$period = Period::last( $days );
		$dash   = Dashboard_Data::get( array( 'days' => $days ) );
		$pages  = Data_Store::top( 'page', $period['start'], $period['end'], 10 );
		foreach ( $pages as $index => $page ) {
			$post_id                      = Data_Store::post_for_url( $page['id'] );
			$pages[ $index ]['label']     = esc_url_raw( $page['label'], array( 'http', 'https' ) );
			$pages[ $index ]['post_id']   = $post_id;
			$pages[ $index ]['title']     = $post_id ? get_the_title( $post_id ) : '';
			$pages[ $index ]['edit_link'] = $post_id && current_user_can( 'edit_post', $post_id ) ? get_edit_post_link( $post_id, 'raw' ) : '';
		}
		return rest_ensure_response(
			array(
				'period'  => $period,
				'totals'  => $dash['totals'],
				'series'  => Data_Store::site_series( $period['start'], $period['end'] ),
				'pages'   => $pages,
				'queries' => Data_Store::top( 'query', $period['start'], $period['end'], 10 ),
				'actions' => $dash['top_actions'],
				'movers'  => $dash['movers'],
				'health'  => $dash['connections'],
				'score'   => isset( $dash['health_score'] ) ? $dash['health_score'] : null,
				'pull'    => Gsc_Pull::status(),
				// S14: the Overview has content with no connection at all.
				'site'    => \QuantumCloud\SeoAiSearch\Api\Site_Health::get(),
				'setup'   => \QuantumCloud\SeoAiSearch\Api\Site_Health::setup(),
			)
		);
	}

	/**
	 * Start a Search Console import or update.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function pull( WP_REST_Request $request ) {
		$job = Gsc_Pull::start( (string) $request->get_param( 'mode' ) );
		return $this->respond( is_wp_error( $job ) ? $job : array( 'job_id' => (int) $job ) );
	}
}
