<?php
/**
 * WPBot Core: shared services for the WPBot Growth Suite.
 *
 * Bundled by host plugins (WPBot Pro now, WPBot Free later) at
 * includes/wpbot-core/. Every host includes this file; the loader then boots
 * only the newest bundled copy.
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

// The module uses PHP 7.4 syntax. Older PHP: stay silent, the host warns.
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	return;
}

require_once __DIR__ . '/loader.php';

qcld_wpbot_core_register_version( '0.15.0', __DIR__ );
