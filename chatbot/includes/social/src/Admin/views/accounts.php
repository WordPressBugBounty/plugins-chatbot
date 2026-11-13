<?php
/**
 * Accounts (screen 01).
 *
 * @package QuantumCloud\Social
 */

use QuantumCloud\Social\Accounts\Account_Store;
use QuantumCloud\Social\Admin\Admin_Controller;
use QuantumCloud\WPBotCore\Connections\Meta;
use QuantumCloud\WPBotCore\Connections\Social_Apps;
use QuantumCloud\WPBotCore\Connections\Social_Auth;

defined( 'ABSPATH' ) || exit;

$qcsoc_accounts = Account_Store::all();
$qcsoc_manage   = current_user_can( 'wpbot_social_settings' );
$qcsoc_notice   = $qcsoc_manage ? Social_Auth::take_notice() : '';
$qcsoc_brands   = (array) apply_filters( 'qcld_wpbot_social/brands', array() );
$qcsoc_groups   = array();
foreach ( $qcsoc_accounts as $qcsoc_account ) {
	$qcsoc_groups[ $qcsoc_account['brand_id'] ][] = $qcsoc_account;
}
?>
<?php if ( '' !== $qcsoc_notice ) : ?>
	<div class="notice notice-error inline"><p><?php echo esc_html( $qcsoc_notice ); ?></p></div>
