<?php
/**
 * Results & Reports: changes with before-and-after results, summary,
 * weekly email preview and test.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Reports\Weekly_Email;
use QuantumCloud\SeoAiSearch\Results\Change_Log;
use QuantumCloud\SeoAiSearch\Results\Results;
use QuantumCloud\SeoAiSearch\Settings;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under /results and /email.
 */
final class Results_Controller extends Rest_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/results',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'results' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);
		register_rest_route(
			self::NS,
			'/email/preview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function () {
					$facts = Weekly_Email::facts();
					return rest_ensure_response(
						array(
							'news'   => $facts['news'],
							'html'   => Weekly_Email::html( $facts ),
							'to'     => Weekly_Email::recipients(),
							'footer' => '' !== Weekly_Email::footer() || Settings::get( 'hide_email_footer' ),
						)
					);
				},
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/email/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => function () {
					$user = wp_get_current_user();
					return rest_ensure_response(
						array(
							'sent' => Weekly_Email::test( (string) $user->user_email ),
							'to'   => (string) $user->user_email,
						)
					);
				},
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Changes with results, newest first, and the summary.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function results( WP_REST_Request $request ) {
		if ( ! \QuantumCloud\WPBotCore\Connections\Google::connected() ) {
			// No Search Console import triggers the daily update's data; fill the on-page results now (small batch).
			\QuantumCloud\SeoAiSearch\Results\Results::update();
		}
		$list = Change_Log::query(
			array(
				'kind'   => sanitize_key( (string) $request->get_param( 'kind' ) ),
				'limit'  => 100,
				'offset' => absint( $request->get_param( 'offset' ) ),
			)
		);
		return rest_ensure_response(
			array_merge(
				$list,
				array(
					'summary' => Results::summary( 28 ),
					'days'    => (array) Settings::get( 'results_days' ),
				)
			)
		);
	}
}
