(function ($){
    'use strict';

    $( document ).ready(function() {


        $(document).on('click','#qc_wpbotpro_content_generator', function(){

            //console.log('working');
            $('#qc_wpbotpro_content_generator_modal').modal( { backdrop: 'static', keyboard: false  }, 'show');
            jQuery('#qc_wpbotpro_content_generator_modal').modal('show')
            
        });


        $(document).on('click','#qc_wpbotpro_floating_openai_keyword_suggestion', function(){
            var qcld_keyword_suggestion         = $('#qc_wpbotpro_floating_openai_keyword_suggestion_mf').val();
            var qcld_keyword_number             = $('#qcld_keyword_number').val();
            var qc_wpbotpro_article_language           = $('#qc_wpbotpro_article_language').val();
            var qc_wpbotpro_article_number_of_heading  = $('#qc_wpbotpro_article_number_of_heading').val();
            var qc_wpbotpro_article_heading_tag        = $('#qc_wpbotpro_article_heading_tag').val();
            var qc_wpbotpro_article_heading_style      = $('#qc_wpbotpro_article_heading_style').val();
            var qc_wpbotpro_article_heading_tone       = $('#qc_wpbotpro_article_heading_tone').val();
            var qc_wpbotpro_article_heading_img        = $('#qc_wpbotpro_article_heading_img').val();
            var qc_wpbotpro_article_heading_img        = $("input[name=qc_wpbotpro_article_heading_img]:checked").val();
            var qc_wpbotpro_article_heading_tagline    = $("input[name=qc_wpbotpro_article_heading_tagline]:checked").val();
            var qc_wpbotpro_article_heading_intro      = $("input[name=qc_wpbotpro_article_heading_intro]:checked").val();
            var qc_wpbotpro_article_heading_conclusion = $("input[name=qc_wpbotpro_article_heading_conclusion]:checked").val();
            var qc_wpbotpro_article_label_anchor_text  = $('#qc_wpbotpro_article_label_anchor_text').val();
            var qc_wpbotpro_article_target_url         = $('#qc_wpbotpro_article_target_url').val();
            var qc_wpbotpro_article_target_label_cta   = $('#qc_wpbotpro_article_target_label_cta').val();
            var qc_wpbotpro_article_cta_pos            = $('#qc_wpbotpro_article_cta_pos').val();
            var qc_wpbotpro_article_label_keywords     = $('#qc_wpbotpro_article_label_keywords').val();
            var qc_wpbotpro_article_label_word_to_avoid= $('#qc_wpbotpro_article_label_word_to_avoid').val();
            var qc_wpbotpro_article_label_keywords_bold= $("input[name=qc_wpbotpro_article_label_keywords_bold]:checked").val();
            var qc_wpbotpro_article_heading_faq        = $("input[name=qc_wpbotpro_article_heading_faq]:checked").val();
            var qc_wpbotpro_article_img_size           = $('#qc_wpbotpro_article_img_size').val();

            $('#qc_wpbotpro_floating_openai_keyword_suggestion').addClass('spinning');
            $('#qc_wpbotpro_floating_openai_keyword_suggestion').prop("disabled",true);
            $('#qc_wpbotpro_floating_bait_article_keyword_data').hide();
            $('.qcld_seo-playground-buttons').hide();

            $.ajax({
              url: qc_wpbotpro_floating_ajaxurl,
              method: 'POST',
              data: {
                  'action': 'qc_wpbotpro_floating_openai_keyword_suggestion_content_function',
                  'keyword'                         : qcld_keyword_suggestion,
                  'keyword_number'                  : qcld_keyword_number,
                  'qc_wpbotpro_article_language'           : qc_wpbotpro_article_language,
                  'qc_wpbotpro_article_number_of_heading'  : qc_wpbotpro_article_number_of_heading,
                  'qc_wpbotpro_article_heading_tag'        : qc_wpbotpro_article_heading_tag,
                  'qc_wpbotpro_article_heading_style'      : qc_wpbotpro_article_heading_style,
                  'qc_wpbotpro_article_heading_tone'       : qc_wpbotpro_article_heading_tone,
                  'qc_wpbotpro_article_heading_img'        : qc_wpbotpro_article_heading_img,
                  'qc_wpbotpro_article_heading_tagline'    : qc_wpbotpro_article_heading_tagline,
                  'qc_wpbotpro_article_heading_intro'      : qc_wpbotpro_article_heading_intro,
                  'qc_wpbotpro_article_heading_conclusion' : qc_wpbotpro_article_heading_conclusion,
                  'qc_wpbotpro_article_label_anchor_text'  : qc_wpbotpro_article_label_anchor_text,
                  'qc_wpbotpro_article_target_url'         : qc_wpbotpro_article_target_url,
                  'qc_wpbotpro_article_target_label_cta'   : qc_wpbotpro_article_target_label_cta,
                  'qc_wpbotpro_article_cta_pos'            : qc_wpbotpro_article_cta_pos,
                  'qc_wpbotpro_article_label_keywords'     : qc_wpbotpro_article_label_keywords,
                  'qc_wpbotpro_article_label_word_to_avoid': qc_wpbotpro_article_label_word_to_avoid,
                  'qc_wpbotpro_article_label_keywords_bold': qc_wpbotpro_article_label_keywords_bold,
                  'qc_wpbotpro_article_heading_faq'        : qc_wpbotpro_article_heading_faq,
                  'qc_wpbotpro_article_img_size'           : qc_wpbotpro_article_img_size,
                  'security'                        : qc_wpbotpro_floating_ajax_nonce
                  //'selectedlanguage': selectedlanguage,
              },
              dataType: 'json',
              success: function(response) {
                //$('#qc_wpbotpro_floating_bait_keyword_data').append(response.keywords);
                $('#qc_wpbotpro_floating_openai_keyword_suggestion').prop("disabled",false);
                $('#qc_wpbotpro_floating_openai_keyword_suggestion').removeClass('spinning');
                $('.qcld_seo-playground-buttons').show();
                //$('#qc_wpbotpro_floating_bait_article_keyword_data').html('<div class="qcld_copied-content-wrap"><div class="qcld_copied-content_text btn d-none link-success">Copied</div><a class="btn btn-sm btn-secondary qcld-copied-content_text"><span class="dashicons dashicons-admin-page"></span></a></div><textarea id="qc_wpbotpro_floating_content_result_msg">' + response.keywords +'</textarea>');
                //$('#qc_wpbotpro_floating_bait_article_keyword_data').append('<div class="qc_wpbotpro_floating_content_result_wrap"><div class="qc_wpbotpro_floating_rewrite_result_count">' + response.keywords.length +'</div></div>');
                        // qc_wpbotpro_floating_content_result_msg
                $('#qc_wpbotpro_floating_content_result_msg').focus();
                $('#qc_wpbotpro_floating_content_result_msg').focusout();
                $('#qc_wpbotpro_floating_bait_article_keyword_data').show();
                $('#qc_wpbotpro_floating_content_rewrite_result').show();
                $('.qc_wpbotpro_floating_bait_single_field .qc_wpbotpro_floating_rewrite_result_count').text( response.keywords.length );
                var editor = tinyMCE.get('qc_wpbotpro_floating_content_result_msg');
                var basicEditor = true;
                if ( $('#wp-qc_wpbotpro_floating_content_result_msg-wrap').hasClass('tmce-active') && editor ) {
                    basicEditor = false;
                }
                if(basicEditor){
                    $('#qc_wpbotpro_floating_content_result_msg').val( response.keywords );
                }else{
                    editor.setContent( response.keywords );
                }
              }
            });

        });


        $(document).on('click', '.qc_wpbotpro_floating_openai_article_save', function (e) {
            var qc_wpbotpro_floating_draft_btn = $(this);
            var title = $('#qc_wpbotpro_floating_openai_keyword_suggestion_mf').val();
            var content = $('#qc_wpbotpro_floating_content_result_msg').val();

            var editor = tinyMCE.get('qc_wpbotpro_floating_content_result_msg');
            var basicEditor = true;
            if ( $('#wp-qc_wpbotpro_floating_content_result_msg-wrap').hasClass('tmce-active') && editor ) {
                basicEditor = false;
            }

            if(!basicEditor){
                var content = editor.getContent();

            }

            if(title === ''){
                alert('Please enter title');
            }else if(content === ''){
                alert('Please wait content generated');
            }else{
                $.ajax({
                    url: qc_wpbotpro_floating_ajaxurl,
                    data: {title: title, content: content, action: 'qc_wpbotpro_floating_openai_save_draft_post_extra', security: qc_wpbotpro_floating_ajax_nonce },
                    dataType: 'json',
                    type: 'POST',
                    beforeSend: function (){
                        qc_wpbotpro_floating_draft_btn.attr('disabled','disabled');
                        qc_wpbotpro_floating_draft_btn.append('<span class="spinner"></span>');
                        qc_wpbotpro_floating_draft_btn.find('.spinner').css('visibility','unset');
                    },
                    success: function (res){
                        qc_wpbotpro_floating_draft_btn.removeAttr('disabled');
                        qc_wpbotpro_floating_draft_btn.find('.spinner').remove();
                        if(res.status === 'success'){
                            window.location.replace( res.post_link );
                        }
                        else{
                            alert(res.msg);
                        }
                    },
                    error: function (){
                        qc_wpbotpro_floating_draft_btn.removeAttr('disabled');
                        qc_wpbotpro_floating_draft_btn.find('.spinner').remove();
                        alert('Something went wrong');
                    }
                });
            }
        });

        $(document).on('click','#qc_wpbotpro_floating_openai_keyword_rewrite_article',function(event){
            var qc_wpbotpro_floating_content_rewrite = $('#qc_wpbotpro_floating_content_rewrite').val();
            $('#qc_wpbotpro_floating_openai_keyword_rewrite_article').addClass('spinning');
            $('#qc_wpbotpro_floating_content_rewrite_result').hide();
            $.ajax({
              url: qc_wpbotpro_floating_ajaxurl,
              method: 'POST',
              data: {
                  'action': 'qc_wpbotpro_floating_openai_keyword_rewrite_article',
                  'security': qc_wpbotpro_floating_ajax_nonce,
                  'keyword': qc_wpbotpro_floating_content_rewrite,
              },
              dataType: 'json',
              success: function(response) {
                //$('#qc_wpbotpro_floating_content_rewrite_result').html('<div class="qcld_copied-content-wrap"><div class="qcld_copied-content btn d-none link-success">Copied</div><a class="btn btn-sm btn-secondary qcld-copied-content"><span class="dashicons dashicons-admin-page"></span></a></div><textarea id="qc_wpbotpro_floating_content_rewrite_result_msg">' + response.keywords +'</textarea>');
                //$('#qc_wpbotpro_floating_content_rewrite_result').append('<div class="qc_wpbotpro_floating_rewrite_result_count_wrap"><div class="qc_wpbotpro_floating_rewrite_result_count">' + response.keywords.length +'</div></div>');
                $('#qc_wpbotpro_floating_content_rewrite_result').show();
                var editor = tinyMCE.get('qc_wpbotpro_floating_content_rewrite_result_msg');
                var basicEditor = true;
                if ( $('#wp-qc_wpbotpro_floating_content_rewrite_result_msg-wrap').hasClass('tmce-active') && editor ) {
                    basicEditor = false;
                }
                if(basicEditor){
                    $('#qc_wpbotpro_floating_content_rewrite_result_msg').val( response.keywords );
                }else{
                    editor.setContent( response.keywords );
                }
                var words = $.trim(response.keywords).split(" ");
                //console.log(words.length)
                $('.qc_wpbotpro_floating_rewrite_result_count_wrap .qc_wpbotpro_floating_rewrite_result_count').text( words.length );
                $('#qc_wpbotpro_floating_content_rewrite_result_msg').focus();
                $('#qc_wpbotpro_floating_content_rewrite_result_msg').focusout();
                $('#qc_wpbotpro_floating_openai_keyword_rewrite_article').removeClass('spinning');
              }
            });
          

        });

        $(document).on('click', '.qc_wpbotpro_floating_openai_article_clear', function (e) {

            $('#qc_wpbotpro_floating_openai_keyword_suggestion_mf').val('');
            $('#qc_wpbotpro_floating_content_result_msg').val('');
            $('.qcld_seo-playground-buttons').hide();
            $('.qc_wpbotpro_floating_rewrite_result_count').hide();

        });

        $(document).on('keyup focusout','#qc_wpbotpro_floating_content_rewrite',function(event){
            var currentDom = $(this);
            $('.qc_wpbotpro_floating_content_rewrite_count').remove();
            var words = $.trim(currentDom.val()).split(" ");
            $('.qc_wpbotpro_floating_content_rewrite_count_wrap').html('<span class="qc_wpbotpro_floating_content_rewrite_count">'+words.length+'</span>');
        });

        $(document).on('keyup focusout','#qc_wpbotpro_floating_content_rewrite_result_msg',function(event){
            var currentDomss = $(this);
            var editor = tinyMCE.get('qc_wpbotpro_floating_content_rewrite_result_msg');
            var basicEditor = true;
            if ( $('#wp-qc_wpbotpro_floating_content_rewrite_result_msg-wrap').hasClass('tmce-active') && editor ) {
                basicEditor = false;
            }
            if(basicEditor){
                var wordsss = $.trim(currentDomss.val()).split(" ");
            }else{
                var datasssss = editor.setContent( );
                var wordsss = $.trim(datasssss).split(" ");
            }
            $('.qc_wpbotpro_floating_rewrite_result_count_wrap .qc_wpbotpro_floating_rewrite_result_count').text(wordsss.length);
        });

        $(document).on('keyup focusout','#qc_wpbotpro_floating_content_result_msg',function(event){
            var currentDoms = $(this);
            var wordss = $.trim(currentDoms.val()).split(" ");
            $('.qc_wpbotpro_floating_bait_single_field .qc_wpbotpro_floating_rewrite_result_count').text(wordss.length);
        });

        $(document).on('click','.qcld-copied-content',function(event){
            var currentDom = $(this);
            var copy_con = $('#qc_wpbotpro_floating_content_rewrite_result_msg').val();

            var editor = tinyMCE.get('qc_wpbotpro_floating_content_rewrite_result_msg');
            var basicEditor = true;
            if ( $('#wp-qc_wpbotpro_floating_content_rewrite_result_msg-wrap').hasClass('tmce-active') && editor ) {
                basicEditor = false;
            }

            if(!basicEditor){
                var copy_con = editor.getContent();

            }
            

            $(this).addClass("qcld_copied");

            $(".qcld_copied-content").removeClass("d-none");
            setTimeout(() => {
                $(".qcld_copied-content").addClass("d-none");
            }, 1500);
            var $temp = $("<input>");
            //$("body").append($temp);
            currentDom.append($temp);
            $temp.val( copy_con ).select();
            document.execCommand("copy");
            $temp.remove();

        });
        $(document).on('click','.qcld-copied-content_text',function(event){
            var currentDom = $(this);
            var copy_con = currentDom.parent().parent().parent().find('#qc_wpbotpro_floating_content_result_msg').val();
            var copy_text = (copy_con !== '') ? copy_con : currentDom.parent().parent().parent().find('#qc_wpbotpro_floating_content_result_msg').text();

            var editor = tinyMCE.get('qc_wpbotpro_floating_content_result_msg');
            var basicEditor = true;
            if ( $('#wp-qc_wpbotpro_floating_content_result_msg-wrap').hasClass('tmce-active') && editor ) {
                basicEditor = false;
            }

            if(!basicEditor){
                var copy_text = editor.getContent();
            }

            currentDom.addClass("qcld_copied");

            currentDom.parent().find(".qcld_copied-content_text").removeClass("d-none");
            setTimeout(() => {
            currentDom.parent().find(".qcld_copied-content_text").addClass("d-none");
            }, 1500);

            var $temp = $("<input>");
            currentDom.append($temp);
            $temp.val( copy_text ).select();
            document.execCommand("copy");
            $temp.remove();

        });

        var qc_wpbotpro_floating_generator_working = false;
        var eventGenerator = false;
        var qc_wpbotpro_floating_limitLines = 5;
        function qcld_stopOpenAIGenerator(){
            $('.qcld_seo-playground-buttons').show();
            $('.qc_wpbotpro_floating_openai_generator_stop').hide();
            qc_wpbotpro_floating_generator_working = false;
            $('.qc_wpbotpro_floating_generator_button .spinner').hide();
            $('.qc_wpbotpro_floating_generator_button').removeAttr('disabled');
            //eventGenerator.close();
        }
        $(document).on('click', '.qc_wpbotpro_floating_openai_generator_stop', function (e) {
            qcld_stopOpenAIGenerator();
        });

        $(document).on('click', '.qc_wpbotpro_floating_openai_generator_button', function (e) {
            var btn = $(this);
            var title = $('.qc_wpbotpro_floating_prompt').val();
            if( title !== '' ) {
                var count_line = 0;
                var qc_wpbotpro_floating_generator_result = $('.qc_wpbotpro_floating_generator_result');
                qc_wpbotpro_floating_generator_result.val('');
                $('.qc_wpbotpro_floating_openai_generator_stop').show();
                $('.qcld_seo-playground-buttons').hide();
                $('.qc_wpbotpro_floating_openai_generator_button').addClass('spinning');

                var editor = tinyMCE.get('qc_wpbotpro_floating_generator_result');
                var basicEditor = true;
                if ( $('#wp-qc_wpbotpro_floating_generator_result-wrap').hasClass('tmce-active') && editor ) {
                    basicEditor = false;
                }
                var qc_wpbotpro_floating_currentContent_Text = '';
                //console.log( eventGenerator );

                $.ajax({
                    url: qc_wpbotpro_floating_ajaxurl,
                    data: { title: title, action: 'qc_wpbotpro_floating_openai_qc_wpbotpro_content_generator_by_ajax', security: qc_wpbotpro_floating_ajax_nonce },
                    dataType: 'json',
                    type: 'POST',
                    beforeSend: function (){
                        btn.attr('disabled','disabled');
                        btn.find('.spinner').show();
                        btn.find('.spinner').css('visibility','unset');
                    },
                    success: function (res){
                        btn.removeAttr('disabled');
                        $('.qc_wpbotpro_floating_openai_generator_stop').hide();
                        $('.qc_wpbotpro_floating_openai_generator_button').removeClass('spinning');
                        //console.log( res)
                        if(res.status === 'success'){
                            //window.location.href;
                            $('.qcld_seo-playground-buttons').show();
                            if(basicEditor){
                                qc_wpbotpro_floating_currentContent_Text = $('#qc_wpbotpro_floating_generator_result').val();
                            }else{
                                qc_wpbotpro_floating_currentContent_Text = editor.getContent();
                                qc_wpbotpro_floating_currentContent_Text = qc_wpbotpro_floating_currentContent_Text.replace(/<\/?p(>|$)/g, "");
                            }
                            if(e.data === "[DONE]"){
                                count_line += 1;
                                if(basicEditor) {

                                    $('#qc_wpbotpro_floating_generator_result').val( qc_wpbotpro_floating_currentContent_Text + '<br><br>' );

                                }else{

                                    editor.setContent( qc_wpbotpro_floating_currentContent_Text + '<br><br>' );

                                }
                            } else{
                               

                                if(basicEditor){
                                    
                                    if(res.title){
                                        $('.qc_wpbotpro_floating_prompt').val( res.title );
                                    }
                                    $('#qc_wpbotpro_floating_generator_result').val( res.data );

                                }else{
                                    if(res.title){
                                        $('.qc_wpbotpro_floating_prompt').val( res.title );
                                    }
                                    editor.setContent(qc_wpbotpro_floating_currentContent_Text+  res.data );

                                }


                            }
                            
                            if(count_line === qc_wpbotpro_floating_limitLines){
                                qcld_stopOpenAIGenerator();
                            }




                        }else{
                            alert(res.msg);
                        }
                    },
                    error: function (){
                        btn.removeAttr('disabled');
                        btn.find('.spinner').remove();
                        $('.qc_wpbotpro_floating_openai_generator_stop').hide();
                        alert('Something went wrong');
                    }
                });

            }
        });
    
        $(document).on('click', '.qc_wpbotpro_floating_openai_playground_save', function (e) {
            var qc_wpbotpro_floating_draft_btn = $(this);
            var title = $('.qc_wpbotpro_floating_prompt').val();
            var editor = tinyMCE.get('qc_wpbotpro_floating_generator_result');
            var basicEditor = true;
            if ( $('#wp-qc_wpbotpro_floating_generator_result-wrap').hasClass('tmce-active') && editor ) {
                basicEditor = false;
            }
            var content = '';
            if (basicEditor){
                content = $('#qc_wpbotpro_floating_generator_result').val();
            }else{
                content = editor.getContent();
            }
            if(title === ''){
                alert('Please enter title');
            }
            else if(content === ''){
                alert('Please wait content generated');
            }
            else{
                $.ajax({
                    url: ajaxurl,
                    data: {title: title, content: content, action: 'qc_wpbotpro_floating_openai_save_draft_post_extra', security: qc_wpbotpro_floating_ajax_nonce },
                    dataType: 'json',
                    type: 'POST',
                    beforeSend: function (){
                        qc_wpbotpro_floating_draft_btn.attr('disabled','disabled');
                        qc_wpbotpro_floating_draft_btn.append('<span class="spinner"></span>');
                        qc_wpbotpro_floating_draft_btn.find('.spinner').css('visibility','unset');
                    },
                    success: function (res){
                        qc_wpbotpro_floating_draft_btn.removeAttr('disabled');
                        qc_wpbotpro_floating_draft_btn.find('.spinner').remove();
                        if(res.status === 'success'){
                            //window.location.href;
                            window.location.replace( res.post_link );
                        }
                        else{
                            alert(res.msg);
                        }
                    },
                    error: function (){
                        qc_wpbotpro_floating_draft_btn.removeAttr('disabled');
                        qc_wpbotpro_floating_draft_btn.find('.spinner').remove();
                        alert('Something went wrong');
                    }
                });
            }
        });
        $(document).on('click', '.qc_wpbotpro_floating_openai_playground_clear', function (e) {

            $('.qc_wpbotpro_floating_prompt').val('');
            var editor = tinyMCE.get('qc_wpbotpro_floating_generator_result');
            var basicEditor = true;
            if ( $('#wp-qc_wpbotpro_floating_generator_result-wrap').hasClass('tmce-active') && editor ) {
                basicEditor = false;
            }
            if(basicEditor){
                $('#qc_wpbotpro_floating_generator_result').val('');
            }
            else{
                editor.setContent('');
            }
        });

    });

})(jQuery)