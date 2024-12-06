<?php 

defined('ABSPATH') or die("You can't access this file directly.");


add_action( 'admin_enqueue_scripts', 'qc_wpbotpro_floating_openai_floating_admin_enqueue_styles' );
if ( ! function_exists( 'qc_wpbotpro_floating_openai_floating_admin_enqueue_styles' ) ) {
  function qc_wpbotpro_floating_openai_floating_admin_enqueue_styles() {


    wp_enqueue_script('qc_wpbotpro_floating_openai_bootstrap_script', QCLD_wpCHATBOT_PLUGIN_URL. 'inc/bootstrap.js');

    wp_enqueue_style('qc_wpbotpro_floating_openai_floating_icon_css', QCLD_wpCHATBOT_PLUGIN_URL. 'inc/qcld-floating-icons.css' );

    wp_enqueue_script('qc_wpbotpro_floating_openai_floating_icon', QCLD_wpCHATBOT_PLUGIN_URL. 'inc/qcld-floating-icons.js' );

    wp_add_inline_script( 'qc_wpbotpro_floating_openai_floating_icon', 
        'var qc_wpbotpro_floating_ajaxurl               = "' . admin_url('admin-ajax.php') . '"; 
         var qc_wpbotpro_floating_ajax_nonce            = "'. wp_create_nonce( 'kbx-qc' ).'";  
         ', 'before');

  }
}

$qcld_disable_floating_icon = get_option('qc_wpbotpro_disable_floating_icon');

//var_dump( $qcld_disable_floating_icon );
//wp_die();
if($qcld_disable_floating_icon !== "1" ){
add_action('admin_footer', 'qc_wpbotpro_floating_icon_content_html');
}
if ( ! function_exists( 'qc_wpbotpro_floating_icon_content_html' ) ) {
  function qc_wpbotpro_floating_icon_content_html(){

      $screen = get_current_screen();
      //var_dump( $screen->post_type );
      //wp_die();
      //if( isset( $screen->post_type ) && ( $screen->post_type == 'page' || $screen->post_type == 'post' ) ){

      ?>
      <div class="qc_wpbotpro_content_wrap">
          <label for="linkbait-post-class"><?php echo esc_html__( "AI", 'kbx-qc' ); ?></label>
          
          <div class="qc_wpbotpro_content_wrap_inn">
          <img src="<?php echo esc_url(QCLD_wpCHATBOT_PLUGIN_URL.'inc/ai.png') ?>" alt="loading">
          <input type="button" class="button" id="qc_wpbotpro_content_generator" value="Generate">
          </div>
      </div>
    <div class="qc_wpbotpro_floating-outer">
        <div class="qc_wpbotpro_floating">
          
            <!-- Sidebar Right -->
            <div class="modal fade right" id="qc_wpbotpro_content_generator_modal" tabindex="-1" role="dialog" data-bs-backdrop="static" >
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="keywords_resultLabel"><?php echo esc_html__('Content Generator', 'kbx-qc' ); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="modal-list">  
                              <?php 


                                $open_ai_api_key = get_option('open_ai_api_key');
                                if( empty($open_ai_api_key) ){ 

                              ?>
                              <p style="color:red;"><b><?php esc_html_e('Please add API key from'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=wpbot_openAi')); ?>" target="_blank"><?php esc_html_e('Settings.'); ?></a> <?php esc_html_e('Otherwise, AI will not work.', 'kbx-qc'); ?></b></p>
                              <?php } ?>
                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                              <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="article-tab" data-bs-toggle="tab" data-bs-target="#article-tab-pane" type="button" role="tab" aria-controls="article-tab-pane" aria-selected="true"><?php esc_html_e('Generate New Article', 'kbx-qc'); ?></button>
                              </li>
                              <li class="nav-item" role="presentation">
                                <button class="nav-link" id="content-tab" data-bs-toggle="tab" data-bs-target="#content-tab-pane" type="button" role="tab" aria-controls="content-tab-pane" aria-selected="false"><?php esc_html_e('Rewrite Contents', 'kbx-qc'); ?></button>
                              </li>
                              <li class="nav-item" role="presentation">
                                <button class="nav-link" id="content-tab" data-bs-toggle="tab" data-bs-target="#playground-tab-pane" type="button" role="tab" aria-controls="playground-tab-pane" aria-selected="false"><?php esc_html_e('Playground', 'kbx-qc'); ?></button>
                              </li>
                            </ul>

                            <div class="tab-content" id="myTabContent">
                                <div class="tab-pane fade show active" id="article-tab-pane" role="tabpanel" aria-labelledby="article-tab" tabindex="0">
                                    <div class="qc_wpbotpro_floating">
                                        <div class="qc_wpbotpro_floating-input">
                                            <div class="qc_wpbotpro_floating-input-field">
                                                <label for="qc_wpbotpro_floating_openai_keyword_suggestion" class="form-label"><?php esc_html_e('Prompt', 'kbx-qc'); ?></label><br>
                                                <input type="text" id="qc_wpbotpro_floating_openai_keyword_suggestion_mf" class="form-control" data-press="qc_wpbotpro_floating_openai_keyword_suggestion" placeholder="<?php esc_html_e( "Write me a long article on how to make money online", 'kbx-qc' ); ?>"><br>
                                                <p><?php esc_html_e( "Ex: Write me a long article on how to make money online", 'kbx-qc' ); ?></p>
                                            </div>
                                           
                                        </div>
                                        <div class="qc_wpbotpro_floating-input qc_wpbotpro_floating_pro_feature_content">
                                            <div class="qc_wpbotpro_floating-input-field qc_wpbotpro_floating-input-field_ai_wrap">
                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_number_of_heading"><?php esc_html_e( "How many headings?", 'kbx-qc' ); ?> </label>
                                                    <input type="number" placeholder="e.g. 5" id="qc_wpbotpro_article_number_of_heading" class="qc_wpbotpro_article_number_of_heading" name="qc_wpbotpro_article_number_of_heading" value="">
                                                </div>

                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_heading_tag"><?php esc_html_e( "Heading Tag", 'kbx-qc' ); ?> </label>
                                                    <select name="qc_wpbotpro_article_heading_tag" id="qc_wpbotpro_article_heading_tag">
                                                        <option value="h1"><?php esc_html_e( "h1", 'kbx-qc' ); ?></option>
                                                        <option value="h2"><?php esc_html_e( "h2", 'kbx-qc' ); ?></option>
                                                        <option value="h3"><?php esc_html_e( "h3", 'kbx-qc' ); ?></option>
                                                        <option value="h4"><?php esc_html_e( "h4", 'kbx-qc' ); ?></option>
                                                        <option value="h5"><?php esc_html_e( "h5", 'kbx-qc' ); ?></option>
                                                        <option value="h6"><?php esc_html_e( "h6", 'kbx-qc' ); ?></option>
                                                    </select>
                                                </div>

                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_heading_style"><?php esc_html_e( "Writing Style", 'kbx-qc' ); ?> </label>
                                                    <select name="qc_wpbotpro_article_heading_style" id="qc_wpbotpro_article_heading_style">
                                                        <option value="infor"><?php esc_html_e( "Informative", 'kbx-qc' ); ?></option>
                                                        <option value="analy"><?php esc_html_e( "Analytical", 'kbx-qc' ); ?></option>
                                                        <option value="argum"><?php esc_html_e( "Argumentative", 'kbx-qc' ); ?></option>
                                                        <option value="creat"><?php esc_html_e( "Creative", 'kbx-qc' ); ?></option>
                                                        <option value="criti"><?php esc_html_e( "Critical", 'kbx-qc' ); ?></option>
                                                        <option value="descr"><?php esc_html_e( "Descriptive", 'kbx-qc' ); ?></option>
                                                        <option value="evalu"><?php esc_html_e( "Evaluative", 'kbx-qc' ); ?></option>
                                                        <option value="expos"><?php esc_html_e( "Expository", 'kbx-qc' ); ?></option>
                                                        <option value="journ"><?php esc_html_e( "Journalistic", 'kbx-qc' ); ?></option>
                                                        <option value="narra"><?php esc_html_e( "Narrative", 'kbx-qc' ); ?></option>
                                                        <option value="persu"><?php esc_html_e( "Persuasive", 'kbx-qc' ); ?></option>
                                                        <option value="refle"><?php esc_html_e( "Reflective", 'kbx-qc' ); ?></option>
                                                        <option value="simpl"><?php esc_html_e( "Simple", 'kbx-qc' ); ?></option>
                                                        <option value="techn"><?php esc_html_e( "Technical", 'kbx-qc' ); ?></option>
                                                        <option value="repor"><?php esc_html_e( "Report", 'kbx-qc' ); ?></option>
                                                        <option value="resea"><?php esc_html_e( "Research", 'kbx-qc' ); ?></option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="qc_wpbotpro_floating-input qc_wpbotpro_floating_pro_feature_content">
                                            <div class="qc_wpbotpro_floating-input-field qc_wpbotpro_floating-input-field_ai_wrap">

                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_heading_tone"><?php esc_html_e( "Writing Tone", 'kbx-qc' ); ?> </label>
                                                    <select name="qc_wpbotpro_article_heading_tone" id="qc_wpbotpro_article_heading_tone">
                                                        <option value="formal"><?php esc_html_e( "Formal", 'kbx-qc' ); ?></option>
                                                        <option value="asser"><?php esc_html_e( "Assertive", 'kbx-qc' ); ?></option>
                                                        <option value="cheer"><?php esc_html_e( "Cheerful", 'kbx-qc' ); ?></option>
                                                        <option value="humor"><?php esc_html_e( "Humorous", 'kbx-qc' ); ?></option>
                                                        <option value="informal"><?php esc_html_e( "Informal", 'kbx-qc' ); ?></option>
                                                        <option value="inspi"><?php esc_html_e( "Inspirational", 'kbx-qc' ); ?></option>
                                                        <option value="neutr"><?php esc_html_e( "Neutral", 'kbx-qc' ); ?></option>
                                                        <option value="profe"><?php esc_html_e( "Professional", 'kbx-qc' ); ?></option>
                                                        <option value="sarca"><?php esc_html_e( "Sarcastic", 'kbx-qc' ); ?></option>
                                                        <option value="skept"><?php esc_html_e( "Skeptical", 'kbx-qc' ); ?></option>
                                                        <option value="curio"><?php esc_html_e( "Curious", 'kbx-qc' ); ?></option>
                                                        <option value="disap"><?php esc_html_e( "Disappointed", 'kbx-qc' ); ?></option>
                                                        <option value="encou"><?php esc_html_e( "Encouraging", 'kbx-qc' ); ?></option>
                                                        <option value="optim"><?php esc_html_e( "Optimistic", 'kbx-qc' ); ?></option>
                                                        <option value="surpr"><?php esc_html_e( "Surprised", 'kbx-qc' ); ?></option>
                                                        <option value="worry"><?php esc_html_e( "Worried", 'kbx-qc' ); ?></option>

                                        
                                                    </select>
                                                </div>

                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_img_size" ><?php esc_html_e('Image Size', 'kbx-qc'); ?> </label>
                                                    <select name="qc_wpbotpro_article_img_size" id="qc_wpbotpro_article_img_size">
                                                        <!-- <option value="256x256"><?php esc_html_e( "256x256", 'kbx-qc' ); ?> </option>
                                                        <option value="512x512"><?php esc_html_e( "512x512", 'kbx-qc' ); ?> </option> -->
                                                      <option value="1024x1024"><?php esc_html_e( "1024x1024", 'kbx-qc' ); ?> </option>
                                                      <option value="1792x1024"><?php esc_html_e('1792x1024', 'kbx-qc'); ?></option>
                                                      <option value="1024x1792"><?php esc_html_e('1024x1792', 'kbx-qc'); ?></option>
                                                    </select>
                                                </div>

                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_language"><?php esc_html_e( "Language", 'kbx-qc' ); ?> </label>
                                                    <select name="qc_wpbotpro_article_language" id="qc_wpbotpro_article_language">
                                                        <option value="en"><?php esc_html_e( "English", 'kbx-qc' ); ?> </option>
                                                        <option value="ar"><?php esc_html_e( "Arabic", 'kbx-qc' ); ?> </option>
                                                        <option value="bg"><?php esc_html_e( "Bulgarian", 'kbx-qc' ); ?> </option>
                                                        <option value="zh"><?php esc_html_e( "Chinese", 'kbx-qc' ); ?> </option>
                                                        <option value="cs"><?php esc_html_e( "Czech", 'kbx-qc' ); ?> </option>
                                                        <option value="nl"><?php esc_html_e( "Dutch", 'kbx-qc' ); ?> </option>
                                                        <option value="fr"> <?php esc_html_e( "French", 'kbx-qc' ); ?> </option>
                                                        <option value="de"> <?php esc_html_e( "German", 'kbx-qc' ); ?> </option>
                                                        <option value="el"> <?php esc_html_e( "Greek", 'kbx-qc' ); ?> </option>
                                                        <option value="hi"> <?php esc_html_e( "Hindi", 'kbx-qc' ); ?> </option>
                                                        <option value="hu"> <?php esc_html_e( "Hungarian", 'kbx-qc' ); ?> </option>
                                                        <option value="id"> <?php esc_html_e( "Indonesian", 'kbx-qc' ); ?> </option>
                                                        <option value="it"> <?php esc_html_e( "Italian", 'kbx-qc' ); ?> </option>
                                                        <option value="ja"> <?php esc_html_e( "Japanese", 'kbx-qc' ); ?> </option>
                                                        <option value="ko"> <?php esc_html_e( "Korean", 'kbx-qc' ); ?> </option>
                                                        <option value="pl"> <?php esc_html_e( "Polish", 'kbx-qc' ); ?> </option>
                                                        <option value="pt"> <?php esc_html_e( "Portuguese", 'kbx-qc' ); ?> </option>
                                                        <option value="ro"> <?php esc_html_e( "Romanian", 'kbx-qc' ); ?> </option>
                                                        <option value="ru"> <?php esc_html_e( "Russian", 'kbx-qc' ); ?> </option>
                                                        <option value="es"> <?php esc_html_e( "Spanish", 'kbx-qc' ); ?> </option>
                                                        <option value="sv"> <?php esc_html_e( "Swedish", 'kbx-qc' ); ?> </option>
                                                        <option value="tr"> <?php esc_html_e( "Turkish", 'kbx-qc' ); ?> </option>
                                                        <option value="uk"> <?php esc_html_e( "Ukranian", 'kbx-qc' ); ?> </option>
                                                    </select>
                                                </div>
                                            </div>        
                                        </div>        
                                        <div class="qc_wpbotpro_floating-input qc_wpbotpro_floating_pro_feature_content">
                                            <div class="qc_wpbotpro_floating-input-field qc_wpbotpro_floating-input-field_ai_wrap">

                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_label_anchor_text"><?php esc_html_e( "Anchor Text", 'kbx-qc' ); ?> </label>
                                                    <input type="text" id="qc_wpbotpro_article_label_anchor_text" placeholder="e.g. battery life" class="qc_wpbotpro_article_label_anchor_text" name="qc_wpbotpro_article_label_anchor_text" >
                                                </div>

                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_target_url"><?php esc_html_e( "Target URL", 'kbx-qc' ); ?> </label>
                                                    <input type="url" id="qc_wpbotpro_article_target_url" placeholder="https://..." class="qc_wpbotpro_article_target_url" name="qc_wpbotpro_article_target_url">
                                                </div>

                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_target_label_cta"><?php esc_html_e( "Add Call-to-Action", 'kbx-qc' ); ?> </label>
                                                    <input type="url" id="qc_wpbotpro_article_target_label_cta" placeholder="https://..." class="qc_wpbotpro_article_target_label_cta" name="qc_wpbotpro_article_target_label_cta">
                                                </div>


                                            </div>
                                        </div>
                                        <div class="qc_wpbotpro_floating-input qc_wpbotpro_floating_pro_feature_content">
                                            <div class="qc_wpbotpro_floating-input-field qc_wpbotpro_floating-input-field_ai_wrap">


                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_cta_pos"><?php esc_html_e( "Call-to-Action Position", 'kbx-qc' ); ?> </label>
                                                    <select name="qc_wpbotpro_article_cta_pos" id="qc_wpbotpro_article_cta_pos">
                                                        <option value="beg"><?php esc_html_e( "Beginning", 'kbx-qc' ); ?></option>
                                                        <option value="end"><?php esc_html_e( "End", 'kbx-qc' ); ?></option>
                                                    </select>
                                                    <p><i><?php esc_html_e( "Use Call-to-Action Position", 'kbx-qc' ); ?></i></p>
                                                </div>

                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_label_keywords"><?php esc_html_e( "Add Keywords", 'kbx-qc' ); ?> </label>
                                                    <input type="text" id="qc_wpbotpro_article_label_keywords" placeholder="Write Keywords..." class="qc_wpbotpro_article_label_keywords" name="qc_wpbotpro_article_label_keywords">
                                                    <p><i><?php esc_html_e( "Use comma to seperate keywords", 'kbx-qc' ); ?></i></p>
                                                </div>

                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_label_word_to_avoid"><?php esc_html_e( "Keywords to Avoid", 'kbx-qc' ); ?> </label>
                                                    <input type="text" id="qc_wpbotpro_article_label_word_to_avoid" placeholder="Write Keywords..." class="qc_wpbotpro_article_label_word_to_avoid" name="qc_wpbotpro_article_label_word_to_avoid" value="">
                                                    <p><i><?php esc_html_e( "Use comma to seperate keywords", 'kbx-qc' ); ?></i></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="qc_wpbotpro_floating-input qc_wpbotpro_floating_pro_feature_content">
                                            <div class="qc_wpbotpro_floating-input-field qc_wpbotpro_floating-input-field_ai_wrap">

                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_label_keywords_bold"><?php esc_html_e( "Make Keywords Bold", 'kbx-qc' ); ?> </label>
                                                    <input type="checkbox" id="qc_wpbotpro_article_label_keywords_bold" class="qc_wpbotpro_article_label_keywords_bold" name="qc_wpbotpro_article_label_keywords_bold" value="1">
                                                </div>

                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_heading_img"><?php esc_html_e( "Add Image", 'kbx-qc' ); ?> </label>
                                                    <input type="checkbox" name="qc_wpbotpro_article_heading_img" id="qc_wpbotpro_article_heading_img" class="qc_wpbotpro_article_heading_img" value="1"/>
                                                </div>

                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_heading_tagline"><?php esc_html_e( "Add Tagline", 'kbx-qc' ); ?> </label>
                                                    <input type="checkbox" id="qc_wpbotpro_article_heading_tagline"  name="qc_wpbotpro_article_heading_tagline" class="qc_wpbotpro_article_heading_tagline" value="1" />
                                                </div>


                                            </div>
                                        </div>
                                        <div class="qc_wpbotpro_floating-input qc_wpbotpro_floating_pro_feature_content">
                                            <div class="qc_wpbotpro_floating-input-field qc_wpbotpro_floating-input-field_ai_wrap">

                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_heading_intro"><?php esc_html_e( "Add Introduction", 'kbx-qc' ); ?> </label>
                                                    <input type="checkbox" id="qc_wpbotpro_article_heading_intro" name="qc_wpbotpro_article_heading_intro" class="qc_wpbotpro_article_heading_intro" value="1"/>
                                                </div>

                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_heading_conclusion"><?php esc_html_e( "Add Conclusion", 'kbx-qc' ); ?> </label>
                                                    <input type="checkbox" id="qc_wpbotpro_article_heading_conclusion" name="qc_wpbotpro_article_heading_conclusion" class="qc_wpbotpro_article_heading_conclusion" value="1" />
                                                </div>

                                                <div class="qc_wpbotpro_ai_con">
                                                    <label for="qc_wpbotpro_article_heading_faq"><?php esc_html_e( "Add Faq", 'kbx-qc' ); ?> </label>
                                                    <input type="checkbox" id="qc_wpbotpro_article_heading_faq" name="qc_wpbotpro_article_heading_faq" class="qc_wpbotpro_article_heading_faq" value="1" />
                                                </div>

                                            </div>
                                        </div>
                                        <button id="qc_wpbotpro_floating_openai_keyword_suggestion" class="btn btn-info" ><?php esc_html_e('Generate', 'kbx-qc'); ?></button>
                                        <p style="color:red;"><b><?php esc_html_e('(Please'); ?> <a href="<?php echo esc_url('https://platform.openai.com/settings/organization/billing/'); ?>" target="_blank"><?php esc_html_e('Pre-purchase credit'); ?></a> <?php esc_html_e('from OpenAI API platform and increase the API usage limit. Otherwise, AI features will not work)'); ?></b></p>
                                        <hr/>
                                        <div class="qc_wpbotpro_floating_bait_single_field"> 
                                            <div id="qc_wpbotpro_floating_bait_article_keyword_data">
                                                <div class="qcld_copied-content-wrap">
                                                    <div class="qcld_copied-content_text btn d-none link-success"><?php esc_html_e("Copied", 'kbx-qc'); ?></div>
                                                    <a class="btn btn-sm btn-secondary qcld-copied-content_text"><span class="dashicons dashicons-admin-page"></span></a>
                                                </div>
                                                <?php
                                                    wp_editor('','qc_wpbotpro_floating_content_result_msg', array('media_buttons' => false, 'textarea_name' => 'qc_wpbotpro_floating_content_result_msg', 'editor_height' => 400 ) );
                                                ?>
                                                <div class="qc_wpbotpro_floating_content_result_wrap">
                                                    <div class="qc_wpbotpro_floating_rewrite_result_count"></div>
                                                </div>
                                            </div>

                                            <div class="qcld_seo-playground-buttons">
                                                <button class="button button-primary qc_wpbotpro_floating_openai_article_save" ><?php esc_html_e("Save as Draft", 'kbx-qc'); ?></button>
                                                <button class="button qc_wpbotpro_floating_openai_article_clear"><?php esc_html_e("Clear", 'kbx-qc'); ?></button>
                                            </div>
                                        </div>
                                    </div>

                                </div>  
                                <div class="tab-pane fade" id="content-tab-pane" role="tabpanel" aria-labelledby="content-tab" tabindex="0">
                                    
                                  
                                    <div class="qc_wpbotpro_floating">
                                    <h5><?php esc_html_e( 'Rewrite Contents', 'kbx-qc' ); ?> </h5>
                                    <textarea id="qc_wpbotpro_floating_content_rewrite" class="form-control" data-press="qc_wpbotpro_floating_content_rewrite"></textarea>
                                    <div class="qc_wpbotpro_floating_content_rewrite_count_wrap"><span class="qc_wpbotpro_floating_content_rewrite_count">0</span></div>
                                    <button id="qc_wpbotpro_floating_openai_keyword_rewrite_article" class="btn btn-info"><?php esc_html_e( 'Generate', 'kbx-qc' ); ?></button>
                                    <div id="qc_wpbotpro_floating_content_rewrite_result">
                                        <div class="qcld_copied-content-wrap">
                                            <div class="qcld_copied-content btn d-none link-success"><?php esc_html_e("Copied", 'kbx-qc'); ?></div>
                                            <a class="btn btn-sm btn-secondary qcld-copied-content"><span class="dashicons dashicons-admin-page"></span></a>
                                        </div>
                                        <?php
                                            wp_editor('','qc_wpbotpro_floating_content_rewrite_result_msg', array('media_buttons' => false, 'textarea_name' => 'qc_wpbotpro_floating_content_rewrite_result_msg', 'editor_height' => 400 ) );
                                        ?>
                                        <div class="qc_wpbotpro_floating_rewrite_result_count_wrap">
                                            <div class="qc_wpbotpro_floating_rewrite_result_count"></div>
                                        </div>
                                    </div>
                                    </div>

                                </div>  
                                <div class="tab-pane fade" id="playground-tab-pane" role="tabpanel" aria-labelledby="content-tab" tabindex="0">
                                    
                                  
                                    <div class="qc_wpbotpro_floating qc_wpbotpro_floating-playground">
                                      <table class="form-table form-table-prompt">
                                          <tbody>
                                          <tr>
                                              <th scope="row"><?php esc_html_e("Enter your prompt", 'kbx-qc'); ?></th>
                                              <td>
                                                  <input type="text" class="regular-text qc_wpbotpro_floating_prompt" placeholder="Write Your Prompt">
                                                  &nbsp;<button class="btn btn-info qc_wpbotpro_floating_openai_generator_button"><?php esc_html_e("Generate", 'kbx-qc'); ?></button>
                                                  &nbsp;<button class="btn btn-info qc_wpbotpro_floating_openai_generator_stop"><?php esc_html_e("Stop", 'kbx-qc'); ?></button>
                                                  <p style="color:red;"><b><?php esc_html_e('(Please'); ?> <a href="<?php echo esc_url('https://platform.openai.com/settings/organization/billing/'); ?>" target="_blank"><?php esc_html_e('Pre-purchase credit'); ?></a> <?php esc_html_e('from OpenAI API platform and increase the API usage limit. Otherwise, AI features will not work)'); ?></b></p>
                                              </td>
                                          </tr>
                                          <tr>
                                              <th scope="row"><?php esc_html_e("Result", 'kbx-qc'); ?></th>
                                              <td>
                                                  <?php
                                                  wp_editor('','qc_wpbotpro_floating_generator_result', array('media_buttons' => false, 'textarea_name' => 'qc_wpbotpro_floating_generator_result'));
                                                  ?>
                                                  <p class="qcld_seo-playground-buttons">
                                                      <button class="button button-primary qc_wpbotpro_floating_openai_playground_save"><?php esc_html_e("Save as Draft", 'kbx-qc'); ?></button>
                                                      <button class="button qc_wpbotpro_floating_openai_playground_clear"><?php esc_html_e("Clear", 'kbx-qc'); ?></button>
                                                  </p>
                                              </td>
                                          </tr>
                                          </tbody>
                                      </table>
                                   
                                    </div>

                                </div>  
                            </div>


                            </div>
                        </div>
                    </div>
      
                </div>
            </div>
        </div>
    </div>
      <?php 
    //}
  }
}


add_action( 'wp_ajax_qc_wpbotpro_floating_openai_keyword_suggestion_content_function', 'qc_wpbotpro_floating_openai_keyword_suggestion_content_callback' );
add_action( 'wp_ajax_nopriv_qc_wpbotpro_floating_openai_keyword_suggestion_content_function', 'qc_wpbotpro_floating_openai_keyword_suggestion_content_callback' );

if ( ! function_exists( 'qc_wpbotpro_floating_openai_keyword_suggestion_content_callback' ) ) {
    function qc_wpbotpro_floating_openai_keyword_suggestion_content_callback(){

        check_ajax_referer( 'kbx-qc', 'security');

        set_time_limit(600);

        $OPENAI_API_KEY                     = get_option('open_ai_api_key');
        $ai_engines                         = get_option('openai_engines');
        $max_token                          = get_option('openai_max_tokens') ? get_option('openai_max_tokens') : 4000;
        $temperature                        = get_option('openai_temperature') ? get_option('openai_temperature') : 0;
        $ppenalty                           = get_option('presence_penalty');
        $fpenalty                           = get_option('frequency_penalty');

        $qc_wpbotpro_article_text                  = isset($_POST['keyword'])                          ? sanitize_text_field( $_POST['keyword'] ) : '';
        $keyword_number                     = isset( $_POST['keyword_number'] )                 ? sanitize_text_field( $_POST['keyword_number'] ) : '';
        $qc_wpbotpro_article_language              = isset($_POST['qc_wpbotpro_article_language'])            ? sanitize_text_field( $_POST['qc_wpbotpro_article_language'] ) : '';
        $qc_wpbotpro_article_number_of_heading     = isset($_POST['qc_wpbotpro_article_number_of_heading'])   ? sanitize_text_field( $_POST['qc_wpbotpro_article_number_of_heading'] ) : '';
        $qc_wpbotpro_article_heading_tag           = isset($_POST['qc_wpbotpro_article_heading_tag'])         ? sanitize_text_field( $_POST['qc_wpbotpro_article_heading_tag'] ) : '';
        $qc_wpbotpro_article_heading_style         = isset($_POST['qc_wpbotpro_article_heading_style'])       ? sanitize_text_field( $_POST['qc_wpbotpro_article_heading_style'] ) : '';
        $qc_wpbotpro_article_heading_tone          = isset($_POST['qc_wpbotpro_article_heading_tone'])        ? sanitize_text_field( $_POST['qc_wpbotpro_article_heading_tone'] ) : '';
        $qc_wpbotpro_article_heading_img           = isset($_POST['qc_wpbotpro_article_heading_img'])         ? sanitize_text_field( $_POST['qc_wpbotpro_article_heading_img'] ) : '';
        $qc_wpbotpro_article_heading_tagline       = isset($_POST['qc_wpbotpro_article_heading_tagline'])     ? sanitize_text_field( $_POST['qc_wpbotpro_article_heading_tagline'] ) : '';
        $qc_wpbotpro_article_heading_intro         = isset($_POST['qc_wpbotpro_article_heading_intro'])       ? sanitize_text_field( $_POST['qc_wpbotpro_article_heading_intro'] ) : '';
        $qc_wpbotpro_article_heading_conclusion    = isset($_POST['qc_wpbotpro_article_heading_conclusion'])  ? sanitize_text_field( $_POST['qc_wpbotpro_article_heading_conclusion'] ) : '';
        $qc_wpbotpro_article_label_anchor_text     = isset($_POST['qc_wpbotpro_article_label_anchor_text'])   ? sanitize_text_field( $_POST['qc_wpbotpro_article_label_anchor_text'] ) : '';
        $qc_wpbotpro_article_target_url            = isset($_POST['qc_wpbotpro_article_target_url'])          ? sanitize_text_field( $_POST['qc_wpbotpro_article_target_url'] ) : '';
        $qc_wpbotpro_article_target_label_cta      = isset($_POST['qc_wpbotpro_article_target_label_cta'])    ? sanitize_text_field( $_POST['qc_wpbotpro_article_target_label_cta'] ) : '';
        $qc_wpbotpro_article_cta_pos               = isset($_POST['qc_wpbotpro_article_cta_pos'])             ? sanitize_text_field( $_POST['qc_wpbotpro_article_cta_pos'] ) : '';
        $qc_wpbotpro_article_label_keywords        = isset($_POST['qc_wpbotpro_article_label_keywords'])      ? sanitize_text_field( $_POST['qc_wpbotpro_article_label_keywords'] ) : '';
        $qc_wpbotpro_article_label_word_to_avoid   = isset($_POST['qc_wpbotpro_article_label_word_to_avoid']) ? sanitize_text_field( $_POST['qc_wpbotpro_article_label_word_to_avoid'] ) : '';
        $qc_wpbotpro_article_label_keywords_bold   = isset($_POST['qc_wpbotpro_article_label_keywords_bold']) ? intval( $_POST['qc_wpbotpro_article_label_keywords_bold'] ) : '';
        $qc_wpbotpro_article_heading_faq           = isset($_POST['qc_wpbotpro_article_heading_faq'])         ? intval( $_POST['qc_wpbotpro_article_heading_faq'] ) : '';

        $img_size                           = isset($_POST['qc_wpbotpro_article_img_size'])            ? sanitize_text_field( $_POST['qc_wpbotpro_article_img_size'] ) : '1024x1024';
        //$img_size = "512x512";

        if ( empty($qc_wpbotpro_article_language) ) {
            $qc_wpbotpro_article_language = "en";
        }
        // if number of heading is not set, set it to 5
        if ( empty($qc_wpbotpro_article_number_of_heading) ) {
            $qc_wpbotpro_article_number_of_heading = 2;
        }
        // if writing style is not set, set it to descriptive
        if ( empty($qc_wpbotpro_article_heading_style) ) {
            $qc_wpbotpro_article_heading_style = "infor";
        }
        // if writing tone is not set, set it to assertive
        if ( empty($qc_wpbotpro_article_heading_tone) ) {
            $qc_wpbotpro_article_heading_tone = "formal";
        }
        // if heading tag is not set, set it to h2
        if ( empty($qc_wpbotpro_article_heading_tag) ) {
            $qc_wpbotpro_article_heading_tag = "h2";
        }

        $writing_style  = apply_filters('qc_wpbotpro_floating_openai_filter_for_style', $qc_wpbotpro_article_heading_style, $qc_wpbotpro_article_language );
        $tone_text      = apply_filters('qc_wpbotpro_floating_openai_filter_for_tone', $qc_wpbotpro_article_heading_tone, $qc_wpbotpro_article_language );

        if ( $qc_wpbotpro_article_language == "en" ) {

            if ( $qc_wpbotpro_article_number_of_heading == 1 ) {
                $prompt_text = " blog topic about ";
            } else {
                $prompt_text = " blog topics about ";
            }
            
            $intro_text = "Write an introduction about ";
            $conclusion_text = "Write a conclusion about ";
            $tagline_text = "Write a tagline about ";
            $introduction = "Introduction";
            $conclusion = "Conclusion";
            $faq_text = strval( $qc_wpbotpro_article_number_of_heading ) . " questions and answers about " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "Q&A";
            $style_text = "Writing style: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Keywords: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Exclude following keywords: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            $mycta = "Write a Call to action about: " . $qc_wpbotpro_article_text . " and create a href tag link to: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "de" ) {
            $prompt_text = " blog-Themen über ";
            $intro_text = "Schreiben Sie eine Einführung über ";
            $conclusion_text = "Schreiben Sie ein Fazit über ";
            $tagline_text = "Schreiben Sie eine Tagline über ";
            $introduction = "Einführung";
            $conclusion = "Fazit";
            $faq_text = strval( $qc_wpbotpro_article_number_of_heading ) . " Fragen und Antworten über " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "Fragen und Antworten";
            $style_text = "Schreibstil: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Schlüsselwörter: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Ausschließen folgende Schlüsselwörter: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            $mycta = "Schreiben Sie eine Call to action über: " . $qc_wpbotpro_article_text . " und erstellen Sie einen href-Tag-Link zu: " . $qc_wpbotpro_article_target_label_cta . ".";
        } else  if ( $qc_wpbotpro_article_language == "fr" ) {
            $prompt_text = " sujets de blog sur ";
            $intro_text = "Écrivez une introduction sur ";
            $conclusion_text = "Écrivez une conclusion sur ";
            $tagline_text = "Rédigez un slogan sur ";
            $introduction = "Introduction";
            $conclusion = "Conclusion";
            $faq_text = strval( $qc_wpbotpro_article_number_of_heading ) . " questions et réponses sur " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "Questions et réponses";
            $style_text = "Style d'écriture: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Mots clés: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Exclure les mots-clés suivants: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            $mycta = "Écrivez un appel à l'action sur: " . $qc_wpbotpro_article_text . " et créez un lien href tag vers: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "es" ) {
            $prompt_text = " temas de blog sobre ";
            $intro_text = "Escribe una introducción sobre ";
            $conclusion_text = "Escribe una conclusión sobre ";
            $tagline_text = "Escribe una eslogan sobre ";
            $introduction = "Introducción";
            $conclusion = "Conclusión";
            $faq_text = strval( $qc_wpbotpro_article_number_of_heading ) . " preguntas y respuestas sobre " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "Preguntas y respuestas";
            $style_text = "Estilo de escritura: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Palabras clave: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Excluir las siguientes palabras clave: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            $mycta = "Escribe una llamada a la acción sobre: " . $qc_wpbotpro_article_text . " y cree un enlace de etiqueta html <a href> para: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "it" ) {
            $prompt_text = " argomenti di blog su ";
            $intro_text = "Scrivi un'introduzione su ";
            $conclusion_text = "Scrivi una conclusione su ";
            $tagline_text = "Scrivi un slogan su ";
            $introduction = "Introduzione";
            $conclusion = "Conclusione";
            $faq_text = strval( $qc_wpbotpro_article_number_of_heading ) . " domande e risposte su " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "Domande e risposte";
            $style_text = "Stile di scrittura: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Parole chiave: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Escludere le seguenti parole chiave: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            $mycta = "Scrivi un call to action su: " . $qc_wpbotpro_article_text . " e crea un href tag link a: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "pt" ) {
            $prompt_text = " tópicos de blog sobre ";
            $intro_text = "Escreva uma introdução sobre ";
            $conclusion_text = "Escreva uma conclusão sobre ";
            $tagline_text = "Escreva um slogan sobre ";
            $introduction = "Introdução";
            $conclusion = "Conclusão";
            $faq_text = strval( $qc_wpbotpro_article_number_of_heading ) . " perguntas e respostas sobre " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "Perguntas e respostas";
            $style_text = "Estilo de escrita: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Palavras-chave: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Excluir as seguintes palavras-chave: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            $mycta = "Escreva um call to action sobre: " . $qc_wpbotpro_article_text . " e crie um href tag link para: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "nl" ) {
            $prompt_text = " blogonderwerpen over ";
            $intro_text = "Schrijf een inleiding over ";
            $conclusion_text = "Schrijf een conclusie over ";
            $tagline_text = "Schrijf een slogan over ";
            $introduction = "Inleiding";
            $conclusion = "Conclusie";
            $faq_text = strval( $qc_wpbotpro_article_number_of_heading ) . " vragen en antwoorden over " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "Vragen en antwoorden";
            $style_text = "Schrijfstijl: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Trefwoorden: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Sluit de volgende trefwoorden uit: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            $mycta = "Schrijf een call to action over: " . $qc_wpbotpro_article_text . " en maak een href tag link naar: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "ru" ) {
            $prompt_text = "Перечислите ";
            $prompt_last = " идей блога о ";
            $intro_text = "Напишите введение о ";
            $conclusion_text = "Напишите заключение о ";
            $tagline_text = "Напишите слоган о ";
            $introduction = "Введение";
            $conclusion = "Заключение";
            $faq_text = strval( $qc_wpbotpro_article_number_of_heading ) . " вопросов и ответов о " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "Вопросы и ответы";
            $style_text = "Стиль написания: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = $prompt_text . strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_last . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Ключевые слова: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = $prompt_text . strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_last . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Исключите следующие ключевые слова: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            $mycta = "Напишите call to action о: " . $qc_wpbotpro_article_text . " и сделайте href tag link на: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "ja" ) {
            $prompt_text = " に関するブログのアイデアを ";
            $prompt_last = " つ挙げてください";
            $intro_text = " について紹介文を書く";
            $conclusion_text = " についての結論を書く";
            $tagline_text = " についてのスローガンを書く";
            $introduction = "序章";
            $conclusion = "結論";
            $faq_text = $qc_wpbotpro_article_text . " に関する " . strval( $qc_wpbotpro_article_number_of_heading ) . " の質問と回答.";
            $faq_heading = "よくある質問";
            $style_text = "書き方: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = $qc_wpbotpro_article_text . $prompt_text . strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_last . ".";
            } else {
                $keyword_text = ". キーワード: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = $qc_wpbotpro_article_text . $prompt_text . strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_last . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " 次のキーワードを除外します。 " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $qc_wpbotpro_article_text . $intro_text;
            $myconclusion = $qc_wpbotpro_article_text . $conclusion_text;
            $mytagline = $qc_wpbotpro_article_text . $tagline_text;
            // Write a call to action about $qc_wpbotpro_article_text and create a href tag link to: $qc_wpbotpro_article_target_label_cta.
            $mycta = $qc_wpbotpro_article_text . " についてのコール・トゥ・アクションを書き、hrefタグリンクを作成します。 " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "zh" ) {
            $prompt_text = " 关于 ";
            $of_text = " 的 ";
            $piece_text = " 个博客创意";
            $intro_text = "写一篇关于 ";
            $intro_last = " 的介绍";
            $conclusion_text = "写一篇关于 ";
            // write a tagline about
            $tagline_text = "写一个标语关于 ";
            $conclusion_last = " 的结论";
            $introduction = "介绍";
            $conclusion = "结论";
            $faq_text = $qc_wpbotpro_article_text . " 的 " . strval( $qc_wpbotpro_article_number_of_heading ) . " 个问题和答案.";
            $faq_heading = "常见问题";
            $style_text = "写作风格: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = $prompt_text . $qc_wpbotpro_article_text . $of_text . strval( $qc_wpbotpro_article_number_of_heading ) . $piece_text . ".";
            } else {
                $keyword_text = ". 关键字: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = $prompt_text . $qc_wpbotpro_article_text . $of_text . strval( $qc_wpbotpro_article_number_of_heading ) . $piece_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " 排除以下关键字：" . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text . $intro_last;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text . $conclusion_last;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            // 写一个关于 123 的号召性用语并创建一个 <a href> html 标签链接到：
            $mycta = "写一个关于 " . $qc_wpbotpro_article_text . " 的号召性用语并创建一个 <a href> html 标签链接到： " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "ko" ) {
            $prompt_text = " 다음과 관련된 ";
            $prompt_last = "가지 블로그 아이디어: ";
            $intro_text = "블로그 토픽에 대한 소개를 작성하십시오 ";
            $conclusion_text = "블로그 토픽에 대한 결론을 작성하십시오 ";
            $introduction = "소개";
            $conclusion = "결론";
            $faq_text = $qc_wpbotpro_article_text . "에 대한 " . strval( $qc_wpbotpro_article_number_of_heading ) . "개의 질문과 답변.";
            $faq_heading = "자주 묻는 질문";
            // write a tagline about
            $tagline_text = "에 대한 태그라인 작성 ";
            $style_text = "작성 스타일: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = $prompt_text . strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_last . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". 키워드: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = $prompt_text . strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_last . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " 다음 키워드를 제외하십시오. " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $qc_wpbotpro_article_text . $tagline_text;
            // Write a call to action about $qc_wpbotpro_article_text and create a href tag link to: $qc_wpbotpro_article_target_label_cta.
            $mycta = $qc_wpbotpro_article_text . "에 대한 호출 행동을 작성하고 href 태그 링크를 만듭니다. " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "id" ) {
            $prompt_text = " topik blog tentang ";
            $intro_text = "Tulis pengantar tentang ";
            $conclusion_text = "Tulis kesimpulan tentang ";
            $introduction = "Pengantar";
            $conclusion = "Kesimpulan";
            $faq_text = strval( $qc_wpbotpro_article_number_of_heading ) . " pertanyaan dan jawaban tentang " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "Pertanyaan dan jawaban";
            // write a tagline about
            $tagline_text = "Tulis tagline tentang ";
            $style_text = "Gaya penulisan: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Kata kunci: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Hindari kata kunci berikut: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            // Write a call to action about $qc_wpbotpro_article_text and create a href tag link to: $qc_wpbotpro_article_target_label_cta.
            $mycta = "Tulis panggilan tindakan tentang " . $qc_wpbotpro_article_text . " dan buat tautan tag href ke: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "tr" ) {
            $prompt_text = " hakkında ";
            $prompt_last = " blog başlığı listele.";
            $intro_text = " ile ilgili bir giriş yazısı yaz.";
            $conclusion_text = " ile ilgili bir sonuç yazısı yaz.";
            $introduction = "Giriş";
            $conclusion = "Sonuç";
            $faq_text = $qc_wpbotpro_article_text . " hakkında " . strval( $qc_wpbotpro_article_number_of_heading ) . " soru ve cevap.";
            $faq_heading = "SSS";
            // write a tagline about
            $tagline_text = " ile ilgili bir slogan yaz.";
            $style_text = "Yazı stili: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = $qc_wpbotpro_article_text . $prompt_text . strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_last . ".";
            } else {
                $keyword_text = ". Anahtar kelimeler: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = $qc_wpbotpro_article_text . $prompt_text . strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_last . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Bu anahtar kelimeleri kullanma: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $qc_wpbotpro_article_text . $intro_text;
            $myconclusion = $qc_wpbotpro_article_text . $conclusion_text;
            $mytagline = $qc_wpbotpro_article_text . $tagline_text;
            // Write a call to action about $qc_wpbotpro_article_text and create a href tag link to: $qc_wpbotpro_article_target_label_cta.
            $mycta = $qc_wpbotpro_article_text . " hakkında bir çağrıyı harekete geçir ve bir href etiketi bağlantısı oluştur: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "hi" ) {
            $prompt_text = " के बारे में ";
            $prompt_last = " ब्लॉग विषय सूचीबद्ध करें.";
            $intro_text = "का परिचय लिखिए ";
            $conclusion_text = "के बारे में निष्कर्ष लिखिए ";
            $introduction = "प्रस्तावना";
            $conclusion = "निष्कर्ष";
            $faq_text = $qc_wpbotpro_article_text . " के बारे में " . strval( $qc_wpbotpro_article_number_of_heading ) . " प्रश्न और उत्तर.";
            $faq_heading = "सामान्य प्रश्न";
            // write a tagline about
            $tagline_text = " के बारे में एक नारा लिखिए";
            $style_text = "लेखन शैली: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = $qc_wpbotpro_article_text . $prompt_text . strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_last . ".";
            } else {
                $keyword_text = ". कीवर्ड: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = $qc_wpbotpro_article_text . $prompt_text . strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_last . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " निम्नलिखित खोजशब्दों को बाहर करें: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $qc_wpbotpro_article_text . $tagline_text;
            // Write a call to action about $qc_wpbotpro_article_text and create a href tag link to: $qc_wpbotpro_article_target_label_cta.
            $mycta = $qc_wpbotpro_article_text . " के बारे में कोई कॉल एक्शन लिखें और एक href टैग लिंक बनाएं: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "pl" ) {
            $prompt_text = " tematów blogów o ";
            $intro_text = "Napisz wprowadzenie o ";
            $conclusion_text = "Napisz konkluzja o ";
            $introduction = "Wstęp";
            $conclusion = "Konkluzja";
            $faq_text = "Napisz " . strval( $qc_wpbotpro_article_number_of_heading ) . " pytania i odpowiedzi o " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "Pytania i odpowiedzi";
            // write a tagline about
            $tagline_text = "Napisz slogan o ";
            $style_text = "Styl pisania: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Słowa kluczowe:: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text . ".";
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Wyklucz następujące słowa kluczowe: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            $mycta = "Napisz wezwanie do działania dotyczące " . $qc_wpbotpro_article_text . " i utwórz link tagu HTML <a href> do: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "uk" ) {
            $prompt_text = " теми блогів про ";
            $intro_text = "Напишіть вступ про ";
            $conclusion_text = "Напишіть висновок про ";
            $introduction = "Вступ";
            $conclusion = "Висновок";
            $faq_text = "Напишіть " . strval( $qc_wpbotpro_article_number_of_heading ) . " питання та відповіді про " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "Питання та відповіді";
            // write a tagline about
            $tagline_text = "Напишіть слоган про ";
            $style_text = "Стиль письма: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Ключові слова: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Виключіть такі ключові слова: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            // Напишіть заклик до дії про Google і створіть посилання на тег html <a href> для:
            $mycta = "Напишіть заклик до дії про " . $qc_wpbotpro_article_text . " і створіть посилання на тег html <a href> для: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "ar" ) {
            $prompt_text = " موضوعات المدونات على ";
            $intro_text = "اكتب مقدمة عن: ";
            $conclusion_text = "اكتب استنتاجًا عن: ";
            $introduction = "مقدمة";
            $conclusion = "استنتاج";
            $faq_text = "اكتب " . strval( $qc_wpbotpro_article_number_of_heading ) . " أسئلة وأجوبة عن " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "الأسئلة الشائعة";
            // write a tagline about اكتب شعارًا عن
            $tagline_text = " اكتب شعارًا عن ";
            $style_text = "نمط الكتابة: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". الكلمات الدالة: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " تجنب الكلمات التالية: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $qc_wpbotpro_article_text . $tagline_text;
            $mycta = "اكتب عبارة تحث المستخدم على اتخاذ إجراء بشأن " . $qc_wpbotpro_article_text . " وأنشئ <a href> رابط وسم html من أجل: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "ro" ) {
            $prompt_text = " subiecte de blog despre ";
            $intro_text = "Scrieți o introducere despre ";
            $conclusion_text = "Scrieți o concluzie despre ";
            $introduction = "Introducere";
            $conclusion = "Concluzie";
            $faq_text = "Scrieți " . strval( $qc_wpbotpro_article_number_of_heading ) . " întrebări și răspunsuri despre " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "Întrebări și răspunsuri";
            // write a tagline about
            $tagline_text = "Scrieți un slogan despre ";
            $style_text = "Stilul de scriere: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Cuvinte cheie: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Evitați cuvintele: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            // Scrieți un îndemn despre Google și creați o etichetă html <a href> link către:
            $mycta = "Scrieți un îndemn despre " . $qc_wpbotpro_article_text . " și creați o etichetă html <a href> link către: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "hu" ) {
            // Írj 5 blogtémát a Google-ról
            $prompt_text = " blog témákat a következő témában: ";
            $intro_text = "Írj bevezetést ";
            $conclusion_text = "Írj következtetést ";
            $introduction = "Bevezetés";
            $conclusion = "Következtetés";
            $faq_text = "Írj " . strval( $qc_wpbotpro_article_number_of_heading ) . " kérdést és választ a következő témában: " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "GYIK";
            // write a tagline about
            $tagline_text = "Írj egy tagline-t ";
            $style_text = "Írásmód: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Kulcsszavak: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Kerülje a következő szavakat: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            // Írjon cselekvésre ösztönzést a 123-ról, és hozzon létre egy <a href> html címke hivatkozást:
            $mycta = "Írjon cselekvésre ösztönzést a  " . $qc_wpbotpro_article_text . "-rol, témában, és hozzon létre egy <a href> html címke hivatkozást: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "cs" ) {
            $prompt_text = " blog témata o ";
            $intro_text = "Napi úvodní zprávy o ";
            $conclusion_text = "Napi závěrečná zpráva o ";
            $introduction = "Úvodní zpráva";
            $conclusion = "Závěrečná zpráva";
            $faq_text = "Napi " . strval( $qc_wpbotpro_article_number_of_heading ) . " otázky a odpovědi o " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "Často kladené otázky";
            // write a tagline about
            $tagline_text = "Napi tagline o ";
            $style_text = "Styl psaní: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Klíčová slova: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Vyhněte se slovům: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            // Write a call to action about $qc_wpbotpro_article_text and create a href tag link to: $qc_wpbotpro_article_target_label_cta.
            $mycta = "Napi hovor k akci o " . $qc_wpbotpro_article_text . " a vytvořte href tag link na: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "el" ) {
            $prompt_text = " θέματα ιστολογίου για ";
            $intro_text = "Γράψτε μια εισαγωγή για ";
            $conclusion_text = "Γράψτε μια συμπέραση για ";
            $introduction = "Εισαγωγή";
            $conclusion = "Συμπέραση";
            $faq_text = "Γράψτε " . strval( $qc_wpbotpro_article_number_of_heading ) . " ερωτήσεις και απαντήσεις για " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "Συχνές ερωτήσεις";
            // write a tagline about
            $tagline_text = "Γράψτε μια tagline για ";
            $style_text = "Στυλ συγγραφής: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Λέξεις-κλειδιά: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Αποφύγετε τις εξής λέξεις: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            // Write a call to action about $qc_wpbotpro_article_text and create a href tag link to: $qc_wpbotpro_article_target_label_cta.
            $mycta = "Γράψτε μια κλήση σε ενέργεια για " . $qc_wpbotpro_article_text . " και δημιουργήστε έναν σύνδεσμο href tag στο: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "bg" ) {
            $prompt_text = " блог теми за ";
            $intro_text = "Напишете въведение за ";
            $conclusion_text = "Напишете заключение за ";
            $introduction = "Въведение";
            $conclusion = "Заключение";
            $faq_text = "Напишете " . strval( $qc_wpbotpro_article_number_of_heading ) . " въпроси и отговори за " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "Често задавани въпроси";
            // write a tagline about
            $tagline_text = "Напишете tagline за ";
            $style_text = "Стил на писане: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Ключови думи: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Избягвайте думите: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            // Write a call to action about $qc_wpbotpro_article_text and create a href tag link to: $qc_wpbotpro_article_target_label_cta.
            $mycta = "Напишете действие за " . $qc_wpbotpro_article_text . " и създайте връзка href tag към: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else if ( $qc_wpbotpro_article_language == "sv" ) {
            $prompt_text = " bloggämnen om ";
            $intro_text = "Skriv en introduktion om ";
            $conclusion_text = "Skriv en slutsats om ";
            $introduction = "Introduktion";
            $conclusion = "Slutsats";
            $faq_text = "Skriv " . strval( $qc_wpbotpro_article_number_of_heading ) . " frågor och svar om " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "FAQ";
            // write a tagline about
            $tagline_text = "Skriv en tagline om ";
            $style_text = "Skrivstil: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Nyckelord: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Undvik ord: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            // Write a call to action about $qc_wpbotpro_article_text and create a href tag link to: $qc_wpbotpro_article_target_label_cta.
            $mycta = "Skriv ett åtgärdsförslag om " . $qc_wpbotpro_article_text . " och skapa en href tag-länk till: " . $qc_wpbotpro_article_target_label_cta . ".";

        } else {
            $prompt_text = " blog topics about ";
            $intro_text = "Write an introduction about ";
            $conclusion_text = "Write a conclusion about ";
            $introduction = "Introduction";
            $conclusion = "Conclusion";
            $faq_text = "Write " . strval( $qc_wpbotpro_article_number_of_heading ) . " questions and answers about " . $qc_wpbotpro_article_text . ".";
            $faq_heading = "Q&A";
            // write a tagline about
            $tagline_text = "Write a tagline about ";
            $style_text = "Writing style: " . $writing_style . ".";
            
            if ( empty($qc_wpbotpro_article_label_keywords) ) {
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . ".";
            } else {
                $keyword_text = ". Keywords: " . $qc_wpbotpro_article_label_keywords . ".";
                $myprompt = strval( $qc_wpbotpro_article_number_of_heading ) . $prompt_text . $qc_wpbotpro_article_text . $keyword_text;
            }
            
            // if $qc_wpbotpro_article_label_word_to_avoid is not empty, add it to the prompt
            
            if ( !empty($qc_wpbotpro_article_label_word_to_avoid) ) {
                $avoid_text = " Exclude the following keywords: " . $qc_wpbotpro_article_label_word_to_avoid . ".";
                $myprompt = $myprompt . $avoid_text;
            }
            
            $myintro = $intro_text . $qc_wpbotpro_article_text;
            $myconclusion = $conclusion_text . $qc_wpbotpro_article_text;
            $mytagline = $tagline_text . $qc_wpbotpro_article_text;
            // Write a call to action about $qc_wpbotpro_article_text and create a href tag link to: $qc_wpbotpro_article_target_label_cta.
            $mycta = "Write a call to action about " . $qc_wpbotpro_article_text . " and create a href tag link to: " . $qc_wpbotpro_article_target_label_cta . ".";
            
        }



        $result_data = '';

        if(!empty($qc_wpbotpro_article_text)){

            $qcld_ai_settings_open_ai = get_option('qcld_ai_settings_open_ai');

            if( $ai_engines == 'gpt-3.5-turbo' || $ai_engines == 'gpt-4' || $ai_engines == 'gpt-4o' || $ai_engines == 'gpt-4o-mini' ){
                $gptkeyword = [];
                $ch = curl_init();
                $url = 'https://api.openai.com/v1/chat/completions';

                array_push($gptkeyword, array(
                           "role"       => "user",
                           "content"    =>  $myprompt
                        ));

                $post_fields = array(
                    "model"         => $ai_engines,
                    "messages"      => $gptkeyword,
                    "max_tokens"    => (int)$max_token,
                    "temperature"   => ( float ) $temperature
                );
                $header  = [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $OPENAI_API_KEY
                ];
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                $result = curl_exec($ch);
                if (curl_errno($ch)) {
                   echo esc_html('Error: ') . esc_html(curl_error($ch));
                }
                curl_close($ch);
                $complete = json_decode( $result );
                // we need to catch the error here
                
                if ( isset( $complete->error ) ) {
                    $complete = $complete->error->message;
                    // exit
                    echo  esc_html( $complete ) ;
                    exit;
                } else {
                    //$complete = $complete->choices[0]->message->content;
                    $complete = isset( $complete->choices[0]->message->content ) ? trim( $complete->choices[0]->message->content ) : '';
                }

            }else{

                $request_body = [
                    "prompt"            => $myprompt,
                    "model"             => $ai_engines,
                    "max_tokens"        => (int)$max_token,
                    "temperature"       => (float)$temperature,
                    "presence_penalty"  => (float)$ppenalty,
                    "frequency_penalty" => (float)$fpenalty,
                    "top_p"             => 1,
                    "best_of"           => 1,
                ];
                $data    = json_encode($request_body);
                $url     = "https://api.openai.com/v1/completions";
                $apt_key = "Authorization: Bearer ". $OPENAI_API_KEY;

                $curl = curl_init($url);
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                $headers    = array(
                   "Content-Type: application/json",
                   $apt_key ,
                );
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                $result     = curl_exec($curl);
                curl_close($curl);
               // $results    = json_decode($result);

               // $result_data = isset( $results->choices[0]->text ) ? trim( $results->choices[0]->text ) : '';


                $complete = json_decode( $result );
                // we need to catch the error here
                
                if ( isset( $complete->error ) ) {
                    $complete = $complete->error->message;
                    // exit
                    echo  esc_html( $complete ) ;
                    exit;
                } else {
                    //$complete = $complete->choices[0]->text;
                    $complete = isset( $complete->choices[0]->text ) ? trim( $complete->choices[0]->text ) : '';
                }

            }
        
            // trim the text
            $complete = !empty( $complete ) ? trim( $complete ) : '';
            $mylist = array();
            $mylist = preg_split( "/\r\n|\n|\r/", $complete );
            // delete 1. 2. 3. etc from beginning of the line
            $mylist = preg_replace( '/^\\d+\\.\\s/', '', $mylist );
            $allresults = "";
            $qc_wpbotpro_article_heading_tag = sanitize_text_field( $_REQUEST["qc_wpbotpro_article_heading_tag"] );


            $allresults  = apply_filters('qc_wpbotpro_floating_openai_article_heading_tag', $allresults, $mylist, $myprompt, $qc_wpbotpro_article_heading_tag, $style_text, $tone_text, $avoid_text, $qc_wpbotpro_article_label_word_to_avoid );

        
            
            if ( $qc_wpbotpro_article_heading_intro == "1" ) {
                // we need to catch the error here

                $allresults  = apply_filters('qc_wpbotpro_floating_openai_article_heading_intro', $allresults, $myintro, $introduction );
            
            }
            
            // if wpai_add_faq is checked then call api with faq prompt
            
            if ( $qc_wpbotpro_article_heading_faq == "1" ) {
                // we need to catch the error here

                $allresults  = apply_filters('qc_wpbotpro_floating_openai_article_heading_faq', $allresults, $faq_text, $faq_heading );

            
            }
            
            //if myconclusion is not empty,calls the openai api
            
            if ( $qc_wpbotpro_article_heading_conclusion == "1" ) {

                $allresults  = apply_filters('qc_wpbotpro_floating_openai_article_heading_conclusion', $allresults, $myconclusion, $conclusion );

            
            }
            
            // qc_wpbotpro_article_heading_tagline is checked then call the openai api
            
            if ( $qc_wpbotpro_article_heading_tagline == "1" ) {

                $allresults  = apply_filters('qc_wpbotpro_floating_openai_article_heading_tagline', $allresults, $mytagline, $conclusion );

            
            }
            
            // if qc_wpbotpro_article_label_keywords_bold is checked then then find all keywords and bold them. keywords are separated by comma
            if ( $qc_wpbotpro_article_label_keywords_bold == "1" ) {
                // check to see at least one keyword is entered
                
                if ( $qc_wpbotpro_article_label_keywords != "" ) {
                    // split keywords by comma if there are more than one but if there is only one then it will not split
                    
                    if ( strpos( $qc_wpbotpro_article_label_keywords, ',' ) !== false ) {
                        $keywords = explode( ",", $qc_wpbotpro_article_label_keywords );
                    } else {
                        $keywords = array( $qc_wpbotpro_article_label_keywords );
                    }
                    
                    // loop through keywords and bold them
                    foreach ( $keywords as $keyword ) {
                        $keyword = trim( $keyword );
                        // replace keyword with bold keyword but make sure exact match is found. for example if the keyword is "the" then it should not replace "there" with "there".. capital dont matter
                        $allresults = preg_replace( '/\\b' . $keyword . '\\b/', '<strong>' . $keyword . '</strong>', $allresults );
                    }
                }
            
            }
            // if qc_wpbotpro_article_target_url and qc_wpbotpro_article_label_anchor_text is not empty then find qc_wpbotpro_article_label_anchor_text in the text and create a link using qc_wpbotpro_article_target_url
            if ( $qc_wpbotpro_article_target_url != "" && $qc_wpbotpro_article_label_anchor_text != "" ) {
                // create a link if anchor text found.. rules: 1. only for first occurance 2. exact match 3. case insensitive 4. if anchor text found inside any h1,h2,h3,h4,h5,h6, a then skip it. 5. use anchor text to create link dont replace it with existing text
                $allresults = preg_replace(
                    '/(?<!<h[1-6]><a href=")(?<!<a href=")(?<!<h[1-6]>)(?<!<h[1-6]><strong>)(?<!<strong>)(?<!<h[1-6]><em>)(?<!<em>)(?<!<h[1-6]><strong><em>)(?<!<strong><em>)(?<!<h[1-6]><em><strong>)(?<!<em><strong>)\\b' . $qc_wpbotpro_article_label_anchor_text . '\\b(?![^<]*<\\/a>)(?![^<]*<\\/h[1-6]>)(?![^<]*<\\/strong>)(?![^<]*<\\/em>)(?![^<]*<\\/strong><\\/em>)(?![^<]*<\\/em><\\/strong>)/i',
                    '<a href="' . $qc_wpbotpro_article_target_url . '">' . $qc_wpbotpro_article_label_anchor_text . '</a>',
                    $allresults,
                    1
                );
            }


            // if qc_wpbotpro_article_target_label_cta is not empty then call api to get cta text and create a link using qc_wpbotpro_article_target_label_cta
            
            if ( $qc_wpbotpro_article_target_label_cta != "" ) {


                $gptkeyword = [];

                $qcld_ai_settings_open_ai = get_option('qcld_ai_settings_open_ai');

                if( $ai_engines == 'gpt-3.5-turbo' || $ai_engines == 'gpt-4' || $ai_engines == 'gpt-4o' || $ai_engines == 'gpt-4o-mini'){
                    $ch = curl_init();
                    $url = 'https://api.openai.com/v1/chat/completions';

                    array_push($gptkeyword, array(
                               "role"       => "user",
                               "content"    =>  $mycta
                            ));

                    $post_fields = array(
                        "model"         => $ai_engines,
                        "messages"      => $gptkeyword,
                        "max_tokens"    => (int)$max_token,
                        "temperature"   => ( float ) $temperature
                    );
                    $header  = [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $OPENAI_API_KEY
                    ];
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                    $result = curl_exec($ch);
                    if (curl_errno($ch)) {
                       echo esc_html('Error: ') . esc_html(curl_error($ch));
                    }
                    curl_close($ch);
                    //$complete = json_decode( $result );
                   // $complete = isset($complete->choices[0]->message->content) ? $complete->choices[0]->message->content : '';

                    // we need to catch the error here
                    $completecta = json_decode( $result );
                    
                    if ( isset( $completecta->error ) ) {
                        $completecta = $completecta->error->message;
                        // exit
                        echo  esc_html( $completecta ) ;
                        exit;
                    } else {
                        //$completecta = $completecta->choices[0]->message->content;
                        $completecta = isset( $completecta->choices[0]->message->content ) ? trim( $completecta->choices[0]->message->content ) : '';
                        // trim the text
                        $completecta = !empty($completecta) ? trim( $completecta ) : '';
                        // add <p> to the beginning of the text
                        $completecta = "<p>" . $completecta . "</p>"."\n";
                        
                        if ( $wpai_cta_pos == "beg" ) {
                            $allresults = preg_replace(
                                '/(<h[1-6]>)/',
                                $completecta . ' $1',
                                $allresults,
                                1
                            );
                        } else {
                            $allresults = $allresults . $completecta;
                        }
                    
                    }

                }else{

                    // call api to get cta text
                    $request_body = [
                        "prompt"            => $mycta,
                        "model"             => $ai_engines,
                        "max_tokens"        => (int)$max_token,
                        "temperature"       => (float)$temperature,
                        "presence_penalty"  => (float)$ppenalty,
                        "frequency_penalty" => (float)$fpenalty,
                        "top_p"             => 1,
                        "best_of"           => 1,
                    ];
                    $data    = json_encode($request_body);
                    $url     = "https://api.openai.com/v1/completions";
                    $apt_key = "Authorization: Bearer ". $OPENAI_API_KEY;

                    $curl = curl_init($url);
                    curl_setopt($curl, CURLOPT_URL, $url);
                    curl_setopt($curl, CURLOPT_POST, true);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    $headers    = array(
                       "Content-Type: application/json",
                       $apt_key ,
                    );
                    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                    $result     = curl_exec($curl);
                    curl_close($curl);

                    // we need to catch the error here
                    $completecta = json_decode( $result );
                    
                    if ( isset( $completecta->error ) ) {
                        $completecta = $completecta->error->message;
                        // exit
                        echo  esc_html( $completecta ) ;
                        exit;
                    } else {
                        //$completecta = $completecta->choices[0]->text;
                        $completecta = isset( $completecta->choices[0]->text ) ? trim( $completecta->choices[0]->text ) : '';
                        // trim the text
                        $completecta = !empty($completecta) ? trim( $completecta ) : '';
                        // add <p> to the beginning of the text
                        $completecta = "<p>" . $completecta . "</p>"."\n";
                        
                        if ( $wpai_cta_pos == "beg" ) {
                            $allresults = preg_replace(
                                '/(<h[1-6]>)/',
                                $completecta . ' $1',
                                $allresults,
                                1
                            );
                        } else {
                            $allresults = $allresults . $completecta;
                        }
                    
                    }

                }
            
            }
            
            // if add image is checked then we should send api request to get image

            if ( $qc_wpbotpro_article_heading_img == "1" ) {

                $imgresult  = apply_filters('qc_wpbotpro_floating_openai_article_heading_img',  $qc_wpbotpro_article_text, $img_size );

                //var_dump( $imgresult );
                //wp_die();

                // get half of qc_wpbotpro_article_number_of_heading and insert image in the middle
                $half = intval( $qc_wpbotpro_article_number_of_heading ) / 2;
                $half = round( $half );
                $half = $half - 1;
                // use qc_wpbotpro_article_heading_tag to add heading tag to image
                $allresults = explode( "</" . $qc_wpbotpro_article_heading_tag . ">", $allresults );
                $allresults[$half] = $allresults[$half] . $imgresult;
                $allresults = implode( "</" . $qc_wpbotpro_article_heading_tag . ">", $allresults );
                    
                $Parsedown = new Parsedown();
                
                $allresults = !empty( $allresults ) ? $Parsedown->text( $allresults ) : $allresults;

                wp_send_json( [ 'status' => 'success', 'keywords' => $allresults ] );
                wp_die();

            } else {

                wp_send_json( [ 'status' => 'success', 'keywords' => $allresults ] );
                wp_die();
            }


        }
    
        wp_send_json( [ 'status' => 'success', 'keywords' => $result_data ] );
        wp_die();
        
        // var_dump($dataresponse);wp_die();

    }
}

add_action( 'wp_ajax_qc_wpbotpro_floating_openai_save_draft_post_extra', 'qc_wpbotpro_floating_openai_save_draft_post_callback' );
add_action( 'wp_ajax_nopriv_qc_wpbotpro_floating_openai_save_draft_post_extra', 'qc_wpbotpro_floating_openai_save_draft_post_callback' );

if ( ! function_exists( 'qc_wpbotpro_floating_openai_save_draft_post_callback' ) ) {
    function qc_wpbotpro_floating_openai_save_draft_post_callback(){

        check_ajax_referer( 'kbx-qc', 'security');

        $qc_wpbotpro_floating_result = array(
            'status' => 'error',
            'msg'    => 'Something went wrong',
        );
        
        if ( isset( $_POST['title'] ) && !empty($_POST['title']) && isset( $_POST['content'] ) && !empty($_POST['content']) ) {

            $qc_wpbotpro_floating_allowed_html_content_post = wp_kses_allowed_html( 'post' );
            $qc_wpbotpro_floating_title     = sanitize_text_field( $_POST['title'] );
            $qc_wpbotpro_floating_content   = wp_kses( $_POST['content'], $qc_wpbotpro_floating_allowed_html_content_post );
            $qc_wpbotpro_floating_post_id   = wp_insert_post( array(
                'post_title'    => $qc_wpbotpro_floating_title,
                'post_content'  => $qc_wpbotpro_floating_content,
                ) 
            );
            
            if ( !is_wp_error( $qc_wpbotpro_floating_post_id ) ) {
                if ( array_key_exists( 'qc_wpbotpro_floating_settings', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_meta_key', $_POST['qc_wpbotpro_floating_settings'] );
                }
                if ( array_key_exists( 'qc_wpbotpro_floating_language', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_language', $_POST['qc_wpbotpro_floating_language'] );
                }
                if ( array_key_exists( 'qc_wpbotpro_floating_preview_title', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_preview_title', $_POST['qc_wpbotpro_floating_preview_title'] );
                }
                if ( array_key_exists( 'qc_wpbotpro_floating_number_of_heading', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_number_of_heading', $_POST['qc_wpbotpro_floating_number_of_heading'] );
                }
                if ( array_key_exists( 'qc_wpbotpro_floating_heading_tag', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_heading_tag', $_POST['qc_wpbotpro_floating_heading_tag'] );
                }
                if ( array_key_exists( 'qc_wpbotpro_floating_writing_style', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_writing_style', $_POST['qc_wpbotpro_floating_writing_style'] );
                }
                if ( array_key_exists( 'qc_wpbotpro_floating_writing_tone', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_writing_tone', $_POST['qc_wpbotpro_floating_writing_tone'] );
                }
                if ( array_key_exists( 'qc_wpbotpro_floating_modify_headings', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_modify_headings', $_POST['qc_wpbotpro_floating_modify_headings'] );
                }
                if ( array_key_exists( 'qc_wpbotpro_floating_add_img', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_add_img', $_POST['qc_wpbotpro_floating_add_img'] );
                }
                if ( array_key_exists( 'qc_wpbotpro_floating_add_tagline', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_add_tagline', $_POST['qc_wpbotpro_floating_add_tagline'] );
                }
                if ( array_key_exists( 'qc_wpbotpro_floating_add_intro', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_add_intro', $_POST['qc_wpbotpro_floating_add_intro'] );
                }
                if ( array_key_exists( 'qc_wpbotpro_floating_add_conclusion', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_add_conclusion', $_POST['qc_wpbotpro_floating_add_conclusion'] );
                }
                if ( array_key_exists( 'qc_wpbotpro_floating_anchor_text', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_anchor_text', $_POST['qc_wpbotpro_floating_anchor_text'] );
                }
                if ( array_key_exists( 'qc_wpbotpro_floating_target_url', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_target_url', $_POST['qc_wpbotpro_floating_target_url'] );
                }
                if ( array_key_exists( 'qc_wpbotpro_floating_generated_text', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_generated_text', $_POST['qc_wpbotpro_floating_generated_text'] );
                }
                // qc_wpbotpro_floating_cta_pos
                if ( array_key_exists( 'qc_wpbotpro_floating_cta_pos', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_cta_pos', $_POST['qc_wpbotpro_floating_cta_pos'] );
                }
                // qc_wpbotpro_floating_target_url_cta
                if ( array_key_exists( 'qc_wpbotpro_floating_target_url_cta', $_POST ) ) {
                    update_post_meta( $qc_wpbotpro_floating_post_id, 'qc_wpbotpro_floating_target_url_cta', $_POST['qc_wpbotpro_floating_target_url_cta'] );
                }
                $qc_wpbotpro_floating_result['status']  = 'success';
                $qc_wpbotpro_floating_result['msg']     = esc_html('Data Successfully Submitted.');
                $qc_wpbotpro_floating_result['id']      = $qc_wpbotpro_floating_post_id;
                $qc_wpbotpro_floating_result['post_link'] = esc_url( admin_url('edit.php') );

            }
        
        }
        
        wp_send_json( $qc_wpbotpro_floating_result );
    }
}


add_action( 'wp_ajax_qc_wpbotpro_floating_openai_keyword_rewrite_article', 'qc_wpbotpro_floating_openai_keyword_rewrite_article_callback' );
add_action( 'wp_ajax_nopriv_qc_wpbotpro_floating_openai_keyword_rewrite_article', 'qc_wpbotpro_floating_openai_keyword_rewrite_article_callback' );
if ( ! function_exists( 'qc_wpbotpro_floating_openai_keyword_rewrite_article_callback' ) ) {
    function qc_wpbotpro_floating_openai_keyword_rewrite_article_callback () {

        check_ajax_referer( 'kbx-qc', 'security');

        set_time_limit(600);

        $keyword        = isset( $_POST['keyword'] ) ?  $_POST['keyword'] : '';
        $OPENAI_API_KEY = get_option('open_ai_api_key');
        $ai_engines     = get_option('openai_engines');
        $max_token      = get_option('openai_max_tokens') ? get_option('openai_max_tokens') : 4000;
        $temperature    = get_option('openai_temperature') ? get_option('openai_temperature') : 0;
        $ppenalty       = get_option('presence_penalty');
        $fpenalty       = get_option('frequency_penalty');

        $datas = explode("\n",$keyword);

        $result_data = '';

        foreach( $datas as $data ) {

            if(!empty($data)){

                $prompt         = "rewrite this paragraph for a unique artical:\n\n" . $data;

                $gptkeyword = [];

                if($ai_engines == 'gpt-3.5-turbo' || $ai_engines == 'gpt-4' || $ai_engines == 'gpt-4o' || $ai_engines == 'gpt-4o-mini' ){

                    $ch = curl_init();
                    $url = 'https://api.openai.com/v1/chat/completions';

                    array_push($gptkeyword, array(
                               "role"       => "user",
                               "content"    =>  $prompt
                            ));

                    $post_fields = array(
                        "model"         => $ai_engines,
                        "messages"      => $gptkeyword,
                        "max_tokens"    => (int)$max_token,
                        "temperature"   => 0
                    );
                    $header  = [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $OPENAI_API_KEY
                    ];
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                    $result = curl_exec($ch);
                    if (curl_errno($ch)) {
                       echo esc_html('Error: ') . esc_html(curl_error($ch));
                    }
                    curl_close($ch);

                    $complete    = json_decode($result);

                    $Parsedown = new Parsedown();

                    $result_data .= isset( $complete->choices[0]->message->content ) ? $Parsedown->text( trim( $complete->choices[0]->message->content ) ) : '';


                }else{

                    $request_body = [
                        "prompt"            => $prompt,
                        "model"             =>  $ai_engines,
                        "max_tokens"        => (int)$max_token,
                        "temperature"       => (float)$temperature,
                        "presence_penalty"  => (float)$ppenalty,
                        "frequency_penalty" => (float)$fpenalty,
                    ];
                    $data    = json_encode($request_body);
                    $url     = "https://api.openai.com/v1/completions";
                    $apt_key = "Authorization: Bearer ". $OPENAI_API_KEY;

                    $curl = curl_init($url);
                    curl_setopt($curl, CURLOPT_URL, $url);
                    curl_setopt($curl, CURLOPT_POST, true);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    $headers    = array(
                       "Content-Type: application/json",
                       $apt_key ,
                    );
                    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                    $result     = curl_exec($curl);
                    curl_close($curl);
                    $results    = json_decode($result);
                    
                    $Parsedown = new Parsedown();

                    $result_data .= isset( $results->choices[0]->text ) ? $Parsedown->text( trim( $results->choices[0]->text ) ) : '';

                }

            }

        }

    
        wp_send_json( [ 'status' => 'success', 'keywords' => $result_data ] );
        wp_die();

    }
}



add_action( 'wp_ajax_qc_wpbotpro_floating_openai_qc_wpbotpro_content_generator_by_ajax', 'qc_wpbotpro_floating_openai_qc_wpbotpro_content_generator_by_ajax_callback' );
add_action( 'wp_ajax_nopriv_qc_wpbotpro_floating_openai_qc_wpbotpro_content_generator_by_ajax', 'qc_wpbotpro_floating_openai_qc_wpbotpro_content_generator_by_ajax_callback' );
if ( ! function_exists( 'qc_wpbotpro_floating_openai_qc_wpbotpro_content_generator_by_ajax_callback' ) ) {
    function qc_wpbotpro_floating_openai_qc_wpbotpro_content_generator_by_ajax_callback(){


        check_ajax_referer( 'kbx-qc', 'security');

        $qc_wpbotpro_floating_result = array(
            'status' => 'error',
            'msg'    => 'Something went wrong',
        );

        if(isset($_REQUEST['title']) && !empty($_REQUEST['title'])) {
            $qc_wpbotpro_floating_prompt = sanitize_text_field($_REQUEST['title']);

            $OPENAI_API_KEY = get_option('open_ai_api_key');
            $ai_engines     = get_option('openai_engines');
            $max_token      = get_option('openai_max_tokens') ? get_option('openai_max_tokens') : 4000;
            $temperature    = get_option('openai_temperature') ? get_option('openai_temperature') : 0;
            $ppenalty       = get_option('presence_penalty');
            $fpenalty       = get_option('frequency_penalty');

            if ( isset( $qc_wpbotpro_floating_prompt ) && !empty( $qc_wpbotpro_floating_prompt ) ) {

                    $gptkeyword = [];
                    if($ai_engines == 'gpt-3.5-turbo' || $ai_engines == 'gpt-4' || $ai_engines == 'gpt-4o' || $ai_engines == 'gpt-4o-mini'){
                        $ch = curl_init();
                        $url = 'https://api.openai.com/v1/chat/completions';

                        array_push($gptkeyword, array(
                                   "role"       => "user",
                                   "content"    =>  $qc_wpbotpro_floating_prompt
                                ));

                        $post_fields = array(
                            "model"         => $ai_engines,
                            "messages"      => $gptkeyword,
                            "max_tokens"    => (int)$max_token,
                            "temperature"   => ( float ) $temperature
                        );
                        $header  = [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $OPENAI_API_KEY
                        ];
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                        $result = curl_exec($ch);
                        if (curl_errno($ch)) {
                           echo esc_html('Error: ') . esc_html(curl_error($ch));
                        }
                        curl_close($ch);


                        $complete    = json_decode($result);

                        if ( isset( $complete->error ) ) {
                            $qc_wpbotpro_floating_result['msg'] = esc_html( trim( $complete->error->message ) );
                        } else {
                            $qc_wpbotpro_floating_result['data']    = $complete->choices[0]->message->content;
                            $qc_wpbotpro_floating_result['status']  = 'success';
                        }

                        //return $qc_wpbotpro_floating_result;

                        wp_send_json( $qc_wpbotpro_floating_result );

                        /*  
                        echo $result;
                        echo PHP_EOL;
                        ob_flush();
                        flush();
                        return strlen($result);
                        */

                    }else{

                        $ai_engines = 'text-davinci-003';
                        $request_body = [
                            "prompt"            => $qc_wpbotpro_floating_prompt,
                            "model"             =>  $ai_engines,
                            "max_tokens"        => (int)$max_token,
                            "temperature"       => (float)$temperature,
                            "presence_penalty"  => (float)$ppenalty,
                            "frequency_penalty" => (float)$fpenalty,
                            "stream"            => true,
                        ];
                        
                        $data    = json_encode($request_body);
                        $url     = "https://api.openai.com/v1/completions";
                        $apt_key = "Authorization: Bearer ". $OPENAI_API_KEY;

                        $curl = curl_init($url);
                        curl_setopt($curl, CURLOPT_URL, $url);
                        curl_setopt($curl, CURLOPT_POST, true);
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                        $headers    = array(
                           "Content-Type: application/json",
                           $apt_key,
                        );
                        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                        $result     = curl_exec($curl);
                        curl_close($curl);

                        //return $qc_wpbotpro_floating_result;
                        $complete    = json_decode($result);

                        if ( isset( $complete->error ) ) {
                            $qc_wpbotpro_floating_result['msg'] = esc_html( trim( $complete->error->message ) );
                        } else {
                            $qc_wpbotpro_floating_result['data'] = $complete->choices[0]->text;
                            $qc_wpbotpro_floating_result['status'] = 'success';
                        }

                        wp_send_json( $qc_wpbotpro_floating_result );

                       /* echo $result;
                        echo PHP_EOL;
                        ob_flush();
                        flush();
                        return strlen($result);*/

                    }

            
            }


        }
    }

}