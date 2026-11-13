<?php
/**
 * WPBot Social core module.
 *
 * Bundled by WPBot Pro (Master) at includes/social/. Needs WPBot Core
 * (includes/wpbot-core/) loaded first. The loader boots only the newest
 * bundled copy.
 *
 * @package QuantumCloud\Social
 */

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	return;
}

require_once __DIR__ . '/loader.php';

qcld_wpbot_social_register_version( '0.2.3', __DIR__ );
