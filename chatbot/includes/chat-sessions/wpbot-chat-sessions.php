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
		// All menu registration is now handled by chatbot (qcld-wpwbot.php)
		// We no longer register submenus here to keep the sidebar clean.
	}

	function qc_wpbot_cs_tabbed_wrapper() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'sessions';

		$tabs = array(
			'sessions'     => array(
				'label' => __( 'Chat Sessions', 'wpbot-chat-history' ),
				'icon'  => 'dashicons-format-chat',
			),
			'not-answered' => array(
				'label' => __( 'Questions Not Answered', 'wpbot-chat-history' ),
				'icon'  => 'dashicons-editor-help',
			),
			'ai-insight'   => array(
				'label' => __( 'AI Insight', 'wpbot-chat-history' ),
				'icon'  => 'dashicons-lightbulb',
			),
		);

		if ( function_exists( 'qcpdcs_is_woowbot_active' ) && qcpdcs_is_woowbot_active() ) {
			$tabs['woowbot-sessions'] = array(
				'label' => __( 'ChatBot Sessions', 'wpbot-chat-history' ),
				'icon'  => 'dashicons-cart',
			);
		}

		?>
		<div class="wrap qcld-main-wrapper qcld-chat-sessions-wrap">
			<h1 style="display:none"><?php esc_html_e( 'Chat Sessions', 'wpbot-chat-history' ); ?></h1>

			<div class="qcld-wp-chatbot-wrap-header">
				<div class="qcld-wp-chatbot-wrap-header-logo">
					<a href="#" class="qcld-wp-chatbot-wrap-site__logo">
						<img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/chatbot.png' ); ?>" alt="WPBot"> WPBot Control Panel
					</a>
					<p><strong>Core Version:</strong> v<?php echo esc_html( QCLD_wpCHATBOT_VERSION ); ?></p>
				</div>
				<ul class="qcld-wp-chatbot-wrap-version-wrapper">
					<li>
						<a class="wpchatbot-Upgrade" href="https://www.wpbot.pro/" target="_blank"><?php esc_html_e( 'Upgrade To Pro', 'chatbot' ); ?></a>
					</li>
				</ul>
			</div>

			<div class="qcld-wp-chatbot-wrap-header_inn qcld-chat-sessions-header">
				<div class="qcld-wp-chatbot-wrap-header_inn_heading">
					<h1 class="wp-heading-inline"><?php esc_html_e( 'Sessions & Analytics', 'wpbot-chat-history' ); ?></h1>
				</div>
				<nav class="nav-tab-wrapper qcld-chat-sessions-tabs">
					<?php foreach ( $tabs as $tab_id => $tab ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbcs-botsessions-page&tab=' . rawurlencode( $tab_id ) ) ); ?>" class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
							<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
							<span><?php echo esc_html( $tab['label'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</nav>
			</div>

			<div class="qcld-chat-sessions-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'not-answered':
						if ( function_exists( 'qcld_wpbot_not_answered_question' ) ) {
							qcld_wpbot_not_answered_question();
						}
						break;
					case 'ai-insight':
						if ( function_exists( 'qcld_wpbot_schedule_session_reporting' ) ) {
							qcld_wpbot_schedule_session_reporting();
						}
						break;
					case 'woowbot-sessions':
						if ( function_exists( 'woowbot_cs_menu_page_callback_func' ) ) {
							woowbot_cs_menu_page_callback_func();
						}
						break;
					case 'sessions':
					default:
						if ( function_exists( 'qc_wpbot_cs_menu_page_callback_func' ) ) {
							qc_wpbot_cs_menu_page_callback_func();
						}
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

// ─── Admin Scripts & Styles ───────────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', 'qcld_wb_chatbot_session_admin_scripts_free' );

function qcld_wb_chatbot_session_admin_scripts_free( $hook ) {
		// Only enqueue on our specific sessions page
		if ( ! isset( $_GET['page'] ) || 'wbcs-botsessions-page' !== $_GET['page'] ) {
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

	wp_register_style( 'qcld-wp-chatbot-history-style', QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL . 'css/history-style.css', array( 'qlcd-wp-chatbot-admin-style' ), QCLD_wpCHATBOT_VERSION . '.' . (int) @filemtime( __DIR__ . '/css/history-style.css' ), 'screen' );
	wp_enqueue_style( 'qcld-wp-chatbot-history-style' );

	// SweetAlert2 — used by admin.js for Swal.fire() and Swal.showLoading()
	wp_register_script( 'qcld-wp-chatbot-sweetalrt-cs', QCLD_wpCHATBOT_PLUGIN_URL . 'js/sweetalrt.js', array( 'jquery' ), QCLD_wpCHATBOT_VERSION, true );
	wp_enqueue_script( 'qcld-wp-chatbot-sweetalrt-cs' );

	// admin.js depends on SweetAlert2 being loaded first
	wp_register_script( 'qcld-wp-session-admin-cs', QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL . 'js/admin.js', array( 'jquery', 'qcld-wp-chatbot-sweetalrt-cs' ), QCLD_wpCHATBOT_VERSION . '.' . (int) @filemtime( __DIR__ . '/js/admin.js' ), true );
	wp_enqueue_script( 'qcld-wp-session-admin-cs' );
	wp_localize_script(
		'qcld-wp-session-admin-cs',
		'ajax_object',
		array( 
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'ajax_nonce' => wp_create_nonce( 'wpbot_session_ajax_nonce' )
		)
	);

	wp_register_script( 'qcld-wp-dataTables-cs', QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL . 'js/qcld-dataTables.min.js', array( 'jquery' ), QCLD_wpCHATBOT_VERSION, true );
	wp_enqueue_script( 'qcld-wp-dataTables-cs' );
}


// ─── AI Insight Page Callback ─────────────────────────────────────────────────
function qcld_wpbot_schedule_session_reporting() {
	wp_register_style( 'qcld-wp-chatbot-history-style', QCLD_CHATBOT_FREE_SESSION_PLUGIN_URL . 'css/history-style.css', array(), QCLD_wpCHATBOT_VERSION, 'screen' );
	wp_enqueue_style( 'qcld-wp-chatbot-history-style' );
	?>
	<div class="wrap wpbot-ai-insight-page">
		<div class="wpbot-ai-insight-header">
			<h2><?php echo esc_html__( 'AI Insight', 'chatbot' ); ?></h2>
			<p><?php echo esc_html__( 'Scheduled AI summaries of your chat conversations.', 'chatbot' ); ?></p>
		</div>

		<div class="wpbot-ai-insight-locked">
			<div class="wpbot-ai-insight-locked__icon" aria-hidden="true">
				<span class="dashicons dashicons-lock"></span>
			</div>
			<h3><?php echo esc_html__( 'Feature Locked', 'chatbot' ); ?></h3>
			<p class="wpbot-ai-insight-locked__desc">
				<?php echo esc_html__( 'The AI Insight feature allows you to receive an AI-based summary of all chat conversations emailed directly to you on a schedule.', 'chatbot' ); ?>
			</p>
			<p class="wpbot-ai-insight-locked__cta">
				<?php echo esc_html__( 'Please upgrade to WPBot Pro to unlock this feature!', 'chatbot' ); ?>
			</p>
			<a href="https://www.wpbot.pro/" target="_blank" rel="noopener noreferrer" class="wpbot-ai-insight-locked__btn">
				<?php echo esc_html__( 'Upgrade to Pro', 'chatbot' ); ?>
			</a>
		</div>
	</div>
	<?php
}


// ─── Questions Not Answered Page Callback ─────────────────────────────────────
function qcld_wpbot_not_answered_question() {
	global $wpdb;
	$wpdb->show_errors = true; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$table             = $wpdb->prefix . 'wpbot_failed_response'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter

	if ( isset( $_GET['msg'] ) && $_GET['msg'] == 'success' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success"><p>Record has been Deleted Successfully!</p></div>';
	}

	if ( isset( $_GET['action'] ) && $_GET['action'] == 'deleteall' && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpcs_delete_all' ) ) { // Security: nonce required so a crafted link cannot wipe all records.
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
	$wpdb->show_errors = true; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter

	$tableuser         = $wpdb->prefix . 'wpbot_user'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$tableconversation = $wpdb->prefix . 'wpbot_conversation'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$mainurl           = admin_url( 'admin.php?page=wbcs-botsessions-page' );

	if ( isset( $_GET['min_interaction'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$mainurl .= '&min_interaction=' . intval( $_GET['min_interaction'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	if ( isset( $_GET['wp_user'] ) && $_GET['wp_user'] != '' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$mainurl .= '&wp_user=' . intval( $_GET['wp_user'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	$msg = '';

	if ( isset( $_GET['action'] ) && $_GET['action'] == 'deleteall' && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpcs_delete_all' ) ) { // Security: nonce required so a crafted link cannot wipe all records.
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
			array( 
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'ajax_nonce' => wp_create_nonce( 'wpbot_session_ajax_nonce' )
			)
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
			$customPagHTML = '<div class="qcld-session-pagination"><span class="wpbot_pagination">Page ' . esc_html( $page ) . ' of ' . esc_html( $totalPage ) . '</span>' . paginate_links(
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

		$deleteurl = wp_nonce_url( admin_url( 'admin.php?page=wbcs-botsessions-page&action=deleteall' ), 'wpcs_delete_all' );
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


			<form id="wpcs_form_sessions" action="<?php echo esc_url( $mainurl ); ?>" method="POST">
				<?php wp_nonce_field( 'wpcs_bulk_action' ); ?>
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
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;
	$wpdb->show_errors = true; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter

	$tableuser1         = $wpdb->prefix . 'wpbot_user'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$tableconversation1 = $wpdb->prefix . 'wpbot_conversation'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$table              = $wpdb->prefix . 'wpbot_failed_response'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter

	// Delete single "not answered" record.
	if ( isset( $_GET['page'] ) && $_GET['page'] == 'wbcs-botsessions-notansweredpage' && isset( $_GET['act'] ) && $_GET['act'] == 'delete' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$userid = intval( $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'wpcs_delete_session_' . $userid );
		$wpdb->delete( $table, array( 'id' => $userid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		wp_safe_redirect( admin_url( 'admin.php?page=wbcs-botsessions-notansweredpage&msg=success' ) );
		exit;
	}

	// Delete single chat session.
	if ( isset( $_GET['page'] ) && $_GET['page'] == 'wbcs-botsessions-page' && isset( $_GET['act'] ) && $_GET['act'] == 'delete' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$userid = intval( $_GET['userid'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'wpcs_delete_session_' . $userid );
		$wpdb->delete( $tableuser1, array( 'id' => $userid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $tableconversation1, array( 'user_id' => $userid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		wp_safe_redirect( admin_url( 'admin.php?page=wbcs-botsessions-page&msg=success' ) );
		exit;
	}

	// Export all sessions as CSV.
	if ( isset( $_POST['wpbot_session_export_all'] ) && isset( $_POST['wpbot_session_remove'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		check_admin_referer( 'wpcs_bulk_action' );
		$users    = $wpdb->get_results( "SELECT wu.`id`, wu.`session_id`, wu.`name`, wu.`email`, wu.`date`, wu.`phone`, wu.`interaction`, wc.`conversation` FROM $tableuser1 as wu, $tableconversation1 as wc WHERE 1 AND wu.id = wc.user_id LIMIT 5000" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$sessions = array();
		if ( ! empty( $users ) ) {
			foreach ( $users as $user ) {
				$sessions[] = wpbot_conversations_export( $user );
			}
		}
		qcld_wpbot_chatsession_download_send_headers( 'wpbot_chatsession_' . gmdate( 'Y-m-d' ) . '.csv' );
		print wpbot_chatsession_array2csv( $sessions ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw CSV download, escaping would corrupt the file.
		exit;
	}

	// Export selected sessions or delete selected sessions.
	if ( isset( $_POST['wpbot_session_remove'] ) && ! empty( $_POST['sessions'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		check_admin_referer( 'wpcs_bulk_action' );
		$userids = array_map( 'intval', $_POST['sessions'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( isset( $_POST['wpbot_session_export'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$sessions = array();
			foreach ( $userids as $userid ) {
				$user       = $wpdb->get_row( $wpdb->prepare( "SELECT wu.`id`, wu.`session_id`, wu.`name`, wu.`email`, wu.`date`, wu.`phone`, wu.`interaction`, wc.`conversation` FROM $tableuser1 as wu, $tableconversation1 as wc WHERE 1 AND wu.id = wc.user_id AND wu.id = %d", $userid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$sessions[] = wpbot_conversations_export( $user );
			}
			qcld_wpbot_chatsession_download_send_headers( 'wpbot_chatsession_' . gmdate( 'Y-m-d' ) . '.csv' );
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

function wpcs_send_email() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die();
	}
	check_ajax_referer( 'wpbot_session_ajax_nonce', 'security' );
	
	$subject = isset( $_POST['data']['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['data']['subject'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$message = isset( $_POST['data']['message'] ) ? wp_kses_post( wp_unslash( $_POST['data']['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$to      = isset( $_POST['data']['to'] ) ? sanitize_email( wp_unslash( $_POST['data']['to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	if ( empty( $to ) || empty( $message ) ) {
		wp_send_json( array( 'status' => 'fail', 'message' => esc_html__( 'Email and Message are required.', 'chatbot' ) ) );
	}

	global $wpdb;
	$tableuser   = $wpdb->prefix . 'wpbot_user';
	$table_sql   = esc_sql( $tableuser );
	$cache_key   = 'wpbot_user_email_' . md5( $to );
	$user_exists = wp_cache_get( $cache_key, 'wpbot' );
	if ( false === $user_exists ) {
		$user_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prepare(
				'SELECT id FROM `' . $table_sql . '` WHERE email = %s LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$to
			)
		);
		wp_cache_set( $cache_key, $user_exists, 'wpbot', 60 );
	}

	$admin_email = get_option( 'admin_email' );
	if ( ! $user_exists && $to !== $admin_email ) {
		wp_send_json( array( 'status' => 'fail', 'message' => 'Invalid recipient address. Email must be a stored session email or admin email.' ) );
	}

	$primary_color = '#5b4e96';
	$site_name     = get_bloginfo( 'name' );
	$site_url      = site_url();
	$from_name     = $site_name . ' Support';
	$date_str      = wp_date( 'M j, Y - g:i A' );

	if ( empty( $subject ) ) {
		$subject = sprintf( esc_html__( 'Message from %s Support', 'chatbot' ), $site_name );
	}

	$from_email = get_option( 'qlcd_wp_chatbot_from_email' ) ?: $admin_email;

	$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
	$headers[] = 'From: ' . esc_html( $from_name ) . ' <' . esc_html( $from_email ) . '>';
	$headers[] = 'Reply-To: ' . esc_html( $from_name ) . ' <' . esc_html( $from_email ) . '>';

	$email_body = '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . esc_html( $subject ) . '</title>
<style>
:root {
	--woobot-primary-color: ' . esc_attr( $primary_color ) . ';
}
.woobot-primary-btn {
	background-color: ' . esc_attr( $primary_color ) . ' !important;
	background-color: var(--woobot-primary-color) !important;
	border: 1px solid ' . esc_attr( $primary_color ) . ' !important;
	border: 1px solid var(--woobot-primary-color) !important;
	color: #ffffff !important;
}
.woobot-primary-tab {
	background-color: ' . esc_attr( $primary_color ) . ' !important;
	background-color: var(--woobot-primary-color) !important;
	border: 1px solid ' . esc_attr( $primary_color ) . ' !important;
	border: 1px solid var(--woobot-primary-color) !important;
	color: #ffffff !important;
}
</style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6fb; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #334155;">
	<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f6fb; padding: 30px 10px;">
		<tr>
			<td align="center">
				<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 640px; background-color: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 6px 24px rgba(91, 78, 150, 0.08); border: 1px solid #e2e8f0;">
					<!-- Header Banner -->
					<tr>
						<td class="woobot-primary-tab" style="background: ' . esc_attr( $primary_color ) . '; background: linear-gradient(135deg, ' . esc_attr( $primary_color ) . ' 0%, #463a7a 100%); padding: 32px 30px; text-align: left; color: #ffffff;">
							<div style="font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.85); margin-bottom: 6px;">' . esc_html( $site_name ) . '</div>
							<h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #ffffff; line-height: 1.3;">' . esc_html__( 'Message from Support', 'chatbot' ) . '</h1>
							<p style="margin: 6px 0 16px 0; font-size: 14px; color: rgba(255,255,255,0.9); line-height: 1.4;">' . esc_html__( 'Our support team has sent you a message.', 'chatbot' ) . '</p>
							<div>
								<span style="display: inline-block; background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3); color: #ffffff; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-right: 6px; margin-top: 4px;">' . esc_html__( 'Support Message', 'chatbot' ) . '</span>
								<span style="display: inline-block; background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3); color: #ffffff; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 4px;">' . esc_html( $date_str ) . '</span>
							</div>
						</td>
					</tr>
					<!-- Content -->
					<tr>
						<td style="padding: 30px 32px; background-color: #ffffff;">
							<!-- Intro -->
							<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 1.6; color: #334155;">
								' . esc_html__( 'Hello,', 'chatbot' ) . '<br>' . esc_html__( 'Thank you for reaching out to us. Here is our message regarding your chat conversation:', 'chatbot' ) . '
							</p>

							<!-- Message Body Card -->
							<div style="background-color: #faf9fd; border-left: 4px solid ' . esc_attr( $primary_color ) . '; border-radius: 0 10px 10px 0; padding: 20px 22px; font-size: 15px; line-height: 1.65; color: #1e293b; margin: 20px 0 24px 0; word-break: break-word;">
								' . nl2br( $message ) . '
							</div>

							<!-- Follow up note -->
							<p style="margin: 20px 0 24px 0; font-size: 14px; line-height: 1.6; color: #64748b;">
								' . esc_html__( 'If you have any questions or need further assistance, please feel free to reply directly to this email.', 'chatbot' ) . '
							</p>

							<!-- CTA Button -->
							<div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center;">
								<a href="' . esc_url( $site_url ) . '" class="woobot-primary-btn" style="background-color: ' . esc_attr( $primary_color ) . '; background-color: var(--woobot-primary-color); border: 1px solid ' . esc_attr( $primary_color ) . '; border: 1px solid var(--woobot-primary-color); color: #ffffff; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; display: inline-block; box-shadow: 0 4px 12px rgba(91, 78, 150, 0.25);">
									' . esc_html__( 'Visit Our Website', 'chatbot' ) . ' &rarr;
								</a>
							</div>
						</td>
					</tr>
					<!-- Footer -->
					<tr>
						<td style="background-color: #faf9fd; padding: 20px 30px; border-top: 1px solid #e4e0f2; text-align: center; color: #64748b; font-size: 12.5px; line-height: 1.5;">
							<p style="margin: 0 0 4px 0;">' . sprintf( esc_html__( 'Sent by the customer support team at %s.', 'chatbot' ), '<strong>' . esc_html( $site_name ) . '</strong>' ) . '</p>
							<p style="margin: 0; color: #94a3b8; font-size: 11.5px;">&copy; ' . gmdate( 'Y' ) . ' ' . esc_html( $site_name ) . ' &bull; <a href="' . esc_url( $site_url ) . '" style="color: ' . esc_attr( $primary_color ) . '; text-decoration: none;">' . esc_html( wp_parse_url( $site_url, PHP_URL_HOST ) ) . '</a></p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';

	$result = wp_mail( $to, $subject, $email_body, $headers );
	if ( $result ) {
		$response = array( 'status' => 'success', 'message' => esc_html__( 'Email has been sent successfully!', 'chatbot' ) );
	} else {
		$response = array( 'status' => 'fail', 'message' => esc_html__( 'Unable to send email. Please contact your server administrator.', 'chatbot' ) );
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

		$tableuser         = $wpdb->prefix . 'wpbot_user'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$tableconversation = $wpdb->prefix . 'wpbot_conversation'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// SECURITY FIX: Pass raw (decoded) input to qcld_wpbot_input_validation(), which now
		// correctly runs html_entity_decode() BEFORE wp_kses(). Previously, wp_kses() ran first
		// on entity-encoded input (&lt;img onerror=...&gt;), saw inert text, and passed it through.
		// html_entity_decode() then revived the executable markup after sanitization had already run.
		$raw_conversation = isset( $_POST['conversation'] ) ? wp_unslash( $_POST['conversation'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		// Security: cap transcript size so a visitor can't fill the database through this public action.
		if ( ! is_string( $raw_conversation ) || strlen( $raw_conversation ) > 2 * MB_IN_BYTES ) {
			echo wp_json_encode( array( 'status' => 'fail' ) );
			die();
		}
		$conversation     = qcld_wpbot_input_validation( $raw_conversation ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email          = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$phone          = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$name           = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$session_id     = isset( $_POST['session_id'] ) ? sanitize_text_field( $_POST['session_id'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$wpuser_id      = get_current_user_id(); // Security: taken from the login, not the request, so a session can't be attached to another user's [qcpress_user] history.
		$source_url     = isset( $_POST['source_url'] ) && ! empty( $_POST['source_url'] ) ? sanitize_url( wp_unslash( $_POST['source_url'] ) ) : ( isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$user_agent     = isset( $_POST['user_agent'] ) && ! empty( $_POST['user_agent'] ) ? sanitize_text_field( wp_unslash( $_POST['user_agent'] ) ) : ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$session_mailed = isset( $_POST['session_mailed'] ) ? sanitize_text_field( $_POST['session_mailed'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Prepend source URL to conversation.
		$conversation = '&#x3C;ul&#x3E;&#x3C;li class=&#x22;session_start_url&#x22;&#x3E;&#x3C;span&#x3E;Source URL: &#x3C;/span&#x3E;&#x3C;a href=&#x22;' . $source_url . '&#x22;&#x3E;' . $source_url . '&#x3C;/a&#x3E;&#x3C;/li&#x3E;&#x3C;/ul&#x3E;' . $conversation;

		$response           = array();
		$response['status'] = 'success';

		// CHECK LOGGED IN USER: Overwrite POST data with verified server-side session info
		if ( is_user_logged_in() ) {
			$wpuser_id = get_current_user_id();
			// Optional: If name/email weren't provided by the chatbot front-end, pull them from the WP profile
			$current_user = wp_get_current_user();
			$name  = isset( $_POST['name'] ) && ! empty( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : $current_user->display_name;
			$email = isset( $_POST['email'] ) && ! empty( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : $current_user->user_email;
		}

		$user_exists = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tableuser WHERE 1 AND session_id = %s", $session_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$is_new_insert = false;

		if ( empty( $user_exists ) ) {
			// Security: limit how many new sessions (rows, cron events and notification flags) one IP can create.
			$new_session_key   = 'wpcs_new_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
			$new_session_count = (int) get_transient( $new_session_key );
			if ( $new_session_count >= 60 ) {
				echo wp_json_encode( array( 'status' => 'fail' ) );
				die();
			}
			set_transient( $new_session_key, $new_session_count + 1, HOUR_IN_SECONDS );

			$lock_key = 'wpcs_lock_' . md5( $session_id );
			if ( add_option( $lock_key, '1', '', 'no' ) ) {
				$interaction = (int) substr_count( $conversation, 'wp-chat-user-msg' );
				if ( $interaction == 0 ) {
					$interaction = (int) substr_count( $conversation, 'woo-chat-user-msg' );
				}

				if ( $interaction != 0 ) {
					$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
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
					$user_id = $wpdb->insert_id; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
					$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
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
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
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
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
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

		if ( $is_new_insert && ( get_option( 'session_email_notification_update' ) == 'checked' ) ) {
			wpbot_schedule_delayed_session_email_notification( $session_id );
		}

		// WPBot Automator Trigger - Debounced by 3 minutes
		$cron_args = array( $session_id );
		if ( wp_next_scheduled( 'wpbot_automator_delayed_trigger', $cron_args ) ) {
			wp_clear_scheduled_hook( 'wpbot_automator_delayed_trigger', $cron_args );
		}
		wp_schedule_single_event( time() + 3 * MINUTE_IN_SECONDS, 'wpbot_automator_delayed_trigger', $cron_args );

		echo wp_json_encode( $response );
		die();
	}
}
add_action( 'wp_ajax_qcld_wb_chatbot_conversation_save', 'qcld_wb_chatbot_conversation_save' );
add_action( 'wp_ajax_nopriv_qcld_wb_chatbot_conversation_save', 'qcld_wb_chatbot_conversation_save' );

add_action( 'wpbot_delayed_session_email_notification', 'wpbot_send_delayed_session_email_notification' );
add_action( 'init', 'wpbot_send_due_session_email_notifications' );

if ( ! function_exists( 'wpbot_schedule_delayed_session_email_notification' ) ) {
	function wpbot_schedule_delayed_session_email_notification( $session_id ) {
		$session_id = sanitize_text_field( $session_id );

		if ( empty( $session_id ) ) {
			return;
		}

		$send_at = time() + ( 5 * MINUTE_IN_SECONDS );
		$queue   = get_option( 'wpbot_delayed_session_email_notification_queue', array() );

		if ( ! is_array( $queue ) ) {
			$queue = array();
		}

		if ( empty( $queue[ $session_id ] ) ) {
			$queue[ $session_id ] = $send_at;
			update_option( 'wpbot_delayed_session_email_notification_queue', $queue, false );
		}

		$notification_args = array( $session_id );
		if ( ! wp_next_scheduled( 'wpbot_delayed_session_email_notification', $notification_args ) ) {
			wp_schedule_single_event( (int) $queue[ $session_id ], 'wpbot_delayed_session_email_notification', $notification_args );
		}
	}
}

if ( ! function_exists( 'wpbot_send_due_session_email_notifications' ) ) {
	function wpbot_send_due_session_email_notifications() {
		// Fallback for sites with WP-Cron disabled; otherwise the single event sends it.
		if ( ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ) {
			return;
		}
		if ( get_option( 'session_email_notification_update' ) != 'checked' ) {
			return;
		}

		if ( get_transient( 'wpbot_delayed_session_email_notification_lock' ) ) {
			return;
		}

		$queue = get_option( 'wpbot_delayed_session_email_notification_queue', array() );
		if ( empty( $queue ) || ! is_array( $queue ) ) {
			return;
		}

		set_transient( 'wpbot_delayed_session_email_notification_lock', 1, MINUTE_IN_SECONDS );

		$now = time();
		foreach ( $queue as $session_id => $send_at ) {
			if ( (int) $send_at <= $now ) {
				wpbot_send_delayed_session_email_notification( $session_id );
			}
		}

		delete_transient( 'wpbot_delayed_session_email_notification_lock' );
	}
}

if ( ! function_exists( 'wpbot_send_delayed_session_email_notification' ) ) {
	function wpbot_send_delayed_session_email_notification( $session_id ) {
		if ( get_option( 'session_email_notification_update' ) != 'checked' ) {
			return;
		}

		global $wpdb;

		$tableuser         = $wpdb->prefix . 'wpbot_user';
		$tableconversation = $wpdb->prefix . 'wpbot_conversation';
		$session_id        = sanitize_text_field( $session_id );

		if ( empty( $session_id ) ) {
			return;
		}

		$send_lock_key = 'wpbot_delayed_session_email_notification_send_' . md5( $session_id );
		if ( get_transient( $send_lock_key ) ) {
			return;
		}
		set_transient( $send_lock_key, 1, MINUTE_IN_SECONDS );

		$sent_flag_key = 'wpbot_delayed_session_email_notification_sent_' . md5( $session_id );
		if ( get_option( $sent_flag_key ) ) {
			$queue = get_option( 'wpbot_delayed_session_email_notification_queue', array() );
			if ( is_array( $queue ) && isset( $queue[ $session_id ] ) ) {
				unset( $queue[ $session_id ] );
				update_option( 'wpbot_delayed_session_email_notification_queue', $queue, false );
			}
			delete_transient( $send_lock_key );
			return;
		}

		$queue = get_option( 'wpbot_delayed_session_email_notification_queue', array() );
		if ( is_array( $queue ) && isset( $queue[ $session_id ] ) ) {
			if ( (int) $queue[ $session_id ] > time() ) {
				delete_transient( $send_lock_key );
				return;
			}
		}

		$userinfo = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT u.id, u.name, u.email, u.phone, u.session_id, u.date, c.conversation, c.environment_info
				FROM {$tableuser} AS u
				LEFT JOIN {$tableconversation} AS c ON c.user_id = u.id
				WHERE u.session_id = %s
				ORDER BY c.id DESC
				LIMIT 1",
				$session_id
			)
		);

		if ( empty( $userinfo ) ) {
			delete_transient( $send_lock_key );
			return;
		}

		$user_id        = ! empty( $userinfo->id ) ? absint( $userinfo->id ) : 0;
		$user_name      = ! empty( $userinfo->name ) ? trim( $userinfo->name ) : esc_html__( 'Anonymous Visitor', 'chatbot' );
		$user_email     = ! empty( $userinfo->email ) ? trim( $userinfo->email ) : '';
		$user_phone     = ! empty( $userinfo->phone ) ? trim( $userinfo->phone ) : '';
		$session_raw_id = ! empty( $userinfo->session_id ) ? trim( $userinfo->session_id ) : $session_id;
		$session_time   = ! empty( $userinfo->date ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $userinfo->date ) ) : current_time( 'F j, Y, g:i a' );
		$browser_info   = ! empty( $userinfo->environment_info ) ? trim( $userinfo->environment_info ) : '';

		$source_url = '';
		if ( ! empty( $userinfo->conversation ) ) {
			$conversation_html = html_entity_decode( $userinfo->conversation, ENT_QUOTES, get_bloginfo( 'charset' ) );
			if ( preg_match( '/class=["\']session_start_url["\'].*?href=["\']([^"\']+)["\']/i', $conversation_html, $matches ) ) {
				$source_url = esc_url_raw( $matches[1] );
			}
		}

		$primary_color = '#5b4e96';
		$site_name     = get_bloginfo( 'name' );
		$admin_email   = get_option( 'admin_email' );
		$to            = get_option( 'qlcd_wp_chatbot_admin_email' ) != '' ? get_option( 'qlcd_wp_chatbot_admin_email' ) : $admin_email;
		$dashboard_url = admin_url( 'admin.php?page=wbcs-botsessions-page' . ( $user_id > 0 ? '&userid=' . $user_id : '' ) );
		$settings_url  = admin_url( 'admin.php?page=wbcs-botsessions-page' );

		$subject = sprintf( esc_html__( '[%s] New Chat Session Started', 'chatbot' ), $site_name );

		// Extract conversation messages
		$messages = array();
		if ( ! empty( $userinfo->conversation ) ) {
			$allowed_html = array_merge(
				wp_kses_allowed_html( 'post' ),
				array(
					'div'  => array(
						'class'  => true,
						'id'     => true,
						'style'  => true,
						'data-*' => true,
					),
					'span' => array(
						'class'  => true,
						'id'     => true,
						'style'  => true,
						'data-*' => true,
					),
					'ul'   => array( 'class' => true ),
					'li'   => array( 'class' => true ),
					'img'  => array(
						'src'   => true,
						'alt'   => true,
						'class' => true,
						'style' => true,
					),
				)
			);

			$messages = qcld_wpch_conversation_extract( wp_kses( htmlspecialchars_decode( $userinfo->conversation ), $allowed_html ) );
		}

		$conversation_rendered = '';
		if ( ! empty( $messages ) && is_array( $messages ) ) {
			foreach ( $messages as $message ) {
				if ( isset( $message['bot'] ) && trim( $message['bot'] ) !== '' ) {
					$bot_text = nl2br( esc_html( trim( $message['bot'] ) ) );
					$conversation_rendered .= '
					<table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom: 12px;">
						<tr>
							<td align="left">
								<div style="display: inline-block; max-width: 88%; background-color: #f1f5f9; border-left: 3px solid ' . esc_attr( $primary_color ) . '; border-left: 3px solid var(--woobot-primary-color); border-radius: 8px; padding: 10px 14px; text-align: left;">
									<div style="font-size: 11px; font-weight: 700; color: ' . esc_attr( $primary_color ) . '; color: var(--woobot-primary-color); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">' . esc_html__( 'ChatBot', 'chatbot' ) . '</div>
									<div style="font-size: 14px; line-height: 1.5; color: #1e293b;">' . $bot_text . '</div>
								</div>
							</td>
						</tr>
					</table>';
				}
				if ( isset( $message['user'] ) && trim( $message['user'] ) !== '' ) {
					$user_text = nl2br( esc_html( $message['user'] ) );
					$conversation_rendered .= '
					<table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom: 12px;">
						<tr>
							<td align="right">
								<div style="display: inline-block; max-width: 88%; background-color: #ecfdf5; border-right: 3px solid #10b981; border-radius: 8px; padding: 10px 14px; text-align: left;">
									<div style="font-size: 11px; font-weight: 700; color: #059669; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">'  . esc_html( $user_name ) . '</div>
									<div style="font-size: 14px; line-height: 1.5; color: #1e293b;">' . $user_text . '</div>
								</div>
							</td>
						</tr>
					</table>';
				}
			}
		}

		if ( empty( $conversation_rendered ) ) {
			$conversation_rendered = '<div style="background-color: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 18px; text-align: center; color: #64748b; font-size: 13px; font-style: italic;">' . esc_html__( 'No conversation transcript recorded for this session.', 'chatbot' ) . '</div>';
		}

		$bodyContent = '
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . esc_html( $subject ) . '</title>
<style>
:root {
	--woobot-primary-color: ' . esc_attr( $primary_color ) . ';
}
.woobot-primary-btn {
	background-color: ' . esc_attr( $primary_color ) . ' !important;
	background-color: var(--woobot-primary-color) !important;
	border: 1px solid ' . esc_attr( $primary_color ) . ' !important;
	border: 1px solid var(--woobot-primary-color) !important;
	color: #ffffff !important;
}
.woobot-primary-tab {
	background-color: ' . esc_attr( $primary_color ) . ' !important;
	background-color: var(--woobot-primary-color) !important;
	border: 1px solid ' . esc_attr( $primary_color ) . ' !important;
	border: 1px solid var(--woobot-primary-color) !important;
	color: #ffffff !important;
}
</style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f8; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
	<table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f4f6f8; padding: 30px 15px;">
		<tr>
			<td align="center">
				<table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 620px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;">
					
					<!-- Header Banner -->
					<tr>
						<td class="woobot-primary-tab" style="background-color: #5b4e96; border: 1px solid #5b4e96; color: #ffffff; padding: 30px 32px; text-align: left;">
							<div style="display: inline-block; background: rgba(255, 255, 255, 0.2); color: #ffffff; font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; padding: 4px 10px; border-radius: 20px; font-weight: 600; margin-bottom: 8px;">
								' . esc_html__( 'Session Alert', 'chatbot' ) . '
							</div>
							<h1 style="color: #010101ff; font-size: 22px; font-weight: 700; margin: 0 0 6px 0; line-height: 1.3;">
								' . esc_html__( 'New Chat Session Started', 'chatbot' ) . '
							</h1>
							<p style="color: #050505ff; opacity: 0.9; font-size: 14px; margin: 0;">
								' . sprintf( esc_html__( 'A visitor has started a new conversation on %s.', 'chatbot' ), '<strong>' . esc_html( $site_name ) . '</strong>' ) . '
							</p>
						</td>
					</tr>

					<!-- Content Body -->
					<tr>
						<td style="padding: 28px 32px;">
							
							<!-- Session Details Card -->
							<table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 25px; overflow: hidden;">
								<tr>
									<td colspan="2" class="woobot-primary-tab" style="padding: 12px 18px; background-color: ' . esc_attr( $primary_color ) . '; background-color: var(--woobot-primary-color); border: 1px solid ' . esc_attr( $primary_color ) . '; border: 1px solid var(--woobot-primary-color); color: #343333ff; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
										' . esc_html__( 'Session Information', 'chatbot' ) . '
									</td>
								</tr>
								<tr>
									<td style="padding: 10px 18px; font-size: 13px; color: #64748b; width: 35%; border-bottom: 1px solid #f1f5f9;">' . esc_html__( 'Session ID', 'chatbot' ) . '</td>
									<td style="padding: 10px 18px; font-size: 13px; color: #0f172a; font-weight: 600; font-family: monospace; border-bottom: 1px solid #f1f5f9;">' . esc_html( $session_raw_id ) . '</td>
								</tr>
								<tr>
									<td style="padding: 10px 18px; font-size: 13px; color: #64748b; border-bottom: 1px solid #f1f5f9;">' . esc_html__( 'Visitor Name', 'chatbot' ) . '</td>
									<td style="padding: 10px 18px; font-size: 13px; color: #0f172a; font-weight: 600; border-bottom: 1px solid #f1f5f9;">'  . esc_html( $user_name ) . '</td>
								</tr>
								' . ( ! empty( $user_email ) ? '
								<tr>
									<td style="padding: 10px 18px; font-size: 13px; color: #64748b; border-bottom: 1px solid #f1f5f9;">' . esc_html__( 'Email', 'chatbot' ) . '</td>
									<td style="padding: 10px 18px; font-size: 13px; color: ' . esc_attr( $primary_color ) . '; font-weight: 600; border-bottom: 1px solid #f1f5f9;"><a href="mailto:' . esc_attr( $user_email ) . '" style="color: ' . esc_attr( $primary_color ) . '; color: var(--woobot-primary-color); text-decoration: none;">' . esc_html( $user_email ) . '</a></td>
								</tr>' : '' ) . '
								' . ( ! empty( $user_phone ) ? '
								<tr>
									<td style="padding: 10px 18px; font-size: 13px; color: #64748b; border-bottom: 1px solid #f1f5f9;">' . esc_html__( 'Phone', 'chatbot' ) . '</td>
									<td style="padding: 10px 18px; font-size: 13px; color: #0f172a; font-weight: 600; border-bottom: 1px solid #f1f5f9;"><a href="tel:' . esc_attr( $user_phone ) . '" style="color: #0f172a; text-decoration: none;">' . esc_html( $user_phone ) . '</a></td>
								</tr>' : '' ) . '
								<tr>
									<td style="padding: 10px 18px; font-size: 13px; color: #64748b; border-bottom: 1px solid #f1f5f9;">' . esc_html__( 'Date & Time', 'chatbot' ) . '</td>
									<td style="padding: 10px 18px; font-size: 13px; color: #0f172a; border-bottom: 1px solid #f1f5f9;">' . esc_html( $session_time ) . '</td>
								</tr>
								' . ( ! empty( $source_url ) ? '
								<tr>
									<td style="padding: 10px 18px; font-size: 13px; color: #64748b; border-bottom: 1px solid #f1f5f9;">' . esc_html__( 'Page Link', 'chatbot' ) . '</td>
									<td style="padding: 10px 18px; font-size: 13px; color: ' . esc_attr( $primary_color ) . '; border-bottom: 1px solid #f1f5f9; word-break: break-all;"><a href="' . esc_url( $source_url ) . '" target="_blank" style="color: ' . esc_attr( $primary_color ) . '; color: var(--woobot-primary-color); text-decoration: underline;">' . esc_html( $source_url ) . '</a></td>
								</tr>' : '' ) . '
								' . ( ! empty( $browser_info ) ? '
								<tr>
									<td style="padding: 10px 18px; font-size: 13px; color: #64748b;">' . esc_html__( 'Browser', 'chatbot' ) . '</td>
									<td style="padding: 10px 18px; font-size: 13px; color: #475569; word-break: break-all;">' . esc_html( $browser_info ) . '</td>
								</tr>' : '' ) . '
							</table>

							<!-- Dashboard CTA Button -->
							<table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom: 30px;">
								<tr>
									<td align="center">
										<a href="' . esc_url( $dashboard_url ) . '" target="_blank" class="woobot-primary-btn" style="background-color: #5b4e96 !important; border: 1px solid #5b4e96 !important; color: #fff !important; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 6px; display: inline-block; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);">
											' . esc_html__( 'View Session in Dashboard', 'chatbot' ) . ' &rarr;
										</a>
									</td>
								</tr>
							</table>

							<!-- Transcript Section Header Tab -->
							<table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom: 15px;">
								<tr>
									<td class="woobot-primary-tab" style="padding: 10px 16px; background-color: #5b4e96 !important; border: 1px solid #5b4e96 !important; color: #fff; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; border-radius: 6px;">
										' . esc_html__( 'View Full Conversation', 'chatbot' ) . '
									</td>
								</tr>
							</table>

							<!-- Transcript Messages -->
							<div style="margin-bottom: 20px;">
								' . $conversation_rendered . '
							</div>

						</td>
					</tr>

					<!-- Footer -->
					<tr>
						<td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 30px; text-align: center;">
							<p style="margin: 0 0 6px 0; font-size: 12px; color: #64748b; line-height: 1.5;">
								' . sprintf( esc_html__( 'This notification was sent automatically by %s WPBot.', 'chatbot' ), '<strong>' . esc_html( $site_name ) . '</strong>' ) . '
							</p>
							<p style="margin: 0; font-size: 11px; color: #94a3b8; line-height: 1.5;">
								' . sprintf(
									esc_html__( 'To change notification preferences, visit %s.', 'chatbot' ),
									'<a href="' . esc_url( $settings_url ) . '" style="color: ' . esc_attr( $primary_color ) . '; color: var(--woobot-primary-color); text-decoration: underline;">' . esc_html__( 'Bot Sessions Settings', 'chatbot' ) . '</a>'
								) . '
							</p>
						</td>
					</tr>

				</table>
			</td>
		</tr>
	</table>
</body>
</html>';

		$from_email = get_option( 'qlcd_wp_chatbot_from_email' ) ?: $admin_email;
		$from_name  = $site_name . ' Support';
		$headers    = array( 'Content-Type: text/html; charset=UTF-8' );
		$headers[]  = 'From: ' . esc_html( $from_name ) . ' <' . esc_html( $from_email ) . '>';

		update_option( $sent_flag_key, 1, false );
		$mail_sent = wp_mail( $to, $subject, $bodyContent, $headers );

		if ( is_array( $queue ) && isset( $queue[ $session_id ] ) ) {
			unset( $queue[ $session_id ] );
			update_option( 'wpbot_delayed_session_email_notification_queue', $queue, false );
		}

		delete_transient( $send_lock_key );
	}
}

// ─── AJAX: Date Filter ────────────────────────────────────────────────────────
add_action( 'wp_ajax_qcld_chatbot_session_date_filter', 'qcld_chatbot_session_date_filter_free' );

function qcld_chatbot_session_date_filter_free() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die();
	}
	check_ajax_referer( 'wpbot_session_ajax_nonce', 'security' );
	global $wpdb;
	$tableuser  = $wpdb->prefix . 'wpbot_user'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$start_date = sanitize_text_field( $_POST['start_date'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$end_date   = sanitize_text_field( $_POST['end_date'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$result     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $tableuser WHERE date BETWEEN %s AND %s", $start_date, $end_date ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	echo wp_json_encode( $result );
	wp_die();
}

// ─── AJAX: Email Transcript ───────────────────────────────────────────────────
add_action( 'wp_ajax_wpbot_send_email_transcript', 'wpbot_send_email_transcript_free' );

function wpbot_send_email_transcript_free() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json( array( 'status' => 'fail', 'message' => 'Unauthorized' ) );
	}
	check_ajax_referer( 'wpbot_session_ajax_nonce', 'security' );

	global $wpdb;

	$session = trim( sanitize_text_field( $_POST['session'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

	$url         = wp_parse_url( get_site_url() );
	$domain      = $url['host'];
	$admin_email = get_option( 'admin_email' );
	$fromEmail   = get_option( 'qlcd_wp_chatbot_from_email' ) ? get_option( 'qlcd_wp_chatbot_from_email' ) : 'wordpress@' . $domain;
	$subject     = 'Chat transcript by ' . get_bloginfo( 'name' );

	$tableuser         = $wpdb->prefix . 'wpbot_user'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$tableconversation = $wpdb->prefix . 'wpbot_conversation'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter

	$user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tableuser WHERE 1 AND session_id = %s", $session ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

	$response = array( 'status' => 'fail', 'message' => 'Session not found.' );

	if ( ! empty( $user ) ) {
		$email = sanitize_email( $user->email ); // Use email from user record
		if ( empty( $email ) ) {
			wp_send_json( array( 'status' => 'fail', 'message' => 'User has no email.' ) );
		}
		
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

function forward_session_to_email_free() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json( array( 'success' => false, 'msg' => esc_html__( 'Insufficient permissions', 'chatbot' ) ) );
		wp_die();
	}
	check_ajax_referer( 'wpbot_session_ajax_nonce', 'security' );
	global $wpdb;

	$session_id        = sanitize_text_field( $_POST['session_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$to                = sanitize_email( $_POST['email'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$tableuser         = $wpdb->prefix . 'wpbot_user'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$tableconversation = $wpdb->prefix . 'wpbot_conversation'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter

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

function wpbot_session_hover_details_free() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json( array( 'success' => false, 'msg' => esc_html__( 'Insufficient permissions', 'chatbot' ) ) );
		wp_die();
	}
	check_ajax_referer( 'wpbot_session_ajax_nonce', 'security' );
	global $wpdb;
	$session_id        = sanitize_text_field( $_POST['session_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$tableconversation = $wpdb->prefix . 'wpbot_conversation';
	$tableuser         = $wpdb->prefix . 'wpbot_user';
	$email_from 	   = get_option( 'qlcd_wp_chatbot_from_email' ); 
	$result            = $wpdb->get_row( $wpdb->prepare( "SELECT c.*, u.email, u.name, u.session_id as user_session_id FROM $tableconversation AS c LEFT JOIN $tableuser AS u ON c.user_id = u.id WHERE c.user_id = %d", $session_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	if ( ! empty( $result ) ) {
		$result->email_from = $email_from;
		$result->status     = 'success';

		// SECURITY FIX: The stored conversation is entity-encoded (htmlspecialchars output).
		// Decode it and re-sanitize with wp_kses before returning to the admin UI.
		// This guarantees admin.js always receives clean, safe HTML — no onerror/onclick can survive.
		if ( isset( $result->conversation ) ) {
			$decoded = html_entity_decode( (string) $result->conversation, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$result->conversation = wp_kses( $decoded, wpbot_get_safe_conversation_tags() );
		}
	}
	echo wp_json_encode( $result );
	wp_die();
}

// ─── AJAX: Send Reply Email ───────────────────────────────────────────────────
add_action( 'wp_ajax_wpbot_send_reply_email', 'wpbot_send_reply_email_free' );

function wpbot_send_reply_email_free() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json( array( 'success' => false, 'msg' => esc_html__( 'Insufficient permissions', 'chatbot' ) ) );
		wp_die();
	}
	check_ajax_referer( 'wpbot_session_ajax_nonce', 'security' );

	$to       = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$from_raw = isset( $_POST['from_email'] ) ? trim( wp_unslash( $_POST['from_email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$from     = sanitize_email( $from_raw );
	$subject  = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$message  = isset( $_POST['message'] ) ? wp_kses_post( wp_unslash( $_POST['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

	if ( empty( $to ) || empty( $message ) ) {
		wp_send_json( array( 'success' => false, 'msg' => esc_html__( 'Email and Message are required', 'chatbot' ) ) );
		wp_die();
	}

	$primary_color = '#5b4e96';
	$site_name     = get_bloginfo( 'name' );
	$site_url      = site_url();
	$from_name     = $site_name . ' Support';
	$date_str      = wp_date( 'M j, Y - g:i A' );

	if ( empty( $subject ) ) {
		$subject = sprintf( esc_html__( 'Response to your inquiry from %s', 'chatbot' ), $site_name );
	}

	$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
	if ( ! empty( $from ) && is_email( $from ) ) {
		$headers[] = 'From: ' . esc_html( $from_name ) . ' <' . esc_html( $from ) . '>';
		$headers[] = 'Reply-To: ' . esc_html( $from_name ) . ' <' . esc_html( $from ) . '>';
	} else {
		$admin_email = get_option( 'admin_email' );
		$from_email  = get_option( 'qlcd_wp_chatbot_from_email' ) ?: $admin_email;
		$headers[]   = 'From: ' . esc_html( $from_name ) . ' <' . esc_html( $from_email ) . '>';
		$headers[]   = 'Reply-To: ' . esc_html( $from_name ) . ' <' . esc_html( $from_email ) . '>';
	}

	$email_body = '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . esc_html( $subject ) . '</title>
<style>
:root {
	--woobot-primary-color: ' . esc_attr( $primary_color ) . ';
}
.woobot-primary-btn {
	background-color: ' . esc_attr( $primary_color ) . ' !important;
	background-color: var(--woobot-primary-color) !important;
	border: 1px solid ' . esc_attr( $primary_color ) . ' !important;
	border: 1px solid var(--woobot-primary-color) !important;
	color: #ffffff !important;
}
.woobot-primary-tab {
	background-color: ' . esc_attr( $primary_color ) . ' !important;
	background-color: var(--woobot-primary-color) !important;
	border: 1px solid ' . esc_attr( $primary_color ) . ' !important;
	border: 1px solid var(--woobot-primary-color) !important;
	color: #ffffff !important;
}
</style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6fb; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #334155;">
	<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f6fb; padding: 30px 10px;">
		<tr>
			<td align="center">
				<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 640px; background-color: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 6px 24px rgba(91, 78, 150, 0.08); border: 1px solid #e2e8f0;">
					<!-- Header Banner -->
					<tr>
						<td class="woobot-primary-tab" style="background: ' . esc_attr( $primary_color ) . '; background: linear-gradient(135deg, ' . esc_attr( $primary_color ) . ' 0%, #463a7a 100%); padding: 32px 30px; text-align: left; color: #ffffff;">
							<div style="font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.85); margin-bottom: 6px;">' . esc_html( $site_name ) . '</div>
							<h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #ffffff; line-height: 1.3;">' . esc_html__( 'Response to Your Inquiry', 'chatbot' ) . '</h1>
							<p style="margin: 6px 0 16px 0; font-size: 14px; color: rgba(255,255,255,0.9); line-height: 1.4;">' . esc_html__( 'Our support team has replied to your chat conversation.', 'chatbot' ) . '</p>
							<div>
								<span style="display: inline-block; background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3); color: #ffffff; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-right: 6px; margin-top: 4px;">' . esc_html__( 'Support Reply', 'chatbot' ) . '</span>
								<span style="display: inline-block; background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3); color: #ffffff; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 4px;">' . esc_html( $date_str ) . '</span>
							</div>
						</td>
					</tr>
					<!-- Content -->
					<tr>
						<td style="padding: 30px 32px; background-color: #ffffff;">
							<!-- Intro -->
							<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 1.6; color: #334155;">
								' . esc_html__( 'Hello,', 'chatbot' ) . '<br>' . esc_html__( 'Thank you for reaching out to us. Here is our response regarding your inquiry:', 'chatbot' ) . '
							</p>

							<!-- Message Body Card -->
							<div style="background-color: #faf9fd; border-left: 4px solid ' . esc_attr( $primary_color ) . '; border-radius: 0 10px 10px 0; padding: 20px 22px; font-size: 15px; line-height: 1.65; color: #1e293b; margin: 20px 0 24px 0; word-break: break-word;">
								' . nl2br( $message ) . '
							</div>

							<!-- Follow up note -->
							<p style="margin: 20px 0 24px 0; font-size: 14px; line-height: 1.6; color: #64748b;">
								' . esc_html__( 'If you have any further questions or need additional assistance, please feel free to reply directly to this email.', 'chatbot' ) . '
							</p>

							<!-- CTA Button -->
							<div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center;">
								<a href="' . esc_url( $site_url ) . '" class="woobot-primary-btn" style="background-color: ' . esc_attr( $primary_color ) . '; background-color: var(--woobot-primary-color); border: 1px solid ' . esc_attr( $primary_color ) . '; border: 1px solid var(--woobot-primary-color); color: #ffffff; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; display: inline-block; box-shadow: 0 4px 12px rgba(91, 78, 150, 0.25);">
									' . esc_html__( 'Visit Our Website', 'chatbot' ) . ' &rarr;
								</a>
							</div>
						</td>
					</tr>
					<!-- Footer -->
					<tr>
						<td style="background-color: #faf9fd; padding: 20px 30px; border-top: 1px solid #e4e0f2; text-align: center; color: #64748b; font-size: 12.5px; line-height: 1.5;">
							<p style="margin: 0 0 4px 0;">' . sprintf( esc_html__( 'Sent by the customer support team at %s.', 'chatbot' ), '<strong>' . esc_html( $site_name ) . '</strong>' ) . '</p>
							<p style="margin: 0; color: #94a3b8; font-size: 11.5px;">&copy; ' . gmdate( 'Y' ) . ' ' . esc_html( $site_name ) . ' &bull; <a href="' . esc_url( $site_url ) . '" style="color: ' . esc_attr( $primary_color ) . '; text-decoration: none;">' . esc_html( wp_parse_url( $site_url, PHP_URL_HOST ) ) . '</a></p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';

	$sent = wp_mail( $to, $subject, $email_body, $headers );

	if ( $sent ) {
		wp_send_json( array( 'success' => true, 'msg' => esc_html__( 'Email replied successfully', 'chatbot' ) ) );
	} else {
		wp_send_json( array( 'success' => false, 'msg' => esc_html__( 'Failed to send email', 'chatbot' ) ) );
	}
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

		$api_response = wp_remote_post(
			'https://api.openai.com/v1/responses',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $post_fields ),
				'timeout' => 60,
			)
		);
		$result = is_wp_error( $api_response ) ? '' : wp_remote_retrieve_body( $api_response );

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
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized', 'chatbot' ) );
	}

	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wpbot_conversations_csv' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'chatbot' ) );
	}

	global $wpdb;
	$tableuser         = $wpdb->prefix . 'wpbot_user'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$tableconversation = $wpdb->prefix . 'wpbot_conversation'; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$userid            = sanitize_text_field( $_GET['user_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$userinfo = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tableuser WHERE 1 AND id = %d", $userid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$result   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tableconversation WHERE 1 AND user_id = %d", $userid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$data     = array();

	if ( ! empty( $result ) ) {
		$data[]   = array( 'User Name', $userinfo->name );
		$data[]   = array( 'User Email', $userinfo->email );
		$data[]   = array( 'Session ID', $userinfo->session_id );
		$data[]   = array( 'Date', gmdate( 'M,d,Y h:i:s A', strtotime( $userinfo->date ) ) );
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
	qcld_wpbot_chatsession_download_send_headers( $userinfo->name . '_wpbot_chatsession_' . gmdate( 'Y-m-d' ) . '.csv' );
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
				'Date'       => gmdate( 'M,d,Y h:i:s A', strtotime( $user->date ) ),
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
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fputs, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output memory stream for CSV export.
		$df = fopen( 'php://output', 'w' );
		fputs( $df, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // UTF-8 BOM
		foreach ( $array as $data ) {
			fputcsv( $df, array_keys( $data ), ',', '"', '\\' );
			break;
		}
		foreach ( $array as $row ) {
			fputcsv( $df, qcld_wpbot_csv_safe_row( $row ), ',', '"', '\\' ); // Security: CSV formula injection.
		}
		fclose( $df );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fputs, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
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
		if ( ! $user->exists() ) {
			return '<p>No user logged in.</p>';
		}
		// Security: conversation.user_id holds the session row id (wpbot_user.id), not the WordPress user id.
		// Joining on u.user_id showed other people's transcripts.
		$result             = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $tableuser AS u LEFT JOIN $conversatios_table AS c ON u.id = c.user_id WHERE u.user_id = %d AND u.user_id > 0", $user->ID ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
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
			'display'  => esc_attr__( 'Session min', 'chatbot' ),
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

		$api_response = wp_remote_post(
			'https://api.openai.com/v1/responses',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $post_fields ),
				'timeout' => 60,
			)
		);
		$result = is_wp_error( $api_response ) ? '' : wp_remote_retrieve_body( $api_response );

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
