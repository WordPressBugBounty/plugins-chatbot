<?php
/**
 * Uninstall helper for host plugins.
 *
 * Call from the host's uninstall.php:
 *
 *     require_once __DIR__ . '/includes/insights/uninstall-helper.php';
 *     qcin_uninstall_for_host( 'wpbot' );
 *
 * Data is removed only when no other host plugin still uses the module and
 * the owner turned on "Delete all Insights data when the plugin is deleted".
 *
 * @package QuantumCloud\Insights
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcin_uninstall_for_host' ) ) {
	/**
	 * Remove a host and, if it was the last one, optionally all data.
	 *
	 * @param string $host_id Host id, e.g. 'wpbot'.
	 */
	function qcin_uninstall_for_host( $host_id ) {
		global $wpdb;

		$hosts = array_values( array_diff( (array) get_option( 'qcin_hosts', array() ), array( $host_id ) ) );
		update_option( 'qcin_hosts', $hosts, false );
		if ( ! empty( $hosts ) ) {
			return;
		}

		$settings = (array) get_option( 'qcin_settings', array() );
		foreach ( array( 'qcin_finalize_sessions', 'qcin_aggregate', 'qcin_cleanup', 'qcin_cleanup_more', 'qcin_health_check', 'qcin_email_report' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		if ( empty( $settings['delete_on_uninstall'] ) ) {
			return;
		}

		// Core tables, plus any table a feature added with the same prefix
		// ({prefix}qcin_...), since feature code is not loaded during uninstall.
		$tables = array();
		foreach ( array( 'sessions', 'pageviews', 'urls', 'selectors', 'clicks', 'heatmap_bins', 'scroll_bins', 'signals', 'ai_results' ) as $name ) {
			$tables[] = $wpdb->prefix . 'qcin_' . $name;
		}
		$found  = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'qcin_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$tables = array_unique( array_merge( $tables, is_array( $found ) ? $found : array() ) );
		foreach ( $tables as $table ) {
			if ( ! preg_match( '/^[A-Za-z0-9_]+$/', (string) $table ) || 0 !== strpos( (string) $table, $wpdb->prefix . 'qcin_' ) ) {
				continue;
			}
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$suffix = get_option( 'qcin_storage_suffix' );
		if ( is_string( $suffix ) && preg_match( '/^[a-z0-9]{12}$/', $suffix ) ) {
			$uploads = wp_upload_dir( null, false );
			$dir     = untrailingslashit( $uploads['basedir'] ) . '/qc-insights-' . $suffix;
			if ( is_dir( $dir ) ) {
				$items = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
					RecursiveIteratorIterator::CHILD_FIRST
				);
				foreach ( $items as $item ) {
					$item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() ); // phpcs:ignore
				}
				@rmdir( $dir ); // phpcs:ignore
			}
		}

		foreach ( array( 'qcin_settings', 'qcin_db_version', 'qcin_storage_suffix', 'qcin_site_token', 'qcin_hosts', 'qcin_hm_watermark', 'qcin_job_runs', 'qcin_settings_version', 'qcin_ai', 'qcin_ai_usage', 'qcin_reports', 'qcin_report_last', 'qcin_report_result', 'qcin_report_checked', 'qcin_report_since' ) as $option ) {
			delete_option( $option );
		}
		// Options features saved with the same prefix (qcin_...).
		$options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'qcin_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $options as $option ) {
			delete_option( $option );
		}
		delete_transient( 'qcin_health' );
	}
}
