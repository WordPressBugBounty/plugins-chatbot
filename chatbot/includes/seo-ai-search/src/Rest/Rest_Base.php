<?php
/**
 * Shared REST pieces: namespace, permission checks, errors.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Rest;

use QuantumCloud\SeoAiSearch\Module;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Base for the module's controllers.
 */
abstract class Rest_Base {

	/**
	 * REST namespace.
	 */
	const NS = 'qcld-wpbot-seo/v1';

	/**
	 * View level (screens and data).
	 *
	 * @return bool
	 */
	public function can_view() {
		return current_user_can( Module::cap( 'view' ) );
	}

	/**
	 * Use level (scans, fixes, drafts).
	 *
	 * @return bool
	 */
	public function can_use() {
		return current_user_can( Module::cap( 'use' ) );
	}

	/**
	 * Publish or send level (live changes, sending, bulk saves).
	 *
	 * @return bool
	 */
	public function can_publish() {
		return current_user_can( Module::cap( 'publish' ) );
	}

	/**
	 * Manage settings and keys.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( Module::cap( 'settings' ) );
	}

	/**
	 * Manage settings and keys, and an administrator (Google, keys).
	 *
	 * @return bool
	 */
	public function can_admin() {
		return current_user_can( 'manage_options' ) && current_user_can( Module::cap( 'settings' ) );
	}

	/**
	 * Response or error with a status.
	 *
	 * @param mixed $result Result or WP_Error.
	 * @return \WP_REST_Response|WP_Error
	 */
	protected function respond( $result ) {
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( ! is_array( $data ) || empty( $data['status'] ) ) {
				$result->add_data( array_merge( is_array( $data ) ? $data : array(), array( 'status' => 502 ) ) );
			}
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * Argument schema for a number of days.
	 *
	 * @return array
	 */
	protected function days_arg() {
		return array(
			'type'              => 'integer',
			'default'           => 28,
			'minimum'           => 7,
			'maximum'           => 480,
			'sanitize_callback' => 'absint',
		);
	}

	/**
	 * Error for an unknown value.
	 *
	 * @param string $what Field.
	 * @return WP_Error
	 */
	protected function invalid( $what ) {
		/* translators: %s: field name. */
		return new WP_Error( 'qcld_wpbot_seo_invalid', sprintf( __( 'Invalid %s.', 'chatbot' ), $what ), array( 'status' => 400 ) );
	}
}
