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
if ( file_exists( __DIR__ . '/includes/wpbot-core/uninstall-helper.php' ) ) {
	require_once __DIR__ . '/includes/wpbot-core/uninstall-helper.php';
	qcld_wpbot_core_uninstall_for_host( 'wpbot-free' );
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
