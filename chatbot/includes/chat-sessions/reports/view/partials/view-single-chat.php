<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>

<?php
	global $wpdb;

	$tableuser         = $wpdb->prefix . 'wpbot_user';
	$tableconversation = $wpdb->prefix . 'wpbot_conversation';
	$table_user_sql    = '`' . esc_sql( $tableuser ) . '`';
	$table_conv_sql    = '`' . esc_sql( $tableconversation ) . '`';
	$userid            = isset( $_GET['userid'] ) ? absint( wp_unslash( $_GET['userid'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$userinfo = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_user_sql} WHERE id = %d", $userid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

	$delurl = wp_nonce_url( admin_url( 'admin.php?page=wbcs-botsessions-page&userid=' . $userinfo->id . '&act=delete' ), 'wpcs_delete_session_' . $userinfo->id );

	$export = wp_nonce_url( admin_url( 'admin-post.php?action=wpbot_conversations.csv&user_id=' . $userid ), 'wpbot_conversations_csv' );

	wp_register_style( 'qcld-wp-chatbot-history-style', QCLD_wpCHATBOT_HISTORY_PLUGIN_URL . '/css/history-style.css', '', QCLD_wpCHATBOT_VERSION, 'screen' );
	wp_enqueue_style( 'qcld-wp-chatbot-history-style' );
?>

<div class="wpbot-session-details-page">
	<div class="sld_menu_title qcld_session_history_result wpbot-session-meta-card">
		<div class="wpbot-session-meta-card__header">
			<h2><?php echo esc_html__( 'Session Details', 'chatbot' ); ?></h2>
			<div class="wpbot-session-meta-card__actions">
				<a href="<?php echo esc_url( $delurl ); ?>" class="btn btn-danger" onclick="return confirm('Are you sure?')">
					<i class="bi bi-trash me-1"></i> <?php echo esc_html__( 'Delete', 'chatbot' ); ?>
				</a>
				<a href="<?php echo esc_url( $export ); ?>" class="btn btn-primary">
					<i class="bi bi-filetype-csv me-1"></i> <?php echo esc_html__( 'Export', 'chatbot' ); ?>
				</a>
			</div>
		</div>

		<div class="wpbot-session-meta-grid">
			<div class="wpbot-session-meta-item">
				<span class="wpbot-session-meta-item__label"><?php echo esc_html__( 'Session ID', 'chatbot' ); ?></span>
				<span class="wpbot-session-meta-item__value"><?php echo ( $userinfo->session_id != '' ) ? esc_html( $userinfo->session_id ) : '---'; ?></span>
			</div>
			<div class="wpbot-session-meta-item">
				<span class="wpbot-session-meta-item__label"><?php echo esc_html__( 'User Name', 'chatbot' ); ?></span>
				<span class="wpbot-session-meta-item__value"><?php echo esc_html( $userinfo->name ? $userinfo->name : '---' ); ?></span>
			</div>
			<div class="wpbot-session-meta-item">
				<span class="wpbot-session-meta-item__label"><?php echo esc_html__( 'User Email', 'chatbot' ); ?></span>
				<span class="wpbot-session-meta-item__value"><?php echo ( $userinfo->email != '' ) ? esc_html( $userinfo->email ) : '---'; ?></span>
			</div>
			<div class="wpbot-session-meta-item">
				<span class="wpbot-session-meta-item__label"><?php echo esc_html__( 'Phone Number', 'chatbot' ); ?></span>
				<span class="wpbot-session-meta-item__value"><?php echo ( $userinfo->phone != '' ) ? esc_html( $userinfo->phone ) : '---'; ?></span>
			</div>
			<div class="wpbot-session-meta-item">
				<span class="wpbot-session-meta-item__label"><?php echo esc_html__( 'Date and Time', 'chatbot' ); ?></span>
				<span class="wpbot-session-meta-item__value"><?php echo esc_html( gmdate( 'M d, Y h:i:s A', strtotime( $userinfo->date ) ) ); ?></span>
			</div>
		</div>
	</div>

	<?php
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_conv_sql} WHERE user_id = %d", $userid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

	if ( ! empty( $result ) ) :

		$qcld_wb_chatbot_theme = get_option( 'qcld_wb_chatbot_theme' );

		if ( file_exists( QCLD_wpCHATBOT_PLUGIN_DIR_PATH . '/templates/' . $qcld_wb_chatbot_theme . '/style.css' ) ) {
			wp_register_style( 'qcld-wp-chatbot-style', QCLD_wpCHATBOT_PLUGIN_URL . '/templates/' . $qcld_wb_chatbot_theme . '/style.css', '', QCLD_wpCHATBOT_VERSION, 'screen' );
			wp_enqueue_style( 'qcld-wp-chatbot-style' );
		}

		wp_enqueue_style( 'qcld-wp-chatbot-jquery-ui' );
		wp_register_style( 'qcld-wp-chatbot-jquery-ui', QCLD_wpCHATBOT_HISTORY_PLUGIN_URL . '/css/jqueryui.css', '', '', 'screen' );
		wp_register_style( 'qcld-wp-chatbot-common-style', QCLD_wpCHATBOT_PLUGIN_URL . '/css/common-style.css', '', QCLD_wpCHATBOT_VERSION, 'screen' );
		wp_enqueue_style( 'qcld-wp-chatbot-common-style' );
		?>

	<div class="single-chat-container-wrapper wpbot-session-chat-card">
		<div class="wpbot-session-chat-card__header">
			<h3><?php echo esc_html__( 'Chat Messages', 'chatbot' ); ?></h3>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbcs-botsessions-page' ) ); ?>" class="btn btn-primary wpbot-session-back-btn">
				<i class="bi bi-arrow-left me-1"></i> <?php echo esc_html__( 'Conversation List', 'chatbot' ); ?>
			</a>
		</div>
		<div class="wp-chatbot-messages-wrapper">
			<?php
				$allowed_html = array_merge(
					wp_kses_allowed_html( 'post' ),
					array(
						'div'  => array(
							'class'   => true,
							'id'      => true,
							'style'   => true,
							'data-*'  => true,
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
				echo wp_kses( htmlspecialchars_decode( $result->conversation ), $allowed_html );
			?>
		</div>
	</div>

	<button type="button" class="wpbot-reply-fab" id="wpbot_reply_fab" aria-expanded="false" aria-controls="wpbot_reply_panel">
		<i class="bi bi-envelope" aria-hidden="true"></i>
		<span><?php echo esc_html__( 'Reply via Email', 'chatbot' ); ?></span>
	</button>

	<div class="wpbot-reply-panel" id="wpbot_reply_panel" hidden>
		<div class="wpbot-reply-panel__header">
			<h4><?php echo esc_html__( 'Reply via Email', 'chatbot' ); ?></h4>
			<button type="button" class="wpbot-reply-panel__close" id="wpbot_reply_panel_close" aria-label="<?php esc_attr_e( 'Close', 'chatbot' ); ?>">&times;</button>
		</div>
		<div class="wpbot-reply-panel__body">
			<?php if ( ! empty( $userinfo->email ) ) : ?>
			<div class="email-reply-container">
				<p class="wpbot-reply-panel__to"><?php echo esc_html__( 'To:', 'chatbot' ); ?> <strong><?php echo esc_html( $userinfo->email ); ?></strong></p>
				<div class="form-group mb-3">
					<label for="reply_subject" class="form-label"><?php echo esc_html__( 'Subject', 'chatbot' ); ?></label>
					<input type="text" id="reply_subject" class="form-control" placeholder="<?php esc_attr_e( 'Reply to your chat session', 'chatbot' ); ?>">
				</div>
				<div class="form-group mb-3">
					<label for="reply_message" class="form-label"><?php echo esc_html__( 'Message', 'chatbot' ); ?></label>
					<textarea id="reply_message" class="form-control" rows="4" placeholder="<?php esc_attr_e( 'Type your reply here...', 'chatbot' ); ?>"></textarea>
				</div>
				<button type="button" class="btn btn-primary" id="btn_send_reply_email" data-email="<?php echo esc_attr( $userinfo->email ); ?>"><?php echo esc_html__( 'Send Reply', 'chatbot' ); ?></button>
			</div>
			<?php else : ?>
			<p class="wpbot-reply-panel__warning"><?php echo esc_html__( 'No user email available for this session.', 'chatbot' ); ?></p>
			<?php endif; ?>
			<div class="forward-session-wrapper">
				<input type="hidden" id="details_session_id" value="<?php echo esc_attr( $userinfo->session_id ); ?>">
				<input type="email" id="details_session_email" class="form-control" placeholder="<?php esc_attr_e( 'Enter email to forward session details', 'chatbot' ); ?>">
				<span class="btn btn-secondary forward_session"><?php echo esc_html__( 'Forward Session', 'chatbot' ); ?></span>
			</div>
		</div>
	</div>

	<script>
	(function () {
		var fab = document.getElementById('wpbot_reply_fab');
		var panel = document.getElementById('wpbot_reply_panel');
		var closeBtn = document.getElementById('wpbot_reply_panel_close');
		if (!fab || !panel) {
			return;
		}
		function openPanel() {
			panel.hidden = false;
			panel.classList.add('is-open');
			fab.setAttribute('aria-expanded', 'true');
			fab.classList.add('is-active');
		}
		function closePanel() {
			panel.classList.remove('is-open');
			fab.setAttribute('aria-expanded', 'false');
			fab.classList.remove('is-active');
			window.setTimeout(function () {
				if (!panel.classList.contains('is-open')) {
					panel.hidden = true;
				}
			}, 220);
		}
		fab.addEventListener('click', function () {
			if (panel.classList.contains('is-open')) {
				closePanel();
			} else {
				openPanel();
			}
		});
		if (closeBtn) {
			closeBtn.addEventListener('click', closePanel);
		}
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && panel.classList.contains('is-open')) {
				closePanel();
			}
		});
	})();
	</script>
	<?php endif; ?>
</div>
