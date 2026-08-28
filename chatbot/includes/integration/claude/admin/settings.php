<?php
/**
 * AI Admin Template
 *
 * @package Botmaster
 * @since 14.8.2
 */
?>

				<div class="card-header bg-dark text-white py-sm-4 border-0">
					<div class="row">
						<div class="col-auto me-auto">
							<h4><?php esc_html_e( 'Claude AI Settings', 'wpchatbot' ); ?></h4> 
						</div>
					</div>
				</div>
				<div class="card-body p-sm-0">
					
					<ul class="nav nav-tabs">
						<li class="active"><a data-toggle="tab" href="#wp-chatbot-claude-settings"><?php echo esc_html__( 'Claude AI settings', 'wpchatbot' ); ?></a></li>
						<li><a data-toggle="tab" href="#wp-chatbot-claude-help"><?php echo esc_html__( 'Claude AI Help', 'wpchatbot' ); ?></a></li>
					</ul>

					<div class="tab-content">
						<div id="wp-chatbot-claude-settings" class="tab-pane in active">
							<div class="row gx-0">
								<div class="mb-3">
									<div class="form-check form-switch my-4">
										<input class="form-check-input" type="checkbox" <?php echo ( get_option( 'qcld_claude_enabled' ) == 1 ) ? esc_attr( 'checked' ) : ''; ?>  role="switch" value="" id="qcld_claude_enabled">
										<label class="form-check-label" for="qcld_claude_enabled">
										<?php esc_html_e( 'Enable Claude AI', 'wpchatbot' ); ?><span style="color:red"> <?php esc_html_e( '(if you want results from claude only, disable Site Search from Settings->Start Menu)', 'wpchatbot' ); ?></span>
										</label>
									</div>
								</div>
							</div>
							
							<div class="row gx-0">
								<div class="mb-3">
									<div class="form-check form-switch my-4">
										<input class="form-check-input" type="checkbox" <?php echo ( get_option( 'qcld_claude_stream_enabled' ) == 1 ) ? esc_attr( 'checked' ) : ''; ?>  role="switch" value="" id="qcld_claude_stream_enabled">
										<label class="form-check-label" for="qcld_claude_stream_enabled">
										<?php esc_html_e( 'Enable Streaming', 'wpchatbot' ); ?>
										</label>
									</div>
								</div>
							</div>

							<div class="row gx-0">
								<div class="mb-3">
									<div class="form-check form-switch my-4">
										<input class="form-check-input" type="checkbox" <?php echo ( get_option( 'qcld_claude_rag_enabled' ) == 1 ) ? esc_attr( 'checked' ) : ''; ?>  role="switch" value="" id="qcld_claude_rag_enabled">
										<label class="form-check-label" for="qcld_claude_rag_enabled">
										<?php esc_html_e( 'Enable Claude RAG', 'wpchatbot' ); ?>
										</label>
									</div>
								</div>
							</div>
							<div class="row gx-0">
								<div class="mb-3">
									<div class="form-check form-switch my-4">
										<input class="form-check-input" type="checkbox" <?php echo ( get_option( 'qcld_claude_page_suggestion_enabled' ) == '1' ) ? esc_attr( 'checked' ) : ''; ?>  role="switch" value="" id="qcld_claude_page_suggestion_enabled">
										<label class="form-check-label" for="qcld_claude_page_suggestion_enabled">
										<?php esc_html_e( 'Enable page suggestions with claude Result', 'wpchatbot' ); ?>
										</label>
									</div>
								<!-- POST TYPE -->
								<div class="form-check form-switch my-4">
								<label><?php esc_html_e( 'Select POST TYPE(s) to include with search results', 'wpchatbot' ); ?></label>
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
													id="site_claude_search_posttypes_<?php echo $post_type->name; ?>"
													type="checkbox"
													name="site_claude_search_posttypes[]"
													value="<?php echo $post_type->name; ?>" <?php echo ( ( get_option( 'qcld_openai_relevant_post' ) != '' ) && in_array( $post_type->name, get_option( 'qcld_openai_relevant_post' ) ) ) ? 'checked' : ''; ?>>
											<label  class="form-check-label" for="site_claude_search_posttypes_<?php echo $post_type->name; ?>"> <?php echo $post_type->name; ?></label>
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
									<label for="qcld_claude_api_key" class="form-label"><?php esc_html_e( 'Claude API Key', 'wpchatbot' ); ?></label>
									<div class="input-group">
										<input type="password" class="form-control" id="qcld_claude_api_key" name="qcld_claude_api_key" placeholder="Enter your Claude API Key" value="<?php echo esc_attr( get_option( 'qcld_claude_api_key' ) ); ?>">
									</div>
									<small class="form-text text-muted"><?php esc_html_e( 'Get your API key from https://console.anthropic.com/', 'wpchatbot' ); ?> </small>
								</div>
							</div>
							<div class="row gx-0">
								<div class="form-group mb-3">
									<label for="qcld_voyage_api_key" class="form-label"><?php esc_html_e( 'Voyage AI API Key (Required for RAG)', 'wpchatbot' ); ?></label>
									<div class="input-group">
										<input type="password" class="form-control" id="qcld_voyage_api_key" name="qcld_voyage_api_key" placeholder="Enter your Voyage AI API Key" value="<?php echo esc_attr( get_option( 'qcld_voyage_api_key' ) ); ?>">
									</div>
									<small class="form-text text-muted"><?php esc_html_e( 'Anthropic uses Voyage AI for embeddings. Get your Voyage API key from https://dash.voyageai.com/api-keys to use RAG with Claude.', 'wpchatbot' ); ?> </small>
								</div>
							</div>
							<div class="row gx-0">
								<div class="form-group mb-3">
									<label for="qcld_claude_model" class="form-label"><?php esc_html_e( 'Select Claude Model', 'wpchatbot' ); ?></label>
									<select class="form-control" id="qcld_claude_model" name="qcld_claude_model">
										<?php 
										$models = array(
											'claude-fable-5' => 'Claude Fable 5',
											'claude-opus-4-8' => 'Claude Opus 4.8',
											'claude-opus-4-7' => 'Claude Opus 4.7',
											'claude-sonnet-4-6' => 'Claude Sonnet 4.6 (Latest Sonnet)',
											'claude-opus-4-6' => 'Claude Opus 4.6',
											'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
											'claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5',
										);
										$current_model = get_option( 'qcld_claude_model' );
										if($current_model == 'claude-3-opus-latest') {
											$current_model = 'claude-3-opus-20240229';
										}
										foreach($models as $key => $val) {
											$selected = ($key == $current_model) ? 'selected' : '';
											echo '<option value="' . esc_attr( $key ) . '" ' . $selected . '>' . esc_html( $val ) . '</option>';
										}
										?>
									</select>
								</div>
							</div>
							<div class="row g-0"> 
								<div class="gx-0">
									<div class="form-group mb-3">
										<label for="qcld_claude_system_content" class="form-label"><?php esc_html_e( 'System Content for Claude (Optional)', 'wpchatbot' ); ?></label>
										<textarea rows="5" class="form-control" id="qcld_claude_system_content" name="qcld_claude_system_content" placeholder="Content for the response"><?php echo esc_textarea( get_option( 'qcld_claude_system_content' ) ); ?></textarea>										
									</div>
								</div>
								<div class="gx-0">
									<div class="form-group mb-3">
										<label for="qcld_claude_prepend_content" class="form-label"><?php esc_html_e( 'Your Prompt to be Added before the User Query for Customized Results (Optional)', 'wpchatbot' ); ?></label>
										<textarea rows="5" class="form-control" id="qcld_claude_prepend_content" name="qcld_claude_prepend_content" placeholder="Content for the response"><?php echo esc_textarea( get_option( 'qcld_claude_prepend_content' ) ); ?></textarea>
										
									</div>
								</div>
								<div class="gx-0">
									<div class="form-group mb-3">
										<label for="qcld_claude_append_content" class="form-label"><?php esc_html_e( 'Your Prompt to be Appended at the End of the User Query for Customized Results (Optional)', 'wpchatbot' ); ?></label>
										<textarea rows="5" class="form-control" id="qcld_claude_append_content" name="qcld_claude_append_content" placeholder="Content for the response"><?php echo esc_textarea( get_option( 'qcld_claude_append_content' ) ); ?></textarea>
										
									</div>
								</div>
								<div class="form-group mb-3">
									<a class="btn btn-success" id="qcld_save_claude_setting"><?php esc_html_e( 'Save settings', 'wpchatbot' ); ?></a>
								</div>
							</div>
						</div>
						<div id="wp-chatbot-claude-help" class="tab-pane">
						<?php
								require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/claude/admin/help.php';
						?>
						</div>
						<div id="wp-chatbot-claude-rag" class="tab-pane">
						<?php
								require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/claude/admin/rag-manager.php';
						?>
						</div>
					</div>
				</div>
