<?php
/**
 * WPBot Asset Cache in WPBot Free: loads the bundled core module
 * (includes/asset-optimizer/) and registers WPBot Free as its host.
 *
 * The folder is a synced copy of its own repository
 * (npm run sync:asset-optimizer); never edit it here. Everything WPBot Free
 * specific lives in the adapter loaded below.
 *
 * Loaded from qcld-wpwbot.php:
 *     require_once __DIR__ . '/includes/class-wpbot-free-asset-optimizer.php';
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '7.4', '<' ) || ! file_exists( __DIR__ . '/asset-optimizer/asset-optimizer.php' ) ) {
	return;
}

if ( ! defined( 'QCLD_WPBOT_FREE_ASSET_CACHE_FILE' ) ) {
	// The main plugin file (deactivation hook, folder and URL).
	define( 'QCLD_WPBOT_FREE_ASSET_CACHE_FILE', dirname( __DIR__ ) . '/qcld-wpwbot.php' );
}

// Read before WPBot Free's activation routine stores its default options:
// a stored option means WPBot Free was installed before the asset cache existed.
if ( ! defined( 'QCLD_WPBOT_FREE_ASSET_CACHE_EXISTING' ) ) {
	define( 'QCLD_WPBOT_FREE_ASSET_CACHE_EXISTING', false !== get_option( 'wp_chatbot_position_x', false ) );
}

if ( ! function_exists( 'qcld_wpbot_free_asset_cache_register' ) ) {
	/**
	 * Register WPBot Free as the asset cache host.
	 *
	 * @return void
	 */
	function qcld_wpbot_free_asset_cache_register() {
		require_once __DIR__ . '/class-wpbot-free-asset-optimizer-adapter.php';
		\QuantumCloud\AssetOptimizer\Module::instance()->register_host( new QCLD_WPBot_Free_Asset_Optimizer_Adapter() );
	}
	add_action( 'qcld_wpbot_ao_loaded', 'qcld_wpbot_free_asset_cache_register' );
}

require_once __DIR__ . '/asset-optimizer/asset-optimizer.php';
