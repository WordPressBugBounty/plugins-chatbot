<?php
/**
 * Tables of the AI spend track (rebuild 6).
 *
 * Own migration option, as the wpbot-core rules ask of every track that is not the version
 * owner: qcld_wpbot_core_spend_db_version. Forward-only, safe to run twice.
 *
 * Version 1:
 * - the usage table (owned by the main track) gains the spend columns: group, task, source,
 *   channel, cached / cache-write / reasoning tokens, audio seconds, characters, price_known;
 * - a daily totals table, so the spend strip and the limits read a few rows instead of every
 *   call, and totals outlive the per-call rows (retention);
 * - existing usage rows get their group, and their daily totals are built once.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Spend;

use QuantumCloud\WPBotCore\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Spend schema.
 */
final class Spend_Schema {

	const VERSION = '1';

	const OPTION = 'qcld_wpbot_core_spend_db_version';

	const DAILY = 'qcld_wpbot_core_usage_daily';

	/**
	 * Hook the installer (after the main track, which creates the usage table).
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 6 );
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
	 * Version plus a hash of the definitions.
	 *
	 * @return string
	 */
	public static function signature() {
		return self::VERSION . ':' . md5( implode( '', self::definitions() ) );
	}

	/**
	 * Run dbDelta, then fill the new columns of older rows (first install only).
	 *
	 * @return void
	 */
	public static function install() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$first = false === get_option( self::OPTION );
		foreach ( self::definitions() as $sql ) {
			dbDelta( $sql );
		}
		if ( $first ) {
			self::backfill();
		}
		update_option( self::OPTION, self::signature(), true );
	}

	/**
	 * Full table name.
	 *
	 * @param string $name Table constant value.
	 * @return string
	 */
	public static function table( $name ) {
		return Schema::table( $name );
	}

	/**
	 * CREATE TABLE statements. The usage table repeats the main track's columns (dbDelta only
	 * adds what is missing, it never drops a column).
	 *
	 * @return string[]
	 */
	public static function definitions() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$usage   = self::table( Schema::USAGE );
		$daily   = self::table( self::DAILY );
		return array(
			"CREATE TABLE {$usage} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  day date NOT NULL,
  module varchar(40) NOT NULL DEFAULT '',
  feature varchar(60) NOT NULL DEFAULT '',
  provider varchar(40) NOT NULL DEFAULT '',
  model varchar(120) NOT NULL DEFAULT '',
  job_id bigint(20) unsigned NOT NULL DEFAULT 0,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  tokens_in int(10) unsigned NOT NULL DEFAULT 0,
  tokens_out int(10) unsigned NOT NULL DEFAULT 0,
  images smallint(5) unsigned NOT NULL DEFAULT 0,
  web_searches smallint(5) unsigned NOT NULL DEFAULT 0,
  cost_estimate decimal(10,6) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  grp varchar(40) NOT NULL DEFAULT '',
  task varchar(60) NOT NULL DEFAULT '',
  source varchar(10) NOT NULL DEFAULT 'routed',
  channel varchar(20) NOT NULL DEFAULT '',
  tokens_cached int(10) unsigned NOT NULL DEFAULT 0,
  tokens_cache_write int(10) unsigned NOT NULL DEFAULT 0,
  tokens_reasoning int(10) unsigned NOT NULL DEFAULT 0,
  audio_seconds int(10) unsigned NOT NULL DEFAULT 0,
  characters int(10) unsigned NOT NULL DEFAULT 0,
  price_known tinyint(1) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  KEY module_day (module,day),
  KEY job_id (job_id),
  KEY grp_day (grp,day),
  KEY created_at (created_at)
) {$charset};",
			"CREATE TABLE {$daily} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  row_hash char(32) NOT NULL DEFAULT '',
  day date NOT NULL,
  grp varchar(40) NOT NULL DEFAULT '',
  module varchar(40) NOT NULL DEFAULT '',
  feature varchar(60) NOT NULL DEFAULT '',
  provider varchar(40) NOT NULL DEFAULT '',
  model varchar(120) NOT NULL DEFAULT '',
  channel varchar(20) NOT NULL DEFAULT '',
  source varchar(10) NOT NULL DEFAULT 'routed',
  calls int(10) unsigned NOT NULL DEFAULT 0,
  tokens_in bigint(20) unsigned NOT NULL DEFAULT 0,
  tokens_out bigint(20) unsigned NOT NULL DEFAULT 0,
  images int(10) unsigned NOT NULL DEFAULT 0,
  unknown_price int(10) unsigned NOT NULL DEFAULT 0,
  cost decimal(14,6) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY row_hash (row_hash),
  KEY day_grp (day,grp)
) {$charset};",
		);
	}

	/**
	 * Give older usage rows their group and build their daily totals (13 months back).
	 *
	 * @return void
	 */
	public static function backfill() {
		global $wpdb;
		$usage = self::table( Schema::USAGE );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own tables, one-time migration.
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$pairs = (array) $wpdb->get_results( "SELECT DISTINCT module, feature FROM {$usage} WHERE grp = ''", ARRAY_A );
		foreach ( $pairs as $pair ) {
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$usage} SET grp = %s, channel = %s WHERE grp = '' AND module = %s AND feature = %s",
					Features::group_of( $pair['module'], $pair['feature'] ),
					Features::channel_of( $pair['module'], $pair['feature'] ),
					$pair['module'],
					$pair['feature']
				)
			);
		}
		$since = gmdate( 'Y-m-d', strtotime( '-13 months' ) );
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names come from code; values are placeholders.
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT day, grp, module, feature, provider, model, channel, source, COUNT(*) AS calls, SUM(tokens_in) AS tokens_in, SUM(tokens_out) AS tokens_out, SUM(images) AS images, SUM(cost_estimate) AS cost FROM {$usage} WHERE day >= %s GROUP BY day, grp, module, feature, provider, model, channel, source",
				$since
			),
			ARRAY_A
		);
		// phpcs:enable
		foreach ( $rows as $row ) {
			Ledger::add_daily( $row, (int) $row['calls'], 0 );
		}
	}

	/**
	 * Tables of this track, for uninstall (the usage table belongs to the main track).
	 *
	 * @return string[]
	 */
	public static function all_tables() {
		return array( self::DAILY );
	}
}
