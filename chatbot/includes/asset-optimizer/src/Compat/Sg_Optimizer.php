<?php
/**
 * SiteGround Speed Optimizer (hooks checked against 7.8.6): JS/CSS minify
 * excludes and async JS excludes (handles), JS combine excludes (paths and
 * inline content); CSS and JS combine read their excludes from options, so
 * those options are filtered; purge with sg_cachepress_purge_everything().
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer\Compat;

defined( 'ABSPATH' ) || exit;

/**
 * SiteGround Speed Optimizer.
 */
class Sg_Optimizer extends Integration {

	/** {@inheritDoc} */
	public function slug() {
		return 'sg-cachepress';
	}

	/** {@inheritDoc} */
	public function active() {
		return function_exists( 'sg_cachepress_purge_cache' );
	}

	/** {@inheritDoc} */
	public function automatic() {
		return true;
	}

	/** {@inheritDoc} */
	public function exclude() {
		$x = $this->x;
		foreach ( array(
			'sgo_js_minify_exclude'  => $x->scripts,
			'sgo_js_async_exclude'   => $x->scripts,
			'sgo_css_minify_exclude' => $x->styles,
			'option_siteground_optimizer_combine_javascript_exclude' => $x->scripts,
			'option_siteground_optimizer_combine_css_exclude' => $x->styles,
		) as $hook => $handles ) {
			add_filter(
				$hook,
				static function ( $items ) use ( $handles ) {
					return self::add( $items, $handles );
				}
			);
		}
		add_filter(
			'sgo_javascript_combine_excluded_internal_paths',
			static function ( $items ) use ( $x ) {
				return self::add( $items, $x->paths );
			}
		);
		add_filter(
			'sgo_javascript_combine_excluded_inline_content',
			static function ( $items ) use ( $x ) {
				return self::add( $items, $x->markers );
			}
		);
	}

	/** {@inheritDoc} */
	public function can_purge() {
		return function_exists( 'sg_cachepress_purge_everything' ) || function_exists( 'sg_cachepress_purge_cache' );
	}

	/** {@inheritDoc} */
	public function purge() {
		if ( function_exists( 'sg_cachepress_purge_everything' ) ) {
			sg_cachepress_purge_everything();
			return true;
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
			return true;
		}
		return false;
	}
}
