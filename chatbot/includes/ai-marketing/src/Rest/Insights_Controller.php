<?php
/**
 * REST: dashboard numbers and checks.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Rest;

use QuantumCloud\AiMarketing\Checks\Domain;
use QuantumCloud\AiMarketing\Checks\Pre_Send;
use QuantumCloud\AiMarketing\Content\Campaigns;
use QuantumCloud\AiMarketing\Insights\Stats;
use QuantumCloud\AiMarketing\Settings;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Insight and check routes.
 */
final class Insights_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$view = Rest_Controller::can( 'wpbot_marketing_view' );
		foreach ( array(
			array( '/stats', WP_REST_Server::READABLE, 'stats' ),
			array( '/checks/domain', WP_REST_Server::READABLE, 'domain' ),
			array( '/checks/pre-send', WP_REST_Server::CREATABLE, 'pre_send' ),
		) as $route ) {
			register_rest_route(
				Rest_Controller::NS,
				$route[0],
				array(
					'methods'             => $route[1],
					'callback'            => array( __CLASS__, $route[2] ),
					'permission_callback' => $view,
				)
			);
		}
	}

	/**
	 * GET /stats.
	 *
	 * @return array
	 */
	public static function stats() {
		return Stats::overview();
	}

	/**
	 * GET /checks/domain?fresh=1.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function domain( WP_REST_Request $request ) {
		return Domain::check( (bool) $request->get_param( 'fresh' ) );
	}

	/**
	 * POST /checks/pre-send: campaign_id, or subject + preview_text + content_html.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function pre_send( WP_REST_Request $request ) {
		$body  = Audience_Controller::body( $request );
		$email = ! empty( $body['campaign_id'] ) ? Campaigns::email_for( (int) $body['campaign_id'] ) : $body;
		return Pre_Send::check( is_array( $email ) ? $email : array(), array( 'company_address' => (string) Settings::get( 'company_address' ) ) );
	}
}
