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
		$table_sql      = esc_sql( $table );

		if ( isset( $_POST['wpbot_email_subscription_remove'] ) && '1' === $_POST['wpbot_email_subscription_remove'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			check_admin_referer( 'wpbot_email_subscription_delete', 'wpbot_email_subscription_nonce' );
			if ( current_user_can( 'manage_options' ) && isset( $_POST['emails'] ) && is_array( $_POST['emails'] ) ) {
				$email_ids = array_map( 'absint', wp_unslash( $_POST['emails'] ) );
				foreach ( $email_ids as $email_id ) {
					$wpdb->delete( $table, array( 'id' => $email_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				}
				echo '<script>window.location.href="' . esc_url_raw( admin_url( 'admin.php?page=email-subscription&msg=success' ) ) . '";</script>';
				exit;
			}
		}

		$current_user  = wp_get_current_user();
		$url           = admin_url( 'edit.php?post_type=sld&page=qcsld_click_list' );
		$customPagHTML = '';
		// Main Report Area — cache total count (invalidated when subscriptions change).
		$total_cache_key = 'wpbot_email_sub_total';
		$total           = wp_cache_get( $total_cache_key, 'wpbot' );
		if ( false === $total ) {
			$total = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				'SELECT COUNT(*) FROM `' . $table_sql . '`' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);
			wp_cache_set( $total_cache_key, $total, 'wpbot', 300 );
		}
		$items_per_page = 50;

		$page   = isset( $_GET['cpage'] ) ? abs( (int) $_GET['cpage'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$offset = ( $page * $items_per_page ) - $items_per_page;

		// Cache paginated rows per page number.
		$rows_cache_key = 'wpbot_email_sub_rows_p' . $page;
		$rows           = wp_cache_get( $rows_cache_key, 'wpbot' );
		if ( false === $rows ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					'SELECT * FROM `' . $table_sql . '` ORDER BY id DESC LIMIT %d, %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$offset,
					$items_per_page
				)
			);
			wp_cache_set( $rows_cache_key, $rows, 'wpbot', 300 );
		}

		$totalPage = ceil( $total / $items_per_page );

if ( $totalPage > 1 ) {
	$customPagHTML = '<div><span class="wpbot_pagination">Page ' . esc_html( $page ) . ' of ' . esc_html( $totalPage ) . '</span>' . paginate_links(
		array(
			'base'      => add_query_arg( 'cpage', '%#%' ),
			'format'    => '',
			'prev_text' => esc_html__( '&laquo;', 'chatbot' ),
			'next_text' => esc_html__( '&raquo;', 'chatbot' ),
			'total'     => esc_html( $totalPage ),
			'current'   => esc_html( $page ),
		)
	) . '</div>';
}
		$mainurl = admin_url( 'admin.php?page=email-subscription' );

?>	
		<div class="qchero_sliders_list_wrapper wpbot-userdata-page">
			<div class="sld_menu_title">
				<h2><?php echo esc_html__( 'User Data', 'chatbot' ); ?></h2>
			</div>

			<?php if ( $customPagHTML != '' ) : ?>
			<div class="sld_menu_title sld_menu_title_align wpbot-userdata-toolbar">
				<div class="qcld-session-pagination"><?php echo wp_kses_post( $customPagHTML ); ?></div>
			</div>
			<?php endif; ?>

			<?php
			if ( isset( $_GET['msg'] ) && $_GET['msg'] == 'success' ) {// phpcs:ignore WordPress.Security.NonceVerification.Missing
				echo '<div class="notice notice-success"><p>Record has beed Deleted Successfully!</p></div>';
			}
			?>

			<form id="wpcs_form_sessions" class="wpbot-userdata-form" action="<?php echo esc_url( $mainurl ); ?>" method="POST">
			<?php wp_nonce_field( 'wpbot_email_subscription_delete', 'wpbot_email_subscription_nonce' ); ?>
			<input type="hidden" name="wpbot_email_subscription_remove" value="1" />

			<div class="wpbot-userdata-actions">
				<button type="button" class="button-primary" id="wpbot_submit_email_form"><?php echo esc_html__( 'Delete', 'chatbot' ); ?></button>
				<a class="button-primary wpbot-userdata-export" href="<?php echo esc_url( admin_url( 'admin-post.php?action=wpbprint.csv' ) ); ?>"><?php echo esc_html__( 'Export All Contacts', 'chatbot' ); ?></a>
				<span class="wpbot-userdata-total"><?php echo esc_html__( 'Total', 'chatbot' ); ?> <strong><?php echo esc_html( $total ); ?></strong></span>
			</div>

			<div class="qchero_slider_table_area">
				<div class="sld_payment_table">
					<div class="sld_payment_row header">

						<div class="sld_payment_cell">
							<input type="checkbox" id="wpbot_checked_all" />
						</div>

						<div class="sld_payment_cell">
							<?php echo esc_html__( 'Date', 'chatbot' ); ?>
						</div>
						<div class="sld_payment_cell">
							<?php echo esc_html__( 'Name', 'chatbot' ); ?>
						</div>
						<div class="sld_payment_cell">
							<?php echo esc_html__( 'Email', 'chatbot' ); ?>
						</div>
						<div class="sld_payment_cell">
							<?php echo esc_html__( 'Phone', 'chatbot' ); ?>
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
						<div class="sld_responsive_head"><?php echo esc_html__( 'Date', 'chatbot' ); ?></div>
						<?php echo esc_html( gmdate( 'm/d/Y', strtotime( $row->date ) ) ); ?>
					</div>
					<div class="sld_payment_cell">
						<div class="sld_responsive_head"><?php echo esc_html__( 'Name', 'chatbot' ); ?></div>
						<?php echo esc_html( $row->name ); ?>
					</div>
					<div class="sld_payment_cell">
						<div class="sld_responsive_head"><?php echo esc_html__( 'Email', 'chatbot' ); ?></div>
						<?php
							echo esc_html( $row->email );
						?>
					</div>
					<div class="sld_payment_cell">
						<div class="sld_responsive_head"><?php echo esc_html__( 'Phone', 'chatbot' ); ?></div>
						<?php
							echo esc_html( $row->phone );

						?>
					</div>

				</div>
				<?php
			}
			?>

			</div>
			</div>
			</form>
		</div>
