<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WPBot
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Read the chatbot's "Delete chatbot data when the plugin is deleted" switch first: the chatbot
// module's helper below deletes it with the module's other options. With the switch on and no other
// WPBot plugin installed, WPBot Free's own data is deleted at the end, after the modules' helpers.
require_once __DIR__ . '/includes/uninstall-free-data.php';
if ( file_exists( __DIR__ . '/includes/chatbot/uninstall-helper.php' ) ) {
	require_once __DIR__ . '/includes/chatbot/uninstall-helper.php'; // Functions only; called below.
}
$qcld_delete                 = '1' === (string) get_option( 'qcld_wpbot_chat_delete_on_uninstall', '' );
$qcld_wpbot_free_delete_data = $qcld_delete && qcld_wpbot_free_uninstall_wanted( __DIR__ );

// SEO & AI Search, then WPBot Core: each removes WPBot Free from its host list and deletes
// data only when no other WPBot plugin still bundles it and the owner turned on
// "Delete data on uninstall" (off by default). Posts are never deleted.
if ( file_exists( __DIR__ . '/includes/seo-ai-search/uninstall-helper.php' ) ) {
	require_once __DIR__ . '/includes/seo-ai-search/uninstall-helper.php';
	qcld_wpbot_seo_uninstall_for_host( 'wpbot-free' );
}
// AI Writer: removes WPBot Free from its host list; its data is deleted only when no other
// WPBot plugin bundles it and the owner turned on "Delete AI Writer data". Posts are never deleted.
if ( version_compare( PHP_VERSION, '7.4', '>=' ) && file_exists( __DIR__ . '/includes/ai-writer/uninstall-helper.php' ) ) {
	require_once __DIR__ . '/includes/ai-writer/uninstall-helper.php';
	qcld_wpbot_writer_uninstall_for_host( 'wpbot-free' );
}
// WPBot Asset Cache: deletes WPBot Free's cache folder (uploads/wpbot-cache/free/) and its
// cron event; its options only when WPBot Pro (which shares them) is not installed.
if ( version_compare( PHP_VERSION, '7.4', '>=' ) && file_exists( __DIR__ . '/includes/asset-optimizer/uninstall-helper.php' ) ) {
	require_once __DIR__ . '/includes/asset-optimizer/uninstall-helper.php';
	qcld_wpbot_ao_uninstall_for_host( 'free' );
}
// WPBot Social: its tables, options (accounts, encrypted tokens, Social's own Telegram bot)
// and user meta are shared with WPBot Pro Master, so they are deleted only when no WPBot Pro
// plugin is installed and the owner turned on "Delete Social data" (Social › Settings ›
// Advanced). Its cron events are cleared only then too, because WPBot Pro uses the same ones.
// Posts already published on the networks are never deleted. Runs before WPBot Core, which
// drops the vault table.
$qcld_wpbot_free_social_self  = wp_normalize_path( (string) realpath( __DIR__ ) ) . '/';
$qcld_wpbot_free_social_hosts = array_filter(
	(array) glob( WP_PLUGIN_DIR . '/*/qcld-wpwbot.php' ),
	function ( $file ) use ( $qcld_wpbot_free_social_self ) {
		return is_string( $file ) && '' !== $file && 0 !== strpos( wp_normalize_path( (string) realpath( $file ) ), $qcld_wpbot_free_social_self );
	}
);
if ( empty( $qcld_wpbot_free_social_hosts ) && version_compare( PHP_VERSION, '7.4', '>=' ) && file_exists( __DIR__ . '/includes/social/uninstall-helper.php' ) ) {
	require_once __DIR__ . '/includes/social/uninstall-helper.php';
	// On Social's opt-in the helper also deletes Social's developer apps and connection health
	// kept by WPBot Core (qcld_wpbot_core_social_apps, qcld_wpbot_core_connection_status).
	qcld_wpbot_social_uninstall_for_host( 'wpbot-free' );
} elseif ( ! empty( $qcld_wpbot_free_social_hosts ) ) {
	// Another WPBot plugin keeps running Social: leave its data and schedules alone, but take
	// WPBot Free off Social's host list so that plugin's own uninstall, as the last host,
	// can delete the data when the owner opted in.
	$qcld_wpbot_free_social_list = get_option( 'qcld_wpbot_social_hosts', array() );
	if ( is_array( $qcld_wpbot_free_social_list ) && in_array( 'wpbot-free', $qcld_wpbot_free_social_list, true ) ) {
		update_option( 'qcld_wpbot_social_hosts', array_values( array_diff( $qcld_wpbot_free_social_list, array( 'wpbot-free' ) ) ), true );
	}
}

