<?php
/**
 * Abstract Module base class.
 *
 * Every module must extend this class.
 *
 * Based on "AI Marketing Expert" by Noor Alam
 * https://wordpress.org/plugins/ai-marketing-expert/
 * Original: includes/class-module.php
 * Copyright (C) Noor Alam (wpthemespace.com). Licensed GPLv2 or later.
 *
 * Modified by QuantumCloud for WPBot, 2026: Namespace, prefixes and text
 * domain renamed, integrated with WPBot.
 *
 * @package QCLD_Wpbot_Ai_Seo
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Qcld_Wpbot_Ai_Seo_Module {

	/**
	 * Get unique module identifier.
	 *
	 * @return string e.g. 'email-marketing'
	 */
	abstract public function get_id(): string;

	/**
	 * Get human-readable module name.
	 *
	 * @return string e.g. 'Email Marketing'
	 */
	abstract public function get_name(): string;

	/**
	 * Get module description.
	 *
	 * @return string
	 */
	abstract public function get_description(): string;

	/**
	 * Get module icon (dashicon name without 'dashicons-' prefix).
	 *
	 * @return string e.g. 'email-alt'
	 */
	abstract public function get_icon(): string;

	/**
	 * Get module version.
	 *
	 * @return string
	 */
	abstract public function get_version(): string;

	/**
	 * Initialize the module (register hooks, etc.).
	 * Called only if the module is active.
	 */
	abstract public function init(): void;

	/**
	 * Create module database tables.
	 * Called during plugin activation and upgrades.
	 *
	 * @param string $charset_collate Database charset collation.
	 */
	abstract public function create_tables( string $charset_collate ): void;

	/**
	 * Register REST API routes for this module.
	 */
	abstract public function register_routes(): void;

	/**
	 * Check if this module has pro features available.
	 *
	 * @return bool
	 */
	public function has_pro(): bool {
		return qcld_wpbot_ai_seo_module_has_pro( $this->get_id() );
	}

	/**
	 * Get the list of pro features for this module.
	 *
	 * @return array [ 'feature_key' => 'Feature description', ... ]
	 */
	public function get_pro_features(): array {
		return array();
	}

	/**
	 * Save all module settings at once.
	 *
	 * @param array $settings Settings array.
	 * @return bool
	 */
	public function save_settings( array $settings ): bool {
		$option_name = "qcld_wpbot_ai_seo_{$this->get_id()}_settings";
		$updated     = update_option( $option_name, $settings, false );
		qcld_wpbot_ai_seo_clear_settings_cache( array( $option_name ) );
		return $updated;
	}

	/**
	 * Get module configuration for the React dashboard.
	 *
	 * @return array
	 */
	public function get_dashboard_config(): array {
		return array(
			'id'           => $this->get_id(),
			'name'         => $this->get_name(),
			'description'  => $this->get_description(),
			'icon'         => $this->get_icon(),
			'version'      => $this->get_version(),
			'has_pro'      => $this->has_pro(),
			'pro_features' => $this->get_pro_features(),
		);
	}
}
