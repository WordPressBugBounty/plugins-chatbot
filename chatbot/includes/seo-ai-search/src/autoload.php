<?php
/**
 * Class autoloader for the module: QuantumCloud\SeoAiSearch\X\Y => src/X/Y.php.
 *
 * @package QuantumCloud\SeoAiSearch
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'QuantumCloud\\SeoAiSearch\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$path = __DIR__ . '/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
