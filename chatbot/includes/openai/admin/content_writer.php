<?php 
global $wpchatbot_pro_professional_init,$wpchatbot_pro_master_init;
if((isset($wpchatbot_pro_master_init) && $wpchatbot_pro_master_init->is_valid()) || (isset($wpchatbot_pro_professional_init) && $wpchatbot_pro_professional_init->is_valid()) || (function_exists('get_openaiaddon_valid_license') && get_openaiaddon_valid_license())){
?>
<div class="wrap fs-section">
        <div class="qcld-seohelp qcld_seo_ai_single_content">
            <div class="qcld-seohelp-input">
                <div class="qcld-seohelp-input-field">
                    <label for="qcld_article_keyword_suggestion" class="form-label"><?php esc_html_e('Prompt', 'chatbot' ); ?></label><br>
                    <input type="text" id="qcld_article_keyword_suggestion_mf" class="form-control" data-press="qcld_article_keyword_suggestion"><br>
                </div>
            </div>
            <div class="qcld-seohelp-input">
                <div class="qcld-seohelp-input-field qcld-seohelp-input-field_ai_wrap">
                    <div class="qcld_seohelp_ai_con">
                        <label for="qcld_article_number_of_heading"><?php esc_html_e( "How many headings?", 'chatbot' ); ?></label>
                        <input type="number" placeholder="e.g. 5" id="qcld_article_number_of_heading" class="qcld_article_number_of_heading" name="qcld_article_number_of_heading" value="">
                    </div>

                    <div class="qcld_seohelp_ai_con">
                        <label for="qcld_article_heading_tag"><?php esc_html_e( "Heading Tag", 'chatbot' ); ?></label>
                        <select name="qcld_article_heading_tag" id="qcld_article_heading_tag">
                            <option value="h1"><?php esc_html_e( "h1", 'chatbot' ); ?></option>
                            <option value="h2"><?php esc_html_e( "h2", 'chatbot' ); ?></option>
                            <option value="h3"><?php esc_html_e( "h3", 'chatbot' ); ?></option>
                            <option value="h4"><?php esc_html_e( "h4", 'chatbot' ); ?></option>
                            <option value="h5"><?php esc_html_e( "h5", 'chatbot' ); ?></option>
                            <option value="h6"><?php esc_html_e( "h6", 'chatbot' ); ?></option>
                        </select>
                    </div>

                    <div class="qcld_seohelp_ai_con">
                        <label for="qcld_article_heading_style"><?php esc_html_e( "Writing Style", 'chatbot' ); ?></label>
                        <select name="qcld_article_heading_style" id="qcld_article_heading_style">
                            <option value="infor"><?php esc_html_e( "Informative", 'chatbot' ); ?></option>
                            <option value="analy"><?php esc_html_e( "Analytical", 'chatbot' ); ?></option>
                            <option value="argum"><?php esc_html_e( "Argumentative", 'chatbot' ); ?></option>
                            <option value="creat"><?php esc_html_e( "Creative", 'chatbot' ); ?></option>
                            <option value="criti"><?php esc_html_e( "Critical", 'chatbot' ); ?></option>
                            <option value="descr"><?php esc_html_e( "Descriptive", 'chatbot' ); ?></option>
                            <option value="evalu"><?php esc_html_e( "Evaluative", 'chatbot' ); ?></option>
                            <option value="expos"><?php esc_html_e( "Expository", 'chatbot' ); ?></option>
                            <option value="journ"><?php esc_html_e( "Journalistic", 'chatbot' ); ?></option>
                            <option value="narra"><?php esc_html_e( "Narrative", 'chatbot' ); ?></option>
                            <option value="persu"><?php esc_html_e( "Persuasive", 'chatbot' ); ?></option>
                            <option value="refle"><?php esc_html_e( "Reflective", 'chatbot' ); ?></option>
                            <option value="simpl"><?php esc_html_e( "Simple", 'chatbot' ); ?></option>
                            <option value="techn"><?php esc_html_e( "Technical", 'chatbot' ); ?></option>
                            <option value="repor"><?php esc_html_e( "Report", 'chatbot' ); ?></option>
                            <option value="resea"><?php esc_html_e( "Research", 'chatbot' ); ?></option>
                        </select>
                    </div>

                    <div class="qcld_seohelp_ai_con">
                        <label for="qcld_article_heading_tone"><?php esc_html_e( "Writing Tone", 'chatbot' ); ?></label>
                        <select name="qcld_article_heading_tone" id="qcld_article_heading_tone">
                            <option value="formal"><?php esc_html_e( "Formal", 'chatbot' ); ?></option>
                            <option value="asser"><?php esc_html_e( "Assertive", 'chatbot' ); ?></option>
                            <option value="cheer"><?php esc_html_e( "Cheerful", 'chatbot' ); ?></option>
                            <option value="humor"><?php esc_html_e( "Humorous", 'chatbot' ); ?></option>
                            <option value="informal"><?php esc_html_e( "Informal", 'chatbot' ); ?></option>
                            <option value="inspi"><?php esc_html_e( "Inspirational", 'chatbot' ); ?></option>
                            <option value="neutr"><?php esc_html_e( "Neutral", 'chatbot' ); ?></option>
                            <option value="profe"><?php esc_html_e( "Professional", 'chatbot' ); ?></option>
                            <option value="sarca"><?php esc_html_e( "Sarcastic", 'chatbot' ); ?></option>
                            <option value="skept"><?php esc_html_e( "Skeptical", 'chatbot' ); ?></option>
                            <option value="curio"><?php esc_html_e( "Curious", 'chatbot' ); ?></option>
                            <option value="disap"><?php esc_html_e( "Disappointed", 'chatbot' ); ?></option>
                            <option value="encou"><?php esc_html_e( "Encouraging", 'chatbot' ); ?></option>
                            <option value="optim"><?php esc_html_e( "Optimistic", 'chatbot' ); ?></option>
                            <option value="surpr"><?php esc_html_e( "Surprised", 'chatbot' ); ?></option>
                            <option value="worry"><?php esc_html_e( "Worried", 'chatbot' ); ?></option>

            
                        </select>
                    </div>

                    <div class="qcld_seohelp_ai_con">
                        <label for="qcld_article_img_size" ><?php esc_html_e('Image Size', 'chatbot' ); ?></label>
                        <select name="qcld_article_img_size" id="qcld_article_img_size">
                            <option value="256x256"><?php esc_html_e( "256x256", 'chatbot' ); ?> </option>
                            <option value="512x512" selected><?php esc_html_e( "512x512", 'chatbot' ); ?> </option>
                            <option value="1024x1024"><?php esc_html_e( "1024x1024", 'chatbot' ); ?> </option>
                        </select>
                    </div>

                    <div class="qcld_seohelp_ai_con">
                        <label for="qcld_article_language"><?php esc_html_e( "Language", 'chatbot' ); ?></label>
                        <select name="qcld_article_language" id="qcld_article_language">
                            <option value="en"><?php esc_html_e( "English", 'chatbot' ); ?> </option>
                            <option value="ar"><?php esc_html_e( "Arabic", 'chatbot' ); ?> </option>
                            <option value="bg"><?php esc_html_e( "Bulgarian", 'chatbot' ); ?> </option>
                            <option value="zh"><?php esc_html_e( "Chinese", 'chatbot' ); ?> </option>
                            <option value="cs"><?php esc_html_e( "Czech", 'chatbot' ); ?> </option>
                            <option value="nl"><?php esc_html_e( "Dutch", 'chatbot' ); ?> </option>
                            <option value="fr"> <?php esc_html_e( "French", 'chatbot' ); ?> </option>
                            <option value="de"> <?php esc_html_e( "German", 'chatbot' ); ?> </option>
                            <option value="el"> <?php esc_html_e( "Greek", 'chatbot' ); ?> </option>
                            <option value="hi"> <?php esc_html_e( "Hindi", 'chatbot' ); ?> </option>
                            <option value="hu"> <?php esc_html_e( "Hungarian", 'chatbot' ); ?> </option>
                            <option value="id"> <?php esc_html_e( "Indonesian", 'chatbot' ); ?> </option>
                            <option value="it"> <?php esc_html_e( "Italian", 'chatbot' ); ?> </option>
                            <option value="ja"> <?php esc_html_e( "Japanese", 'chatbot' ); ?> </option>
                            <option value="ko"> <?php esc_html_e( "Korean", 'chatbot' ); ?> </option>
                            <option value="pl"> <?php esc_html_e( "Polish", 'chatbot' ); ?> </option>
                            <option value="pt"> <?php esc_html_e( "Portuguese", 'chatbot' ); ?> </option>
                            <option value="ro"> <?php esc_html_e( "Romanian", 'chatbot' ); ?> </option>
                            <option value="ru"> <?php esc_html_e( "Russian", 'chatbot' ); ?> </option>
                            <option value="es"> <?php esc_html_e( "Spanish", 'chatbot' ); ?> </option>
                            <option value="sv"> <?php esc_html_e( "Swedish", 'chatbot' ); ?> </option>
                            <option value="tr"> <?php esc_html_e( "Turkish", 'chatbot' ); ?> </option>
                            <option value="uk"> <?php esc_html_e( "Ukranian", 'chatbot' ); ?> </option>
                        </select>
                    </div>


                </div>
            </div>
            <div class="qcld-seohelp-input">
                <div class="qcld-seohelp-input-field qcld-seohelp-input-field_ai_wrap col-sm-12">
                    <div class="qcld_seohelp_ai_con ">
                        <input type="checkbox" name="qcld_article_heading_img" id="qcld_article_heading_img" class="qcld_article_heading_img" value="1"/>
                        <label for="qcld_article_heading_img" class="form-check-label"><?php esc_html_e( "Add Image", 'chatbot' ); ?></label>
                    </div>

                    <div class="qcld_seohelp_ai_con form-check">
                        <input type="checkbox" id="qcld_article_heading_tagline"  name="qcld_article_heading_tagline" class="qcld_article_heading_tagline" value="1" />
                        <label for="qcld_article_heading_tagline" class="form-check-label"><?php esc_html_e( "Add Tagline", 'chatbot' ); ?></label>
                    </div>

                    <div class="qcld_seohelp_ai_con form-check">
                        <input type="checkbox" id="qcld_article_heading_intro" name="qcld_article_heading_intro" class="qcld_article_heading_intro" value="1"/>
                        <label for="qcld_article_heading_intro" class="form-check-label"><?php esc_html_e( "Add Introduction", 'chatbot' ); ?></label>
                    </div>

                    <div class="qcld_seohelp_ai_con form-check">
                        <input type="checkbox" id="qcld_article_heading_conclusion" name="qcld_article_heading_conclusion" class="qcld_article_heading_conclusion" value="1" />
                        <label for="qcld_article_heading_conclusion" class="form-check-label"><?php esc_html_e( "Add Conclusion", 'chatbot' ); ?></label>
                    </div>

                    <div class="qcld_seohelp_ai_con form-check">
                        <input type="checkbox" id="qcld_article_heading_faq" name="qcld_article_heading_faq" class="qcld_article_heading_faq" value="1" />
                        <label for="qcld_article_heading_faq" class="form-check-label"><?php esc_html_e( "Add Faq", 'chatbot' ); ?></label>
                    </div>
                </div>
            </div>
            <div class="qcld-seohelp-input">
                <div class="qcld-seohelp-input-field qcld-seohelp-input-field_ai_wrap">

                    <div class="qcld_seohelp_ai_con">
                        <label for="qcld_article_label_anchor_text"><?php esc_html_e( "Anchor Text", 'chatbot' ); ?></label>
                        <input type="text" id="qcld_article_label_anchor_text" placeholder="e.g. battery life" class="qcld_article_label_anchor_text" name="qcld_article_label_anchor_text" >
                    </div>

                    <div class="qcld_seohelp_ai_con">
                        <label for="qcld_article_target_url"><?php esc_html_e( "Target URL", 'chatbot' ); ?></label>
                        <input type="url" id="qcld_article_target_url" placeholder="https://..." class="qcld_article_target_url" name="qcld_article_target_url">
                    </div>

                    <div class="qcld_seohelp_ai_con">
                        <label for="qcld_article_target_label_cta"><?php esc_html_e( "Add Call-to-Action", 'chatbot' ); ?></label>
                        <input type="url" id="qcld_article_target_label_cta" placeholder="https://..." class="qcld_article_target_label_cta" name="qcld_article_target_label_cta">
                    </div>


                    <div class="qcld_seohelp_ai_con">
                        <label for="qcld_article_cta_pos"><?php esc_html_e( "Call-to-Action Position", 'chatbot' ); ?></label>
                        <select name="qcld_article_cta_pos" id="qcld_article_cta_pos">
                            <option value="beg"><?php esc_html_e( "Beginning", 'chatbot' ); ?></option>
                            <option value="end"><?php esc_html_e( "End", 'chatbot' ); ?></option>
                        </select>
                    </div>


                </div>
            </div>
            <div class="qcld-seohelp-input">
                <div class="qcld-seohelp-input-field qcld-seohelp-input-field_ai_wrap">

                    <div class="qcld_seohelp_ai_con">
                        <label for="qcld_article_label_keywords"><?php esc_html_e( "Add Keywords", 'chatbot' ); ?></label>
                        <input type="text" id="qcld_article_label_keywords" placeholder="Write Keywords..." class="qcld_article_label_keywords" name="qcld_article_label_keywords">
                        <p><i><?php esc_html_e( "Use comma to seperate keywords", 'chatbot' ); ?></i></p>
                    </div>

                    <div class="qcld_seohelp_ai_con">
                        <label for="qcld_article_label_word_to_avoid"><?php esc_html_e( "Keywords to Avoid", 'chatbot' ); ?></label>
                        <input type="text" id="qcld_article_label_word_to_avoid" placeholder="Write Keywords..." class="qcld_article_label_word_to_avoid" name="qcld_article_label_word_to_avoid" value="">
                        <p><i><?php esc_html_e( "Use comma to seperate keywords", 'chatbot' ); ?></i></p>
                    </div>

                    <div class="qcld_seohelp_ai_con form-check">
                        <input type="checkbox" id="qcld_article_label_keywords_bold" class="qcld_article_label_keywords_bold" name="qcld_article_label_keywords_bold" value="1">
                        <label for="qcld_article_label_keywords_bold" class="form-check-label"><?php esc_html_e( "Make Keywords Bold", 'chatbot' ); ?></label>
                    </div>


                </div>
            </div>
            <button id="qcld_article_keyword_suggestion" class="btn btn-info"><?php esc_html_e('Generate', 'chatbot'); ?></button>
        </div>
        <hr/>
        <div class="linkbait_single_field"> 
            <div id="linkbait_article_keyword_data">
            </div>
        </div>
</div>
<?php
 } else { ?>
<div class="row my-4">
    <div  class="col-md-12">
        <?php esc_html_e('Fine tuning and training is available with the ','chatbot');?>
        <a href="https://www.wpbot.pro/pricing/"><?php esc_html_e('WPBot Pro Professional','chatbot'); ?></a>
        <?php esc_html_e(' and ', 'chatbot'); ?>
        <a href="https://www.wpbot.pro/pricing/"><?php esc_html_e('Master','chatbot'); ?></a>
        <?php esc_html_e(' Licenses','chatbot'); ?>
    </div>
</div>
<?php } ?>