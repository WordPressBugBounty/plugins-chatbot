<?php
/**
 * Database tables of AI Writer.
 *
 * Same signature approach as WPBot Core: version + hash of definitions;
 * changes only add; running install() twice is safe. Extensions add tables
 * through qcld_wpbot_writer/schema_tables.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades AI Writer's tables.
 */
final class Schema {

	const VERSION = '1';
	const OPTION  = 'qcld_wpbot_writer_db_version';

	const IDEAS      = 'qcld_wpbot_writer_ideas';
	const VOICES     = 'qcld_wpbot_writer_voices';
	const DOCUMENTS  = 'qcld_wpbot_writer_documents';
	const SEARCH_LOG = 'qcld_wpbot_writer_search_log';

	/**
	 * Full table name.
	 *
	 * @param string $name Constant above.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . $name;
	}

	/**
	 * Install or upgrade when the signature changed.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( get_option( self::OPTION ) !== self::signature() ) {
			self::install();
		}
	}

	/**
	 * Current signature.
	 *
	 * @return string
	 */
	public static function signature() {
		return self::VERSION . ':' . md5( implode( '', self::definitions() ) );
	}

	/**
	 * Run dbDelta() for every table.
	 *
	 * @return void
	 */
	public static function install() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( self::definitions() as $sql ) {
			dbDelta( $sql );
		}
		update_option( self::OPTION, self::signature(), true );
	}

	/**
	 * CREATE TABLE statements (dbDelta format), including extensions'.
	 *
	 * @return string[]
	 */
	public static function definitions() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$ideas      = self::table( self::IDEAS );
		$voices     = self::table( self::VOICES );
		$documents  = self::table( self::DOCUMENTS );
		$search_log = self::table( self::SEARCH_LOG );

		$tables = array(
			"CREATE TABLE {$ideas} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  source varchar(40) NOT NULL DEFAULT '',
  source_ref varchar(191) NOT NULL DEFAULT '',
  title varchar(255) NOT NULL DEFAULT '',
  reason text NULL,
  trend varchar(20) NOT NULL DEFAULT '',
  relevance tinyint(3) unsigned NOT NULL DEFAULT 0,
  covered_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
  sample longtext NULL,
  session_count int(10) unsigned NOT NULL DEFAULT 0,
  first_seen datetime NULL,
  last_seen datetime NULL,
  rank_score decimal(8,3) NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'new',
  group_id bigint(20) unsigned NOT NULL DEFAULT 0,
  run_id varchar(40) NOT NULL DEFAULT '',
  topic varchar(191) NULL,
  main_query varchar(191) NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY source_ref (source,source_ref),
  KEY status_rank (status,rank_score)
) {$charset};",
			"CREATE TABLE {$voices} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  stable_key varchar(64) NOT NULL DEFAULT '',
  type varchar(30) NOT NULL DEFAULT 'voice',
  name varchar(191) NOT NULL DEFAULT '',
  profile longtext NULL,
  samples longtext NULL,
  owner_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY stable_key (stable_key),
  KEY type (type)
) {$charset};",
			"CREATE TABLE {$documents} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
  title varchar(255) NOT NULL DEFAULT '',
  text longtext NULL,
  summary text NULL,
  tokens int(10) unsigned NOT NULL DEFAULT 0,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY created_by (created_by)
) {$charset};",
			"CREATE TABLE {$search_log} (
  day date NOT NULL,
  term_hash char(32) NOT NULL,
  term varchar(100) NOT NULL DEFAULT '',
  searches int(10) unsigned NOT NULL DEFAULT 0,
  zero_results int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (day,term_hash)
) {$charset};",
		);

		/**
		 * Add CREATE TABLE statements (dbDelta format) to AI Writer's schema run.
		 *
		 * @param string[] $tables Statements.
		 */
		return (array) apply_filters( 'qcld_wpbot_writer/schema_tables', $tables );
	}

	/**
	 * Core module table names, for uninstall.
	 *
	 * @return string[]
	 */
	public static function core_tables() {
		return array(
			self::table( self::IDEAS ),
			self::table( self::VOICES ),
			self::table( self::DOCUMENTS ),
			self::table( self::SEARCH_LOG ),
		);
	}
}
