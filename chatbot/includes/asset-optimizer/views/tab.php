<?php
/**
 * Performance tab content, printed inside the host's ChatBot Settings form.
 *
 * Variables: $module (Module), $settings (array), $status (array), $field (string).
 *
 * @package QuantumCloud\AssetOptimizer
 */

defined( 'ABSPATH' ) || exit;

$qcld_wpbot_ao_checkboxes = array(
	'enabled'         => array(
		__( 'Enable WPBot asset cache', 'chatbot' ),
		__( 'Serve smaller copies of the chatbot\'s own scripts and styles. Off means no change at all to your pages.', 'chatbot' ),
	),
	'minify_css'      => array(
		__( 'Minify CSS', 'chatbot' ),
		'',
	),
	'minify_js'       => array(
		__( 'Minify JavaScript', 'chatbot' ),
		'',
	),
	'defer_js'        => array(
		__( 'Defer WPBot scripts', 'chatbot' ),
		__( 'Needs WordPress 6.3 or later; older versions load scripts as before.', 'chatbot' ),
	),
	'nonblocking_css' => array(
		__( 'Load chatbot styles without blocking the page', 'chatbot' ),
		__( 'Not applied on pages where the chatbot is part of the page content.', 'chatbot' ),
	),
);
?>
<section id="section-flip-performance" class="qcld-wpbot-ao">
	<div class="top-section">
		<div class="row">
			<div class="col-xs-12 col-md-8">
				<?php wp_nonce_field( \QuantumCloud\AssetOptimizer\Admin\Admin::NONCE_SAVE, $field . '_nonce', false ); ?>

				<?php if ( 'constant' === $status['kill_switch'] ) : ?>
					<div class="qcld-wpbot-ao-banner"><?php esc_html_e( 'The asset cache is switched off by the WPBOT_ASSET_CACHE_DISABLE constant in wp-config.php.', 'chatbot' ); ?></div>
				<?php elseif ( 'script_debug' === $status['kill_switch'] ) : ?>
					<div class="qcld-wpbot-ao-banner"><?php esc_html_e( 'SCRIPT_DEBUG is on, so the original files are served.', 'chatbot' ); ?></div>
				<?php endif; ?>

				<div class="cxsc-settings-blocks">
					<h4 class="qc-opt-title"><?php esc_html_e( 'Asset cache', 'chatbot' ); ?></h4>
					<?php foreach ( $qcld_wpbot_ao_checkboxes as $qcld_wpbot_ao_key => $qcld_wpbot_ao_label ) : ?>
						<div class="qcld-wpbot-ao-field">
							<input type="checkbox" value="1" id="qcld-wpbot-ao-<?php echo esc_attr( $qcld_wpbot_ao_key ); ?>" name="<?php echo esc_attr( $field . '[' . $qcld_wpbot_ao_key . ']' ); ?>" <?php checked( ! empty( $settings[ $qcld_wpbot_ao_key ] ) ); ?>>
							<label for="qcld-wpbot-ao-<?php echo esc_attr( $qcld_wpbot_ao_key ); ?>"><?php echo esc_html( $qcld_wpbot_ao_label[0] ); ?></label>
							<?php if ( '' !== $qcld_wpbot_ao_label[1] ) : ?>
								<p class="qc-opt-dcs"><?php echo esc_html( $qcld_wpbot_ao_label[1] ); ?></p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="cxsc-settings-blocks">
					<h4 class="qc-opt-title"><?php esc_html_e( 'Advanced', 'chatbot' ); ?></h4>
					<div class="qcld-wpbot-ao-field">
						<input type="checkbox" value="1" id="qcld-wpbot-ao-combine" name="<?php echo esc_attr( $field . '[combine]' ); ?>" <?php checked( ! empty( $settings['combine'] ) ); ?>>
						<label for="qcld-wpbot-ao-combine"><?php esc_html_e( 'Combine WPBot files', 'chatbot' ); ?></label>
						<p class="qc-opt-dcs"><?php esc_html_e( 'Fewer requests, but test your chatbot after turning it on: some themes and add-ons expect separate files.', 'chatbot' ); ?></p>
					</div>
					<div class="qcld-wpbot-ao-field">
						<label for="qcld-wpbot-ao-skip"><?php esc_html_e( 'Files to leave untouched', 'chatbot' ); ?></label>
						<textarea id="qcld-wpbot-ao-skip" class="form-control" rows="4" name="<?php echo esc_attr( $field . '[skip]' ); ?>"><?php echo esc_textarea( implode( "\n", (array) $settings['skip'] ) ); ?></textarea>
						<p class="qc-opt-dcs"><?php esc_html_e( 'One script or style handle, or file name, per line (for example qcld-wp-chatbot-plugin or common-style.css).', 'chatbot' ); ?></p>
					</div>
					<?php if ( array() !== $status['purgeable'] ) : ?>
						<div class="qcld-wpbot-ao-field">
							<input type="checkbox" value="1" id="qcld-wpbot-ao-purge" name="<?php echo esc_attr( $field . '[purge_page_cache]' ); ?>" <?php checked( ! empty( $settings['purge_page_cache'] ) ); ?>>
							<label for="qcld-wpbot-ao-purge"><?php esc_html_e( 'Purge page cache when WPBot cache is cleared', 'chatbot' ); ?></label>
						</div>
					<?php endif; ?>
					<?php
					/**
					 * Print extra Performance settings (the Pro module adds its own here).
					 *
					 * @param array<string,mixed> $settings Current settings.
					 * @param string              $field    Form field name prefix.
					 */
					do_action( 'qcld_wpbot_ao_render_settings', $settings, $field );
					?>
				</div>

				<?php require __DIR__ . '/status.php'; ?>
			</div>
			<div class="col-xs-12 col-md-4">
				<?php require __DIR__ . '/help.php'; ?>
			</div>
		</div>
	</div>
</section>
