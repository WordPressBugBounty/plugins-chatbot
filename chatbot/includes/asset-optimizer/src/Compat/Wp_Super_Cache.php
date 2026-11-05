<?php
/**
 * WP Super Cache (checked against 3.1.5): page cache only; purge with
 * wp_cache_clear_cache().
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer\Compat;

defined( 'ABSPATH' ) || exit;

/**
 * WP Super Cache.
 */
class Wp_Super_Cache extends Integration {

	/** {@inheritDoc} */
	public function slug() {
		return 'wp-super-cache';
	}

	/** {@inheritDoc} */
	public function active() {
		return function_exists( 'wp_cache_clear_cache' );
	}

	/** {@inheritDoc} */
	public function automatic() {
		return true;
	}

	/** {@inheritDoc} */
	public function can_purge() {
		return function_exists( 'wp_cache_clear_cache' );
	}

	/** {@inheritDoc} */
	public function purge() {
		if ( ! $this->can_purge() ) {
			return false;
		}
		wp_cache_clear_cache();
		return true;
	}
}
