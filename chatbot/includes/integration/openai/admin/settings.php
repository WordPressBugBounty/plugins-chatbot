<div class="row g-0">
    <div class="col-sm-12">
        <div class="form-check form-switch my-4">
            <input class="form-check-input" type="checkbox" <?php echo (get_option( 'ai_enabled') == 1) ? esc_attr( 'checked','wpbot') :'';?>  role="switch" value="" id="is_ai_enabled">
            <label class="form-check-label" for="is_ai_enabled">
            <?php  esc_html_e( 'Enable OpenAI','wpbot'); ?><span style="color:red"> <?php  esc_html_e( '(if you want results from OpenAI only, disable Site Search from Settings->Start Menu)','wpbot'); ?></span>
            </label>
        </div>
        <div class="form-check form-switch my-4">
            <input class="form-check-input" type="checkbox" <?php echo (get_option('context_awareness_enabled') == '1') ? esc_attr( 'checked','wpbot') :'';?>  role="switch" value="" id="is_context_awareness_enabled">
            <label class="form-check-label" for="is_context_awareness_enabled">
            <?php  esc_html_e( 'Context awareness','wpbot'); ?>
            </label>
            
        </div>
        <div class="form-check form-switch my-4">
            <input class="form-check-input" type="checkbox" <?php echo (get_option('page_suggestion_enabled') == '1') ? esc_attr( 'checked','wpbot') :'';?>  role="switch" value="" id="is_page_suggestion_enabled">
            <label class="form-check-label" for="is_page_suggestion_enabled">
            <?php  esc_html_e( 'Enable WordPress page suggestions with GPT Results (the links are suggested by WordPress and not AI)','wpbot'); ?>
            </label>
        </div>

        		<!-- POST TYPE -->
		<div class="form-check form-switch my-4">
		    <label><?php esc_html_e( 'Select POST TYPE(s) to include with search results', 'wpchatbot' ); ?></label>
			<div id="wp-chatbot-post-converter">
				<ul class="checkbox-list">
					<?php
						$get_cpt_args = array(
							'public' => true,
						);
						$post_types   = get_post_types( $get_cpt_args, 'object' );

                        foreach ($post_types as $post_type) {
                            if ($post_type->name != 'attachment') {
                                $is_pro = !in_array($post_type->name, ['post', 'page']);
                                ?>
                                <div class="form-check form-check-inline">
                                    <input
                                        id="site_search_posttypes_<?php echo $post_type->name; ?>"
                                        type="checkbox"
                                        name="site_search_posttypes[]"
                                        value="<?php echo $post_type->name; ?>"
                                        <?php echo (($is_pro) ? 'disabled' : ''); ?>
                                       
                                        <?php echo ((get_option('qcld_openai_relevant_post') != '') && in_array($post_type->name, get_option('qcld_openai_relevant_post'))) ? 'checked' : ''; ?>>
                                    <label class="form-check-label <?php echo ($is_pro ? 'pro-locked' : ''); ?>" for="site_search_posttypes_<?php echo $post_type->name; ?>">
                                        <?php echo $post_type->name; ?>
                                        <?php if ($is_pro) { ?>
                                            <span class="pro-badge">PRO</span>
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
        <div class="mb-3 form-check">
                <label for="api_key" class="form-label"><?php esc_html_e( 'Api key','wpbot');?></label>
                <input type="password" class="form-control" id="api_key" name="api_key" placeholder="Api key" value="<?php esc_attr_e(get_option( 'open_ai_api_key'),'wpbot'); ?>">
        </div>
        <div class="qcld-wpbot-pricing-filter-form-check">
        </div>
         <div class="mb-3 form-check">
            <label for="max_tokens" id="openai_engines" class="form-label"><?php esc_html_e( 'OpenAI Model','wpbot');?></label>
            <select class="form-select" aria-label="Default select example" name="openai_engines" id="openai_engines">
                <option value="gpt-5-mini" <?php echo ((get_option( 'openai_engines') == 'gpt-5-mini') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-5-Mini','wpbot');?></option>
                <option value="gpt-5-nano" <?php echo ((get_option( 'openai_engines') == 'gpt-5-nano') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-5-Nano','wpbot');?></option>
                <option value="gpt-5" <?php echo ((get_option( 'openai_engines') == 'gpt-5') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-5','wpbot');?></option>
                <option value="gpt-4.1-mini" <?php echo ((get_option( 'openai_engines') == 'gpt-4.1-mini') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-4.1-Mini','wpbot');?></option>
                <option value="gpt-4.1-nano" <?php echo ((get_option( 'openai_engines') == 'gpt-4.1-nano') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-4.1-Nano','wpbot');?></option>
                <option value="gpt-4.1" <?php echo ((get_option( 'openai_engines') == 'gpt-4.1') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-4.1','wpbot');?></option>
                <option value="gpt-4o-mini" <?php echo ((get_option( 'openai_engines') == 'gpt-4o-mini') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-4o-Mini','wpbot');?></option>
                <option value="gpt-4o" <?php echo ((get_option( 'openai_engines') == 'gpt-4o') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-4o','wpbot');?></option>
                <option value="gpt-4-turbo" <?php echo ((get_option( 'openai_engines') == 'gpt-4-turbo') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'gpt-4-turbo','wpbot');?></option>
                <option value="gpt-4" <?php echo ((get_option( 'openai_engines') == 'gpt-4') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-4','wpbot');?></option>
                <option value="gpt-3.5-turbo" <?php echo ((get_option( 'openai_engines') == 'gpt-3.5-turbo') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-3 turbo','wpbot'); ?></option>
            </select>
        </div> 
        
        <div class="mb-3 form-check">
            <label for="qcld_openai_system_content"><?php esc_attr_e( 'System Command (Use it to Instruct ChatGPT how to behave). You can write a detailed prompt here that includes details about your services, products, and how to contact you or anything relevant to get','wpbot');?> <span class="qcls_openAI_customized"><?php esc_attr_e( 'Customized Results','wpbot');?></span> <?php esc_attr_e( '. Upto 3000 words is fine.','wpbot');?></label>
            <textarea type="text" class="form-control" id="qcld_openai_system_content" placeholder="<?php echo esc_attr('You are a helpful Assistant. Be concise and relevant in your answers and do not introduce new topic.'); ?>"><?php  echo esc_html( get_option( 'qcld_openai_system_content')); ?></textarea>
            <label><small><?php esc_html_e("To set the ChatBot's tone and character set a system message according to your need","wpbot"); ?></small></label></br>
            <label><small><?php esc_html_e("Example: You are a helpful Assistant. Be concise and relevant in your answers and do not introduce new topic.","wpbot"); ?></small></label>
        </div>
        <div class="mb-3 form-check">
            <label for="qcld_openai_append_content"><?php esc_attr_e( 'Prompt to be Appended at the End of the User Query (Optional)','wpbot');?></label>
            <textarea type="text" class="form-control" id="qcld_openai_append_content" placeholder="<?php echo esc_attr('Content for the response'); ?>"><?php  echo esc_html( get_option( 'qcld_openai_append_content')); ?></textarea>

        </div>
        <div class="alert alert-warning"> 
           <p> <?php echo esc_html('Danger Zone (you may not get any responses from AI if the keywords are not set properly. Remove keywords if you face problems )'); ?></p>
        </div>
         <div class="mb-3 form-check">
            <label for="qcld_openai_include_keyword"><?php esc_attr_e( 'Connect to OpenAI only when user query includes one of the following Comma Separated Keywords','wpbot');?></label>
            <textarea type="text" class="form-control" id="qcld_openai_include_keyword"><?php echo esc_attr( get_option( 'openai_include_keyword')); ?></textarea>
        </div>
         <div class="mb-3 form-check">
            <label for="qcld_openai_exclude_keyword"><?php esc_attr_e( 'Connect to OpenAI only when user query does NOT include one of the following Comma Separated Keywords','wpbot');?></label>
            <textarea type="text" class="form-control" id="qcld_openai_exclude_keyword"><?php  echo esc_attr( get_option( 'openai_exclude_keyword')); ?></textarea>
        </div>
        <div class="mb-3">
            <div class="form-check form-switch my-4">
                <input class="form-check-input" type="checkbox" <?php echo (get_option( 'qcld_openai_relevant_enabled') == 1) ? esc_attr( 'checked','wpbot') :'';?>  role="switch" value="" id="is_relevant_enabled">
                <label class="form-check-label" for="is_relevant_enabled">
                <?php  esc_html_e( 'Ask OpenAI to reply when question is relevant to above Keywords (Enabling this option will improve accuracy but it will use OpenAI Tokens)'); ?>
                </label>
            </div>
        </div>
   
        <div class="mb-3">
            <a class="btn btn-success" id="save_setting"><?php esc_html_e( 'Save settings','wpbot');?></a>
        </div>
        <div class="mb-3 form-check">
            <a class="btn btn-warning" id="qcld_check_connection"><?php esc_html_e( 'Check Connection  ','wpbot');?><i class="fa fa-spinner" id="rotationloader"></i></a> <?php echo esc_html('Save the Settings first and then press the Check Connection button'); ?><br/>
            <div id="qcld_openAI_trubleshooter"></div>
        </div>
        <div class="alert alert-danger"> 
           <p> <?php echo esc_html('**If OpenAI is not responding back and the bot is just loading, then likely you hit your OpenAI usage limit. Please pre-purchase credit to use OpenAI API and increase the Usage limit. You can add credits to your API account by visiting the '); ?> <a href="https://platform.openai.com/account/billing"><?php echo esc_html('billing page.'); ?></a></p>
           <p>
           <a href="https://wpbot.pro/docs/knowledgebase/how-to-save-money-and-reduce-openai-api-cost-for-your-chatbot/"> <?php echo esc_html('How to reduce cost '); ?></a><?php echo esc_html('and save money on OpenAI API cost for your ChatBot.'); ?>
        </p>
        </div>
    </div>
</div>
