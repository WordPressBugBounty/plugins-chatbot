<?php
/**
 * DeepSeek settings (AI Settings > DeepSeek).
 *
 * @package Chatbot
 */

defined( 'ABSPATH' ) || exit;

$qcld_deepseek_model = (string) get_option( 'qcld_deepseekai_engines', 'deepseek-chat' );
?>
<div class="qcl-openai">
	<div class="row gx-0">
		<div class="col-xs-12">
			<div class="card admin-maxwith">
				<div class="card-header bg-dark text-white py-sm-4 border-0">
					<div class="row">
						<div class="col-auto me-auto">
							<h4><?php esc_html_e( 'DeepSeek Settings', 'chatbot' ); ?></h4>
						</div>
					</div>
				</div>
				<div class="card-body p-sm-0">
					<div class="tab-content">
						<div id="wp-chatbot-deepseek-settings" class="tab-pane in active">
		<div class="form-check form-switch my-4">
			<input class="form-check-input" type="checkbox" <?php checked( '1', (string) get_option( 'qcld_deepseekai_enabled' ) ); ?> role="switch" value="1" id="is_deepseekai_enabled">
			<label class="form-check-label" for="is_deepseekai_enabled"><?php esc_html_e( 'Enable DeepSeek', 'chatbot' ); ?></label>
		</div>
		<div class="mb-3">
			<label for="qcld_deepseekai_api_key" class="form-label"><?php esc_html_e( 'API key', 'chatbot' ); ?></label>
			<input type="password" autocomplete="new-password" class="form-control" id="qcld_deepseekai_api_key" name="qcld_deepseekai_api_key" placeholder="<?php esc_attr_e( 'API key', 'chatbot' ); ?>" value="<?php echo esc_attr( qcld_wpbot_api_key_field_value( 'qcld_deepseekai_api_key' ) ); ?>">
			<p class="description"><?php echo wp_kses_post( __( 'Create a key at <a href="https://platform.deepseek.com/api_keys" target="_blank" rel="noopener">platform.deepseek.com</a>.', 'chatbot' ) ); ?></p>
		</div>
		<div class="mb-3">
			<label for="qcld_deepseekai_engines" class="form-label"><?php esc_html_e( 'Model', 'chatbot' ); ?></label>
			<select class="form-select" name="qcld_deepseekai_engines" id="qcld_deepseekai_engines">
				<option value="deepseek-chat" <?php selected( 'deepseek-chat', $qcld_deepseek_model ); ?>><?php esc_html_e( 'DeepSeek Chat (deepseek-chat)', 'chatbot' ); ?></option>
				<option value="deepseek-reasoner" <?php selected( 'deepseek-reasoner', $qcld_deepseek_model ); ?>><?php esc_html_e( 'DeepSeek Reasoner (deepseek-reasoner)', 'chatbot' ); ?></option>
			</select>
		</div>
		<div class="mb-3">
			<label for="qcld_deepseekai_temperature" class="form-label"><?php esc_html_e( 'Temperature (0 to 2, empty for the default)', 'chatbot' ); ?></label>
			<input id="qcld_deepseekai_temperature" class="form-control" type="number" min="0" max="2" step="0.1" name="temperature" value="<?php echo esc_attr( get_option( 'qcld_deepseekai_temperature' ) ); ?>">
		</div>
		<div class="mb-3">
			<label for="deepseekai_max_tokens" class="form-label"><?php esc_html_e( 'Max tokens (empty for the default)', 'chatbot' ); ?></label>
			<input id="deepseekai_max_tokens" class="form-control" type="number" min="1" max="8192" name="max_tokens" value="<?php echo esc_attr( get_option( 'deepseekai_max_tokens' ) ); ?>">
		</div>
		<div class="form-check form-switch my-3">
			<input class="form-check-input" type="checkbox" value="1" id="qcld_deepseekai_rag_enabled" <?php checked( '1', (string) get_option( 'qcld_deepseekai_rag_enabled' ) ); ?>>
			<label class="form-check-label" for="qcld_deepseekai_rag_enabled"><?php esc_html_e( 'Answer from the knowledge base', 'chatbot' ); ?></label>
			<p class="description"><?php esc_html_e( 'DeepSeek has no embeddings: the knowledge base is searched with another saved key (OpenAI, Gemini, Mistral or OpenRouter), or by keywords when there is none.', 'chatbot' ); ?></p>
		</div>
		<div class="form-check form-switch my-3">
			<input class="form-check-input" type="checkbox" value="1" id="qcld_deepseekai_page_suggestion_enabled" <?php checked( '1', (string) get_option( 'qcld_deepseekai_page_suggestion_enabled' ) ); ?>>
			<label class="form-check-label" for="qcld_deepseekai_page_suggestion_enabled"><?php esc_html_e( 'Show related pages under the answer', 'chatbot' ); ?></label>
		</div>
		<div class="mb-3">
			<?php do_action( 'qcld_wpbot_chat/provider_settings_fields', 'deepseek' ); // Chatbot rebuild: common AI options this provider had no field for. ?>
			<a class="btn btn-success" id="qcld_deepseek_save_setting"><?php esc_html_e( 'Save settings', 'chatbot' ); ?></a>
		</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
