<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="qcld-session-email-notice">
	<label class="qcld-session-email-notice__label" for="is_enabled_session_email_notice">
		<input class="form-check-input qcld-session-email-notice__input" type="checkbox" <?php echo ( get_option( 'session_email_notification_update' ) === 'checked' ) ? 'checked' : ''; ?> role="switch" value="" id="is_enabled_session_email_notice">
		<span class="qcld-session-email-notice__switch" aria-hidden="true"></span>
		<span class="qcld-session-email-notice__text"><?php esc_html_e( 'Enable Email Notification for New Chat Sessions', 'chatbot' ); ?></span>
	</label>
</div>
<div class="chatsession_table_area">
	<div class="container-fluid my-2">
		<div class="row">
			<div class="col-md-6 text-left">
				<button class="btn btn-primary" id="wpbot_submit_session_delete" name="wpbot_session_delete"><?php echo esc_html( 'Delete' ); ?></button>

				<a href="<?php echo esc_url( $deleteurl ); ?>" class="btn btn-primary" ><?php echo esc_html( 'Delete All Sessions' ); ?></a>

				<button class="btn btn-primary" id="wpbot_submit_session_export" name="wpbot_session_export"><?php echo esc_html( 'Export' ); ?></button>

				<button class="btn btn-primary" id="wpbot_submit_session_export_all" name="wpbot_session_export_all"><?php echo esc_html( 'Export All' ); ?></button>
			</div>
			<div class="col-md-6">
				<div class="text-end">
					Filter: 
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbcs-botsessions-page&FilterDate=LastWeek' ) ); ?>" class="btn btn-success"><?php echo esc_html( 'Last Week' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbcs-botsessions-page&FilterDate=LastMonth' ) ); ?>" class="btn btn-success"><?php echo esc_html( 'Last Month' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wbcs-botsessions-page&FilterDate=Last3Months ' ) ); ?>" class="btn btn-success"><?php echo esc_html( 'Last 3 Months' ); ?></a>
				</div>
			</div>
		</div>
	</div>

	<table class="table table-striped align-middle" id="chatsession-table">
		<thead>
			<tr class="table-primary">
				<th  class="text-center" data-dt-order="disable">
					<input type="checkbox" id="wpbot_checked_all" />
				</th>
				<th class="text-left">
					<?php echo esc_html__( 'Date', 'chatbot' ); ?>
				</th>
				<th class="text-left" width="100px">
					<?php echo esc_html__( 'Interaction Count', 'chatbot' ); ?>
				</th>
				<th class="text-left">
					<?php echo esc_html__( 'Session ID', 'chatbot' ); ?>
				</th>
				<th class="text-left">
					<?php echo esc_html__( 'Name', 'chatbot' ); ?>
				</th>
				<th class="text-left">
					<?php echo esc_html__( 'Email', 'chatbot' ); ?>
				</th>
				<th class="text-left">
					<?php echo esc_html__( 'Phone', 'chatbot' ); ?>
				</th>
				<th class="text-left" data-dt-order="disable">
					<?php echo esc_html__( 'Action', 'chatbot' ); ?>
				</th>
			</tr>
		</thead>
		<tbody>

			<!-- Table Body -->
			<?php
			foreach ( $result as $row ) {
				$url    = admin_url( 'admin.php?page=wbcs-botsessions-page&userid=' . $row->id );
				$delurl = wp_nonce_url( admin_url( 'admin.php?page=wbcs-botsessions-page&userid=' . $row->id . '&act=delete' ), 'wpcs_delete_session_' . $row->id );
				?>
			<tr>
				<td class="text-center">
					<input type="checkbox" name="sessions[]" class="wpbot_sessions_checkbox" value="<?php echo esc_html( $row->id ); ?>" />
				</td>
				<td class="text-left">
					<a  class="" data-id="<?php echo esc_attr( $row->id ); ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( date( 'M,d,Y h:i:s A', strtotime( $row->date ) ) ); ?></a>
				</td>
				<td class="text-left">
				<?php echo esc_html( $row->interaction ); ?>
				</td>
				<td class="text-left">
				<?php echo esc_html( $row->session_id ); ?>
				</td>
				<td class="text-left">
				<?php echo esc_html( $row->name ); ?>
				</td>
				<td class="text-left">
				<?php
					echo esc_html( $row->email );
				?>
				</td>
				<td class="text-left">
				<?php
					echo esc_html( $row->phone );
				?>
				</td>
				<td class="text-left">
					<a href="<?php echo esc_url( $delurl ); ?>" class="btn btn-danger" onclick="return confirm('Are you sure?')"><?php echo esc_html( 'Delete' ); ?></a>
					<span class="btn btn-secondary forward_session" data-id="<?php echo esc_attr($row->session_id); ?>"><?php echo esc_html( 'Forward Session' ); ?></span>
					<span class="btn btn-info show_details_click" data-id="<?php echo esc_attr( $row->id ); ?>"><?php echo esc_html( 'View Chat & Reply' ); ?></span>
				
				</td>
			</tr>
				<?php

			} //End of ForEach

			?>
			<!-- Table Body Ends Here-->

		</tbody>
	</table>

</div>


<script>
	document.addEventListener('DOMContentLoaded', function() {
		if (typeof DataTable !== 'undefined') {
			new DataTable('#chatsession-table', {
				info: false,
			});
		}
	});
</script>
<style>
	.session_modal {
		display: none;
		position: fixed;
		z-index: 100000;
		left: 0;
		top: 0;
		width: 100%;
		height: 100%;
		overflow: auto;
		background-color: rgba(0, 0, 0, 0.45);
	}

	#session_foward_modal .modal-content {
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		margin: 0;
		padding: 28px 24px 24px;
		width: calc(100% - 32px);
		max-width: 480px;
		height: auto;
		max-height: none;
		background-color: #fff;
		border: 1px solid #e4e0f2;
		border-radius: 12px;
		box-shadow: 0 12px 40px rgba(91, 78, 150, 0.2);
		box-sizing: border-box;
	}

	#session_foward_modal .forward_session_close {
		position: absolute;
		top: 10px;
		right: 10px;
		background: #564a8e;
		color: #fff;
		width: 28px;
		height: 28px;
		text-align: center;
		line-height: 26px;
		border-radius: 50%;
		cursor: pointer;
		font-size: 18px;
	}

	#session_foward_modal .forward_session_close:hover {
		background: #463a7a;
	}
</style>
<div id="session_foward_modal" class="session_modal">
 	<div class="modal-content">
		<p class="details_modal_body">
			<div class="forward_session_close">&times;</div>
			<input type="hidden" id="details_session_id">
			<input type="email" id="details_session_email" class="form-control" placeholder="<?php esc_attr_e( 'Enter email to forward session details', 'chatbot' ); ?>">
			<input type="text" id="details_session_subject" class="form-control mt-2" placeholder="<?php esc_attr_e( 'Enter email subject', 'chatbot' ); ?>">
			<a class="btn btn-primary mt-2" id="qcld_details_forward_submit"><?php echo esc_html( 'Forward' ); ?></a>
		</p>
	</div>
</div>
<div id="session_details_modal" class="session_modal">
 	<div class="modal-content">
		<div class="details_session_close">&times;</div>
		<div class="details_modal_body">
			<div class="loader-mask">
				<div class="loader">
					<div></div>
					<div></div>
				</div>
			</div>
		</div>
	</div>
</div>
