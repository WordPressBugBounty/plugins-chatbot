<?php
/**
 * Autoloader for the booted copy of WPBot Core.
 *
 * QuantumCloud\WPBotCore\AI\Keys lives in src/AI/Keys.php.
 *
 * @package QuantumCloud\WPBotCore
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'QuantumCloud\\WPBotCore\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$path = __DIR__ . '/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

require_once __DIR__ . '/AI/functions.php';
