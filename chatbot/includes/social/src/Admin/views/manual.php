<?php
/**
 * Post yourself (screen 08): ready copies for networks apps cannot post to.
 *
 * @package QuantumCloud\Social
 */

use QuantumCloud\Social\Accounts\Account_Store;
use QuantumCloud\Social\Admin\Admin_Controller;
use QuantumCloud\Social\Module;
use QuantumCloud\Social\Networks\Manual_Network;
use QuantumCloud\Social\Posts\Target_Store;

defined( 'ABSPATH' ) || exit;

$qcsoc_targets = Target_Store::manual( 30 );
$qcsoc_next    = Target_Store::next_manual();
?>
<section class="qcsoc-card qcsoc-manual__head">
	<div>
		<h2><?php esc_html_e( 'Post yourself', 'chatbot' ); ?></h2>
		<p class="qcsoc-muted"><?php esc_html_e( 'These networks don\'t let apps post. WPBot writes each copy and gets the image ready; you post it in a couple of clicks.', 'chatbot' ); ?></p>
	</div>
	<?php if ( '' !== $qcsoc_next ) : ?>
		<span class="qcsoc-pill"><span class="dashicons dashicons-bell" aria-hidden="true"></span> <?php /* translators: %s: date and time. */ echo esc_html( sprintf( __( 'Next reminder %s', 'chatbot' ), get_date_from_gmt( $qcsoc_next, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) ); ?></span>
	<?php endif; ?>
</section>
<div class="qcsoc-manual" data-qcsoc="manual">
	<?php if ( array() === $qcsoc_targets ) : ?>
		<section class="qcsoc-card">
			<p><?php esc_html_e( 'Nothing to post yourself right now.', 'chatbot' ); ?></p>
			<p class="qcsoc-muted">
			<?php
			if ( \QuantumCloud\Social\Module::MODE_MASTER === \QuantumCloud\Social\Module::instance()->mode() ) {
				esc_html_e( 'Pick WhatsApp (or, with Master, Medium, Nextdoor, Quora or Snapchat) in Create post. At the scheduled time the copy appears here and you get a reminder.', 'chatbot' );
			} else {
				// No Master previews outside Master (WPBot Free: wordpress.org, no upsell inside core screens).
				esc_html_e( 'Pick WhatsApp in Create post. At the scheduled time the copy appears here and you get a reminder.', 'chatbot' );
			}
			?>
			<a href="<?php echo esc_url( Admin_Controller::url( 'write' ) ); ?>"><?php esc_html_e( 'Create post', 'chatbot' ); ?></a></p>
		</section>
	<?php endif; ?>
	<?php foreach ( $qcsoc_targets as $qcsoc_t ) : ?>
		<?php
		$qcsoc_account = Account_Store::get( $qcsoc_t['account_id'] );
		$qcsoc_net     = $qcsoc_account ? Module::instance()->network( $qcsoc_account['network'] ) : null;
		if ( ! $qcsoc_net instanceof Manual_Network ) {
			continue;
		}
		$qcsoc_how   = $qcsoc_net->how();
		$qcsoc_text  = isset( $qcsoc_t['draft']['text'] ) ? (string) $qcsoc_t['draft']['text'] : '';
		$qcsoc_link  = isset( $qcsoc_t['draft']['link'] ) ? (string) $qcsoc_t['draft']['link'] : '';
		$qcsoc_full  = trim( $qcsoc_text . ( '' !== $qcsoc_link && false === strpos( $qcsoc_text, $qcsoc_link ) ? "\n" . $qcsoc_link : '' ) );
		$qcsoc_image = isset( $qcsoc_t['draft']['images'][0]['url'] ) ? (string) $qcsoc_t['draft']['images'][0]['url'] : '';
		$qcsoc_open  = $qcsoc_net->open_url( $qcsoc_full, $qcsoc_link );
		$qcsoc_done  = 'done' === $qcsoc_t['state'];
		?>
		<article class="qcsoc-card qcsoc-manual__card" data-target="<?php echo esc_attr( (string) $qcsoc_t['id'] ); ?>" data-copy="<?php echo esc_attr( $qcsoc_full ); ?>">
			<header class="qcsoc-version__head">
				<strong><?php echo esc_html( $qcsoc_net->label() ); ?></strong>
				<?php if ( '' !== $qcsoc_how['kind'] ) : ?>
					<span class="qcsoc-pill"><?php echo esc_html( $qcsoc_how['kind'] ); ?></span>
				<?php endif; ?>
			</header>
			<?php if ( '' !== $qcsoc_image ) : ?>
				<img class="qcsoc-manual__image" src="<?php echo esc_url( $qcsoc_image ); ?>" alt="">
			<?php endif; ?>
			<p class="qcsoc-manual__text"><?php echo nl2br( esc_html( $qcsoc_full ) ); ?></p>
			<div class="qcsoc-manual__actions">
				<button type="button" class="button" data-qcsoc-copy-text><span class="dashicons dashicons-admin-page" aria-hidden="true"></span> <?php esc_html_e( 'Copy text', 'chatbot' ); ?></button>
				<?php if ( '' !== $qcsoc_image ) : ?>
					<a class="button" href="<?php echo esc_url( $qcsoc_image ); ?>" download><span class="dashicons dashicons-download" aria-hidden="true"></span> <?php esc_html_e( 'Image', 'chatbot' ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $qcsoc_open ) : ?>
					<a class="button" href="<?php echo esc_url( $qcsoc_open ); ?>" target="_blank" rel="noopener noreferrer" data-qcsoc-open><span class="dashicons dashicons-external" aria-hidden="true"></span> <?php /* translators: %s: network. */ echo esc_html( sprintf( __( 'Open %s', 'chatbot' ), $qcsoc_net->label() ) ); ?></a>
				<?php endif; ?>
			</div>
			<?php if ( $qcsoc_how['phone'] && '' !== $qcsoc_open ) : ?>
				<div class="qcsoc-manual__phone">
					<div class="qcsoc-qr" data-qcsoc-qr="<?php echo esc_attr( $qcsoc_open ); ?>" role="img" aria-label="<?php esc_attr_e( 'QR code to open this copy on your phone', 'chatbot' ); ?>"></div>
					<p class="qcsoc-muted"><?php echo esc_html( $qcsoc_how['note'] ); ?></p>
				</div>
			<?php elseif ( '' !== $qcsoc_how['note'] ) : ?>
				<p class="qcsoc-muted"><?php echo esc_html( $qcsoc_how['note'] ); ?></p>
			<?php endif; ?>
			<footer class="qcsoc-manual__foot">
				<?php if ( $qcsoc_done ) : ?>
					<span class="qcsoc-pill qcsoc-pill--ok"><?php esc_html_e( 'Posted', 'chatbot' ); ?></span>
					<?php if ( '' !== (string) $qcsoc_t['remote_url'] ) : ?>
						<a href="<?php echo esc_url( $qcsoc_t['remote_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $qcsoc_t['remote_url'] ); ?></a>
					<?php endif; ?>
				<?php elseif ( current_user_can( 'wpbot_social_publish' ) ) : ?>
					<label class="qcsoc-manual__url"><span class="screen-reader-text"><?php esc_html_e( 'Link to the post (optional)', 'chatbot' ); ?></span><input type="url" placeholder="<?php esc_attr_e( 'Link to the post (optional)', 'chatbot' ); ?>" data-qcsoc-posted-url></label>
					<button type="button" class="button button-primary" data-qcsoc-posted><?php esc_html_e( 'Mark as posted', 'chatbot' ); ?></button>
					<button type="button" class="button-link" data-qcsoc-skip><?php esc_html_e( 'Skip', 'chatbot' ); ?></button>
				<?php endif; ?>
			</footer>
		</article>
	<?php endforeach; ?>
	<aside class="qcsoc-card qcsoc-manual__how">
		<h3><?php esc_html_e( 'How "Post yourself" works', 'chatbot' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'Pick these networks in Create post like any other; WPBot writes a copy in each one\'s style.', 'chatbot' ); ?></li>
			<li><?php esc_html_e( 'At the scheduled time you get a reminder (email and the Posts badge).', 'chatbot' ); ?></li>
			<li><?php esc_html_e( 'Copy, open the network, paste. Images are ready at the right size.', 'chatbot' ); ?></li>
			<li><?php echo esc_html( \QuantumCloud\Social\Module::MODE_MASTER === \QuantumCloud\Social\Module::instance()->mode() ? __( 'Mark as posted. Paste the post\'s link if you like; visits from the tracked link show in Analytics either way.', 'chatbot' ) : __( 'Mark as posted. Paste the post\'s link if you like.', 'chatbot' ) ); ?></li>
		</ol>
		<p class="qcsoc-callout"><?php esc_html_e( 'If a network opens a posting API later, it moves to automatic publishing without changing your posts.', 'chatbot' ); ?></p>
	</aside>
	<span class="qcsoc-status" data-qcsoc-status role="status"></span>
</div>
