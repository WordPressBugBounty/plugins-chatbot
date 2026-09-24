<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * QuantumCloud Promo + Support Page
 * Revised On: 18-10-2023
 */

if ( ! defined( 'qcld_wpchatbot_comments_support_path' ) ) {
	define( 'qcld_wpchatbot_comments_support_path', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'qcld_wpchatbot_comments_support_url' ) ) {
	define( 'qcld_wpchatbot_comments_support_url', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'qcld_support_img_url' ) ) {
	define( 'qcld_support_img_url', qcld_wpchatbot_comments_support_url . '/images' );
}

/**
 * Callback function to add the menu
 *
 * @return void
 */
function qcld_wpchatbot_comments_show_promo_page_callback_func() {
	$capability = function_exists( 'qcld_wpchatbot_get_menu_capability' ) ? qcld_wpchatbot_get_menu_capability( 'wpbot_support' ) : 'manage_options';

	/*add_submenu_page(
		'comment-link-remove',
		esc_html__( 'More WordPress Goodies for You!', 'chatbot' ),
		esc_html__( 'Support', 'chatbot' ),
		$capability,
		'qcclr_comment_supports',
		'qcld_wpchatbot_comments_promo_support_page_callback_func'
	);*/

} //show_promo_page_callback_func.

add_action( 'admin_menu', 'qcld_wpchatbot_comments_show_promo_page_callback_func', 110 );



if ( ! function_exists( 'qcld_wpchatbot_comments_include_promo_page_scripts' ) ) {

	/**
	 * Script Main Class function
	 *
	 * @return void
	 */
	function qcld_wpchatbot_comments_include_promo_page_scripts() {

		if ( isset( $_GET['page'] ) && ! empty( $_GET['page'] ) && ( $_GET['page'] == 'wpbot_support_page' ) ) {

			$support_css_ver = defined( 'QCLD_wpCHATBOT_VERSION' ) ? QCLD_wpCHATBOT_VERSION : '1.0.0';
			$support_deps    = array();

			if ( defined( 'QCLD_wpCHATBOT_PLUGIN_URL' ) ) {
				wp_enqueue_style(
					'qlcd-wp-chatbot-admin-style',
					QCLD_wpCHATBOT_PLUGIN_URL . 'css/admin-style.css',
					array(),
					$support_css_ver,
					'screen'
				);
				$support_deps[] = 'qlcd-wp-chatbot-admin-style';
			}

			wp_enqueue_style(
				'qcld-wpchatbot-support-style-css',
				qcld_wpchatbot_comments_support_url . 'css/support-page.css',
				$support_deps,
				$support_css_ver,
				'screen'
			);

		}
	}

	add_action( 'admin_enqueue_scripts', 'qcld_wpchatbot_comments_include_promo_page_scripts' );

}

/*******************************
 * Callback function to show the HTML.
 */

require_once qcld_wpchatbot_comments_support_path . '/qc-clr-recommendbot-support-plugin.php';

if ( ! function_exists( 'qcpromo_wpbot_free_support_page_callback_func' ) ) {

	/**
	 * Callback function
	 *
	 * @return void
	 */
	function qcpromo_wpbot_free_support_page_callback_func() {

		$plugin_version = defined( 'QCLD_wpCHATBOT_VERSION' ) ? QCLD_wpCHATBOT_VERSION : '';
		$plugin_img     = defined( 'QCLD_wpCHATBOT_IMG_URL' ) ? QCLD_wpCHATBOT_IMG_URL . '/chatbot.png' : '';

		?>
		<div class="wrap qcld-main-wrapper wpbot-support-page">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Get Support', 'chatbot' ); ?></h1>

			<div class="wpbot-support-inner">
				<div class="qcld-wp-chatbot-wrap-header">
					<div class="qcld-wp-chatbot-wrap-header-logo">
						<?php if ( $plugin_img ) : ?>
							<img src="<?php echo esc_url( $plugin_img ); ?>" alt="WPBot">
						<?php endif; ?>
						<div class="qcld-wp-chatbot-wrap-header-copy">
							<a href="#" class="qcld-wp-chatbot-wrap-site__logo"><?php esc_html_e( 'WPBot Control Panel', 'chatbot' ); ?></a>
							<?php if ( $plugin_version ) : ?>
								<p><strong><?php esc_html_e( 'Core Version:', 'chatbot' ); ?></strong> v<?php echo esc_html( $plugin_version ); ?></p>
							<?php endif; ?>
						</div>
					</div>
					<ul class="qcld-wp-chatbot-wrap-version-wrapper">
						<li>
							<a class="wpchatbot-Upgrade" href="https://www.wpbot.pro/" target="_blank"><?php esc_html_e( 'Upgrade To Pro', 'chatbot' ); ?></a>
						</li>
					</ul>
				</div>

				<div class="wpbot-support-hero">
					<h2><?php esc_html_e( 'Get Support', 'chatbot' ); ?></h2>
					<p><?php esc_html_e( 'Stuck? Need help? Is the plugin missing a feature you need? Open a support ticket and our team will help you out.', 'chatbot' ); ?></p>
				</div>

				<div class="wpbot-support-grid">
					<article class="wpbot-support-card">
						<div class="wpbot-support-card__icon">
							<span class="dashicons dashicons-sos" aria-hidden="true"></span>
						</div>
						<h3><?php esc_html_e( 'Priority Support', 'chatbot' ); ?></h3>
						<p><?php esc_html_e( 'Pro users get faster, one-on-one help from our support team.', 'chatbot' ); ?></p>
						<a class="wpbot-support-btn wpbot-support-btn--primary" href="<?php echo esc_url( 'https://qc.turbopowers.com/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get Priority Support', 'chatbot' ); ?></a>
					</article>

					<article class="wpbot-support-card">
						<div class="wpbot-support-card__icon">
							<span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
						</div>
						<h3><?php esc_html_e( 'Knowledge Base', 'chatbot' ); ?></h3>
						<p><?php esc_html_e( 'Browse guides and documentation to find quick answers on your own.', 'chatbot' ); ?></p>
						<a class="wpbot-support-btn wpbot-support-btn--outline" href="<?php echo esc_url( 'https://wpbot.pro/docs/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Online KnowledgeBase', 'chatbot' ); ?></a>
					</article>

					<article class="wpbot-support-card">
						<div class="wpbot-support-card__icon">
							<span class="dashicons dashicons-wordpress" aria-hidden="true"></span>
						</div>
						<h3><?php esc_html_e( 'Free Version Support', 'chatbot' ); ?></h3>
						<p><?php esc_html_e( 'Get help from the WordPress.org support forum for the free version.', 'chatbot' ); ?></p>
						<a class="wpbot-support-btn wpbot-support-btn--soft" href="<?php echo esc_url( 'https://wordpress.org/support/plugin/chatbot/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get Support for Free Version', 'chatbot' ); ?></a>
					</article>
				</div>
			</div>
		</div>
		<?php
	}
}


