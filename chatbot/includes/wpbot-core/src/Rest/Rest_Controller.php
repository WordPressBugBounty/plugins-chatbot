<?php
/**
 * Shared helpers for WPBot Core REST controllers.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Rest;

use QuantumCloud\WPBotCore\Permissions\Sections;

defined( 'ABSPATH' ) || exit;

/**
 * Base controller: namespace and permission helpers.
 */
abstract class Rest_Controller {

	const NAMESPACE_V1 = 'wpbot-core/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	abstract public function register_routes();

	/**
	 * Route argument for a module id that adopted the permissions grid.
	 *
	 * @return array
	 */
	protected function module_arg() {
		return array(
			'type'              => 'string',
			'required'          => true,
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => static function ( $value ) {
				return in_array( sanitize_key( $value ), Sections::adopted_ids(), true );
			},
		);
	}

	/**
	 * Whether the current user has a level of a module's section.
	 *
	 * @param string $module Module (section) id.
	 * @param string $level  Level.
	 * @return bool
	 */
	protected function can( $module, $level ) {
		$module = sanitize_key( $module );
		return in_array( $module, Sections::adopted_ids(), true ) && current_user_can( Sections::cap( $module, $level ) );
	}
}
