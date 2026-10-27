<?php
/**
 * PSR-4 style autoloader for the booted copy of the module.
 *
 * Class QuantumCloud\Insights\Storage\Local_File_Store maps to
 * src/Storage/Local_File_Store.php.
 *
 * @package QuantumCloud\Insights
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'QuantumCloud\\Insights\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
