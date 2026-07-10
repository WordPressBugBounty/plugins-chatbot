jQuery(document).ready(function(){
	'use strict'
	//start new-update-for-codecanyon
	jQuery('#qcld_chatsessions_enter_license_or_purchase_key').on('focusout', function(){
		qc_chatsessions_set_plugin_license_fields();
	});

	jQuery('#qcld_chatsessions_enter_license_or_purchase_key').on('keypress',function (e) {
		  qc_chatsessions_set_plugin_license_fields();
	});

	jQuery('#qc-license-form input[type="submit"]').on('click', function(){
		qc_chatsessions_set_plugin_license_fields();
		jQuery('#qc-license-form').removeAttr('onsubmit').submit();
	});

	function qc_chatsessions_set_plugin_license_fields(){
		var license_input = jQuery('#qcld_chatsessions_enter_license_or_purchase_key').val();
		if( /^(\w{8})-((\w{4})-){3}(\w{12})$/.test(license_input) ){
			jQuery('input[name="qcld_chatsessions_buy_from_where"]').val('codecanyon');
			jQuery('input[name="qcld_chatsessions_enter_envato_key"]').val(license_input);
		}else{
			jQuery('input[name="qcld_chatsessions_buy_from_where"]').val('quantumcloud');
			jQuery('input[name="qcld_chatsessions_enter_license_key"]').val(license_input);
		}
	}
	function htmlspecialchars_decode(text) {
		const parser = new DOMParser();
		const doc = parser.parseFromString(text, 'text/html');
		return doc.documentElement.textContent;
	}
	var session_hover_state = '';
	const modal = document.getElementById("session_details_modal");
	jQuery('.session_detail_hover').hover(function(e) {
		var self = this;
		var hoverTimer = '';
		const modal = document.getElementById("session_details_modal");
		
		window.onclick = function(event) {
			if (event.target == modal) {
				modal.style.display = "none";
			}
		}
		if(session_hover_state == ''){
			session_hover_state = 1;
			modal.style.display = "none";
			jQuery('.details_modal_body').html('');
			modal.style.left = (jQuery('.session_detail_hover').width() * 2)+'px';
			setTimeout(function() {
				modal.style.display = "block";
				jQuery('.loader-mask').show();
				jQuery('.loader').show();
				jQuery.ajax({
					url: ajax_object.ajax_url,
					type: 'POST',
					dataType: "JSON",
					data:  {
						action : 'wpbot_session_hover_details',
						session_id: jQuery(self).attr('data-id'),
					},
					success: function (response) {
						jQuery('.loader').fadeOut();
            			jQuery('.loader-mask').delay(350).fadeOut('slow');
						let htmlString = response.conversation;
						const doc = htmlspecialchars_decode(htmlString);
						jQuery('.details_modal_body').html(doc)
						session_hover_state = '';
					//	location.reload();
					},  
				});
			}, 100);
		}
	});
	jQuery('#chatsession-table').on('click', '.show_details_click', function(e) {
		var self = this;
		const modal = document.getElementById("session_details_modal");
		
		window.onclick = function(event) {
			if (event.target == modal) {
				modal.style.display = "none";
			}
		}
		if(session_hover_state == ''){
			session_hover_state = 1;
			modal.style.display = "none";
			jQuery('.details_modal_body').html('');
			modal.style.left = (jQuery('.session_detail_hover').width() * 2)+'px';
			setTimeout(function() {
				modal.style.display = "block";
				jQuery('.loader-mask').show();
				jQuery('.loader').show();
				jQuery.ajax({
					url: ajax_object.ajax_url,
					type: 'POST',
					dataType: "JSON",
					data:  {
						action : 'wpbot_session_hover_details',
						session_id: jQuery(self).attr('data-id'),
					},
					success: function (response) {
						jQuery('.loader').fadeOut();
            			jQuery('.loader-mask').delay(350).fadeOut('slow');
						let htmlString = response.conversation;
						const doc = htmlspecialchars_decode(htmlString);
						jQuery('.details_modal_body').html(doc)
						session_hover_state = '';
					//	location.reload();
					},  
				});
			}, 100);
		}
	});
	jQuery('#session_details_modal').on('click','.details_session_close',function(){
		const modal = document.getElementById("session_details_modal");
		modal.style.display = "none";
	})
	jQuery('#wpchatbot-sessioncorn-settings').on('click','#save_wpsseion_corn_setting',function(){
		Swal.showLoading();
		if (jQuery('#is_ai_enabled').is(":checked")){
			var is_ai_enabled = 1;
		}else{
			var is_ai_enabled = 0;
		}
		var corn_schedule_interval = jQuery("[id*='corn_schedule_interval'] :selected").val();
		var qcld_wbsession_corn_starttime = jQuery('#qcld_wbsession_corn_starttime').val();
		var qcld_wpsession_corn_promt = jQuery('#wpsession_corn_promt').val();
		jQuery.ajax({
			url: ajax_object.ajax_url,
			type: 'POST',
			dataType: "JSON",
			data:  {
				action : 'wpbot_seesion_corn_save',
				ai_enabled: is_ai_enabled,
				corn_schedule_interval: corn_schedule_interval,
				qcld_wbsession_corn_starttime: qcld_wbsession_corn_starttime,
				qcld_wpsession_corn_promt: qcld_wpsession_corn_promt,
			},
			success: function (response) {
				var cleanResponse = response.response ? response.response.replace(/<br\s*\/?>/gi, '\n').replace(/<\/?[^>]+(>|$)/g, "") : "";
				var cleanMsg = response.msg ? response.msg.replace(/<br\s*\/?>/gi, '\n').replace(/<\/?[^>]+(>|$)/g, "") : "";
				Swal.fire({
					title: cleanMsg,
					text: cleanResponse,
					icon: response.icon,
					width: 450,
					confirmButtonText: 'Got it',
					confirmButtonWidth: 100,
					confirmButtonClass: 'btn btn-lg'     
				}).then(() => {
					// location.reload();
				})
			
			},  
		});
	})
	jQuery('#wpchatbot-sessioncorn-mannual').on('click','#swpsseion_mannual_scraper_corn',function(){
		Swal.showLoading();
		var wpchatbot_sessioncorn_mannual_number = jQuery('#wpchatbot-sessioncorn-mannual-number').val();
		jQuery.ajax({
			url: ajax_object.ajax_url,
			type: 'POST',
			dataType: "JSON",
			data:  {
				action : 'qcld_chatbot_session_mannual_scraper',
				wpchatbot_session_mannual_number : wpchatbot_sessioncorn_mannual_number,
			},
			success: function (response) {
				var cleanResponse = response.response ? response.response.replace(/<br\s*\/?>/gi, '\n').replace(/<\/?[^>]+(>|$)/g, "") : "";
				var cleanMsg = response.msg ? response.msg.replace(/<br\s*\/?>/gi, '\n').replace(/<\/?[^>]+(>|$)/g, "") : "";
				Swal.fire({
					title: cleanMsg,
					text: cleanResponse,
					icon: response.icon,
					width: 450,
					confirmButtonText: 'Got it',
					confirmButtonWidth: 100,
					confirmButtonClass: 'btn btn-lg'     
				}).then(() => {
					// location.reload();
				})
			},  
		});
	})
	
	//end new-update-for-codecanyon
	jQuery('#wpcs_form_sessions').on('change', '#is_enabled_session_email_notice', function() {
		if(this.checked) {
			jQuery.ajax({
                url: ajax_object.ajax_url,
                type: 'POST',
                dataType: "JSON",
                data:  {
                    action : 'session_email_notification_update',
                    email_notification: 'checked',
                },
                success: function (response) {
                   location.reload();
                },  
            });
		}else{
			jQuery.ajax({
                url: ajax_object.ajax_url,
                type: 'POST',
                dataType: "JSON",
                data:  {
                    action : 'session_email_notification_update',
                    email_notification: '',
                },
                success: function (response) {
                   location.reload();
                },  
            });
		}
	});

	jQuery('#chatsession-table').on('click', '.forward_session', function () {
		setTimeout(function() {
				var detailselement = document.getElementById("wp-chatbot-messages-container");
				detailselement.remove();
		}, 500);
		const forwardModal = document.getElementById("session_foward_modal");
		if (forwardModal) {
			forwardModal.style.display = "block";
			jQuery('#details_session_id').val(jQuery(this).attr('data-id'));
		}
		
	});
	jQuery('#session_foward_modal').on('click', '.forward_session_close', function () {
		const forwardModal = document.getElementById("session_foward_modal");
		if (forwardModal) {
			forwardModal.style.display = "none";
		}
	});

	jQuery('#session_foward_modal').on('click', '#qcld_details_forward_submit', function () {
			var session_id = jQuery('#details_session_id').val();
			jQuery.ajax({
			url: ajax_object.ajax_url,
			type: 'POST',
			dataType: "JSON",
			data:  {
				action : 'forward_session_to_email',
				session_id: session_id,
				email: jQuery('#details_session_email').val(),
				subject: jQuery('#details_session_subject').val(),
			},
			success: function (response) {
				jQuery('#session_foward_modal').hide()
			}
        });
	});
	jQuery('.wp-chatbot-messages-wrapper').on('click', '.forward_session', function () {
		
			var session_id = jQuery('#details_session_id').val();
			jQuery.ajax({
			url: ajax_object.ajax_url,
			type: 'POST',
			dataType: "JSON",
			data:  {
				action : 'forward_session_to_email',
				session_id: session_id,
				email: jQuery('#details_session_email').val(),
				subject: jQuery('#details_session_subject').val(),
			},
			success: function (response) {
				if(response.success){
					Swal.fire({
						title: 'Success',
						text: response.message,
						icon: 'success',
						confirmButtonText: 'OK'
					});
				} else {
					Swal.fire({
						title: 'Error',
						text: response.message,
						icon: 'error',
						confirmButtonText: 'OK'
					});
				}
			}
        });
	});
	
});