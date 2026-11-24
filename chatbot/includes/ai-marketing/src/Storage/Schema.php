<?php
/**
 * Database tables ({prefix}qcld_wpbot_mk_*), created and upgraded with dbDelta.
 *
 * The old CRM tables (wpbot_crm_*) are never touched here: Migration\From_Crm
 * copies from them and leaves them exactly as they are.
 *
 * @package QuantumCloud\AiMarketing
 */

namespace QuantumCloud\AiMarketing\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Schema.
 */
final class Schema {

	const VERSION        = '3';
	const VERSION_OPTION = 'qcld_wpbot_marketing_db_version';
	const SECRET_OPTION  = 'qcld_wpbot_marketing_secret';

	/**
	 * Table short names.
	 *
	 * @var string[]
	 */
	const TABLES = array(
		'contacts',
		'identities',
		'consents',
		'lists',
		'memberships',
		'events',
		'templates',
		'campaigns',
		'messages',
		'journeys',
		'journey_runs',
		'suggestions',
		'activity',
		'suppression',
		'legacy_map',
	);

	/**
	 * Full table name.
	 *
	 * @param string $name Short name.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'qcld_wpbot_mk_' . $name;
	}

	/**
	 * Create or upgrade the tables when the schema version changed.
	 *
	 * @return void
	 */
	public static function maybe_migrate() {
		if ( self::VERSION === (string) get_option( self::VERSION_OPTION, '' ) ) {
			return;
		}
		self::install();
	}

	/**
	 * Run dbDelta for every table and store the version.
	 *
	 * @return void
	 */
	public static function install() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		foreach ( self::sql() as $statement ) {
			dbDelta( $statement );
		}
		update_option( self::VERSION_OPTION, self::VERSION, true );
	}

	/**
	 * Secret for signed links (own key; old links use the old CRM secret).
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
	 * CREATE TABLE statements in dbDelta format.
	 *
	 * @return string[]
	 */
	public static function sql() {
		global $wpdb;
		$c = $wpdb->get_charset_collate();
		$t = array();
		foreach ( self::TABLES as $name ) {
			$t[ $name ] = self::table( $name );
		}

		$sql = array();

		// Status: subscribed, pending, lead, prospect, unsubscribed, bounced, complained.
		$sql[] = "CREATE TABLE {$t['contacts']} (
id bigint(20) unsigned NOT NULL auto_increment,
email varchar(190) NULL default NULL,
phone varchar(50) NOT NULL default '',
first_name varchar(100) NOT NULL default '',
last_name varchar(100) NOT NULL default '',
company varchar(190) NOT NULL default '',
website varchar(255) NOT NULL default '',
language varchar(20) NOT NULL default '',
status varchar(20) NOT NULL default 'subscribed',
lifecycle varchar(20) NOT NULL default '',
source varchar(50) NOT NULL default '',
attributes longtext NULL,
token char(32) NOT NULL,
score_engagement smallint(5) NOT NULL default 0,
score_intent smallint(5) NOT NULL default 0,
confirmed_at datetime NULL default NULL,
unsubscribed_at datetime NULL default NULL,
last_activity_at datetime NULL default NULL,
legacy_id bigint(20) unsigned NULL default NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY email (email),
UNIQUE KEY token (token),
UNIQUE KEY legacy_id (legacy_id),
KEY status (status),
KEY phone (phone),
KEY created_at (created_at)
) {$c};";

		// One person, many ids: wp_user, woo_customer, chat_visitor, whatsapp, messenger, telegram ...
		$sql[] = "CREATE TABLE {$t['identities']} (
id bigint(20) unsigned NOT NULL auto_increment,
contact_id bigint(20) unsigned NOT NULL,
kind varchar(30) NOT NULL,
value varchar(190) NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY kind_value (kind,value),
KEY contact_id (contact_id)
) {$c};";

		// What the person agreed to, where and when. kind: marketing or contact.
		$sql[] = "CREATE TABLE {$t['consents']} (
