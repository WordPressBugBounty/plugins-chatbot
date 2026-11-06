<?php
/**
 * The built scripts use React's automatic JSX runtime, which WordPress
 * registers as "react-jsx-runtime" from 6.6. On older versions (6.2 to 6.5)
 * this registers a small equivalent built on React.createElement, so the
 * AI Writer screens load there too. It does nothing when WordPress has it.
 *
 * @package QuantumCloud\AiWriter
 */

namespace QuantumCloud\AiWriter\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the JSX runtime handle when missing.
 */
final class Jsx_Runtime {

	const HANDLE = 'react-jsx-runtime';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function hook() {
		add_action( 'wp_default_scripts', array( __CLASS__, 'register' ), 20 );
	}

	/**
	 * Register the handle on WordPress versions without it.
	 *
	 * @param \WP_Scripts $scripts Scripts registry.
	 * @return void
	 */
	public static function register( $scripts ) {
		if ( ! $scripts instanceof \WP_Scripts || $scripts->query( self::HANDLE, 'registered' ) ) {
			return;
		}
		$scripts->add( self::HANDLE, false, array( 'react' ) );
		$scripts->add_inline_script( self::HANDLE, self::source(), 'after' );
	}

	/**
	 * The jsx() and jsxs() helpers pass children inside props and the key apart;
	 * React.createElement accepts the same props with the key added back.
	 *
	 * @return string
	 */
	private static function source() {
		return 'window.ReactJSXRuntime=window.ReactJSXRuntime||(function(R){'
			. 'function j(t,p,k){var q=p||{};if(k!==undefined){q=Object.assign({},q,{key:k});}return R.createElement(t,q);}'
			. 'return {jsx:j,jsxs:j,Fragment:R.Fragment};})(window.React);';
	}
}
