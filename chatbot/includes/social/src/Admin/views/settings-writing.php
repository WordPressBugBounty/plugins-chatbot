<?php
/**
 * Settings › Writing (screen 22).
 *
 * @package QuantumCloud\Social
 */

use QuantumCloud\Social\Writing\Network_Rules;
use QuantumCloud\Social\Writing\Writer;
use QuantumCloud\Social\Writing\Writing_Settings;

defined( 'ABSPATH' ) || exit;

$qcsoc_writing = Writing_Settings::all();
$qcsoc_model   = Writer::model();
$qcsoc_techs   = Network_Rules::techniques();
$qcsoc_ai_url  = class_exists( '\QuantumCloud\WPBotCore\AI\AI_Readiness' ) ? \QuantumCloud\WPBotCore\AI\AI_Readiness::settings_url( Writing_Settings::MODULE ) : admin_url( 'admin.php?page=wpbot-panel' );
$qcsoc_sets    = array();
foreach ( (array) $qcsoc_writing['hashtag_sets'] as $qcsoc_set ) {
	$qcsoc_sets[] = $qcsoc_set['name'] . ': ' . implode( ' ', $qcsoc_set['tags'] );
}
?>
<div class="qcsoc-grid" data-qcsoc="settings" data-qcsoc-endpoint="writing">
	<section class="qcsoc-card">
		<h3><?php esc_html_e( 'AI', 'chatbot' ); ?></h3>
		<div class="qcsoc-row">
			<div><strong><?php esc_html_e( 'AI provider and model', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'Uses the keys saved in WPBot › AI Settings. Usage and budget are counted for Social.', 'chatbot' ); ?></p></div>
			<div data-qcsoc-ai-model>
				<?php if ( '' !== $qcsoc_model['provider'] ) : ?>
					<span class="qcsoc-pill qcsoc-pill--ok"><?php echo esc_html( $qcsoc_model['provider'] . ' · ' . $qcsoc_model['model'] ); ?></span>
				<?php else : ?>
					<span class="qcsoc-pill qcsoc-pill--warn"><?php esc_html_e( 'No AI provider is turned on', 'chatbot' ); ?></span>
					<a href="<?php echo esc_url( $qcsoc_ai_url ); ?>"><?php esc_html_e( 'Open AI Settings', 'chatbot' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<div class="qcsoc-row">
			<label for="qcsoc-image-model"><strong><?php esc_html_e( 'Image model', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'For "Create with AI" images.', 'chatbot' ); ?></p></label>
			<select id="qcsoc-image-model" name="image_model" data-qcsoc-setting>
				<option value="auto" <?php selected( $qcsoc_writing['image_model'], 'auto' ); ?>><?php esc_html_e( 'AI Studio when active (default)', 'chatbot' ); ?></option>
				<option value="off" <?php selected( $qcsoc_writing['image_model'], 'off' ); ?>><?php esc_html_e( 'Off', 'chatbot' ); ?></option>
			</select>
		</div>
		<div class="qcsoc-row">
			<label for="qcsoc-budget"><strong><?php esc_html_e( 'Monthly budget (USD)', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'Social stops AI writing at this amount; you are told first. 0 means no limit.', 'chatbot' ); ?></p></label>
			<input type="number" id="qcsoc-budget" name="budget" min="0" step="0.5" data-qcsoc-setting value="<?php echo esc_attr( (string) $qcsoc_writing['budget'] ); ?>">
		</div>
		<div class="qcsoc-row">
			<div><strong><?php esc_html_e( 'Show the cost before writing', 'chatbot' ); ?></strong></div>
			<label class="qcsoc-switch"><input type="checkbox" name="show_cost" data-qcsoc-setting <?php checked( $qcsoc_writing['show_cost'] ); ?>><span class="screen-reader-text"><?php esc_html_e( 'Show the cost before writing', 'chatbot' ); ?></span></label>
		</div>
	</section>
	<section class="qcsoc-card">
		<h3><?php esc_html_e( 'Voice and rules', 'chatbot' ); ?></h3>
		<div class="qcsoc-row qcsoc-row--stack">
			<label for="qcsoc-voice"><strong><?php esc_html_e( 'Brand voice', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'Leave empty to use Business Context; brands can override it.', 'chatbot' ); ?></p></label>
			<input type="text" id="qcsoc-voice" name="voice" maxlength="200" data-qcsoc-setting value="<?php echo esc_attr( $qcsoc_writing['voice'] ); ?>" placeholder="<?php esc_attr_e( 'Warm, plain, a little playful', 'chatbot' ); ?>">
		</div>
		<div class="qcsoc-row">
			<div><strong><?php esc_html_e( 'Honesty check', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'Flag claims not in Business Context or the source', 'chatbot' ); ?></p></div>
			<label class="qcsoc-switch"><input type="checkbox" name="honesty" data-qcsoc-setting <?php checked( $qcsoc_writing['honesty'] ); ?>><span class="screen-reader-text"><?php esc_html_e( 'Honesty check', 'chatbot' ); ?></span></label>
		</div>
		<div class="qcsoc-row">
			<label for="qcsoc-hashtags"><strong><?php esc_html_e( 'Hashtags', 'chatbot' ); ?></strong></label>
			<select id="qcsoc-hashtags" name="hashtags" data-qcsoc-setting>
				<?php
				foreach ( array(
					'fits' => __( 'What fits each network', 'chatbot' ),
					'none' => __( 'None', 'chatbot' ),
					'few'  => __( 'A few (up to 2)', 'chatbot' ),
					'many' => __( 'More (up to 8)', 'chatbot' ),
				) as $qcsoc_value => $qcsoc_label ) :
					?>
					<option value="<?php echo esc_attr( $qcsoc_value ); ?>" <?php selected( $qcsoc_writing['hashtags'], $qcsoc_value ); ?>><?php echo esc_html( $qcsoc_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="qcsoc-row">
			<label for="qcsoc-emojis"><strong><?php esc_html_e( 'Emojis', 'chatbot' ); ?></strong></label>
			<select id="qcsoc-emojis" name="emojis" data-qcsoc-setting>
				<?php
				foreach ( array(
					'none' => __( 'None', 'chatbot' ),
					'few'  => __( 'A few', 'chatbot' ),
					'many' => __( 'Where they fit', 'chatbot' ),
				) as $qcsoc_value => $qcsoc_label ) :
					?>
					<option value="<?php echo esc_attr( $qcsoc_value ); ?>" <?php selected( $qcsoc_writing['emojis'], $qcsoc_value ); ?>><?php echo esc_html( $qcsoc_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="qcsoc-row qcsoc-row--stack">
			<label for="qcsoc-sets"><strong><?php esc_html_e( 'Saved hashtag sets', 'chatbot' ); ?></strong><p class="qcsoc-muted"><?php esc_html_e( 'One set per line: "Name: #tag #tag".', 'chatbot' ); ?></p></label>
			<textarea id="qcsoc-sets" name="hashtag_sets" rows="3" data-qcsoc-setting data-qcsoc-format="sets"><?php echo esc_textarea( implode( "\n", $qcsoc_sets ) ); ?></textarea>
		</div>
	</section>
	<section class="qcsoc-card qcsoc-card--wide">
		<h3><?php esc_html_e( 'Per network', 'chatbot' ); ?></h3>
		<p class="qcsoc-muted"><?php esc_html_e( 'How each network\'s version is written. Defaults follow each network\'s habits.', 'chatbot' ); ?></p>
		<table class="widefat striped qcsoc-table">
			<thead><tr><th scope="col"><?php esc_html_e( 'Network', 'chatbot' ); ?></th><th scope="col"><?php esc_html_e( 'Technique', 'chatbot' ); ?></th><th scope="col"><?php esc_html_e( 'Length', 'chatbot' ); ?></th><th scope="col"><?php esc_html_e( 'Link', 'chatbot' ); ?></th><th scope="col"><?php esc_html_e( 'Extra', 'chatbot' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( array_keys( Network_Rules::defaults() ) as $qcsoc_network ) : ?>
				<?php $qcsoc_rule = Network_Rules::get( $qcsoc_network ); ?>
				<tr>
					<th scope="row"><?php echo esc_html( $qcsoc_rule['label'] ); ?></th>
					<td>
						<label class="screen-reader-text" for="qcsoc-tech-<?php echo esc_attr( $qcsoc_network ); ?>"><?php /* translators: %s: network. */ echo esc_html( sprintf( __( 'Technique for %s', 'chatbot' ), $qcsoc_rule['label'] ) ); ?></label>
						<select id="qcsoc-tech-<?php echo esc_attr( $qcsoc_network ); ?>" name="networks.<?php echo esc_attr( $qcsoc_network ); ?>.technique" data-qcsoc-setting>
							<?php foreach ( $qcsoc_techs as $qcsoc_value => $qcsoc_label ) : ?>
								<option value="<?php echo esc_attr( $qcsoc_value ); ?>" <?php selected( $qcsoc_rule['technique'], $qcsoc_value ); ?>><?php echo esc_html( $qcsoc_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
					<td><?php echo esc_html( $qcsoc_rule['length'] ); ?></td>
					<td><?php echo esc_html( $qcsoc_rule['link'] ); ?></td>
					<td><?php echo esc_html( '' !== $qcsoc_rule['extra'] ? $qcsoc_rule['extra'] : '—' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</section>
	<span class="qcsoc-status" data-qcsoc-status role="status"></span>
</div>
