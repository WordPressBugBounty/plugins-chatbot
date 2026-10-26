<?php
/**
 * Module Manager - Discovers, registers, and manages modules.
 *
 * Based on "AI Marketing Expert" by Noor Alam
 * https://wordpress.org/plugins/ai-marketing-expert/
 * Original: includes/class-module-manager.php
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

class Qcld_Wpbot_Ai_Seo_Module_Manager {
	private const CACHE_GROUP = 'qcld_wpbot_ai_seo_module_manager';
	private const CACHE_TTL   = 60;

	/**
	 * Registered modules.
	 *
	 * @var Qcld_Wpbot_Ai_Seo_Module[]
	 */
	private array $modules = array();

	/**
	 * Active module IDs from database.
	 *
	 * @var array
	 */
	private array $active_modules = array();

	private static function get_cache_version(): string {
		$version = wp_cache_get( 'version', self::CACHE_GROUP );
		if ( false === $version ) {
			$version = '1';
			wp_cache_set( 'version', $version, self::CACHE_GROUP, self::CACHE_TTL );
		}

		return (string) $version;
	}

	private static function bump_cache_version(): void {
		wp_cache_set( 'version', (string) microtime( true ), self::CACHE_GROUP, self::CACHE_TTL );
	}

	private static function build_cache_key( string $prefix, array $parts = array() ): string {
		return $prefix . ':' . md5( wp_json_encode( $parts ) . ':' . self::get_cache_version() );
	}

	/**
	 * Discover and register available modules.
	 */
	public function discover_modules(): void {
		// Each module lives in its own folder under includes/.
		foreach ( array( 'content-generator', 'seo' ) as $module_id ) {
			$module_file = QCLD_WPBOT_AI_SEO_PLUGIN_DIR . 'includes/' . $module_id . '/qcld-ai-seo-' . $module_id . '-module.php';

			if ( file_exists( $module_file ) ) {
				require_once $module_file;

				/**
				 * Fires after a module file is loaded.
				 * The module should register itself via this hook.
				 *
				 * @param Qcld_Wpbot_Ai_Seo_Module_Manager $manager Module manager instance.
				 */
				do_action( "qcld_wpbot_ai_seo_load_module_{$module_id}", $this );
			}
		}

		// Allow external modules to register.
		do_action( 'qcld_wpbot_ai_seo_register_modules', $this );

		// Load active states from DB.
		$this->load_active_states();
	}

	/**
	 * Register a module.
	 *
	 * @param Qcld_Wpbot_Ai_Seo_Module $module Module instance.
	 */
	public function register( Qcld_Wpbot_Ai_Seo_Module $module ): void {
		$this->modules[ $module->get_id() ] = $module;
	}

	/**
	 * Initialize all active modules.
	 */
	public function init_active_modules(): void {
		foreach ( $this->modules as $id => $module ) {
			if ( $this->is_active( $id ) ) {
				$module->init();

				// Register table creation hook.
				add_action( 'qcld_wpbot_ai_seo_create_tables', array( $module, 'create_tables' ) );
			}
		}
	}

	/**
	 * Load active states from database.
	 */
	private function load_active_states(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'qcld_wpbot_ai_seo_modules';
		$cache_key = self::build_cache_key( 'active_states', array( 'table' => $table ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			$this->active_modules = $cached;
			return;
		}

		// Check if the table exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			// Table doesn't exist yet, all modules default to active.
			$this->active_modules = array_keys( $this->modules );
			wp_cache_set( $cache_key, $this->active_modules, self::CACHE_GROUP, self::CACHE_TTL );
			return;
		}

		$results = $wpdb->get_results(
			$wpdb->prepare( 'SELECT module_id, is_active FROM %i', $table ),
			ARRAY_A
		);

		if ( empty( $results ) ) {
			// No records yet, all modules are active by default.
			$this->active_modules = array_keys( $this->modules );

			// Insert default records.
			foreach ( $this->modules as $id => $module ) {
				$wpdb->insert(
					$table,
					array(
						'module_id'    => $id,
						'is_active'    => 1,
						'activated_at' => current_time( 'mysql', true ),
						'updated_at'   => current_time( 'mysql', true ),
					),
					array( '%s', '%d', '%s', '%s' )
				);
			}
		} else {
			$db_states = array();
			foreach ( $results as $row ) {
				$db_states[ $row['module_id'] ] = (bool) $row['is_active'];
			}

			// Merge: known modules get DB state, new modules default to active.
			foreach ( $this->modules as $id => $module ) {
				if ( isset( $db_states[ $id ] ) ) {
					if ( $db_states[ $id ] ) {
						$this->active_modules[] = $id;
					}
				} else {
					// New module - activate by default.
					$this->active_modules[] = $id;
					$wpdb->insert(
						$table,
						array(
							'module_id'    => $id,
							'is_active'    => 1,
							'activated_at' => current_time( 'mysql', true ),
							'updated_at'   => current_time( 'mysql', true ),
						),
						array( '%s', '%d', '%s', '%s' )
					);
				}
			}
		}

		wp_cache_set( $cache_key, $this->active_modules, self::CACHE_GROUP, self::CACHE_TTL );
	}

	/**
	 * Check if a module is active.
	 *
	 * @param string $module_id Module identifier.
	 * @return bool
	 */
	public function is_active( string $module_id ): bool {
		return in_array( $module_id, $this->active_modules, true );
	}

	/**
	 * Get a module by ID.
	 *
	 * @param string $module_id Module identifier.
	 * @return Qcld_Wpbot_Ai_Seo_Module|null
	 */
	public function get( string $module_id ): ?Qcld_Wpbot_Ai_Seo_Module {
		return $this->modules[ $module_id ] ?? null;
	}

	/**
	 * Get all active modules.
	 *
	 * @return Qcld_Wpbot_Ai_Seo_Module[]
	 */
	public function get_active(): array {
		return array_filter(
			$this->modules,
			function ( $module ) {
				return $this->is_active( $module->get_id() );
			}
		);
	}

	/**
	 * Get dashboard config for all modules (for React).
	 *
	 * @return array
	 */
	public function get_dashboard_configs(): array {
		if ( empty( $this->modules ) ) {
			$this->discover_modules();
		}
		$this->active_modules = array();
		$this->load_active_states();

		$configs = array();
		foreach ( $this->modules as $module ) {
			$config              = $module->get_dashboard_config();
			$config['is_active'] = $this->is_active( $module->get_id() );
			$configs[]           = $config;
		}
		return $configs;
	}
}
