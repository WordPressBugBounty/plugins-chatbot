<div class="qcl-openai">
    <div class="row gx-0">
        <div class="col-xs-12">
            <div class="card admin-maxwith">
                <div class="card-header bg-dark text-white py-sm-4 border-0">


                <div class="lineanimation">
                    <svg width="350" height="350" viewBox="0 0 308 309" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <defs>
                    <circle id="a" cx="150" cy="150" r="150"></circle>
                    <linearGradient x1="50%" y1="0%" x2="50%" y2="62.304%" id="c">
                    <stop stop-color="#09DFF3" offset="0%"></stop>
                    <stop stop-color="#44BEFF" offset="100%"></stop>
                    </linearGradient>
                    </defs>
                    <g>
                    <path id="l1" d="M0 130 L300 130"></path>
                    <path id="l2" d="M0 150 L300 150"></path>
                    <path id="l3" d="M0 170 L300 170"></path>
                    <path id="l4" d="M0 190 L300 190"></path>
                    </g>
                    </svg>
                </div>

                    <div class="row">
              
                        <div class="col-auto me-auto ai-settings-title-container">
                         
                                <h4><?php esc_html_e( 'AI Settings','openai_addon');?></h4> 
                            
                            <select id="ai-provider-selector" class="form-select ai-settings-selector">
                                <option value="openai" <?php echo (get_option( 'ai_enabled') == 1) ? esc_attr( 'selected','wpchatbot') :'';?> ><?php echo esc_html__( 'OpenAI','wpchatbot')?></option>
                               
                                <option value="openrouter" <?php echo (get_option( 'qcld_openrouter_enabled') == 1) ? esc_attr( 'selected','wpchatbot') :'';?> ><?php echo esc_html__( 'OpenRouter','wpchatbot')?></option>
                            </select>
                        </div>

                    </div>
                </div>
                <div id="openai-settings" class="ai-settings-provider" <?php echo (get_option( 'ai_enabled') == 1) || (get_option( 'qcld_openrouter_enabled') == 0) ? 'style="display: block;"' :'style="display: none;"';?> >
                    <?php require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/openai/admin/admin_ui2.php'); ?>
                </div>
                <div id="openrouter-settings" class="ai-settings-provider" <?php echo (get_option( 'qcld_openrouter_enabled') == 1) ? 'style="display: block;"' :'style="display: none;"';?> >
                    <?php require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/openrouter/admin/settings.php'); ?>
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




