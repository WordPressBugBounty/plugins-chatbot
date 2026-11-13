<?php
/**
 * Create post › Write with AI (screen 05). The server writes one version per
 * network (POST /write) after showing the cost (POST /write/estimate); the
 * chosen versions open in Create post as a draft, never scheduled directly.
 *
 * @package QuantumCloud\Social
 */

use QuantumCloud\Social\Accounts\Account_Store;
use QuantumCloud\Social\Admin\Admin_Controller;
use QuantumCloud\Social\Writing\Network_Rules;
use QuantumCloud\Social\Writing\Writer;
use QuantumCloud\Social\Writing\Writing_Settings;

defined( 'ABSPATH' ) || exit;

$qcsoc_rules     = Network_Rules::defaults();
$qcsoc_connected = array();
foreach ( Account_Store::all() as $qcsoc_account ) {
	if ( 'ok' === $qcsoc_account['status'] ) {
		$qcsoc_connected[ $qcsoc_account['network'] ][] = array(
			'id'   => (int) $qcsoc_account['id'],
			'name' => $qcsoc_account['name'],
		);
	}
}
$qcsoc_multi = Writer::multi();
$qcsoc_model = Writer::model();
$qcsoc_wset  = Writing_Settings::all();
$qcsoc_posts = get_posts(
	array(
		'post_type'      => array( 'post', 'page' ),
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'has_password'   => false,
	)
);
$qcsoc_woo   = function_exists( 'wc_get_products' );
$qcsoc_prods = $qcsoc_woo ? wc_get_products(
	array(
		'status' => 'publish',
		'limit'  => 50,
	)
) : array();
$qcsoc_kinds = array(
	'idea'    => array( __( 'An idea', 'chatbot' ), 'dashicons-lightbulb' ),
	'post'    => array( __( 'A post or page', 'chatbot' ), 'dashicons-media-document' ),
	'product' => array( __( 'A product', 'chatbot' ), 'dashicons-cart' ),
	'url'     => array( __( 'A web address', 'chatbot' ), 'dashicons-admin-links' ),
	'event'   => array( __( 'An event or offer', 'chatbot' ), 'dashicons-calendar' ),
	'blank'   => array( __( 'Blank', 'chatbot' ), 'dashicons-edit' ),
);
// Prefill from a link (for example "Write posts from this angle" on Trends): kind, about, link, product_id, goal.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only prefill of empty form fields; nothing is saved.
$qcsoc_pre = array(
	'kind'       => isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : 'idea',
	'about'      => isset( $_GET['about'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_GET['about'] ) ), 0, 3000 ) : '',
	'link'       => isset( $_GET['link'] ) ? esc_url_raw( wp_unslash( $_GET['link'] ) ) : '',
	'product_id' => isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0,
	'goal'       => isset( $_GET['goal'] ) ? sanitize_key( wp_unslash( $_GET['goal'] ) ) : '',
);
// phpcs:enable WordPress.Security.NonceVerification.Recommended
if ( $qcsoc_woo && $qcsoc_pre['product_id'] && ! in_array(
	$qcsoc_pre['product_id'],
	array_map(
		static function ( $p ) {
			return $p->get_id(); },
		$qcsoc_prods
	),
	true
) ) {
	$qcsoc_extra = wc_get_product( $qcsoc_pre['product_id'] );
	if ( $qcsoc_extra && 'publish' === $qcsoc_extra->get_status() ) {
		array_unshift( $qcsoc_prods, $qcsoc_extra );
	}
}
if ( ! isset( $qcsoc_kinds[ $qcsoc_pre['kind'] ] ) || ( 'product' === $qcsoc_pre['kind'] && ! $qcsoc_woo ) ) {
	$qcsoc_pre['kind'] = 'idea';
}
?>
<nav class="qcsoc-tabs qcsoc-tabs--page" aria-label="<?php esc_attr_e( 'Create post', 'chatbot' ); ?>">
	<a class="is-active" aria-current="page" href="<?php echo esc_url( Admin_Controller::url( 'write' ) ); ?>"><?php esc_html_e( 'Write with AI', 'chatbot' ); ?></a>
	<a href="<?php echo esc_url( Admin_Controller::url( 'compose' ) ); ?>"><?php esc_html_e( 'Write it yourself', 'chatbot' ); ?></a>
	<?php do_action( 'qcld_wpbot_social/create_tabs', 'write' ); ?>
