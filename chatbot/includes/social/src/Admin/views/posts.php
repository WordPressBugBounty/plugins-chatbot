<?php
/**
 * Posts (screen 12): every post with its per-account status, "Fix & retry"
 * for failures and "It went out" for posts the owner must check.
 *
 * @package QuantumCloud\Social
 */

use QuantumCloud\Social\Admin\Admin_Controller;
use QuantumCloud\Social\Posts\Post_Store;
use QuantumCloud\Social\Rest\Posts_Controller;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
$qcsoc_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$qcsoc_search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$qcsoc_paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
// phpcs:enable
$qcsoc_result  = Post_Store::query(
	array(
		'status' => $qcsoc_status,
		'search' => $qcsoc_search,
		'brands' => \QuantumCloud\Social\Support\Brand_Access::allowed(),
		'limit'  => 20,
		'offset' => ( $qcsoc_paged - 1 ) * 20,
	)
);
$qcsoc_publish = current_user_can( 'wpbot_social_publish' );
$qcsoc_labels  = array(
	''          => __( 'All', 'chatbot' ),
	'scheduled' => __( 'Scheduled', 'chatbot' ),
	'published' => __( 'Published', 'chatbot' ),
	'partial'   => __( 'Partly published', 'chatbot' ),
	'failed'    => __( 'Failed', 'chatbot' ),
	'missed'    => __( 'Missed', 'chatbot' ),
	'skipped'   => __( 'Skipped', 'chatbot' ),
	'draft'     => __( 'Drafts', 'chatbot' ),
);
$qcsoc_states  = array(
	'draft'       => array( '', __( 'Draft', 'chatbot' ) ),
	'scheduled'   => array( '', __( 'Scheduled', 'chatbot' ) ),
	'queued'      => array( 'warn', __( 'Publishing…', 'chatbot' ) ),
	'sending'     => array( 'warn', __( 'Publishing…', 'chatbot' ) ),
	'retry'       => array( 'warn', __( 'Will retry', 'chatbot' ) ),
	'unknown'     => array( 'warn', __( 'Checking', 'chatbot' ) ),
	'reconciling' => array( 'warn', __( 'Checking', 'chatbot' ) ),
	'done'        => array( 'ok', __( 'Published', 'chatbot' ) ),
	'failed'      => array( 'bad', __( 'Failed', 'chatbot' ) ),
	'check'       => array( 'bad', __( 'Check and confirm', 'chatbot' ) ),
	'missed'      => array( 'bad', __( 'Missed', 'chatbot' ) ),
	'manual'      => array( 'warn', __( 'Post yourself', 'chatbot' ) ),
	'skipped'     => array( '', __( 'Skipped', 'chatbot' ) ),
);
?>
<?php
$qcsoc_sources = array(
	'ai_writer'  => __( 'From AI Writer', 'chatbot' ),
	'auto_share' => __( 'Auto-share', 'chatbot' ),
	'ai'         => __( 'Written with AI', 'chatbot' ),
	'automator'  => __( 'From Automator', 'chatbot' ),
);
?>
<?php $qcsoc_tiles = \QuantumCloud\Social\Posts\Overview::current( \QuantumCloud\Social\Support\Brand_Access::allowed() ); ?>
<?php if ( null !== $qcsoc_tiles ) : ?>
<section class="qcsoc-tiles" data-qcsoc="overview" aria-label="<?php esc_attr_e( 'Overview', 'chatbot' ); ?>">
	<?php
	$qcsoc_tile_items = array(
		array( $qcsoc_tiles['published'], __( 'Published in the last 30 days', 'chatbot' ), 'published' ),
		array( $qcsoc_tiles['scheduled'], __( 'Scheduled', 'chatbot' ), 'scheduled' ),
		array( $qcsoc_tiles['manual'], __( 'Waiting for you to post', 'chatbot' ), '' ),
		array( $qcsoc_tiles['attention'], __( 'Need attention', 'chatbot' ), 'failed' ),
	);
	foreach ( $qcsoc_tile_items as $qcsoc_tile ) :
		$qcsoc_tile_url = '' === $qcsoc_tile[2] ? Admin_Controller::url( 'manual' ) : Admin_Controller::url( 'posts', array( 'status' => $qcsoc_tile[2] ) );
		?>
		<a class="qcsoc-tile<?php echo ( 'failed' === $qcsoc_tile[2] && $qcsoc_tile[0] > 0 ) ? ' is-bad' : ''; ?>" href="<?php echo esc_url( $qcsoc_tile_url ); ?>"><strong><?php echo esc_html( number_format_i18n( (int) $qcsoc_tile[0] ) ); ?></strong><span><?php echo esc_html( $qcsoc_tile[1] ); ?></span></a>
	<?php endforeach; ?>
	<?php if ( array() !== $qcsoc_tiles['networks'] ) : ?>
		<p class="qcsoc-tiles__networks qcsoc-muted">
			<?php
			$qcsoc_net_parts = array();
			foreach ( $qcsoc_tiles['networks'] as $qcsoc_net => $qcsoc_net_n ) {
				$qcsoc_net_parts[] = Admin_Controller::network_label( $qcsoc_net ) . ' ' . number_format_i18n( (int) $qcsoc_net_n );
			}
			echo esc_html( implode( ' · ', $qcsoc_net_parts ) );
			?>
		</p>
	<?php endif; ?>