/*******************************
 * Handle Ajex Request for Form Processing.
 */
add_action( 'wp_ajax_qcld_wpchatbot_comments_process_qc_promo_form', 'qcld_wpchatbot_comments_process_qc_promo_form' );

if ( ! function_exists( 'qcld_wpchatbot_comments_process_qc_promo_form' ) ) {

	/**
	 * Comnt process function
	 *
	 * @return void
	 */
	function qcld_wpchatbot_comments_process_qc_promo_form() {

		check_ajax_referer( 'wpchatbot', 'security' );

		$data['status']  = 'failed';
		$data['message'] = esc_html__(
			'Problem in processing your form submission request! Apologies for the inconveniences.<br> 
Please email to <span style="color:#22A0C9;font-weight:bold !important;font-size:14px "> quantumcloud@gmail.com </span> with any feedback. We will get back to you right away!',
			'chatbot'
		);

		$name        = isset( $_POST['post_name'] ) ? trim( sanitize_text_field( $_POST['post_name'] ) ) : '';
		$email       = isset( $_POST['post_email'] ) ? trim( sanitize_email( $_POST['post_email'] ) ) : '';
		$subject     = isset( $_POST['post_subject'] ) ? trim( sanitize_text_field( $_POST['post_subject'] ) ) : '';
		$message     = isset( $_POST['post_message'] ) ? trim( sanitize_text_field( $_POST['post_message'] ) ) : '';
		$plugin_name = isset( $_POST['post_plugin_name'] ) ? trim( sanitize_text_field( $_POST['post_plugin_name'] ) ) : '';

		if ( $name == '' || $email == '' || $subject == '' || $message == '' ) {
			$data['message'] = esc_html__( 'Please fill up all the requried form fields.', 'chatbot' );
		} elseif ( filter_var( $email, FILTER_VALIDATE_EMAIL ) === false ) {
			$data['message'] = esc_html__( 'Invalid email address.', 'chatbot' );
		} else {

			// build email body.

			$bodyContent = '';

			$bodyContent .= '<p><strong>' . esc_html__( 'Support Request Details:', 'chatbot' ) . '</strong></p><hr>';

			$bodyContent .= '<p>' . esc_html__( 'Name', 'chatbot' ) . ' : ' . esc_html( $name ) . '</p>';
			$bodyContent .= '<p>' . esc_html__( 'Email', 'chatbot' ) . ' : ' . esc_html( $email ) . '</p>';
			$bodyContent .= '<p>' . esc_html__( 'Subject', 'chatbot' ) . ' : ' . esc_html( $subject ) . '</p>';
			$bodyContent .= '<p>' . esc_html__( 'Message', 'chatbot' ) . ' : ' . esc_html( $message ) . '</p>';

			$bodyContent .= '<p>' . esc_html__( 'Sent Via the Plugin', 'chatbot' ) . ' : ' . esc_html( $plugin_name ) . '</p>';

			$bodyContent .= '<p></p><p>' . esc_html__( 'Mail sent from:', 'chatbot' ) . ' <strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>, ' . esc_html__( 'URL:', 'chatbot' ) . ' [' . esc_html( get_bloginfo( 'url' ) ) . '].</p>';
			$bodyContent .= '<p>' . esc_html__( 'Mail Generated on:', 'chatbot' ) . ' ' . esc_html( gmdate( 'F j, Y, g:i a' ) ) . '</p>';

			$toEmail = 'quantumcloud@gmail.com'; // Receivers email address.
			// $toEmail = "qc.kadir@gmail.com"; //Receivers email address.

			// Extract Domain.
			$url    = get_site_url();
			$url    = wp_parse_url( $url );
			$domain = isset( $url['host'] ) ? $url['host'] : '';

			$fakeFromEmailAddress = 'wordpress@' . $domain;

			$to        = $toEmail;
			$body      = $bodyContent;
			$headers   = array();
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			$headers[] = 'From: ' . esc_attr( $name ) . ' <' . esc_attr( $fakeFromEmailAddress ) . '>';
			$headers[] = 'Reply-To: ' . esc_attr( $name ) . ' <' . esc_attr( $email ) . '>';

			$finalSubject = esc_html( 'From Plugin Support Page:' ) . ' ' . esc_attr( $subject );

			$result = wp_mail( $to, $finalSubject, $body, $headers );

			if ( $result ) {
				$data['status']  = 'success';
				$data['message'] = esc_html__( 'Your email was sent successfully. Thanks!', 'chatbot' );
			}
		}

		ob_clean();

		echo json_encode( $data );

		die();
	}
}
