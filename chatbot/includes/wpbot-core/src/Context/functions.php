<?php
/**
 * Public API of the shared Site Context (see Context\Context).
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_core_context' ) ) {
	/**
	 * The shared Site Context: summary (what the business is, focus ideas), layers, settings.
	 *
	 * @return array summary (array|null), edited, summary_at, stale, sources, settings, scanned_at, cost.
	 */
	function qcld_wpbot_core_context() {
		return \QuantumCloud\WPBotCore\Context\Context::get();
	}
}

if ( ! function_exists( 'qcld_wpbot_core_context_text' ) ) {
	/**
	 * The summary as short plain text for prompts ('' when none was built yet).
	 *
	 * @return string
	 */
	function qcld_wpbot_core_context_text() {
		return \QuantumCloud\WPBotCore\Context\Context::text();
	}
}

if ( ! function_exists( 'qcld_wpbot_core_context_refresh' ) ) {
	/**
	 * Read the free layers again (no AI); with $module, also rebuild the AI summary (costs AI credits).
	 *
	 * @param string $module Module whose AI pays for the summary ('' = no summary).
	 * @return array|WP_Error
	 */
	function qcld_wpbot_core_context_refresh( $module = '' ) {
		$state = \QuantumCloud\WPBotCore\Context\Context::refresh_facts();
		if ( is_wp_error( $state ) || '' === (string) $module ) {
			return $state;
		}
		return \QuantumCloud\WPBotCore\Context\Context::summarize( (string) $module );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_context_post_ids' ) ) {
	/**
	 * Posts the site scan found (main pages, then best-selling products).
	 *
	 * @return int[]
	 */
	function qcld_wpbot_core_context_post_ids() {
		return \QuantumCloud\WPBotCore\Context\Context::post_ids();
	}
}
