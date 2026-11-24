<?php
/**
 * WPBot Free as host of WPBot AI Marketing: menu under WPBot, WPBot's look,
 * "essentials" mode, and the chatbot's saved subscribers.
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Host adapter for WPBot AI Marketing in WPBot Free.
 */
class QCLD_WPBot_Free_Marketing_Adapter extends \QuantumCloud\AiMarketing\Host\Abstract_Host_Adapter {

	/**
	 * Host id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'wpbot-free';
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
	 * Under the WPBot menu.
	 *
	 * @return string
	 */
	public function get_menu_parent() {
		return QCLD_WPBot_Free_Core_Adapter::instance()->get_menu_parent();
	}

	/**
	 * WPBot brand values.
	 *
	 * @return array
	 */
	public function get_brand() {
		return array_merge( parent::get_brand(), (array) QCLD_WPBot_Free_Core_Adapter::instance()->get_brand() );
	}

	/**
	 * WPBot admin styles.
	 *
	 * @return array
	 */
	public function get_admin_styles() {
		return QCLD_WPBot_Free_Core_Adapter::instance()->get_admin_styles();
	}

	/**
	 * WPBot's admin header.
	 *
	 * @return void
	 */
	public function render_admin_header() {
		QCLD_WPBot_Free_Core_Adapter::instance()->render_admin_header( __( 'AI Marketing', 'chatbot' ) );
	}

	/**
	 * WPBot Free runs the Essentials (owner, 19 Nov 2026).
	 *
	 * @return string
	 */
	public function get_marketing_mode() {
		return 'essentials';
	}

	/**
	 * WPBot's AI settings.
	 *
	 * @return string
	 */
	public function get_ai_settings_url() {
		return QCLD_WPBot_Free_Core_Adapter::instance()->get_ai_settings_url();
	}

	/**
	 * Where WPBot Pro Master is sold.
	 *
	 * @return string
	 */
	public function get_upgrade_url() {
		return 'https://www.wpbot.pro/pricing/';
	}

	/**
	 * The chatbot's saved subscribers.
	 *
	 * @return QCLD_WPBot_Marketing_Lead_Source
	 */
	public function get_lead_source() {
		return new QCLD_WPBot_Marketing_Lead_Source();
	}
}
