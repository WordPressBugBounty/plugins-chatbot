<?php
/**
 * Public AI spending functions for modules, hosts and add-ons.
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_core_spend_allow' ) ) {
	/**
	 * Whether an AI call may run (spending limits, paused features).
	 *
	 * @param string $module   Module id.
	 * @param string $feature  Feature id.
	 * @param float  $estimate Expected cost in USD (0 when unknown).
	 * @return true|WP_Error WP_Error code qcld_wpbot_core_budget_reached when refused.
	 */
	function qcld_wpbot_core_spend_allow( $module, $feature, $estimate = 0.0 ) {
		return \QuantumCloud\WPBotCore\Spend\Spend::allow( $module, $feature, $estimate );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_spend_record' ) ) {
	/**
	 * Record what an AI call used. See Spend::record().
	 *
	 * @param string $module  Module id.
	 * @param string $feature Feature id.
	 * @param array  $usage   tokens_in, tokens_out, tokens_cached, images, characters, audio_seconds, web_searches, kind.
	 * @param array  $meta    provider, model, task, job_id, channel, cost.
	 * @return array id, cost, price_known, group.
	 */
	function qcld_wpbot_core_spend_record( $module, $feature, array $usage, array $meta = array() ) {
		return \QuantumCloud\WPBotCore\Spend\Spend::record( $module, $feature, $usage, $meta );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_spend_wrap' ) ) {
	/**
	 * Allow, run your own AI call (for example wp_remote_post), read its usage and record it.
	 *
	 * @param string   $module  Module id.
	 * @param string   $feature Feature id.
	 * @param callable $call    Returns a wp_remote_* response, a WP_Error, or anything with $meta['usage'].
	 * @param array    $meta    provider, model, task, job_id, estimate, usage.
	 * @return mixed|WP_Error
	 */
	function qcld_wpbot_core_spend_wrap( $module, $feature, $call, array $meta = array() ) {
		return \QuantumCloud\WPBotCore\Spend\Spend::wrap( $module, $feature, $call, $meta );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_spend_estimate_job' ) ) {
	/**
	 * Pre-flight check for an expensive job.
	 *
	 * @param string $module   Module id.
	 * @param string $feature  Feature id.
	 * @param float  $estimate Estimated cost of the job in USD.
	 * @return array estimate, remaining (null = no limit), fits, scope.
	 */
	function qcld_wpbot_core_spend_estimate_job( $module, $feature, $estimate ) {
		return \QuantumCloud\WPBotCore\Spend\Spend::estimate_job( $module, $feature, $estimate );
	}
}

if ( ! function_exists( 'qcld_wpbot_core_spend_status' ) ) {
	/**
	 * Spend against limits for the current period.
	 *
	 * @return array period, global, groups.
	 */
	function qcld_wpbot_core_spend_status() {
		return \QuantumCloud\WPBotCore\Spend\Spend::status();
	}
}
