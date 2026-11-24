<?php
/**
 * Read-only access to the old AI Marketing (CRM) tables, {prefix}wpbot_crm_*.
 *
 * Nothing in this module writes to these tables (owner, 19 Nov 2026: current
 * entries are never deleted).
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Old CRM tables.
 */
final class Legacy_Tables {

	/**
	 * Old table short names.
	 *
	 * @var string[]
	 */
	const TABLES = array( 'contacts', 'groups', 'group_members', 'campaigns', 'messages', 'events', 'templates', 'automations', 'automation_runs' );

	/**
	 * Full old table name.
	 *
	 * @param string $name Short name.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'wpbot_crm_' . $name;
	}

	/**
	 * Whether an old table exists.
	 *
	 * @param string $name Short name.
	 * @return bool
	 */
	public static function exists( $name ) {
		global $wpdb;
		$table = self::table( $name );
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Schema check.
	}

	/**
	 * Whether the old module left any data on this site.
	 *
	 * @return bool
	 */
	public static function present() {
		return self::exists( 'contacts' );
	}

	/**
	 * Row count of an old table (0 when it does not exist).
	 *
	 * @param string $name Short name.
	 * @return int
	 */
	public static function count( $name ) {
		global $wpdb;
		if ( ! self::exists( $name ) ) {
			return 0;
		}
		$table = self::table( $name );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Fixed table name.
	}

	/**
	 * Rows after an id, oldest first.
	 *
	 * @param string $name     Short name.
	 * @param int    $after_id Last id already read.
	 * @param int    $limit    Batch size.
	 * @return array[]
	 */
	public static function rows_after( $name, $after_id, $limit ) {
		global $wpdb;
		if ( ! self::exists( $name ) ) {
			return array();
		}
		$table = self::table( $name );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id > %d ORDER BY id ASC LIMIT %d", (int) $after_id, (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Fixed table name.
	}

	/**
	 * List/tag memberships, in pages (the table has no id column).
	 *
	 * @param int $offset Rows already read.
	 * @param int $limit  Batch size.
	 * @return array[]
	 */
	public static function memberships( $offset, $limit ) {
		global $wpdb;
		if ( ! self::exists( 'group_members' ) ) {
			return array();
		}
		$table = self::table( 'group_members' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY group_id ASC, contact_id ASC LIMIT %d OFFSET %d", (int) $limit, (int) $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Fixed table name.
	}

	/**
	 * The old link-signing secret (for links in emails already sent), or ''.
	 *
	 * @return string
	 */
	public static function secret() {
		$secret = get_option( 'wpbot_crm_secret' );
		return is_string( $secret ) ? $secret : '';
	}

	/**
	 * The old settings option.
	 *
	 * @return array
	 */
	public static function settings() {
		$settings = get_option( 'wpbot_crm_settings', array() );
		return is_array( $settings ) ? $settings : array();
	}
}
