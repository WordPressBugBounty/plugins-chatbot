<?php
/**
 * Defaults for host adapters.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Host;

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
		return __( 'Social', 'chatbot' );
	}

	/**
	 * Default brand (WPBot purple).
	 *
	 * @return array<string,string>
	 */
	public function get_brand() {
		return array(
			'primary'      => '#5b4a9e',
			'primary_dark' => '#463882',
			'primary_soft' => '#efecf8',
			'text'         => '#1d2327',
			'muted'        => '#646970',
			'background'   => '#f6f7f7',
			'radius'       => '12px',
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
	public function render_admin_header() {}

	/**
	 * No license by default: Social stays off.
	 *
	 * @return string
	 */
	public function get_license() {
		return '';
	}

	/**
	 * AI settings URL.
	 *
	 * @return string
	 */
	public function get_ai_settings_url() {
		return admin_url();
	}
}
