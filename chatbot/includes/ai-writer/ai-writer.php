<?php
/**
 * WPBot AI Writer core module.
 *
 * Bundled by host plugins at includes/ai-writer/ (WPBot Pro now, WPBot Free
 * later, the same files). Needs WPBot Core (includes/wpbot-core/) loaded
 * first. The loader boots only the newest bundled copy.
 *
 * @package QuantumCloud\AiWriter
 */

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	return;
}

require_once __DIR__ . '/loader.php';

qcld_wpbot_writer_register_version( '0.28.0', __DIR__ );
