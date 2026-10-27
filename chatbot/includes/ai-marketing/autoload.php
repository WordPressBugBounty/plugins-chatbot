<?php
/**
 * Autoloader for the CRM module.
 *
 * QuantumCloud\WPBot\CRM\Domain\ContactRepository maps to
 * includes/ai-marketing/Domain/ContactRepository.php.
 *
 * @package QuantumCloud\WPBot\CRM
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'QuantumCloud\\WPBot\\CRM\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		if ( '' === $relative || false !== strpos( $relative, '..' ) ) {
			return;
		}
		$file = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
