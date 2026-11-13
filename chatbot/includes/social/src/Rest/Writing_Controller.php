<?php
/**
 * Write with AI and Settings › Writing routes under wpbot-social/v1.
 * Writing needs wpbot_social_use; changing settings needs wpbot_social_settings.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Rest;

use QuantumCloud\Social\Writing\Network_Rules;
use QuantumCloud\Social\Writing\Source;
use QuantumCloud\Social\Writing\Writer;
use QuantumCloud\Social\Writing\Writing_Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Writing controller.
 */
final class Writing_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns       = Rest_Controller::NS;
		$use      = Rest_Controller::can( 'wpbot_social_use' );
		$settings = Rest_Controller::can( 'wpbot_social_settings' );
		register_rest_route(
			$ns,
			'/writing',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_settings' ),
					'permission_callback' => $use,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_settings' ),
					'permission_callback' => $settings,
				),
			)
		);
		foreach ( array(
			'/write/estimate' => 'estimate',
			'/write'          => 'write',
			'/write/rewrite'  => 'rewrite',
		) as $route => $callback ) {
			register_rest_route(
				$ns,
				$route,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, $callback ),
					'permission_callback' => $use,
				)
			);
		}
	}

	/**
	 * Settings, rules and the AI status for the screens.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$rules = array();
		foreach ( array_keys( Network_Rules::defaults() ) as $network ) {
			$rules[ $network ] = Network_Rules::get( $network );
		}
		return array(
			'settings'   => Writing_Settings::all(),
			'rules'      => $rules,
			'techniques' => Network_Rules::techniques(),
			'model'      => Writer::model(),
			'multi'      => Writer::multi(),
		);
	}

	/**
	 * Save Settings › Writing.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function save_settings( WP_REST_Request $request ) {
		Writing_Settings::update( (array) $request->get_json_params() );
		return self::get_settings();
	}

	/**
	 * Cost before writing.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function estimate( WP_REST_Request $request ) {
		return Writer::estimate( (array) $request->get_json_params() );
	}

	/**
	 * Write the versions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function write( WP_REST_Request $request ) {
		return Writer::write( (array) $request->get_json_params() );
	}

	/**
	 * Rewrite one network's version.
	 *
	 * @param WP_REST_Request $request Request (writer input plus network).
	 * @return array|WP_Error
	 */
	public static function rewrite( WP_REST_Request $request ) {
		$input   = (array) $request->get_json_params();
		$network = Writer::networks( array( isset( $input['network'] ) ? (string) $input['network'] : '' ) );
		if ( array() === $network ) {
			return new WP_Error( 'qcld_wpbot_social_networks', __( 'Unknown network.', 'chatbot' ), array( 'status' => 400 ) );
		}
		$source = Source::build( $input );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		return Writer::write_one( $network[0], $source, $input );
	}
}
