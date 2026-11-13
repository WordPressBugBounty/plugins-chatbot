<?php
/**
 * Sensible defaults for host adapters.
 *
 * A host overrides only what differs; an adapter that extends this class
 * keeps working when new interface methods are added.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Host;

defined( 'ABSPATH' ) || exit;

/**
 * Base adapter with neutral defaults.
 */
abstract class Abstract_Host_Adapter implements Host_Adapter {

	/**
	 * {@inheritDoc}
	 */
	public function get_menu_parent() {
		return 'options-general.php';
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
			// Names and links (CB1d, additive): white-label add-ons change them through the host.
			'name'         => 'WPBot',
			'logo'         => '',
			'url'          => '',
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
	public function get_ai_import() {
		return array(
			'active'    => '',
			'providers' => array(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_ai_settings_url() {
		return '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_chat_sources() {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_knowledge_search() {
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_knowledge_writer() {
		return null;
	}
}
