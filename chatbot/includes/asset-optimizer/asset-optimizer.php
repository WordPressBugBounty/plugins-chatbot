<?php
/**
 * WPBot Asset Cache core module.
 *
 * Bundled by host plugins at includes/asset-optimizer/ (WPBot Pro and WPBot
 * Free, the same files). Each host requires this file once from its
 * main plugin file and registers its adapter on `qcld_wpbot_ao_loaded`.
 * The loader boots only the newest bundled copy.
 *
 * @package QuantumCloud\AssetOptimizer
 */

defined( 'ABSPATH' ) || exit;

// PHP 7.4 and WordPress 5.7 (wp_print_script_tag() and wp_print_inline_script_tag()) or newer;
// on anything older the module stays out and the host works as before.
if ( version_compare( PHP_VERSION, '7.4', '<' ) || ! function_exists( 'wp_print_inline_script_tag' ) ) {
	return;
}

require_once __DIR__ . '/loader.php';

qcld_wpbot_ao_register_version( '0.7.5', __DIR__ );
