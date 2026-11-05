<?php
/**
 * Public competitor API (round 2, R2). Shared with AI Writer; see the AI
 * Writer handover brief in the SEO & AI Search repository.
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_core_competitors' ) ) {
	/**
	 * The shared competitor list.
	 *
	 * @return array[] domain, reason, source (owner|ai|keyword_provider|search_console|ai_writer|seo), added_at.
	 */
	function qcld_wpbot_core_competitors() {
		return \QuantumCloud\WPBotCore\Competitors\Competitors::all();
	}
}

if ( ! function_exists( 'qcld_wpbot_core_suggest_competitors' ) ) {
	/**
	 * Verified competitor suggestions from the module's AI. Uses AI credits: call it only when a person asks. Saves nothing.
	 *
	 * @param array $args module (default "seo"), limit (1–15, default 8), exclude (domains), optional focus
	 *                    (what to compete on) and kind (any|local|online|content).
	 * @return array|WP_Error suggestions (domain, reason, source, title), dropped (domain, why), web_search, cost, focus, kind.
	 */
	function qcld_wpbot_core_suggest_competitors( array $args = array() ) {
		return \QuantumCloud\WPBotCore\Competitors\Suggest::run( $args );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_save_competitors' ) ) {
	/**
	 * Replace the shared competitor list. The caller checks the user's permission first.
	 *
	 * @param array $items Domains or URLs, or arrays (domain, reason, source).
	 * @return array[] The saved list.
	 */
	function qcld_wpbot_core_save_competitors( array $items ) {
		return \QuantumCloud\WPBotCore\Competitors\Competitors::save( $items );
	}
}
