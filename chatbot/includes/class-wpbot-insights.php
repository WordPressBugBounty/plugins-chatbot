<?php
/**
 * WPBot Insights: connects the bundled Insights module (includes/insights/)
 * to WPBot Free.
 *
 * The module itself contains no WPBot code. Everything WPBot-specific
 * (menu, branding, AI keys to copy) lives in the adapter loaded below.
 *
 * Loaded from qcld-wpwbot.php:
 *     require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/class-wpbot-insights.php';
 *
 * @package WPBot
 */

defined( 'ABSPATH' ) || exit;

if ( ! file_exists( __DIR__ . '/insights/insights.php' ) ) {
	return;
}

require_once __DIR__ . '/insights/insights.php';

if ( ! function_exists( 'qcld_wpbot_insights_register' ) ) {
	/**
	 * Register WPBot as an Insights host once the module is loaded.
	 */
	function qcld_wpbot_insights_register() {
		require_once __DIR__ . '/class-wpbot-insights-adapter.php';
		\QuantumCloud\Insights\Module::instance()->register_host( new QCLD_WPBot_Insights_Adapter() );
		// Inactive previews of the Site Insight Pro features, marked "Pro".
		require_once __DIR__ . '/class-wpbot-insights-pro-preview.php';
	}
	add_action( 'qcin/loaded', 'qcld_wpbot_insights_register' );
}
