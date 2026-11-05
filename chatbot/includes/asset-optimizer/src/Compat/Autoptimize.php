<?php
/**
 * Autoptimize (hooks checked against 3.1.17): JS and CSS aggregation
 * excludes (comma-separated substrings) and "consider minified" lists, so
 * excluded WPBot files are not minified a second time. jQuery stays out of
 * the aggregated bundle too: WPBot's scripts need it before the bundle runs.
 * Autoptimize has no page cache.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer\Compat;

defined( 'ABSPATH' ) || exit;

/**
 * Autoptimize.
 */
class Autoptimize extends Integration {

	/** {@inheritDoc} */
	public function slug() {
		return 'autoptimize';
	}

	/** {@inheritDoc} */
	public function active() {
		return defined( 'AUTOPTIMIZE_PLUGIN_VERSION' );
	}

	/** {@inheritDoc} */
	public function automatic() {
		return true;
	}

	/** {@inheritDoc} */
	public function exclude() {
		$paths = $this->x->paths;
		$js    = implode( ', ', array_merge( $this->x->strings(), $this->x->jquery ) );
		$css   = implode( ', ', array_merge( $paths, array( 'wpbot-ao-' ) ) );
		foreach ( array( 'autoptimize_filter_js_consider_minified', 'autoptimize_filter_css_consider_minified' ) as $hook ) {
			add_filter(
				$hook,
				static function ( $items ) use ( $paths ) {
					return self::add( $items, $paths );
				}
			);
		}
		add_filter(
			'autoptimize_filter_js_exclude',
			static function ( $items ) use ( $js ) {
				return trim( (string) $items . ', ' . $js, ', ' );
			}
		);
		add_filter(
			'autoptimize_filter_css_exclude',
			static function ( $items ) use ( $css ) {
				return trim( (string) $items . ', ' . $css, ', ' );
			}
		);
	}
}
