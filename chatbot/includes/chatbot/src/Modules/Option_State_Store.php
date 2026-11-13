<?php
/**
 * Module state kept in the `wpbot_master_addons` option.
 *
 * The option name and format stay exactly as WPBot Pro has stored them, so every
 * site keeps its on/off choices and the code that still reads the option directly
 * (add-on lifecycles, menu permissions) keeps working.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Option-backed state store.
 */
final class Option_State_Store implements State_Store {

	/**
	 * Option name (legacy, kept).
	 */
	const OPTION = 'wpbot_master_addons';

	/**
	 * {@inheritDoc}
	 */
	public function get() {
		$state = get_option( self::OPTION );
		return is_array( $state ) ? $state : null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $state State in the legacy format.
	 */
	public function set( array $state ) {
		update_option( self::OPTION, $state );
	}
}
