<?php
/**
 * JSX runtime for WordPress 6.2–6.5.
 *
 * The admin app is built with @wordpress/scripts, which depends on the
 * "react-jsx-runtime" script WordPress ships only from 6.6. On older
 * versions this registers a tiny equivalent on top of WordPress's React,
 * only when the handle is missing (AI Writer registers the same shim; the
 * first one wins).
 *
 * @package QuantumCloud\SeoAiSearch
 */

namespace QuantumCloud\SeoAiSearch\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the JSX runtime when WordPress lacks it.
 */
final class Jsx_Runtime {

	/**
	 * Script handle WordPress 6.6+ provides.
	 */
	const HANDLE = 'react-jsx-runtime';

	/**
	 * Hook into default script registration.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'wp_default_scripts', array( __CLASS__, 'register' ), 20 );
	}

	/**
	 * Register the shim when the handle is missing.
	 *
	 * @param \WP_Scripts $scripts Scripts registry.
	 * @return void
	 */
	public static function register( $scripts ) {
		if ( ! $scripts instanceof \WP_Scripts || $scripts->query( self::HANDLE, 'registered' ) ) {
			return;
		}
		$scripts->add( self::HANDLE, false, array( 'react' ) );
		$scripts->add_inline_script(
			self::HANDLE,
			'window.ReactJSXRuntime=window.ReactJSXRuntime||(function(R){'
			. 'function j(t,p,k){var q=p||{};if(k!==undefined){q=Object.assign({},q,{key:k});}return R.createElement(t,q);}'
			. 'return {jsx:j,jsxs:j,Fragment:R.Fragment};})(window.React);',
			'after'
		);
	}
}
