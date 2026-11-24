<?php
/**
 * Tables for visitor identity (C-1) and chat events (C-5), with their own version option so they
 * never touch the sessions schema.
 *
 * - {prefix}qcld_wpbot_chat_visitors: one row per visitor key with what it is linked to.
 * - {prefix}qcld_wpbot_chat_events: one row per event (product view, offer shown, handoff, order
 *   attributed…), read by Store Insights and the daily store metrics; pruned after 180 days.
 *
 * @package QuantumCloud\Chatbot
 */

namespace QuantumCloud\Chatbot\Visitor;

defined( 'ABSPATH' ) || exit;

/**
 * Visitor and event tables.
 */
final class Schema {

	/**
	 * Schema version.
	 */
	const VERSION = 1;

	/**
	 * Option holding the installed version.
	 */
	const OPTION = 'qcld_wpbot_chat_visitor_db';

	/**
	 * Visitors table.
	 *
	 * @return string
	 */
	public static function visitors_table() {
		global $wpdb;
		return $wpdb->prefix . 'qcld_wpbot_chat_visitors';
	}

	/**
	 * Events table.
	 *
	 * @return string
	 */
	public static function events_table() {
		global $wpdb;
		return $wpdb->prefix . 'qcld_wpbot_chat_events';
	}

	/**
	 * Install when the version changed.
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
	 * Create or update the tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset  = $wpdb->get_charset_collate();
		$visitors = self::visitors_table();
		$events   = self::events_table();
		dbDelta(
			"CREATE TABLE {$visitors} (
  visitor_key char(32) NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  insight_visitor char(64) NOT NULL DEFAULT '',
  insight_session varchar(64) NOT NULL DEFAULT '',
  store varchar(20) NOT NULL DEFAULT '',
  customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
  email_hash char(64) NOT NULL DEFAULT '',
  verified_hash char(64) NOT NULL DEFAULT '',
  verified_at datetime NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (visitor_key),
  KEY user_id (user_id),
  KEY insight_visitor (insight_visitor),
  KEY email_hash (email_hash)
) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$events} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  type varchar(40) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  visitor_key char(32) NOT NULL DEFAULT '',
  session_id varchar(128) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  channel varchar(20) NOT NULL DEFAULT 'web',
  object_type varchar(30) NOT NULL DEFAULT '',
  object_id bigint(20) unsigned NOT NULL DEFAULT 0,
  value decimal(14,4) NOT NULL DEFAULT 0,
  data longtext NULL,
  PRIMARY KEY  (id),
  KEY type_time (type, created_at),
  KEY visitor_key (visitor_key),
  KEY object (object_type, object_id)
) {$charset};"
		);
		update_option( self::OPTION, self::VERSION, false );
	}
}
