<?php
/**
 * PSR-4 autoloader for QuantumCloud\AssetOptimizer (src/).
 *
 * @package QuantumCloud\AssetOptimizer
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'QuantumCloud\\AssetOptimizer\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_file( $file ) ) {
			require_once $file;
		}
	}
);
