
   
                <div class="card-body p-sm-0">
                    
                    <ul class="nav nav-tabs">
                        <li class="active"><a data-toggle="tab" href="#wp-chatbot-openrouter-settings"><i class="dashicons dashicons-admin-home"></i><?php echo esc_html__('OpenRouter settings', 'chatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-openrouter-rag"><i class="dashicons dashicons-admin-generic"></i><?php echo esc_html__('RAG', 'chatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-openrouter-help"><i class="dashicons dashicons-editor-help"></i><?php echo esc_html__('OpenRouter Help', 'chatbot'); ?></a></li>
                    </ul>

                    <div class="tab-content">
                        <div id="wp-chatbot-openrouter-settings" class="tab-pane in active">
                          <div class="col-sm-12">  
                        
                            <div class="row gx-0">
                                <div class="mb-3">
                                    <div class="form-check form-switch my-4">
                                        <input class="form-check-input" type="checkbox" <?php echo (get_option('qcld_openrouter_enabled') == 1) ? esc_attr('checked','chatbot') :'';?>  role="switch" value="" id="<?php esc_attr_e('qcld_openrouter_enabled','chatbot'); ?>">
                                        <label class="form-check-label" for="<?php esc_attr_e('qcld_openrouter_enabled','chatbot'); ?>">
                                        <?php esc_html_e('Enable OpenRouter AI','chatbot'); ?><span style="color:red"> <?php esc_html_e('(if you want results from OpenRouter only, disable Site Search from Settings->Start Menu)','chatbot'); ?></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="row gx-0">
                            <div class="form-check form-switch my-4">
                                <input class="form-check-input" type="checkbox" <?php echo (get_option('opnrouter_context_awareness_enabled') == '1') ? esc_attr( 'checked','chatbot') :'';?>  role="switch" value="" id="opnrouter_is_context_awareness_enabled">
                                <label class="form-check-label" for="opnrouter_is_context_awareness_enabled">
                                <?php  esc_html_e( 'Context awareness','chatbot'); ?>
                                </label>
                      
                            </div>
                            </div>
                            <div class="row gx-0">
                                <div class="mb-3">
                                    <div class="form-check form-switch my-4">
                                        <input class="form-check-input" type="checkbox" <?php echo (get_option('qcld_openrouter_page_suggestion_enabled') == '1') ? esc_attr( 'checked','chatbot') :'';?>  role="switch" value="" id="qcld_openrouter_page_suggestion_enabled">
                                        <label class="form-check-label" for="<?php esc_attr_e( 'qcld_openrouter_page_suggestion_enabled','chatbot'); ?>">
                                        <?php  esc_html_e( 'Enable page suggestions with OpenRouter Result','chatbot'); ?>
                                        </label>
                                    </div>
                          
                                <!-- POST TYPE -->
                                <div class="form-check form-switch my-4">
								<label><?php esc_html_e( 'Select POST TYPE(s) to include with search results', 'chatbot' ); ?></label>
									<div id="wp-chatbot-post-converter">
										<ul class="checkbox-list">
											<?php
												$get_cpt_args = array(
													'public'   => true,
												);
												$post_types = get_post_types($get_cpt_args, 'object');
												foreach ($post_types as $post_type) {
													if ($post_type->name != 'attachment') {
														$is_pro = !in_array($post_type->name, ['post', 'page']);
														?>
														<div class="form-check form-check-inline">
															<input
																id="site_openrouter_search_posttypes_<?php echo esc_html( $post_type->name ); ?>"
																type="checkbox"
																name="site_openrouter_search_posttypes[]"
																value="<?php echo esc_html( $post_type->name ); ?>"
																<?php echo (($is_pro) ? 'disabled' : ''); ?>
                                                                
																<?php echo ((get_option('qcld_openai_relevant_post') != '') && in_array($post_type->name, get_option('qcld_openai_relevant_post'))) ? 'checked' : ''; ?>>
															<label class="form-check-label <?php echo ($is_pro ? 'pro-locked' : ''); ?>" for="site_openrouter_search_posttypes_<?php echo esc_html( $post_type->name ); ?>">
																<?php echo esc_html( $post_type->name ); ?>
																<?php if ($is_pro) { ?>
																	<span class="pro-badge">Pro</span>
																<?php } ?>
															</label>
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
                                <div class="mb-3 form-check ">
                                    <label for="qcld_openrouter_api_key" class="form-label"><?php esc_html_e('OpenRouter API Key','chatbot');?></label>
                                    <input type="password" class="form-control" id="qcld_openrouter_api_key" name="qcld_openrouter_api_key" placeholder="Enter your OpenRouter API Key" value="<?php echo esc_attr(get_option('qcld_openrouter_api_key')); ?>">
                                    <small class="form-text text-muted"><?php esc_html_e('Get your API key from https://openrouter.ai/settings/keys','chatbot'); ?></small>
                                </div>
                            </div>
                            <div class="row g-0"> 
                                <div class="mb-3 form-check ">
                                    <label for="qcld_openrouter_model" class="form-label"><?php esc_html_e('OpenRouter Model','chatbot');?></label>
                                    <select id="qcld_openrouter_model" class="form-control" name="qcld_openrouter_model" data-current-model="<?php echo esc_attr(get_option('qcld_openrouter_model')); ?>">
                                        <option value=""><?php esc_html_e('Loading models...','chatbot'); ?></option>
                                    </select>
                                    <small class="form-text text-muted"><?php esc_html_e('Select a model from OpenRouter','chatbot'); ?></small>
                                </div>
                                
                                <div class="row gx-0">
                                    <div class="mb-3 form-check ">
                                        <label for="qcld_openrouter_prepend_content" class="form-label"><?php esc_html_e('Your Prompt to be Added before the User Query for Customized Results (Optional)','chatbot');?></label>
                                        <input type="text" class="form-control" id="qcld_openrouter_prepend_content" name="qcld_openrouter_prepend_content" placeholder="Content for the response" value="<?php echo esc_attr( get_option('qcld_openrouter_prepend_content') ); ?>">
                                        
                                    </div>
                                </div>

                                <div class="row gx-0">
                                    <div class="mb-3 form-check ">
                                        <label for="qcld_openrouter_append_content" class="form-label"><?php esc_html_e('Your Prompt to be Appended at the End of the User Query for Customized Results (Optional)','chatbot');?></label>
                                        <input type="text" class="form-control" id="qcld_openrouter_append_content" name="qcld_openrouter_append_content" placeholder="Content for the response" value="<?php echo esc_attr( get_option('qcld_openrouter_append_content') ); ?>">
                                        
                                    </div>
                                </div>
                                </div>
                                <div class="mb-3">
                                    <a class="btn btn-success" id="qcld_save_openrouter_setting"><?php esc_html_e('Save settings','chatbot');?></a>
                                </div>
                            </div>

                            
                        </div>
                        <div id="wp-chatbot-openrouter-rag" class="tab-pane">
                            <div class="col-sm-12">
                                <div class="wrap">
                                    <h3>OpenRouter RAG Settings</h3>
                                    <p>If you enable RAG, you must configure the <a id="ai-knowledge-base-tab-openrouter" href="<?php echo admin_url('admin.php?page=wpbot_openAi#ai-knowledge-base-tab'); ?>">Knowledgebase</a> for Post types and other data to embed.</p>
                                    <div class="form-check form-switch my-4">
                                        <input class="form-check-input"
                                            type="checkbox"
                                            id="is_page_rag_enabled_openrouter"
                                            <?php echo (get_option('is_page_rag_enabled') == '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_page_rag_enabled_openrouter">
                                            Enable RAG
                                        </label>
                                    </div>
                                    <div class="mb-3">
                                        <a class="btn btn-success" id="save_setting"><?php esc_html_e( 'Save settings','chatbot');?></a>
                                    </div>
                                </div>
                                    <div class="qcld-row">
                                        <div class="alert alert-info mt-20" role="alert">
                                            <p><b><?php echo esc_html__( 'Fine Tuning VS GPT Assistants VS RAG:', 'wpchatbot' ); ?></b></p>
                                            <p>
                                            <?php echo esc_html__( 'We suggest using GPT Assistants or RAG instead of Fine Tuning as Fine Tuning requires a lot of properly formatted data and GPT Assistants are easier to set up. You can still use your website data to train the bot.', 'wpchatbot' ); ?>
                                            </p></br>
                                            <p>
                                            <b><?php echo esc_html__( 'How to Use RAG in This Plugin:', 'wpchatbot' ); ?></b>
                                            <ol>
                                                <li><?php echo esc_html__( 'Enable RAG from the settings panel', 'wpchatbot' ); ?></li>
                                                <li><?php echo esc_html__( 'Click “Embed All Selected Sources” button, after selecting the sources from the', 'wpchatbot' ); ?> <a href="<?php echo admin_url('admin.php?page=wpbot_openAi#ai-knowledge-base-tab'); ?>" target="_blank">knowledgebase tab</a></li>
                                                <li><?php echo esc_html__( '(Optional) Upload PDFs or CSV files for embedding', 'wpchatbot' ); ?></li>
                                                <li><?php echo esc_html__( 'The system automatically stores embeddings in the database', 'wpchatbot' ); ?></li>
                                                <li><?php echo esc_html__( 'User questions will now be answered using your site’s knowledge base', 'wpchatbot' ); ?></li> 
                                                <li><?php echo esc_html__( 'You need to configure the OpenAI API key, AI Model and System Command under the main OpenAI Settings', 'wpchatbot' ); ?></li> 
                                            </ol>
                                            <strong><?php echo esc_html__( 'You can update or re-embed content at any time without retraining.', 'wpchatbot' ); ?></strong>
                                            </p>
                                            
                                        </div>

                                </div>	
                            </div>
                        </div>
                        <div id="wp-chatbot-openrouter-help" class="tab-pane">
                            <div class="accordion" id="qcldopenaiaccordion">
                                <div class="card">
                                    <div class="card-header" id="panelsStayOpen-headingZero-openrouter">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#panelsStayOpen-collapseZero-openrouter" aria-expanded="true" aria-controls="panelsStayOpen-collapseZero-openrouter">
                                                <?php esc_html_e( 'Getting Started with openrouter','chatbot');?>
                                            </button>
                                        </h2>
                                    </div>
                                    <div id="panelsStayOpen-collapseZero-openrouter" class="collapse" aria-labelledby="panelsStayOpen-headingZero-openrouter" data-parent="#qcldopenaiaccordion">
                                        <div class="card-body-openrouter">
                                            <h5><?php esc_html_e( 'OpenRouter is an unified Interface or Aggregator for LLMs. You can choose from hundreds of different AI models from OpenAI to Deepseek or Claude to get AI responses.','chatbot');?></h5>
                                            <h5><?php esc_html_e( 'All you have to do is add the OpenRouter API Key and select an OpenRouter Model.','chatbot');?></h5>
                                            <h5><?php esc_html_e( 'Grab your OpenAI API key from','chatbot');?> <a href="https://openrouter.ai/settings/keys">HERE</a></h5>
                                            <p><?php esc_html_e( 'Please make sure that DialogFlow, OpenAI are Disabled if you want OpenRouter to work.','chatbot');?></p>
                                        </div>
                                    </div>
                                </div>



                            </div>
                        </div>
                </div>
   </div>