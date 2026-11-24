<?php
/**
 * What a host plugin (WPBot Free, WPBot Pro, later KBx) tells the Help Center module.
 *
 * Everything host-specific comes through this one interface so the module never
 * checks which plugin it runs in. More methods are added as the module grows
 * (menus, brand); every host adapter is updated in the same round.
 *
 * @package QuantumCloud\HelpCenter
 */

namespace QuantumCloud\HelpCenter\Host;

defined( 'ABSPATH' ) || exit;

/**
 * Host adapter contract.
 */
interface Host_Adapter {

	/**
	 * Short host id, for logs and diagnostics ("free" or "pro").
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Absolute path of the host's main plugin file.
	 *
	 * @return string
	 */
	public function plugin_file();

	/**
	 * Capability needed to switch the Help Center on or off.
	 *
	 * @return string
	 */
	public function modules_capability();
}