</nav>
<div class="qcsoc-columns qcsoc-write" data-qcsoc="write" data-multi="<?php echo $qcsoc_multi ? '1' : '0'; ?>" data-show-cost="<?php echo $qcsoc_wset['show_cost'] ? '1' : '0'; ?>" data-connected="<?php echo esc_attr( (string) wp_json_encode( (object) $qcsoc_connected ) ); ?>">
	<section class="qcsoc-card">
		<h2><?php esc_html_e( 'Create post', 'chatbot' ); ?></h2>
		<p class="qcsoc-muted"><?php esc_html_e( 'Start from anything. WPBot writes a version for each network.', 'chatbot' ); ?></p>
		<?php if ( '' === $qcsoc_model['provider'] ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'No AI provider is turned on for Social. Add a key in AI Settings, or choose "Blank" and write yourself.', 'chatbot' ); ?></p></div>
		<?php endif; ?>
		<fieldset class="qcsoc-field">
			<legend><strong><?php esc_html_e( 'Start from', 'chatbot' ); ?></strong></legend>
			<div class="qcsoc-kinds">
				<?php foreach ( $qcsoc_kinds as $qcsoc_kind => $qcsoc_meta ) : ?>
					<?php
					if ( 'product' === $qcsoc_kind && ! $qcsoc_woo ) {
						continue;
					}
					?>
					<label class="qcsoc-kind"><input type="radio" name="kind" value="<?php echo esc_attr( $qcsoc_kind ); ?>" <?php checked( $qcsoc_pre['kind'], $qcsoc_kind ); ?>> <span class="dashicons <?php echo esc_attr( $qcsoc_meta[1] ); ?>" aria-hidden="true"></span> <?php echo esc_html( $qcsoc_meta[0] ); ?></label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<div class="qcsoc-field" data-kind-show="post">
			<label for="qcsoc-w-post"><strong><?php esc_html_e( 'Post or page', 'chatbot' ); ?></strong></label>
			<select id="qcsoc-w-post" name="post_id">
				<?php foreach ( $qcsoc_posts as $qcsoc_p ) : ?>
					<option value="<?php echo esc_attr( (string) $qcsoc_p->ID ); ?>"><?php echo esc_html( get_the_title( $qcsoc_p ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php if ( $qcsoc_woo ) : ?>
			<div class="qcsoc-field" data-kind-show="product">
				<label for="qcsoc-w-product"><strong><?php esc_html_e( 'Product', 'chatbot' ); ?></strong></label>
				<select id="qcsoc-w-product" name="product_id">
					<?php foreach ( $qcsoc_prods as $qcsoc_prod ) : ?>
						<option value="<?php echo esc_attr( (string) $qcsoc_prod->get_id() ); ?>" <?php selected( $qcsoc_pre['product_id'], $qcsoc_prod->get_id() ); ?>><?php echo esc_html( $qcsoc_prod->get_name() ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>
		<div class="qcsoc-field" data-kind-show="url">
			<label for="qcsoc-w-url"><strong><?php esc_html_e( 'Web address', 'chatbot' ); ?></strong></label>
			<input type="url" id="qcsoc-w-url" name="url" placeholder="https://">
		</div>
		<div class="qcsoc-field qcsoc-fields" data-kind-show="event">
			<label><?php esc_html_e( 'Event or offer', 'chatbot' ); ?><input type="text" name="event_title" maxlength="120"></label>
			<label><?php esc_html_e( 'When', 'chatbot' ); ?><input type="text" name="event_date" maxlength="80"></label>
			<label><?php esc_html_e( 'Where', 'chatbot' ); ?><input type="text" name="event_place" maxlength="120"></label>
			<label><?php esc_html_e( 'Offer', 'chatbot' ); ?><input type="text" name="event_offer" maxlength="120"></label>
			<label><?php esc_html_e( 'Code', 'chatbot' ); ?><input type="text" name="event_code" maxlength="40"></label>
			<label><?php esc_html_e( 'Ends', 'chatbot' ); ?><input type="text" name="event_ends" maxlength="80"></label>
		</div>
		<div class="qcsoc-field" data-kind-hide="blank">
			<label for="qcsoc-w-about"><strong><?php esc_html_e( 'What is it about?', 'chatbot' ); ?></strong></label>
			<textarea id="qcsoc-w-about" name="about" rows="4" maxlength="3000"><?php echo esc_textarea( $qcsoc_pre['about'] ); ?></textarea>
		</div>
		<div class="qcsoc-fields">
			<label><?php esc_html_e( 'Goal', 'chatbot' ); ?>
				<select name="goal">
					<option value=""><?php esc_html_e( 'Let WPBot choose', 'chatbot' ); ?></option>
					<option value="awareness" <?php selected( $qcsoc_pre['goal'], 'awareness' ); ?>><?php esc_html_e( 'Awareness', 'chatbot' ); ?></option>
					<option value="engagement" <?php selected( $qcsoc_pre['goal'], 'engagement' ); ?>><?php esc_html_e( 'Engagement', 'chatbot' ); ?></option>
					<option value="traffic" <?php selected( $qcsoc_pre['goal'], 'traffic' ); ?>><?php esc_html_e( 'Visits to the site', 'chatbot' ); ?></option>
					<option value="sales" <?php selected( $qcsoc_pre['goal'], 'sales' ); ?>><?php esc_html_e( 'Sales', 'chatbot' ); ?></option>
					<option value="event" <?php selected( $qcsoc_pre['goal'], 'event' ); ?>><?php esc_html_e( 'Event sign-ups', 'chatbot' ); ?></option>
				</select>
			</label>
			<label data-kind-show="idea event blank"><?php esc_html_e( 'Link to', 'chatbot' ); ?><input type="url" name="link" placeholder="https://" value="<?php echo esc_attr( $qcsoc_pre['link'] ); ?>"></label>
		</div>
		<?php $qcsoc_brands = (array) apply_filters( 'qcld_wpbot_social/brands', array() ); ?>
		<?php if ( array() !== $qcsoc_brands ) : ?>
			<div class="qcsoc-field">
				<label for="qcsoc-w-brand"><strong><?php esc_html_e( 'Brand', 'chatbot' ); ?></strong></label>
				<select id="qcsoc-w-brand" name="brand_id">
					<option value="0"><?php esc_html_e( 'No brand', 'chatbot' ); ?></option>
					<?php foreach ( $qcsoc_brands as $qcsoc_bid => $qcsoc_bname ) : ?>
						<option value="<?php echo esc_attr( (string) $qcsoc_bid ); ?>"><?php echo esc_html( $qcsoc_bname ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="qcsoc-muted"><?php esc_html_e( 'Uses the brand\'s voice unless you type one below.', 'chatbot' ); ?></p>
			</div>
		<?php endif; ?>
		<div class="qcsoc-field">
			<label for="qcsoc-w-voice"><strong><?php esc_html_e( 'Voice', 'chatbot' ); ?></strong></label>
			<input type="text" id="qcsoc-w-voice" name="voice" maxlength="200" value="<?php echo esc_attr( $qcsoc_wset['voice'] ); ?>" placeholder="<?php esc_attr_e( 'Brand voice from Business Context', 'chatbot' ); ?>">
			<?php if ( '' !== Writer::context() ) : ?>
				<p class="qcsoc-muted"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Uses Business Context: facts, offers, "never say".', 'chatbot' ); ?></p>
			<?php endif; ?>
		</div>
		<fieldset class="qcsoc-field">
			<legend><strong><?php esc_html_e( 'Write for', 'chatbot' ); ?></strong>
			<?php
			if ( ! $qcsoc_multi ) :
				?>
				<span class="qcsoc-muted"><?php echo esc_html( \QuantumCloud\Social\Module::MODE_MASTER === \QuantumCloud\Social\Module::instance()->mode() ? __( '(one network at a time; Master writes for all at once)', 'chatbot' ) : __( '(one network at a time)', 'chatbot' ) ); ?></span><?php endif; ?></legend>
			<div class="qcsoc-chips">
				<?php foreach ( $qcsoc_rules as $qcsoc_net => $qcsoc_rule ) : ?>
					<?php
					if ( $qcsoc_rule['manual'] ) {
						continue;
					}
					?>
					<label class="qcsoc-chip"><input type="<?php echo $qcsoc_multi ? 'checkbox' : 'radio'; ?>" name="networks" value="<?php echo esc_attr( $qcsoc_net ); ?>"> <span class="qcsoc-chip__net"><?php echo esc_html( $qcsoc_rule['label'] ); ?></span><?php echo empty( $qcsoc_connected[ $qcsoc_net ] ) ? ' <span class="qcsoc-muted">' . esc_html__( '(not connected)', 'chatbot' ) . '</span>' : ''; ?></label>
				<?php endforeach; ?>
			</div>
			<p class="qcsoc-muted"><?php esc_html_e( 'Post yourself (WPBot writes the copy and reminds you):', 'chatbot' ); ?></p>
			<div class="qcsoc-chips">
				<?php foreach ( $qcsoc_rules as $qcsoc_net => $qcsoc_rule ) : ?>
					<?php
					if ( ! $qcsoc_rule['manual'] ) {
						continue;
					}
					?>
					<label class="qcsoc-chip"><input type="<?php echo $qcsoc_multi ? 'checkbox' : 'radio'; ?>" name="networks" value="<?php echo esc_attr( $qcsoc_net ); ?>"> <span class="qcsoc-chip__net"><?php echo esc_html( $qcsoc_rule['label'] ); ?></span></label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<div class="qcsoc-write__go">
			<span class="qcsoc-muted" data-qcsoc-estimate role="status"></span>
			<button type="button" class="button button-primary" data-qcsoc-write disabled><?php esc_html_e( 'Write', 'chatbot' ); ?></button>
		</div>
		<p class="qcsoc-callout"><span class="dashicons dashicons-media-text" aria-hidden="true"></span> <?php esc_html_e( 'Writing a long article? AI Writer does that, and can send its social versions here.', 'chatbot' ); ?></p>
	</section>
	<section class="qcsoc-write__results" data-qcsoc-results aria-live="polite">
		<div class="qcsoc-card qcsoc-write__head" hidden data-qcsoc-results-head>
			<div>
				<h2 data-qcsoc-results-title></h2>
				<p class="qcsoc-muted"><?php esc_html_e( 'Edit or rewrite any of them, then open the ones you want in Create post to schedule.', 'chatbot' ); ?></p>
				<?php if ( array() === $qcsoc_connected ) : ?>
					<p class="qcsoc-muted" data-qcsoc-no-accounts><?php esc_html_e( 'No account is connected yet. Connect one in Accounts to schedule these versions.', 'chatbot' ); ?> <a href="<?php echo esc_url( Admin_Controller::url( 'accounts' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Accounts', 'chatbot' ); ?></a></p>
				<?php endif; ?>
			</div>
			<button type="button" class="button button-primary" data-qcsoc-continue><?php esc_html_e( 'Continue with selected', 'chatbot' ); ?></button>
		</div>
		<div class="qcsoc-versions" data-qcsoc-versions></div>
		<span class="qcsoc-status" data-qcsoc-status role="status"></span>
	</section>
</div>
