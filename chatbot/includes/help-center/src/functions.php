<?php
/**
 * Public functions of the Help Center module.
 *
 * @package QuantumCloud\HelpCenter
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_wpbot_help_center' ) ) {
	/**
	 * The booted Help Center module.
	 *
	 * @return \QuantumCloud\HelpCenter\Module
	 */
	function qcld_wpbot_help_center() {
		return \QuantumCloud\HelpCenter\Module::instance();
	}
}

if ( ! function_exists( 'qcld_wpbot_help_center_is_on' ) ) {
	/**
	 * Whether the Help Center is switched on.
	 *
	 * @return bool
	 */
	function qcld_wpbot_help_center_is_on() {
		return \QuantumCloud\HelpCenter\Feature_Switch::is_on();
	}
}
