<?php
/**
 * Autoloader for the booted copy of the chatbot module.
 *
 * QuantumCloud\Chatbot\Modules\Registry lives in src/Modules/Registry.php.
 *
 * @package QuantumCloud\Chatbot
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'QuantumCloud\\Chatbot\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$path = __DIR__ . '/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
