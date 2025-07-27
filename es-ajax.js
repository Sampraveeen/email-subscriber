jQuery(document).ready(function ($) {
    $('#es-subscribe-form').on('submit', function (e) {
        e.preventDefault();

        var email = $('#es_email').val();

        $.ajax({
            url: es_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'es_ajax_subscribe',
                nonce: es_ajax_obj.nonce,
                email: email
            },
            success: function (response) {
                if (response.success) {
                    $('#es-message').css('color', 'green').text(response.data);
                    $('#es-subscribe-form')[0].reset();
                } else {
                    $('#es-message').css('color', 'red').text(response.data);
                }
            }
        });
    });
});
