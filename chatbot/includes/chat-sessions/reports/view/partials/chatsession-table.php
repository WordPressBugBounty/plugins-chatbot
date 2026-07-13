<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="my-4 mx-4">
	<input class="form-check-input" type="checkbox" <?php echo ( get_option( 'session_email_notification_update' ) === 'checked' ) ? 'checked' : ''; ?>  role="switch" value="" id="is_enabled_session_email_notice">
	<label class="form-check-label" for="is_enabled_session_email_notice">
	<?php esc_html_e( 'Enable Email Notification for New Chat Sessions', 'chatbot' ); ?>
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
				<th class="text-left">
					<?php echo esc_html__( 'User Interaction Count', 'chatbot' ); ?>
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
					<a class="#" data-id="<?php echo esc_attr($row->id); ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( date( 'M,d,Y h:i:s A', strtotime( $row->date ) ) ); ?></a>
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
					<a href="<?php echo esc_url( $url ); ?>" class="btn btn-info"><?php echo esc_html( 'View Chat' ); ?></a>

					<a href="<?php echo esc_url( $delurl ); ?>" class="btn btn-danger" onclick="return confirm('Are you sure?')"><?php echo esc_html( 'Delete' ); ?></a>
					<span class="btn btn-secondary forward_session" data-id="<?php echo esc_attr($row->session_id); ?>"><?php echo esc_html( 'Forward Session to Email' ); ?></span>
					<span class="btn btn-info show_details_click" data-id="<?php echo esc_attr( $row->id ); ?>"><?php echo esc_html( 'View Chat Here' ); ?></span>
					<?php if ( $row->email != '' ) : ?>
						<a href="#" data-email="<?php echo esc_html( $row->email ); ?>" class="btn btn-secondary"><?php echo esc_html( 'Send Email' ); ?></a>
					<?php endif; ?>
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
        display: none; /* Hidden by default */
        position: fixed; /* Stay in place */
        z-index: 1; /* Sit on top */
        left: 0;
        top: 0;
        width: 100%; /* Full width */
        height: 100%; /* Full height */
        overflow: auto; /* Enable scroll if needed */
        background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
    }

    .modal-content {
        background-color: #fefefe;
        margin: 15% auto; /* 15% from the top and centered */
        padding: 20px;
        border: 1px solid #888;
        width: 80%; /* Could be more or less, depending on screen size */
    }

    .close-details_modal_close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
    }

    .close-details_modal_close:hover,
    .close-details_modal_close:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }
	#session_foward_modal .modal-content {
		background-color: #fefefe;
		margin: 15% auto;
		padding: 20px;
		border: 1px solid #888;
		width: 100%;
		height: 100%;
		margin: 0 auto;
		top: 50%;
		bottom: 50%;
		max-height: 245px;
		position: absolute;
		left: 0;
		right: 0;
		max-width: 480px;
		border-radius: 6px;
	}

	#session_foward_modal .forward_session_close {
		position: absolute;
		top: 0;
		right: 6px;
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
		<p class="details_modal_body">
			<div class="details_session_close">&times;</div>
			<div class="loader-mask">
				<div class="loader">
					<div></div>
					<div></div>
				</div>
			</div>
		</p>
	</div>
</div>
