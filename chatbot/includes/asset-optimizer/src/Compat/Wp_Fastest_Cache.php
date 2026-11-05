<?php
/**
 * WP Fastest Cache (checked against 1.5.4): purge through its own
 * wpfc_clear_all_cache action. Its minify options (premium) have no
 * exclusion filter, so they are listed as a manual step.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer\Compat;

defined( 'ABSPATH' ) || exit;

/**
 * WP Fastest Cache.
 */
class Wp_Fastest_Cache extends Integration {

	/** {@inheritDoc} */
	public function slug() {
		return 'wp-fastest-cache';
	}

	/** {@inheritDoc} */
	public function active() {
		return class_exists( 'WpFastestCache', false );
	}

	/** {@inheritDoc} */
	public function can_purge() {
		return has_action( 'wpfc_clear_all_cache' ) !== false;
	}

	/** {@inheritDoc} */
	public function purge() {
		if ( ! $this->can_purge() ) {
			return false;
		}
		do_action( 'wpfc_clear_all_cache', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WP Fastest Cache's own purge action.
		return true;
	}
}
