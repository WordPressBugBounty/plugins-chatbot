
<div class="qcl-openai">
    <div class="row gx-0">
        <div class="col-xs-12">
            <div class="card admin-maxwith  qcld-openai-main-box">
                <div class="card-header bg-dark text-white py-sm-4 border-0">>

                    <div class="row">
                        <div class="col-auto me-auto">
                            <h4><?php esc_html_e( 'OpenAI Settings','chatbot');?></h4> 
                        </div>
                    </div>
                </div>
                <div class="card-body p-sm-0">
                    <!-- <div class="alert alert-danger" role="alert">
                        <?php // echo esc_html__('OpenAI has disabled some of the older models. Please use GPT 3.5 or 4 to Fine tune. You need to update the dataset and Fine tune again. Please check the Help section for details.', 'chatbot'); ?>
                    </div> -->  
                    <ul class="nav nav-tabs">
                        <li class="active"><a data-toggle="tab" href="#wp-chatbot-openai-settings"><i class="dashicons dashicons-admin-generic"></i><?php echo esc_html__('OpenAI settings', 'chatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-openai-training-model"><i class="dashicons dashicons-plugins-checked"></i> <?php echo esc_html__('Training Model', 'chatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-openai-assistants"> <i class="dashicons dashicons-admin-home"></i><?php echo esc_html__('GPT Assistant', 'chatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-data_post_converter"><i class="dashicons dashicons-database-add"></i><?php echo esc_html__('Fine Tune with Website Data', 'chatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-img_generator"><i class="dashicons dashicons-format-gallery"></i><?php echo esc_html__('AI Image Generator', 'chatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-content_writer"><i class="dashicons dashicons-format-status"></i><?php echo esc_html__('AI Article Generator', 'chatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-openai-help"><i class="dashicons dashicons-editor-help"></i> <?php echo esc_html__('Help', 'chatbot'); ?></a></li>
                    </ul>
                    <div class="tab-content">
                        <div id="wp-chatbot-openai-settings" class="tab-pane in active">
                            <?php require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/openai/admin/settings.php'); ?>
                        </div>
                        <div id="wp-chatbot-openai-training-model" class="tab-pane">
                            <?php 
                                require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/openai/admin/files.php');
                            ?>
                        </div>
                        <div id="wp-chatbot-openai-assistants" class="tab-pane">
                            <?php 
                                require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/openai/admin/assistant.php');
                            ?>
                        </div>
                        <div id="wp-chatbot-data_post_converter" class="tab-pane">
                            <?php 
                               require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/openai/admin/data_post_converter.php');
                            ?>
                        </div>
                        <div id="wp-chatbot-img_generator" class="tab-pane">
                            <div class="row">
                                <div class="col-xs-12">
                                    <?php require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/openai/admin/img_generator.php' ); ?>
                                </div>
                            </div>
                        </div>
                        <div id="wp-chatbot-content_writer" class="tab-pane">
                            <div class="row">
                                <div class="col-xs-12">
                                    <?php require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/openai/admin/content_writer.php' ); ?>
                                </div>
                            </div>
                        </div>
                        <div id="wp-chatbot-openai-help" class="tab-pane">
                            <?php require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/openai/admin/help.php' ); ?>
                        </div>
                    </div>
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