id bigint(20) unsigned NOT NULL auto_increment,
contact_id bigint(20) unsigned NOT NULL,
channel varchar(20) NOT NULL default 'email',
kind varchar(20) NOT NULL default 'marketing',
source varchar(50) NOT NULL default '',
wording text NULL,
ip_hash char(64) NOT NULL default '',
created_at datetime NOT NULL,
confirmed_at datetime NULL default NULL,
withdrawn_at datetime NULL default NULL,
PRIMARY KEY  (id),
KEY contact_channel (contact_id,channel)
) {$c};";

		// Lists and tags. kind: static or smart. origin: owner, scan, system, agent, migration.
		$sql[] = "CREATE TABLE {$t['lists']} (
id bigint(20) unsigned NOT NULL auto_increment,
type varchar(10) NOT NULL default 'list',
name varchar(190) NOT NULL default '',
slug varchar(190) NOT NULL default '',
description text NULL,
kind varchar(10) NOT NULL default 'static',
rules longtext NULL,
origin varchar(20) NOT NULL default 'owner',
system_key varchar(50) NULL default NULL,
smart_key varchar(80) NULL default NULL,
locked tinyint(1) NOT NULL default 0,
legacy_id bigint(20) unsigned NULL default NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY type_slug (type,slug),
UNIQUE KEY system_key (system_key),
UNIQUE KEY smart_key (smart_key),
KEY legacy_id (legacy_id)
) {$c};";

		// Why each person is in a list (reason shown to the owner), and how sure the sorter was.
		$sql[] = "CREATE TABLE {$t['memberships']} (
list_id bigint(20) unsigned NOT NULL,
contact_id bigint(20) unsigned NOT NULL,
reason varchar(255) NOT NULL default '',
confidence tinyint(3) unsigned NOT NULL default 100,
origin varchar(20) NOT NULL default 'owner',
added_at datetime NOT NULL,
PRIMARY KEY  (list_id,contact_id),
KEY contact_id (contact_id)
) {$c};";

		// One timeline per contact: chat, page, form, order, email sent/open/click, unsubscribe ...
		$sql[] = "CREATE TABLE {$t['events']} (
id bigint(20) unsigned NOT NULL auto_increment,
contact_id bigint(20) unsigned NOT NULL default 0,
message_id bigint(20) unsigned NULL default NULL,
type varchar(30) NOT NULL,
data text NULL,
legacy_id bigint(20) unsigned NULL default NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY contact_created (contact_id,created_at),
KEY type_created (type,created_at),
KEY message_id (message_id),
UNIQUE KEY legacy_id (legacy_id)
) {$c};";

		$sql[] = "CREATE TABLE {$t['templates']} (
id bigint(20) unsigned NOT NULL auto_increment,
name varchar(190) NOT NULL default '',
category varchar(50) NOT NULL default '',
subject varchar(255) NOT NULL default '',
content_html longtext NULL,
design longtext NULL,
is_builtin tinyint(1) NOT NULL default 0,
legacy_id bigint(20) unsigned NULL default NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY legacy_id (legacy_id)
) {$c};";

		$sql[] = "CREATE TABLE {$t['campaigns']} (
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
origin varchar(20) NOT NULL default 'owner',
scheduled_at datetime NULL default NULL,
started_at datetime NULL default NULL,
finished_at datetime NULL default NULL,
recipients int(10) unsigned NOT NULL default 0,
sent int(10) unsigned NOT NULL default 0,
failed int(10) unsigned NOT NULL default 0,
legacy_id bigint(20) unsigned NULL default NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY status_scheduled (status,scheduled_at),
UNIQUE KEY legacy_id (legacy_id)
) {$c};";

		// Status: queued, sending, sent, failed, skipped, unknown. A row is sent once:
		// claim is set atomically before sending and never cleared after a send.
		$sql[] = "CREATE TABLE {$t['messages']} (
