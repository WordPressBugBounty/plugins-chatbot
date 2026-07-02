<?php
/**
 * WPBot Sessions & Analytics — Built-in Chat Session Module
 *
 * Provides full chat session recording, analytics, AI Insight, and
 * "Questions Not Answered" reporting natively in the free chatbot plugin.
 *
 * This module is a port of the Pro plugin's chat-session-addon.
 * It uses the SAME database table names (wpbot_user, wpbot_conversation,
 * wpbot_failed_response) so data is shared if the Pro addon is later activated.
 *
 * Everything is guarded with function_exists() checks so that when the Pro
 * addon IS active it takes full precedence and this code is skipped entirely.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Guard: Do not run if the Pro addon is active ────────────────────────────
// The Pro addon defines qcwp_chat_session_menu_fnc(); if that function already
// exists we skip everything here to avoid duplicate menus / conflicts.
if ( function_exists( 'qcwp_chat_session_menu_fnc' ) ) {
	return;
}

// ─── Constants ────────────────────────────────────────────────────────────────
// Define our own URL / path constants for assets.
// Also define the legacy constant names that all copied report partials reference,
// so those files work without modification.

if ( ! defined( 'QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL' ) ) {
	define( 'QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'QCLD_CHATBOT_FREE_SESSION_DIR_PATH' ) ) {
	define( 'QCLD_CHATBOT_FREE_SESSION_DIR_PATH', plugin_dir_path( __FILE__ ) );
}

// Legacy alias constants — used by all copied partials / report files.
if ( ! defined( 'QCLD_wpCHATBOT_HISTORY_PLUGIN_URL' ) ) {
	define( 'QCLD_wpCHATBOT_HISTORY_PLUGIN_URL', QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL );
}
if ( ! defined( 'QCLD_WPCHATBOT_HISTORY_DIR_PATH' ) ) {
	define( 'QCLD_WPCHATBOT_HISTORY_DIR_PATH', QCLD_CHATBOT_FREE_SESSION_DIR_PATH );
}

// ─── Database Structure ───────────────────────────────────────────────────────
require_once QCLD_CHATBOT_FREE_SESSION_DIR_PATH . 'inc/chatsession-db-structure.php';

// ─── Admin Menu ───────────────────────────────────────────────────────────────
add_action( 'admin_menu', 'qcwp_chat_session_menu_fnc_free' );

function qcwp_chat_session_menu_fnc_free() {

	$capability = function_exists( 'qcld_wpbot_get_menu_capability' ) ? qcld_wpbot_get_menu_capability( 'sessions' ) : 'manage_options';

	if ( current_user_can( $capability ) ) {

		add_menu_page(
			'WPBot Sessions & Analytics',
			'WPBot Sessions & Analytics',
			$capability,
			'wbcs-botsessions-page',
			'qc_wpbot_cs_menu_page_callback_func',
			'dashicons-chart-bar',
			'9'
		);

		add_submenu_page(
			'wbcs-botsessions-page',
			'Questions Not Answered',
			'Questions Not Answered',
			$capability,
			'wbcs-botsessions-notansweredpage',
			'qcld_wpbot_not_answered_question'
		);

		add_submenu_page(
			'wbcs-botsessions-page',
			'AI Insight',
			'AI Insight',
			$capability,
			'wbcs-schedule-session-reporting',
			'qcld_wpbot_schedule_session_reporting'
		);
	}
}

// ─── Admin Scripts & Styles ───────────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', 'qcld_wb_chatbot_session_admin_scripts_free' );

function qcld_wb_chatbot_session_admin_scripts_free( $hook ) {
	// WordPress generates hook suffixes as follows:
	//   top-level page  → toplevel_page_{slug}
	//   sub-pages       → {parent-menu-title}_page_{slug}  (title, lowercased, spaces→hyphens)
	// Our parent title is "WPBot Sessions & Analytics" → "wpbot-sessions-analytics"
	$session_hooks = array(
		'toplevel_page_wbcs-botsessions-page',
		'wpbot-sessions-analytics_page_wbcs-botsessions-notansweredpage',
		'wpbot-sessions-analytics_page_wbcs-botsessions-reports',
		'wpbot-sessions-analytics_page_wbcs-schedule-session-reporting',
	);
	$is_session_page = false;
	foreach ( $session_hooks as $session_hook ) {
		if ( strpos( $hook, $session_hook ) !== false || ( isset( $_GET['page'] ) && $_GET['page'] === str_replace( 'toplevel_page_', '', $session_hook ) ) ) {
			$is_session_page = true;
			break;
		}
	}
	
	if ( isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'wbcs-botsessions-page', 'wbcs-botsessions-notansweredpage', 'wbcs-botsessions-reports', 'wbcs-schedule-session-reporting' ) ) ) {
		$is_session_page = true;
	}

	if ( ! $is_session_page ) {
		return;
	}

	wp_register_style( 'qlcd-wp-bootstrap-cs', QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL . 'css/qlcd-wp-bootstrap.css', array(), QCLD_wpCHATBOT_VERSION, 'screen' );
	wp_enqueue_style( 'qlcd-wp-bootstrap-cs' );

	wp_register_style( 'qlcd-wp-bootstrap-icons-cs', QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL . 'css/qlcd-wp-bootstrap-icons.css', array(), QCLD_wpCHATBOT_VERSION, 'screen' );
	wp_enqueue_style( 'qlcd-wp-bootstrap-icons-cs' );

	wp_register_style( 'qlcd-wp-dataTables-cs', QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL . 'css/qlcd-wp-dataTables.css', array(), QCLD_wpCHATBOT_VERSION, 'screen' );
	wp_enqueue_style( 'qlcd-wp-dataTables-cs' );

	wp_register_style( 'qlcd-wp-session-style-cs', QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL . 'reports/view/assets/style.css', array(), QCLD_wpCHATBOT_VERSION, 'screen' );
	wp_enqueue_style( 'qlcd-wp-session-style-cs' );

	// SweetAlert2 — used by admin.js for Swal.fire() and Swal.showLoading()
	wp_register_script( 'qcld-wp-chatbot-sweetalrt-cs', QCLD_wpCHATBOT_PLUGIN_URL . 'js/sweetalrt.js', array( 'jquery' ), QCLD_wpCHATBOT_VERSION, true );
	wp_enqueue_script( 'qcld-wp-chatbot-sweetalrt-cs' );

	// admin.js depends on SweetAlert2 being loaded first
	wp_register_script( 'qcld-wp-session-admin-cs', QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL . 'js/admin.js', array( 'jquery', 'qcld-wp-chatbot-sweetalrt-cs' ), QCLD_wpCHATBOT_VERSION, true );
	wp_enqueue_script( 'qcld-wp-session-admin-cs' );
	wp_localize_script(
		'qcld-wp-session-admin-cs',
		'ajax_object',
		array( 'ajax_url' => admin_url( 'admin-ajax.php' ) )
	);

	wp_register_script( 'qcld-wp-dataTables-cs', QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL . 'js/qcld-dataTables.min.js', array( 'jquery' ), QCLD_wpCHATBOT_VERSION, true );
	wp_enqueue_script( 'qcld-wp-dataTables-cs' );
}


// ─── AI Insight Page Callback ─────────────────────────────────────────────────
function qcld_wpbot_schedule_session_reporting() {
	?>
	<div class="wrap">
		<h2><?php echo esc_html( 'AI Insight' ); ?></h2>
		<div class="notice notice-warning inline" style="margin-top: 20px; padding: 20px;">
			<h3><span class="dashicons dashicons-lock"></span> <?php echo esc_html( 'Feature Locked' ); ?></h3>
			<p>
				<?php echo esc_html( 'The AI Insight feature allows you to receive an AI-based summary of all chat conversations emailed directly to you on a schedule.' ); ?>
			</p>
			<p>
				<strong><?php echo esc_html( 'Please upgrade to WPBot Pro to unlock this feature!' ); ?></strong>
			</p>
			<p>
				<a href="https://www.wpbot.pro/" target="_blank" class="button button-primary button-large"><?php echo esc_html( 'Upgrade to Pro' ); ?></a>
			</p>
		</div>
	</div>
	<?php
}


// ─── Questions Not Answered Page Callback ─────────────────────────────────────
function qcld_wpbot_not_answered_question() {
	global $wpdb;
	$wpdb->show_errors = true;
	$table             = $wpdb->prefix . 'wpbot_failed_response';

	if ( isset( $_GET['msg'] ) && $_GET['msg'] == 'success' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success"><p>Record has been Deleted Successfully!</p></div>';
	}

	if ( isset( $_GET['action'] ) && $_GET['action'] == 'deleteall' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wpdb->query( "TRUNCATE TABLE `$table`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		echo '<div class="notice notice-success"><p>All Records have been deleted successfully!</p></div>';
	}

	$sql  = "SELECT * FROM $table WHERE 1 ORDER BY `id` DESC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$sql1 = "SELECT count(*) FROM $table WHERE 1 ORDER BY `id` DESC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$total          = $wpdb->get_var( $sql1 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$items_per_page = 30;
	$page           = isset( $_GET['cpage'] ) ? abs( (int) $_GET['cpage'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$offset         = ( $page * $items_per_page ) - $items_per_page;
	$sql           .= " LIMIT {$offset}, {$items_per_page}";
	$result         = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$totalPage      = ceil( $total / $items_per_page );
	$customPagHTML  = '';
	if ( $totalPage > 1 ) {
		$customPagHTML = '<div><span class="wpbot_pagination">Page ' . esc_html( $page ) . ' of ' . esc_html( $totalPage ) . '</span>' . paginate_links(
			array(
				'base'      => add_query_arg( 'cpage', '%#%' ),
				'format'    => '',
				'prev_text' => __( '« prev' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'next_text' => __( 'next »' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'total'     => esc_html( $totalPage ),
				'current'   => esc_html( $page ),
			)
		) . '</div>';
	}

	wp_register_style( 'qcld-wp-chatbot-history-style', QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL . 'css/history-style.css', array(), QCLD_wpCHATBOT_VERSION, 'screen' );
	wp_enqueue_style( 'qcld-wp-chatbot-history-style' );
	?>

	<div class="sld_menu_title qcld_session_chat_menu_title">
		<h2><?php echo esc_html__( 'Questions Not Answered', 'chatbot' ) . ' (' . intval( $total ) . ')'; ?></h2>
	</div>

	<?php if ( $customPagHTML != '' ) : ?>
	<div class="sld_menu_title sld_menu_title_align"><?php echo wp_kses_post( $customPagHTML ); ?></div>
	<?php endif; ?>

	<?php
	require_once QCLD_CHATBOT_FREE_SESSION_DIR_PATH . 'reports/view/partials/questions-not-answered.php';
}

// ─── Main Chat Sessions Page Callback ────────────────────────────────────────
function qc_wpbot_cs_menu_page_callback_func() {

	global $wpdb;
	$wpdb->show_errors = true;

	$tableuser         = $wpdb->prefix . 'wpbot_user';
	$tableconversation = $wpdb->prefix . 'wpbot_conversation';
	$mainurl           = admin_url( 'admin.php?page=wbcs-botsessions-page' );

	if ( isset( $_GET['min_interaction'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$mainurl .= '&min_interaction=' . intval( $_GET['min_interaction'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	if ( isset( $_GET['wp_user'] ) && $_GET['wp_user'] != '' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$mainurl .= '&wp_user=' . intval( $_GET['wp_user'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	$msg = '';

	if ( isset( $_GET['action'] ) && $_GET['action'] == 'deleteall' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wpdb->query( "TRUNCATE TABLE `$tableuser`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( "TRUNCATE TABLE `$tableconversation`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$msg = esc_html( 'All Sessions have been deleted successfully!' );
	}

	if ( isset( $_GET['msg'] ) && $_GET['msg'] == 'success' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success"><p>Record has been Deleted Successfully!</p></div>';
	}

	if ( isset( $_GET['userid'] ) && $_GET['userid'] != '' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		require_once QCLD_CHATBOT_FREE_SESSION_DIR_PATH . 'reports/view/partials/view-single-chat.php';
	} else {

		wp_register_style( 'qcld-wp-chatbot-history-style', QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL . 'css/history-style.css', array(), QCLD_wpCHATBOT_VERSION, 'screen' );
		wp_enqueue_style( 'qcld-wp-chatbot-history-style' );
		wp_register_style( 'qcld-wp-chatbot-jquery-ui', QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL . 'css/jqueryui.css', array(), '', 'screen' );
		wp_enqueue_style( 'qcld-wp-chatbot-jquery-ui' );
		wp_register_script( 'qcld-wp-chatsession-admin-js', QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL . 'js/chatsession.js', array( 'jquery' ), QCLD_wpCHATBOT_VERSION, true );
		wp_enqueue_script( 'qcld-wp-chatsession-admin-js' );
		wp_localize_script(
			'qcld-wp-chatsession-admin-js',
			'ajax_object',
			array( 'ajax_url' => admin_url( 'admin-ajax.php' ) )
		);
		wp_register_script( 'qcld-wp-jqueryui-js', QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL . 'js/jqueryui.js', array( 'jquery' ), QCLD_wpCHATBOT_VERSION, true );
		wp_enqueue_script( 'qcld-wp-jqueryui-js' );

		$where = '';
		if ( isset( $_GET['min_interaction'] ) && $_GET['min_interaction'] != 'all' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['min_interaction'] ) && $_GET['min_interaction'] > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$where = ' and `interaction` >= ' . intval( $_GET['min_interaction'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			if ( isset( $_GET['min_interaction'] ) && $_GET['min_interaction'] == 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$where = ' and `interaction` = 0';
			}
		}

		$wwhere = '';
		if ( isset( $_GET['wp_user'] ) && $_GET['wp_user'] != 'all' && $_GET['wp_user'] != 0 && $_GET['wp_user'] != '' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$wwhere = ' and `user_id` = ' . intval( $_GET['wp_user'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$sql  = "SELECT * FROM $tableuser WHERE 1 $where $wwhere ORDER BY `date` DESC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql1 = "SELECT count(*) FROM $tableuser WHERE 1 $where $wwhere"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$dateFilter = '';
		if ( isset( $_GET['FilterDate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $_GET['FilterDate'] === 'LastWeek' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$dateFilter = " WHERE `date` >= CURDATE() - INTERVAL 7 DAY";
			}
			if ( $_GET['FilterDate'] === 'LastMonth' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$dateFilter = " WHERE `date` >= CURDATE() - INTERVAL 30 DAY";
			}
			if ( $_GET['FilterDate'] === 'Last3Months' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$dateFilter = " WHERE `date` >= CURDATE() - INTERVAL 90 DAY";
			}
			$sql  = "SELECT * FROM $tableuser $dateFilter $wwhere ORDER BY `date` DESC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql1 = "SELECT count(*) FROM $tableuser $dateFilter"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$total          = $wpdb->get_var( $sql1 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$items_per_page = 30;
		$page           = isset( $_GET['cpage'] ) ? abs( (int) $_GET['cpage'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offset         = ( $page * $items_per_page ) - $items_per_page;
		$sql           .= " LIMIT {$offset}, {$items_per_page}";
		$result         = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$totalPage      = ceil( $total / $items_per_page );
		$customPagHTML  = '';
		if ( $totalPage > 1 ) {
			$customPagHTML = '<div><span class="wpbot_pagination">Page ' . esc_html( $page ) . ' of ' . esc_html( $totalPage ) . '</span>' . paginate_links(
				array(
					'base'      => add_query_arg( 'cpage', '%#%' ),
					'format'    => '',
					'prev_text' => __( '« prev' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'next_text' => __( 'next »' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'total'     => esc_html( $totalPage ),
					'current'   => esc_html( $page ),
				)
			) . '</div>';
		}

		$deleteurl = admin_url( 'admin.php?page=wbcs-botsessions-page&action=deleteall' );
		?>

		<div class="qchero_sliders_list_wrapper qcld-session-history_menu_box">
			<?php if ( $msg != '' ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $msg ); ?></p>
				</div>
			<?php endif; ?>

			<div class="sld_menu_title qcld-session-history_menu_title">
				<h2><?php echo esc_html__( 'Chat Sessions', 'chatbot' ) . ' (' . intval( $total ) . ')'; ?></h2>
			</div>

			<div>
				<?php
				if ( isset( $_GET['FilterDate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$filterText = '';
					if ( $_GET['FilterDate'] === 'LastWeek' ) { $filterText = 'LAST WEEK'; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					if ( $_GET['FilterDate'] === 'LastMonth' ) { $filterText = 'LAST MONTH'; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					if ( $_GET['FilterDate'] === 'Last3Months' ) { $filterText = 'LAST 3 MONTHS'; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					echo '<div class="sld_menu_title"><em>Filtering Records by: <strong>' . esc_html( $filterText ) . '</strong></em></div>';
				}
				?>
			</div>

			<?php if ( $customPagHTML != '' ) : ?>
			<div class="sld_menu_title sld_menu_title_align"><?php echo wp_kses_post( $customPagHTML ); ?></div>
			<?php endif; ?>

			<form id="wpcs_form_sessions" action="<?php echo esc_url( $mainurl ); ?>" method="POST" style="width:100%">
				<input type="hidden" name="wpbot_session_remove" />

				<?php if ( ! empty( $result ) ) : ?>
					<?php require_once QCLD_CHATBOT_FREE_SESSION_DIR_PATH . 'reports/view/partials/chatsession-table.php'; ?>
				<?php else : ?>
					<div class="sld_menu_title"><h2>No result found.</h2></div>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}
}

// ─── Request Handler (delete, export, redirect) ───────────────────────────────
add_action( 'init', 'qc_wp_cs_request_handle_free' );

function qc_wp_cs_request_handle_free() {
	global $wpdb;
	$wpdb->show_errors = true;

	$tableuser1         = $wpdb->prefix . 'wpbot_user';
	$tableconversation1 = $wpdb->prefix . 'wpbot_conversation';
	$table              = $wpdb->prefix . 'wpbot_failed_response';

	// Delete single "not answered" record.
	if ( isset( $_GET['page'] ) && $_GET['page'] == 'wbcs-botsessions-notansweredpage' && isset( $_GET['act'] ) && $_GET['act'] == 'delete' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$userid = intval( $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wpdb->delete( $table, array( 'id' => $userid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		wp_safe_redirect( admin_url( 'admin.php?page=wbcs-botsessions-notansweredpage&msg=success' ) );
		exit;
	}

	// Delete single chat session.
	if ( isset( $_GET['page'] ) && $_GET['page'] == 'wbcs-botsessions-page' && isset( $_GET['act'] ) && $_GET['act'] == 'delete' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$userid = intval( $_GET['userid'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wpdb->delete( $tableuser1, array( 'id' => $userid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $tableconversation1, array( 'user_id' => $userid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		wp_safe_redirect( admin_url( 'admin.php?page=wbcs-botsessions-page&msg=success' ) );
		exit;
	}

	// Export all sessions as CSV.
	if ( isset( $_POST['wpbot_session_export_all'] ) && isset( $_POST['wpbot_session_remove'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$users    = $wpdb->get_results( "SELECT wu.`id`, wu.`session_id`, wu.`name`, wu.`email`, wu.`date`, wu.`phone`, wu.`interaction`, wc.`conversation` FROM $tableuser1 as wu, $tableconversation1 as wc WHERE 1 AND wu.id = wc.user_id LIMIT 5000" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$sessions = array();
		if ( ! empty( $users ) ) {
			foreach ( $users as $user ) {
				$sessions[] = wpbot_conversations_export( $user );
			}
		}
		qcld_wpbot_chatsession_download_send_headers( 'wpbot_chatsession_' . date( 'Y-m-d' ) . '.csv' );
		print wpbot_chatsession_array2csv( $sessions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw CSV download, escaping would corrupt the file.
		exit;
	}

	// Export selected sessions or delete selected sessions.
	if ( isset( $_POST['wpbot_session_remove'] ) && ! empty( $_POST['sessions'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$userids = array_map( 'intval', $_POST['sessions'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( isset( $_POST['wpbot_session_export'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$sessions = array();
			foreach ( $userids as $userid ) {
				$user       = $wpdb->get_row( $wpdb->prepare( "SELECT wu.`id`, wu.`session_id`, wu.`name`, wu.`email`, wu.`date`, wu.`phone`, wu.`interaction`, wc.`conversation` FROM $tableuser1 as wu, $tableconversation1 as wc WHERE 1 AND wu.id = wc.user_id AND wu.id = %d", $userid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$sessions[] = wpbot_conversations_export( $user );
			}
			qcld_wpbot_chatsession_download_send_headers( 'wpbot_chatsession_' . date( 'Y-m-d' ) . '.csv' );
			print wpbot_chatsession_array2csv( $sessions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw CSV download, escaping would corrupt the file.
			exit;
		}

		if ( isset( $_POST['wpbot_session_delete'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			foreach ( $userids as $userid ) {
				$wpdb->delete( $tableuser1, array( 'id' => $userid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->delete( $tableconversation1, array( 'user_id' => $userid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}
			wp_safe_redirect( admin_url( 'admin.php?page=wbcs-botsessions-page&msg=success' ) );
			exit;
		}
	}
}

// ─── Admin Footer: Email Modal ────────────────────────────────────────────────
add_action( 'admin_footer', 'wpcs_admin_footer_content_free' );

function wpcs_admin_footer_content_free() {
	if ( isset( $_GET['page'] ) && $_GET['page'] == 'wbcs-botsessions-page' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div id="wpcsmyModal" class="wpcsmodal">
			<div class="wpcsmodal-content">
				<span class="wpcsclose">&times;</span>
				<h2><?php echo esc_html( 'Send an Email to' ); ?> <span id="wpcs_show_email"></span></h2>
				<div class="wpcs_form_container">
					<form id="wpcs_email_form" action="">
						<label for="fname"><?php echo esc_html( 'Subject' ); ?></label>
						<input type="text" class="wpcs_text_field" id="wpcs_email_subject" name="wpcs_email_subject" placeholder="Subject.." required>
						<label for="lname"><?php echo esc_html( 'Your Message' ); ?></label>
						<textarea id="wpcs_email_message" class="wpcs_text_field" name="wpcs_email_message" placeholder="" style="height:200px" required></textarea>
						<input type="hidden" id="wpcs_to_email_address" value="" />
						<input type="submit" class="wpcs_submit_field" id="wpcs_email_submit" value="Submit">
						<span id="wpcs_email_loading" style="display:none;"><img style="width:20px;" src="<?php echo esc_url( QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL . 'images/ajax-loader.gif' ); ?>"></span>
						<span id="wpcs_email_status"></span>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}

// ─── AJAX: Send Email to User ─────────────────────────────────────────────────
add_action( 'wp_ajax_wpcs_send_email', 'wpcs_send_email' );
add_action( 'wp_ajax_nopriv_wpcs_send_email', 'wpcs_send_email' );

function wpcs_send_email() {
	$subject = sanitize_text_field( $_POST['data']['subject'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$message = sanitize_text_field( $_POST['data']['message'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$to      = sanitize_email( $_POST['data']['to'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

	$url       = get_site_url();
	$url       = wp_parse_url( $url );
	$domain    = $url['host'];
	$fromEmail = 'wordpress@' . $domain;
	$headers   = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: ' . esc_html( $domain ) . ' <' . esc_html( $fromEmail ) . '>',
	);

	$result = wp_mail( $to, $subject, $message, $headers );
	if ( $result ) {
		$response = array( 'status' => 'success', 'message' => 'Email has been sent successfully!' );
	} else {
		$response = array( 'status' => 'fail', 'message' => 'Unable to send email. Please contact your server administrator.' );
	}
	ob_clean();
	echo wp_json_encode( $response );
	die();
}

// ─── AJAX: Save Email Notification Preference ─────────────────────────────────
add_action( 'wp_ajax_session_email_notification_update', 'session_email_notification_update_free' );

function session_email_notification_update_free() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die();
	}
	$email_notification = sanitize_text_field( $_POST['email_notification'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	update_option( 'session_email_notification_update', $email_notification );
	wp_send_json( array( 'success' => true ) );
}

// ─── AJAX: Conversation Save (Frontend) ──────────────────────────────────────
// This is the main conversation-save handler. Guarded with function_exists
// so the Pro addon's definition wins if it's active.
if ( ! function_exists( 'qcld_wb_chatbot_conversation_save' ) ) {

	function qcld_wb_chatbot_conversation_save() {

		check_ajax_referer( 'qcsecretbotnonceval123qc', 'security' );
		global $wpdb;

		$tableuser         = $wpdb->prefix . 'wpbot_user';
		$tableconversation = $wpdb->prefix . 'wpbot_conversation';

		$allowed_html = array_merge(
			wp_kses_allowed_html( 'post' ),
			array(
				'div'  => array( 'class' => true, 'id' => true, 'style' => true, 'data-*' => true ),
				'span' => array( 'class' => true, 'id' => true, 'style' => true, 'data-*' => true ),
				'ul'   => array( 'class' => true ),
				'li'   => array( 'class' => true ),
				'img'  => array( 'src' => true, 'alt' => true, 'class' => true, 'style' => true ),
			)
		);
		$raw_conversation = isset( $_POST['conversation'] ) ? wp_unslash( $_POST['conversation'] ) : '';
		$clean_conversation = wp_kses( $raw_conversation, $allowed_html );
		$conversation   = qcld_wpbot_input_validation( $clean_conversation ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email          = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$phone          = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$name           = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$session_id     = isset( $_POST['session_id'] ) ? sanitize_text_field( $_POST['session_id'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$wpuser_id      = isset( $_POST['user_id'] ) ? sanitize_text_field( $_POST['user_id'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$source_url     = isset( $_POST['source_url'] ) && ! empty( $_POST['source_url'] ) ? sanitize_url( wp_unslash( $_POST['source_url'] ) ) : ( isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$user_agent     = isset( $_POST['user_agent'] ) && ! empty( $_POST['user_agent'] ) ? sanitize_text_field( wp_unslash( $_POST['user_agent'] ) ) : ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$session_mailed = isset( $_POST['session_mailed'] ) ? sanitize_text_field( $_POST['session_mailed'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Prepend source URL to conversation.
		$conversation = '&#x3C;ul&#x3E;&#x3C;li class=&#x22;session_start_url&#x22;&#x3E;&#x3C;span&#x3E;Source URL: &#x3C;/span&#x3E;&#x3C;a href=&#x22;' . $source_url . '&#x22;&#x3E;' . $source_url . '&#x3C;/a&#x3E;&#x3C;/li&#x3E;&#x3C;/ul&#x3E;' . $conversation;

		$response           = array();
		$response['status'] = 'success';

		$user_exists = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tableuser WHERE 1 AND session_id = %s", $session_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$is_new_insert = false;

		if ( empty( $user_exists ) ) {
			$lock_key = 'wpcs_lock_' . md5( $session_id );
			if ( add_option( $lock_key, '1', '', 'no' ) ) {
				$interaction = (int) substr_count( $conversation, 'wp-chat-user-msg' );
				if ( $interaction == 0 ) {
					$interaction = (int) substr_count( $conversation, 'woo-chat-user-msg' );
				}

				if ( $interaction != 0 ) {
					$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
						$tableuser,
						array(
							'date'        => current_time( 'mysql' ),
							'name'        => $name,
							'email'       => $email,
							'phone'       => $phone,
							'session_id'  => $session_id,
							'interaction' => $interaction,
							'user_id'     => $wpuser_id,
						)
					);
					$user_id = $wpdb->insert_id;
					$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
						$tableconversation,
						array(
							'user_id'          => $user_id,
							'conversation'     => $conversation,
							'interaction'      => $interaction,
							'environment_info' => $user_agent,
						)
					);
					$is_new_insert = true;
				}
				delete_option( $lock_key );
			} else {
				$retries = 3;
				while ( $retries > 0 ) {
					usleep( 500000 );
					$user_exists = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tableuser WHERE 1 AND session_id = %s", $session_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
					if ( ! empty( $user_exists ) ) {
						break;
					}
					$retries--;
				}
			}
		}

		if ( ! $is_new_insert && ! empty( $user_exists ) ) {
			$interaction = (int) substr_count( $conversation, 'wp-chat-user-msg' );
			if ( $interaction == 0 ) {
				$interaction = (int) substr_count( $conversation, 'woo-chat-user-msg' );
			}

			$user_id = isset( $user_exists->id ) ? $user_exists->id : get_current_user_id();
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$tableuser,
				array(
					'date'        => current_time( 'mysql' ),
					'name'        => $name,
					'email'       => $email,
					'phone'       => $phone,
					'interaction' => $interaction,
					'user_id'     => $wpuser_id,
				),
				array( 'id' => $user_id ),
				array( '%s', '%s', '%s', '%s', '%d', '%d' ),
				array( '%d' )
			);
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$tableconversation,
				array(
					'conversation' => $conversation,
					'interaction'  => $interaction,
				),
				array( 'user_id' => $user_id ),
				array( '%s', '%d' ),
				array( '%d' )
			);
		}

		// Email notification for new session.
		if ( $is_new_insert && ( get_option( 'session_email_notification_update' ) == 'checked' ) ) {
			$admin_email  = get_option( 'admin_email' );
			$subject      = esc_html__( 'Someone has started a new chat session with ChatBot.', 'chatbot' );
			$bodyContent  = '<p>' . esc_html__( 'Hi,', 'chatbot' ) . '</p>';
			$bodyContent .= '<p>' . esc_html__( 'Someone has started a new chat session with ChatBot. Please go to ', 'chatbot' ) . '<a href="' . admin_url() . 'admin.php?page=wbcs-botsessions-page">' . esc_html__( 'Bot Sessions Dashboard', 'chatbot' ) . '</a>' . esc_html__( ' and find him/her.', 'chatbot' ) . '</p>';
			
			$bodyContent .= '<ul>';
			$bodyContent .= '<li>' . esc_html__( 'Session ID:', 'chatbot' ) . ' <strong>' . esc_html( $session_id ) . '</strong></li>';
			if ( ! empty( $email ) ) {
				$bodyContent .= '<li>' . esc_html__( 'Email:', 'chatbot' ) . ' <strong>' . esc_html( $email ) . '</strong></li>';
			}
			if ( ! empty( $phone ) ) {
				$bodyContent .= '<li>' . esc_html__( 'Phone:', 'chatbot' ) . ' <strong>' . esc_html( $phone ) . '</strong></li>';
			}
			if ( ! empty( $source_url ) ) {
				$bodyContent .= '<li>' . esc_html__( 'Page Link:', 'chatbot' ) . ' <strong><a href="' . esc_url( $source_url ) . '">' . esc_html( $source_url ) . '</a></strong></li>';
			}
			if ( ! empty( $user_agent ) ) {
				$bodyContent .= '<li>' . esc_html__( 'Browser:', 'chatbot' ) . ' <strong>' . esc_html( $user_agent ) . '</strong></li>';
			}
			$bodyContent .= '</ul>';

			$bodyContent .= '<p>' . esc_html__( 'Thanks', 'chatbot' ) . '</p>';
			$bodyContent .= '<p>' . esc_html__( '(You can disable email notifications from ', 'chatbot' ) . '<a href="' . admin_url() . 'admin.php?page=wbcs-botsessions-page">' . esc_html__( 'Bot - Sessions)', 'chatbot' ) . '</a></p>';
			
			$to           = get_option( 'qlcd_wp_chatbot_admin_email' ) != '' ? get_option( 'qlcd_wp_chatbot_admin_email' ) : $admin_email;
			$headers      = array( 'Content-Type: text/html; charset=UTF-8' );
			wp_mail( $to, $subject, $bodyContent, $headers );
		}

		// WPBot Automator Trigger - Debounced by 3 minutes
		$cron_args = array( $session_id );
		if ( wp_next_scheduled( 'wpbot_automator_delayed_trigger', $cron_args ) ) {
			wp_clear_scheduled_hook( 'wpbot_automator_delayed_trigger', $cron_args );
		}
		wp_schedule_single_event( time() + 60, 'wpbot_automator_delayed_trigger', $cron_args );

		echo wp_json_encode( $response );
		die();
	}
}
add_action( 'wp_ajax_qcld_wb_chatbot_conversation_save', 'qcld_wb_chatbot_conversation_save' );
add_action( 'wp_ajax_nopriv_qcld_wb_chatbot_conversation_save', 'qcld_wb_chatbot_conversation_save' );

// ─── AJAX: Date Filter ────────────────────────────────────────────────────────
add_action( 'wp_ajax_qcld_chatbot_session_date_filter', 'qcld_chatbot_session_date_filter_free' );
add_action( 'wp_ajax_nopriv_qcld_chatbot_session_date_filter', 'qcld_chatbot_session_date_filter_free' );

function qcld_chatbot_session_date_filter_free() {
	global $wpdb;
	$tableuser  = $wpdb->prefix . 'wpbot_user';
	$start_date = sanitize_text_field( $_POST['start_date'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$end_date   = sanitize_text_field( $_POST['end_date'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$result     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $tableuser WHERE date BETWEEN %s AND %s", $start_date, $end_date ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	echo wp_json_encode( $result );
	wp_die();
}

// ─── AJAX: Email Transcript ───────────────────────────────────────────────────
add_action( 'wp_ajax_wpbot_send_email_transcript', 'wpbot_send_email_transcript_free' );
add_action( 'wp_ajax_nopriv_wpbot_send_email_transcript', 'wpbot_send_email_transcript_free' );

function wpbot_send_email_transcript_free() {
	global $wpdb;

	$session = trim( sanitize_text_field( $_POST['session'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$email   = sanitize_email( $_POST['email'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

	$url         = wp_parse_url( get_site_url() );
	$domain      = $url['host'];
	$admin_email = get_option( 'admin_email' );
	$fromEmail   = get_option( 'qlcd_wp_chatbot_from_email' ) ? get_option( 'qlcd_wp_chatbot_from_email' ) : 'wordpress@' . $domain;
	$subject     = 'Chat transcript by ' . get_bloginfo( 'name' );

	$tableuser         = $wpdb->prefix . 'wpbot_user';
	$tableconversation = $wpdb->prefix . 'wpbot_conversation';

	$user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tableuser WHERE 1 AND session_id = %s", $session ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

	$response = array( 'status' => 'fail', 'message' => 'Session not found.' );

	if ( ! empty( $user ) ) {
		$result      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tableconversation WHERE 1 AND user_id = %d", $user->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$bodyContent = '';
		$bodyContent .= '<p><strong>' . esc_html__( 'User Details', 'chatbot' ) . ':</strong></p><hr>';
		$bodyContent .= '<p>' . esc_html__( 'Name', 'chatbot' ) . ' : ' . esc_html( $user->name ) . '</p>';
		$bodyContent .= '<p>' . esc_html__( 'Email', 'chatbot' ) . ' : ' . esc_html( $email ) . '</p>';
		$bodyContent .= '<p><b>Conversations</b></p><p>-----------------------</p>';
		$messages     = qcld_wpch_conversation_extract( htmlspecialchars_decode( $result->conversation ) );
		foreach ( $messages as $message ) {
			if ( isset( $message['bot'] ) && trim( $message['bot'] ) != '' ) {
				$bodyContent .= '<p>Chatbot : ' . esc_html( trim( $message['bot'] ) ) . '</p>';
			}
			if ( isset( $message['user'] ) && trim( $message['user'] ) != '' ) {
				$bodyContent .= '<p>' . esc_html( $user->name ) . ' : ' . esc_html( trim( $message['user'] ) ) . '</p>';
			}
		}
		$bodyContent .= '<p>-----------------------</p>';
		$bodyContent .= '<p>Mail Generated on: ' . current_time( 'F j, Y, g:i a' ) . '</p>';
		$headers      = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . esc_html( $user->name ) . ' <' . esc_html( $fromEmail ) . '>',
			'Reply-To: ' . esc_html( $user->name ) . ' <' . esc_html( $email ) . '>',
		);
		$result_mail  = wp_mail( $email, $subject, $bodyContent, $headers );
		if ( $result_mail ) {
			$response = array( 'status' => 'success', 'message' => 'Email transcript sent successfully.' );
		}
	}
	echo wp_json_encode( $response );
	die();
}

// ─── AJAX: Forward Session to Email ──────────────────────────────────────────
add_action( 'wp_ajax_forward_session_to_email', 'forward_session_to_email_free' );
add_action( 'wp_ajax_nopriv_forward_session_to_email', 'forward_session_to_email_free' );

function forward_session_to_email_free() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json( array( 'success' => false, 'msg' => esc_html__( 'Insufficient permissions', 'chatbot' ) ) );
		wp_die();
	}
	global $wpdb;

	$session_id        = sanitize_text_field( $_POST['session_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$to                = sanitize_email( $_POST['email'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$tableuser         = $wpdb->prefix . 'wpbot_user';
	$tableconversation = $wpdb->prefix . 'wpbot_conversation';

	$userinfo = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tableuser WHERE 1 AND session_id = %s", $session_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$result   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tableconversation WHERE 1 AND user_id = %d", $userinfo->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

	if ( ! empty( $result ) ) {
		$raw_html        = isset( $result->conversation ) ? (string) $result->conversation : '';
		$decoded_content = html_entity_decode( $raw_html );
		$doc             = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $decoded_content );
		libxml_clear_errors();

		$decoded_content = '';
		$body_nodes      = $doc->getElementsByTagName( 'body' );
		if ( $body_nodes->length > 0 ) {
			foreach ( $body_nodes->item( 0 )->childNodes as $child_node ) {
				$decoded_content .= $doc->saveHTML( $child_node );
			}
		}

		$email_body = '<!DOCTYPE html><html><head><style>
			.wp-chatbot-messages-container { list-style: none; padding: 20px; background: #f4f7f6; font-family: sans-serif; }
			.wp-chatbot-msg { margin-bottom: 15px; display: flex; flex-wrap: wrap; }
			.wp-chatbot-agent { font-weight: bold; color: #333; display: block; margin-bottom: 4px; }
			.wp-chatbot-paragraph { background: #ffffff; padding: 10px; border-radius: 8px; border: 1px solid #ddd; flex: 1; }
			.wp-chat-user-msg { display: flex; flex-wrap: wrap; flex-direction: row-reverse; }
			.wp-chat-user-msg .wp-chatbot-paragraph { background: #ffffff; padding: 10px; border-radius: 8px; border: 1px solid #ddd; flex: none; }
			body ul { width:100%; max-width: 640px; list-style: none; padding: 0; margin: 0 auto; }
		</style></head><body>' . $decoded_content . '</body></html>';

		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : 'Chat Session Transcript'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $subject ) ) { $subject = 'Chat Session Transcript'; }
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( $to, $subject, $email_body, $headers );
		wp_send_json( array( 'success' => true, 'msg' => esc_html__( 'Session has been forwarded to email successfully', 'chatbot' ) ) );
		wp_die();
	} else {
		wp_send_json( array( 'success' => false, 'msg' => esc_html__( 'No conversation found for this session', 'chatbot' ) ) );
		wp_die();
	}
}

// ─── AJAX: Session Hover Details ─────────────────────────────────────────────
add_action( 'wp_ajax_wpbot_session_hover_details', 'wpbot_session_hover_details_free' );
add_action( 'wp_ajax_nopriv_wpbot_session_hover_details', 'wpbot_session_hover_details_free' );

function wpbot_session_hover_details_free() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json( array( 'success' => false, 'msg' => esc_html__( 'Insufficient permissions', 'chatbot' ) ) );
		wp_die();
	}
	global $wpdb;
	$session_id        = sanitize_text_field( $_POST['session_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$tableconversation = $wpdb->prefix . 'wpbot_conversation';
	$result            = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tableconversation WHERE 1 AND user_id = %d", $session_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	if ( ! empty( $result ) ) {
		$result->status = 'success';
	}
	echo wp_json_encode( $result );
	wp_die();
}

// ─── AJAX: Save Cron Settings ─────────────────────────────────────────────────
add_action( 'wp_ajax_wpbot_seesion_corn_save', 'wpbot_seesion_corn_save_free' );

function wpbot_seesion_corn_save_free() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json( array( 'success' => false, 'msg' => esc_html__( 'Insufficient permissions', 'chatbot' ) ) );
		wp_die();
	}

	$wbsession_ai_enabled             = isset( $_POST['ai_enabled'] ) ? sanitize_text_field( $_POST['ai_enabled'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$wbsession_corn_schedule_interval = isset( $_POST['corn_schedule_interval'] ) ? sanitize_text_field( $_POST['corn_schedule_interval'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$qcld_wpsession_corn_promt        = isset( $_POST['qcld_wpsession_corn_promt'] ) ? sanitize_text_field( $_POST['qcld_wpsession_corn_promt'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$qcld_wbsession_corn_starttime    = isset( $_POST['qcld_wbsession_corn_starttime'] ) ? sanitize_text_field( $_POST['qcld_wbsession_corn_starttime'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	$openai_enabled = get_option( 'ai_enabled' );
	$apiKey         = get_option( 'open_ai_api_key' );

	if ( $openai_enabled != '1' ) {
		wp_send_json( array( 'success' => false, 'icon' => 'error', 'response' => esc_html__( 'OpenAI is Not Enabled', 'chatbot' ) ) );
		wp_die();
	}
	if ( $apiKey == '' ) {
		wp_send_json( array( 'success' => false, 'icon' => 'error', 'response' => esc_html__( 'OpenAI API key is not set', 'chatbot' ) ) );
		wp_die();
	}

	update_option( 'qcld_wbsession_ai_enable', $wbsession_ai_enabled );
	update_option( 'qcld_wbsession_corn_interval', $wbsession_corn_schedule_interval );
	update_option( 'qcld_wbsession_corn_starttime', $qcld_wbsession_corn_starttime );
	update_option( 'qcld_wpsession_corn_promt', $qcld_wpsession_corn_promt );

	wp_clear_scheduled_hook( 'qcld_wpsession_mysql_scraper_event' );
	wp_send_json( array( 'success' => true, 'icon' => 'success', 'response' => esc_html__( 'Settings Saved Successfully', 'chatbot' ) ) );
	wp_die();
}

// ─── AJAX: Manual AI Scraper ──────────────────────────────────────────────────
add_action( 'wp_ajax_qcld_chatbot_session_mannual_scraper', 'qcld_chatbot_session_mannual_scraper_free' );

if ( ! function_exists( 'qcld_chatbot_session_mannual_scraper_free' ) ) {
	function qcld_chatbot_session_mannual_scraper_free() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'msg' => 'Insufficient permissions.' ) );
			wp_die();
		}
		if ( get_option( 'qcld_wbsession_ai_enable' ) != '1' ) {
			wp_send_json( array( 'success' => false, 'icon' => 'error', 'response' => esc_html__( 'AI Insight is not enabled.', 'chatbot' ) ) );
			wp_die();
		}

		global $wpdb;
		$num = isset( $_POST['wpchatbot_session_mannual_number'] ) ? intval( $_POST['wpchatbot_session_mannual_number'] ) : 20; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! is_numeric( $num ) || $num <= 0 ) {
			wp_send_json( array( 'success' => false, 'icon' => 'error', 'response' => esc_html__( 'Invalid number of sessions.', 'chatbot' ) ) );
			wp_die();
		}

		$tableuser         = $wpdb->prefix . 'wpbot_user';
		$tableconversation = $wpdb->prefix . 'wpbot_conversation';
		$results           = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			"SELECT u.id, c.user_id, u.date, u.session_id, c.id AS conversation_id, c.conversation
			 FROM $tableuser AS u LEFT JOIN $tableconversation AS c ON u.id = c.user_id
			 ORDER BY u.date DESC LIMIT %d", $num ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$remarkable_session = array();
		foreach ( $results as $row ) {
			$trimmed = wpsession_message_html_filter_free( htmlspecialchars_decode( $row->conversation ) );
			$remarkable_session[] = array( 'id' => $row->session_id, 'conversation' => $trimmed );
		}

		$keyword    = get_option( 'qcld_wpsession_corn_promt' ) ?: 'Below is Chat conversation session data from our users on our website. Each conversation starts with an ID. Can you analyze each conversation and summarize each of them? Note down the total number of conversations you analyzed. Create a condensed summary at the end of your report for all the conversations. Point out the important questions asked by the users below the summary and include the IDs for the important points.';
		$gptkeyword = array(
			array( 'role' => 'system', 'content' => array( array( 'type' => 'input_text', 'text' => $keyword ) ) ),
			array( 'role' => 'user', 'content' => array( array( 'type' => 'input_text', 'text' => wp_json_encode( $remarkable_session ) ) ) ),
		);

		$api_key     = get_option( 'open_ai_api_key' );
		$engines     = get_option( 'openai_engines' );
		$post_fields = array( 'model' => $engines, 'input' => $gptkeyword );
		$header      = array( 'Content-Type: application/json', 'Authorization: Bearer ' . $api_key );

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, 'https://api.openai.com/v1/responses' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $post_fields ) );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
		$result = curl_exec( $ch );
		curl_close( $ch );

		$mess = json_decode( $result );
		if ( ! empty( $mess->error ) ) {
			wp_send_json( array( 'status' => 'error', 'icon' => 'error', 'msg' => esc_html( $mess->error->code ), 'response' => esc_html( $mess->error->message ) ) );
			wp_die();
		}

		$msg = isset( $mess->output[0]->content ) ? $mess->output[0]->content[0]->text : ( $mess->output[1]->content[0]->text ?? '' );
		$msg = preg_replace( "/\r\n|\r|\n/", '<br/>', $msg );

		$to      = get_option( 'qlcd_wp_chatbot_admin_email' ) ?: get_option( 'admin_email' );
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . esc_html( get_bloginfo( 'name' ) ) . ' <wordpress@' . wp_parse_url( get_site_url(), PHP_URL_HOST ) . '>',
		);
		wp_mail( $to, 'ChatBot Sessions Analysis', $msg, $headers );

		wp_send_json( array( 'status' => 'success', 'icon' => 'success', 'response' => 'Please Check Email for Report' ) );
		wp_die();
	}
}

// ─── Helper: HTML filter for AI scraper ──────────────────────────────────────
if ( ! function_exists( 'wpsession_message_html_filter_free' ) ) {
	function wpsession_message_html_filter_free( $html ) {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );

		$lis               = $dom->getElementsByTagName( 'li' );
		$full_conversation = array();
		foreach ( $lis as $li ) {
			$agentDiv     = $li->getElementsByTagName( 'div' )->item( 1 );
			$paragraphDiv = $li->getElementsByTagName( 'div' )->item( 2 );
			if ( $paragraphDiv ) {
				$full_conversation[] = array(
					'id'           => trim( $agentDiv->textContent ),
					'conversation' => trim( $paragraphDiv->textContent ),
				);
			}
		}
		return wp_json_encode( $full_conversation );
	}
}

// ─── Helper: Conversation Extract ────────────────────────────────────────────
if ( ! function_exists( 'qcld_wpch_conversation_extract' ) ) {
	function qcld_wpch_conversation_extract( $html ) {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html );
		$lis      = iterator_to_array( $doc->getElementsByTagName( 'li' ) );
		$messages = array();
		foreach ( $lis as $li ) {
			if ( strpos( $li->getAttribute( 'class' ), 'wp-chatbot-msg' ) !== false ) {
				$messages[]['bot'] = trim( $li->textContent );
			}
			if ( strpos( $li->getAttribute( 'class' ), 'wp-chat-user-msg' ) !== false ) {
				$messages[]['user'] = trim( $li->textContent );
			}
		}
		$messages = array_filter( $messages, function( $val ) {
			if ( isset( $val['bot'] ) && empty( $val['bot'] ) ) { return false; }
			return true;
		} );
		return $messages;
	}
}

// ─── CSV Export Helpers ───────────────────────────────────────────────────────
add_action( 'admin_post_wpbot_conversations.csv', 'wpbot_conversations_csv_export_free' );

function wpbot_conversations_csv_export_free() {
	global $wpdb;
	$tableuser         = $wpdb->prefix . 'wpbot_user';
	$tableconversation = $wpdb->prefix . 'wpbot_conversation';
	$userid            = sanitize_text_field( $_GET['user_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$userinfo = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tableuser WHERE 1 AND id = %d", $userid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$result   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tableconversation WHERE 1 AND user_id = %d", $userid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$data     = array();

	if ( ! empty( $result ) ) {
		$data[]   = array( 'User Name', $userinfo->name );
		$data[]   = array( 'User Email', $userinfo->email );
		$data[]   = array( 'Session ID', $userinfo->session_id );
		$data[]   = array( 'Date', date( 'M,d,Y h:i:s A', strtotime( $userinfo->date ) ) );
		$data[]   = array( 'Bot Message', 'User Message' );
		$messages = qcld_wpch_conversation_extract( htmlspecialchars_decode( $result->conversation ) );
		foreach ( $messages as $message ) {
			if ( isset( $message['bot'] ) && trim( $message['bot'] ) != '' ) {
				$data[] = array( str_replace( '&nbsp;', ' ', trim( $message['bot'] ) ), '' );
			}
			if ( isset( $message['user'] ) && trim( $message['user'] ) != '' ) {
				$data[] = array( '', str_replace( '&nbsp;', ' ', trim( $message['user'] ) ) );
			}
		}
	}
	qcld_wpbot_chatsession_download_send_headers( $userinfo->name . '_wpbot_chatsession_' . date( 'Y-m-d' ) . '.csv' );
	print wpbot_chatsession_array2csv( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw CSV download, escaping would corrupt the file.
}

if ( ! function_exists( 'wpbot_conversations_export' ) ) {
	function wpbot_conversations_export( $user ) {
		$user_id   = isset( $user->id ) ? $user->id : $user;
		$dataArray = array();
		if ( ! empty( $user ) ) {
			$messages  = qcld_wpch_conversation_extract( htmlspecialchars_decode( $user->conversation ) );
			$dataArray = array(
				'Session ID' => $user->session_id,
				'Date'       => date( 'M,d,Y h:i:s A', strtotime( $user->date ) ),
				'User Name'  => $user->name,
				'User Email' => $user->email,
			);
			$conversations = '';
			foreach ( $messages as $message ) {
				if ( isset( $message['bot'] ) && trim( $message['bot'] ) != '' ) {
					$conversations .= 'Bot Message: ' . str_replace( '&nbsp;', ' ', trim( $message['bot'] ) ) . "\n";
				}
				if ( isset( $message['user'] ) && trim( $message['user'] ) != '' ) {
					$conversations .= 'User Message: ' . str_replace( '&nbsp;', ' ', trim( $message['user'] ) ) . "\n";
				}
			}
			$dataArray['Conversations'] = $conversations;
		}
		$dataArray['Interaction'] = $user->interaction;
		return $dataArray;
	}
}

if ( ! function_exists( 'qcld_wpbot_chatsession_download_send_headers' ) ) {
	function qcld_wpbot_chatsession_download_send_headers( $filename ) {
		$now = gmdate( 'D, d M Y H:i:s' );
		header( 'Expires: Tue, 03 Jul 2001 06:00:00 GMT' );
		header( 'Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate' );
		header( "Last-Modified: {$now} GMT" );
		header( 'Content-Encoding: UTF-8' );
		header( 'Content-type: text/csv; charset=UTF-8' );
		header( "Content-Disposition: attachment;filename={$filename}" );
		header( 'Content-Transfer-Encoding: binary' );
	}
}

if ( ! function_exists( 'wpbot_chatsession_array2csv' ) ) {
	function wpbot_chatsession_array2csv( array &$array ) {
		if ( count( $array ) == 0 ) { return null; }
		ob_start();
		$df = fopen( 'php://output', 'w' );
		fputs( $df, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // UTF-8 BOM
		foreach ( $array as $data ) {
			fputcsv( $df, array_keys( $data ) );
			break;
		}
		foreach ( $array as $row ) {
			fputcsv( $df, $row );
		}
		fclose( $df );
		return ob_get_clean();
	}
}

// ─── Shortcode: User Session History ─────────────────────────────────────────
if ( ! function_exists( 'qc_current_user_session' ) ) {
	function qc_current_user_session() {
		$user               = wp_get_current_user();
		global $wpdb;
		$tableuser          = $wpdb->prefix . 'wpbot_user';
		$conversatios_table = $wpdb->prefix . 'wpbot_conversation';
		$result             = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $tableuser AS u LEFT JOIN $conversatios_table AS c ON u.user_id = c.user_id WHERE u.user_id = %d", $user->ID ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		if ( ! $user->exists() ) {
			return '<p>No user logged in.</p>';
		}
		ob_start(); ?>
		<style>
			.cell-content #wp-chatbot-messages-container { height: 200px; overflow: scroll; overflow-x: hidden; }
			.session_start_url { display: none; }
			.table-striped tr { border: 2px solid #888; }
		</style>
		<table class="table table-striped align-middle" id="chatsession-table">
		<thead>
			<tr class="table-primary">
				<th class="text-left"><?php echo esc_html__( 'Date', 'chatbot' ); ?></th>
				<th class="text-left"><?php echo esc_html__( 'Session ID', 'chatbot' ); ?></th>
				<th class="text-left"><?php echo esc_html__( 'Name', 'chatbot' ); ?></th>
				<th class="text-left" data-dt-order="disable"><?php echo esc_html__( 'Conversation', 'chatbot' ); ?></th>
			</tr>
			<?php foreach ( $result as $key => $value ) : ?>
			<tr>
				<td class="text-left"><?php echo esc_html( $value->date ); ?></td>
				<td class="text-left"><?php echo esc_html( $value->session_id ); ?></td>
				<td class="text-left"><?php echo esc_html( $value->name ); ?></td>
				<td class="text-left"><div class="cell-content"><a class="qcld-modal-content" data-value="<?php echo esc_attr( $value->conversation ); ?>">view data</a></div></td>
			</tr>
			<?php endforeach; ?>
		</thead>
		</table>
		<?php
		return ob_get_clean();
	}
}
add_shortcode( 'qcpress_user', 'qc_current_user_session' );

// ─── WP-Cron: AI Insight Scheduled Email ─────────────────────────────────────
add_filter( 'cron_schedules', 'qcld_wpsession_wp_cron_schedule_free' );

if ( ! function_exists( 'qcld_wpsession_wp_cron_schedule_free' ) ) {
	function qcld_wpsession_wp_cron_schedule_free( $schedules ) {
		$schedules['session_schedules'] = array(
			'interval' => ( get_option( 'qcld_wbsession_corn_interval' ) != null ) ? get_option( 'qcld_wbsession_corn_interval' ) : 86400,
			'display'  => esc_attr( 'Session min', 'wpchatbot' ),
		);
		return $schedules;
	}
}

$wpsession_corn_start_times = wp_date( 'Y-m-d' ) . ' ' . get_option( 'qcld_wbsession_corn_starttime' );
$wpsession_corn_start_time  = strtotime( $wpsession_corn_start_times );
if ( ! wp_next_scheduled( 'qcld_wpsession_mysql_scraper_event' ) && ( get_option( 'qcld_wbsession_ai_enable' ) == '1' ) ) {
	wp_schedule_event( $wpsession_corn_start_time, 'session_schedules', 'qcld_wpsession_mysql_scraper_event' );
}

add_action( 'qcld_wpsession_mysql_scraper_event', 'qcld_wpsession_mysql_scraper_function_free' );

if ( ! function_exists( 'qcld_wpsession_mysql_scraper_function_free' ) ) {
	function qcld_wpsession_mysql_scraper_function_free() {
		if ( get_option( 'qcld_wbsession_ai_enable' ) != '1' ) { return; }

		global $wpdb;
		$interval_hours    = ( (int) get_option( 'qcld_wbsession_corn_interval' ) ?: 86400 ) / 3600;
		$tableuser         = $wpdb->prefix . 'wpbot_user';
		$tableconversation = $wpdb->prefix . 'wpbot_conversation';
		$results           = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			"SELECT u.id, c.user_id, u.date, u.session_id, c.id, c.conversation
			 FROM $tableuser AS u LEFT JOIN $tableconversation AS c ON u.id = c.user_id
			 WHERE u.date >= (NOW() - INTERVAL %d HOUR) ORDER BY u.date DESC", $interval_hours ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$remarkable_session = array();
		foreach ( $results as $row ) {
			$remarkable_session[] = array( 'id' => $row->session_id, 'conversation' => wpsession_message_html_filter_free( htmlspecialchars_decode( $row->conversation ) ) );
		}

		$keyword    = get_option( 'qcld_wpsession_corn_promt' ) ?: 'Below is Chat conversation session data from our users on our website. Each conversation starts with an ID. Can you analyze each conversation and summarize each of them?';
		$gptkeyword = array(
			array( 'role' => 'system', 'content' => array( array( 'type' => 'input_text', 'text' => $keyword ) ) ),
			array( 'role' => 'user', 'content' => array( array( 'type' => 'input_text', 'text' => wp_json_encode( $remarkable_session ) ) ) ),
		);

		$api_key     = get_option( 'open_ai_api_key' );
		$engines     = get_option( 'openai_engines' );
		$post_fields = array( 'model' => $engines, 'input' => $gptkeyword );
		$header      = array( 'Content-Type: application/json', 'Authorization: Bearer ' . $api_key );

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, 'https://api.openai.com/v1/responses' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $post_fields ) );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
		$result = curl_exec( $ch );
		curl_close( $ch );

		$mess = json_decode( $result );
		$msg  = isset( $mess->output[0]->content[0]->text ) ? $mess->output[0]->content[0]->text : ( isset( $mess->output[1]->content[0]->text ) ? $mess->output[1]->content[0]->text : 'No response from OpenAI.' );
		$msg  = preg_replace( "/\r\n|\r|\n/", '<br/>', $msg );

		$to      = get_option( 'qlcd_wp_chatbot_admin_email' ) ?: get_option( 'admin_email' );
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . esc_html( get_bloginfo( 'name' ) ) . ' <wordpress@' . wp_parse_url( get_site_url(), PHP_URL_HOST ) . '>',
		);
		wp_mail( $to, 'ChatBot Sessions Analysis', $msg, $headers );
	}
}

// ─── Reports (Bot - Reports submenu) ─────────────────────────────────────────
require_once QCLD_CHATBOT_FREE_SESSION_DIR_PATH . 'reports/chatbot-history-reporting.php';
