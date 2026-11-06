<?php
/**
 * Defaults for AI Writer host adapters.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Host;

defined( 'ABSPATH' ) || exit;

/**
 * Base adapter with neutral defaults.
 */
abstract class Abstract_Host_Adapter implements Host_Adapter {

	/**
	 * {@inheritDoc}
	 */
	public function get_menu_parent() {
		return 'tools.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_menu_title() {
		return __( 'AI Writer', 'chatbot' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_menu_before() {
		return '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_brand() {
		return array(
			'primary'      => '#5b4e96',
			'primary_dark' => '#463a7a',
			'primary_soft' => '#f3f1fa',
			'border'       => '#e4e0f2',
			'background'   => '#f5f7fd',
			'text'         => '#2d2a3a',
			'muted'        => '#6b6680',
			'danger'       => '#e25563',
			'success'      => '#22b573',
			'radius'       => '16px',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_admin_styles() {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function render_admin_header() {
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_pro_previews() {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_url() {
		return '';
	}
}
