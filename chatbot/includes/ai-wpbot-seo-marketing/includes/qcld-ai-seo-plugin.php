<?php
/**
 * Main Plugin class (Singleton).
 *
 * @package QCLD_Wpbot_Ai_Seo
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Qcld_Wpbot_Ai_Seo_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Qcld_Wpbot_Ai_Seo_Plugin|null
	 */
	private static ?Qcld_Wpbot_Ai_Seo_Plugin $instance = null;

	/**
	 * Module manager instance.
	 *
	 * @var Qcld_Wpbot_Ai_Seo_Module_Manager
	 */
	private Qcld_Wpbot_Ai_Seo_Module_Manager $modules;

	/**
	 * Admin handler.
	 *
	 * @var Qcld_Wpbot_Ai_Seo_Admin
	 */
	private Qcld_Wpbot_Ai_Seo_Admin $admin;

	/**
	 * Whether active modules have been initialized.
	 *
	 * @var bool
	 */
	private bool $modules_initialized = false;

	/**
	 * Get singleton instance.
	 *
	 * @return Qcld_Wpbot_Ai_Seo_Plugin
	 */
	public static function instance(): Qcld_Wpbot_Ai_Seo_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - private for singleton.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 */
	public function __wakeup() {
		throw new \Exception( esc_html__( 'Cannot unserialize singleton.', 'chatbot' ) );
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks(): void {
		if ( did_action( 'plugins_loaded' ) ) {
			$this->on_plugins_loaded();
		} else {
			add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		}

		if ( did_action( 'init' ) ) {
			$this->on_init();
		} else {
			add_action( 'init', array( $this, 'on_init' ) );
		}
	}

	/**
	 * Runs on plugins_loaded.
	 */
	public function on_plugins_loaded(): void {
		// Reset AI provider error state when AI keys/settings are saved, and
		// register the "Reset provider status" endpoint.
		Qcld_Wpbot_Ai_Seo_Ai_Provider::register_hooks();

		// Discover modules early so admin and REST handlers can reference them.
		$this->modules = new Qcld_Wpbot_Ai_Seo_Module_Manager();
		$this->modules->discover_modules();

		// Initialize admin if in admin context.
		if ( is_admin() ) {
			$this->admin = new Qcld_Wpbot_Ai_Seo_Admin( $this->modules );
		}

		/**
		 * Fires after the plugin is fully loaded.
		 *
		 * @param Qcld_Wpbot_Ai_Seo_Plugin $plugin Plugin instance.
		 */
		do_action( 'qcld_wpbot_ai_seo_loaded', $this );
	}

	/**
	 * Runs on init.
	 */
	public function on_init(): void {
		// Translations use WPBot's 'chatbot' text domain (loaded by WPBot).

		$this->init_active_modules();

		// Now check database version and run migrations (modules hooks are ready).
		$this->maybe_upgrade_db();

		// Daily retention cleanup (AI usage log + debug log).
		add_action( 'qcld_wpbot_ai_seo_daily_cleanup', array( Qcld_Wpbot_Ai_Seo_Usage_Tracker::class, 'cleanup' ) );
		add_action( 'qcld_wpbot_ai_seo_daily_cleanup', 'qcld_wpbot_ai_seo_prune_logs' );

		// Keep the daily cleanup scheduled (e.g. after WPBot was deactivated and reactivated).
		if ( ! wp_next_scheduled( 'qcld_wpbot_ai_seo_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'qcld_wpbot_ai_seo_daily_cleanup' );
		}
	}

	/**
	 * Check and run database migrations.
	 */
	private function maybe_upgrade_db(): void {
		$current_version = get_option( 'qcld_wpbot_ai_seo_db_version', '0' );

		if ( version_compare( $current_version, QCLD_WPBOT_AI_SEO_DB_VERSION, '<' ) ) {
			Qcld_Wpbot_Ai_Seo_Database::migrate();
			update_option( 'qcld_wpbot_ai_seo_db_version', QCLD_WPBOT_AI_SEO_DB_VERSION );
		}
	}

	/**
	 * Initialize active modules once translations are available.
	 */
	private function init_active_modules(): void {
		if ( $this->modules_initialized ) {
			return;
		}

		$this->modules->init_active_modules();
		$this->modules_initialized = true;
	}

	/**
	 * Get the module manager.
	 *
	 * @return Qcld_Wpbot_Ai_Seo_Module_Manager
	 */
	public function modules(): Qcld_Wpbot_Ai_Seo_Module_Manager {
		return $this->modules;
	}

}
