<div class="row g-0">
    <div class="col-sm-10">
        <div class="form-check form-switch my-4">
            <input class="form-check-input" type="checkbox" <?php echo (get_option( 'ai_enabled') == 1) ? esc_attr( 'checked','chatbot') :'';?>  role="switch" value="" id="is_ai_enabled">
            <label class="form-check-label" for="is_ai_enabled">
            <?php  esc_html_e( 'Enable Open AI ','chatbot'); ?><span style="color:red"> <?php  esc_html_e( '(if you want results from OpenAI only, disable Site Search from Settings->Start Menu)','chatbot'); ?></span>
            </label>
        </div>
    
        <div class="form-check form-switch my-4">
            <input class="form-check-input" type="checkbox" <?php echo (get_option('page_suggestion_enabled') == '1') ? esc_attr( 'checked','chatbot') :'';?>  role="switch" value="" id="is_page_suggestion_enabled">
            <label class="form-check-label" for="is_page_suggestion_enabled">
            <?php  esc_html_e( 'Enable WordPress page suggestions with GPT Results (the links are suggested by WordPress and not AI)','chatbot'); ?>
            </label>
        </div>
        <div class="mb-3">
                <label for="api_key" class="form-label"><?php esc_html_e( 'Api key','chatbot');?></label>
                <input type="password" class="form-control" id="api_key" name="api_key" placeholder="Api key" value="<?php echo esc_attr(get_option( 'open_ai_api_key')); ?>">
        </div>
        <div class="mb-3">
            <label for="max_tokens" class="form-label"><?php esc_html_e( 'Max tokens (0-4000) Depending on the model','chatbot');?></label>
            <input id="max_tokens" class="form-control" type="text" name="max_tokens" value="<?php echo esc_attr(get_option( 'openai_max_tokens')); ?>">
        </div>
        <div class="mb-3">
            <div class="row gx-0">
                <div class="col-8">
                    <label for="temperature" class="form-label"><?php esc_html_e( 'Temperature','chatbot');?></label>
                </div>
                <div class="col-4 me-auto text-end">
                    <span name="temperatureout" id="temperatureout" ><?php echo esc_html(get_option( 'openai_temperature')); ?></span></div>
                </div>
            <input id="temperature" type="range" class="form-range" min="0" max="2" step="0.01" name="temperature" value="<?php echo esc_attr(get_option( 'openai_temperature')); ?>"  onchange="updateTemp(this.value);" />
            <label class="mb-3">
                <small><?php  esc_html_e( 'Temperature is a value between 0 and 2 that essentially lets you control how confident the model should be when making these predictions','chatbot');?></small>
            </label>
            <span name="temperatureout" id="temperatureout" ><?php  echo esc_html(get_option( 'openai_temperature')); ?></span>
        </div>
        <div class="mb-3">
            <div class="row gx-0"><div class="col-8"><label for="presence_penalty" class="form-label"><?php esc_html_e( 'Presence Penalty','chatbot');?></label></div><div class="col-4 me-auto text-end"><span id="presence_penalty_out" ><?php echo esc_html(get_option( 'presence_penalty')); ?></span></div></div>
            <input id="presence_penalty" type="range" class="form-range" min="0" max="2" step="0.1" name="presence_penalty" value="<?php echo esc_attr(get_option( 'presence_penalty')); ?>">
            <p class="mb-3"><small><?php  esc_html_e( 'Number between 0 and 2.0. Positive values penalize new tokens based on whether they appear in the text so far, increasing the model’s likelihood to talk about new topics.','chatbot');?></small></p>
        </div>
        <div class="mb-3">
            <div class="row gx-0"><div class="col-8"><label for="frequency_penalty" class="form-label"><?php esc_html_e( 'Frequency Penalty','chatbot');?></label></div><div class="col-4 me-auto text-end"><span id="frequency_penalty_out" ><?php echo esc_html(get_option( 'frequency_penalty')); ?></span></div></div>
            <input id="frequency_penalty" type="range" class="form-range" min="0" max="2" step="0.1" name="frequency_penalty" value="<?php echo esc_attr(get_option( 'frequency_penalty'));  ?>">
            <label><small><?php  esc_html_e( 'Number between 0 and 2.0. Positive values penalize new tokens based on their existing frequency in the text so far, decreasing the model’s likelihood to repeat the same line verbatim.','chatbot');?></small></label>
        </div>

        <div class="mb-3">
            <label for="max_tokens" id="openai_engines" class="form-label"><?php esc_html_e( 'OpenAI Model','chatbot');?></label>
            <select class="form-select" aria-label="Default select example" name="openai_engines" id="openai_engines">
                <option value="gpt-4.1-mini" <?php echo ((get_option( 'openai_engines') == 'gpt-4.1-mini') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-4.1-Mini','chatbot');?></option>
                <option value="gpt-4.1-nano" <?php echo ((get_option( 'openai_engines') == 'gpt-4.1-nano') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-4.1-Nano','chatbot');?></option>
                <option value="gpt-4.1" <?php echo ((get_option( 'openai_engines') == 'gpt-4.1') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-4.1','chatbot');?></option>
                <option value="gpt-4o-mini" <?php echo ((get_option( 'openai_engines') == 'gpt-4o-mini') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-4o-Mini','chatbot');?></option>
                <option value="gpt-4o" <?php echo ((get_option( 'openai_engines') == 'gpt-4o') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-4o','chatbot');?></option>
                <option value="gpt-4-turbo" <?php echo ((get_option( 'openai_engines') == 'gpt-4-turbo') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'gpt-4-turbo','chatbot');?></option>
                <option value="gpt-4" <?php echo ((get_option( 'openai_engines') == 'gpt-4') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-4','chatbot');?></option>
                <option value="gpt-3.5-turbo" <?php echo ((get_option( 'openai_engines') == 'gpt-3.5-turbo') ? esc_attr('selected') : '') ; ?>><?php esc_html_e( 'GPT-3 turbo','chatbot'); ?></option>
                
                
            </select>
        </div> 
        
        <div class="mb-3">
            <label for="qcld_openai_system_content"><?php esc_attr_e( 'System Command (Use it to Instruct ChatGPT how to behave)','chatbot');?></label>
            <textarea type="text" class="form-control" id="qcld_openai_system_content" placeholder="<?php echo esc_attr('You are a helpful Assistant. Be concise and relevant in your answers and do not introduce new topic.'); ?>"><?php  echo esc_html( get_option( 'qcld_openai_system_content')); ?></textarea>
            <label><small><?php esc_html_e("To set the ChatBot's tone and character set a system message according to your need",'chatbot'); ?></small></label></br>
            <label><small><?php esc_html_e("Example: You are a helpful Assistant. Be concise and relevant in your answers and do not introduce new topic.",'chatbot'); ?></small></label>
        </div>
        <div class="alert alert-warning"> 
           <p> <?php echo esc_html('Danger Zone'); ?></p>
        </div>
        <div class="mb-3">
            <label for="qcld_openai_include_keyword"><?php esc_attr_e( 'Connect to OpenAI only when user query includes one of the following Comma Separated Keywords','chatbot');?></label>
            <textarea type="text" class="form-control" id="qcld_openai_include_keyword"><?php echo esc_attr( get_option( 'openai_include_keyword')); ?></textarea>
        </div>
        <div class="mb-3">
            <label for="qcld_openai_exclude_keyword"><?php esc_attr_e( 'Connect to OpenAI only when user query does NOT include one of the following Comma Separated Keywords','chatbot');?></label>
            <textarea type="text" class="form-control" id="qcld_openai_exclude_keyword"><?php  echo esc_attr( get_option( 'openai_exclude_keyword')); ?></textarea>
        </div>
        <div class="mb-3">
            <div class="form-check form-switch my-4">
                <input class="form-check-input" type="checkbox" <?php echo (get_option( 'qcld_openai_relevant_enabled') == 1) ? esc_attr( 'checked','chatbot') :'';?>  role="switch" value="" id="is_relevant_enabled">
                <label class="form-check-label" for="is_relevant_enabled">
                <?php  esc_html_e( 'Ask OpenAI to reply when question is relevant to above Keywords (Enabling this option will improve accuracy but it will use OpenAI Tokens)','chatbot'); ?>
                </label>
            </div>
        </div>
   
        <div class="mb-3">
            <a class="btn btn-success" id="save_setting"><?php esc_html_e( 'Save settings','chatbot');?></a>
        </div>
        <div class="mb-3">
            <a class="btn btn-warning" id="qcld_check_connection"><?php esc_html_e( 'Check Connection  ','chatbot');?><i class="dashicons dashicons-image-rotate" id="rotationloader"></i></a> <?php echo esc_html('Save the Settings first and then press the Check Connection button'); ?><br/>
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
