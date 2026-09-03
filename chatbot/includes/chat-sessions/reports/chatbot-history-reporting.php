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
add_action( 'admin_enqueue_scripts', 'qcld_wpbot_reporting_enqueue_assets' );

function qcld_wpbot_reporting_enqueue_assets( $hook ) {
	if ( strpos( $hook, 'wbcs-botsessions-reports' ) === false ) {
		return;
	}

	wp_enqueue_script(
		'qcld-wp-apexcharts',
		QCLD_wpCHATBOT_HISTORY_PLUGIN_URL . 'reports/view/assets/apexcharts.min.js',
		array( 'jquery' ),
		QCLD_wpCHATBOT_VERSION,
		true
	);

	$inline_script = <<<'JS'
jQuery(document).ready(function($) {
	$(".feedback-card").on("click", function() {
		let type = $(this).data("type");
		$("#feedback-results").html("<p>Loading...</p>");

		$.post(ajaxurl, {
			action: "wpbot_get_feedback_ajax",
			feedback_type: type
		}, function(response) {
			if (response.success) {
				$("#feedback-results").html(response.data.html);
			} else {
				$("#feedback-results").html("<p>No results found.</p>");
			}
		});
	});
});
jQuery(document).on("click", ".wpbot-page-link", function(e) {
	e.preventDefault();

	let page = jQuery(this).data("page");
	let type = jQuery(this).data("type");

	jQuery.post(ajaxurl, {
		action: "wpbot_get_feedback_ajax",
		feedback_type: type,
		page: page
	}, function(response) {
		if (response.success) {
			jQuery("#feedback-results").html(response.data.html);
		}
	});
});
jQuery(document).on("click", "#wpbot-clear-feedback", function(e) {
	e.preventDefault();

	if (!confirm("Are you sure you want to delete all feedback?")) {
		return;
	}

	jQuery.post(ajaxurl, {
		action: "wpbot_clear_all_feedback"
	}, function(response) {
		if (response.success) {
			jQuery("#wpbot-clear-feedback-msg").text(response.data.message).css("color", "green");
			location.reload();
		} else {
			jQuery("#wpbot-clear-feedback-msg").text(response.data.message).css("color", "red");
		}
	});
});
JS;

	wp_add_inline_script( 'qcld-wp-apexcharts', $inline_script );
}

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
