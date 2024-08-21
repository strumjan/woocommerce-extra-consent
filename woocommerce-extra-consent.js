jQuery(document).ready(function($) {
    function checkEmailConsent() {
        var email = $('#billing_email').val();
        var consentMessage = $('#consent-checkbox-label').text().trim();

        if (email !== '') {
            $.ajax({
                url: wc_checkout_params.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wec_check_email_consent',
                    email: email,
                    consent_message: consentMessage,
                },
                success: function(response) {
                    if (response.data && response.data.hide_consent_field) {
                        $('#consent-checkbox-field').hide();
                    } else {
                        $('#consent-checkbox-field').show();
                    }
                }
            });
        }
    }

    setTimeout(function() {
        checkEmailConsent();
    }, 1000);

    $('input#billing_email').on('blur', function() {
        checkEmailConsent();
    });

});
