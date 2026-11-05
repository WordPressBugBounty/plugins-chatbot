<?php
/**
 * Which SEO plugin is active, and what it already prints.
 *
 * One place for detection, so the module never prints meta, schema or
 * redirects an SEO plugin already handles.
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * SEO plugin detection.
 */
final class Seo_Plugins {

	/**
	 * Active SEO plugin id: yoast, rankmath, aioseo, seopress, slimseo, tsf, or ''.
	 *
	 * @return string
	 */
	public static function active() {
		$id = '';
		if ( defined( 'WPSEO_VERSION' ) ) {
			$id = 'yoast';
		} elseif ( defined( 'RANK_MATH_VERSION' ) || class_exists( '\RankMath' ) ) {
			$id = 'rankmath';
		} elseif ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			$id = 'aioseo';
		} elseif ( defined( 'SEOPRESS_VERSION' ) ) {
			$id = 'seopress';
		} elseif ( defined( 'SLIM_SEO_VER' ) ) {
			$id = 'slimseo';
		} elseif ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
			$id = 'tsf';
		}
		/**
		 * Active SEO plugin id ('' when none).
		 *
		 * @param string $id Detected id.
		 */
		return (string) apply_filters( 'qcld_wpbot_seo/seo_plugin', $id );
	}

	/**
	 * Readable name of the active SEO plugin.
	 *
	 * @return string
	 */
	public static function label() {
		$labels = array(
			'yoast'    => 'Yoast SEO',
			'rankmath' => 'Rank Math',
			'aioseo'   => 'All in One SEO',
			'seopress' => 'SEOPress',
			'slimseo'  => 'Slim SEO',
			'tsf'      => 'The SEO Framework',
		);
		$id     = self::active();
		return isset( $labels[ $id ] ) ? $labels[ $id ] : '';
	}

	/**
	 * Whether an SEO plugin prints meta tags and schema.
	 *
	 * @return bool
	 */
	public static function handles_head() {
		return '' !== self::active();
	}

	/**
	 * Whether a redirect manager handles redirects (SEO plugin modules or
	 * the Redirection plugin).
	 *
	 * @return bool
	 */
	public static function handles_redirects() {
		$handled = defined( 'REDIRECTION_VERSION' )
			|| class_exists( '\RankMath\Redirections\Redirections' )
			|| defined( 'WPSEO_PREMIUM_VERSION' )
			|| class_exists( '\AIOSEO\Plugin\Pro\Redirects\Redirects' )
			|| defined( 'SEOPRESS_PRO_VERSION' );
		return (bool) apply_filters( 'qcld_wpbot_seo/redirects_handled', $handled );
	}
}
