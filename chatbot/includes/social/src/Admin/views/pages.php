<?php
/**
 * Choose Pages and Instagram accounts (screen 04).
 *
 * @package QuantumCloud\Social
 */

use QuantumCloud\Social\Admin\Admin_Controller;
use QuantumCloud\WPBotCore\Connections\Social_Auth;

defined( 'ABSPATH' ) || exit;

$qcsoc_notice = Social_Auth::take_notice();
$qcsoc_brands = (array) apply_filters( 'qcld_wpbot_social/brands', array() );
?>
<p class="qcsoc-crumbs"><a href="<?php echo esc_url( Admin_Controller::url( 'accounts' ) ); ?>">‹ <?php esc_html_e( 'Accounts', 'chatbot' ); ?></a> · <a href="<?php echo esc_url( Admin_Controller::url( 'connect', array( 'network' => 'meta' ) ) ); ?>"><?php esc_html_e( 'Connect Facebook & Instagram', 'chatbot' ); ?></a></p>
<?php if ( '' !== $qcsoc_notice ) : ?>
	<div class="notice notice-error inline"><p><?php echo esc_html( $qcsoc_notice ); ?></p></div>
<?php endif; ?>
<section class="qcsoc-card" data-qcsoc="pages" data-brands="<?php echo esc_attr( (string) wp_json_encode( $qcsoc_brands ) ); ?>">
	<h2><?php esc_html_e( 'Choose Pages and Instagram accounts', 'chatbot' ); ?></h2>
	<p class="qcsoc-muted" data-qcsoc-pages-who></p>
	<div data-qcsoc-pages-body><p class="qcsoc-muted"><?php esc_html_e( 'Loading your Pages…', 'chatbot' ); ?></p></div>
	<div class="qcsoc-callout"><span class="dashicons dashicons-info" aria-hidden="true"></span> <?php esc_html_e( 'Facebook shows the Pages you can manage. To add Pages another person manages, they sign in here with their own Facebook account, or give you a role on the Page in Meta Business Suite.', 'chatbot' ); ?></div>
	<div class="qcsoc-footer">
		<span class="qcsoc-muted" data-qcsoc-pages-count></span>
		<a class="button" href="<?php echo esc_url( Admin_Controller::url( 'connect', array( 'network' => 'meta' ) ) ); ?>"><?php esc_html_e( 'Sign in as someone else', 'chatbot' ); ?></a>
		<button type="button" class="button button-primary" data-qcsoc-pages-save disabled><?php esc_html_e( 'Connect accounts', 'chatbot' ); ?></button>
	</div>
	<span class="qcsoc-status" data-qcsoc-status role="status"></span>
</section>
