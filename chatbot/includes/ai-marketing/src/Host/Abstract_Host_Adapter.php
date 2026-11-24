<?php
/**
 * Defaults for host adapters, so a host only overrides what differs.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Host;

defined( 'ABSPATH' ) || exit;

/**
 * Base host adapter.
 */
abstract class Abstract_Host_Adapter implements Host_Adapter {

	/**
	 * Menu label.
	 *
	 * @return string
	 */
	public function get_menu_title() {
		return __( 'AI Marketing', 'chatbot' );
	}

	/**
	 * Neutral brand values.
	 *
	 * @return array<string,string>
	 */
	public function get_brand() {
		return array(
			'primary'      => '#5b4e96',
			'primary_dark' => '#463b78',
			'primary_soft' => '#efedf7',
			'text'         => '#1d2327',
			'muted'        => '#646970',
			'background'   => '#f6f7f7',
			'radius'       => '8px',
		);
	}

	/**
	 * No extra styles.
	 *
	 * @return array[]
	 */
	public function get_admin_styles() {
		return array();
	}

	/**
	 * No header.
	 *
	 * @return void
	 */
	public function render_admin_header() {
	}

	/**
	 * Essentials unless the host says otherwise.
	 *
	 * @return string
	 */
	public function get_marketing_mode() {
		return 'essentials';
	}

	/**
	 * General settings.
	 *
	 * @return string
	 */
	public function get_ai_settings_url() {
		return admin_url( 'options-general.php' );
	}

	/**
	 * No upgrade page.
	 *
	 * @return string
	 */
	public function get_upgrade_url() {
		return '';
	}

	/**
	 * No chatbot leads.
	 *
	 * @return Lead_Source|null
	 */
	public function get_lead_source() {
		return null;
	}
}
