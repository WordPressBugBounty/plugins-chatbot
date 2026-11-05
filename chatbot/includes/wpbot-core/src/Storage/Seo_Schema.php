<?php
/**
 * WPBot Core tables added for SEO & AI Search, on their own migration track.
 *
 * Kept apart from Schema (owned by the AI Writer build) so the two branches
 * never compete for one schema version. Forward-only, safe to run twice.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the SEO additions' tables.
 */
final class Seo_Schema {

	/**
	 * Bump when a definition changes.
	 */
	const VERSION = '1';

	/**
	 * Option holding the installed signature.
	 */
	const OPTION = 'qcld_wpbot_core_seo_db_version';

	/**
	 * Keyword data cache (S12).
	 */
	const KEYWORD_DATA = 'qcld_wpbot_core_keyword_data';

	/**
	 * Keyword Vault (S14): keywords the owner researched, plans or ranks for.
	 */
	const VAULT = 'qcld_wpbot_core_vault';

	/**
	 * Hook the installer.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 5 );
	}

	/**
	 * Full table name.
	 *
	 * @param string $name Table constant value.
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
	 * Version plus a hash of the definitions.
	 *
	 * @return string
	 */
	public static function signature() {
		return self::VERSION . ':' . md5( implode( '', self::definitions() ) );
	}

	/**
	 * Run dbDelta for every table.
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
	 * CREATE TABLE statements.
	 *
	 * @return string[]
	 */
	public static function definitions() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$kw      = self::table( self::KEYWORD_DATA );
		$vault   = self::table( self::VAULT );
		return array(
			"CREATE TABLE {$kw} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				keyword_hash char(32) NOT NULL,
				keyword varchar(255) NOT NULL,
				country varchar(8) NOT NULL DEFAULT '',
				language varchar(8) NOT NULL DEFAULT '',
				provider varchar(32) NOT NULL DEFAULT '',
				volume int(11) DEFAULT NULL,
				cpc decimal(10,2) DEFAULT NULL,
				competition decimal(5,4) DEFAULT NULL,
				trend text NULL,
				competition_level varchar(8) NOT NULL DEFAULT '',
				bid_low decimal(10,2) DEFAULT NULL,
				bid_high decimal(10,2) DEFAULT NULL,
				fetched_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY keyword_place (keyword_hash,country,language),
				KEY fetched_at (fetched_at)
			) {$charset};",
			"CREATE TABLE {$vault} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				keyword varchar(191) NOT NULL DEFAULT '',
				keyword_hash char(32) NOT NULL DEFAULT '',
				language varchar(8) NOT NULL DEFAULT '',
				country varchar(8) NOT NULL DEFAULT '',
				status varchar(12) NOT NULL DEFAULT 'researched',
				intent varchar(16) NOT NULL DEFAULT '',
				topic_id bigint(20) unsigned NOT NULL DEFAULT 0,
				post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				demand varchar(8) NOT NULL DEFAULT '',
				difficulty_label varchar(8) NOT NULL DEFAULT '',
				volume int(11) DEFAULT NULL,
				volume_source varchar(30) NOT NULL DEFAULT '',
				cpc decimal(10,2) DEFAULT NULL,
				difficulty tinyint(3) unsigned DEFAULT NULL,
				difficulty_source varchar(30) NOT NULL DEFAULT '',
				rank_position smallint(5) unsigned DEFAULT NULL,
				rank_source varchar(30) NOT NULL DEFAULT '',
				rank_at datetime DEFAULT NULL,
				notes text NULL,
				source varchar(20) NOT NULL DEFAULT 'manual',
				context longtext NULL,
				module varchar(40) NOT NULL DEFAULT '',
				trend varchar(8) NOT NULL DEFAULT '',
				competition varchar(8) NOT NULL DEFAULT '',
				bid_low decimal(10,2) DEFAULT NULL,
				bid_high decimal(10,2) DEFAULT NULL,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY keyword_place (keyword_hash,language,country),
				KEY status (status),
				KEY post_id (post_id),
				KEY topic_id (topic_id)
			) {$charset};",
		);
	}

	/**
	 * Table names, for uninstall.
	 *
	 * @return string[]
	 */
	public static function all_tables() {
		return array( self::KEYWORD_DATA, self::VAULT );
	}
}