<?php endif; ?>
<section class="qcsoc-card" data-qcsoc="accounts">
	<div class="qcsoc-card__head">
		<div>
			<h2><?php esc_html_e( 'Connected accounts', 'chatbot' ); ?></h2>
			<p class="qcsoc-muted"><?php esc_html_e( 'Posts go out from these accounts. Tokens are encrypted and stay on this site.', 'chatbot' ); ?></p>
		</div>
		<div class="qcsoc-actions">
			<?php do_action( 'qcld_wpbot_social/accounts_actions' ); ?>
			<?php if ( $qcsoc_manage ) : ?>
				<a class="button button-primary" href="#qcsoc-add-network"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Connect an account', 'chatbot' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( array() === $qcsoc_accounts ) : ?>
		<div class="qcsoc-empty">
			<p><strong><?php esc_html_e( 'No accounts yet.', 'chatbot' ); ?></strong></p>
			<p class="qcsoc-muted"><?php esc_html_e( 'Connect a Facebook Page, an Instagram account, Threads, a Google Business Profile location or a Telegram channel to start posting from WordPress.', 'chatbot' ); ?></p>
		</div>
	<?php else : ?>
		<table class="qcsoc-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Account', 'chatbot' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Network', 'chatbot' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Connection method', 'chatbot' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'chatbot' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Auto-share new posts', 'chatbot' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'chatbot' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $qcsoc_groups as $qcsoc_brand_id => $qcsoc_rows ) : ?>
				<?php if ( $qcsoc_brand_id && isset( $qcsoc_brands[ $qcsoc_brand_id ] ) ) : ?>
					<tr class="qcsoc-group"><th colspan="6" scope="rowgroup"><span class="dashicons dashicons-portfolio" aria-hidden="true"></span> <?php echo esc_html( $qcsoc_brands[ $qcsoc_brand_id ] ); ?> <span class="qcsoc-muted"><?php /* translators: %d: number of accounts. */ echo esc_html( sprintf( _n( 'brand · %d account', 'brand · %d accounts', count( $qcsoc_rows ), 'chatbot' ), count( $qcsoc_rows ) ) ); ?></span></th></tr>
				<?php endif; ?>
				<?php foreach ( $qcsoc_rows as $qcsoc_row ) : ?>
					<?php $qcsoc_in_use = 'facebook' === $qcsoc_row['network'] && method_exists( Meta::class, 'connection_in_use' ) && Meta::connection_in_use( $qcsoc_row['remote_id'] ); ?>
					<tr data-account="<?php echo esc_attr( (string) $qcsoc_row['id'] ); ?>"<?php echo $qcsoc_in_use ? ' data-qcsoc-in-use' : ''; ?>>
						<td>
							<div class="qcsoc-account">
								<span class="qcsoc-avatar qcsoc-avatar--<?php echo esc_attr( $qcsoc_row['network'] ); ?>"><?php echo esc_html( Admin_Controller::initials( $qcsoc_row['name'] ) ); ?></span>
								<span>
									<strong><?php echo esc_html( $qcsoc_row['name'] ); ?></strong><br>
									<span class="qcsoc-muted">
									<?php
									$qcsoc_kinds = array(
										'page'         => __( 'Facebook Page', 'chatbot' ),
										'professional' => __( 'Instagram professional · via the Facebook Page', 'chatbot' ),
										'profile'      => __( 'Threads profile', 'chatbot' ),
										'location'     => __( 'Google Business Profile · uses your Google connection', 'chatbot' ),
										'channel'      => __( 'Telegram channel · posts through a Telegram bot', 'chatbot' ),
									);
									echo esc_html( isset( $qcsoc_kinds[ $qcsoc_row['kind'] ] ) ? $qcsoc_kinds[ $qcsoc_row['kind'] ] : $qcsoc_row['kind'] );
									?>
									</span>
								</span>
							</div>
						</td>
						<td><?php echo esc_html( Admin_Controller::network_label( $qcsoc_row['network'] ) ); ?></td>
						<td class="qcsoc-muted">
							<?php
							if ( 'google_business' === $qcsoc_row['network'] ) {
								esc_html_e( 'Uses your Google connection', 'chatbot' );
							} elseif ( 'telegram' === $qcsoc_row['network'] ) {
								echo esc_html( 'own' === \QuantumCloud\Social\Accounts\Connector::telegram_bot_view()['source'] ? __( 'Bot token · Social', 'chatbot' ) : __( 'Bot token · Telegram add-on', 'chatbot' ) );
							} else {
								esc_html_e( 'Your own app', 'chatbot' );
							}
							?>
						</td>
						<td>
							<?php if ( 'ok' === $qcsoc_row['status'] ) : ?>
								<span class="qcsoc-pill qcsoc-pill--ok"><?php esc_html_e( 'Connected', 'chatbot' ); ?></span>
							<?php elseif ( 'expired' === $qcsoc_row['status'] ) : ?>
								<span class="qcsoc-pill qcsoc-pill--bad"><?php esc_html_e( 'Expired · posts paused', 'chatbot' ); ?></span>
							<?php else : ?>
								<span class="qcsoc-pill qcsoc-pill--warn"><?php echo esc_html( '' !== $qcsoc_row['status_message'] ? $qcsoc_row['status_message'] : __( 'Needs attention', 'chatbot' ) ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<label class="qcsoc-switch">
								<input type="checkbox" data-qcsoc-autoshare <?php checked( $qcsoc_row['auto_share'] ); ?> <?php disabled( ! $qcsoc_manage ); ?>>
								<span class="screen-reader-text"><?php /* translators: %s: account name. */ echo esc_html( sprintf( __( 'Auto-share new posts to %s', 'chatbot' ), $qcsoc_row['name'] ) ); ?></span>
							</label>
						</td>
						<td class="qcsoc-right">
							<?php if ( $qcsoc_manage ) : ?>
								<?php if ( 'expired' === $qcsoc_row['status'] && in_array( $qcsoc_row['network'], array( 'facebook', 'instagram', 'threads' ), true ) ) : ?>
									<a class="button button-primary" href="<?php echo esc_url( Admin_Controller::url( 'connect', array( 'network' => 'threads' === $qcsoc_row['network'] ? 'threads' : 'meta' ) ) ); ?>"><?php esc_html_e( 'Reconnect', 'chatbot' ); ?></a>
								<?php endif; ?>
								<button type="button" class="button-link qcsoc-danger" data-qcsoc-disconnect><?php esc_html_e( 'Disconnect', 'chatbot' ); ?></button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>

<?php if ( $qcsoc_manage ) : ?>
<section class="qcsoc-card" id="qcsoc-add-network">
	<h2><?php esc_html_e( 'Add a network', 'chatbot' ); ?></h2>
	<p class="qcsoc-muted"><?php esc_html_e( 'You connect each network through your own developer app. Set it up once; the steps are on the next screen.', 'chatbot' ); ?></p>
	<div class="qcsoc-networks">
		<?php
		$qcsoc_networks = array(
			array( 'meta', __( 'Facebook & Instagram', 'chatbot' ), __( 'One Meta app for Pages and Instagram', 'chatbot' ), Social_Apps::has( 'meta' ) ),
			array( 'threads', __( 'Threads', 'chatbot' ), __( 'Its own Threads app and sign-in', 'chatbot' ), Social_Apps::has( 'threads' ) ),
			array( 'google', __( 'Google Business Profile', 'chatbot' ), __( 'Uses your Google connection', 'chatbot' ), null ),
			array( 'telegram', __( 'Telegram channel', 'chatbot' ), __( 'Posts through a Telegram bot you add', 'chatbot' ), null ),
		);
		foreach ( $qcsoc_networks as $qcsoc_net ) :
			?>
			<a class="qcsoc-network" href="<?php echo esc_url( Admin_Controller::url( 'connect', array( 'network' => $qcsoc_net[0] ) ) ); ?>" data-network="<?php echo esc_attr( $qcsoc_net[0] ); ?>">
				<span class="qcsoc-avatar qcsoc-avatar--<?php echo esc_attr( $qcsoc_net[0] ); ?>"><?php echo esc_html( Admin_Controller::initials( $qcsoc_net[1] ) ); ?></span>
				<strong><?php echo esc_html( $qcsoc_net[1] ); ?></strong>
				<span class="qcsoc-muted"><?php echo esc_html( $qcsoc_net[2] ); ?></span>
				<?php if ( true === $qcsoc_net[3] ) : ?>
					<span class="qcsoc-pill qcsoc-pill--ok"><?php esc_html_e( 'App ready', 'chatbot' ); ?></span>
				<?php else : ?>
					<span class="qcsoc-pill"><?php echo esc_html( false === $qcsoc_net[3] ? __( 'Set up app', 'chatbot' ) : __( 'Connect', 'chatbot' ) ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
		<?php do_action( 'qcld_wpbot_social/add_network_cards' ); ?>
	</div>
</section>
<?php endif; ?>
