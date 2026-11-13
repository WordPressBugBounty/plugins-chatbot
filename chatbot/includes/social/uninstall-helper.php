<?php
/**
 * Uninstall helper for hosts that bundle WPBot Social.
 *
 * Hosts call qcld_wpbot_social_uninstall_for_host( $host_id ) from their
 * uninstall.php. Every host that ran Social is in the option
 * qcld_wpbot_social_hosts (Module::start() adds it). The uninstalling host
 * is removed from that list; data is deleted only when no host is left and
 * the owner opted in (Settings › Advanced › Delete Social data). Posts
 * already published on the networks are never touched, and the ChatBot's
 * channel settings are left alone.
 *
 * A host that never ran Social on this site (WPBot Free installed next to
 * WPBot Pro) is not in the list, so a host's uninstall should also skip this
 * call while another WPBot host plugin is installed.
 *
 * @package QuantumCloud\Social
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_social_core_owned_options' ) ) {
	/**
	 * WPBot Core options that exist only for Social, deleted with Social's data.
	 *
	 * @return string[]
	 */
	function qcld_wpbot_social_core_owned_options() {
		return array( 'qcld_wpbot_core_social_apps', 'qcld_wpbot_core_connection_status' );
	}
}

if ( ! function_exists( 'qcld_wpbot_social_uninstall_for_host' ) ) {
	/**
	 * Remove Social data when no host is left and the owner opted in.
	 *
	 * @param string $host_id Host being uninstalled ("wpbot-pro", "wpbot-free").
	 * @return bool Whether data was deleted.
	 */
	function qcld_wpbot_social_uninstall_for_host( $host_id ) {
		global $wpdb;
		$hosts = get_option( 'qcld_wpbot_social_hosts', array() );
		$hosts = array_values( array_diff( is_array( $hosts ) ? array_map( 'strval', $hosts ) : array(), array( (string) $host_id ) ) );
		if ( array() !== $hosts ) {
			// Another host still runs Social on this data: keep everything, schedules included.
			update_option( 'qcld_wpbot_social_hosts', $hosts, true );
			return false;
		}
		// Last host. Its schedules go (Scheduler and Health add them back if Social starts
		// again; only pending auto-share single events are lost).
		$settings = get_option( 'qcld_wpbot_social_settings', array() );
		foreach ( (array) _get_cron_array() as $events ) {
			foreach ( array_keys( (array) $events ) as $hook ) {
				if ( 0 === strpos( (string) $hook, 'qcld_wpbot_social' ) ) {
					wp_clear_scheduled_hook( $hook );
				}
			}
		}
		if ( ! is_array( $settings ) || empty( $settings['delete_on_uninstall'] ) ) {
			return false;
		}
		$like   = $wpdb->esc_like( $wpdb->prefix . 'qcld_wpbot_social_' ) . '%';
		$tables = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Every Social table, whatever version created it (QA #57).
		foreach ( $tables as $table ) {
			$wpdb->query( 'DROP TABLE IF EXISTS `' . str_replace( '`', '', $table ) . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Names from SHOW TABLES with this module's prefix.
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'qcld_wpbot_social_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bulk delete of this module's options.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( 'qcld_wpbot_social_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bulk delete of this module's user meta.
		// The two WPBot Core options only Social uses (QA #88): the owner's own app IDs and
		// encrypted secrets (Meta, Threads, LinkedIn, X ...) and the connection health record.
		// No other Core data is touched; Core's own opt-in still covers the rest.
		foreach ( qcld_wpbot_social_core_owned_options() as $option ) {
			delete_option( $option );
		}
		return true;
	}
}
