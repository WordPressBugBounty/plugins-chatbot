<?php
/**
 * WPBot AI Marketing core module.
 *
 * Bundled by WPBot Pro and WPBot Free at includes/ai-marketing/. Needs WPBot
 * Core (includes/wpbot-core/) loaded first. The loader boots only the newest
 * bundled copy.
 *
 * @package QuantumCloud\AiMarketing
 */

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	return;
}

require_once __DIR__ . '/loader.php';

qcld_wpbot_marketing_register_version( '2.0.0-dev.1', __DIR__ );
