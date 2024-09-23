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
</svg></div>

                    <div class="row">
                        <div class="col-auto me-auto">
                            <h4><?php esc_html_e( 'OpenAI Settings','openai_addon');?></h4> 
                        </div>
                    </div>
                </div>
                <div class="card-body p-sm-0">
                    <!-- <div class="alert alert-danger" role="alert">
                        <?php // echo esc_html__('OpenAI has disabled some of the older models. Please use GPT 3.5 or 4 to Fine tune. You need to update the dataset and Fine tune again. Please check the Help section for details.', 'wpchatbot'); ?>
                    </div> -->
                    <ul class="nav nav-tabs">
                        <li class="active"><a data-toggle="tab" href="#wp-chatbot-openai-settings"><?php echo esc_html__('OpenAI settings', 'wpchatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-openai-training-model"><?php echo esc_html__('Training Model', 'wpchatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-openai-assistants"><?php echo esc_html__('GPT Assistant', 'wpchatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-data_post_converter"><?php echo esc_html__('Fine Tune with Website Data', 'wpchatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-img_generator"><?php echo esc_html__('AI Image Generator', 'wpchatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-content_writer"><?php echo esc_html__('AI Article Generator', 'wpchatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-openai-help"><?php echo esc_html__('Help', 'wpchatbot'); ?></a></li>
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
     