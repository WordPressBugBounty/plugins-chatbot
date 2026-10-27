<?php
/**
 * CRM module bootstrap. Required once from the main plugin file.
 *
 * @package QuantumCloud\WPBot\CRM
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WPBOT_CRM_LOADED' ) ) {
	return;
}
define( 'WPBOT_CRM_LOADED', true );

require_once __DIR__ . '/autoload.php';

\QuantumCloud\WPBot\CRM\Plugin::boot();
