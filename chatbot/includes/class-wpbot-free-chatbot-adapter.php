<?php
/**
 * WPBot Free's host adapter for the chatbot core module.
 *
 * @package Chatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tells the chatbot module about WPBot Free.
 */
final class QCLD_WPBot_Free_Chatbot_Adapter implements \QuantumCloud\Chatbot\Host\Host_Adapter {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'free';
	}

	/**
	 * {@inheritDoc}
	 */
	public function modules_dir() {
		return dirname( __DIR__ ) . '/addons';
	}

	/**
	 * {@inheritDoc}
	 */
	public function modules_capability() {
		return 'manage_options';
	}

	/**
	 * The widget's front-end assets: everything under the plugin folder except the
	 * bundled modules in includes/. The chatbot module loads them behind its launcher.
	 * css/wpbot-front-host(.min).css stays on the page: it sizes the search chat button and
	 * hides the floating button ("Disable Floating Button") before the widget loads.
	 *
	 * @return array
	 */
	public function widget_assets() {
		return array(
			'url'     => plugin_dir_url( dirname( __DIR__ ) . '/qcld-wpwbot.php' ),
			'exclude' => array( 'includes/', 'css/wpbot-front-host.' ),
		);
	}
}