</section>
<?php endif; ?>
<section class="qcsoc-card" data-qcsoc="posts">
	<div class="qcsoc-card__head">
		<h2><?php esc_html_e( 'Posts', 'chatbot' ); ?></h2>
		<?php $qcsoc_manual_n = \QuantumCloud\Social\Posts\Target_Store::manual_count(); ?>
		<a class="button" href="<?php echo esc_url( Admin_Controller::url( 'manual' ) ); ?>"><?php esc_html_e( 'Post yourself', 'chatbot' ); ?><?php echo $qcsoc_manual_n ? ' <span class="qcsoc-badge">' . esc_html( (string) $qcsoc_manual_n ) . '</span>' : ''; ?></a>
		<?php if ( current_user_can( 'wpbot_social_use' ) ) : ?>
			<a class="button button-primary" href="<?php echo esc_url( Admin_Controller::url( 'write' ) ); ?>"><?php esc_html_e( 'Create post', 'chatbot' ); ?></a>
		<?php endif; ?>
	</div>
	<nav class="qcsoc-tabs" aria-label="<?php esc_attr_e( 'Filter posts', 'chatbot' ); ?>">
		<?php foreach ( $qcsoc_labels as $qcsoc_key => $qcsoc_label ) : ?>
			<a href="<?php echo esc_url( Admin_Controller::url( 'posts', '' === $qcsoc_key ? array() : array( 'status' => $qcsoc_key ) ) ); ?>" class="<?php echo $qcsoc_key === $qcsoc_status ? 'is-active' : ''; ?>"><?php echo esc_html( $qcsoc_label ); ?></a>
		<?php endforeach; ?>
	</nav>
	<form method="get" class="qcsoc-search">
		<input type="hidden" name="page" value="wpbot-social"><input type="hidden" name="view" value="posts">
		<label class="screen-reader-text" for="qcsoc-s"><?php esc_html_e( 'Search posts', 'chatbot' ); ?></label>
		<input type="search" id="qcsoc-s" name="s" value="<?php echo esc_attr( $qcsoc_search ); ?>"> <button class="button"><?php esc_html_e( 'Search', 'chatbot' ); ?></button>
	</form>
	<?php if ( array() === $qcsoc_result['items'] ) : ?>
		<p class="qcsoc-empty qcsoc-muted"><?php esc_html_e( 'No posts here yet.', 'chatbot' ); ?></p>
	<?php else : ?>
		<table class="qcsoc-table">
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Post', 'chatbot' ); ?></th>
				<th scope="col"><?php esc_html_e( 'When', 'chatbot' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Accounts', 'chatbot' ); ?></th>
				<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'chatbot' ); ?></span></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $qcsoc_result['items'] as $qcsoc_raw ) : ?>
				<?php $qcsoc_post = Posts_Controller::post_view( $qcsoc_raw ); ?>
				<tr data-post="<?php echo esc_attr( (string) $qcsoc_post['id'] ); ?>">
					<td><strong><?php echo esc_html( '' !== $qcsoc_post['title'] ? $qcsoc_post['title'] : wp_trim_words( $qcsoc_post['text'], 10 ) ); ?></strong><?php echo isset( $qcsoc_sources[ $qcsoc_post['source_type'] ] ) ? ' <span class="qcsoc-pill">' . esc_html( $qcsoc_sources[ $qcsoc_post['source_type'] ] ) . '</span>' : ''; ?><br><span class="qcsoc-muted"><?php echo esc_html( wp_trim_words( $qcsoc_post['text'], 18 ) ); ?></span></td>
					<td class="qcsoc-muted"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $qcsoc_post['schedule'] ) ); ?></td>
					<td>
						<?php foreach ( $qcsoc_post['targets'] as $qcsoc_target ) : ?>
							<?php $qcsoc_state = isset( $qcsoc_states[ $qcsoc_target['state'] ] ) ? $qcsoc_states[ $qcsoc_target['state'] ] : array( '', $qcsoc_target['state'] ); ?>
							<div class="qcsoc-target" data-target="<?php echo esc_attr( (string) $qcsoc_target['id'] ); ?>">
								<?php echo esc_html( Admin_Controller::network_label( $qcsoc_target['network'] ) . ' · ' . $qcsoc_target['account'] ); ?>
								<span class="qcsoc-pill<?php echo '' !== $qcsoc_state[0] ? ' qcsoc-pill--' . esc_attr( $qcsoc_state[0] ) : ''; ?>"><?php echo esc_html( $qcsoc_state[1] ); ?></span>
								<?php if ( '' !== $qcsoc_target['remote_url'] && 'done' === $qcsoc_target['state'] ) : ?>
									<a href="<?php echo esc_url( $qcsoc_target['remote_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'chatbot' ); ?></a>
								<?php endif; ?>
								<?php if ( in_array( $qcsoc_target['state'], array( 'failed', 'check', 'retry', 'missed' ), true ) && '' !== $qcsoc_target['error_message'] ) : ?>
									<div class="qcsoc-muted"><?php echo esc_html( $qcsoc_target['error_message'] ); ?><?php echo 'retry' === $qcsoc_target['state'] && '' !== $qcsoc_target['next_attempt'] ? ' · ' . esc_html( $qcsoc_target['next_attempt'] ) : ''; ?></div>
								<?php endif; ?>
								<?php if ( $qcsoc_publish && in_array( $qcsoc_target['state'], array( 'failed', 'check' ), true ) ) : ?>
									<button type="button" class="button-link" data-qcsoc-target-action="retry"><?php esc_html_e( 'Fix & retry', 'chatbot' ); ?></button>
								<?php endif; ?>
								<?php if ( $qcsoc_publish && 'missed' === $qcsoc_target['state'] ) : ?>
									<button type="button" class="button-link" data-qcsoc-target-action="post_now"><?php esc_html_e( 'Post now', 'chatbot' ); ?></button>
									<button type="button" class="button-link" data-qcsoc-target-action="reschedule"><?php esc_html_e( 'Reschedule', 'chatbot' ); ?></button>
									<button type="button" class="button-link" data-qcsoc-target-action="skip"><?php esc_html_e( 'Skip', 'chatbot' ); ?></button>
								<?php endif; ?>
								<?php if ( $qcsoc_publish && 'check' === $qcsoc_target['state'] ) : ?>
									<button type="button" class="button-link" data-qcsoc-target-action="posted"><?php esc_html_e( 'It went out', 'chatbot' ); ?></button>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</td>
					<td class="qcsoc-right">
						<?php if ( 'draft' === $qcsoc_post['status'] && current_user_can( 'wpbot_social_use' ) ) : ?>
							<a class="button" href="<?php echo esc_url( Admin_Controller::url( 'compose', array( 'post' => $qcsoc_post['id'] ) ) ); ?>"><?php esc_html_e( 'Open', 'chatbot' ); ?></a>
						<?php endif; ?>
						<?php if ( $qcsoc_publish && ! in_array( $qcsoc_post['status'], array( 'publishing' ), true ) ) : ?>
							<button type="button" class="button-link qcsoc-danger" data-qcsoc-post-delete><?php esc_html_e( 'Delete', 'chatbot' ); ?></button>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $qcsoc_result['total'] > 20 ) : ?>
			<p class="qcsoc-pages">
				<?php
				if ( $qcsoc_paged > 1 ) :
					?>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $qcsoc_paged - 1 ) ); ?>">‹</a><?php endif; ?>
				<?php
				if ( $qcsoc_paged * 20 < $qcsoc_result['total'] ) :
					?>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $qcsoc_paged + 1 ) ); ?>">›</a><?php endif; ?>
			</p>
		<?php endif; ?>
	<?php endif; ?>
	<span class="qcsoc-status" data-qcsoc-status role="status"></span>
</section>
