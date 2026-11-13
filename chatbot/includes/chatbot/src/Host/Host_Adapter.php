<?php
/**
 * What a host plugin (WPBot Free or WPBot Pro) tells the chatbot module.
 *
 * Everything host-specific (edition, folders, capability) comes through this
 * one interface so the module never checks which plugin it runs in.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Host;

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
	 * Absolute path of the host's add-on folder (the base of the stored
	 * Module Manager paths such as "chat-session-addon/wpbot-chat-history-addon.php").
	 * Empty when the host has no add-ons.
	 *
	 * @return string
	 */
	public function modules_dir();

	/**
	 * Capability needed to change modules.
	 *
	 * @return string
	 */
	public function modules_capability();
}
