<?php
/**
 * Connect a network (screen 03): Meta, Threads, Google Business Profile, Telegram.
 *
 * @package QuantumCloud\Social
 */

use QuantumCloud\Social\Accounts\Connector;
use QuantumCloud\Social\Accounts\Meta_Scopes;
use QuantumCloud\Social\Admin\Admin_Controller;
use QuantumCloud\Social\Settings;
use QuantumCloud\WPBotCore\Connections\Meta;
use QuantumCloud\WPBotCore\Connections\Social_Apps;
use QuantumCloud\WPBotCore\Connections\Social_Auth;

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing.
$qcsoc_network = isset( $_GET['network'] ) ? sanitize_key( wp_unslash( $_GET['network'] ) ) : 'meta';
$qcsoc_network = in_array( $qcsoc_network, array( 'meta', 'threads', 'google', 'telegram' ), true ) ? $qcsoc_network : 'meta';
?>
<p class="qcsoc-crumbs"><a href="<?php echo esc_url( Admin_Controller::url( 'accounts' ) ); ?>">‹ <?php esc_html_e( 'Accounts', 'chatbot' ); ?></a></p>
<div class="qcsoc-columns">
<section class="qcsoc-card qcsoc-card--main" data-qcsoc="connect" data-app="<?php echo esc_attr( $qcsoc_network ); ?>">
<?php if ( 'meta' === $qcsoc_network || 'threads' === $qcsoc_network ) : ?>
	<?php
	$qcsoc_app  = Social_Apps::public_view( $qcsoc_network );
	$qcsoc_meta = 'meta' === $qcsoc_network;
	?>
	<h2><?php echo esc_html( $qcsoc_meta ? __( 'Connect Facebook & Instagram', 'chatbot' ) : __( 'Connect Threads', 'chatbot' ) ); ?></h2>
	<p class="qcsoc-muted"><?php echo esc_html( $qcsoc_meta ? __( 'Connection method: Your own Meta app', 'chatbot' ) : __( 'Connection method: Your own Threads app', 'chatbot' ) ); ?></p>
	<ol class="qcsoc-steps">
		<li>
			<strong><?php echo esc_html( $qcsoc_meta ? __( 'Create a Meta app', 'chatbot' ) : __( 'Create a Meta app with the Threads use case', 'chatbot' ) ); ?></strong>
			<p class="qcsoc-muted"><?php echo esc_html( $qcsoc_meta ? __( 'At developers.facebook.com › My Apps › Create app › type "Business".', 'chatbot' ) : __( 'At developers.facebook.com › My Apps › Create app › use case "Access the Threads API".', 'chatbot' ) ); ?></p>
		</li>
		<li>
			<strong><?php esc_html_e( 'Add this redirect address to the app', 'chatbot' ); ?></strong>
			<p class="qcsoc-muted"><?php echo esc_html( $qcsoc_meta ? __( 'Facebook Login for Business › Settings › Valid OAuth Redirect URIs', 'chatbot' ) : __( 'Threads API › Settings › Redirect Callback URLs', 'chatbot' ) ); ?></p>
			<div class="qcsoc-copy">
				<code data-qcsoc-copy-source><?php echo esc_html( Social_Auth::redirect_uri() ); ?></code>
				<button type="button" class="button" data-qcsoc-copy><span class="dashicons dashicons-admin-page" aria-hidden="true"></span> <?php esc_html_e( 'Copy', 'chatbot' ); ?></button>
			</div>
		</li>
		<li>
			<strong><?php esc_html_e( 'Paste the app\'s ID and secret', 'chatbot' ); ?></strong>
			<p class="qcsoc-muted"><?php esc_html_e( 'App settings › Basic. The secret is encrypted on this site and never shown again.', 'chatbot' ); ?></p>
			<div class="qcsoc-fields">
				<label><?php esc_html_e( 'App ID', 'chatbot' ); ?><input type="text" name="client_id" autocomplete="off" value="<?php echo esc_attr( $qcsoc_app['client_id'] ); ?>"></label>
				<label><?php esc_html_e( 'App secret', 'chatbot' ); ?><input type="password" name="client_secret" autocomplete="new-password" placeholder="<?php echo esc_attr( $qcsoc_app['secret_hint'] ); ?>"></label>
			</div>
			<?php $qcsoc_mismatch = $qcsoc_meta && method_exists( Meta::class, 'channel_app_mismatch' ) ? Meta::channel_app_mismatch() : ''; ?>
			<?php if ( '' !== $qcsoc_mismatch ) : ?>
				<div class="notice notice-warning inline" data-qcsoc-app-mismatch><p>
					<?php
					/* translators: %s: the Meta App ID the chatbot's Messenger channel uses. */
					echo esc_html( sprintf( __( 'Your chatbot\'s Messenger channel uses a different Meta app (App ID %s). In this version WPBot Social and the chatbot channels must use the same Meta app, so enter that App ID and its secret here. Chatbot webhooks keep checking signatures with the channel\'s own app secret.', 'chatbot' ), $qcsoc_mismatch ) );
					?>
				</p></div>
			<?php endif; ?>
			<button type="button" class="button" data-qcsoc-save-app><?php esc_html_e( 'Save app', 'chatbot' ); ?></button>
			<span class="qcsoc-status" data-qcsoc-status role="status"></span>
		</li>
		<?php if ( $qcsoc_meta ) : ?>
		<li>
			<strong><?php esc_html_e( 'Choose what WPBot may do', 'chatbot' ); ?></strong>
			<p class="qcsoc-muted"><?php esc_html_e( 'WPBot asks Meta only for what you turn on.', 'chatbot' ); ?></p>
			<?php
			$qcsoc_chosen = (array) Settings::get( 'scopes' );
			foreach ( Meta_Scopes::options() as $qcsoc_id => $qcsoc_option ) :
				if ( $qcsoc_option['master'] && ! has_action( 'qcld_wpbot_social_pro/loaded' ) ) {
					continue;
				}
				?>
				<label class="qcsoc-check"><input type="checkbox" name="scopes[]" value="<?php echo esc_attr( $qcsoc_id ); ?>" <?php checked( in_array( $qcsoc_id, $qcsoc_chosen, true ) ); ?>> <?php echo esc_html( $qcsoc_option['label'] ); ?> <span class="qcsoc-muted">(<?php echo esc_html( implode( ', ', $qcsoc_option['permissions'] ) ); ?>)</span></label>
			<?php endforeach; ?>
		</li>
		<?php endif; ?>
		<li>
			<strong><?php esc_html_e( 'Connect', 'chatbot' ); ?></strong>
			<p class="qcsoc-muted"><?php echo esc_html( $qcsoc_meta ? __( 'Facebook opens; pick the Pages and Instagram accounts to use.', 'chatbot' ) : __( 'Threads opens; sign in with the profile to post from.', 'chatbot' ) ); ?></p>
			<button type="button" class="button button-primary" data-qcsoc-connect <?php disabled( ! $qcsoc_app['has_secret'] || '' === $qcsoc_app['client_id'] ); ?>><?php echo esc_html( $qcsoc_meta ? __( 'Continue with Facebook', 'chatbot' ) : __( 'Continue with Threads', 'chatbot' ) ); ?></button>
		</li>
	</ol>
