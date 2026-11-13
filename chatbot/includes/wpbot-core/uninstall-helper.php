<?php
/**
 * Uninstall helper for WPBot Core. Hosts call it from their uninstall.php.
 *
 * Data is shared by every host that bundles the module, so a host only
 * removes itself from the host list. Data is deleted only when no host is
 * left and the owner turned on "Delete data on uninstall". Posts and roles
 * are never touched.
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_core_uninstall_for_host' ) ) {
	/**
	 * Remove a host and, if it was the last one, clean up.
	 *
	 * @param string $host_id Host id, for example "wpbot-pro".
	 * @return void
	 */
	function qcld_wpbot_core_uninstall_for_host( $host_id ) {
		global $wpdb;

		$hosts = get_option( 'qcld_wpbot_core_hosts', array() );
		$hosts = array_values( array_diff( is_array( $hosts ) ? $hosts : array(), array( (string) $host_id ) ) );
		update_option( 'qcld_wpbot_core_hosts', $hosts, false );
		if ( array() !== $hosts ) {
			return;
		}

		wp_clear_scheduled_hook( 'qcld_wpbot_core_run_jobs' );
		wp_clear_scheduled_hook( 'qcld_wpbot_core_run_jobs', array( 'kick' ) );
		wp_clear_scheduled_hook( 'qcld_wpbot_core_context_weekly' );
		wp_clear_scheduled_hook( 'qcld_wpbot_core_spend_hourly' );
		wp_clear_scheduled_hook( 'qcld_wpbot_core_spend_daily' );
		wp_clear_scheduled_hook( 'qcld_wpbot_core_errors_daily' );

		$settings = get_option( 'qcld_wpbot_core_settings', array() );
		if ( empty( $settings['delete_on_uninstall'] ) ) {
			return;
		}

		// Every table WPBot Core creates: Storage\Schema::all_tables() plus the SEO track (Seo_Schema), the knowledge base (Kb_Schema) and AI spend (Spend_Schema).
		foreach ( array( 'jobs', 'job_steps', 'usage', 'ai_cache', 'packages', 'facts', 'keyword_map', 'topics', 'kit', 'voc', 'experiments', 'experiment_variants', 'experiment_stats', 'technique_results', 'keyword_data', 'vault', 'kb_chunks', 'kb_sources', 'usage_daily', 'errors' ) as $name ) {
			$table = $wpdb->prefix . 'qcld_wpbot_core_' . $name;
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Fixed names, uninstall only.
		}

		$items = get_posts(
			array(
				'post_type'      => 'wpbot_content_item',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $items as $item_id ) {
			wp_delete_post( (int) $item_id, true );
		}

		foreach ( array( 'qcld_wpbot_core_settings', 'qcld_wpbot_core_keys', 'qcld_wpbot_core_key_health', 'qcld_wpbot_core_permissions', 'qcld_wpbot_core_profile', 'qcld_wpbot_core_connections', 'qcld_wpbot_core_db_version', 'qcld_wpbot_core_hosts', 'qcld_wpbot_core_seo_db_version', 'qcld_wpbot_core_kb_db_version', 'qcld_wpbot_core_kb_state', 'qcld_wpbot_core_quota', 'qcld_wpbot_core_keyword_keys', 'qcld_wpbot_core_ai_setup_repaired', 'qcld_wpbot_core_competitors', 'qcld_wpbot_core_voc_state', 'qcld_wpbot_core_experiments_front', 'qcld_wpbot_core_context', 'qcld_wpbot_core_context_docs', 'qcld_wpbot_core_social_apps', 'qcld_wpbot_core_connection_status', 'qcld_wpbot_core_spend', 'qcld_wpbot_core_spend_state', 'qcld_wpbot_core_spend_db_version', 'qcld_wpbot_core_errors_db_version', 'qcld_wpbot_core_errors_settings' ) as $option ) {
			delete_option( $option );
		}
		delete_transient( 'qcld_wpbot_core_daily_sweep' );
		if ( function_exists( 'delete_metadata' ) ) {
			delete_metadata( 'user', 0, 'qcld_wpbot_core_context_notice_hidden', '', true );
		}
	}
}
