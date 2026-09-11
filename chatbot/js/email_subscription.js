jQuery(document).ready(function($) {
    // Check/uncheck all checkboxes
    $('#wpbot_checked_all').on('change', function() {
        $('.wpbot_email_checkbox').prop('checked', $(this).prop('checked'));
    });

    // Delete confirmation and form submission
    $('#wpbot_submit_email_form').on('click', function(e) {
        e.preventDefault();
        
        var checkedLength = $('.wpbot_email_checkbox:checked').length;
        if (checkedLength === 0) {
            alert('Please select at least one record to delete.');
            return false;
        }

        if (confirm('Are you sure you want to delete the selected ' + checkedLength + ' record(s)?')) {
            $('input[name="wpbot_email_subscription_remove"]').val('1');
            $('#wpcs_form_sessions').submit();
        }
    });
});
