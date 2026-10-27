<?php
/**
 * A pluggable feature. Core screens and Pro features both implement this,
 * so the core module never checks for Pro.
 *
 * @package QuantumCloud\Insights
 */

namespace QuantumCloud\Insights\Features;

use QuantumCloud\Insights\Module;

defined( 'ABSPATH' ) || exit;

interface Feature {

	/**
	 * Unique id, e.g. 'core.recordings' or 'pro.funnels'.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Add hooks, routes, jobs, admin screens or schema for this feature.
	 *
	 * @param Module $module Module instance.
	 */
	public function register( Module $module );
}
