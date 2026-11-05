<?php
/**
 * Uninstall helper: hosts call qcld_wpbot_seo_uninstall_for_host() from
 * their uninstall.php, after the Pro helper and before WPBot Core's.
 *
 * Data is deleted only when the owner turned on "Delete data on uninstall"
 * and no other host still bundles the module. Posts, SEO plugin fields and
 * the old SEO Analyzer's tables are never touched.
 *
 * @package QuantumCloud\SeoAiSearch
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_seo_uninstall_for_host' ) ) {
	/**
	 * Remove one host; delete data when it was the last and the owner opted in.
	 *
	 * @param string $host_id Host id.
	 * @return void
	 */
	function qcld_wpbot_seo_uninstall_for_host( $host_id ) {
		global $wpdb;
		$hosts = get_option( 'qcld_wpbot_seo_hosts', array() );
		$hosts = array_values( array_diff( is_array( $hosts ) ? $hosts : array(), array( (string) $host_id ) ) );
		update_option( 'qcld_wpbot_seo_hosts', $hosts, false );
		if ( array() !== $hosts ) {
			return;
		}

		wp_clear_scheduled_hook( 'qcld_wpbot_seo_daily' );
		wp_clear_scheduled_hook( 'qcld_wpbot_seo_site_topics' );
		wp_clear_scheduled_hook( 'qcld_wpbot_seo_llms_rebuild' );
		wp_clear_scheduled_hook( 'qcld_wpbot_seo_llms_check_soon' );
		wp_clear_scheduled_hook( 'qcld_wpbot_seo_weekly_email' );
		// Pro events and data (the Pro module is not loaded at uninstall).
		wp_clear_scheduled_hook( 'qcld_wpbot_seo_pro_scan' );
		wp_clear_scheduled_hook( 'qcld_wpbot_seo_pro_competitor_recheck' );
		wp_clear_scheduled_hook( 'qcld_wpbot_seo_pro_links_daily' );
		wp_clear_scheduled_hook( 'qcld_wpbot_seo_pro_kw_refresh' );
		wp_clear_scheduled_hook( 'qcld_wpbot_seo_pro_visibility' );
		wp_clear_scheduled_hook( 'qcld_wpbot_seo_pro_report' );
		wp_clear_scheduled_hook( 'qcld_wpbot_seo_pro_ranks' );
		wp_clear_scheduled_hook( 'qcld_wpbot_seo_opps_rebuild' );
		wp_clear_scheduled_hook( 'qcld_wpbot_seo_pro_cited_checks' );
		wp_clear_scheduled_hook( 'qcld_wpbot_seo_pro_reviews_daily' );
		wp_unschedule_hook( 'qcld_wpbot_seo_pro_review_request' );

		$settings = get_option( 'qcld_wpbot_seo_settings', array() );
		if ( empty( $settings['delete_on_uninstall'] ) ) {
			return;
		}

		$tables = array( 'urls', 'queries', 'gsc_daily', 'ga_daily', 'opportunities', 'scans', 'scan_pages', 'issues', 'changes', 'redirects', 'not_found', 'locations', 'links', 'prospects', 'outreach', 'outreach_steps', 'exchanges', 'research', 'audits', 'ads', 'pro_autolinks', 'pro_prompts', 'pro_runs', 'pro_answers', 'cited_places', 'cited_posts', 'reviews' );
		foreach ( $tables as $name ) {
			$table = $wpdb->prefix . 'qcld_wpbot_seo_' . $name;
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed names, uninstall only.
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_qcld_wpbot_seo_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall only.
		foreach ( array( 'qcld_wpbot_seo_settings', 'qcld_wpbot_seo_secrets', 'qcld_wpbot_seo_db_version', 'qcld_wpbot_seo_hosts', 'qcld_wpbot_seo_gsc_state', 'qcld_wpbot_seo_ga_state', 'qcld_wpbot_seo_pagespeed', 'qcld_wpbot_seo_opps_built', 'qcld_wpbot_seo_email_state', 'qcld_wpbot_seo_link_summary', 'qcld_wpbot_seo_bing_links', 'qcld_wpbot_seo_citations', 'qcld_wpbot_seo_pro_settings', 'qcld_wpbot_seo_pro_alerts', 'qcld_wpbot_seo_pro_links_state', 'qcld_wpbot_seo_pro_kw_state', 'qcld_wpbot_seo_pro_location_notes', 'qcld_wpbot_seo_pro_competing_seen', 'qcld_wpbot_seo_pro_rank_state', 'qcld_wpbot_seo_pro_rank_last', 'qcld_wpbot_seo_ads_state', 'qcld_wpbot_seo_ads_seen', 'qcld_wpbot_seo_pro_ads_state', 'qcld_wpbot_seo_competitors_seeded', 'qcld_wpbot_seo_competitors_auto', 'qcld_wpbot_seo_competitor_prefs', 'qcld_wpbot_seo_pro_competitor_recheck', 'qcld_wpbot_seo_pro_opps_seen', 'qcld_wpbot_seo_first_scan', 'qcld_wpbot_seo_topic_gaps', 'qcld_wpbot_seo_llms_cache', 'qcld_wpbot_seo_llms_changed', 'qcld_wpbot_seo_llms_check', 'qcld_wpbot_seo_cited_focus', 'qcld_wpbot_seo_gbp_links', 'qcld_wpbot_seo_area_plan', 'qcld_wpbot_seo_reviews_synced', 'qcld_wpbot_seo_pro_review_optout', 'qcld_wpbot_seo_pro_review_sent' ) as $option ) {
			delete_option( $option );
		}
		delete_transient( 'qcld_wpbot_seo_pro_patterns' );
		delete_transient( 'qcld_wpbot_seo_site_health' );
		/**
		 * Fires after the module's data was deleted (extensions clean up).
		 */
		do_action( 'qcld_wpbot_seo/delete_all' );
	}
}
