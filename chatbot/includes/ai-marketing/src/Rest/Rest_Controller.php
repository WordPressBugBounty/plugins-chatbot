<?php
/**
 * REST routes under wpbot-marketing/v1. Every route checks a
 * wpbot_marketing_* capability (WPBot Core's permission grid); cookie
 * requests also need the wp_rest nonce (WordPress core). Master routes also
 * check the run mode on the server.
 *
 * The Pro module adds its controllers through the
 * qcld_wpbot_marketing/rest_controllers filter.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Rest;

use QuantumCloud\AiMarketing\Migration\From_Crm;
use QuantumCloud\AiMarketing\Module;
use QuantumCloud\AiMarketing\Settings;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller.
 */
final class Rest_Controller {

	const NS = 'wpbot-marketing/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$view     = self::can( 'wpbot_marketing_view' );
		$settings = self::can( 'wpbot_marketing_settings' );
		$routes   = array(
			array( '/status', WP_REST_Server::READABLE, 'status', $view ),
			array( '/settings', WP_REST_Server::READABLE, 'get_settings', $settings ),
			array( '/settings', WP_REST_Server::CREATABLE, 'save_settings', $settings ),
			array( '/migration', WP_REST_Server::READABLE, 'migration', $settings ),
			array( '/migration', WP_REST_Server::CREATABLE, 'run_migration', $settings ),
		);
		foreach ( $routes as $route ) {
			register_rest_route(
				self::NS,
				$route[0],
				array(
					'methods'             => $route[1],
					'callback'            => array( __CLASS__, $route[2] ),
					'permission_callback' => $route[3],
				)
			);
		}

		/**
		 * Filters extra REST controllers (classes with a static register()).
		 * The Pro module adds its controllers here; each checks Module::master().
		 *
		 * @param string[] $controllers Class names.
		 */
		$controllers = (array) apply_filters( 'qcld_wpbot_marketing/rest_controllers', array( Audience_Controller::class, Capture_Controller::class, Campaigns_Controller::class, Journeys_Controller::class, Insights_Controller::class, Autopilot_Controller::class ) );
		foreach ( $controllers as $controller ) {
			if ( is_string( $controller ) && class_exists( $controller ) && method_exists( $controller, 'register' ) ) {
				call_user_func( array( $controller, 'register' ) );
			}
		}
	}

	/**
	 * Permission callback for a capability.
	 *
	 * @param string $cap Capability.
	 * @return callable
	 */
	public static function can( $cap ) {
		return static function () use ( $cap ) {
			return current_user_can( $cap );
		};
	}

	/**
	 * Permission callback for a Master capability: the capability and Master mode.
	 *
	 * @param string $cap Capability.
	 * @return callable
	 */
	public static function can_master( $cap ) {
		return static function () use ( $cap ) {
			return current_user_can( $cap ) && Module::instance()->master();
		};
	}

	/**
	 * GET /status: mode, Master features, migration state.
	 *
	 * @return array
	 */
	public static function status() {
		$module = Module::instance();
		$host   = $module->host();
		return array(
			'version'     => defined( 'QCLD_WPBOT_MARKETING_VERSION' ) ? QCLD_WPBOT_MARKETING_VERSION : '',
			'mode'        => $module->mode(),
			'features'    => $module->pro_features(),
			'upgrade_url' => $host ? (string) $host->get_upgrade_url() : '',
			'migration'   => From_Crm::report(),
		);
	}

	/**
	 * GET /settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		return Settings::all();
	}

	/**
	 * POST /settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function save_settings( WP_REST_Request $request ) {
		$input = $request->get_json_params();
		return Settings::save( is_array( $input ) ? $input : (array) $request->get_body_params() );
	}

	/**
	 * GET /migration: report and the old tables' counts.
	 *
	 * @return array
	 */
	public static function migration() {
		return From_Crm::overview();
	}

	/**
	 * POST /migration: start (or continue) the copy and run it for a few seconds.
	 *
	 * @return array
	 */
	public static function run_migration() {
		From_Crm::run_now( 15 );
		return From_Crm::overview();
	}
}
