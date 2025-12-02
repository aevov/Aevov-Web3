(function($) {
    'use strict';

    var APSToolsSettings = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Bind event handlers for the settings form
            $('#aps-settings-form').on('submit', this.saveSettings);
        },

        saveSettings: function(e) {
            e.preventDefault();

            var formData = $('#aps-settings-form').serialize();

            $.ajax({
                url: apsTools.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'save_aps_settings',
                    nonce: apsTools.nonce,
                    formData: formData
                },
                success: function(response) {
                    if (response.success) {
                        alert('Settings saved successfully!');
                    } else {
                        alert('Error saving settings: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred while saving settings.');
                }
            });
        }
    };

    $(document).ready(function() {
        APSToolsSettings.init();
    });
})(jQuery);