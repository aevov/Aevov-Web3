(function ($) {
    'use strict';

    $(function () {
        $('#aevov-generate-text').on('click', function (e) {
            e.preventDefault();

            var $button = $(this);
            var $spinner = $button.siblings('.spinner');
            var $container = $('#aevov-generated-text-container');
            var prompt = $('#aevov-prompt').val();

            if (!prompt) {
                $container.html('<p style="color: red;">Please enter a prompt.</p>');
                return;
            }

            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $container.html('<p>Generating...</p>');

            wp.apiFetch({
                path: '/aevov-language-engine/v1/generate',
                method: 'POST',
                data: { prompt: prompt },
            }).done(function (response) {
                if (response.text) {
                    $container.html('<p>' + response.text.replace(/\n/g, '<br>') + '</p>');
                } else {
                    $container.html('<p style="color: red;">An unknown error occurred.</p>');
                }
            }).fail(function (response) {
                var errorMessage = 'An error occurred.';
                if (response && response.responseJSON && response.responseJSON.message) {
                    errorMessage = response.responseJSON.message;
                }
                $container.html('<p style="color: red;">' + errorMessage + '</p>');
            }).always(function () {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            });
        });
    });

})(jQuery);
