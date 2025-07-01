<?php 
global $wpchatbot_pro_professional_init,$wpchatbot_pro_master_init;
if((isset($wpchatbot_pro_master_init) && $wpchatbot_pro_master_init->is_valid()) || (isset($wpchatbot_pro_professional_init) && $wpchatbot_pro_professional_init->is_valid()) || (function_exists('get_openaiaddon_valid_license') && get_openaiaddon_valid_license())){
?>
<div class="row g-0">
    <div class="col-sm-10">
        <div class="form-check form-switch my-4">
            <input class="form-check-input" type="checkbox" <?php echo (get_option( 'is_asst_enabled') == 1) ? esc_attr( 'checked','wpbot') :'';?>  role="switch" value="" id="is_assistant_enabled','wpbot'); ?>">
            <label class="form-check-label" for="is_assistant_enabled','wpbot'); ?>">
            <?php  esc_html_e( 'Enable Open AI Assistants instead of fine tune','wpbot'); ?>
            </label>
        </div>
        <div class="mb-3">
            <label for="qcld_openai_assistants" class="form-label"><?php esc_html_e( 'Assistants ID','wpbot');?></label>
            <input id="qcld_openai_assistants" class="form-control" type="text" name="qcld_openai_assistants" value="<?php echo esc_attr( get_option( 'qcld_openai_assistants')); ?>">
            <label><small><?php  esc_html_e( 'Copy your Assistants ID from the OpenAI Playground','wpbot');?></small></label>
        </div>
        
        <div class="mb-3">
            <label for="qcld_openai_assistants_file" class="form-label"><?php esc_html_e( 'Assistants File ID','wpbot');?></label>
            <input id="qcld_openai_assistants_file" class="form-control" type="text" name="qcld_openai_assistants_file" value="<?php echo esc_attr( get_option( 'qcld_openai_assistants_file')); ?>">
            <label><small><?php  esc_html_e( 'Copy your Assistants File ID from the OpenAI Playground','wpbot');?></small></label>
        </div>
        <div class="mb-3">
            <a class="btn btn-success" id="save_assistant_setting"><?php esc_html_e( 'Save settings','wpbot');?></a>
        </div>
    </div>
</div>
<?php
} else { ?>
<div class="row my-4">
    <div  class="col-md-12">
        <?php esc_html_e('GPT Assistant is available with the ');?>
        <a href="https://www.wpbot.pro/pricing/"><?php esc_html_e('WPBot Pro Professional'); ?></a>
        <?php esc_html_e(' and '); ?>
        <a href="https://www.wpbot.pro/pricing/"><?php esc_html_e('Master'); ?></a>
        <?php esc_html_e(' Licenses'); ?>
    </div>
</div>
<div class="alert alert-info" role="alert">
        <p><b><?php echo esc_html__('Fine Tuning VS GPT Assistants:', 'wpchatbot'); ?></b></p>
        <p>
            <?php echo esc_html__('We suggest using GPT Assistants instead of Fine Tuning as Fine Tuning requires a lot of properly formatted data and GPT Assistants are easier to set up. You can still use your website data to train the bot.', 'wpchatbot'); ?>
        </p></br>
        <p>
        <b><?php echo esc_html__('Setting up GPT Assistants:', 'wpchatbot'); ?></b>
        <ol>
            <li><?php echo esc_html__('Go to the GPT training with Website Data tab. Select all the Post types you need and press the button Convert Data for Assistant. Once it is done, download the data file in JSON format', 'wpchatbot'); ?></li>
            <li><?php echo esc_html__('Go to ', 'wpchatbot'); ?><a href="https://platform.openai.com/playground/assistants" target="_blank"><?php echo esc_html__('Assistants playground', 'wpchatbot'); ?></a><?php echo esc_html__(' and create a new Assistant', 'wpchatbot'); ?></li>
            <li><?php echo esc_html__('Upload the JSON file you downloaded and give the GPT an Instruction similar to the one below but customize it to your website URL:', 'wpchatbot'); ?>
            <p><?php echo esc_html__('Your name is Carry. You are a ChatBot assistant for the website ', 'wpchatbot'); ?><a href="https://www.quantumcloud.com" target="_blank"><?php echo esc_html__('https://www.quantumcloud.com. ', 'wpchatbot'); ?></a><?php echo esc_html__('Use the website data and the uploaded file to answer any questions from our website users.', 'wpchatbot'); ?></p>
            </li>
            <li><?php echo esc_html__('Copy the Assistants ID and paste under the GPT Assistants tab under the OpenAI settings on your website. You are done. If you have purchased credits from the OpenAI API platform, it will start working.', 'wpchatbot'); ?></li>
            <li><?php  echo esc_html__('You can use different GPT Assistants on different pages with chatbot Widgets. Paste this sample shortcode and replace the Assistant ID as needed: Ex: [chatbot-widget hud_design="lite" gpt_assistant_id="assst_12345678"]', 'wpchatbot'); ?></li> 
        </ol>
        <strong><a href="https://wpbot.pro/docs/knowledgebase/how-to-train-ai-with-your-website-data-using-chatbot/" target="_blank"><?php echo esc_html__('Please check this article for step by step guide.', 'wpchatbot'); ?></a></strong>
        </p>
        
    </div>
<?php } ?>