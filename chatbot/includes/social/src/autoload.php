<?php
/**
 * Autoloader for the booted copy of WPBot Social.
 *
 * QuantumCloud\Social\Networks\Facebook lives in src/Networks/Facebook.php.
 *
 * @package QuantumCloud\Social
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'QuantumCloud\\Social\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$path = __DIR__ . '/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
