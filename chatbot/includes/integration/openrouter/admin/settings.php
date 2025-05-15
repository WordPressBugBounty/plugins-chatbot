
   
                <div class="card-body p-sm-0">
                    
                    <ul class="nav nav-tabs">
                        <li class="active"><a data-toggle="tab" href="#wp-chatbot-openrouter-settings"><?php echo esc_html__('OpenRouter settings', 'wpchatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-openrouter-help"><?php echo esc_html__('OpenRouter Help', 'wpchatbot'); ?></a></li>
                    </ul>

                    <div class="tab-content">
                        <div id="wp-chatbot-openrouter-settings" class="tab-pane in active">
                            <div class="row gx-0">
                                <div class="<?php esc_attr_e('mb-3','wpbot');?>">
                                    <div class="<?php esc_attr_e('form-check form-switch my-4','wpbot');?>">
                                        <input class="<?php esc_attr_e('form-check-input','wpbot');?>" type="checkbox" <?php echo (get_option('qcld_openrouter_enabled') == 1) ? esc_attr('checked','wpbot') :'';?>  role="switch" value="" id="<?php esc_attr_e('qcld_openrouter_enabled','wpbot'); ?>">
                                        <label class="<?php esc_attr_e('form-check-label','wpbot');?>" for="<?php esc_attr_e('qcld_openrouter_enabled','wpbot'); ?>">
                                        <?php esc_html_e('Enable OpenRouter AI','wpbot'); ?><span style="color:red"> <?php esc_html_e('(if you want results from OpenRouter only, disable Site Search from Settings->Start Menu)','wpbot'); ?></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="row gx-0">
                                <div class="<?php esc_attr_e( 'mb-3','wpbot');?>">
                                    <div class="<?php esc_attr_e( 'form-check form-switch my-4','wpbot');?>">
                                        <input class="<?php esc_attr_e( 'form-check-input','wpbot');?>" type="checkbox" <?php echo (get_option('qcld_openrouter_page_suggestion_enabled') == '1') ? esc_attr( 'checked','wpbot') :'';?>  role="switch" value="" id="<?php esc_attr_e( 'qcld_openrouter_page_suggestion_enabled','wpbot'); ?>">
                                        <label class="<?php esc_attr_e( 'form-check-label','wpbot');?>" for="<?php esc_attr_e( 'qcld_openrouter_page_suggestion_enabled','wpbot'); ?>">
                                        <?php  esc_html_e( 'Enable page suggestions with OpenRouter Result','wpbot'); ?>
                                        </label>
                                    </div>
                                <!-- POST TYPE -->
                                <div class="<?php esc_attr_e( 'form-check form-switch my-4','wpbot');?>">
                        
                                </div>
                             <!-- /POST TYPE -->
                                </div>  
                            </div>
                            <div class="row gx-0">
                                <div class="mb-3">
                                    <label for="<?php esc_attr_e('qcld_openrouter_api_key','wpbot');?>" class="<?php esc_attr_e('form-label','wpbot');?>"><?php esc_html_e('OpenRouter API Key','wpbot');?></label>
                                    <input type="password" class="<?php esc_attr_e('form-control','wpbot');?>" id="<?php esc_attr_e('qcld_openrouter_api_key','wpbot');?>" name="qcld_openrouter_api_key" placeholder="Enter your OpenRouter API Key" value="<?php esc_attr_e(get_option('qcld_openrouter_api_key'),'wpbot'); ?>">
                                    <small class="form-text text-muted"><?php esc_html_e('Get your API key from https://openrouter.ai/settings/keys','wpbot'); ?></small>
                                </div>
                            </div>
                            <div class="<?php esc_attr_e('row g-0','wpbot');?>"> 
                                <div class="<?php esc_attr_e('mb-3','wpbot');?>">
                                    <label for="<?php esc_attr_e('qcld_openrouter_model','wpbot');?>" class="<?php esc_attr_e('form-label','wpbot');?>"><?php esc_html_e('OpenRouter Model','wpbot');?></label>
                                    <select id="<?php esc_attr_e('qcld_openrouter_model','wpbot');?>" class="<?php esc_attr_e('form-control','wpbot');?>" name="qcld_openrouter_model" data-current-model="<?php echo esc_attr(get_option('qcld_openrouter_model')); ?>">
                                        <option value=""><?php esc_html_e('Loading models...','wpbot'); ?></option>
                                    </select>
                                    <small class="form-text text-muted"><?php esc_html_e('Select a model from OpenRouter','wpbot'); ?></small>
                                </div>
                                <div class="<?php esc_attr_e('mb-3','wpbot');?>">
                                    <a class="<?php esc_attr_e('btn btn-success','wpbot');?>" id="<?php esc_attr_e('qcld_save_openrouter_setting','wpbot');?>"><?php esc_html_e('Save settings','wpbot');?></a>
                                </div>
                            </div>
                        </div>
                        <div id="wp-chatbot-openrouter-help" class="tab-pane">
                            <div class="accordion" id="qcldopenaiaccordion">
                                <div class="card">
                                    <div class="card-header" id="panelsStayOpen-headingZero-openrouter">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#panelsStayOpen-collapseZero-openrouter" aria-expanded="true" aria-controls="panelsStayOpen-collapseZero-openrouter">
                                                <?php esc_html_e( 'Getting Started with openrouter','openai_addon');?>
                                            </button>
                                        </h2>
                                    </div>
                                    <div id="panelsStayOpen-collapseZero-openrouter" class="collapse show" aria-labelledby="panelsStayOpen-headingZero-openrouter" data-parent="#qcldopenaiaccordion">
                                        <div class="card-body">
                                            <h4><?php esc_html_e( 'Openrouter is a lightweight, easy-to-use, and fast alternative to OpenAI. It is a language model that can be used to generate text, images, and other media.','openai_addon');?></h4>
                                            <h5><?php esc_html_e( 'First, you add the openrouter Host URL, Port and Model name.','openai_addon');?></h5>
                                            <p><?php esc_html_e( 'Please make sure DialogFlow, OpenAI are Disabled if you want openrouter to work','openai_addon');?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="card">
                                    <div class="card-header" id="panelsStayOpen-headingOne-openrouter">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#panelsStayOpen-collapseOne-openrouter" aria-expanded="false" aria-controls="panelsStayOpen-collapseOne-openrouter">
                                                <?php esc_html_e( 'How to Setup Openrouter','openai_addon');?>
                                            </button>
                                        </h2>
                                    </div>
                                    <div id="panelsStayOpen-collapseOne-openrouter" class="collapse" aria-labelledby="panelsStayOpen-headingOne-openrouter " data-parent="#qcldopenaiaccordion">
                                        <div class="card-body">
                                            <p><?php esc_html_e( "Here the steps to setup openrouter:","openai_addon"); ?></p>
                                                    <ul>
                                                        <li><a href="https://openrouter.ai/settings/keys"><?php esc_html_e( 'https://openrouter.ai/settings/keys.','openai_addon');?></a></li>

                                                    </ul>
                                        </div>
                                    </div>
                                </div>


                            </div>
                        </div>
                </div>
   