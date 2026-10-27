<?php
/**
 * Database schema for the CRM module.
 *
 * @package QuantumCloud\WPBot\CRM
 */

namespace QuantumCloud\WPBot\CRM\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Creates/upgrades the wpbot_crm_* tables with dbDelta and owns the module's install options.
 */
final class Schema {

	const VERSION        = '1.0.0';
	const VERSION_OPTION = 'wpbot_crm_db_version';
	const SECRET_OPTION  = 'wpbot_crm_secret';

	/**
	 * Table suffixes (without "{$wpdb->prefix}wpbot_crm_").
	 *
	 * @var string[]
	 */
	const TABLES = array( 'contacts', 'groups', 'group_members', 'campaigns', 'messages', 'events', 'templates', 'automations', 'automation_runs' );

	/**
	 * Full table name for a suffix, e.g. table( 'contacts' ) → wp_wpbot_crm_contacts.
	 *
	 * @param string $name Table suffix.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'wpbot_crm_' . $name;
	}

	/**
	 * Install or upgrade when the stored version differs from VERSION.
	 *
	 * @return bool True when an install/upgrade ran.
	 */
	public static function maybe_upgrade() {
		if ( self::VERSION === get_option( self::VERSION_OPTION ) ) {
			return false;
		}
		self::install();
		return true;
	}

	/**
	 * Create/upgrade all tables, ensure the HMAC secret, seed built-in templates.
	 */
	public static function install() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::sql() );

		self::secret();

		$seeder = __NAMESPACE__ . '\\TemplateSeeder';
		if ( class_exists( $seeder ) && method_exists( $seeder, 'seed' ) ) {
			call_user_func( array( $seeder, 'seed' ) );
		}

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/**
	 * The site's HMAC secret (64 chars), created on first use.
	 *
	 * @return string
	 */
	public static function secret() {
		$secret = get_option( self::SECRET_OPTION );
		if ( ! is_string( $secret ) || strlen( $secret ) < 32 ) {
			$secret = wp_generate_password( 64, false, false );
			update_option( self::SECRET_OPTION, $secret, false );
		}
		return $secret;
	}

	/**
	 * Whether every table exists.
	 *
	 * @return bool
	 */
	public static function tables_exist() {
		global $wpdb;
		foreach ( self::TABLES as $name ) {
			$table = self::table( $name );
			if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Drop every CRM table and delete every wpbot_crm_* option. Used on uninstall.
	 */
	public static function drop_all() {
		global $wpdb;
		foreach ( self::TABLES as $name ) {
			$table = self::table( $name );
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
		}
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( 'wpbot_crm_' ) . '%',
				$wpdb->esc_like( '_transient_wpbot_crm_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_wpbot_crm_' ) . '%'
			)
		);
		wp_cache_flush();
	}

	/**
	 * CREATE TABLE statements in dbDelta format.
	 *
	 * @return string[]
	 */
	public static function sql() {
		global $wpdb;
		$c = $wpdb->get_charset_collate();
		$e = 'ENGINE=InnoDB';

		$contacts      = self::table( 'contacts' );
		$groups        = self::table( 'groups' );
		$group_members = self::table( 'group_members' );
		$campaigns     = self::table( 'campaigns' );
		$messages      = self::table( 'messages' );
		$events        = self::table( 'events' );
		$templates     = self::table( 'templates' );
		$automations   = self::table( 'automations' );
		$runs          = self::table( 'automation_runs' );

		$sql = array();

		$sql[] = "CREATE TABLE {$contacts} (
id bigint(20) unsigned NOT NULL auto_increment,
email varchar(190) NOT NULL,
first_name varchar(100) NOT NULL default '',
last_name varchar(100) NOT NULL default '',
phone varchar(50) NOT NULL default '',
company varchar(190) NOT NULL default '',
website varchar(255) NOT NULL default '',
status varchar(20) NOT NULL default 'subscribed',
source varchar(50) NOT NULL default '',
attributes longtext NULL,
token char(32) NOT NULL,
confirmed_at datetime NULL default NULL,
unsubscribed_at datetime NULL default NULL,
last_activity_at datetime NULL default NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY email (email),
UNIQUE KEY token (token),
KEY status (status),
KEY created_at (created_at)
) {$e} {$c};";

		$sql[] = "CREATE TABLE {$groups} (
