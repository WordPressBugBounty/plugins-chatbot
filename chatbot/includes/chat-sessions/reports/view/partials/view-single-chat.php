<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" integrity="sha384-4LISF5TTJX/fLmGSxO53rV4miRxdg84mZsxmO8Rx5jGtp/LbrixFETvWa5a6sESd" crossorigin="anonymous">

<link href="<?php echo QCLD_wpCHATBOT_HISTORY_PLUGIN_URL . '/reports/view/assets/style.css'; ?>" rel="stylesheet">

<style>
	.wp-chatbot-messages-wrapper ul:first-child li{
		border-color: #41464b;
		background-color: #e2e3e5;
		padding: 10px;
		font-weight: bold;
	}

	.wp-chatbot-messages-container{
		padding: 10px;
	}

	.wp-chatbot-msg, wp-chatbot-paragraph{
		text-align: justify !important;
	}

	.single-chat-container-wrapper{
		background-color: #fff;
		border-left: 3px solid #0073aa;
		margin-top: 20px;
		padding: 25px;
	}
	.forward-session-wrapper {
		display: flex;
		align-items: center;
	}

	.forward-session-wrapper span.btn.btn-secondary.forward_session {
		min-width: 220px;
		border-radius: 0 6px 6px 0;
	}

	.forward-session-wrapper input#details_session_email {
		border-radius: 6px 0 0 6px;
		padding: 2px 12px;
	}

	.forward-session-wrapper input#details_session_email:focus {
		outline: none;
		box-shadow: none
	}
	
</style>

<?php

	global $wpdb;

	$userid = sanitize_text_field( $_GET['userid'] );

	$userinfo = $wpdb->get_row( "select * from $tableuser where 1 and id = '" . $userid . "'" );

	$delurl = admin_url( 'admin.php?page=wbcs-botsessions-page&userid=' . $userinfo->id . '&act=delete' );

	$export = admin_url( 'admin-post.php?action=wpbot_conversations.csv&user_id=' . $userid );

?>
		<div class="sld_menu_title qcld_session_history_result" style="text-align: left;">
			<table class="table table-bordered">
				<tbody>
					<tr>
						<th><?php echo esc_html( 'Session ID' ); ?></th>
						<td>
							<?php echo ( $userinfo->session_id != '' ) ? esc_html( $userinfo->session_id ) : '---'; ?>
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html( 'User Name' ); ?></th>
						<td>
							<?php echo esc_html( $userinfo->name ); ?>
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html( 'User Email' ); ?></th>
						<td>
							<?php echo ( $userinfo->email != '' ) ? esc_html( $userinfo->email ) : '---'; ?>
						</td>
					</tr>
					<tr>
						<th><?php echo esc_html( 'Phone Number' ); ?></th>
						<td>
							<?php echo ( $userinfo->phone != '' ) ? esc_html( $userinfo->phone ) : '---'; ?>
						</td>
					</tr>
					<tr>
						<th>
							<?php echo esc_html( 'Date and Time' ); ?>
						</th>
						<td>
							<?php echo date( 'M d, Y h:i:s A', strtotime( $userinfo->date ) ); ?>
						</td>
					</tr>
					<tr>
						<th>Action Buttons</th>
						<td>
							<a href="<?php echo esc_url( $delurl ); ?>" class="btn btn-primary" onclick="return confirm('are you sure?')">
								<i class="bi bi-trash me-1"></i> Delete
							</a>
			
							<a href="<?php echo esc_url( $export ); ?>" class="btn btn-primary">
								<i class="bi bi-filetype-csv me-1"></i> Export
							</a>

							<a href="<?php echo admin_url( 'admin.php?page=wbcs-botsessions-page' ); ?>" class="btn btn-secondary">
								<i class="bi bi-gear-wide-connected me-1"></i> Conversation List
							</a>

						</td>
					</tr>
				</tbody>
			</table>
		</div>

	<?php

		$result = $wpdb->get_row( "select * from $tableconversation where 1 and user_id = '" . $userid . "'" );

	if ( ! empty( $result ) ) :

		$qcld_wb_chatbot_theme = get_option( 'qcld_wb_chatbot_theme' );

		if ( file_exists( QCLD_wpCHATBOT_PLUGIN_DIR_PATH . '/templates/' . $qcld_wb_chatbot_theme . '/style.css' ) ) {
			wp_register_style( 'qcld-wp-chatbot-style', QCLD_wpCHATBOT_PLUGIN_URL . '/templates/' . $qcld_wb_chatbot_theme . '/style.css', '', QCLD_wpCHATBOT_VERSION, 'screen' );
			wp_enqueue_style( 'qcld-wp-chatbot-style' );
		}

		wp_register_style( 'qcld-wp-chatbot-history-style', QCLD_wpCHATBOT_HISTORY_PLUGIN_URL . '/css/history-style.css', '', QCLD_wpCHATBOT_VERSION, 'screen' );
		wp_enqueue_style( 'qcld-wp-chatbot-history-style' );
		wp_enqueue_style( 'qcld-wp-chatbot-jquery-ui' );
		wp_register_style( 'qcld-wp-chatbot-jquery-ui', QCLD_wpCHATBOT_HISTORY_PLUGIN_URL . '/css/jqueryui.css', '', '', 'screen' );
		wp_register_style( 'qcld-wp-chatbot-common-style', QCLD_wpCHATBOT_PLUGIN_URL . '/css/common-style.css', '', QCLD_wpCHATBOT_VERSION, 'screen' );
		wp_enqueue_style( 'qcld-wp-chatbot-common-style' );

		?>
		<div class="single-chat-container-wrapper">
			<div class="container-fluid">
				<div class="row">
					<div class="col-md-10 text-left">
						<h3 class="mb-3">Chat Messages</h3>
						<div class="wp-chatbot-messages-wrapper">
						<?php
							echo htmlspecialchars_decode( $result->conversation );
						?>
						<div class="forward-session-wrapper">
						<input type="hidden" id="details_session_id" value="<?php echo esc_attr( $userinfo->session_id ); ?>">
						<input type="email" id="details_session_email" class="form-control" placeholder="<?php esc_attr_e( 'Enter email to forward session details', 'wpchatbot' ); ?>">
						<span class="btn btn-secondary forward_session"><?php echo esc_html( 'Forward Session to Email' ); ?></span>
						</div>
						</div>
					</div>
				  
				</div>
			</div>
		</div>

		<br>

		<a href="<?php echo admin_url( 'admin.php?page=wbcs-botsessions-page' ); ?>" class="btn btn-primary">
			<i class="bi bi-gear-wide-connected me-1"></i> Conversation List
		</a>
		<?php

		endif;