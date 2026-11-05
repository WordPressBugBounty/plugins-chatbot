<?php
/**
 * W3 Total Cache (hooks checked against 2.11.0): skip WPBot's tags in JS and
 * CSS auto-minification; purge with w3tc_flush_all().
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer\Compat;

defined( 'ABSPATH' ) || exit;

/**
 * W3 Total Cache.
 */
class W3_Total_Cache extends Integration {

	/** {@inheritDoc} */
	public function slug() {
		return 'w3-total-cache';
	}

	/** {@inheritDoc} */
	public function active() {
		return defined( 'W3TC' );
	}

	/** {@inheritDoc} */
	public function automatic() {
		return true;
	}

	/** {@inheritDoc} */
	public function exclude() {
		$x = $this->x;
		foreach ( array( 'w3tc_minify_js_do_tag_minification', 'w3tc_minify_css_do_tag_minification' ) as $hook ) {
			add_filter(
				$hook,
				static function ( $minify, $tag = '', $file = '' ) use ( $x ) {
					return ( $x->matches( (string) $file ) || $x->matches( (string) $tag ) ) ? false : $minify;
				},
				10,
				3
			);
		}
	}

	/** {@inheritDoc} */
	public function can_purge() {
		return function_exists( 'w3tc_flush_all' );
	}

	/** {@inheritDoc} */
	public function purge() {
		if ( ! $this->can_purge() ) {
			return false;
		}
		w3tc_flush_all();
		return true;
	}
}
