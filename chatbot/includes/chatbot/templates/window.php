<?php
/**
 * The chat window (one markup for every design; owner, 20 November 2026).
 *
 * Every ID, class and hook below that starts with "wp-chatbot-", "qcld-" or "wpbot" is used by
 * the conversation script or an add-on (voice, live chat, forms, start screen, store,
 * Multi-Language) and must stay. New parts use the "qcw" prefix.
 *
 * Variables: $data (Window_Render::data(), filtered by qcld_wpbot_chat/window_data).
 *
 * @package QuantumCloud\Chatbot
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- included inside Window_Render::output(), so the variables are local; the hook names are the add-ons' existing ones and must not change.

$qcw_menu   = $data['menu'];
$qcw_items  = array_filter(
	array(
		'new'      => $qcw_menu['new'],
		'email'    => $qcw_menu['email'] && $data['transcript'],
		'download' => $qcw_menu['download'],
		'sound'    => $qcw_menu['sound'],
		'text'     => $qcw_menu['text'],
		'lang'     => $qcw_menu['lang'],
		'person'   => $qcw_menu['person'],
	)
);
$qcw_guide  = $data['guide'];
$qcw_flags  = array_keys( array_filter( array_intersect_key( $qcw_guide, array_flip( array( 'page', 'tryask', 'composer', 'ghost', 'nudge' ) ) ) ) );
$qcw_bottom = '';
if ( $data['bottom_menu'] && class_exists( '\QuantumCloud\Chatbot\Menu\Menu_Items' ) ) {
	$qcw_bottom = str_replace( ' data-qcld-menu="1"', '', \QuantumCloud\Chatbot\Menu\Menu_Items::render() );
}
if ( 'embedded' === $data['context'] ) {
	// On a page ([wpbot-page]): the conversation script's page path looks for this container (the app has its own).
	echo '<div id="wp-chatbot-shortcode-template-container" class="wp-chatbot-shortcode-template-container chatbot-shortcode-template-01 qcw-page"' . ( ! empty( $data['width'] ) ? ' style="max-width:' . esc_attr( $data['width'] ) . ';margin-inline:auto"' : '' ) . '>';
	if ( ! empty( $data['scope'] ) ) {
		// This window's own design: its colour tokens apply inside this container only.
		echo '<style>' . str_replace( '.qcw', '#wp-chatbot-shortcode-template-container .qcw', $data['scope'] ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from checked hex colours and numbers only.
	}
}
?>
<div id="wp-chatbot-ball-container" role="region" aria-label="<?php echo esc_attr__( 'Chat', 'chatbot' ); ?>" class="wp-chatbot-template-01 <?php echo esc_attr( implode( ' ', $data['classes'] ) ); ?>" data-qcw-mode="<?php echo esc_attr( $data['mode'] ); ?>" data-qcw-design="<?php echo esc_attr( $data['theme']['design'] ); ?>" data-qcw-side="<?php echo esc_attr( $data['theme']['side'] ); ?>" data-qcw-open="<?php echo esc_attr( $data['theme']['motion']['open'] ); ?>" data-qcw-send="<?php echo esc_attr( $data['theme']['motion']['send'] ); ?>" data-qcw-tip="<?php echo $data['theme']['guide']['tip'] ? '1' : '0'; ?>" data-qcw-guide="<?php echo esc_attr( implode( ',', $qcw_flags ) ); ?>"<?php echo $qcw_guide['phrases'] ? ' data-qcw-phrases="' . esc_attr( wp_json_encode( $qcw_guide['phrases'] ) ) . '"' : ''; ?>>

	<?php do_action( 'render_start_menu' ); ?>
	<div class="wpbot-saas-live-chat"></div>
	<div class="wp-chatbot-container">
		<?php
		if ( function_exists( 'qcpd_wpwc_addon_lang_init' ) ) {
			do_action( 'qcld_wpwc_product_details_woocommerce' );
		}
		?>
		<div id="wp-chatbot-board-container" class="wp-chatbot-board-container qcw-board<?php echo $data['floating'] ? '' : ' active-chat-board'; ?>" role="dialog" aria-label="<?php echo esc_attr( $data['name'] ); ?>">
			<div class="qcw-amb" aria-hidden="true"><i></i><i></i><i></i></div>
			<div class="wp-chatbot-header qcw-head">
				<span class="qcw-av" aria-hidden="true"><img src="<?php echo esc_url( $data['agent'] ); ?>" alt=""></span>
				<span class="qcw-id"><span class="qcw-name"><?php echo esc_html( $data['name'] ); ?></span></span>
				<?php do_action( 'render_back_to_menu_button' ); ?>
				<span class="qcw-btns">
					<?php if ( $qcw_items ) : ?>
						<button type="button" class="qcw-ib qcw-more" aria-label="<?php echo esc_attr__( 'More options', 'chatbot' ); ?>" aria-haspopup="menu" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.2"/><circle cx="12" cy="12" r="1.2"/><circle cx="19" cy="12" r="1.2"/></svg></button>
					<?php endif; ?>
					<?php if ( $data['floating'] ) : ?>
						<button type="button" id="wp-chatbot-desktop-close" class="qcw-ib qcw-close" aria-label="<?php echo esc_attr( $data['close'] ); ?>" title="<?php echo esc_attr( $data['close'] ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button>
						<?php endif; ?>
				</span>
				<?php
				// The conversation script's own controls, run from the three-dot menu.
				?>
				<span class="qcw-legacy" hidden>
					<span id="wp-chatbot-desktop-reload" title="<?php echo esc_attr( $data['reset'] ); ?>"></span>
					<?php if ( $data['transcript'] ) : ?>
						<span id="wp-chatbot-email-transcript"></span>
					<?php endif; ?>
				</span>
				<?php
				if ( function_exists( 'qcld_wpbotml' ) && count( qcld_wpbotml()->languages ) > 1 ) {
					echo '<span class="qcw-lang-source" hidden>';
					do_action( 'ml_render_lan_dropdown' );
					echo '</span>';
				}
				?>
			</div>
			<?php if ( $qcw_items ) : ?>
				<div class="qcw-menu" role="menu" aria-label="<?php echo esc_attr__( 'Chat options', 'chatbot' ); ?>" hidden data-items="<?php echo esc_attr( implode( ',', array_keys( $qcw_items ) ) ); ?>"></div>
			<?php endif; ?>
			<div class="qcw-toast" role="status" hidden></div>

			<div class="wp-chatbot-ball-inner wp-chatbot-content qcw-body" tabindex="0">
				<?php if ( 'full' === $data['context'] ) : ?>
					<?php
					// The app ([wpwbot_app]): the conversation script shows the cart and the checkout here.
					?>
					<div class="wp-chatbot-cart-checkout-wrapper">
						<div id="wp-chatbot-cart-short-code"></div>
						<div id="wp-chatbot-checkout-short-code"></div>
					</div>
				<?php endif; ?>
				<div class="wp-chatbot-messages-wrapper">
					<ul id="wp-chatbot-messages-container" class="wp-chatbot-messages-container" aria-live="polite"></ul>
				</div>
				<?php do_action( 'wpbot_voice_record_wrapper' ); ?>
			</div>

			<div class="wp-chatbot-footer qcw-dock">
				<?php if ( $qcw_guide['tryask'] || $qcw_guide['composer'] ) : ?>
					<div class="qcw-pop" role="listbox" aria-label="<?php echo esc_attr__( 'Suggested questions', 'chatbot' ); ?>" hidden></div>
				<?php endif; ?>
				<?php if ( '' !== $qcw_guide['buttons'] ) : ?>
					<?php
					// This page's suggestions, as the conversation script's own buttons (the window shows copies).
					?>
					<div class="qcw-suggest" hidden data-page-title="<?php echo esc_attr__( 'Popular on this page', 'chatbot' ); ?>" data-try-title="<?php echo esc_attr__( 'Try asking', 'chatbot' ); ?>" data-nudge="<?php echo esc_attr__( 'Not sure where to start? People on this page often ask:', 'chatbot' ); ?>"><?php echo wp_kses_post( $qcw_guide['buttons'] ); ?></div>
				<?php endif; ?>
				<?php if ( $qcw_guide['phrases'] && class_exists( '\QuantumCloud\Chatbot\Menu\Menu_Items' ) ) : ?>
					<div class="qcw-allitems" hidden><?php echo wp_kses_post( \QuantumCloud\Chatbot\Menu\Menu_Items::render() ); ?></div>
				<?php endif; ?>
				<div class="qcw-tip" hidden><span><?php echo esc_html__( 'Type a question, or tap one of the suggestions.', 'chatbot' ); ?></span><button type="button" class="qcw-tip-ok"><?php echo esc_html__( 'Got it', 'chatbot' ); ?></button></div>
				<?php if ( '' !== $qcw_bottom ) : ?>
					<div class="qcld-horizontal-scroll qcw-chipbar">
						<button type="button" class="qcw-cb qcw-cb-l" aria-label="<?php echo esc_attr__( 'Scroll the menu left', 'chatbot' ); ?>" hidden><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg></button>
						<div class="qcld-bottom-menu-wrapper">
							<div class="qcld-bottom-menu-item qcw-chips"><?php echo wp_kses_post( $qcw_bottom ); ?></div>
						</div>
						<button type="button" class="qcw-cb qcw-cb-r" aria-label="<?php echo esc_attr__( 'Scroll the menu right', 'chatbot' ); ?>" hidden><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>
					</div>
				<?php endif; ?>
				<div id="wp-chatbot-editor-container" class="wp-chatbot-editor-container qcw-composer">
					<label for="wp-chatbot-editor" class="qcw-sr"><?php echo esc_html__( 'Message', 'chatbot' ); ?></label>
					<textarea id="wp-chatbot-editor" class="wp-chatbot-editor" rows="1" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>"></textarea>
					<?php if ( $qcw_guide['ghost'] ) : ?>
						<div class="qcw-ghost" aria-hidden="true"></div>
					<?php endif; ?>
					<?php if ( $qcw_guide['tryask'] ) : ?>
						<button type="button" class="qcw-ib qcw-try" aria-label="<?php echo esc_attr__( 'Try asking', 'chatbot' ); ?>" title="<?php echo esc_attr__( 'Try asking', 'chatbot' ); ?>" aria-haspopup="listbox" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3z"/></svg></button>
					<?php endif; ?>
					<?php do_action( 'wpbot_voice_icon' ); ?>
					<button type="button" id="wp-chatbot-send-message" class="wp-chatbot-button qcw-send" aria-label="<?php echo esc_attr__( 'Send', 'chatbot' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"/></svg></button>
				</div>
			</div>
			<div class="qcw-scan" aria-hidden="true"></div>
		</div>
	</div>
</div>
<?php
if ( 'embedded' === $data['context'] ) {
	echo '</div>';
}
