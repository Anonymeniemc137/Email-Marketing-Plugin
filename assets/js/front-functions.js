/* 
** Frontend - Email marketing form submission javascript + AJAX logic.
*/

jQuery(document).ready(function($) {
    // Handle email marketing form submission
    $('#dg-em-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const overlay = $('#dg-em-overlay');
        const responseEl = form.find('#dg-em-response');

        // Validate consent checkbox
        const consentChecked = form.find('input[name="dg_em_consent"]').is(':checked');
        if (!consentChecked) {
            responseEl.html('<p style="color:red;">You must consent to be contacted and accept our Privacy Policy.</p>');
            return;
        }

        // Show loading overlay
        overlay.show();

        // Submit form data via AJAX
        $.post(dg_em_ajax_obj.ajax_url, {
            action: 'dg_em_submit_form',
            dg_em_first_name: form.find('input[name="dg_em_first_name"]').val(),
            dg_em_email: form.find('input[name="dg_em_email"]').val(),
            dg_em_consent: form.find('input[name="dg_em_consent"]').val(),
            security: form.find('input[name="dg_em_nonce"]').val()
        }, function(response) {
            overlay.hide();
            if (response.success) {
                responseEl.html('<p style="color:green;">' + response.data.message + '</p>');
                form[0].reset();
            } else {
                responseEl.html('<p style="color:red;">' + response.data.message + '</p>');
            }
        }).fail(function() {
            overlay.hide();
            responseEl.html('<p style="color:red;">Request failed. Please try again.</p>');
        });
    });
});