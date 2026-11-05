<?php
/**
 * What optimisers must leave alone while WPBot's cache is on: WPBot's
 * folders, handles, inline script markers and chatbot selectors.
 *
 * @package QuantumCloud\AssetOptimizer
 */

namespace QuantumCloud\AssetOptimizer\Compat;

defined( 'ABSPATH' ) || exit;

/**
 * Value object.
 */
class Exclusions {

	/**
	 * URL paths of WPBot's plugin folder and the cache folder (with trailing slash).
	 *
	 * @var string[]
	 */
	public $paths = array();

	/**
	 * Script handles.
	 *
	 * @var string[]
	 */
	public $scripts = array();

	/**
	 * Style handles.
	 *
	 * @var string[]
	 */
	public $styles = array();

	/**
	 * Strings that mark WPBot's inline scripts and styles.
	 *
	 * @var string[]
	 */
	public $markers = array();

	/**
	 * CSS selectors of the chatbot (for "remove unused CSS" safelists).
	 *
	 * @var string[]
	 */
	public $selectors = array();

	/**
	 * The jQuery files: WPBot needs jQuery, so it must not be delayed either.
	 *
	 * @var string[]
	 */
	public $jquery = array( '/wp-includes/js/jquery/jquery.min.js', '/wp-includes/js/jquery/jquery.js', '/wp-includes/js/jquery/jquery-migrate' );

	/**
	 * Every path and marker (for plugins that match substrings anywhere in a tag).
	 *
	 * @return string[]
	 */
	public function strings() {
		return array_values( array_unique( array_merge( $this->paths, $this->markers ) ) );
	}

	/**
	 * Paths as regular-expression fragments (for plugins that match patterns).
	 *
	 * @param string $suffix Pattern appended to each path, for example "(.*).js".
	 * @return string[]
	 */
	public function patterns( $suffix = '(.*)' ) {
		$out = array();
		foreach ( $this->paths as $path ) {
			$out[] = preg_quote( $path, '#' ) . $suffix;
		}
		return $out;
	}

	/**
	 * Whether a URL or path is one of WPBot's files.
	 *
	 * @param string $url URL or path.
	 * @return bool
	 */
	public function matches( $url ) {
		foreach ( $this->paths as $path ) {
			if ( '' !== $path && false !== strpos( (string) $url, $path ) ) {
				return true;
			}
		}
		return false;
	}
}
