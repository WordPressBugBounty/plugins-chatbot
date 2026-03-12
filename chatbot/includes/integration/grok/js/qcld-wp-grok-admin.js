jQuery(document).ready(function($) {
    // Function to fetch models from OpenRouter API
    var settingsOpenrouter = document.getElementById("qcld_save_grok_setting");
    if(settingsOpenrouter){
        $('.qcl-openai').on('click', '#qcld_save_grok_setting', function(event){
            event.preventDefault();
            if ($('#qcld_grok_enabled').is(":checked")){
                var grok_enabled = 1;
            }else{
                var grok_enabled = 0;
            }
            if ($('#qcld_grok_rag_enabled').is(":checked")){
                var grok_rag_enabled = 1;
            }else{
                var grok_rag_enabled = 0;
            }
            var qcld_grok_page_suggestion_enabled = jQuery("#qcld_grok_page_suggestion_enabled").is(":checked") ? 1 : 0;
            var qcld_grok_api_key = jQuery("#qcld_grok_api_key").val();
            var qcld_grok_model = jQuery('#qcld_grok_model').val();
            var post_grok_types = $.map($('input[name="site_grok_search_posttypes[]"]:checked'), function(c){return c.value; });
            $.ajax({
                url: ajax_object.ajax_url,
                type:'POST',
                data: {
                    action: 'qcld_grok_settings_option',
                    nonce: ajax_object.ajax_nonce,
                    grok_api_key: qcld_grok_api_key,
                    grok_management_api_key: jQuery("#qcld_grok_management_api_key").val(),
                    grok_model: qcld_grok_model,
                    grok_enabled: grok_enabled,
                    grok_collection_id: jQuery("#qcld_grok_collection_id").val(),
                    grok_stream_enabled: jQuery("#qcld_grok_stream_enabled").is(":checked") ? 1 : 0,
                    grok_rag_enabled: grok_rag_enabled,
                    qcld_grok_system_content: jQuery("#qcld_grok_system_content").val(),
                    qcld_grok_page_suggestion_enabled: qcld_grok_page_suggestion_enabled,
                    openai_post_type:post_grok_types
                },
                success: function(data){
                    $('#result').html(data);
                    Swal.fire({
                        title: 'Your settings are saved.',
                        html: '<p style=font-size:14px>Please clear your browser <b>cache</b> and <b>cookies</b> both and reload the front end before testing. Alternatively, you can launch a new browser window in <b>Incognito</b>/Private mode (Ctrl+Shift+N in chrome) to test.</p>',
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
        $('.qcl-openai').on('click', '#add_grok_collection', function(event) {
            event.preventDefault();
            event.stopImmediatePropagation();
            event.stopPropagation();
            $.ajax({
                url: ajax_object.ajax_url,  
                type:'POST',
                data: {
                    action: 'qcld_grok_add_collection', 
                    nonce: ajax_object.ajax_nonce,
                },
                success: function(response){   
                    alert('New collection added successfully.');    
                    loadGrokCollections();
                }
            });
        });
        $('.qcl-openai').on('click', '.remove_grok_collection', function( event ) {
            event.preventDefault();
            event.stopImmediatePropagation();
            event.stopPropagation();
            var collection_id = $(this).data('id');
            if (confirm('Are you sure you want to remove this collection?')) {
                $.ajax({
                    url: ajax_object.ajax_url,  
                    type:'POST',
                    data: {
                        action: 'qcld_grok_remove_collection', 
                        nonce: ajax_object.ajax_nonce,
                        collection_id: collection_id
                    },
                    success: function( response ){
                        console.log( JSON.parse( response ).response );
                        loadGrokCollections();
                    }
                });
            }   
        });
        $.ajax({
            url: ajax_object.ajax_url,  
            type:'POST',
            data: {
                action: 'qcld_grok_collectionlist', 
                nonce: ajax_object.ajax_nonce,
            },
            success: function(response){   
                var data = JSON.parse(response).collections;
                var text = "";
                data.forEach(function (item) {
                    text += '<tr><td>' + item.collection_name + '</td><td>' + item.collection_id + '<form class="file_form_gpt" data-collection="' + item.collection_id + '"><input type="file" class="inputfile" id="grokfileinput_gpt" /><label for="grokfileinput_gpt" class="huge ui grey button"><i class="fa fa-upload"></i>Upload CSV/PDF/JSON</label></form></td><td><a class="btn-danger btn floated remove_grok_collection" data-id="' + item.collection_id + '">Remove</a></td></tr>';
                });
                document.getElementById("grokFileList").innerHTML = text;
            }
        });
        jQuery('.table-responsive').on('change','#grokfileinput_gpt', function() {
            var file_data = $(this).prop('files')[0]; 
            if (!file_data) return; // Exit if no file was selected

            var form_data = new FormData();
            form_data.append('file', file_data);
            form_data.append('name', file_data.name);
            form_data.append('collection_id', $(this).parent().attr('data-collection'));
            form_data.append('action', 'qcld_grok_file_upload');
            form_data.append('purpose', 'assistant');
            form_data.append('nonce', ajax_object.ajax_nonce);
             $.ajax({
                url: ajax_object.ajax_url,
                type: 'POST',
                data: form_data,
                processData: false, // Critical
                contentType: false, // Critical
                success: function(response) {
                    console.log('Upload successful', response);
                }
            });
        })
        function loadGrokCollections() {
            $.ajax({
                url: ajax_object.ajax_url,  
                type:'POST',    
                data: {
                    action: 'qcld_grok_collectionlist', 
                    nonce: ajax_object.ajax_nonce,
                },
                success: function(response){   
                    var data = JSON.parse(response).collections;
                    var text = "";
                    data.forEach(function (item) {
                        text += '<tr><td>' + item.collection_name + '</td><td>' + item.collection_id + '<form class="file_grok_form" data-collection="'+ item.collection_id + '"><input type="file" (change)="fileEvent($event)" class="inputfile" id="grokfileinput" style="display:none"/></form><form class="file_form_gpt" data-collection="' + item.collection_id + '"><input type="file" (change)="fileEvent($event)" class="inputfile" id="grokfileinput_gpt" style="display:none"/><label for="grokfileinput_gpt" class="huge ui grey button"><i class="fa fa-upload"></i>Upload CSV/PDF/JSON</label></form></td><td><a class="btn-danger btn floated remove_grok_collection" data-id="' + item.collection_id + '">Remove</a></td></tr>';
                    });
                    document.getElementById("grokFileList").innerHTML = text;
                }   
            });
        }
    }
}); 