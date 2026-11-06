<?php
/**
 * Autoloader for AI Writer's scoped copy of Dompdf (built by tools/dompdf/build.php; do not edit).
 *
 * @package QuantumCloud\AiWriter
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class ) {
		$classmap = array (
  'QuantumCloud\\AiWriter\\Vendor\\Dompdf\\Cpdf' => 'dompdf/dompdf/lib/Cpdf.php',
);
		if ( isset( $classmap[ $class ] ) ) {
			require __DIR__ . '/' . $classmap[ $class ];
			return;
		}
		foreach ( array (
  'QuantumCloud\\AiWriter\\Vendor\\Sabberworm\\CSS\\' => 'sabberworm/php-css-parser/src/',
  'QuantumCloud\\AiWriter\\Vendor\\Masterminds\\' => 'masterminds/html5/src/',
  'QuantumCloud\\AiWriter\\Vendor\\FontLib\\' => 'dompdf/php-font-lib/src/FontLib/',
  'QuantumCloud\\AiWriter\\Vendor\\Dompdf\\' => 'dompdf/dompdf/src/',
  'QuantumCloud\\AiWriter\\Vendor\\Svg\\' => 'dompdf/php-svg-lib/src/Svg/',
) as $space => $dir ) {
			if ( 0 === strpos( $class, $space ) ) {
				$file = __DIR__ . '/' . $dir . str_replace( '\\', '/', substr( $class, strlen( $space ) ) ) . '.php';
				if ( is_readable( $file ) ) {
					require $file;
				}
				return;
			}
		}
	}
);
require_once __DIR__ . '/sabberworm/php-css-parser/src/Rule/Rule.php';
require_once __DIR__ . '/sabberworm/php-css-parser/src/RuleSet/RuleContainer.php';
