<div class="wrap qcld-main-wrapper">
    <div class="qcld-wp-chatbot-wrap-header-aisection">
        <div class="qcld-wp-chatbot-wrap-header">
            <a href="#" class="qcld-wp-chatbot-wrap-site__logo"><img style="width:100%" src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/chatbot.png' ); ?>" alt="Dialogflow CX"> WPBot Control Panel </a>
            <p><strong>Core Version:</strong> v<?php echo QCLD_wpCHATBOT_VERSION; ?></p>
            <ul class="qcld-wp-chatbot-wrap-version-wrapper">
                <li>
            <a class="wpchatbot-Upgrade" href="https://www.wpbot.pro/" target="_blank">Upgrade To Pro</a> 
            
            </li>
            </ul>
        </div>
    </div>
</div>

<div class="qcl-openai">
    <div class="row gx-0">

            <div class="card admin-maxwith  qcld-openai-main-box">
                <div class="card-header bg-dark text-white py-sm-4 border-0">
                    <div class="row">
              
                        <div class="col-auto me-auto ai-settings-title-container">
                         
                                <a href="<?php echo admin_url( 'admin.php?page=wpbot_openAi' ); ?>"><h4><?php esc_html_e( 'AI Settings','chatbot');?></h4></a> 
                            
                            <select id="ai-provider-selector" class="form-select ai-settings-selector">
                                <option value="openai" <?php echo (get_option( 'ai_enabled') == 1) ? esc_attr( 'selected') :'';?> ><?php echo esc_html__( 'OpenAI','chatbot')?></option>
                                <option value="openrouter" <?php echo (get_option( 'qcld_openrouter_enabled') == 1) ? esc_attr( 'selected') :'';?> ><?php echo esc_html__( 'OpenRouter','chatbot')?></option>
                                <option value="gemini" <?php echo (get_option( 'qcld_gemini_enabled') == 1) ? esc_attr( 'selected') :'';?> ><?php echo esc_html__( 'Gemini','chatbot')?></option>
                            </select>
                            <div class="col-auto ai-settings-title-container">
                                <button id="ai-knowledge-base-tab" class="qcld-btn-primary" link="page=wpbot_openAi#ai-knowledge-base-tab"><?php esc_html_e( 'Knowledge Base (RAG)', 'wpchatbot' ); ?></button>
                            </div>
                        </div>
    

                    </div>
                </div>
				<?php
				if ( get_option( 'ai_enabled' ) != 1 && get_option( 'qcld_openrouter_enabled' ) != 1 && get_option( 'qcld_gemini_enabled' ) != 1 ) {
					?>
							<div id="openai-settings" class="ai-settings-provider">
                            <?php require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/openai/admin/admin_ui2.php'); ?>
							</div>
						
					<?php
				}
				?>
                <div id="openai-settings" class="ai-settings-provider" <?php echo (get_option( 'ai_enabled') == 1) ? 'style="display: block;"' :'style="display: none;"';?> >
                    <?php require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/openai/admin/admin_ui2.php'); ?>
                </div>
                <div id="openrouter-settings" class="ai-settings-provider" <?php echo (get_option( 'qcld_openrouter_enabled') == 1) ? 'style="display: block;"' :'style="display: none;"';?> >
                    <?php require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/openrouter/admin/settings.php'); ?>
                </div> 
                <div id="gemini-settings" class="ai-settings-provider" <?php echo (get_option( 'qcld_gemini_enabled') == 1) ? 'style="display: block;"' :'style="display: none;"';?> >
                    <?php require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/gemini/admin/settings.php'); ?>
                </div>
                <div id="rag-settings" class="ai-settings-provider" style="display: none;">
					<?php require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/admin/templates/rag.php'; ?>
				</div>
                <div class="card-footer bg-dark text-white py-sm-4 border-0"></div>
            </div>



    </div>
</div>
</div>  




<style>

div#promotion-wpchatbot {
    margin: 0;
    padding: 0;
    border: none;
    max-width: initial !important;
    padding: 0 !important;
    margin: 20px 20px 20px 0 !important;
    padding: 15px 15px 15px 0 !important;
    border: none !important;
    border-radius: 6px !important;
    box-shadow: 0px 4px 6px 1px #ebebeb !important;
}


.qc-review-notice{
    max-width: initial !important;
    padding: 0 !important;
    margin: 20px 20px 20px 0 !important;
    padding: 15px 15px 15px 0 !important;
    border: none !important;
    border-radius: 6px !important;
    box-shadow: 0px 4px 6px 1px #ebebeb !important;
    background: #fff;
    color: #000;
}

.qc-review-text h3 {
    color: #000000;
}
.qc-review-text p {
    color: #000000;
}
</style>




