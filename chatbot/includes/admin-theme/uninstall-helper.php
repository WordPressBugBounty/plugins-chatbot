<?php
/**
 * Admin theme module: clean-up for a host plugin's uninstall.php.
 *
 * Call it from the host's uninstall.php:
 *
 *   if ( file_exists( __DIR__ . '/includes/admin-theme/uninstall-helper.php' ) ) {
 *       require_once __DIR__ . '/includes/admin-theme/uninstall-helper.php';
 *       qcld_admin_theme_uninstall( __DIR__ );
 *   }
 *
 * The users' theme choices are shared by every plugin that bundles the module, so they are
 * deleted only when no other installed plugin (active or not) still has a copy.
 *
 * @package QuantumCloud\AdminTheme
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'qcld_admin_theme_uninstall' ) ) {
	/**
	 * Delete the users' theme choices unless another plugin still bundles the module.
	 *
	 * @param string $host_dir The uninstalling plugin's folder (__DIR__ of its uninstall.php).
	 * @return bool Whether the data was deleted.
	 */
	function qcld_admin_theme_uninstall( $host_dir ) {
		$self   = wp_normalize_path( (string) realpath( $host_dir ) ) . '/';
		$copies = array();
		foreach ( array( '', 'includes/', 'inc/', 'modules/', 'lib/' ) as $sub ) {
			$copies = array_merge( $copies, (array) glob( WP_PLUGIN_DIR . '/*/' . $sub . 'admin-theme/admin-theme.php' ) );
		}
		foreach ( $copies as $file ) {
			// realpath() so a symlinked plugin folder still recognises its own copy.
			if ( is_string( $file ) && '' !== $file && 0 !== strpos( wp_normalize_path( (string) realpath( $file ) ), $self ) ) {
				return false;
			}
		}
		foreach ( array( 'qcld_admin_theme', 'qcld_admin_theme_scope', 'qcld_admin_theme_all', 'qcld_admin_theme_prev', 'qcld_admin_theme_last' ) as $key ) {
			delete_metadata( 'user', 0, $key, '', true );
		}
		return true;
	}
}
