<?php
/**
 * Keyword data provider settings: choose a provider, save its key
 * (masked; never returned), monthly budget, balance check. Settings level
 * of the calling module.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Rest;

use QuantumCloud\WPBotCore\Keywords\Countries;
use QuantumCloud\WPBotCore\Keywords\Keyword_Data;
use QuantumCloud\WPBotCore\Keywords\Keyword_Keys;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under /keyword-data.
 */
final class Keyword_Data_Controller extends Rest_Controller {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$permission = function ( WP_REST_Request $request ) {
			return $this->can( (string) $request['module'], 'settings' );
		};
		register_rest_route(
			self::NAMESPACE_V1,
			'/keyword-data',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'view' ),
					'permission_callback' => $permission,
					'args'                => array( 'module' => $this->module_arg() ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => $permission,
					'args'                => array(
						'module'   => $this->module_arg(),
						'provider' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
						'key'      => array( 'type' => 'string' ),
						'remove'   => array( 'type' => 'boolean' ),
						'budget'   => array( 'type' => 'number' ),
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/keyword-data/balance',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'balance' ),
				'permission_callback' => $permission,
				'args'                => array( 'module' => $this->module_arg() ),
			)
		);
	}

	/**
	 * Settings view.
	 *
	 * @return \WP_REST_Response
	 */
	public function view() {
		return rest_ensure_response( $this->with_places( Keyword_Keys::view() ) );
	}

	/**
	 * Save provider, key, budget.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		$changes = array();
		if ( null !== $request['provider'] ) {
			$changes['provider'] = (string) $request['provider'];
		}
		if ( $request['remove'] ) {
			$changes['key'] = null;
		} elseif ( null !== $request['key'] ) {
			$changes['key'] = (string) $request['key'];
		}
		if ( null !== $request['budget'] ) {
			$changes['budget'] = (float) $request['budget'];
		}
		$view = Keyword_Keys::save( $changes );
		return is_wp_error( $view ) ? $view : rest_ensure_response( $this->with_places( $view ) );
	}

	/**
	 * Check the balance (tests the key).
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function balance() {
		$result = Keyword_Data::balance();
		return is_wp_error( $result ) ? $result : rest_ensure_response( $this->with_places( array_merge( Keyword_Keys::view(), array( 'checked' => $result ) ) ) );
	}

	/**
	 * Add the countries list and site defaults.
	 *
	 * @param array $view View.
	 * @return array
	 */
	private function with_places( array $view ) {
		$view['countries'] = Countries::names();
		$view['country']   = Countries::clean( '' );
		$view['language']  = Countries::language( '' );
		return $view;
	}
}
