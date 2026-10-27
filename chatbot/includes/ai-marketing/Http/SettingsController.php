<?php
/**
 * General settings REST routes (API-CONTRACT #36–37).
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Http;

use QuantumCloud\WPBot\CRM\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * GET /settings (bare object, API keys masked) and POST /settings (full set, merge-saved).
 */
class SettingsController extends RestController {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
			)
		);
	}

	/**
	 * Current settings with defaults; API keys masked as ••••1234.
	 *
	 * @return array
	 */
	public function show() {
		return $this->typed( Settings::for_response() );
	}

	/**
	 * Save the posted settings. A masked API key keeps the stored key.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function save( $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) || empty( $input ) ) {
			$input = $request->get_body_params();
		}
		if ( ! is_array( $input ) || empty( $input ) ) {
			return $this->error( 'wpbot_crm_no_settings', __( 'No settings were sent.', 'chatbot' ) );
		}

		foreach ( array( 'from_email', 'reply_to' ) as $key ) {
			if ( isset( $input[ $key ] ) && '' !== trim( (string) $input[ $key ] ) && ! is_email( trim( (string) $input[ $key ] ) ) ) {
				/* translators: %s: field label. */
				return $this->error( 'wpbot_crm_invalid_email', sprintf( __( 'Please enter a valid email address for "%s".', 'chatbot' ), 'from_email' === $key ? __( 'From Email', 'chatbot' ) : __( 'Reply-To', 'chatbot' ) ) );
			}
		}

		// Keys added through the settings_defaults filter with boolean defaults are stored as real booleans.
		foreach ( Settings::defaults() as $key => $default ) {
			if ( is_bool( $default ) && array_key_exists( $key, $input ) ) {
				$input[ $key ] = Settings::to_bool( $input[ $key ] );
			}
		}

		return $this->typed( Settings::save( $input ) );
	}

	/**
	 * Cast values whose default is boolean to real booleans.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	protected function typed( array $settings ) {
		foreach ( Settings::defaults() as $key => $default ) {
			if ( is_bool( $default ) && array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = Settings::to_bool( $settings[ $key ] );
			}
		}
		return $settings;
	}
}
