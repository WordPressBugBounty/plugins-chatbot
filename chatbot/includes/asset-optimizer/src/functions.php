<?php
/**
 * Public API of the asset cache.
 *
 * @package QuantumCloud\AssetOptimizer
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wpbot_asset_cache' ) ) {
	/**
	 * The asset cache module: is_active(), clear(), rebuild(), get_status().
	 *
	 * @return \QuantumCloud\AssetOptimizer\Module
	 */
	function wpbot_asset_cache() {
		return \QuantumCloud\AssetOptimizer\Module::instance();
	}
}
