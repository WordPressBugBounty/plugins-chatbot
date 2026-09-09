jQuery(document).ready(function($) {
    if (typeof ajax_object !== 'undefined' && ajax_object.ajax_url) {
        ajax_object.ajax_url = ajax_object.ajax_url.replace(/^https?:\/\/[^\/]+/, window.location.origin);
    }
    var settingsClaude = document.getElementById("qcld_save_claude_setting");
    if(settingsClaude){
        $('.qcl-openai').on('click', '#qcld_save_claude_setting', function(){
            if ($('#qcld_claude_enabled').is(":checked")){
                var claude_enabled = 1;
            }else{
                var claude_enabled = 0;
            }
            if ($('#qcld_claude_rag_enabled').is(":checked")){
                var claude_rag_enabled = 1;
            }else{
                var claude_rag_enabled = 0;
            }
            var claude_stream_enabled = $('#qcld_claude_stream_enabled').is(":checked") ? 1 : 0;
            var qcld_claude_page_suggestion_enabled = jQuery("#qcld_claude_page_suggestion_enabled").is(":checked") ? 1 : 0;
            var qcld_claude_api_key = jQuery("#qcld_claude_api_key").val();
            var qcld_voyage_api_key = jQuery("#qcld_voyage_api_key").val();
            var qcld_claude_model = jQuery('#qcld_claude_model').val();
            var qcld_claude_system_content = jQuery('#qcld_claude_system_content').val();
            var qcld_claude_append_content = jQuery('#qcld_claude_append_content').val();
            var qcld_claude_prepend_content = jQuery('#qcld_claude_prepend_content').val();
            var post_claude_types = $.map($('input[name="site_claude_search_posttypes[]"]:checked'), function(c){return c.value; });
            $.ajax({
                url: ajax_object.ajax_url,
                type:'POST',
                data: {
                    action: 'qcld_claude_settings_option',
                    nonce: ajax_object.ajax_nonce,
                    claude_api_key: qcld_claude_api_key,
                    voyage_api_key: qcld_voyage_api_key,
                    claude_model: qcld_claude_model,
                    claude_enabled: claude_enabled,
                    claude_rag_enabled: claude_rag_enabled,
                    claude_stream_enabled: claude_stream_enabled,
                    claude_system_content: qcld_claude_system_content,
                    qcld_claude_page_suggestion_enabled: qcld_claude_page_suggestion_enabled,
                    qcld_claude_append_content: qcld_claude_append_content,
                    qcld_claude_prepend_content: qcld_claude_prepend_content,
                    openai_post_type:post_claude_types
                },
                success: function(data){
                    $('#result').html(data);
                    var cleanMsg = data.msg ? data.msg.replace(/<br\s*\/?>/gi, '\n').replace(/<\/?[^>]+(>|$)/g, "") : "";
                    Swal.fire({
                        title: 'Your settings are saved.',
                        text: 'Please clear your browser cache and cookies both and reload the front end before testing.' + (cleanMsg ? '\n\n' + cleanMsg : ''),
                        width: 450,
                        icon: 'success',
                        confirmButtonText: 'Yes',
                        confirmButtonWidth: 100,
                        confirmButtonClass: 'btn btn-lg'     
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
