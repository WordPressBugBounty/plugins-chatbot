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

// Clean up transient cache entries created by the marketing module and WPBot.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_qcwpbot_%' OR option_name LIKE '_transient_timeout_qcwpbot_%'" );

// Email marketing CRM: stop its cron jobs and drop cached values. Contacts and campaigns are kept.
wp_clear_scheduled_hook( 'wpbot_crm/tick' );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_wpbot\\_crm\\_%' OR option_name LIKE '\\_transient\\_timeout\\_wpbot\\_crm\\_%'" );

// Automator add-on: stop its cron jobs, remove stored API keys, and drop its run logs and queued
// tasks (they hold AI responses and personal data). Workflows and user-built tables are kept.
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
