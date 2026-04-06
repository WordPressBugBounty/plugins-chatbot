<?php

/**
 * AI settings Template for OpenAI, Gemini, OpenRouter and Grok
 * @package Botmaster
 */

$wpchatbot_license_valid            = get_option('wpchatbot_license_valid');
// $wpchatbot_license_valid            = 'starter';

?>

    <div class="qcl-openai">



        <h2 class="nav-tab-wrapper">
            <a href="#qcld-rate-limit" class="nav-tab nav-tab-active"></a>
            <a href="#qcldc" class="nav-tab">Sync and upload options</a>
            <a href="#qcld_r" class="nav-tab">KnowledgeBase Database</a>
        </h2>

        <div id="qcld-rag-settings-tab" class="qcld-tab-content active">
            <div class="wrap my-4">
                <p style="color: red"> <b><?php esc_html_e('Please connect to an AI service like OpenAI or Gemini before embedding. ', 'wpbot'); ?></b><b><a href="https://wpbot.pro/docs/knowledgebase/how-to-use-an-embedded-vector-database-and-rag-to-get-customized-responses-from-ai/"><?php esc_html_e('Check this Tutorial for more details.', 'wpbot'); ?></a></b></p>
                <form method="post" id="rag_embed_form">
                    <input type="hidden" name="embed_all_sources" value="1">
                    <button type="button" id="rag_embed_btn" class="button button-primary">Embed All Selected Sources</button>
                </form>

                <?php 
                    if (isset($_POST['embed_all_sources'])):
                        if( ( get_option( 'ai_enabled') == 1  && get_option('open_ai_api_key') ) || ( get_option('qcld_gemini_enabled') == 1 && get_option('qcld_gemini_api_key') ) || ( get_option('qcld_openrouter_enabled') == 1 && get_option('qcld_openrouter_api_key') ) ){
                ?>
                        <h3>Embedding started...</h3>
                        <?php  Qcld_Bot_Rag::instance()->wp_rag_embed_all_sources(); ?>
                 <?php    }else{ ?>
                    <Script>
                    swal.fire('', 'Please connect to an AI service like OpenAI or Gemini with API key before embedding.', 'warning');
                    </Script>
                <?php 
                    }
                endif;
                ?>
            </div>
            <div class="wrap">
                <button class="qcld-btn-primary" id="save_rag_setting">Save Settings</button>
            </div>
        </div>


     

