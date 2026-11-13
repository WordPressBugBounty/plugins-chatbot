<?php
/**
 * Knowledge base tables (chatbot rebuild track).
 *
 * Own migration option, as the wpbot-core rules ask of every track that is not
 * the version owner: qcld_wpbot_core_kb_db_version. Forward-only, safe to run twice.
 *
 * Sources: one row per thing the knowledge base knows (a post, a file, a set of
 * answers), with a hash of its text so unchanged items are never embedded again.
 * Chunks: the pieces that are searched, each with its own vector, the embedding
 * model and vector size (so vectors of different models never mix), and a
 * FULLTEXT index for the fast first pass of a search.
 *
 * Version 2 (chatbot features CB1c, additive): access labels on sources (changed without
 * re-embedding) and on chunks (section-level labels), plus each chunk's heading path and anchor
 * for heading-aware chunks. An empty access means public.
 *
 * @package QuantumCloud\WPBotCore
 */

namespace QuantumCloud\WPBotCore\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Knowledge base schema.
 */
final class Kb_Schema {

	/**
	 * Schema version.
	 */
	const VERSION = '2';

	/**
	 * Migration option of this track.
	 */
	const OPTION = 'qcld_wpbot_core_kb_db_version';

	/**
	 * Sources table (without prefix).
	 */
	const SOURCES = 'qcld_wpbot_core_kb_sources';

	/**
	 * Chunks table (without prefix).
	 */
	const CHUNKS = 'qcld_wpbot_core_kb_chunks';

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
		$sources = self::table( self::SOURCES );
		$chunks  = self::table( self::CHUNKS );
		return array(
			"CREATE TABLE {$sources} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source_type varchar(40) NOT NULL DEFAULT '',
				source_id varchar(191) NOT NULL DEFAULT '',
				title varchar(255) NOT NULL DEFAULT '',
				url varchar(2048) NOT NULL DEFAULT '',
				language varchar(12) NOT NULL DEFAULT '',
				content_hash char(40) NOT NULL DEFAULT '',
				chunks int(10) unsigned NOT NULL DEFAULT 0,
				status varchar(12) NOT NULL DEFAULT 'pending',
				error varchar(255) NOT NULL DEFAULT '',
				access varchar(191) NOT NULL DEFAULT '',
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY source (source_type,source_id),
				KEY status (status),
				KEY access (access)
			) {$charset};",
			"CREATE TABLE {$chunks} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source_ref bigint(20) unsigned NOT NULL DEFAULT 0,
				source_type varchar(40) NOT NULL DEFAULT '',
				chunk_index smallint(5) unsigned NOT NULL DEFAULT 0,
				title varchar(255) NOT NULL DEFAULT '',
				url varchar(2048) NOT NULL DEFAULT '',
				language varchar(12) NOT NULL DEFAULT '',
				content longtext NOT NULL,
				content_hash char(40) NOT NULL DEFAULT '',
				embedding mediumtext NULL,
				embedding_model varchar(120) NOT NULL DEFAULT '',
				dimensions smallint(5) unsigned NOT NULL DEFAULT 0,
				status varchar(12) NOT NULL DEFAULT 'pending',
				access varchar(191) NOT NULL DEFAULT '',
				heading_path varchar(255) NOT NULL DEFAULT '',
				anchor varchar(191) NOT NULL DEFAULT '',
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY source_ref (source_ref),
				KEY access (access),
				KEY status_model (status,embedding_model),
				FULLTEXT KEY search (title,content)
			) {$charset};",
		);
	}

	/**
	 * Table names, for uninstall.
	 *
	 * @return string[]
	 */
	public static function all_tables() {
		return array( self::CHUNKS, self::SOURCES );
	}
}
