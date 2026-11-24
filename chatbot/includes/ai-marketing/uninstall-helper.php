<?php
/**
 * Uninstall helper for hosts that bundle WPBot AI Marketing.
 *
 * Hosts call qcld_wpbot_marketing_uninstall_for_host( $host_id ) from their
 * uninstall.php. Every host that ran AI Marketing is in the option
 * qcld_wpbot_marketing_hosts (Module::start() adds it). The uninstalling host
 * is removed from that list; this module's own data (qcld_wpbot_mk_* tables
 * and qcld_wpbot_marketing_* options) is deleted only when no host is left
 * and the owner opted in (Settings › Advanced › delete_on_uninstall).
 *
 * Never touched, whatever the setting (owner, 19 Nov 2026): the old AI
 * Marketing tables and options (wpbot_crm_*) and the chatbot's User Data
 * (wpbot_subscription).
 *
 * @package QuantumCloud\AiMarketing
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_marketing_uninstall_for_host' ) ) {
	/**
	 * Remove AI Marketing data when no host is left and the owner opted in.
	 *
	 * @param string $host_id Host being uninstalled ("wpbot-pro", "wpbot-free").
	 * @return bool Whether data was deleted.
	 */
	function qcld_wpbot_marketing_uninstall_for_host( $host_id ) {
		global $wpdb;
		$hosts = get_option( 'qcld_wpbot_marketing_hosts', array() );
		$hosts = array_values( array_diff( is_array( $hosts ) ? array_map( 'strval', $hosts ) : array(), array( (string) $host_id ) ) );
		if ( array() !== $hosts ) {
			// Another host still runs AI Marketing on this data: keep everything.
			update_option( 'qcld_wpbot_marketing_hosts', $hosts, true );
			return false;
		}
		foreach ( (array) _get_cron_array() as $events ) {
			foreach ( array_keys( (array) $events ) as $hook ) {
				if ( 0 === strpos( (string) $hook, 'qcld_wpbot_marketing' ) ) {
					wp_clear_scheduled_hook( $hook );
				}
			}
		}
		$settings = get_option( 'qcld_wpbot_marketing_settings', array() );
		if ( ! is_array( $settings ) || empty( $settings['delete_on_uninstall'] ) ) {
			update_option( 'qcld_wpbot_marketing_hosts', array(), true );
			return false;
		}
		$like   = $wpdb->esc_like( $wpdb->prefix . 'qcld_wpbot_mk_' ) . '%';
		$tables = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Every AI Marketing table, whatever version created it.
		// The Master module's tables (qcld_wpbot_mkp_*) go with them.
		$tables = array_merge( $tables, (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'qcld_wpbot_mkp_' ) . '%' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Same, Master module.
		foreach ( $tables as $table ) {
			$wpdb->query( 'DROP TABLE IF EXISTS `' . str_replace( '`', '', $table ) . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Names from SHOW TABLES with this module's prefix only (never wpbot_crm_ or wpbot_subscription).
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'qcld_wpbot_marketing_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bulk delete of this module's options only.
		delete_metadata( 'user', 0, '_qcld_mk_cart', '', true ); // Carts remembered for abandoned-cart journeys (Master).
		return true;
	}
}