<?php elseif ( 'telegram' === $qcsoc_network ) : ?>
	<?php $qcsoc_bot = Connector::telegram_bot_view(); ?>
	<h2><?php esc_html_e( 'Connect a Telegram channel', 'chatbot' ); ?></h2>
	<p class="qcsoc-muted"><?php esc_html_e( 'Posts go out through a Telegram bot that is an admin of your channel. WPBot Social only posts; it does not change the bot\'s settings.', 'chatbot' ); ?></p>
	<ol class="qcsoc-steps">
		<li data-qcsoc-tg-bot>
			<strong><?php esc_html_e( 'Choose the bot', 'chatbot' ); ?></strong>
			<?php if ( 'own' === $qcsoc_bot['source'] ) : ?>
				<p><?php echo esc_html( sprintf( /* translators: 1: bot @username, 2: masked token */ __( 'Social\'s own bot %1$s (token %2$s).', 'chatbot' ), $qcsoc_bot['bot'], $qcsoc_bot['mask'] ) ); ?></p>
				<?php if ( $qcsoc_bot['channels'] > 0 ) : ?>
					<p class="qcsoc-muted"><?php echo esc_html( sprintf( /* translators: %d: number of channels */ _n( '%d channel posts through this bot. Removing or replacing the token means connecting it again.', '%d channels post through this bot. Removing or replacing the token means connecting them again.', (int) $qcsoc_bot['channels'], 'chatbot' ), (int) $qcsoc_bot['channels'] ) ); ?></p>
				<?php endif; ?>
				<button type="button" class="button" data-qcsoc-tg-forget data-qcsoc-channels="<?php echo esc_attr( (string) (int) $qcsoc_bot['channels'] ); ?>"><?php esc_html_e( 'Remove this bot token', 'chatbot' ); ?></button>
			<?php else : ?>
				<?php if ( 'chatbot' === $qcsoc_bot['source'] ) : ?>
					<p><?php esc_html_e( 'Social uses the ChatBot\'s Telegram bot. To post with a different bot, add its token below.', 'chatbot' ); ?></p>
				<?php else : ?>
					<p class="qcsoc-muted"><?php esc_html_e( 'Create a bot with @BotFather in Telegram (/newbot) and paste its token here. The token is stored encrypted and is never shown again.', 'chatbot' ); ?></p>
				<?php endif; ?>
				<div class="qcsoc-fields"><label><?php esc_html_e( 'Bot token', 'chatbot' ); ?><input type="password" name="tg_token" autocomplete="off" spellcheck="false" placeholder="123456789:AA…"></label></div>
				<button type="button" class="button" data-qcsoc-tg-save><?php esc_html_e( 'Save bot token', 'chatbot' ); ?></button>
			<?php endif; ?>
			<span class="qcsoc-status" data-qcsoc-status role="status"></span>
		</li>
		<?php if ( 'none' !== $qcsoc_bot['source'] ) : ?>
			<li><strong><?php esc_html_e( 'Add the bot to your channel as an admin', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'Channel › Administrators › Add admin, with "Post messages" allowed.', 'chatbot' ); ?></p></li>
			<li>
				<strong><?php esc_html_e( 'Enter the channel', 'chatbot' ); ?></strong>
				<div class="qcsoc-fields"><label><?php esc_html_e( 'Channel (@username or numeric id)', 'chatbot' ); ?><input type="text" name="channel" placeholder="@mychannel"></label></div>
				<button type="button" class="button button-primary" data-qcsoc-telegram><?php esc_html_e( 'Connect channel', 'chatbot' ); ?></button>
				<span class="qcsoc-status" data-qcsoc-status role="status"></span>
			</li>
		<?php endif; ?>
	</ol>
