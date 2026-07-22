<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
$no_ai_active = (
	get_option( 'ai_enabled' ) != 1 &&
	get_option( 'qcld_openrouter_enabled' ) != 1 &&
	get_option( 'qcld_gemini_enabled' ) != 1 &&
	get_option( 'qcld_grok_enabled' ) != 1
);
$wizard_done = ( get_option( 'wpbot_ai_setup_wizard_done' ) == 1 );

$show_wizard_automatically = $no_ai_active;
require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/admin/templates/wizard-popup.php';
?>
<div class="wrap qcld-main-wrapper">
    <div class="qcld-wp-chatbot-wrap-header-aisection">
<div class="qcld-wp-chatbot-wrap-header">

    <div class="qcld-wp-chatbot-wrap-header-logo"><a href="#" class="qcld-wp-chatbot-wrap-site__logo"><img style="width:100%" src="<?php echo esc_url( QCLD_wpCHATBOT_IMG_URL . '/chatbot.png' ); ?>" alt="Dialogflow CX"> WPBot Control Panel </a>
    <p><strong>Core Version:</strong> v<?php echo esc_html( QCLD_wpCHATBOT_VERSION ); ?></p>
    </div>
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
                          
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbot_openAi' ) ); ?>"><h4><?php esc_html_e( 'AI Settings','chatbot');?></h4></a> 
                            
                            <?php
                            // Determine which provider is currently active
                            $active_provider = 'openai'; // default
                            if (get_option('qcld_grok_enabled') == 1) $active_provider = 'grok';
                            if (get_option('qcld_gemini_enabled') == 1) $active_provider = 'gemini';
                            if (get_option('qcld_openrouter_enabled') == 1) $active_provider = 'openrouter';
                            if (get_option('ai_enabled') == 1) $active_provider = 'openai';
                            ?>
                            <input type="hidden" id="ai-provider-selector" value="<?php echo esc_attr($active_provider); ?>">
                            
                            <div class="col-auto ai-settings-title-container">
                                <button id="wpbot-trigger-wizard" class="qcld-btn-primary"><?php esc_html_e( 'AI Wizard', 'chatbot' ); ?></button>
                            </div>
                            <div class="col-auto ai-settings-title-container">
                                <button id="ai-knowledge-base-tab" class="qcld-btn-primary" link="page=wpbot_openAi#ai-knowledge-base-tab"><?php esc_html_e( 'Knowledge Base (RAG)', 'chatbot' ); ?></button>     
                            </div>
                            <div class="col-auto ai-settings-title-container">
                                <button id="qcld-common-ai-settings" class="qcld-btn-primary" link="page=wpbot_openAi#common-ai-settings-tab"><?php esc_html_e( 'Common AI Settings', 'chatbot' ); ?></button>
                            </div>
                        </div>
    

                    </div>
                </div>
                <!-- AI Provider Cards - Separate Row -->
                <div class="ai-provider-cards-wrapper">
                   
                    <div class="ai-provider-cards">
                        <div class="ai-provider-card <?php echo ($active_provider === 'openai') ? 'active' : ''; ?>" data-provider="openai">
                            <span class="ai-provider-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="7" fill="#2563EB"/><path d="M4 7.2L6.2 9.4L10 5" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <div class="ai-provider-icon">
                                <svg viewBox="0 0 24 24" width="32" height="32" fill="none"><path d="M22.282 9.821a5.985 5.985 0 0 0-.516-4.91 6.046 6.046 0 0 0-6.51-2.9A6.065 6.065 0 0 0 4.28 4.082a5.985 5.985 0 0 0-3.998 2.9 6.046 6.046 0 0 0 .743 7.097 5.98 5.98 0 0 0 .51 4.911 6.051 6.051 0 0 0 6.515 2.9A5.985 5.985 0 0 0 12.56 24a6.051 6.051 0 0 0 5.772-4.206 5.99 5.99 0 0 0 3.997-2.9 6.056 6.056 0 0 0-.747-7.073zM12.56 22.51a4.476 4.476 0 0 1-2.876-1.04l.143-.08 4.779-2.758a.795.795 0 0 0 .395-.684v-6.74l2.02 1.166a.071.071 0 0 1 .038.052v5.583a4.504 4.504 0 0 1-4.5 4.502zM3.12 18.376a4.48 4.48 0 0 1-.535-3.014l.142.085 4.783 2.759a.77.77 0 0 0 .785 0l5.843-3.369v2.332a.08.08 0 0 1-.033.062L9.26 19.998a4.5 4.5 0 0 1-6.14-1.622zM2.036 7.874a4.485 4.485 0 0 1 2.34-1.97V11.6a.77.77 0 0 0 .388.676l5.815 3.355-2.02 1.168a.076.076 0 0 1-.071 0l-4.83-2.786A4.504 4.504 0 0 1 2.036 7.87zm16.37 3.81L12.57 8.316l2.02-1.166a.076.076 0 0 1 .071 0l4.83 2.791a4.494 4.494 0 0 1-.676 8.105v-5.698a.785.785 0 0 0-.388-.676zm2.01-3.028l-.141-.085-4.774-2.782a.776.776 0 0 0-.785 0L8.87 9.158V6.826a.08.08 0 0 1 .033-.062l4.83-2.787a4.5 4.5 0 0 1 6.683 4.66zM7.823 12.74l-2.02-1.164a.08.08 0 0 1-.038-.057V5.936a4.504 4.504 0 0 1 7.372-3.462l-.142.08L8.21 5.312a.786.786 0 0 0-.395.684l.009 6.744zm1.1-2.368l2.6-1.502 2.6 1.502v3.004l-2.6 1.502-2.6-1.502z" fill="#10A37F"/></svg>
                            </div>
                            <span class="ai-provider-name"><?php esc_html_e('OpenAI', 'chatbot'); ?></span>
                        </div>
                        <div class="ai-provider-card <?php echo ($active_provider === 'gemini') ? 'active' : ''; ?>" data-provider="gemini">
                            <span class="ai-provider-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="7" fill="#2563EB"/><path d="M4 7.2L6.2 9.4L10 5" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <div class="ai-provider-icon">
                                <svg viewBox="0 0 24 24" width="32" height="32" fill="none"><path d="M12 24C12 24 12 12 24 12C12 12 12 0 12 0C12 0 12 12 0 12C12 12 12 24 12 24Z" fill="url(#gemini_grad)"/><defs><linearGradient id="gemini_grad" x1="0" y1="12" x2="24" y2="12"><stop stop-color="#1A73E8"/><stop offset="1" stop-color="#6C48C1"/></linearGradient></defs></svg>
                            </div>
                            <span class="ai-provider-name"><?php esc_html_e('Google Gemini', 'chatbot'); ?></span>
                        </div>
                        <div class="ai-provider-card <?php echo ($active_provider === 'grok') ? 'active' : ''; ?>" data-provider="grok">
                            <span class="ai-provider-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="7" fill="#2563EB"/><path d="M4 7.2L6.2 9.4L10 5" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <div class="ai-provider-icon">
                                <svg viewBox="0 0 24 24" width="32" height="32" fill="none"><path d="M4.24 4L11.47 14.13L4.2 21H5.84L12.2 15.16L17.34 21H22L14.35 10.29L21.1 4H19.46L13.63 9.26L8.9 4H4.24ZM6.56 5.27H8.32L19.68 19.73H17.92L6.56 5.27Z" fill="#1DA1F2"/></svg>
                            </div>
                            <span class="ai-provider-name"><?php esc_html_e('Grok', 'chatbot'); ?></span>
                        </div>
                        <div class="ai-provider-card <?php echo ($active_provider === 'openrouter') ? 'active' : ''; ?>" data-provider="openrouter">
                            <span class="ai-provider-check"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="7" fill="#2563EB"/><path d="M4 7.2L6.2 9.4L10 5" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <div class="ai-provider-icon">
                                <svg viewBox="0 0 24 24" width="32" height="32" fill="none"><path d="M16.778 1.844v1.919q-.569-.026-1.138-.032q-.708-.008-1.415.037c-1.93.126-4.023.728-6.149 2.237c-2.911 2.066-2.731 1.95-4.14 2.75c-.396.223-1.342.574-2.185.798c-.841.225-1.753.333-1.751.333v4.229s.768.108 1.61.333c.842.224 1.789.575 2.185.799c1.41.798 1.228.683 4.14 2.75c2.126 1.509 4.22 2.11 6.148 2.236c.88.058 1.716.041 2.555.005v1.918l7.222-4.168l-7.222-4.17v2.176c-.86.038-1.611.065-2.278.021c-1.364-.09-2.417-.357-3.979-1.465c-2.244-1.593-2.866-2.027-3.68-2.508c.889-.518 1.449-.906 3.822-2.59c1.56-1.109 2.614-1.377 3.978-1.466c.667-.044 1.418-.017 2.278.02v2.176L24 6.014Z" fill="#6B7280"/></svg>
                            </div>
                            <span class="ai-provider-name"><?php esc_html_e('OpenRouter', 'chatbot'); ?></span>
                        </div>
                    </div>
                </div>
				<?php
				if ( get_option( 'ai_enabled' ) != 1 && get_option( 'qcld_openrouter_enabled' ) != 1 && get_option( 'qcld_gemini_enabled' ) != 1 && get_option( 'qcld_grok_enabled' ) != 1 ) {
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
                 <div id="grok-settings" class="ai-settings-provider" <?php  echo (get_option( 'qcld_grok_enabled') == 1) ? 'style="display: block;"' :'style="display: none;"';?> >
                    <?php  require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/grok/admin/settings.php'); ?>
                </div>
                <div id="rag-settings" class="ai-settings-provider" style="display: none;">
					<?php require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/admin/templates/rag.php'; ?>
				</div>
                <div id="common-ai-settings" class="ai-settings-provider" style="display: none;">
                    <?php require_once QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/admin/templates/common-ai-settings.php'; ?>
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




