<?php
/**
 * QuantumCloud Admin Theme (shared module).
 *
 * A per-user look for the WordPress admin: Light, Dark, Match device or Classic,
 * on the pages of the plugins that bundle this module or, as an option, on the
 * entire dashboard.
 *
 * Every QuantumCloud plugin can bundle a copy at includes/admin-theme/. Each copy
 * registers itself when its host's main file loads; on plugins_loaded only the
 * newest copy boots, so a site with several of our plugins gets one switch and
 * one preference. Hosts say which admin pages are theirs with the
 * qcld_admin_theme_plugins filter.
 *
 * @package QuantumCloud\AdminTheme
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $GLOBALS['qcld_admin_theme_copies'] ) || ! is_array( $GLOBALS['qcld_admin_theme_copies'] ) ) {
	$GLOBALS['qcld_admin_theme_copies'] = array();
}
$GLOBALS['qcld_admin_theme_copies'][ __FILE__ ] = '1.0.0';

if ( ! function_exists( 'qcld_admin_theme_boot' ) ) {
	/**
	 * Boot the newest bundled copy of the module.
	 *
	 * @return void
	 */
	function qcld_admin_theme_boot() {
		if ( class_exists( 'Qcld_Admin_Theme', false ) || empty( $GLOBALS['qcld_admin_theme_copies'] ) ) {
			return;
		}
		$copies = $GLOBALS['qcld_admin_theme_copies'];
		uasort( $copies, 'version_compare' );
		end( $copies );
		$file = key( $copies );
		require_once dirname( $file ) . '/src/class-qcld-admin-theme.php';
		Qcld_Admin_Theme::instance( $file, current( $copies ) );
	}
	add_action( 'plugins_loaded', 'qcld_admin_theme_boot', 1 );
}