<?php else : ?>
	<?php $qcsoc_gbp = \QuantumCloud\Social\Admin\Google_Card::business_profile_on(); ?>
	<h2><?php esc_html_e( 'Connect Google Business Profile', 'chatbot' ); ?></h2>
	<p class="qcsoc-muted"><?php esc_html_e( 'Posts go to your Business Profile locations through your Google connection, set up below. Google gives API access to approved projects only.', 'chatbot' ); ?></p>
	<ol class="qcsoc-steps">
		<li data-qcsoc-gbp-step>
			<strong><?php esc_html_e( 'Turn on Business Profile access', 'chatbot' ); ?></strong>
			<p class="qcsoc-muted">
				<?php
				$qcsoc_links = array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				);
				/* translators: %s: Google's access request page. */
				$qcsoc_text = sprintf( __( 'WPBot then asks Google for permission to manage your Business Profile (Google has no read-only permission for it). Your Google Cloud project needs Google\'s approval first: <a href="%s" target="_blank" rel="noopener noreferrer">request access</a>.', 'chatbot' ), esc_url( \QuantumCloud\WPBotCore\Connections\Business_Profile::ACCESS_URL ) );
				echo wp_kses( $qcsoc_text, $qcsoc_links );
				?>
			</p>
			<p>
				<?php if ( $qcsoc_gbp ) : ?>
					<span class="qcsoc-pill qcsoc-pill--ok"><?php esc_html_e( 'On', 'chatbot' ); ?></span>
				<?php else : ?>
					<button type="button" class="button" data-qcsoc-gbp="on"><?php esc_html_e( 'Turn on', 'chatbot' ); ?></button>
				<?php endif; ?>
				<span class="qcsoc-status" data-qcsoc-status role="status"></span>
			</p>
		</li>
		<li>
			<strong><?php esc_html_e( 'Connect Google', 'chatbot' ); ?></strong>
			<?php do_action( 'qcld_wpbot_social/google_card', 'business' ); ?>
		</li>
		<li data-qcsoc-google>
			<strong><?php esc_html_e( 'Add your locations', 'chatbot' ); ?></strong>
			<p><button type="button" class="button" data-qcsoc-google-load><?php esc_html_e( 'Show my locations', 'chatbot' ); ?></button>
			<span class="qcsoc-status" data-qcsoc-status role="status"></span></p>
			<ul class="qcsoc-list" data-qcsoc-google-list></ul>
		</li>
	</ol>
<?php endif; ?>
</section>
<aside class="qcsoc-card qcsoc-card--side">
	<h3><span class="dashicons dashicons-info" aria-hidden="true"></span> <?php esc_html_e( 'Good to know', 'chatbot' ); ?></h3>
	<ul class="qcsoc-bullets">
		<?php if ( 'meta' === $qcsoc_network || 'threads' === $qcsoc_network ) : ?>
			<li><?php esc_html_e( 'In Development mode the app works for you and people you add as app admins or testers. That is enough for your own Pages.', 'chatbot' ); ?></li>
			<li><?php esc_html_e( 'Meta\'s App Review is only needed if people outside your team will connect through this app.', 'chatbot' ); ?></li>
		<?php endif; ?>
		<?php if ( 'meta' === $qcsoc_network ) : ?>
			<li><?php esc_html_e( 'Instagram must be a Business or Creator account linked to a Facebook Page.', 'chatbot' ); ?></li>
		<?php endif; ?>
		<li><?php esc_html_e( 'Instagram and Threads download images from your site, so the site must be reachable from the internet (not a local or password-protected staging site).', 'chatbot' ); ?></li>
		<li><?php esc_html_e( 'Your app\'s ID and secret are kept on this site. Nothing passes through QuantumCloud.', 'chatbot' ); ?></li>
	</ul>
</aside>
</div>
