<?php
/**
 * Public API for other modules (plan 9.1). Names are fixed; copy them word
 * for word into other briefs.
 *
 * @package QuantumCloud\SeoAiSearch
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_seo_get_page_data' ) ) {
	/**
	 * Search data for one page over the last 28 days of final data.
	 *
	 * @param int $post_id Post id.
	 * @return array|null clicks, impressions, ctr, position, main_query, open_issues, audit_score, ai_readiness, period{start,end}; null when the post does not exist.
	 */
	function qcld_wpbot_seo_get_page_data( $post_id ) {
		return \QuantumCloud\SeoAiSearch\Api\Page_Data::get( (int) $post_id );
	}
}

if ( ! function_exists( 'qcld_wpbot_seo_get_dashboard_data' ) ) {
	/**
	 * Headline numbers for the Growth Dashboard.
	 *
	 * @param array $args days (default 28).
	 * @return array totals, movers, critical_issues, ai_visibility_score, connections.
	 */
	function qcld_wpbot_seo_get_dashboard_data( array $args = array() ) {
		return \QuantumCloud\SeoAiSearch\Api\Dashboard_Data::get( $args );
	}
}
