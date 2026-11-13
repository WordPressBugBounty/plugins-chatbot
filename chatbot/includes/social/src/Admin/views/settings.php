<?php
/**
 * Settings › Advanced (screen 26). Other tabs arrive with their milestones.
 *
 * @package QuantumCloud\Social
 */

use QuantumCloud\Social\Settings;
use QuantumCloud\Social\Support\Log;
use QuantumCloud\WPBotCore\Connections\Social_Apps;

defined( 'ABSPATH' ) || exit;

$qcsoc_settings = Settings::all();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab switch.
$qcsoc_tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'publishing';
$qcsoc_tabs    = array(
	'publishing' => __( 'Publishing', 'chatbot' ),
	'writing'    => __( 'Writing', 'chatbot' ),
	'links'      => __( 'Links & tracking', 'chatbot' ),
	'advanced'   => __( 'Advanced', 'chatbot' ),
);
$qcsoc_tabs    = (array) apply_filters( 'qcld_wpbot_social/settings_tabs', $qcsoc_tabs );
$qcsoc_tab     = isset( $qcsoc_tabs[ $qcsoc_tab ] ) ? $qcsoc_tab : 'publishing';
$qcsoc_log     = Log::latest( 20 );
$qcsoc_chatbot = (string) get_option( 'wpfb_app_id', '' );
$qcsoc_meta_id = Social_Apps::client_id( 'meta' );
?>
<section class="qcsoc-card">
	<h2><?php esc_html_e( 'Settings', 'chatbot' ); ?></h2>
	<nav class="qcsoc-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'chatbot' ); ?>">
		<?php foreach ( $qcsoc_tabs as $qcsoc_key => $qcsoc_label ) : ?>
			<a class="<?php echo $qcsoc_key === $qcsoc_tab ? 'is-active' : ''; ?>" <?php echo $qcsoc_key === $qcsoc_tab ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( \QuantumCloud\Social\Admin\Admin_Controller::url( 'settings', array( 'tab' => $qcsoc_key ) ) ); ?>"><?php echo esc_html( $qcsoc_label ); ?></a>
		<?php endforeach; ?>
	</nav>
