jQuery(document).ready(function($) {
    if (typeof ajax_object !== 'undefined' && ajax_object.ajax_url) {
        ajax_object.ajax_url = ajax_object.ajax_url.replace(/^https?:\/\/[^\/]+/, window.location.origin);
    }
    // Function to fetch models from OpenRouter API
    function fetchOpenRouterModels() {
        var modelSelect = $('#qcld_mistral_model');
        modelSelect.html('<option value="">Loading models...</option>');

        // Fetched by the server: the saved key never reaches the browser (the field shows a mask).
        $.ajax({
            url: ajax_object.ajax_url,
            method: 'POST',
            data: {
                action: 'qcld_wpbot_provider_models',
                nonce: ajax_object.ajax_nonce,
                provider: 'mistral',
                api_key: $('#qcld_mistral_api_key').val()
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
                    console.log('Invalid API key. Please check your Mistral AI API key.');
                }
            }
        });
    }

    // Fetch models when page loads


    var settingsOpenrouter = document.getElementById("qcld_save_mistral_setting");
    if(settingsOpenrouter){
        $('.qcl-openai').on('click', '#qcld_save_mistral_setting', function(){
            if ($('#qcld_mistral_enabled').is(":checked")){
                var mistral_enabled = 1;
            }else{
                var mistral_enabled = 0;
            }
            var qcld_mistral_page_suggestion_enabled = jQuery("#qcld_mistral_page_suggestion_enabled").is(":checked") ? 1 : 0;
            var qcld_mistral_rag_enabled = jQuery("#qcld_mistral_rag_enabled").is(":checked") ? 1 : 0;
            var qcld_mistral_rag_link_enabled = jQuery("#qcld_mistral_rag_link_enabled").is(":checked") ? 1 : 0;
            var qcld_mistral_api_key = jQuery("#qcld_mistral_api_key").val();
            var qcld_mistral_model = jQuery('#qcld_mistral_model').val();
            var qcld_mistral_append_content = jQuery('#qcld_mistral_append_content').val();
            var qcld_mistral_prepend_content = jQuery('#qcld_mistral_prepend_content').val();
            var post_mistral_types = $.map($('input[name="site_mistral_search_posttypes[]"]:checked'), function(c){return c.value; });
            $.ajax({
                url: ajax_object.ajax_url,
                type:'POST',
                data: {
                    action: 'qcld_mistral_settings_option',
                    nonce: ajax_object.ajax_nonce,
                    mistral_api_key: qcld_mistral_api_key,
                    mistral_model: qcld_mistral_model,
                    mistral_enabled: mistral_enabled,
                    qcld_mistral_page_suggestion_enabled: qcld_mistral_page_suggestion_enabled,
                    qcld_mistral_rag_enabled: qcld_mistral_rag_enabled,
                    qcld_mistral_rag_link_enabled: qcld_mistral_rag_link_enabled,
                    qcld_mistral_append_content: qcld_mistral_append_content,
                    qcld_mistral_prepend_content: qcld_mistral_prepend_content,
                    openai_post_type:post_mistral_types
                },
                success: function(data){
                    $('#result').html(data);
                    Swal.fire({
                        title: 'Your settings are saved.',
                        text: 'Please clear your browser cache and cookies both and reload the front end before testing. Alternatively, you can launch a new browser window in Incognito/Private mode (Ctrl+Shift+N in chrome) to test.',
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

    if ($('#qcld_mistral_enabled').is(":checked")){
        fetchOpenRouterModels();
    }
    

    // Refresh models when API key changes
    $('#qcld_mistral_api_key').on('change', function() {
        fetchOpenRouterModels();
    });


    var flieElementmistral = document.getElementById("mistfileinput");

    if(flieElementmistral){
        
        $('.error-message').hide();
        $('.success-message').hide();
        $(".file_form").on('change','#mistfileinput',function(){
            var file_data = $('#mistfileinput').prop('files')[0];
            console.log(file_data)
            var form_data = new FormData();
            form_data.append('file', file_data);
            form_data.append('action', 'upload_mistral_training_file');
            form_data.append('purpose','answers');
            form_data.append('nonce',ajax_object.ajax_nonce);
            $('.success-message').html('File is Processing');
            $('.success-message').show();
            $.ajax({
                url: ajax_object.ajax_url,
                type: 'post',
                contentType: false,
                processData: false,
                data: form_data,
                success: function (response) {
                    if(response[0].status == "success"){
                       // $('.success').html(response.message)
                        $('.success-message').hide();
                        $('.success-message').html(response[0].message);
                        $('.success-message').show();
                        setTimeout(function(){
                            $('.success-message').hide();
                        },5000); 
                        location.reload();
                    }
                    if(response[0].status === "error"){
                        //$('.success-message').html(response.message)
                        jQuery('.success-message').hide();
                        jQuery('.error-message').show();
                        jQuery('.error-message').html(response[0].message);
                        setTimeout(function(){
                            jQuery('.error-message').hide();
                        },5000); 
                       location.reload();
                    }
                },  
            });
            
        });
    }

    var flieElementmist_gpt = document.getElementById("openfileinput_mistral");

    if(flieElementmist_gpt){
        $(".file_form_mistral").on('change','#openfileinput_mistral',function(){
            
            var file_data = $('#openfileinput_mistral').prop('files')[0];
            var form_data = new FormData();
            form_data.append('file', file_data);
            form_data.append('action', 'upload_mistral_training_file');
            form_data.append('nonce',ajax_object.ajax_nonce);
            form_data.append('purpose','fine-tune');
            $('.success-message').html('File is Processing');
            $('.success-message').show();
            $.ajax({
                url: ajax_object.ajax_url,
                type: 'post',
                contentType: false,
                processData: false,
                data: form_data,
                success: function (response) {
                    if(response[0].status == "success"){
                    // $('.success').html(response.message)
                        $('.success-message').hide();
                        $('.success-message').html(response[0].message);
                        $('.success-message').show();
                        setTimeout(function(){
                            $('.success-message').hide();
                        },5000); 
                        location.reload();
                    }
                    if(response[0].status === "error"){
                        //$('.success-message').html(response.message)
                        jQuery('.success-message').hide();
                        jQuery('.error-message').show();
                        jQuery('.error-message').html(response[0].message);
                        setTimeout(function(){
                            jQuery('.error-message').hide();
                        },5000); 
                       location.reload();
                    }
                },  
            });
            
        });
    }
    $('#checking-tuning').on('click', function(){
        $('#mistral-files-list').html('<em>Loading...</em>');
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'create_fine_tuning_job',
                nonce: ajax_object.ajax_nonce
            },
            success: function(response) {
                if(response.success && response.data && response.data.data) {
                    var html = '<table class=\"wp-list-table widefat\"><thead><tr><th>File ID</th><th>Filename</th><th>Status</th><th>Purpose</th></tr></thead><tbody>';
                    response.data.data.forEach(function(file){
                        html += '<tr><td>' + file.id + '</td><td>' + file.filename + '</td><td>' + file.status + '</td><td>' + file.purpose + '</td></tr>';
                    });
                    html += '</tbody></table>';
                    $('#mistral-files-list').html(html);
                } else {
                    $('#mistral-files-list').html('<div class=\"notice notice-error\">No files found or error fetching files.</div>');
                }
            },
            error: function() {
                $('#mistral-files-list').html('<div class=\"notice notice-error\">AJAX error.</div>');
            }
        });
    });
    $('#mistral_training_file_select, #mistral_validation_file_select').on('focus', function() {
        var select = $(this);
        var default_text = 'Select File';
        if(select.attr('id') == 'mistral_training_file_select'){
            default_text = 'Select Training File';
        }else if(select.attr('id') == 'mistral_validation_file_select'){
            default_text = 'Select Validation File';
        }

        // Prevent multiple loads
        if (select.data('loaded')) {
            return;
        }
        
        select.html('<option value="">Loading files...</option>');

        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'mistral_file_list',
                nonce: ajax_object.ajax_nonce,
            },
            success: function(res) {
                select.html('<option value="">' + default_text + '</option>');
                if (res.data && Array.isArray(res.data)) {
                    res.data.forEach(function(item) {
                        select.append($('<option>', {
                            value: item.id,
                            text: item.filename + ' (' + item.id + ')'
                        }));
                    });
                    select.data('loaded', true);
                } else {
                    select.html('<option value="">Could not load files</option>');
                }
            },
            error: function() {
                select.html('<option value="">Error loading files</option>');
            }
        });
    });
    // $.ajax({
    //     url: ajax_object.ajax_url,					
    //     type: "POST",
    //     dataType: "JSON",
    //     data: {
    //         action : 'mistral_file_list',
    //         nonce:ajax_object.ajax_nonce,
    //     },
    //     success: function(res) {
    //         if((res.data !== undefined)){
    //             var data = res.data;
    //             var text = "";
    //             data.forEach(itemfunction);
    //             function itemfunction(item) {
    //                 text += '<tr><td>'+item.filename +'</td><td>' +item.id+'</td><td><a class="btn-success btn floated create_ft_model_modal" data-id="'+item.id+'" class="btn-success btn floated download_ft_file">Download</a> <a class="btn-danger btn floated remove_file_openai" data-id="'+item.id+'">Remove</a></td></tr>';
    //             }
    //             document.getElementById("mistralFileList").innerHTML = text;
    //         }
    //     }
    // });
    // $.ajax({
    //     url: ajax_object.ajax_url,					
    //     type: "POST",
    //     dataType: "JSON",
    //     data: {
    //         action : 'mistral_job_list',
    //         nonce:ajax_object.ajax_nonce,
    //     },
    //     success: function(res) {
    //         if((res.data !== undefined)){
    //             var data = res.data;
    //             var text = "";
    //             data.forEach(getJObfunction);
    //             function getJObfunction(item) {
    //                 text += '<tr><td>'+item.status +'</td><td>' +item.id+'</td><td><a class="btn-success btn floated create_ft_model_modal" data-id="'+item.id+'" class="btn-success btn floated download_ft_file">Download</a> <a class="btn-danger btn floated remove_file_openai" data-id="'+item.id+'">Remove</a></td></tr>';
    //             }
    //             document.getElementById("mistralJobList").innerHTML = text;
    //         }
    //     }
    // });

    // $.ajax({
    //     url: ajax_object.ajax_url,					
    //     type: "POST",
    //     dataType: "JSON",
    //     data: {
    //         action : 'mistral_model_list',
    //         nonce:ajax_object.ajax_nonce,
    //     },
    //     success: function(res) {
    //         if((res.data !== undefined)){
    //             var data = res.data;
    //             var text = "";
    //             data.forEach(getJObfunction);
    //             function getJObfunction(item) {
    //                 text += '<tr><td>'+item.status +'</td><td>' +item.id+'</td><td><a class="btn-success btn floated create_ft_model_modal" data-id="'+item.id+'" class="btn-success btn floated download_ft_file">Download</a> <a class="btn-danger btn floated remove_file_openai" data-id="'+item.id+'">Remove</a></td></tr>';
    //             }
    //             document.getElementById("mistralModelList").innerHTML = text;
    //         }
    //     }
    // });

    // Function to populate file dropdowns
    function populateFileSelects() {
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'mistral_file_list',
                nonce: ajax_object.ajax_nonce
            },
            success: function (response) {
                if (response && response.data) {
                    var trainingSelect = $('#mistral_training_file_select');
                    var validationSelect = $('#mistral_validation_file_select');

                    trainingSelect.empty().append('<option value="">Select Training File</option>');
                    validationSelect.empty().append('<option value="">Select Validation File</option>');

                    response.data.forEach(function (file) {
                        if (file.purpose === 'fine-tune') {
                            var option = '<option value="' + file.id + '">' + file.filename + ' (' + file.id + ')</option>';
                            trainingSelect.append(option);
                            validationSelect.append(option);
                        }
                    });
                }
            },
            error: function () {
                alert('Failed to load Mistral files.');
            }
        });
    }

    // Populate the dropdowns on page load
    if ($('#mistral_training_file_select').length) {
        populateFileSelects();
    }

    // Handle the click event for creating a fine-tuning job
    $('#checking-tuning').on('click', function () {
        var trainingFileId = $('#mistral_training_file_select').val();
        var validationFileId = $('#mistral_validation_file_select').val();

        if (!trainingFileId) {
            alert('Please select a training file.');
            return;
        }

        $(this).prop('disabled', true).text('Creating Job...');

        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'create_fine_tuning_job',
                nonce: ajax_object.ajax_nonce,
                training_file_id: trainingFileId,
                validation_file_id: validationFileId
            },
            success: function (response) {
                if (response.success) {
                    alert('Fine-tuning job created successfully! Job ID: ' + response.data.id);
                    location.reload();
                } else {
                    alert('Error creating job: ' + (response.data.message || 'Unknown error'));
                    console.error('Error response:', response.data);
                }
            },
            error: function (xhr) {
                alert('An unexpected error occurred. Please check the console for details.');
                console.error('AJAX error:', xhr.responseText);
            },
            complete: function () {
                $('#checking-tuning').prop('disabled', false).text('Create Job');
            }
        });
    });
}); 