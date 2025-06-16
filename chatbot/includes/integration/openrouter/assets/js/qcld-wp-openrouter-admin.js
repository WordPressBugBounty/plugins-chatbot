jQuery(document).ready(function($) {
    // Function to fetch models from OpenRouter API
    function fetchOpenRouterModels() {
        var modelSelect = $('#qcld_openrouter_model');
        modelSelect.html('<option value="">Loading models...</option>');

        $.ajax({
            url: 'https://openrouter.ai/api/v1/models',
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + $('#qcld_openrouter_api_key').val()
            },
            success: function(response) {
                modelSelect.empty();
                
                if (response.data && response.data.length > 0) {
                    response.data.forEach(function(model) {
                        var option = $('<option></option>')
                            .val(model.id)
                            .text(model.name + ' (' + model.id + ')');
                        
                        // Set selected if this is the current model
                        if (model.id === modelSelect.data('current-model')) {
                            option.prop('selected', true);
                        }
                        
                        modelSelect.append(option);
                    });
                } else {
                    modelSelect.append($('<option></option>')
                        .val('')
                        .text('No models available'));
                }
            },
            error: function(xhr) {
                modelSelect.empty();
                modelSelect.append($('<option></option>')
                    .val('')
                    .text('Error loading models'));
                
                if (xhr.status === 401) {
                    alert('Invalid API key. Please check your OpenRouter API key.');
                }
            }
        });
    }

    // Fetch models when page loads
    fetchOpenRouterModels();

    // Refresh models when API key changes
    $('#qcld_openrouter_api_key').on('change', function() {
        fetchOpenRouterModels();
    });

    var settingsOpenrouter = document.getElementById("qcld_save_openrouter_setting");
    if(settingsOpenrouter){
        $('.qcl-openai').on('click', '#qcld_save_openrouter_setting', function(){
            if ($('#qcld_openrouter_enabled').is(":checked")){
                var openrouter_enabled = 1;
            }else{
                var openrouter_enabled = 0;
            }
            var qcld_openrouter_page_suggestion_enabled = jQuery("#qcld_openrouter_page_suggestion_enabled").is(":checked") ? 1 : 0;
            var qcld_openrouter_api_key = jQuery("#qcld_openrouter_api_key").val();
            var qcld_openrouter_model = jQuery('#qcld_openrouter_model').val();
            var qcld_openrouter_append_content = jQuery('#qcld_openrouter_append_content').val();
            var qcld_openrouter_prepend_content = jQuery('#qcld_openrouter_prepend_content').val();
            var post_openrouter_types = $.map($('input[name="site_openrouter_search_posttypes[]"]:checked'), function(c){return c.value; });
            $.ajax({
                url: ajax_object.ajax_url,
                type:'POST',
                data: {
                    action: 'qcld_openrouter_settings_option',
                    nonce: ajax_object.ajax_nonce,
                    openrouter_api_key: qcld_openrouter_api_key,
                    openrouter_model: qcld_openrouter_model,
                    openrouter_enabled: openrouter_enabled,
                    qcld_openrouter_page_suggestion_enabled: qcld_openrouter_page_suggestion_enabled,
                    qcld_openrouter_append_content: qcld_openrouter_append_content,
                    qcld_openrouter_prepend_content: qcld_openrouter_prepend_content,
                    openai_post_type:post_openrouter_types
                },
                success: function(data){
                    $('#result').html(data);
                    Swal.fire({
                        title: 'Your settings are saved.',
                        html: '<p style=font-size:14px>Please clear your browser <b>cache</b> and <b>cookies</b> both and reload the front end before testing. Alternatively, you can launch a new browser window in <b>Incognito</b>/Private mode (Ctrl+Shift+N in chrome) to test.</p><p><b>Do you want to disable Site Search so all responses come from the AI service?</b></p>',
                        width: 450,
                        icon: 'success',
                        confirmButtonText: 'Yes',
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