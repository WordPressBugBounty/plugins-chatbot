<?php
/**
 * WPBot Free's host adapter for the Help Center module.
 *
 * Loaded only after the module has booted (qcld_wpbot_help_center/loaded), so
 * the module's Host_Adapter interface exists.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tells the Help Center module which host it runs in and who may manage it.
 */
final class QCLD_WPBot_Free_Help_Center_Adapter implements \QuantumCloud\HelpCenter\Host\Host_Adapter {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'free';
	}

	/**
	 * {@inheritDoc}
	 */
	public function plugin_file() {
		return dirname( __DIR__ ) . '/qcld-wpwbot.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function modules_capability() {
		return 'manage_options';
	}
}
