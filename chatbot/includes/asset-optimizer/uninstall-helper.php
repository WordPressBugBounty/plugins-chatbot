<?php
/**
 * Uninstall helper for the WPBot Asset Cache core module. Hosts call it from
 * their uninstall.php.
 *
 * Each package deletes its own cache subfolder (uploads/wpbot-cache/<package>/).
 * The two options are shared by WPBot Free and WPBot Pro, so they are deleted
 * only when the other package is not installed.
 *
 * @package QuantumCloud\AssetOptimizer
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_ao_uninstall_for_host' ) ) {
	/**
	 * Remove one package's cache and, if no other package bundles the module, its data.
	 *
	 * @param string $package Package slug ("pro" or "free").
	 * @return void
	 */
	function qcld_wpbot_ao_uninstall_for_host( $package ) {
		require_once __DIR__ . '/src/autoload.php';

		$self        = wp_normalize_path( (string) realpath( __DIR__ ) );
		$others      = array_filter(
			(array) glob( WP_PLUGIN_DIR . '/*/includes/asset-optimizer/asset-optimizer.php' ),
			static function ( $file ) use ( $self ) {
				return is_string( $file ) && wp_normalize_path( (string) realpath( dirname( $file ) ) ) !== $self;
			}
		);
		$delete_data = array() === $others;

		$site_ids = is_multisite() ? get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) : array( 0 );
		foreach ( $site_ids as $site_id ) {
			if ( $site_id ) {
				switch_to_blog( (int) $site_id );
			}
			qcld_wpbot_ao_uninstall_site( (string) $package, $delete_data );
			if ( $site_id ) {
				restore_current_blog();
			}
		}
		if ( $delete_data ) {
			delete_metadata( 'user', 0, 'qcld_wpbot_ao_unwritable_dismissed', '', true );
		}
	}
}

if ( ! function_exists( 'qcld_wpbot_ao_uninstall_site' ) ) {
	/**
	 * Uninstall on the current site.
	 *
	 * @param string $package     Package slug.
	 * @param bool   $delete_data Whether to delete the options too.
	 * @return void
	 */
	function qcld_wpbot_ao_uninstall_site( $package, $delete_data ) {
		wp_clear_scheduled_hook( 'wpbot_ao_cleanup' );
		( new \QuantumCloud\AssetOptimizer\Storage( $package ) )->destroy();
		if ( ! $delete_data ) {
			return;
		}
		delete_option( 'wpbot_asset_cache_settings' );
		delete_option( 'wpbot_asset_cache_state' );
		// Every other option under the module's prefix (for example the Pro module's).
		global $wpdb;
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'wpbot_asset_cache_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off lookup at uninstall.
		foreach ( (array) $names as $name ) {
			delete_option( $name );
		}

		/**
		 * Let the Pro module and extensions remove their data too.
		 */
		do_action( 'qcld_wpbot_ao_delete_all' );
	}
}
