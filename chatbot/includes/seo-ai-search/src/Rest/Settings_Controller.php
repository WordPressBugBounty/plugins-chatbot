<?php
/**
 * Settings, secrets and connection status.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Secrets;
use QuantumCloud\SeoAiSearch\Settings;
use QuantumCloud\WPBotCore\Connections\Analytics;
use QuantumCloud\WPBotCore\Connections\Google;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes: /settings, /secrets, /analytics/properties, /analytics/property.
 */
final class Settings_Controller extends Rest_Base {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'settings' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/secrets',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static function () {
						return rest_ensure_response( Secrets::public_view() );
					},
					'permission_callback' => array( $this, 'can_admin' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_secrets' ),
					'permission_callback' => array( $this, 'can_admin' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/analytics/properties',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function () {
					return $this->respond( Analytics::properties() );
				},
				'permission_callback' => array( $this, 'can_admin' ),
			)
		);
		register_rest_route(
			self::NS,
			'/analytics/property',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'save_property' ),
				'permission_callback' => array( $this, 'can_admin' ),
				'args'                => array(
					'id' => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => static function ( $value ) {
							return '' === $value || (bool) preg_match( '/^properties\/[0-9]{1,20}$/', (string) $value );
						},
					),
				),
			)
		);
	}

	/**
	 * Settings (secrets never included).
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response( Settings::all() );
	}

	/**
	 * Save settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_settings( WP_REST_Request $request ) {
		$changes = (array) $request->get_param( 'settings' );
		/**
		 * Settings the Pro module or other extensions save in their own
		 * option; return the changes left for the core.
		 *
		 * @param array $changes Changes.
		 */
		$changes = (array) apply_filters( 'qcld_wpbot_seo/save_settings', $changes );
		return rest_ensure_response( Settings::update( $changes ) );
	}

	/**
	 * Save or clear secrets (PageSpeed, Bing).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_secrets( WP_REST_Request $request ) {
		$changes = array();
		foreach ( Secrets::IDS as $id ) {
			$value = $request->get_param( $id );
			if ( null !== $value ) {
				$changes[ $id ] = is_string( $value ) ? $value : '';
			}
			if ( true === rest_sanitize_boolean( $request->get_param( 'clear_' . $id ) ) ) {
				$changes[ $id ] = null;
			}
		}
		return rest_ensure_response( Secrets::update( $changes ) );
	}

	/**
	 * Save the Analytics property.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_property( WP_REST_Request $request ) {
		Google::update( array( 'ga_property' => (string) $request->get_param( 'id' ) ) );
		$status = Analytics::status();
		if ( 'connected' === $status['status'] ) {
			// First 90 days of landing-page data in the background.
			$job              = \QuantumCloud\SeoAiSearch\Google\Ga_Pull::start( 90 );
			$status['job_id'] = is_wp_error( $job ) ? 0 : (int) $job;
		}
		return rest_ensure_response( $status );
	}
}
