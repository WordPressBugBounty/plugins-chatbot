jQuery(document).ready(function(){
	
	jQuery(document).on( 'click', '.qcbot-feedback .notice-dismiss', function(){
		var notice_type = jQuery(this).parents('.qcbot-feedback.is-dismissible').attr('data-dismiss-type');
		jQuery.ajax({
			url: qcld_rating_object.ajax_url,
			data: {
				action: 'qc_chatbot_feedback_notice_dismiss',
		        dismiss_notice: notice_type
			},
			success: function(){ }
		})
	} )
	
	jQuery(document).on( 'click', '.qcbot-blackfriday .notice-dismiss', function(){
		var notice_type = jQuery(this).parents('.qcbot-blackfriday.is-dismissible').attr('data-dismiss-type');
		jQuery.ajax({
			url: qcld_rating_object.ajax_url,
			data: {
				action: 'qc_chatbot_blackfriday_notice_dismiss',
		        dismiss_notice: notice_type
			},
			success: function(){ }
		})
	} )

	// AI Review Generator
	jQuery(document).on( 'click', '#qc-write-review-ai', function(e){
		e.preventDefault();
		
		var container = jQuery('#qc-ai-review-container');
		var textarea = jQuery('#qc-ai-review-text');
		
		container.slideDown();
		container.addClass('loading');
		textarea.val('');
		
		jQuery.ajax({
			url: qcld_rating_object.ajax_url,
			type: 'POST',
			data: {
				action: 'qc_chatbot_generate_review_ai',
				nonce: qcld_rating_object.nonce
			},
			success: function(response){
				container.removeClass('loading');
				if(response.success && response.data && response.data.review) {
					textarea.val(response.data.review);
				} else {
					textarea.val('Failed to generate review. Please try again.');
				}
			},
			error: function() {
				container.removeClass('loading');
				textarea.val('Failed to connect to the server. Please try again.');
			}
		});
	});

	// Copy to Clipboard
	jQuery(document).on( 'click', '#qc-copy-review-btn', function(e){
		e.preventDefault();
		
		var textarea = document.getElementById('qc-ai-review-text');
		if(!textarea || !textarea.value) {
			return;
		}
		
		textarea.select();
		textarea.setSelectionRange(0, 99999); /* For mobile devices */
		
		navigator.clipboard.writeText(textarea.value).then(function() {
			var btn = jQuery('#qc-copy-review-btn');
			var btnText = btn.find('.qc-btn-text');
			
			btn.addClass('copied');
			btnText.text('Copied!');
			
			setTimeout(function() {
				btn.removeClass('copied');
				btnText.text('Copy to Clipboard');
			}, 2000);
		});
	});
	
});