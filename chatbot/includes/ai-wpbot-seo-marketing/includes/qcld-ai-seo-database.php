<?php
/**
 * Database migration manager.
 *
 * Based on "AI Marketing Expert" by Noor Alam
 * https://wordpress.org/plugins/ai-marketing-expert/
 * Original: includes/class-database.php
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

class Qcld_Wpbot_Ai_Seo_Database {

	/**
	 * Run all database migrations.
	 */
	public static function migrate(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Core tables.
		self::create_core_tables( $charset_collate );

		// Module tables - each module handles its own.
		do_action( 'qcld_wpbot_ai_seo_create_tables', $charset_collate );
	}

	/**
	 * Create core plugin tables.
	 *
	 * @param string $charset_collate Database charset.
	 */
	private static function create_core_tables( string $charset_collate ): void {
		global $wpdb;

		$tables = array();

		// Module registry.
		$tables[] = "CREATE TABLE {$wpdb->prefix}qcld_wpbot_ai_seo_modules (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			module_id varchar(50) NOT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			settings longtext,
			activated_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY idx_module_id (module_id)
		) {$charset_collate};";

		// Activity log.
		$tables[] = "CREATE TABLE {$wpdb->prefix}qcld_wpbot_ai_seo_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			module_id varchar(50) DEFAULT 'core',
			level varchar(20) NOT NULL DEFAULT 'info',
			message text NOT NULL,
			context longtext,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_module (module_id),
			KEY idx_level (level),
			KEY idx_created (created_at)
		) {$charset_collate};";

		// AI usage log (token accounting per generation call).
		$tables[] = "CREATE TABLE {$wpdb->prefix}qcld_wpbot_ai_seo_ai_usage (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			connection_id varchar(64) NOT NULL DEFAULT '',
			connection_name varchar(191) NOT NULL DEFAULT '',
			provider varchar(50) NOT NULL DEFAULT '',
			model varchar(191) NOT NULL DEFAULT '',
			task varchar(50) NOT NULL DEFAULT 'text',
			prompt_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			completion_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
			success tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_created (created_at),
			KEY idx_connection (connection_id)
		) {$charset_collate};";

		// Background AI job queue.

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}
	}

}
