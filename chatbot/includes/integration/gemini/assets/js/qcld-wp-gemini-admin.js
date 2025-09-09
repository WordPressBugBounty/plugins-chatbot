jQuery(document).ready(function($) {
    // Function to fetch models from OpenRouter API

    var settingsOpenrouter = document.getElementById("qcld_save_gemini_setting");
    if(settingsOpenrouter){
        $('.qcl-openai').on('click', '#qcld_save_gemini_setting', function(){
            if ($('#qcld_gemini_enabled').is(":checked")){
                var gemini_enabled = 1;
            }else{
                var gemini_enabled = 0;
            }
            var qcld_gemini_page_suggestion_enabled = jQuery("#qcld_gemini_page_suggestion_enabled").is(":checked") ? 1 : 0;
            var gemini_is_context_awareness_enabled = jQuery("#gemini_is_context_awareness_enabled").is(":checked") ? 1 : 0;
            var qcld_gemini_api_key = jQuery("#qcld_gemini_api_key").val();
            var qcld_gemini_model = jQuery('#qcld_gemini_model').val();
            var qcld_gemini_append_content = jQuery('#qcld_gemini_append_content').val();
            var qcld_gemini_prepend_content = jQuery('#qcld_gemini_prepend_content').val();
            var post_gemini_types = $.map($('input[name="site_gemini_search_posttypes[]"]:checked'), function(c){return c.value; });
            $.ajax({
                url: ajax_object.ajax_url,
                type:'POST',
                data: {
                    action: 'qcld_gemini_settings_option',
                    nonce: ajax_object.ajax_nonce,
                    gemini_api_key: qcld_gemini_api_key,
                    gemini_model: qcld_gemini_model,
                    gemini_enabled: gemini_enabled,
                    qcld_gemini_page_suggestion_enabled: qcld_gemini_page_suggestion_enabled,
                    gemini_is_context_awareness_enabled: gemini_is_context_awareness_enabled,
                    qcld_gemini_append_content: qcld_gemini_append_content,
                    qcld_gemini_prepend_content: qcld_gemini_prepend_content,
                    openai_post_type:post_gemini_types
                },
                success: function(data){
                    $('#result').html(data);
                    Swal.fire({
                        title: 'Your settings are saved.',
                        html: '<p style=font-size:14px>Please clear your browser <b>cache</b> and <b>cookies</b> both and reload the front end before testing. Alternatively, you can launch a new browser window in <b>Incognito</b>/Private mode (Ctrl+Shift+N in chrome) to test.</p><p></b></p>',
                        width: 450,
                        icon: 'success',
                        confirmButtonText: 'Got it',
                        confirmButtonWidth: 100,
                        confirmButtonClass: 'btn btn-lg',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: ajax_object.ajax_url,
                                type: 'POST',
                                data: {
                                    action: 'update_settings_option',
                                    nonce: ajax_object.ajax_nonce,
                                    disable_ss: 1
                                },
                      
                            });
                        }
                    });
                }
            });
        });
    }
}); 