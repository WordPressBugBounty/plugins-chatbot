<?php
/**
 * Settings › Publishing (screen 21).
 *
 * @package QuantumCloud\Social
 */

use QuantumCloud\Social\Media\Public_Media_Check;

defined( 'ABSPATH' ) || exit;

$qcsoc_media   = Public_Media_Check::result();
$qcsoc_sharing = array_filter(
	\QuantumCloud\Social\Accounts\Account_Store::all(),
	static function ( $a ) {
		return ! empty( $a['auto_share'] ) && 'ok' === $a['status'];
	}
);
$qcsoc_cats    = get_categories( array( 'hide_empty' => false ) );
?>
<div class="qcsoc-grid" data-qcsoc="settings">
	<section class="qcsoc-card">
		<h3><?php esc_html_e( 'Auto-share new content', 'chatbot' ); ?></h3>
		<p class="qcsoc-muted"><?php esc_html_e( 'When something is published on the site, write a post for it.', 'chatbot' ); ?>
			<?php
			echo esc_html(
				array() === $qcsoc_sharing
					? __( 'No account has auto-share on yet: turn it on in Accounts.', 'chatbot' )
					/* translators: %s: account names. */
					: sprintf( __( 'Shares to: %s.', 'chatbot' ), implode( ', ', wp_list_pluck( $qcsoc_sharing, 'name' ) ) )
			);
			?>
		</p>
		<?php foreach ( \QuantumCloud\Social\Posts\Auto_Share::types() as $qcsoc_type => $qcsoc_label ) : ?>
			<div class="qcsoc-row">
				<div><strong><?php echo esc_html( $qcsoc_label ); ?></strong></div>
				<label class="qcsoc-switch"><input type="checkbox" name="auto_share_types" value="<?php echo esc_attr( $qcsoc_type ); ?>" data-qcsoc-setting data-qcsoc-format="multi" <?php checked( in_array( $qcsoc_type, (array) $qcsoc_settings['auto_share_types'], true ) ); ?>><span class="screen-reader-text"><?php /* translators: %s: content type. */ echo esc_html( sprintf( __( 'Auto-share %s', 'chatbot' ), $qcsoc_label ) ); ?></span></label>
			</div>
		<?php endforeach; ?>
		<div class="qcsoc-row">
			<label for="qcsoc-as-mode"><strong><?php esc_html_e( 'Before it goes out', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'A version the honesty check flags always waits for you.', 'chatbot' ); ?></p></label>
			<select id="qcsoc-as-mode" name="auto_share_mode" data-qcsoc-setting>
				<option value="draft" <?php selected( $qcsoc_settings['auto_share_mode'], 'draft' ); ?>><?php esc_html_e( 'Save as draft for me to check', 'chatbot' ); ?></option>
				<option value="schedule" <?php selected( $qcsoc_settings['auto_share_mode'], 'schedule' ); ?>><?php esc_html_e( 'Schedule it automatically', 'chatbot' ); ?></option>
			</select>
		</div>
		<div class="qcsoc-row">
			<label for="qcsoc-as-delay"><strong><?php esc_html_e( 'Minutes after publishing', 'chatbot' ); ?></strong></label>
			<input type="number" id="qcsoc-as-delay" name="auto_share_delay" min="0" max="1440" data-qcsoc-setting value="<?php echo esc_attr( (string) $qcsoc_settings['auto_share_delay'] ); ?>">
		</div>
		<div class="qcsoc-row">
			<label for="qcsoc-as-cap"><strong><?php esc_html_e( 'At most per account per day', 'chatbot' ); ?></strong></label>
			<input type="number" id="qcsoc-as-cap" name="auto_share_cap" min="1" max="20" data-qcsoc-setting value="<?php echo esc_attr( (string) $qcsoc_settings['auto_share_cap'] ); ?>">
		</div>
		<div class="qcsoc-row">
			<div><strong><?php esc_html_e( 'Write with AI', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'Off, or when AI is not set up: the title and a short excerpt.', 'chatbot' ); ?></p></div>
			<label class="qcsoc-switch"><input type="checkbox" name="auto_share_ai" data-qcsoc-setting <?php checked( $qcsoc_settings['auto_share_ai'] ); ?>><span class="screen-reader-text"><?php esc_html_e( 'Write auto-shares with AI', 'chatbot' ); ?></span></label>
		</div>
		<div class="qcsoc-row qcsoc-row--stack">
			<label for="qcsoc-as-skip"><strong><?php esc_html_e( 'Skip posts in categories', 'chatbot' ); ?></strong></label>
			<select id="qcsoc-as-skip" name="auto_share_skip_cats" multiple size="4" data-qcsoc-setting data-qcsoc-format="select-multi">
				<?php foreach ( $qcsoc_cats as $qcsoc_cat ) : ?>
					<option value="<?php echo esc_attr( (string) $qcsoc_cat->term_id ); ?>" <?php selected( in_array( (int) $qcsoc_cat->term_id, array_map( 'intval', (array) $qcsoc_settings['auto_share_skip_cats'] ), true ) ); ?>><?php echo esc_html( $qcsoc_cat->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</section>
	<section class="qcsoc-card">
		<h3><?php esc_html_e( 'Scheduling', 'chatbot' ); ?></h3>
		<div class="qcsoc-row">
			<div><strong><?php esc_html_e( 'Default time', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php /* translators: %s: time zone. */ echo esc_html( sprintf( __( 'New posts start at this time, in site time (%s).', 'chatbot' ), wp_timezone_string() ) ); ?></p></div>
			<input type="time" name="default_time" data-qcsoc-setting value="<?php echo esc_attr( $qcsoc_settings['default_time'] ); ?>">
		</div>
		<div class="qcsoc-row">
			<label for="qcsoc-missed"><strong><?php esc_html_e( 'Hold posts that are this many hours late', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'If Social could not run for a while (license paused, background jobs stopped), late posts wait for you instead of going out in a burst. 0 sends them anyway.', 'chatbot' ); ?></p></label>
			<input type="number" id="qcsoc-missed" name="missed_after" min="0" max="168" data-qcsoc-setting value="<?php echo esc_attr( (string) $qcsoc_settings['missed_after'] ); ?>">
		</div>
		<div class="qcsoc-row">
			<div><strong><?php esc_html_e( 'Retries', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'Rate limits and network errors: 3 attempts over about an hour. Refused posts are not retried. If WPBot cannot tell whether a post went out, it checks first and never posts twice.', 'chatbot' ); ?></p></div>
		</div>
	</section>
	<section class="qcsoc-card">
		<h3><?php esc_html_e( 'When something goes wrong', 'chatbot' ); ?></h3>
		<div class="qcsoc-row">
			<div><strong><?php esc_html_e( 'Failure email', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'One email per post when it fails or needs you to check.', 'chatbot' ); ?></p></div>
			<label class="qcsoc-switch"><input type="checkbox" name="failure_email" data-qcsoc-setting <?php checked( $qcsoc_settings['failure_email'] ); ?>><span class="screen-reader-text"><?php esc_html_e( 'Send failure emails', 'chatbot' ); ?></span></label>
		</div>
		<div class="qcsoc-row">
			<label for="qcsoc-mail"><strong><?php esc_html_e( 'Send to', 'chatbot' ); ?></strong></label>
			<input type="email" id="qcsoc-mail" name="failure_email_to" data-qcsoc-setting placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>" value="<?php echo esc_attr( $qcsoc_settings['failure_email_to'] ); ?>">
		</div>
		<div class="qcsoc-row">
			<div><strong><?php esc_html_e( 'Public media check', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'Instagram and Threads download images from your site. This check is best effort: a firewall can still block them.', 'chatbot' ); ?></p></div>
			<?php if ( $qcsoc_media['ok'] ) : ?>
				<span class="qcsoc-pill qcsoc-pill--ok"><?php esc_html_e( 'Reachable', 'chatbot' ); ?></span>
			<?php else : ?>
				<span class="qcsoc-pill qcsoc-pill--warn"><?php echo esc_html( $qcsoc_media['reason'] ); ?></span>
			<?php endif; ?>
		</div>
	</section>
	<span class="qcsoc-status" data-qcsoc-status role="status"></span>
</div>
