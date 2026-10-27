<?php
/**
 * Database tables, created and upgraded once per schema version,
 * whichever host loaded the module.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Storage;

defined( 'ABSPATH' ) || exit;

final class Schema {

	const VERSION = '3';
	const OPTION  = 'qcin_db_version';

	/**
	 * Full table name.
	 *
	 * @param string $name Short name, e.g. 'sessions'.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'qcin_' . $name;
	}

	/**
	 * Short names of every module table.
	 *
	 * @return string[]
	 */
	public static function names() {
		return array( 'sessions', 'pageviews', 'urls', 'selectors', 'clicks', 'heatmap_bins', 'scroll_bins', 'signals', 'ai_results' );
	}

	/**
	 * Full names of tables that features create (prefix {prefix}qcin_).
	 * "Delete all data" empties them with the core tables, and uninstall drops
	 * them. Features create and upgrade their own tables with their own
	 * version option; they never change core tables.
	 *
	 * @return string[]
	 */
	public static function feature_tables() {
		global $wpdb;
		/**
		 * Tables added by features, as full names ({prefix}qcin_...).
		 *
		 * @param string[] $tables Table names.
		 */
		$tables = (array) apply_filters( 'qcin/schema_tables', array() );
		$prefix = $wpdb->prefix . 'qcin_';
		$core   = array_map( array( __CLASS__, 'table' ), self::names() );
		$out    = array();
		foreach ( $tables as $table ) {
			$table = (string) $table;
			if ( 0 === strpos( $table, $prefix ) && preg_match( '/^[A-Za-z0-9_]+$/', $table ) && ! in_array( $table, $core, true ) ) {
				$out[] = $table;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Create or upgrade tables if the stored version is older.
	 */
	public static function maybe_install() {
		if ( get_option( self::OPTION ) === self::VERSION ) {
			return;
		}
		self::install();
	}

	/**
	 * Run dbDelta for all tables.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$s       = self::table( 'sessions' );
		$p       = self::table( 'pageviews' );
		$u       = self::table( 'urls' );
		$sel     = self::table( 'selectors' );
		$c       = self::table( 'clicks' );
		$hb      = self::table( 'heatmap_bins' );
		$sb      = self::table( 'scroll_bins' );
		$sg      = self::table( 'signals' );
		$ai      = self::table( 'ai_results' );

		$tables = array(
			"CREATE TABLE {$s} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  uuid char(36) NOT NULL,
  visitor_hash char(64) DEFAULT NULL,
  started_at datetime NOT NULL,
  last_seen_at datetime NOT NULL,
  duration_s int(10) unsigned NOT NULL DEFAULT 0,
  pageviews smallint(5) unsigned NOT NULL DEFAULT 0,
  device varchar(10) NOT NULL DEFAULT '',
  os varchar(20) NOT NULL DEFAULT '',
  browser varchar(20) NOT NULL DEFAULT '',
  country char(2) NOT NULL DEFAULT '',
  referrer_host varchar(191) NOT NULL DEFAULT '',
  utm_source varchar(100) NOT NULL DEFAULT '',
  utm_medium varchar(100) NOT NULL DEFAULT '',
  utm_campaign varchar(100) NOT NULL DEFAULT '',
  entry_url_id bigint(20) unsigned NOT NULL DEFAULT 0,
  exit_url_id bigint(20) unsigned NOT NULL DEFAULT 0,
  is_returning tinyint(1) NOT NULL DEFAULT 0,
  flags int(10) unsigned NOT NULL DEFAULT 0,
  has_recording tinyint(1) NOT NULL DEFAULT 0,
  bytes bigint(20) unsigned NOT NULL DEFAULT 0,
  favourite tinyint(1) NOT NULL DEFAULT 0,
  status tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  KEY started_at (started_at),
  KEY status_last (status,last_seen_at),
  KEY device_started (device,started_at),
  KEY rec_started (has_recording,started_at),
  KEY flags_started (flags,started_at)
) {$charset};",
			"CREATE TABLE {$p} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  session_id bigint(20) unsigned NOT NULL,
  uuid char(36) NOT NULL,
  url_id bigint(20) unsigned NOT NULL DEFAULT 0,
  seq smallint(5) unsigned NOT NULL DEFAULT 0,
  started_at datetime NOT NULL,
  last_seen_at datetime NOT NULL,
  duration_ms int(10) unsigned NOT NULL DEFAULT 0,
  max_scroll_pct tinyint(3) unsigned NOT NULL DEFAULT 0,
  viewport_w smallint(5) unsigned NOT NULL DEFAULT 0,
  viewport_h smallint(5) unsigned NOT NULL DEFAULT 0,
  chunk_count smallint(5) unsigned NOT NULL DEFAULT 0,
  bytes int(10) unsigned NOT NULL DEFAULT 0,
  doc_h int(10) unsigned NOT NULL DEFAULT 0,
  aggregated tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY uuid (uuid),
  KEY session_id (session_id),
  KEY url_started (url_id,started_at),
  KEY aggregated_last (aggregated,last_seen_at)
) {$charset};",
			"CREATE TABLE {$u} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  url_hash char(32) NOT NULL,
  url varchar(2048) NOT NULL DEFAULT '',
  title varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY url_hash (url_hash)
) {$charset};",
			"CREATE TABLE {$sel} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  hash char(32) NOT NULL,
  selector varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY hash (hash)
) {$charset};",
			"CREATE TABLE {$c} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  pageview_id bigint(20) unsigned NOT NULL DEFAULT 0,
  url_id bigint(20) unsigned NOT NULL DEFAULT 0,
  device varchar(10) NOT NULL DEFAULT '',
  selector_id bigint(20) unsigned NOT NULL DEFAULT 0,
  off_x smallint(5) unsigned NOT NULL DEFAULT 0,
  off_y smallint(5) unsigned NOT NULL DEFAULT 0,
  bin_x tinyint(3) unsigned NOT NULL DEFAULT 0,
  bin_y tinyint(3) unsigned NOT NULL DEFAULT 0,
  page_x int(10) unsigned NOT NULL DEFAULT 0,
  page_y int(10) unsigned NOT NULL DEFAULT 0,
  doc_w int(10) unsigned NOT NULL DEFAULT 0,
  doc_h int(10) unsigned NOT NULL DEFAULT 0,
  is_dead tinyint(1) NOT NULL DEFAULT 0,
  is_rage tinyint(1) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY url_device_created (url_id,device,created_at),
  KEY pageview_id (pageview_id),
  KEY created_at (created_at)
) {$charset};",
			"CREATE TABLE {$hb} (
  url_id bigint(20) unsigned NOT NULL,
  device varchar(10) NOT NULL,
  day date NOT NULL,
  selector_id bigint(20) unsigned NOT NULL,
  bin_x tinyint(3) unsigned NOT NULL,
  bin_y tinyint(3) unsigned NOT NULL,
  clicks int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (url_id,device,day,selector_id,bin_x,bin_y),
  KEY day (day)
) {$charset};",
			"CREATE TABLE {$sb} (
  url_id bigint(20) unsigned NOT NULL,
  device varchar(10) NOT NULL,
  day date NOT NULL,
  depth_bucket tinyint(3) unsigned NOT NULL,
  views int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (url_id,device,day,depth_bucket),
  KEY day (day)
) {$charset};",
			"CREATE TABLE {$sg} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  session_id bigint(20) unsigned NOT NULL DEFAULT 0,
  pageview_id bigint(20) unsigned NOT NULL DEFAULT 0,
  type varchar(16) NOT NULL DEFAULT '',
  ts_ms int(10) unsigned NOT NULL DEFAULT 0,
  selector_id bigint(20) unsigned NOT NULL DEFAULT 0,
  data text,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY session_id (session_id),
  KEY pageview_id (pageview_id),
  KEY type_created (type,created_at)
) {$charset};",
			"CREATE TABLE {$ai} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  session_id bigint(20) unsigned NOT NULL DEFAULT 0,
  subject varchar(64) NOT NULL DEFAULT '',
  feature varchar(32) NOT NULL DEFAULT '',
  prompt_version varchar(16) NOT NULL DEFAULT '',
  model varchar(100) NOT NULL DEFAULT '',
  input_hash char(32) NOT NULL DEFAULT '',
  result longtext,
  input_tokens int(10) unsigned NOT NULL DEFAULT 0,
  output_tokens int(10) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY subject_feature (subject,feature),
  KEY session_id (session_id)
) {$charset};",
		);

		/**
		 * Lets features (e.g. Pro) add CREATE TABLE statements.
		 *
		 * @param string[] $tables  SQL statements.
		 * @param string   $charset Charset collate clause.
		 */
		$tables = apply_filters( 'qcin/schema', $tables, $charset );

		dbDelta( $tables );
		update_option( self::OPTION, self::VERSION, true );
	}

	/**
	 * Drop all module tables (uninstall only).
	 */
	public static function drop() {
		global $wpdb;
		foreach ( self::names() as $name ) {
			$table = self::table( $name );
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		delete_option( self::OPTION );
	}
}
