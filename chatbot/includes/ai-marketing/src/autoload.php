<?php
/**
 * Autoloader for the booted copy of WPBot AI Marketing.
 *
 * QuantumCloud\AiMarketing\Storage\Schema lives in src/Storage/Schema.php.
 *
 * @package QuantumCloud\AiMarketing
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'QuantumCloud\\AiMarketing\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$path = __DIR__ . '/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
