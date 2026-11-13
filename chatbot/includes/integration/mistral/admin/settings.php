<?php
/**
 * AI Admin Template
 *
 * @package Chatbot
 * @since 14.8.2
 */
?>
<div class="qcl-openai">
	<div class="row gx-0">
		<div class="col-xs-12">
			<div class="card admin-maxwith">
				<div class="card-header bg-dark text-white py-sm-4 border-0">
					<div class="row">
						<div class="col-auto me-auto">
							<h4><?php esc_html_e( 'Mistral AI Settings', 'chatbot' ); ?></h4> 
						</div>
					</div>
				</div>
				<div class="card-body p-sm-0">
					
					<ul class="nav nav-tabs">
						<li class="active"><a data-toggle="tab" href="#wp-chatbot-mistral-settings"><?php echo esc_html__( 'Mistral AI settings', 'chatbot' ); ?></a></li>
						<!-- <li><a data-toggle="tab" href="#wp-chatbot-mistral-training-model"><?php // echo esc_html__( 'Fine Tuning', 'chatbot' ); ?></a></li> -->
						<li><a data-toggle="tab" href="#wp-chatbot-mistral-help"><?php echo esc_html__( 'Mistral AI Help', 'chatbot' ); ?></a></li>
					</ul>

					<div class="tab-content">
						<div id="wp-chatbot-mistral-settings" class="tab-pane in active">
							<div class="row gx-0">
								<div class="mb-3">
									<div class="form-check form-switch my-4">
										<input class="form-check-input" type="checkbox" <?php echo ( get_option( 'qcld_mistral_enabled' ) == 1 ) ? esc_attr( 'checked' ) : ''; ?>  role="switch" value="" id="qcld_mistral_enabled">
										<label class="form-check-label" for="qcld_mistral_enabled">
										<?php esc_html_e( 'Enable Mistral AI', 'chatbot' ); ?><span style="color:red"> <?php esc_html_e( '(if you want results from mistral only, disable Site Search from Settings->Start Menu)', 'chatbot' ); ?></span>
										</label>
									</div>
								</div>
							</div>
							<?php if ( file_exists( QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/admin/templates/ai-file-upload-setting.php' ) ) { require QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/admin/templates/ai-file-upload-setting.php'; } // Chat file upload is a WPBot Pro feature. ?>
							<div class="row gx-0">
								<div class="mb-3">
									<div class="form-check form-switch my-4">
										<input class="form-check-input" type="checkbox" <?php echo ( get_option( 'qcld_mistral_page_suggestion_enabled' ) == '1' ) ? esc_attr( 'checked' ) : ''; ?>  role="switch" value="" id="qcld_mistral_page_suggestion_enabled">
										<label class="form-check-label" for="qcld_mistral_page_suggestion_enabled">
										<?php esc_html_e( 'Enable page suggestions with mistral Result', 'chatbot' ); ?>
										</label>
									</div>
								</div>
								<div class="col-sm-6">
									<div class="form-check form-switch mt-5">
										<input class="form-check-input" type="checkbox" <?php echo ( get_option( 'qcld_mistral_rag_enabled' ) == '1' ) ? esc_attr( 'checked' ) : ''; ?>  role="switch" value="" id="qcld_mistral_rag_enabled">
										<label class="form-check-label" for="qcld_mistral_rag_enabled">
										<?php esc_html_e( 'Enable RAG', 'chatbot' ); ?>
										</label>
									</div>
								</div>
								<div class="col-sm-6">
									<div class="form-check form-switch mt-5">
										<input class="form-check-input" type="checkbox" <?php echo ( get_option( 'qcld_mistral_rag_link_enabled' ) == 1 ) ? esc_attr( 'checked' ) : ''; ?>  role="switch" value="" id="qcld_mistral_rag_link_enabled">
										<label class="form-check-label" for="qcld_mistral_rag_link_enabled">
										<?php esc_html_e( 'Show exact title and permalink for retrieved RAG document', 'chatbot' ); ?>
										</label>
									</div>
								</div>
							</div>
							<div class="row gx-0">
								<div class="mb-3">
								<!-- POST TYPE -->
								<div class="form-check form-switch my-4">
								<label><?php esc_html_e( 'Select POST TYPE(s) to include with search results', 'chatbot' ); ?></label>
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
													id="site_mistral_search_posttypes_<?php echo $post_type->name; ?>"
													type="checkbox"
													name="site_mistral_search_posttypes[]"
													value="<?php echo $post_type->name; ?>" <?php echo ( ( get_option( 'qcld_openai_relevant_post' ) != '' ) && in_array( $post_type->name, get_option( 'qcld_openai_relevant_post' ) ) ) ? 'checked' : ''; ?>>
											<label  class="form-check-label" for="site_mistral_search_posttypes_<?php echo $post_type->name; ?>"> <?php echo $post_type->name; ?></label>
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
								<div class="form-group">
									<label for="qcld_mistral_api_key" class="form-label"><?php esc_html_e( 'Mistral API Key', 'chatbot' ); ?></label>
									<input type="password" autocomplete="new-password" class="form-control" id="qcld_mistral_api_key" name="qcld_mistral_api_key" placeholder="Enter your Mistral API Key" value="<?php echo esc_attr( qcld_wpbot_api_key_field_value( 'qcld_mistral_api_key' ) ); ?>">
									<small class="form-text text-muted"><?php esc_html_e( 'Get your API key from https://mistral.ai/settings/keys', 'chatbot' ); ?></small>
								</div>
							</div>
							<div class="row g-0"> 
								<div class="form-group">
									<label for="qcld_mistral_model" class="form-label"><?php esc_html_e( 'mistral Model', 'chatbot' ); ?></label>
									<select id="qcld_mistral_model" class="form-control" name="qcld_mistral_model" data-current-model="<?php echo esc_attr( get_option( 'qcld_mistral_model' ) ); ?>">
										<option value=""><?php esc_html_e( 'Loading models...', 'chatbot' ); ?></option>
									</select>
									<small class="form-text text-muted"><?php esc_html_e( 'Select a model from mistral', 'chatbot' ); ?></small>
								</div>
								<div class="gx-0">
									<div class="form-group">
										<label for="qcld_mistral_prepend_content" class="form-label"><?php esc_html_e( 'Your Prompt to be Added before the User Query for Customized Results (Optional)', 'chatbot' ); ?></label>
										<input type="text" class="form-control" id="qcld_mistral_prepend_content" name="qcld_mistral_prepend_content" placeholder="Content for the response" value="<?php echo esc_attr( get_option( 'qcld_mistral_prepend_content' ) ); ?>">
										
									</div>
								</div>

								<div class="gx-0">
									<div class="form-group">
										<label for="qcld_mistral_append_content" class="form-label"><?php esc_html_e( 'Your Prompt to be Appended at the End of the User Query for Customized Results (Optional)', 'chatbot' ); ?></label>
										<input type="text" class="form-control" id="qcld_mistral_append_content" name="qcld_mistral_append_content" placeholder="Content for the response" value="<?php echo esc_attr( get_option( 'qcld_mistral_append_content' ) ); ?>">
										
									</div>
								</div>
								<div class="mb-3">
									<?php do_action( 'qcld_wpbot_chat/provider_settings_fields', 'mistral' ); // Chatbot rebuild: common AI options this provider had no field for. ?>
									<a class="btn btn-success" id="qcld_save_mistral_setting"><?php esc_html_e( 'Save settings', 'chatbot' ); ?></a>
								</div>
							</div>
						</div>
						<div id="wp-chatbot-mistral-training-model" class="tab-pane">
							<?php
								// require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/mistral/admin/files.php';
							?>
						</div>
						<div id="wp-chatbot-mistral-help" class="tab-pane">
						<?php
								require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/mistral/admin/help.php';
						?>
						</div>
				</div>
			</div>
		</div>
	</div>
</div>
</div>