// AI Marketing: only its own data (qcld_wpbot_mk_*, qcld_wpbot_marketing_*), only with the owner's opt-in
// and only when no other WPBot host is installed. The old AI Marketing tables (wpbot_crm_*) and the
// chatbot's User Data are never deleted (owner, 19 Nov 2026).
if ( empty( $qcld_wpbot_free_social_hosts ) && version_compare( PHP_VERSION, '7.4', '>=' ) && file_exists( __DIR__ . '/includes/ai-marketing/uninstall-helper.php' ) ) {
	require_once __DIR__ . '/includes/ai-marketing/uninstall-helper.php';
	qcld_wpbot_marketing_uninstall_for_host( 'wpbot-free' );
} elseif ( ! empty( $qcld_wpbot_free_social_hosts ) ) {
	$qcld_wpbot_free_marketing_list = get_option( 'qcld_wpbot_marketing_hosts', array() );
	if ( is_array( $qcld_wpbot_free_marketing_list ) && in_array( 'wpbot-free', $qcld_wpbot_free_marketing_list, true ) ) {
		update_option( 'qcld_wpbot_marketing_hosts', array_values( array_diff( $qcld_wpbot_free_marketing_list, array( 'wpbot-free' ) ) ), true );
	}
}
// ChatBot module: its tables (messages, knowledge gaps, change log, answer cache), options and
// transients are shared by WPBot Free and WPBot Pro, so they are deleted only when no other WPBot
// plugin with the module is installed and the owner turned on "Delete chatbot data when the plugin
// is deleted" (AI Settings > Chatbot data, off by default).
if ( file_exists( __DIR__ . '/includes/chatbot/uninstall-helper.php' ) ) {
	require_once __DIR__ . '/includes/chatbot/uninstall-helper.php';
	qcld_wpbot_chat_uninstall_for_host( 'wpbot-free', __DIR__ );
}
if ( file_exists( __DIR__ . '/includes/wpbot-core/uninstall-helper.php' ) ) {
	// The chatbot's delete switch also asks WPBot Core to delete its data (keys, knowledge base, jobs,
	// error log) when WPBot Free is its last host. Older copies of the helper take no second argument:
	// for them, Core's own delete_on_uninstall setting is turned on (only when Free is the last host).
	if ( $qcld_delete ) {
		qcld_wpbot_free_uninstall_core_opt_in( 'wpbot-free' );
	}
	require_once __DIR__ . '/includes/wpbot-core/uninstall-helper.php';
	qcld_wpbot_core_uninstall_for_host( 'wpbot-free', $qcld_delete );
}
delete_option( 'qcld_wpbot_free_seo_analyzer_retired' );

// Clean up transient cache entries created by the marketing module and WPBot.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_qcwpbot_%' OR option_name LIKE '_transient_timeout_qcwpbot_%'" );

// Email marketing CRM: stop its cron jobs and drop cached values. Contacts and campaigns are kept.
wp_clear_scheduled_hook( 'wpbot_crm/tick' );
wp_clear_scheduled_hook( 'wpbot_crm/housekeeping' );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_wpbot\\_crm\\_%' OR option_name LIKE '\\_transient\\_timeout\\_wpbot\\_crm\\_%'" );

// Automator add-on: stop its cron jobs, remove stored API keys, and drop its run logs and queued
// tasks (they hold AI responses and personal data). Workflows and user-built tables are kept.
// The data is shared by every copy of the Automator (WPBot Free, WPBot Pro, standalone), so it is
// only removed when no other copy is installed.
$wpbot_automator_self   = wp_normalize_path( (string) realpath( __DIR__ ) ) . '/';
$wpbot_automator_others = array_filter(
	array_merge(
		(array) glob( WP_PLUGIN_DIR . '/*/addons/automator/wpbot-automator.php' ),
		(array) glob( WP_PLUGIN_DIR . '/*/wpbot-automator.php' )
	),
	function ( $file ) use ( $wpbot_automator_self ) {
		// realpath() so a symlinked plugin folder still recognises its own copy.
		return is_string( $file ) && '' !== $file && 0 !== strpos( wp_normalize_path( (string) realpath( $file ) ), $wpbot_automator_self );
	}
);
if ( empty( $wpbot_automator_others ) ) {
	foreach ( array( 'wpbot_automator_prune_logs', 'wpbot_automator_resume_delayed_workflow', 'wpbot_automator_delayed_trigger', 'wpbot_automator_run_queued_trigger' ) as $wpbot_automator_hook ) {
		wp_unschedule_hook( $wpbot_automator_hook );
	}
	delete_option( 'wpbot_automator_credentials' );
	delete_option( 'wpbot_automator_gs_sa_json' );
	delete_option( 'wpbot_automator_trigger_index' );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_wpbot\\_automator\\_%' OR option_name LIKE '\\_transient\\_timeout\\_wpbot\\_automator\\_%'" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpbot_automator_logs" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpbot_automator_scheduled_tasks" );
	delete_option( 'wpbot_automator_db_version' ); // So a reinstall recreates the dropped tables.
	delete_option( 'wpbot_automator_version' );
}

// Admin theme: the users' Light/Dark choices, only when no other installed plugin bundles the module.
if ( file_exists( __DIR__ . '/includes/admin-theme/uninstall-helper.php' ) ) {
	require_once __DIR__ . '/includes/admin-theme/uninstall-helper.php';
	qcld_admin_theme_uninstall( __DIR__ );
}

// Notification digests (AI / Settings → Notifications): stop their cron jobs (WPBot Pro schedules
// its own again if it is installed).
wp_clear_scheduled_hook( 'qcld_wpbot_notification_digest_daily' );
wp_clear_scheduled_hook( 'qcld_wpbot_notification_digest_weekly' );

// WPBot Free's own data, last (see the switch read at the top): conversations, visitors, subscribers,
// failed questions, chat reports, Simple Text Responses, sessions, the old knowledge base table, its
// options, transients, user meta and scheduled events.
if ( $qcld_wpbot_free_delete_data ) {
	qcld_wpbot_free_uninstall_data();
	// Notification choices and the digest queue.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'qcld\\_wpbot\\_notification\\_%'" );
}
