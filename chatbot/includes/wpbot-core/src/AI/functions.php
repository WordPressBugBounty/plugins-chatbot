<?php
/**
 * Public AI functions shared by every module.
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_core_ai_readiness' ) ) {
	/**
	 * Whether AI is ready for a module, and how to fix it when it is not.
	 *
	 * @param string $module Module id (for example seo or writer).
	 * @return array status (ready, no_key, not_enabled, no_model, failing, budget_reached), ready, cause, message, action, action_label, provider, provider_label, url, warning.
	 */
	function qcld_wpbot_core_ai_readiness( $module ) {
		return \QuantumCloud\WPBotCore\AI\AI_Readiness::check( $module );
	}
}
