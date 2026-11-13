<?php
/**
 * WPBot Free adapter for WPBot Social (includes/social/): menu parent,
 * WPBot branding and header, and the run mode.
 *
 * WPBot Free reports Social's "free" mode (Host\Mode_Host): the core module's
 * features run, and the Master module (which never ships in WPBot Free) would
 * not start. WPBot Free has no license; WPBot Pro reports its own mode
 * through its own adapter, and WPBot Free stops running entirely while
 * WPBot Pro is active, so only one host is ever registered.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'QCLD_WPBot_Free_Social_Adapter' ) ) {
	return;
}

/**
 * WPBot Social host adapter for WPBot Free.
 */
class QCLD_WPBot_Free_Social_Adapter extends \QuantumCloud\Social\Host\Abstract_Host_Adapter implements \QuantumCloud\Social\Host\Mode_Host {

	/**
	 * Host id (the same one every module bundled in WPBot Free records).
	 *
	 * @return string
	 */
	public function get_id() {
		return QCLD_WPBot_Free_Core_Adapter::ID;
	}

	/**
	 * Host name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'WPBot';
	}

	/**
	 * WPBot's top-level menu.
	 *
	 * @return string
	 */
	public function get_menu_parent() {
		return QCLD_WPBot_Free_Core_Adapter::instance()->get_menu_parent();
	}

	/**
	 * WPBot admin colours.
	 *
	 * @return array<string,string>
	 */
	public function get_brand() {
		return array_merge( parent::get_brand(), (array) QCLD_WPBot_Free_Core_Adapter::instance()->get_brand() );
	}

	/**
	 * WPBot's admin stylesheets.
	 *
	 * @return array
	 */
	public function get_admin_styles() {
		return QCLD_WPBot_Free_Core_Adapter::instance()->get_admin_styles();
	}

	/**
	 * WPBot's header card.
	 *
	 * @return void
	 */
	public function render_admin_header() {
		QCLD_WPBot_Free_Core_Adapter::instance()->render_admin_header( __( 'Social', 'chatbot' ) );
	}

	/**
	 * Where the AI keys are set.
	 *
	 * @return string
	 */
	public function get_ai_settings_url() {
		return QCLD_WPBot_Free_Core_Adapter::instance()->get_ai_settings_url();
	}

	/**
	 * Social's run mode in WPBot Free: the core features.
	 *
	 * @return string
	 */
	public function get_social_mode() {
		return 'free';
	}
}
