<?php
/**
 * WPBot Insights module bootstrap.
 *
 * A host plugin includes this file (require_once) during its own load.
 * It only registers this copy's version; the loader decides which copy runs.
 *
 * Integration (in the host plugin):
 *
 *     require_once __DIR__ . '/includes/insights/insights.php';
 *     add_action( 'qcin/loaded', function () {
 *         require_once __DIR__ . '/includes/class-my-insights-adapter.php';
 *         \QuantumCloud\Insights\Module::instance()->register_host( new My_Insights_Adapter() );
 *     } );
 *
 * @package QuantumCloud\Insights
 */

defined( 'ABSPATH' ) || exit;

// The module needs PHP 7.2 or newer (the same as WordPress itself). On older
// PHP the host plugin keeps working and Insights stays off. This file and
// loader.php only use PHP 5.6 syntax so they can make this check.
if ( version_compare( PHP_VERSION, '7.2', '<' ) ) {
	return;
}

require_once __DIR__ . '/loader.php';

qcin_register_version( '0.6.4', __DIR__ );
