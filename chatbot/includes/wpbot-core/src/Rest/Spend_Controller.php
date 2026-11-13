<?php
/**
 * REST routes of AI spending (the strip, the (i) panel, the report, the settings).
 *
 * Every route needs the spending capability (manage_options by default, filter
 * qcld_wpbot_core/spend_capability). Writes go through the REST cookie nonce (X-WP-Nonce).
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Rest;

use QuantumCloud\WPBotCore\Spend\Features;
use QuantumCloud\WPBotCore\Spend\Prices;
use QuantumCloud\WPBotCore\Spend\Report;
use QuantumCloud\WPBotCore\Spend\Spend;
use QuantumCloud\WPBotCore\Spend\Spend_Settings;
use QuantumCloud\WPBotCore\Spend\Spend_Urls;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * /wpbot-core/v1/spend…
 */
final class Spend_Controller extends Rest_Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$date = array(
			'type'              => 'string',
			'required'          => false,
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => array( __CLASS__, 'valid_date' ),
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/spend',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'allowed' ),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/spend/group/(?P<id>[a-z0-9_]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_group' ),
				'permission_callback' => array( $this, 'allowed' ),
				'args'                => array(
					'id' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => array( __CLASS__, 'valid_group' ),
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/spend/report',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_report' ),
				'permission_callback' => array( $this, 'allowed' ),
				'args'                => array(
					'from' => $date,
					'to'   => $date,
				),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/spend/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'allowed' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'allowed' ),
					'args'                => array(
						'settings' => array(
							'type'              => 'object',
							'required'          => true,
							'validate_callback' => static function ( $value ) {
								return is_array( $value );
							},
						),
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/spend/raise',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'raise' ),
				'permission_callback' => array( $this, 'allowed' ),
				'args'                => array(
					'scope' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static function ( $value ) {
							return 'global' === $value || Features::exists( (string) $value );
						},
					),
				),
			)
		);
	}

	/**
	 * Spending capability.
	 *
	 * @return bool
	 */
	public function allowed() {
		return current_user_can( Spend::capability() );
	}

	/**
	 * GET /spend: the strip.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_status() {
		$status             = Spend::status();
		$status['urls']     = array(
			'settings' => Spend_Urls::settings(),
			'report'   => Spend_Urls::report(),
		);
		$status['sections'] = Report::sections();
		return rest_ensure_response( $status );
	}

	/**
	 * GET /spend/group/{id}: the (i) panel.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_group( WP_REST_Request $request ) {
		return rest_ensure_response( Report::group( (string) $request['id'] ) );
	}

	/**
	 * GET /spend/report: the AI Usage report (default: the current period).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_report( WP_REST_Request $request ) {
		$status = Spend::status();
		$from   = (string) $request['from'];
		$to     = (string) $request['to'];
		$from   = '' !== $from ? $from : $status['period']['start'];
		$to     = '' !== $to ? $to : substr( current_time( 'mysql' ), 0, 10 );
		if ( $to < $from || ( strtotime( $to ) - strtotime( $from ) ) > 400 * DAY_IN_SECONDS ) {
			return new \WP_Error( 'qcld_wpbot_core_spend_range', __( 'Choose a range of at most 400 days, ending after it starts.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$report             = Report::report( $from, $to );
		$report['sections'] = Report::sections();
		return rest_ensure_response( $report );
	}

	/**
	 * GET /spend/settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response( self::settings_view() );
	}

	/**
	 * POST /spend/settings (partial update; Spend_Settings sanitizes every field).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ) {
		$allowed = array( 'period', 'reset_day', 'reset_weekday', 'global', 'limits', 'paused', 'thresholds', 'recipients', 'prefix', 'retention_days', 'retention_months', 'spike_factor', 'spike_minimum', 'weekly_summary', 'summary_weekday' );
		$changes = array_intersect_key( (array) $request['settings'], array_flip( $allowed ) );
		Spend_Settings::update( $changes );
		return rest_ensure_response( self::settings_view() );
	}

	/**
	 * POST /spend/raise: one-click raise for this period.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function raise( WP_REST_Request $request ) {
		$limit = Spend::raise( (string) $request['scope'] );
		if ( is_wp_error( $limit ) ) {
			return $limit;
		}
		return rest_ensure_response(
			array(
				'limit'  => $limit,
				'status' => Spend::status(),
			)
		);
	}

	/**
	 * Settings plus what the screen needs to draw them.
	 *
	 * @return array
	 */
	public static function settings_view() {
		$all = Spend_Settings::all();
		unset( $all['migrated'] );
		return array(
			'settings' => $all,
			'groups'   => Features::all(),
			'billing'  => Prices::billing_urls(),
			'sections' => Report::sections(),
		);
	}

	/**
	 * Y-m-d or empty.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function valid_date( $value ) {
		return '' === $value || null === $value || (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value );
	}

	/**
	 * Known group.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function valid_group( $value ) {
		return Features::exists( sanitize_key( (string) $value ) );
	}
}
