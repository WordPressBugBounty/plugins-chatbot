<?php
/**
 * Uninstall helper for the AI Writer core module. Hosts call it from their
 * uninstall.php (after the Pro module's helper, before WPBot Core's).
 *
 * A host only removes itself from the host list. Data is deleted only when
 * no host is left and the owner turned on "Delete AI Writer data". Posts,
 * pages and products are never deleted; roles are never touched.
 *
 * @package QuantumCloud\AiWriter
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_writer_uninstall_for_host' ) ) {
	/**
	 * Remove a host and, if it was the last one, clean up.
	 *
	 * @param string $host_id Host id.
	 * @return void
	 */
	function qcld_wpbot_writer_uninstall_for_host( $host_id ) {
		global $wpdb;

		$hosts = get_option( 'qcld_wpbot_writer_hosts', array() );
		$hosts = array_values( array_diff( is_array( $hosts ) ? $hosts : array(), array( (string) $host_id ) ) );
		update_option( 'qcld_wpbot_writer_hosts', $hosts, false );
		if ( array() !== $hosts ) {
			return;
		}

		foreach ( array( 'qcld_wpbot_writer_topics_refresh', 'qcld_wpbot_writer_weekly_email', 'qcld_wpbot_writer_decay_check' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		// CR-16: the PDF font cache (rebuilt when needed; never the owner's data).
		$uploads = wp_upload_dir( null, false );
		$cache   = trailingslashit( $uploads['basedir'] ) . 'qcld-wpbot-writer/pdf-fonts';
		if ( is_dir( $cache ) ) {
			foreach ( (array) glob( $cache . '/*' ) as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
			@rmdir( $cache ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Empty cache folder, uninstall only.
		}

		$settings = get_option( 'qcld_wpbot_writer_settings', array() );
		if ( empty( $settings['delete_on_uninstall'] ) ) {
			return;
		}

		foreach ( array( 'ideas', 'voices', 'documents', 'search_log' ) as $name ) {
			$table = $wpdb->prefix . 'qcld_wpbot_writer_' . $name;
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed names, uninstall only.
		}

		// AI Writer's own post meta (underscore-prefixed); posts themselves stay.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_qcld_wpbot_writer_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall only.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_qcld_wpbot_writer_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall only.

		foreach ( array( 'qcld_wpbot_writer_settings', 'qcld_wpbot_writer_db_version', 'qcld_wpbot_writer_hosts', 'qcld_wpbot_writer_improve_tasks', 'qcld_wpbot_writer_improve_tasks_checked', 'qcld_wpbot_writer_topics_runs', 'qcld_wpbot_writer_topics_job', 'qcld_wpbot_writer_email_sent', 'qcld_wpbot_writer_rss_failing', 'qcld_wpbot_writer_who_ranks_seen' ) as $option ) {
			delete_option( $option );
		}

		/**
		 * Let extensions remove their data too.
		 */
		do_action( 'qcld_wpbot_writer/delete_all' );
	}
}
