<?php
/**
 * Marketing admin page shell.
 *
 * Wraps the React Marketing app (#qcwpbot-app) in the same markup and
 * classes used by the General Settings page (admin_ui.php): notice banner,
 * header card, left tab card, content card and the "Upgrade To Pro" sidebar.
 * The React app itself (campaigns, contacts, settings, REST saving) is
 * unchanged; it still mounts on #qcwpbot-app.
 *
 * @package chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$qcwpbot_mk_pro_features = array(
	'Core WPBot Pro',
	'Chat Sessions and Histories',
	'Extended Search Module',
	'OpenAI Basic and DialogFlow',
	'Simple text Responses Pro Module',
	'Conversational Forms Pro Module',
	'WooCommerce Module',
	'OpenAI Pro Adv. (Fine Tuning, Assistant)',
	'Live (Human) Chat Module',
	'Tavily Search API module',
	'Extended UI Module',
	'WebHook & Mailing List Module',
	'White Label Module',
	'FaceBook Messenger Module',
	'WhatsApp through Twilio Module',
	'Multi Language Module',
	'Settings Export Import Module',
	'Voice Message Module',
	'Voice Chat Module',
	'Telegram Module',
	'Priority Technical Support',
);
?>
<div class="wrap qcld-main-wrapper qcwpbot-mk-page">
	<h1 style="display:none"><?php esc_html_e( 'Marketing', 'chatbot' ); ?></h1>

	<?php /* Notice banner — same markup/classes as the plugin's rating notice (qc-rating-feature). "inline" keeps WP from relocating it. */ ?>
	<div class="notice is-dismissible inline qcbot-str-top-notic qcwpbot-mk-notice" style="background-color: #ffffff;border-radius: 16px;margin: 20px 0 0 0;border-left: 4px solid transparent; border:none;">
		<div class="wpbot-notice-content">
			<div class="qc-review-thumbnail qc-small-thumb">
				<img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/chatbot.png' ); ?>" alt="<?php esc_attr_e( 'WPBot', 'chatbot' ); ?>">
			</div>
			<div class="qc-review-text">
				<p style="font-weight:bold; font-size: 17px;color: var(--woobot-primary) !important;"><?php esc_html_e( 'Hello! Thank you for using our ChatBot.', 'chatbot' ); ?></p>
				<p><?php esc_html_e( 'If you have any feedback or need help, please ', 'chatbot' ); ?><b><a href="<?php echo esc_url( 'https://www.wpbot.pro/free-support/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Contact Us', 'chatbot' ); ?></a></b><?php esc_html_e( '. We take all user feedback seriously and resolve all issues.', 'chatbot' ); ?></p>
			</div>
		</div>
	</div>

	<?php /* Header card — identical to admin_ui.php. */ ?>
	<div class="qcld-wp-chatbot-wrap-header">
		<div class="qcld-wp-chatbot-wrap-header-logo">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbot' ) ); ?>" class="qcld-wp-chatbot-wrap-site__logo"><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/chatbot.png' ); ?>" alt="<?php esc_attr_e( 'WPBot', 'chatbot' ); ?>"> <?php esc_html_e( 'WPBot Control Panel', 'chatbot' ); ?></a>
			<p><strong><?php esc_html_e( 'Core Version:', 'chatbot' ); ?></strong> v<?php echo esc_html( QCLD_wpCHATBOT_VERSION ); ?></p>
		</div>
		<ul class="qcld-wp-chatbot-wrap-version-wrapper">
			<li><a class="wpchatbot-Upgrade" href="<?php echo esc_url( 'https://www.wpbot.pro/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Upgrade To Pro', 'chatbot' ); ?></a></li>
		</ul>
	</div>

	<div class="wp-chatbot-wrap">
		<div class="qcwpbot-mk-layout">

			<?php /* React Marketing app. Its sidebar becomes the left tab card and its main area the rest of the center card (see css/marketing-admin.css). */ ?>
			<div id="qcwpbot-app" class="qcwpbot-dashboard-wrap"></div>

			<?php /* Left card, bottom part — Go Premium (same markup as admin_ui.php). */ ?>
			<div class="qcwpbot-mk-nav-footer">
				<div class="text-center mt-5 qcld-goPreemium">
					<a href="<?php echo esc_url( 'https://www.wpbot.pro/' ); ?>" target="_blank" rel="noopener noreferrer"><span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="21" viewBox="0 0 24 21" fill="none" aria-hidden="true">
						<path d="M19.6799 17.28H4.31988C3.52596 17.28 2.87988 17.9261 2.87988 18.72C2.87988 19.5139 3.52596 20.16 4.31988 20.16H19.6799C20.4738 20.16 21.1199 19.5139 21.1199 18.72C21.1199 17.9261 20.4738 17.28 19.6799 17.28Z" fill="#FF9900"></path>
						<path d="M22.08 2.88C21.0211 2.88 20.16 3.74114 20.16 4.8C20.16 5.51137 20.5536 6.1267 21.1305 6.45886C20.0198 9.08925 18.287 10.703 16.6675 10.5571C14.8665 10.4102 13.3977 8.28094 12.5875 4.71839C13.6262 4.45533 14.4 3.51933 14.4 2.4C14.4 1.07616 13.3238 0 12 0C10.6761 0 9.59995 1.07616 9.59995 2.4C9.59995 3.51938 10.3737 4.45538 11.4124 4.71839C10.6022 8.28094 9.13336 10.4102 7.33242 10.5571C5.71964 10.703 3.97913 9.08925 2.86941 6.45886C3.44634 6.1267 3.83995 5.51133 3.83995 4.8C3.83995 3.74114 2.97881 2.88 1.91995 2.88C0.861141 2.88 0 3.74114 0 4.8C0 5.78498 0.748781 6.58945 1.70494 6.69886L3.55392 16.32H20.4461L22.295 6.69886C23.2512 6.58945 24 5.78498 24 4.8C24 3.74114 23.1389 2.88 22.08 2.88Z" fill="url(#qcwpbot_mk_crown_gradient)"></path>
						<defs>
							<linearGradient id="qcwpbot_mk_crown_gradient" x1="12" y1="0" x2="12" y2="16.32" gradientUnits="userSpaceOnUse">
								<stop stop-color="#FFC045"></stop>
								<stop offset="1" stop-color="#FF9900"></stop>
							</linearGradient>
						</defs>
					</svg></span><?php esc_html_e( 'Go Premium', 'chatbot' ); ?></a>
				</div>
			</div>

			<?php /* Right sidebar — identical to admin_ui.php. */ ?>
			<div class="wp-chatbot-admin-upgrade-pro-sidebar">
				<div class="wp-chatbot-admingradient-color">
					<img class="wp-chatbot-admin-banner" src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/template-sample.gif' ); ?>" alt="">
					<h3 class="wp-chatbot-admincart-title"><?php esc_html_e( 'Upgrade To', 'chatbot' ); ?> <span><?php esc_html_e( 'Pro', 'chatbot' ); ?></span></h3>
					<ul class="feature-list">
						<?php foreach ( $qcwpbot_mk_pro_features as $qcwpbot_mk_feature ) : ?>
							<li><img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/check2.svg' ); ?>" alt=""><?php echo esc_html( $qcwpbot_mk_feature ); ?></li>
						<?php endforeach; ?>
					</ul>
					<a class="wp-chatbot-admin-pro-upgrade-button" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( 'https://www.wpbot.pro/' ); ?>"><?php esc_html_e( 'Upgrade to Pro', 'chatbot' ); ?> <img src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/external-white.svg' ); ?>" alt=""></a>
				</div>
			</div>

		</div>
	</div>
</div>
