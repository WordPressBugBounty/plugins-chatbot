<?php
/**
 * Tables of the chatbot module. Forward-only, safe to run twice.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Sessions;

defined( 'ABSPATH' ) || exit;

/**
 * Schema and migrations (option qcld_wpbot_chat_db_version).
 */
final class Schema {

	/**
	 * Current schema version.
	 */
	const VERSION = 7;

	/**
	 * Option holding the installed version.
	 */
	const OPTION = 'qcld_wpbot_chat_db_version';

	/**
	 * Messages table name.
	 *
	 * @return string
	 */
	public static function messages_table() {
		global $wpdb;
		return $wpdb->prefix . 'qcld_wpbot_chat_messages';
	}

	/**
	 * Knowledge gaps table name (version 3, chatbot features CB1c).
	 *
	 * @return string
	 */
	public static function gaps_table() {
		global $wpdb;
		return $wpdb->prefix . 'qcld_wpbot_chat_gaps';
	}

	/**
	 * Change log and approval queue table name (version 5, chatbot features CB1d; 4 had a column named with a reserved word and was never created).
	 *
	 * @return string
	 */
	public static function changes_table() {
		global $wpdb;
		return $wpdb->prefix . 'qcld_wpbot_chat_changes';
	}

	/**
	 * Answer cache table name (version 6): saved answers with their question, reuse count and check status.
	 *
	 * @return string
	 */
	public static function answer_cache_table() {
		global $wpdb;
		return $wpdb->prefix . 'qcld_wpbot_chat_answer_cache';
	}

	/**
	 * Create or update tables when the stored version is older.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( (int) get_option( self::OPTION, 0 ) >= self::VERSION ) {
			return;
		}
		self::install();
	}

	/**
	 * Create or update the tables (dbDelta).
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::messages_table();
		dbDelta(
			"CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  session_id varchar(128) NOT NULL DEFAULT '',
  role varchar(20) NOT NULL DEFAULT '',
  channel varchar(20) NOT NULL DEFAULT 'web',
  source varchar(30) NOT NULL DEFAULT '',
  text mediumtext NOT NULL,
  payload longtext NULL,
  model varchar(100) NOT NULL DEFAULT '',
  tokens_in int(10) unsigned NOT NULL DEFAULT 0,
  tokens_out int(10) unsigned NOT NULL DEFAULT 0,
  provider varchar(40) NOT NULL DEFAULT '',
  cost decimal(12,6) NOT NULL DEFAULT 0,
  created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY session_id (session_id(64),id),
  KEY created (created)
) {$charset};"
		);
		$gaps = self::gaps_table();
		dbDelta(
			"CREATE TABLE {$gaps} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  gap_key varchar(191) NOT NULL DEFAULT '',
  question varchar(255) NOT NULL DEFAULT '',
  reason varchar(20) NOT NULL DEFAULT '',
  hits int(10) unsigned NOT NULL DEFAULT 0,
  status varchar(12) NOT NULL DEFAULT 'open',
  answer text NULL,
  language varchar(12) NOT NULL DEFAULT '',
  first_seen datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  last_seen datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY gap_key (gap_key),
  KEY status_hits (status,hits)
) {$charset};"
		);
		$changes = self::changes_table();
		dbDelta(
			"CREATE TABLE {$changes} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  kind varchar(60) NOT NULL DEFAULT '',
  summary varchar(255) NOT NULL DEFAULT '',
  input longtext NULL,
  result longtext NULL,
  undo_data longtext NULL,
  status varchar(12) NOT NULL DEFAULT 'pending',
  via varchar(20) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  decided_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  decided datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY status_id (status,id)
) {$charset};"
		);
		$cache = self::answer_cache_table();
		dbDelta(
			"CREATE TABLE {$cache} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  hash char(64) NOT NULL DEFAULT '',
  question text NOT NULL,
  answer mediumtext NOT NULL,
  provider varchar(40) NOT NULL DEFAULT '',
  model varchar(100) NOT NULL DEFAULT '',
  feature varchar(40) NOT NULL DEFAULT '',
  cost decimal(12,6) NOT NULL DEFAULT 0,
  hits int(10) unsigned NOT NULL DEFAULT 0,
  status varchar(12) NOT NULL DEFAULT 'new',
  note varchar(500) NOT NULL DEFAULT '',
  created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  last_hit datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  reviewed datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY hash (hash),
  KEY status_created (status,created),
  KEY created (created)
) {$charset};"
		);
		$from = (int) get_option( self::OPTION, 0 );
		update_option( self::OPTION, self::VERSION, false );
		if ( $from < 7 ) {
			// Train gets the host's "Questions Not Answered" (schema 3 imported them once; 7 again,
			// for the widget's questions logged only there before round 6). Counts are never doubled.
			\QuantumCloud\Chatbot\Train\Gaps::import_legacy();
		}
	}
}