</section>
<?php
if ( 'advanced' !== $qcsoc_tab ) {
	if ( in_array( $qcsoc_tab, array( 'publishing', 'writing', 'links' ), true ) ) {
		include __DIR__ . '/settings-' . $qcsoc_tab . '.php';
	} else {
		do_action( 'qcld_wpbot_social/settings_tab_' . $qcsoc_tab );
	}
	return;
}
?>
<div class="qcsoc-grid" data-qcsoc="settings">
	<section class="qcsoc-card">
		<h3><?php esc_html_e( 'Background jobs', 'chatbot' ); ?></h3>
		<div class="qcsoc-row">
			<div><strong><?php esc_html_e( 'Runs with WordPress cron', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'Scheduled posts go out when WordPress cron runs. On quiet sites, ask your host for a server cron that calls wp-cron.php every 5 minutes (and set DISABLE_WP_CRON in wp-config.php):', 'chatbot' ); ?></p></div>
		</div>
		<pre class="qcsoc-code">*/5 * * * * wget -q -O - <?php echo esc_html( site_url( 'wp-cron.php' ) ); ?></pre>
	</section>
	<section class="qcsoc-card">
		<h3><?php esc_html_e( 'Connections', 'chatbot' ); ?></h3>
		<div class="qcsoc-row">
			<div><strong><?php esc_html_e( 'Check tokens', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'Daily; expired logins pause their accounts and tell you.', 'chatbot' ); ?></p></div>
			<label class="qcsoc-switch"><input type="checkbox" name="check_tokens" data-qcsoc-setting <?php checked( $qcsoc_settings['check_tokens'] ); ?>><span class="screen-reader-text"><?php esc_html_e( 'Check tokens daily', 'chatbot' ); ?></span></label>
		</div>
		<p><button type="button" class="button" data-qcsoc-check><?php esc_html_e( 'Check now', 'chatbot' ); ?></button> <span class="qcsoc-status" data-qcsoc-check-status role="status"></span></p>
		<div class="qcsoc-row">
			<div><strong><?php esc_html_e( 'Webhooks', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'Comments and messages arrive through the ChatBot\'s Messenger and Instagram channels. WPBot Social does not change them.', 'chatbot' ); ?></p></div>
		</div>
		<?php if ( '' !== $qcsoc_chatbot && '' !== $qcsoc_meta_id && $qcsoc_chatbot !== $qcsoc_meta_id ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'The ChatBot channels use a different Meta app than Social. That works, but keep both apps\' settings up to date.', 'chatbot' ); ?></p></div>
		<?php endif; ?>
	</section>
	<section class="qcsoc-card">
		<h3><?php esc_html_e( 'Log', 'chatbot' ); ?></h3>
		<?php if ( array() === $qcsoc_log ) : ?>
			<p class="qcsoc-muted"><?php esc_html_e( 'Nothing logged yet.', 'chatbot' ); ?></p>
		<?php else : ?>
			<table class="qcsoc-table">
				<thead><tr><th scope="col"><?php esc_html_e( 'When', 'chatbot' ); ?></th><th scope="col"><?php esc_html_e( 'Event', 'chatbot' ); ?></th><th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Result', 'chatbot' ); ?></span></th></tr></thead>
				<tbody>
				<?php foreach ( $qcsoc_log as $qcsoc_entry ) : ?>
					<tr>
						<td class="qcsoc-muted"><?php echo esc_html( get_date_from_gmt( $qcsoc_entry['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
						<td><?php echo esc_html( $qcsoc_entry['message'] ); ?></td>
						<td><span class="qcsoc-pill qcsoc-pill--<?php echo esc_attr( 'ok' === $qcsoc_entry['level'] ? 'ok' : ( 'error' === $qcsoc_entry['level'] ? 'bad' : 'warn' ) ); ?>"><?php echo esc_html( ucfirst( $qcsoc_entry['level'] ) ); ?></span></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<div class="qcsoc-row">
			<strong><?php esc_html_e( 'Keep log for', 'chatbot' ); ?></strong>
			<select name="log_days" data-qcsoc-setting>
				<?php foreach ( array( 7, 30, 90, 365 ) as $qcsoc_days ) : ?>
					<?php /* translators: %d: number of days. */ ?>
					<option value="<?php echo esc_attr( (string) $qcsoc_days ); ?>" <?php selected( (int) $qcsoc_settings['log_days'], $qcsoc_days ); ?>><?php echo esc_html( sprintf( _n( '%d day', '%d days', $qcsoc_days, 'chatbot' ), $qcsoc_days ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</section>
	<section class="qcsoc-card">
		<h3><?php esc_html_e( 'Data', 'chatbot' ); ?></h3>
		<div class="qcsoc-row">
			<div><strong><?php esc_html_e( 'Export settings', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'Accounts are not exported; secrets never are.', 'chatbot' ); ?></p></div>
			<button type="button" class="button" data-qcsoc-export><?php esc_html_e( 'Export', 'chatbot' ); ?></button>
		</div>
		<div class="qcsoc-row">
			<div><strong><?php esc_html_e( 'Import settings', 'chatbot' ); ?></strong></div>
			<label class="button"><?php esc_html_e( 'Import', 'chatbot' ); ?><input type="file" accept="application/json,.json" data-qcsoc-import hidden></label>
		</div>
		<div class="qcsoc-row">
			<div><strong><?php esc_html_e( 'Delete Social data when the plugin is deleted', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'Posts, logs, settings and tokens. Posts already published stay on the networks.', 'chatbot' ); ?></p></div>
			<label class="qcsoc-switch"><input type="checkbox" name="delete_on_uninstall" data-qcsoc-setting <?php checked( $qcsoc_settings['delete_on_uninstall'] ); ?>><span class="screen-reader-text"><?php esc_html_e( 'Delete Social data on uninstall', 'chatbot' ); ?></span></label>
		</div>
		<div class="qcsoc-row">
			<div><strong><?php esc_html_e( 'Disconnect all accounts', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'Removes every Social token from this site. The ChatBot channels are not affected.', 'chatbot' ); ?></p></div>
			<button type="button" class="button qcsoc-danger" data-qcsoc-disconnect-all><?php esc_html_e( 'Disconnect all', 'chatbot' ); ?></button>
		</div>
		<span class="qcsoc-status" data-qcsoc-status role="status"></span>
	</section>
</div>
