<?php
/**
 * Autoloader for the booted copy of AI Writer.
 *
 * QuantumCloud\AiWriter\Admin\Admin_Controller lives in src/Admin/Admin_Controller.php.
 *
 * @package QuantumCloud\AiWriter
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'QuantumCloud\\AiWriter\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$path = __DIR__ . '/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
