<?php
/**
 * Public error log function for modules, hosts and add-ons.
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_core_log_error' ) ) {
	/**
	 * Record an error in WPBot's error log (AI Settings > Error log). Secrets are removed;
	 * never pass visitor messages.
	 *
	 * @param string $source  php, fatal, http, ai, job, mail, js or other.
	 * @param string $message What happened.
	 * @param array  $detail  Optional: level (error|warning|notice), module, file, line, code, and other short facts.
	 * @return bool Whether it was stored.
	 */
	function qcld_wpbot_core_log_error( $source, $message, array $detail = array() ) {
		return \QuantumCloud\WPBotCore\Diagnostics\Error_Log::add( (string) $source, (string) $message, $detail );
	}
}
