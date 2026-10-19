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
