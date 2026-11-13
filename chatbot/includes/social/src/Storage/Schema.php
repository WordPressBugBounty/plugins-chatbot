<?php
/**
 * WPBot Social tables. Forward-only migrations, safe to run twice.
 *
 * @package QuantumCloud\Social
 */

namespace QuantumCloud\Social\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Schema.
 */
final class Schema {

	/**
	 * Current schema version.
	 */
	const VERSION = 1;

	/**
	 * Schema version option.
	 */
	const OPTION = 'qcld_wpbot_social_db_version';

	/**
	 * Table names without the WordPress prefix.
	 *
	 * @var string[]
	 */
	const TABLES = array( 'accounts', 'posts', 'post_targets', 'log' );

	/**
	 * Full table name.
	 *
	 * @param string $name Short name from TABLES.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'qcld_wpbot_social_' . $name;
	}

	/**
	 * Create or update tables when the stored version is older.
	 *
	 * @return void
	 */
	public static function maybe_migrate() {
		if ( (int) get_option( self::OPTION, 0 ) >= self::VERSION ) {
			return;
		}
		self::create();
		update_option( self::OPTION, self::VERSION, false );
	}

	/**
	 * Create tables (dbDelta: safe to run again).
	 *
	 * @return void
	 */
	public static function create() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$sql     = array();

		$sql[] = 'CREATE TABLE ' . self::table( 'accounts' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			network varchar(32) NOT NULL,
			kind varchar(32) NOT NULL DEFAULT '',
			remote_id varchar(191) NOT NULL,
			name varchar(191) NOT NULL DEFAULT '',
			handle varchar(191) NOT NULL DEFAULT '',
			login varchar(191) NOT NULL DEFAULT '',
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			brand_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'ok',
			status_message varchar(255) NOT NULL DEFAULT '',
			auto_share tinyint(1) NOT NULL DEFAULT 0,
			meta longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY network_remote (network, remote_id),
			KEY login (login),
			KEY brand_id (brand_id)
		) {$charset};";

		$sql[] = 'CREATE TABLE ' . self::table( 'posts' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			brand_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(191) NOT NULL DEFAULT '',
			source_type varchar(32) NOT NULL DEFAULT '',
			source_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'draft',
			schedule_at datetime NULL,
			author_id bigint(20) unsigned NOT NULL DEFAULT 0,
			meta longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status_schedule (status, schedule_at),
			KEY brand_id (brand_id)
		) {$charset};";

		$sql[] = 'CREATE TABLE ' . self::table( 'post_targets' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			account_id bigint(20) unsigned NOT NULL,
			network varchar(32) NOT NULL,
			draft longtext NULL,
			state varchar(20) NOT NULL DEFAULT 'scheduled',
			schedule_at datetime NULL,
			next_attempt_at datetime NULL,
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			claim varchar(64) NULL,
			claimed_at datetime NULL,
			idempotency_key char(64) NOT NULL,
			remote_id varchar(191) NOT NULL DEFAULT '',
			remote_url varchar(255) NOT NULL DEFAULT '',
			step_state longtext NULL,
			error_code varchar(40) NOT NULL DEFAULT '',
			error_message varchar(255) NOT NULL DEFAULT '',
			attempt_started_at datetime NULL,
			done_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY state_due (state, next_attempt_at),
			KEY post_id (post_id),
			KEY account_id (account_id)
		) {$charset};";

		$sql[] = 'CREATE TABLE ' . self::table( 'log' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			level varchar(10) NOT NULL DEFAULT 'info',
			event varchar(40) NOT NULL DEFAULT '',
			message varchar(255) NOT NULL DEFAULT '',
			context longtext NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
		) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Drop every Social table and the schema option (opt-in uninstall only).
	 *
	 * @return void
	 */
	public static function drop() {
		global $wpdb;
		foreach ( self::TABLES as $name ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table( $name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table names from code.
		}
		delete_option( self::OPTION );
	}
}
