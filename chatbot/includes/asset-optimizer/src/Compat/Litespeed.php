<?php
/**
 * LiteSpeed Cache (hooks checked against 7.9.2): JS/CSS minify and combine
 * excludes, JS defer/delay excludes, guest-mode JS excludes, UCSS/CCSS
 * selector safelists; purge with the litespeed_purge_all action.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer\Compat;

defined( 'ABSPATH' ) || exit;

/**
 * LiteSpeed Cache.
 */
class Litespeed extends Integration {

	/** {@inheritDoc} */
	public function slug() {
		return 'litespeed-cache';
	}

	/** {@inheritDoc} */
	public function active() {
		return defined( 'LSCWP_V' );
	}

	/** {@inheritDoc} */
	public function automatic() {
		return true;
	}

	/** {@inheritDoc} */
	public function exclude() {
		$x     = $this->x;
		$js    = array_merge( $x->strings(), $x->jquery );
		$paths = $x->paths;
		foreach ( array( 'litespeed_optimize_js_excludes', 'litespeed_optm_js_defer_exc', 'litespeed_optm_gm_js_exc' ) as $hook ) {
			add_filter(
				$hook,
				static function ( $items ) use ( $js ) {
					return self::add( $items, $js );
				}
			);
		}
		add_filter(
			'litespeed_optimize_css_excludes',
			static function ( $items ) use ( $paths ) {
				return self::add( $items, $paths );
			}
		);
		foreach ( array( 'litespeed_ucss_whitelist', 'litespeed_ccss_whitelist' ) as $hook ) {
			add_filter(
				$hook,
				static function ( $items ) use ( $x ) {
					return self::add( $items, $x->selectors );
				}
			);
		}
	}

	/** {@inheritDoc} */
	public function can_purge() {
		return true;
	}

	/** {@inheritDoc} */
	public function purge() {
		do_action( 'litespeed_purge_all', 'WPBot asset cache cleared' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's own purge action.
		return true;
	}
}
