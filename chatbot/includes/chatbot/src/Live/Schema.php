<?php
/**
 * Live chat tables (live chat rebuild LC1, plan 3.1).
 *
 * - conversations: one row per human conversation, linked to the bot transcript by session id;
 * - events: what the visitor, the agent console and the agent channels sync on (messages, typing,
 *   assigned, transferred, closed, notes, rating);
 * - agent links: an agent channel thread (Slack channel and thread, Telegram topic, Teams thread)
 *   for a conversation.
 * Messages themselves stay in the chat messages table (role user, agent or system), next to the
 * bot's, so the agent always sees the bot history.
 *
 * @package wpbot-chatbot
 */

namespace QuantumCloud\Chatbot\Live;

defined( 'ABSPATH' ) || exit;

/**
 * Live chat schema.
 */
final class Schema {

	/**
	 * Schema version.
	 */
	const VERSION = 2;

	/**
	 * Option holding the installed version.
	 */
	const OPTION = 'qcld_wpbot_chat_live_db_version';

	/**
	 * Conversations table.
	 *
	 * @return string
	 */
	public static function conversations() {
		global $wpdb;
		return $wpdb->prefix . 'qcld_wpbot_chat_conversations';
	}

	/**
	 * Events table.
	 *
	 * @return string
	 */
	public static function events() {
		global $wpdb;
		return $wpdb->prefix . 'qcld_wpbot_chat_events';
	}

	/**
	 * Agent channel links table.
	 *
	 * @return string
	 */
	public static function links() {
		global $wpdb;
		return $wpdb->prefix . 'qcld_wpbot_chat_agent_links';
	}

	/**
	 * Install or upgrade when the stored version is older.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( (int) get_option( self::OPTION, 0 ) < self::VERSION ) {
			self::install();
		}
	}

	/**
	 * Create or update the tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$conv    = self::conversations();
		$events  = self::events();
		$links   = self::links();
		dbDelta(
			"CREATE TABLE {$conv} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  session_id varchar(128) NOT NULL DEFAULT '',
  visitor_key varchar(64) NOT NULL DEFAULT '',
  channel varchar(20) NOT NULL DEFAULT 'web',
  status varchar(12) NOT NULL DEFAULT 'queued',
  department varchar(40) NOT NULL DEFAULT '',
  agent_id bigint(20) unsigned NOT NULL DEFAULT 0,
  visitor_name varchar(100) NOT NULL DEFAULT '',
  visitor_email varchar(190) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  page_url varchar(255) NOT NULL DEFAULT '',
  page_title varchar(255) NOT NULL DEFAULT '',
  reason varchar(40) NOT NULL DEFAULT '',
  flags varchar(40) NOT NULL DEFAULT '',
  queued_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  first_response_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  closed_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  rating tinyint(1) NOT NULL DEFAULT 0,
  rating_comment varchar(255) NOT NULL DEFAULT '',
  last_event_id bigint(20) unsigned NOT NULL DEFAULT 0,
  imported tinyint(1) NOT NULL DEFAULT 0,
  created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  updated datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY status_department (status,department),
  KEY agent_status (agent_id,status),
  KEY session_id (session_id(64)),
  KEY updated (updated)
) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$events} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  conversation_id bigint(20) unsigned NOT NULL DEFAULT 0,
  type varchar(20) NOT NULL DEFAULT '',
  actor varchar(40) NOT NULL DEFAULT '',
  private tinyint(1) NOT NULL DEFAULT 0,
  payload longtext NULL,
  created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY conversation (conversation_id,id)
) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$links} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  conversation_id bigint(20) unsigned NOT NULL DEFAULT 0,
  channel varchar(20) NOT NULL DEFAULT '',
  remote_id varchar(191) NOT NULL DEFAULT '',
  created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY channel_remote (channel,remote_id),
  KEY conversation (conversation_id)
) {$charset};"
		);
		update_option( self::OPTION, self::VERSION, false );
	}

	/**
	 * Drop the tables (uninstall).
	 *
	 * @return void
	 */
	public static function drop() {
		global $wpdb;
		foreach ( array( self::conversations(), self::events(), self::links() ) as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The module's own tables.
		}
		delete_option( self::OPTION );
	}
}
