jQuery(document).ready(function($) {
    var data = window.qcld_openrouter_admin_data || {};
    var i18n = data.i18n || {};
    var modelSelect = $('#qcld_openrouter_model');
    if (!modelSelect.length) {
        return;
    }

    // Without a model list, keep the saved model so saving never clears it.
    function showCurrentModel(message) {
        var current = modelSelect.data('current-model');
        modelSelect.empty();
        if (current) {
            modelSelect.append($('<option></option>').val(current).text(current).prop('selected', true));
        }
        modelSelect.append($('<option></option>').val('').text(message));
    }

    // Model list through this site's server (admin-ajax), never from the browser to OpenRouter.
    // Runs only when OpenRouter is turned on with a key, or the owner has just entered a key.
    function fetchOpenRouterModels(apiKey) {
        modelSelect.html($('<option></option>').val('').text(i18n.loading || 'Loading models...'));
        $.post(data.ajax_url, {
            action: data.models_action,
            nonce: data.ajax_nonce,
            api_key: apiKey || ''
        }).done(function(response) {
            var models = response && response.success && response.data ? response.data.models : [];
            if (!models || !models.length) {
                showCurrentModel(i18n.none || 'No models available');
                return;
            }
            modelSelect.empty();
            models.forEach(function(model) {
                var option = $('<option></option>').val(model.id).text(model.name + ' (' + model.id + ')');
                if (model.id === modelSelect.data('current-model')) {
                    option.prop('selected', true);
                }
                modelSelect.append(option);
            });
        }).fail(function(xhr) {
            var body = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
            showCurrentModel(i18n.error || 'Error loading models');
            if (body.bad_key) {
                alert(i18n.bad_key || body.msg);
            }
        });
    }

    var keyField = $('#qcld_openrouter_api_key');
    var savedKey = $.trim(keyField.val() || '');
    if ($('#qcld_openrouter_enabled').is(':checked') && savedKey !== '') {
        fetchOpenRouterModels('');
    } else {
        showCurrentModel(i18n.add_key || 'Turn on OpenRouter and add your API key to load the models');
    }

    // The owner typed a new key: load the models for it.
    keyField.on('change', function() {
        var key = $.trim($(this).val() || '');
        if (key !== '') {
            fetchOpenRouterModels(key === savedKey ? '' : key);
        }
    });

    // The owner turned OpenRouter on with a key in the field.
    $('#qcld_openrouter_enabled').on('change', function() {
        var key = $.trim(keyField.val() || '');
        if ($(this).is(':checked') && key !== '') {
            fetchOpenRouterModels(key === savedKey ? '' : key);
        }
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
            var opnrouter_is_context_awareness_enabled = jQuery("#opnrouter_is_context_awareness_enabled").is(":checked") ? 1 : 0;
            var qcld_openrouter_api_key = jQuery("#qcld_openrouter_api_key").val();
            var qcld_openrouter_model = jQuery('#qcld_openrouter_model').val();
            var qcld_openrouter_append_content = jQuery('#qcld_openrouter_append_content').val();
            var qcld_openrouter_prepend_content = jQuery('#qcld_openrouter_prepend_content').val();
            var is_page_rag_enabled = jQuery("#is_page_rag_enabled_openrouter").is(":checked") ? 1 : 0;
            var post_openrouter_types = $.map($('input[name="site_openrouter_search_posttypes[]"]:checked'), function(c){return c.value; });
            Swal.fire({
                html: '<div class="qcld-save-settings-spinner"></div>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                background: 'transparent',
                customClass: {
                    popup: 'qcld-swal-loading-only'
                }
            });
            $.ajax({
                url: data.ajax_url,
                type:'POST',
                data: {
                    action: 'qcld_openrouter_settings_option',
                    nonce: data.ajax_nonce,
                    openrouter_api_key: qcld_openrouter_api_key,
                    openrouter_model: qcld_openrouter_model,
                    openrouter_enabled: openrouter_enabled,
                    qcld_openrouter_page_suggestion_enabled: qcld_openrouter_page_suggestion_enabled,
                    opnrouter_is_context_awareness_enabled: opnrouter_is_context_awareness_enabled,
                    qcld_openrouter_append_content: qcld_openrouter_append_content,
                    qcld_openrouter_prepend_content: qcld_openrouter_prepend_content,
                    openai_post_type:post_openrouter_types,
                    is_page_rag_enabled: is_page_rag_enabled
                },
                success: function(data){
                    $('#result').html(data);
                    var color = data.status === 'success' ? 'color:green;' : 'color:red;';
                    Swal.fire({
                        title: 'Your settings are saved.',
                        html: '<p style=font-size:14px>Please clear your browser <b>cache</b> and <b>cookies</b> both and reload the front end before testing. Alternatively, you can launch a new browser window in <b>Incognito</b>/Private mode (Ctrl+Shift+N in chrome) to test.</p></b><p><span style="'+ color +'">'+ (data.msg ? data.msg : '') +'</span></p>',
                        width: 450,
                        icon: 'success',
                        confirmButtonText: 'Got it',
                        confirmButtonWidth: 100,
                        confirmButtonClass: 'btn btn-lg',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: data.ajax_url,
                                type: 'POST',
                                data: {
                                    action: 'update_settings_option',
                                    nonce: data.ajax_nonce,
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