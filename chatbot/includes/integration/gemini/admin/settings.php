
   
                <div class="card-body p-sm-0">
                    
                    <ul class="nav nav-tabs">
                        <li class="active"><a data-toggle="tab" href="#wp-chatbot-gemini-settings"><i class="fa fa-cog"></i><?php echo esc_html__('Gemini settings', 'wpchatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-gemini-help"><i class="fa fa-question-circle"></i><?php echo esc_html__('Gemini Help', 'wpchatbot'); ?></a></li>
                    </ul>

                    <div class="tab-content">
                        <div id="wp-chatbot-gemini-settings" class="tab-pane in active">
                          <div class="col-sm-12">  
                        
                            <div class="row gx-0">
                                <div class="mb-3">
                                    <div class="form-check form-switch my-4">
                                        <input class="form-check-input" type="checkbox" <?php echo (get_option('qcld_gemini_enabled') == 1) ? esc_attr('checked','wpbot') :'';?>  role="switch" value="" id="<?php esc_attr_e('qcld_gemini_enabled','wpbot'); ?>">
                                        <label class="form-check-label" for="<?php esc_attr_e('qcld_gemini_enabled','wpbot'); ?>">
                                        <?php esc_html_e('Enable Gemini AI','wpbot'); ?><span style="color:red"> <?php esc_html_e('(if you want results from Gemini only, disable Site Search from Settings->Start Menu)','wpbot'); ?></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="row gx-0">
                                <div class="form-check form-switch my-4">
                                    <input class="form-check-input" type="checkbox" <?php echo (get_option('gemeni_context_awareness_enabled') == '1') ? esc_attr( 'checked','wpbot') :'';?>  role="switch" value="" id="gemini_is_context_awareness_enabled">
                                    <label class="form-check-label" for="gemini_is_context_awareness_enabled">
                                    <?php  esc_html_e( 'Context awareness','wpbot'); ?>
                                    </label>
                                    
                                </div>
                            </div>
                            <div class="row gx-0">
                                <div class="mb-3">
                                    <div class="form-check form-switch my-4">
                                        <input class="form-check-input" type="checkbox" <?php echo (get_option('qcld_gemini_page_suggestion_enabled') == '1') ? esc_attr( 'checked','wpbot') :'';?>  role="switch" value="" id="qcld_gemini_page_suggestion_enabled">
                                        <label class="form-check-label" for="<?php esc_attr_e( 'qcld_gemini_page_suggestion_enabled','wpbot'); ?>">
                                        <?php  esc_html_e( 'Enable page suggestions with Gemini Result','wpbot'); ?>
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
												$post_types = get_post_types($get_cpt_args, 'object');
												foreach ($post_types as $post_type) {
													if ($post_type->name != 'attachment') {
														$is_pro = !in_array($post_type->name, ['post', 'page']);
														?>
														<div class="form-check form-check-inline">
															<input
																id="site_gemini_search_posttypes_<?php echo $post_type->name; ?>"
																type="checkbox"
																name="site_gemini_search_posttypes[]"
																value="<?php echo $post_type->name; ?>"
																<?php echo (($is_pro) ? 'disabled' : ''); ?>
                                                                
																<?php echo ((get_option('qcld_openai_relevant_post') != '') && in_array($post_type->name, get_option('qcld_openai_relevant_post'))) ? 'checked' : ''; ?>>
															<label class="form-check-label <?php echo ($is_pro ? 'pro-locked' : ''); ?>" for="site_gemini_search_posttypes_<?php echo $post_type->name; ?>">
																<?php echo $post_type->name; ?>
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
                                    <label for="qcld_gemini_api_key" class="form-label"><?php esc_html_e('Gemini API Key','wpbot');?></label>
                                    <input type="password" class="form-control" id="qcld_gemini_api_key" name="qcld_gemini_api_key" placeholder="Enter your Gemini API Key" value="<?php esc_attr_e(get_option('qcld_gemini_api_key'),'wpbot'); ?>">
                                    <small class="form-text text-muted"><?php esc_html_e('Get your API key from https://gemini.ai/settings/keys','wpbot'); ?></small>
                                </div>
                            </div>
                            <div class="row g-0"> 
                                
                                <div class="row gx-0">
                                    <div class="mb-3 form-check ">
                                        <label for="qcld_gemini_prepend_content" class="form-label"><?php esc_html_e('Your Prompt to be Added before the User Query for Customized Results (Optional)','wpbot');?></label>
                                        <input type="text" class="form-control" id="qcld_gemini_prepend_content" name="qcld_gemini_prepend_content" placeholder="Content for the response" value="<?php echo esc_attr( get_option('qcld_gemini_prepend_content') ); ?>">
                                        
                                    </div>
                                </div>

                                <div class="row gx-0">
                                    <div class="mb-3 form-check ">
                                        <label for="qcld_gemini_append_content" class="form-label"><?php esc_html_e('Your Prompt to be Appended at the End of the User Query for Customized Results (Optional)','wpbot');?></label>
                                        <input type="text" class="form-control" id="qcld_gemini_append_content" name="qcld_gemini_append_content" placeholder="Content for the response" value="<?php echo esc_attr( get_option('qcld_gemini_append_content') ); ?>">
                                        
                                    </div>
                                </div>
                                </div>
                                <div class="mb-3">
                                    <a class="btn btn-success" id="qcld_save_gemini_setting"><?php esc_html_e('Save settings','wpbot');?></a>
                                </div>
                            </div>

                            
                        </div>
                        <div id="wp-chatbot-gemini-help" class="tab-pane">
                            <div class="accordion" id="qcldopenaiaccordion">
                                <div class="card">
                                    <div class="card-header" id="panelsStayOpen-headingZero-gemini">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#panelsStayOpen-collapseZero-gemini" aria-expanded="true" aria-controls="panelsStayOpen-collapseZero-gemini">
                                                <?php esc_html_e( 'Getting Started with gemini','wpbot');?>
                                            </button>
                                        </h2>
                                    </div>
                                    <div id="panelsStayOpen-collapseZero-gemini" class="collapse" aria-labelledby="panelsStayOpen-headingZero-gemini" data-parent="#qcldopenaiaccordion">
                                        <div class="card-body-gemini">
                                            <h5><?php esc_html_e( 'Gemini is an unified Interface or Aggregator for LLMs. You can choose from hundreds of different AI models from OpenAI to Deepseek or Claude to get AI responses.','wpbot');?></h5>
                                            <h5><?php esc_html_e( 'All you have to do is add the Gemini API Key and select an Gemini Model.','wpbot');?></h5>
                                            <h5><?php esc_html_e( 'Grab your OpenAI API key from','wpbot');?> <a href="https://gemini.ai/settings/keys">HERE</a></h5>
                                            <p><?php esc_html_e( 'Please make sure that DialogFlow, OpenAI are Disabled if you want Gemini to work.','wpbot');?></p>
                                        </div>
                                    </div>
                                </div>



                            </div>
                        </div>
                </div>
   