<?php
/**
 * Create post (screen 07). The browser builds the form; the server checks
 * every network's rules (POST /posts/check) and saves (POST /posts).
 *
 * @package QuantumCloud\Social
 */

use QuantumCloud\Social\Accounts\Account_Store;
use QuantumCloud\Social\Admin\Admin_Controller;
use QuantumCloud\Social\Posts\Composer;
use QuantumCloud\Social\Posts\Post_Store;
use QuantumCloud\Social\Settings;

defined( 'ABSPATH' ) || exit;

$qcsoc_accounts = array_values(
	array_filter(
		Account_Store::all(),
		static function ( $account ) {
			return 'ok' === $account['status'];
		}
	)
);
$qcsoc_default  = gmdate( 'Y-m-d', current_time( 'timestamp' ) + DAY_IN_SECONDS ) . 'T' . (string) Settings::get( 'default_time' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Site-time default for a datetime-local input.
$qcsoc_publish  = current_user_can( 'wpbot_social_publish' );
// Reopen a draft (from Write with AI, AI Writer or the Posts screen).
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: loads a draft into the form.
$qcsoc_draft   = isset( $_GET['post'] ) ? Post_Store::get( absint( wp_unslash( $_GET['post'] ) ) ) : null;
$qcsoc_draft   = $qcsoc_draft && \QuantumCloud\Social\Support\Brand_Access::can( (int) $qcsoc_draft['brand_id'] ) ? $qcsoc_draft : null;
$qcsoc_prefill = $qcsoc_draft && 'draft' === $qcsoc_draft['status'] ? Composer::prefill( $qcsoc_draft ) : null;
?>
<?php if ( array() === $qcsoc_accounts ) : ?>
	<section class="qcsoc-card">
		<h2><?php esc_html_e( 'Create post', 'chatbot' ); ?></h2>
		<p><?php esc_html_e( 'Connect an account first.', 'chatbot' ); ?> <a href="<?php echo esc_url( Admin_Controller::url( 'accounts' ) ); ?>"><?php esc_html_e( 'Go to Accounts', 'chatbot' ); ?></a></p>
	</section>
	<?php
	return;
endif;
?>
<div class="qcsoc-columns" data-qcsoc="compose" data-prefill="<?php echo esc_attr( $qcsoc_prefill ? (string) wp_json_encode( $qcsoc_prefill ) : '' ); ?>" data-accounts="
<?php
echo esc_attr(
	(string) wp_json_encode(
		array_map(
			static function ( $a ) {
				return array(
					'id'      => $a['id'],
					'network' => $a['network'],
					'name'    => $a['name'],
				);
			},
			$qcsoc_accounts
		)
	)
);
?>
"
<?php
/**
 * Formats a network supports beyond one post (the Master module adds them): network => [ 'thread', 'first_comment' ].
 *
 * @param array<string,string[]> $formats Formats.
 */
$qcsoc_formats = (array) apply_filters( 'qcld_wpbot_social/formats', array() );
?>
data-formats="<?php echo esc_attr( (string) wp_json_encode( (object) $qcsoc_formats ) ); ?>">
	<section class="qcsoc-card qcsoc-card--main">
		<h2><?php esc_html_e( 'Create post', 'chatbot' ); ?></h2>
		<?php do_action( 'qcld_wpbot_social/compose_source' ); ?>
		<h3><?php esc_html_e( 'Post to', 'chatbot' ); ?></h3>
		<div class="qcsoc-chips" role="group" aria-label="<?php esc_attr_e( 'Accounts', 'chatbot' ); ?>">
			<?php foreach ( $qcsoc_accounts as $qcsoc_account ) : ?>
				<label class="qcsoc-chip"><input type="checkbox" name="accounts[]" value="<?php echo esc_attr( (string) $qcsoc_account['id'] ); ?>" data-network="<?php echo esc_attr( $qcsoc_account['network'] ); ?>"> <span class="qcsoc-chip__net"><?php echo esc_html( Admin_Controller::network_label( $qcsoc_account['network'] ) ); ?></span> <?php echo esc_html( $qcsoc_account['name'] ); ?></label>
			<?php endforeach; ?>
		</div>
		<nav class="qcsoc-tabs" data-qcsoc-tabs aria-label="<?php esc_attr_e( 'Text per network', 'chatbot' ); ?>">
			<a href="#" class="is-active" data-tab=""><?php esc_html_e( 'All networks', 'chatbot' ); ?></a>
		</nav>
		<label class="screen-reader-text" for="qcsoc-text"><?php esc_html_e( 'Post text', 'chatbot' ); ?></label>
		<textarea id="qcsoc-text" class="qcsoc-textarea" rows="8" data-qcsoc-text placeholder="<?php esc_attr_e( 'What do you want to say?', 'chatbot' ); ?>"></textarea>
		<div class="qcsoc-under">
			<span class="qcsoc-muted" data-qcsoc-tabhint></span>
			<span class="qcsoc-muted" data-qcsoc-count>0</span>
		</div>
		<div class="qcsoc-formats" data-qcsoc-formats hidden>
			<label data-qcsoc-format="thread"><?php esc_html_e( 'Continue as a thread (one follow-up post per paragraph; leave a blank line between them)', 'chatbot' ); ?>
				<textarea name="thread_posts" rows="4" maxlength="12000"></textarea>
			</label>
			<label data-qcsoc-format="format"><?php esc_html_e( 'Instagram format', 'chatbot' ); ?>
				<select name="ig_format">
					<option value="feed"><?php esc_html_e( 'Feed post (a video becomes a Reel)', 'chatbot' ); ?></option>
					<option value="reel"><?php esc_html_e( 'Reel', 'chatbot' ); ?></option>
					<option value="story"><?php esc_html_e( 'Story (no caption; disappears after 24 hours)', 'chatbot' ); ?></option>
				</select>
			</label>
			<label data-qcsoc-format="privacy"><?php esc_html_e( 'YouTube visibility', 'chatbot' ); ?>
				<select name="yt_privacy">
					<option value="private"><?php esc_html_e( 'Private (you publish it in YouTube Studio)', 'chatbot' ); ?></option>
					<option value="unlisted"><?php esc_html_e( 'Unlisted', 'chatbot' ); ?></option>
					<option value="public"><?php esc_html_e( 'Public', 'chatbot' ); ?></option>
				</select>
			</label>
			<label data-qcsoc-format="first_comment"><?php esc_html_e( 'First comment (posted right after the post, for example with the link or hashtags)', 'chatbot' ); ?>
				<textarea name="first_comment" rows="2" maxlength="2200"></textarea>
			</label>
		</div>
		<div class="qcsoc-fields">
			<label><?php esc_html_e( 'Title (for your list and the campaign tag)', 'chatbot' ); ?><input type="text" name="title" maxlength="191"></label>
			<label><?php esc_html_e( 'Link', 'chatbot' ); ?><input type="url" name="link" placeholder="https://"></label>
		</div>
		<p class="qcsoc-muted"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span> <?php echo esc_html( \QuantumCloud\Social\Module::MODE_MASTER === \QuantumCloud\Social\Module::instance()->mode() ? __( 'Links to this site get tracking tags (utm_source = the network) so Analytics can show which posts brought visitors.', 'chatbot' ) : __( 'Links to this site get tracking tags (utm_source = the network) so your analytics tool, for example Google Analytics, can show which posts brought visitors.', 'chatbot' ) ); ?></p>
		<?php do_action( 'qcld_wpbot_social/compose_tools' ); ?>
		<h3><?php esc_html_e( 'Media', 'chatbot' ); ?></h3>
		<?php
		$qcsoc_ai_images = \QuantumCloud\Social\Media\Ai_Images::view();
		$qcsoc_sources   = get_posts(
			array(
				'post_type'      => array_values( array_filter( array( 'post', 'page', post_type_exists( 'product' ) ? 'product' : '' ) ) ),
				'post_status'    => 'publish',
				'posts_per_page' => 40,
				'has_password'   => false,
			)
		);
		?>
		<div class="qcsoc-media" data-qcsoc-media>
			<button type="button" class="qcsoc-media__add" data-qcsoc-media-add><span class="dashicons dashicons-plus" aria-hidden="true"></span> <?php esc_html_e( 'Media Library or upload', 'chatbot' ); ?></button>
			<?php if ( array() !== (array) apply_filters( 'qcld_wpbot_social/video_networks', array() ) ) : ?>
				<div class="qcsoc-video" data-qcsoc-video>
					<button type="button" class="button" data-qcsoc-video-add><span class="dashicons dashicons-video-alt3" aria-hidden="true"></span> <?php esc_html_e( 'Add a video', 'chatbot' ); ?></button>
					<span class="qcsoc-muted" data-qcsoc-video-name></span>
					<button type="button" class="button-link" data-qcsoc-video-remove hidden><?php esc_html_e( 'Remove video', 'chatbot' ); ?></button>
					<p class="qcsoc-muted"><?php esc_html_e( 'Vertical MP4, up to 3 minutes, for Reels, YouTube Shorts and TikTok. Networks that take no video use the images instead.', 'chatbot' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<div class="qcsoc-media-tools">
			<details data-qcsoc-from-post>
				<summary><span class="dashicons dashicons-media-document" aria-hidden="true"></span> <?php esc_html_e( 'From a post or product', 'chatbot' ); ?></summary>
				<label class="screen-reader-text" for="qcsoc-from-post"><?php esc_html_e( 'Post or product', 'chatbot' ); ?></label>
				<select id="qcsoc-from-post">
					<option value=""><?php esc_html_e( 'Choose…', 'chatbot' ); ?></option>
					<?php foreach ( $qcsoc_sources as $qcsoc_src ) : ?>
						<option value="<?php echo esc_attr( (string) $qcsoc_src->ID ); ?>"><?php echo esc_html( get_the_title( $qcsoc_src ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<div class="qcsoc-media__picks" data-qcsoc-from-post-images></div>
			</details>
			<details data-qcsoc-ai-image>
				<summary><span class="dashicons dashicons-art" aria-hidden="true"></span> <?php esc_html_e( 'Create with AI', 'chatbot' ); ?></summary>
				<?php if ( $qcsoc_ai_images['available'] ) : ?>
					<label for="qcsoc-ai-prompt"><?php esc_html_e( 'Describe the image', 'chatbot' ); ?></label>
					<textarea id="qcsoc-ai-prompt" rows="3" maxlength="1000"></textarea>
					<div class="qcsoc-fields">
						<?php if ( $qcsoc_ai_images['styles'] ) : ?>
							<label><?php esc_html_e( 'Style', 'chatbot' ); ?>
								<select data-qcsoc-ai-style>
								<?php
								foreach ( $qcsoc_ai_images['styles'] as $qcsoc_style ) :
									?>
									<option value="<?php echo esc_attr( is_array( $qcsoc_style ) ? (string) $qcsoc_style['id'] : (string) $qcsoc_style ); ?>"><?php echo esc_html( is_array( $qcsoc_style ) ? (string) $qcsoc_style['label'] : (string) $qcsoc_style ); ?></option><?php endforeach; ?></select>
							</label>
						<?php endif; ?>
					</div>
					<button type="button" class="button" data-qcsoc-ai-create><?php esc_html_e( 'Create image', 'chatbot' ); ?></button>
					<div class="qcsoc-media__picks" data-qcsoc-ai-images></div>
				<?php else : ?>
					<p class="qcsoc-muted"><?php echo esc_html( $qcsoc_ai_images['reason'] ); ?></p>
				<?php endif; ?>
			</details>
			<p class="qcsoc-muted"><?php esc_html_e( 'Images outside a network\'s shape (Instagram 4:5 to 1.91:1, Threads) get a centred copy at the right size; the original is kept.', 'chatbot' ); ?></p>
		</div>
		<div data-qcsoc-gbp hidden>
			<h3><?php esc_html_e( 'Google Business Profile', 'chatbot' ); ?></h3>
			<div class="qcsoc-fields">
				<label><?php esc_html_e( 'Post type', 'chatbot' ); ?>
					<select name="gbp_type">
						<option value="update"><?php esc_html_e( 'Update', 'chatbot' ); ?></option>
						<option value="offer"><?php esc_html_e( 'Offer', 'chatbot' ); ?></option>
						<option value="event"><?php esc_html_e( 'Event', 'chatbot' ); ?></option>
					</select>
				</label>
				<label data-gbp-extra><?php esc_html_e( 'Title', 'chatbot' ); ?><input type="text" name="gbp_title" maxlength="58"></label>
				<label data-gbp-extra><?php esc_html_e( 'Starts', 'chatbot' ); ?><input type="date" name="gbp_start"></label>
				<label data-gbp-extra><?php esc_html_e( 'Ends', 'chatbot' ); ?><input type="date" name="gbp_end"></label>
				<label data-gbp-offer><?php esc_html_e( 'Coupon code', 'chatbot' ); ?><input type="text" name="gbp_coupon" maxlength="58"></label>
				<label data-gbp-offer><?php esc_html_e( 'Terms', 'chatbot' ); ?><input type="text" name="gbp_terms" maxlength="300"></label>
			</div>
			<p class="qcsoc-muted"><?php esc_html_e( 'Links are not clickable in Google post text; WPBot adds a "Learn more" button with the link instead.', 'chatbot' ); ?></p>
		</div>
		<h3><?php esc_html_e( 'When', 'chatbot' ); ?></h3>
		<div class="qcsoc-when">
			<label class="qcsoc-chip"><input type="radio" name="when" value="schedule" checked> <?php esc_html_e( 'Schedule', 'chatbot' ); ?></label>
			<label class="qcsoc-chip"><input type="radio" name="when" value="now"> <?php esc_html_e( 'Now', 'chatbot' ); ?></label>
			<?php do_action( 'qcld_wpbot_social/compose_when' ); ?>
			<label><span class="screen-reader-text"><?php esc_html_e( 'Date and time', 'chatbot' ); ?></span><input type="datetime-local" name="schedule" value="<?php echo esc_attr( $qcsoc_default ); ?>"></label>
			<span class="qcsoc-muted"><?php esc_html_e( 'site time', 'chatbot' ); ?> (<?php echo esc_html( wp_timezone_string() ); ?>)</span>
		</div>
		<div class="qcsoc-footer">
			<button type="button" class="button" data-qcsoc-save="draft"><?php esc_html_e( 'Save draft', 'chatbot' ); ?></button>
			<?php do_action( 'qcld_wpbot_social/compose_buttons' ); ?>
			<?php if ( $qcsoc_publish ) : ?>
				<button type="button" class="button button-primary" data-qcsoc-save="scheduled" disabled><?php esc_html_e( 'Schedule', 'chatbot' ); ?></button>
			<?php endif; ?>
		</div>
		<span class="qcsoc-status" data-qcsoc-status role="status"></span>
	</section>
	<aside class="qcsoc-card--side">
		<section class="qcsoc-card">
			<h3><?php esc_html_e( 'Preview', 'chatbot' ); ?></h3>
			<div class="qcsoc-preview" data-qcsoc-preview><p class="qcsoc-muted"><?php esc_html_e( 'Choose an account to see the preview.', 'chatbot' ); ?></p></div>
		</section>
		<section class="qcsoc-card">
			<h3><?php esc_html_e( 'Checks before scheduling', 'chatbot' ); ?></h3>
			<ul class="qcsoc-checks" data-qcsoc-checks><li class="qcsoc-muted"><?php esc_html_e( 'Choose accounts and write your post.', 'chatbot' ); ?></li></ul>
			<div class="notice notice-warning inline" data-qcsoc-flags-wrap hidden><p><strong><?php esc_html_e( 'Honesty check: please confirm these before scheduling', 'chatbot' ); ?></strong></p><ul data-qcsoc-flags></ul></div>
		</section>
	</aside>
</div>
