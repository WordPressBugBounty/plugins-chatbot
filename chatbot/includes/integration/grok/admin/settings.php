<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
/**
 * AI Admin Template
 *
 * @package Botmaster
 * @since 14.8.2
 */
?>

<div class="card-body p-sm-0">
	
	<ul class="nav nav-tabs">
		<li class="active"><a data-toggle="tab" href="#wp-chatbot-grok-settings"><?php echo esc_html__( 'Grok AI settings', 'chatbot'); ?></a></li>
		<li><a data-toggle="tab" href="#wp-chatbot-grok-help"><?php echo esc_html__( 'Grok AI Help', 'chatbot'); ?></a></li>
	</ul>

	<div class="tab-content">
		<div id="wp-chatbot-grok-settings" class="tab-pane in active">
			<div class="row gx-0">
				<div class="mb-3">
					<div class="form-check form-switch my-4">
						<input class="form-check-input" type="checkbox" <?php echo ( get_option( 'qcld_grok_enabled' ) == 1 ) ? esc_attr( 'checked' ) : ''; ?>  role="switch" value="" id="qcld_grok_enabled">
						<label class="form-check-label" for="qcld_grok_enabled">
						<?php esc_html_e( 'Enable Grok AI', 'chatbot'); ?><span style="color:red"> <?php esc_html_e( '(if you want results from grok only, disable Site Search from Settings->Start Menu)', 'chatbot'); ?></span>
						</label>
					</div>
				</div>
			</div>
			<div class="row gx-0">
				<div class="mb-3">
					<div class="form-check form-switch my-4">
						<input class="form-check-input" type="checkbox" <?php echo ( get_option( 'qcld_grok_page_suggestion_enabled' ) == '1' ) ? esc_attr( 'checked' ) : ''; ?>  role="switch" value="" id="qcld_grok_page_suggestion_enabled">
						<label class="form-check-label" for="qcld_grok_page_suggestion_enabled">
						<?php esc_html_e( 'Enable page suggestions with grok Result', 'chatbot'); ?>
						</label>
					</div>
				<!-- POST TYPE -->
				<div class="form-check form-switch my-4">
				<label><?php esc_html_e( 'Select POST TYPE(s) to include with search results', 'chatbot'); ?></label>
					<div id="wp-chatbot-post-converter">
						<ul class="checkbox-list">
							<?php
								$get_cpt_args = array(
									'public'   => true,
								);
								$post_types   = get_post_types( $get_cpt_args, 'object' );
								foreach ( $post_types as $post_type ) {
									if ( $post_type->name != 'attachment' ) {
										?>
							<div class="form-check form-check-inline">
							<input
									id="site_grok_search_posttypes_<?php echo $post_type->name; ?>"
									type="checkbox"
									name="site_grok_search_posttypes[]"
									value="<?php echo $post_type->name; ?>" <?php echo ( ( get_option( 'qcld_openai_relevant_post' ) != '' ) && in_array( $post_type->name, get_option( 'qcld_openai_relevant_post' ) ) ) ? 'checked' : ''; ?>>
							<label  class="form-check-label" for="site_grok_search_posttypes_<?php echo $post_type->name; ?>"> <?php echo $post_type->name; ?></label>
							</div>
										<?php
									}
								}
								?>
						</ul>
					</div>
				</div>
			<!-- /POST TYPE -->
				</div>  
			</div>
			<div class="row gx-0">
				<div class="form-group mb-3">
					<label for="qcld_grok_api_key" class="form-label"><?php esc_html_e( 'Grok API Key', 'chatbot'); ?></label>
					<input type="password" class="form-control" id="qcld_grok_api_key" name="qcld_grok_api_key" placeholder="Enter your Grok API Key" value="<?php echo esc_attr( get_option( 'qcld_grok_api_key' ) ); ?>">
					<small class="form-text text-muted"><?php esc_html_e( 'Get your API key from https://grok.ai/settings/keys', 'chatbot'); ?></br><span style="color:red"><?php esc_html_e('It requires a paid Grok API plan', 'chatbot'); ?> </span></small>
				</div>
			</div>
			
			<div class="row g-0"> 
				<div class="gx-0">
					<div class="form-group mb-3">
						<label for="qcld_grok_system_content"><?php esc_attr_e( 'System Command or Prompt for RAG and OpenAI Direct (Use it to Instruct ChatGPT how to behave)', 'chatbot'); ?></label>
						<textarea type="text" rows="5" class="form-control" id="qcld_grok_system_content" placeholder="<?php echo esc_attr( 'You are a helpful Assistant. Be concise and relevant in your answers and do not introduce new topic.' ); ?>"><?php echo esc_html( get_option( 'qcld_grok_system_content' ) ); ?></textarea><br>
						<label><small><?php esc_html_e( "To set the ChatBot's tone and character set a system message according to your need", 'chatbot'); ?></small></label></br>
						<label><small><?php esc_html_e( 'Example: You are a helpful Assistant. Be concise and relevant in your answers and do not introduce new topic.', 'chatbot'); ?></small></label>
					</div>
				</div>
				<div class="form-group mb-3">
					<a class="btn btn-success" id="qcld_save_grok_setting"><?php esc_html_e( 'Save settings', 'chatbot'); ?></a>
				</div>
			</div>
		</div>
		<div id="wp-chatbot-grok-help" class="tab-pane">
		<?php
			require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/grok/admin/help.php';
		?>
		</div>
		<div id="wp-chatbot-grok-rag" class="tab-pane">
		<?php
			//	require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/grok/admin/rag-manager.php';
		?>
		</div>
	</div>
</div>
		

