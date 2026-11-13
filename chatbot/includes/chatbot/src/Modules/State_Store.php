<?php
/**
 * Where Module Manager's on/off state is kept.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * State storage contract (an option on real sites, an array in tests).
 *
 * The state uses the format WPBot Pro has always stored in `wpbot_master_addons`:
 * array( 'active' => array( path => name ), 'inactive' => array( path => name ) ).
 */
interface State_Store {

	/**
	 * Stored state, or null when nothing is stored yet.
	 *
	 * @return array|null
	 */
	public function get();

	/**
	 * Save the state.
	 *
	 * @param array $state State in the legacy format.
	 * @return void
	 */
	public function set( array $state );
}
