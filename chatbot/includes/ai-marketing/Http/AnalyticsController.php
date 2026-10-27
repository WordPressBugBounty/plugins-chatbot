<?php
/**
 * REST: dashboard analytics.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Http;

use QuantumCloud\WPBot\CRM\Domain\AudienceResolver;
use QuantumCloud\WPBot\CRM\Domain\ContactRepository;
use QuantumCloud\WPBot\CRM\Domain\EventRepository;
use QuantumCloud\WPBot\CRM\Domain\MessageRepository;
use QuantumCloud\WPBot\CRM\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Contract #1: GET /analytics/overview?days= → {totals:{subscribers,sent,opened,clicks,unsubscribes}}.
 */
class AnalyticsController extends RestController {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/analytics/overview',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'overview' ),
				'permission_callback' => array( $this, 'admin_permission' ),
				'args'                => array(
					'days' => array(
						'type'              => 'integer',
						'default'           => 30,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Totals. `subscribers` = sendable contacts (all time); the rest within the last `days` days.
	 * `opened` counts first opens per message (one open event is logged per message).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function overview( $request ) {
		$days   = min( 365, max( 1, (int) $request->get_param( 'days' ) ) );
		$from   = Dates::days_ago_utc( $days );
		$to     = gmdate( 'Y-m-d H:i:s', time() + 1 );
		$events = ( new EventRepository() )->counts_by_type( $from, $to, array( 'open', 'click', 'unsubscribe' ) );

		return rest_ensure_response(
			array(
				'days'   => $days,
				'totals' => array(
					'subscribers'  => ( new ContactRepository() )->count( AudienceResolver::SENDABLE ),
					'sent'         => ( new MessageRepository() )->count_sent_since( $from, false ),
					'opened'       => (int) $events['open'],
					'clicks'       => (int) $events['click'],
					'unsubscribes' => (int) $events['unsubscribe'],
				),
			)
		);
	}
}
