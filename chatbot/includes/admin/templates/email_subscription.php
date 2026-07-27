<?php
/**
 * Email Subscription Template
 *
 * @package Botmaster
 * @since 14.8.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

		wp_register_style( 'qlcd-wp-chatbot-admin-style-email', QCLD_wpCHATBOT_PLUGIN_URL . '/css/email_subscription.css', '', QCLD_wpCHATBOT_VERSION, 'screen' );
		wp_enqueue_style( 'qlcd-wp-chatbot-admin-style-email' );

		wp_register_script( 'qcld-wp-chatbot-email-subscription-js', QCLD_wpCHATBOT_PLUGIN_URL . '/js/email_subscription.js', array( 'jquery' ), true );
			wp_enqueue_script( 'qcld-wp-chatbot-email-subscription-js' );

		global $wpdb;
if ( ! function_exists( 'wp_get_current_user' ) ) {
	include ABSPATH . 'wp-includes/pluggable.php';
}

		$table         = $wpdb->prefix . 'wpbot_subscription';

		if ( isset( $_POST['wpbot_email_subscription_remove'] ) && $_POST['wpbot_email_subscription_remove'] == '1' ) {
			if ( current_user_can( 'manage_options' ) && isset( $_POST['emails'] ) && is_array( $_POST['emails'] ) ) {
				foreach ( $_POST['emails'] as $email_id ) {
					$wpdb->delete( $table, array( 'id' => intval( $email_id ) ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				}
				echo '<script>window.location.href="' . esc_url_raw( admin_url( 'admin.php?page=email-subscription&msg=success' ) ) . '";</script>';
				exit;
			}
		}

		$current_user  = wp_get_current_user();
		$url           = admin_url( 'edit.php?post_type=sld&page=qcsld_click_list' );
		$customPagHTML = '';
		// Main Report Area
		$sql1 = $wpdb->prepare( 'SELECT count(*) FROM %i where 1', $table );

		$total          = $wpdb->get_var( $sql1 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$items_per_page = 50;

		$page   = isset( $_GET['cpage'] ) ? abs( (int) $_GET['cpage'] ) : 1;// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$offset = ( $page * $items_per_page ) - $items_per_page;

		$sql  = $wpdb->prepare( 'SELECT * FROM %i where 1 order by id desc LIMIT %d, %d', $table, $offset, $items_per_page );

		$rows      = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$totalPage = ceil( $total / $items_per_page );

if ( $totalPage > 1 ) {
	$customPagHTML = '<div><span class="wpbot_pagination">Page ' . esc_html( $page ) . ' of ' . esc_html( $totalPage ) . '</span>' . paginate_links(
		array(
			'base'      => add_query_arg( 'cpage', '%#%' ),
			'format'    => '',
			'prev_text' => esc_html__( '&laquo;' ),
			'next_text' => esc_html__( '&raquo;' ),
			'total'     => esc_html( $totalPage ),
			'current'   => esc_html( $page ),
		)
	) . '</div>';
}
		$mainurl = admin_url( 'admin.php?page=email-subscription' );

?>	
		<div class="qchero_sliders_list_wrapper">
			<div class="sld_menu_title">
				<h2><?php echo esc_html__( 'User Data', 'wpchatbot' ); ?></h2>

			</div>
			
			<div class="sld_menu_title sld_menu_title_align"><?php echo wp_kses_post( $customPagHTML ); ?><span style="float: right;"><a class="button-primary" href="<?php echo esc_url( admin_url( 'admin-post.php?action=wpbprint.csv' ) ); ?>">Export All Contacts</a> Total <?php echo esc_html( $total ); ?></span> </div>
			
			<?php
			if ( isset( $_GET['msg'] ) && $_GET['msg'] == 'success' ) {// phpcs:ignore WordPress.Security.NonceVerification.Missing
				echo '<div class="notice notice-success"><p>Record has beed Deleted Successfully!</p></div>';
			}
			?>
			
			<form id="wpcs_form_sessions" action="<?php echo esc_url( $mainurl ); ?>" method="POST" style="width:100%">
			<input type="hidden" name="wpbot_email_subscription_remove" />
			
			<button class="button-primary" id="wpbot_submit_email_form">Delete</button>

			<div class="qchero_slider_table_area">
				<div class="sld_payment_table">
					<div class="sld_payment_row header">
						
						<div class="sld_payment_cell">
							<input type="checkbox" id="wpbot_checked_all" />
						</div>

						<div class="sld_payment_cell">
							<?php echo esc_html__( 'Date', 'wpchatbot' ); ?>
						</div>
						<div class="sld_payment_cell">
							<?php echo esc_html__( 'Name', 'wpchatbot' ); ?>
						</div>
						<div class="sld_payment_cell">
							<?php echo esc_html__( 'Email', 'wpchatbot' ); ?>
						</div>
						<div class="sld_payment_cell">
							<?php echo esc_html__( 'Phone', 'wpchatbot' ); ?>
						</div>
						
					</div>

			<?php
			foreach ( $rows as $row ) {
				?>
				<div class="sld_payment_row">

					<div class="sld_payment_cell">
						
						<input type="checkbox" name="emails[]" class="wpbot_email_checkbox" value="<?php echo absint( $row->id ); ?>" />
					</div>
					
					<div class="sld_payment_cell">
						<div class="sld_responsive_head"><?php echo esc_html__( 'Date', 'wpchatbot' ); ?></div>
						<?php echo esc_html( date( 'm/d/Y', strtotime( $row->date ) ) ); ?>
					</div>
					<div class="sld_payment_cell">
						<div class="sld_responsive_head"><?php echo esc_html__( 'Name', 'wpchatbot' ); ?></div>
						<?php echo esc_html( $row->name ); ?>
					</div>
					<div class="sld_payment_cell">
						<div class="sld_responsive_head"><?php echo esc_html__( 'Email', 'wpchatbot' ); ?></div>
						<?php
							echo esc_html( $row->email );
						?>
					</div>
					<div class="sld_payment_cell">
						<div class="sld_responsive_head"><?php echo esc_html__( 'Phone', 'wpchatbot' ); ?></div>
						<?php
							echo esc_html( $row->phone );

						?>
					</div>
					
				</div>
				<?php
			}
			?>

			</div>
			</form>
		</div>
		</div>