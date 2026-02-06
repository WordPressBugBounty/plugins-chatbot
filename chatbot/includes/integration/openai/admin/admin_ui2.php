
                <div class="card-body p-sm-0">
                    <!-- <div class="alert alert-danger" role="alert">
                        <?php // echo esc_html__('OpenAI has disabled some of the older models. Please use GPT 3.5 or 4 to Fine tune. You need to update the dataset and Fine tune again. Please check the Help section for details.', 'chatbot'); ?>
                    </div> -->
                    <ul class="nav nav-tabs">
                        <li class="active"><a data-toggle="tab" href="#wp-chatbot-openai-settings"><span class="wpwbot-admin-tab-icon "> <i class="dashicons dashicons-admin-generic"></i> </span><?php echo esc_html__('OpenAI settings', 'chatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-openai-rag"><span class="wpwbot-admin-tab-icon "> <i class="dashicons dashicons-admin-generic"></i> </span><?php echo esc_html__('RAG', 'chatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-openai-training-model"><span class="wpwbot-admin-tab-icon "> <i class="dashicons dashicons-plugins-checked"></i> </span><?php echo esc_html__('Training Model', 'chatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-openai-assistants"><span class="wpwbot-admin-tab-icon "> <i class="dashicons dashicons-admin-home"></i> </span><?php echo esc_html__('GPT Assistant', 'chatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-data_post_converter"><span class="wpwbot-admin-tab-icon "> <i class="dashicons dashicons-database-add"></i> </span><?php echo esc_html__('Fine Tune with Website Data', 'chatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-img_generator"><span class="wpwbot-admin-tab-icon "> <i class="dashicons dashicons-format-gallery"></i> </span><?php echo esc_html__('AI Image Generator', 'chatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-content_writer"><span class="wpwbot-admin-tab-icon "> <i class="dashicons dashicons-format-status"></i> </span><?php echo esc_html__('AI Article Generator', 'chatbot'); ?></a></li>
                        <li><a data-toggle="tab" href="#wp-chatbot-openai-help"><span class="wpwbot-admin-tab-icon "> <i class="dashicons dashicons-editor-help"></i> </span><?php echo esc_html__('Help', 'chatbot'); ?></a></li>
                    </ul>
                 
                    <div class="tab-content">
                        <div id="wp-chatbot-openai-settings" class="tab-pane in active">
                            <?php require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/openai/admin/settings.php'); ?>
                        </div>
                        <div id="wp-chatbot-openai-rag" class="tab-pane">
                            <?php 
                                require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/openai/admin/openai-rag.php');
                            ?>
                        </div>
                        <div id="wp-chatbot-openai-training-model" class="tab-pane">
                            <?php 
                                require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/openai/admin/files.php');
                            ?>
                        </div>
                        <div id="wp-chatbot-openai-assistants" class="tab-pane">
                            <?php 
                                require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/openai/admin/assistant.php');
                            ?>
                        </div>
                        <div id="wp-chatbot-data_post_converter" class="tab-pane">
                            <?php 
                               require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/openai/admin/data_post_converter.php');
                            ?>
                        </div>
                        <div id="wp-chatbot-img_generator" class="tab-pane">
                            <div class="row">
                                <div class="col-xs-12">
                                    <?php require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/openai/admin/img_generator.php' ); ?>
                                </div>
                            </div>
                        </div>
                        <div id="wp-chatbot-content_writer" class="tab-pane">
                            <div class="row">
                                <div class="col-xs-12">
                                    <?php require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/openai/admin/content_writer.php' ); ?>
                                </div>
                            </div>
                        </div>
                        <div id="wp-chatbot-openai-help" class="tab-pane">
                            <?php require_once(QCLD_wpCHATBOT_PLUGIN_DIR_PATH . 'includes/integration/openai/admin/help.php' ); ?>
                        </div>
                    </div>
                </div>
              
            