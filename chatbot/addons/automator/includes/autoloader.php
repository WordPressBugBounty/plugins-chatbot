<?php
/**
 * Autoloader for WPbot Automator
 *
 * @package WPbot_Automator
 */

namespace WPbot_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoloader class
 */
class Autoloader {

	/**
	 * Run autoloader.
	 */
	public static function run() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class Class name.
	 */
	public static function autoload( $class ) {
		// Check if the class uses our namespace.
		if ( strpos( $class, 'WPbot_Automator\\' ) !== 0 ) {
			return;
		}

		// Remove namespace prefix.
		$class = str_replace( 'WPbot_Automator\\', '', $class );

		// Convert class name to file path.
		$file = str_replace( '_', '-', strtolower( $class ) );
		$file = str_replace( '\\', DIRECTORY_SEPARATOR, $file );

		// Build the file path.
		$path = WPBOT_AUTOMATOR_PLUGIN_DIR . 'includes/' . $file . '.php';

		// Include the file if it exists.
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}