id bigint(20) unsigned NOT NULL auto_increment,
type varchar(10) NOT NULL default 'list',
name varchar(190) NOT NULL default '',
slug varchar(190) NOT NULL default '',
description text NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY type_slug (type,slug)
) {$e} {$c};";

		$sql[] = "CREATE TABLE {$group_members} (
group_id bigint(20) unsigned NOT NULL,
contact_id bigint(20) unsigned NOT NULL,
added_at datetime NOT NULL,
PRIMARY KEY  (group_id,contact_id),
KEY contact_id (contact_id)
) {$e} {$c};";

		$sql[] = "CREATE TABLE {$campaigns} (
id bigint(20) unsigned NOT NULL auto_increment,
name varchar(255) NOT NULL default '',
subject varchar(255) NOT NULL default '',
preview_text varchar(255) NOT NULL default '',
from_name varchar(190) NOT NULL default '',
from_email varchar(190) NOT NULL default '',
reply_to varchar(190) NOT NULL default '',
content_html longtext NULL,
template_id bigint(20) unsigned NULL default NULL,
audience longtext NULL,
settings longtext NULL,
status varchar(20) NOT NULL default 'draft',
scheduled_at datetime NULL default NULL,
started_at datetime NULL default NULL,
finished_at datetime NULL default NULL,
recipients int(10) unsigned NOT NULL default 0,
sent int(10) unsigned NOT NULL default 0,
failed int(10) unsigned NOT NULL default 0,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY status_scheduled (status,scheduled_at),
KEY created_at (created_at)
) {$e} {$c};";

		$sql[] = "CREATE TABLE {$messages} (
id bigint(20) unsigned NOT NULL auto_increment,
campaign_id bigint(20) unsigned NULL default NULL,
automation_id bigint(20) unsigned NULL default NULL,
step_key varchar(40) NULL default NULL,
contact_id bigint(20) unsigned NOT NULL,
status varchar(10) NOT NULL default 'queued',
error text NULL,
queued_at datetime NOT NULL,
sent_at datetime NULL default NULL,
first_open_at datetime NULL default NULL,
opens int(10) unsigned NOT NULL default 0,
first_click_at datetime NULL default NULL,
clicks int(10) unsigned NOT NULL default 0,
PRIMARY KEY  (id),
UNIQUE KEY campaign_contact (campaign_id,contact_id),
KEY status_queued (status,queued_at),
KEY contact_id (contact_id),
KEY automation_id (automation_id),
KEY sent_at (sent_at)
) {$e} {$c};";

		$sql[] = "CREATE TABLE {$events} (
id bigint(20) unsigned NOT NULL auto_increment,
contact_id bigint(20) unsigned NOT NULL default 0,
message_id bigint(20) unsigned NULL default NULL,
type varchar(20) NOT NULL,
data text NULL,
url_hash char(40) NULL default NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY type_created (type,created_at),
KEY contact_id (contact_id),
KEY message_id (message_id),
KEY url_hash (url_hash)
) {$e} {$c};";

		$sql[] = "CREATE TABLE {$templates} (
id bigint(20) unsigned NOT NULL auto_increment,
name varchar(190) NOT NULL default '',
category varchar(50) NOT NULL default '',
subject varchar(255) NOT NULL default '',
content_html longtext NULL,
design longtext NULL,
is_builtin tinyint(1) NOT NULL default 0,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY is_builtin (is_builtin)
) {$e} {$c};";

		$sql[] = "CREATE TABLE {$automations} (
id bigint(20) unsigned NOT NULL auto_increment,
name varchar(190) NOT NULL default '',
status varchar(20) NOT NULL default 'draft',
trigger_type varchar(50) NOT NULL default '',
trigger_config longtext NULL,
steps longtext NULL,
enrolled int(10) unsigned NOT NULL default 0,
completed int(10) unsigned NOT NULL default 0,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY status_trigger (status,trigger_type)
) {$e} {$c};";

		$sql[] = "CREATE TABLE {$runs} (
id bigint(20) unsigned NOT NULL auto_increment,
automation_id bigint(20) unsigned NOT NULL,
contact_id bigint(20) unsigned NOT NULL,
step_index int(10) unsigned NOT NULL default 0,
status varchar(10) NOT NULL default 'waiting',
next_run_at datetime NULL default NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY automation_contact (automation_id,contact_id),
KEY status_next (status,next_run_at),
KEY contact_id (contact_id)
) {$e} {$c};";

		return $sql;
	}
}
