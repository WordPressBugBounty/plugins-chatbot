<?php
/**
 * Sensible defaults so hosts only override what they need.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Host;

defined( 'ABSPATH' ) || exit;

abstract class Abstract_Host_Adapter implements Host_Adapter {

	/**
	 * {@inheritDoc}
	 */
	public function get_capability() {
		return 'manage_options';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_menu_parent() {
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_menu_title() {
		return __( 'Insights', 'qcin-textdomain' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_menu_before() {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_ai_import() {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_brand() {
		return array();
	}

	/**
	 * Credit line and optional upgrade line at the bottom of email reports.
	 * Not part of Host_Adapter, so older adapters keep working; the module
	 * checks for it with method_exists().
	 *
	 * Keys (all optional): credit_text (e.g. "Sent by Site Insight in WPBot"),
	 * credit_url, credit_label (link text), promo_text, promo_url.
	 *
	 * @return array<string,string>
	 */
	public function get_report_branding() {
		return array();
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
		printf(
			'<div class="qcin-default-header"><h1>%s</h1></div>',
			esc_html( $this->get_name() . ' Insights' )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function render_admin_footer() {
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_url() {
		return admin_url();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_ai_settings_url() {
		return $this->get_settings_url();
	}
}
