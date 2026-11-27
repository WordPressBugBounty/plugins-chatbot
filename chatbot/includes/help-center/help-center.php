<?php
/**
 * WPBot Help Center core module.
 *
 * Bundled by host plugins at includes/help-center/ (WPBot Free and WPBot Pro,
 * the same files). Each host requires this file once from its main plugin file
 * and registers its adapter on `qcld_wpbot_help_center/loaded`. The loader boots
 * only the newest bundled copy.
 *
 * The Help Center is off by default. It is switched on from AI / Settings (its
 * own option, not a chatbot module); while it is off, no post type, menu, rewrite rule or front-end code loads.
 *
 * @package QuantumCloud\HelpCenter
 */

defined( 'ABSPATH' ) || exit;

// PHP 7.4 or newer; on anything older the module stays out and the host works as before.
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	return;
}

require_once __DIR__ . '/loader.php';

qcld_wpbot_help_center_register_version( '0.20.0', __DIR__ );
