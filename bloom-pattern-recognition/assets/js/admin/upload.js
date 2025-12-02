(function($) {
    'use strict';

    const BloomUpload = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#tensor-url-form').on('submit', this.handleUrlUpload);
            $('#tensor-file-form').on('submit', this.handleFileUpload);
            $('#tensor-path-form').on('submit', this.handleLocalPathUpload);
        },

        showProcessingStatus: function(message) {
            $('#processing-progress').html('<p>' + message + '</p>');
            $('#status-messages').empty();
        },

        addStatusMessage: function(message, type = 'info') {
            $('#status-messages').append('<p class="' + type + '">' + message + '</p>');
        },

        handleUrlUpload: function(e) {
            e.preventDefault();
            const url = $('#tensor_url').val();
            BloomUpload.showProcessingStatus('Processing URL...');

            $.ajax({
                url: bloomAdmin.restUrl + '/upload/url',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', bloomAdmin.nonce);
                },
                data: {
                    url: url
                },
                success: function(response) {
                    BloomUpload.addStatusMessage(response.message, 'success');
                    BloomUpload.addStatusMessage('Job ID: ' + response.job_id, 'info');
                },
                error: function(xhr, status, error) {
                    const errorMessage = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error.';
                    BloomUpload.addStatusMessage('Error: ' + errorMessage, 'error');
                    console.error('URL upload error:', error, xhr);
                }
            });
        },

        handleFileUpload: function(e) {
            e.preventDefault();
            const fileInput = $('#tensor_file')[0];
            const file = fileInput.files[0];

            if (!file) {
                BloomUpload.addStatusMessage('Please select a file to upload.', 'error');
                return;
            }

            BloomUpload.showProcessingStatus('Uploading file...');

            const formData = new FormData();
            formData.append('file', file);

            $.ajax({
                url: bloomAdmin.restUrl + '/upload/file',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', bloomAdmin.nonce);
                },
                processData: false,
                contentType: false,
                data: formData,
                success: function(response) {
                    BloomUpload.addStatusMessage(response.message, 'success');
                    BloomUpload.addStatusMessage('Job ID: ' + response.job_id, 'info');
                },
                error: function(xhr, status, error) {
                    const errorMessage = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error.';
                    BloomUpload.addStatusMessage('Error: ' + errorMessage, 'error');
                    console.error('File upload error:', error, xhr);
                }
            });
        },

        handleLocalPathUpload: function(e) {
            e.preventDefault();
            const path = $('#tensor_path').val();
            BloomUpload.showProcessingStatus('Processing local path...');

            $.ajax({
                url: bloomAdmin.restUrl + '/upload/local-path',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', bloomAdmin.nonce);
                },
                data: {
                    path: path
                },
                success: function(response) {
                    BloomUpload.addStatusMessage(response.message, 'success');
                    BloomUpload.addStatusMessage('Job ID: ' + response.job_id, 'info');
                },
                error: function(xhr, status, error) {
                    const errorMessage = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unknown error.';
                    BloomUpload.addStatusMessage('Error: ' + errorMessage, 'error');
                    console.error('Local path upload error:', error, xhr);
                }
            });
        }
    };

    $(document).ready(function() {
        BloomUpload.init();
    });

})(jQuery);