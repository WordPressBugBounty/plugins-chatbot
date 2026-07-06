<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/******************************************
 * Add Helper Functions File
 ******************************************/
require_once QCLD_WPCHATBOT_HISTORY_DIR_PATH . '/reports/reporting-helper-functions.php';


/******************************************
 * Add Reporting Menu
 */
add_action( 'admin_menu', 'qcld_history_reporting_menu_func' );

function qcld_history_reporting_menu_func() {
	$capability = function_exists( 'qcld_wpbot_get_menu_capability' ) ? qcld_wpbot_get_menu_capability( 'sessions' ) : 'publish_posts';

	if ( current_user_can( $capability ) ) {

		add_submenu_page( 'wbcs-botsessions-page', 'Bot - Reports', 'Bot - Reports', $capability, 'wbcs-botsessions-reports', 'qcld_wpbot_reporting_page_cb' );

	}
}

// Callback function for "Bot - Reports" menu
function qcld_wpbot_reporting_page_cb() {
	require_once QCLD_WPCHATBOT_HISTORY_DIR_PATH . '/reports/view/reporting-highlights.php';
}
