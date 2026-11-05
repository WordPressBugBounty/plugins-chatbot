<?php
/**
 * WP Rocket (hooks checked against 3.24): minify/combine, defer, delay JS,
 * load CSS asynchronously, remove unused CSS; purge with rocket_clean_domain().
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer\Compat;

defined( 'ABSPATH' ) || exit;

/**
 * WP Rocket.
 */
class Wp_Rocket extends Integration {

	/** {@inheritDoc} */
	public function slug() {
		return 'wp-rocket';
	}

	/** {@inheritDoc} */
	public function active() {
		return defined( 'WP_ROCKET_VERSION' );
	}

	/** {@inheritDoc} */
	public function automatic() {
		return true;
	}

	/** {@inheritDoc} */
	public function exclude() {
		$x       = $this->x;
		$js      = $x->patterns( '(.*).js' );
		$css     = $x->patterns( '(.*).css' );
		$markers = array_map(
			static function ( $marker ) {
				return preg_quote( $marker, '#' );
			},
			$x->markers
		);
		$jquery  = array_map(
			static function ( $path ) {
				return preg_quote( $path, '#' );
			},
			$x->jquery
		);
		add_filter(
			'rocket_exclude_js',
			static function ( $items ) use ( $js ) {
				return self::add( $items, $js );
			}
		);
		add_filter(
			'rocket_exclude_css',
			static function ( $items ) use ( $css ) {
				return self::add( $items, $css );
			}
		);
		add_filter(
			'rocket_exclude_defer_js',
			static function ( $items ) use ( $js ) {
				return self::add( $items, $js );
			}
		);
		add_filter(
			'rocket_delay_js_exclusions',
			static function ( $items ) use ( $js, $markers, $jquery ) {
				return self::add( $items, array_merge( $js, $markers, $jquery ) );
			}
		);
		add_filter(
			'rocket_excluded_inline_js_content',
			static function ( $items ) use ( $x ) {
				return self::add( $items, $x->markers );
			}
		);
		add_filter(
			'rocket_exclude_async_css',
			static function ( $items ) use ( $css ) {
				return self::add( $items, $css );
			}
		);
		add_filter(
			'rocket_rucss_safelist',
			static function ( $items ) use ( $css, $x ) {
				return self::add( $items, array_merge( $css, $x->selectors ) );
			}
		);
		add_filter(
			'rocket_rucss_inline_content_exclusions',
			static function ( $items ) {
				return self::add( $items, array( 'wpbot-ao-', 'wp-chatbot' ) );
			}
		);
	}

	/** {@inheritDoc} */
	public function can_purge() {
		return function_exists( 'rocket_clean_domain' );
	}

	/** {@inheritDoc} */
	public function purge() {
		if ( ! $this->can_purge() ) {
			return false;
		}
		rocket_clean_domain();
		return true;
	}
}
