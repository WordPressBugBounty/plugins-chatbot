<?php
/**
 * Defaults for host adapters; hosts override what they need.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Host;

defined( 'ABSPATH' ) || exit;

/**
 * Host adapter with sensible defaults.
 */
abstract class Abstract_Host_Adapter implements Host_Adapter {

	/**
	 * Parent menu slug.
	 *
	 * @return string
	 */
	public function get_menu_parent() {
		return 'tools.php';
	}

	/**
	 * Menu label.
	 *
	 * @return string
	 */
	public function get_menu_title() {
		return __( 'SEO & AI Search', 'chatbot' );
	}

	/**
	 * Place after nothing (appended).
	 *
	 * @return string|string[]
	 */
	public function get_menu_after() {
		return array();
	}

	/**
	 * Default brand: WPBot purple.
	 *
	 * @return array
	 */
	public function get_brand() {
		return array(
			'primary'      => '#6a3df0',
			'primary_dark' => '#4e27c4',
			'primary_soft' => '#f1ecfe',
			'border'       => '#e4e2ee',
			'background'   => '#f6f5fb',
			'text'         => '#1e1b2e',
			'muted'        => '#646178',
			'danger'       => '#c62828',
			'success'      => '#1f7a3a',
			'radius'       => '12px',
		);
	}

	/**
	 * No extra styles.
	 *
	 * @return array
	 */
	public function get_admin_styles() {
		return array();
	}

	/**
	 * Plain page header.
	 *
	 * @return void
	 */
	public function render_admin_header() {
		echo '<h1 class="qcseo-title">' . esc_html__( 'SEO & AI Search Growth', 'chatbot' ) . '</h1>';
	}

	/**
	 * No Pro previews.
	 *
	 * @return array
	 */
	public function get_pro_previews() {
		return array();
	}

	/**
	 * Settings screen of the module.
	 *
	 * @return string
	 */
	public function get_settings_url() {
		return admin_url( 'admin.php?page=wpbot-seo-ai-search&tab=settings' );
	}

	/**
	 * Footer line for the weekly email (Free hosts: a short upgrade note the
	 * owner can hide in Settings). HTML links allowed.
	 *
	 * @return string
	 */
	public function get_email_footer() {
		return '';
	}

	/**
	 * No mailer.
	 *
	 * @return callable|null
	 */
	public function get_mailer() {
		return null;
	}
}