id bigint(20) unsigned NOT NULL auto_increment,
campaign_id bigint(20) unsigned NULL default NULL,
journey_id bigint(20) unsigned NULL default NULL,
step_key varchar(40) NULL default NULL,
contact_id bigint(20) unsigned NOT NULL,
status varchar(12) NOT NULL default 'queued',
claim varchar(40) NULL default NULL,
attempts tinyint(3) unsigned NOT NULL default 0,
provider_id varchar(190) NOT NULL default '',
error text NULL,
queued_at datetime NOT NULL,
claimed_at datetime NULL default NULL,
sent_at datetime NULL default NULL,
first_open_at datetime NULL default NULL,
opens int(10) unsigned NOT NULL default 0,
first_click_at datetime NULL default NULL,
clicks int(10) unsigned NOT NULL default 0,
legacy_id bigint(20) unsigned NULL default NULL,
PRIMARY KEY  (id),
UNIQUE KEY campaign_contact (campaign_id,contact_id),
KEY status_claim (status,claim),
KEY campaign_status (campaign_id,status),
KEY journey_id (journey_id),
KEY contact_id (contact_id),
KEY provider_id (provider_id),
UNIQUE KEY legacy_id (legacy_id)
) {$c};";

		// mode: off, suggest, draft, autopilot (PLAN.md section 3).
		$sql[] = "CREATE TABLE {$t['journeys']} (
id bigint(20) unsigned NOT NULL auto_increment,
name varchar(190) NOT NULL default '',
status varchar(20) NOT NULL default 'draft',
mode varchar(12) NOT NULL default 'draft',
trigger_type varchar(50) NOT NULL default '',
trigger_config longtext NULL,
steps longtext NULL,
settings longtext NULL,
origin varchar(20) NOT NULL default 'owner',
enrolled int(10) unsigned NOT NULL default 0,
completed int(10) unsigned NOT NULL default 0,
legacy_id bigint(20) unsigned NULL default NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY status_trigger (status,trigger_type),
UNIQUE KEY legacy_id (legacy_id)
) {$c};";

		// run_key lets a journey run again for the same person (re-entry rules).
		$sql[] = "CREATE TABLE {$t['journey_runs']} (
id bigint(20) unsigned NOT NULL auto_increment,
journey_id bigint(20) unsigned NOT NULL,
contact_id bigint(20) unsigned NOT NULL,
run_key varchar(64) NOT NULL default '',
step_index int(10) unsigned NOT NULL default 0,
status varchar(12) NOT NULL default 'waiting',
claim varchar(40) NULL default NULL,
next_run_at datetime NULL default NULL,
entered_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY journey_contact_key (journey_id,contact_id,run_key),
KEY status_next (status,next_run_at),
KEY contact_id (contact_id)
) {$c};";

		// The Approval inbox.
		$sql[] = "CREATE TABLE {$t['suggestions']} (
id bigint(20) unsigned NOT NULL auto_increment,
kind varchar(40) NOT NULL,
dedupe_key varchar(120) NULL default NULL,
title varchar(255) NOT NULL default '',
reason text NULL,
payload longtext NULL,
status varchar(12) NOT NULL default 'pending',
expires_at datetime NULL default NULL,
created_at datetime NOT NULL,
decided_at datetime NULL default NULL,
decided_by bigint(20) unsigned NOT NULL default 0,
PRIMARY KEY  (id),
UNIQUE KEY dedupe_key (dedupe_key),
KEY status_created (status,created_at)
) {$c};";

		// What every automation did, and why.
		$sql[] = "CREATE TABLE {$t['activity']} (
id bigint(20) unsigned NOT NULL auto_increment,
actor varchar(50) NOT NULL default '',
action varchar(50) NOT NULL default '',
object_type varchar(30) NOT NULL default '',
object_id bigint(20) unsigned NOT NULL default 0,
contact_id bigint(20) unsigned NOT NULL default 0,
reason text NULL,
data longtext NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY created_at (created_at),
KEY object (object_type,object_id)
) {$c};";

		$sql[] = "CREATE TABLE {$t['suppression']} (
id bigint(20) unsigned NOT NULL auto_increment,
value varchar(190) NOT NULL,
kind varchar(10) NOT NULL default 'email',
reason varchar(30) NOT NULL default '',
source varchar(50) NOT NULL default '',
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY value (value)
) {$c};";

		// Old CRM id => new id, per kind (contact, group, template, campaign, automation, message, event).
		$sql[] = "CREATE TABLE {$t['legacy_map']} (
kind varchar(20) NOT NULL,
legacy_id bigint(20) unsigned NOT NULL,
new_id bigint(20) unsigned NOT NULL,
PRIMARY KEY  (kind,legacy_id)
) {$c};";

		return $sql;
	}
}
