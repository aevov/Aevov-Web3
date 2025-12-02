(function($) {
    'use strict';

    const BloomIntegration = {
        init: function() {
            this.validateJson = window.apsTools?.validateJson ?? true;
            this.updateStatus();
            this.loadSyncHistory();
            this.bindEvents();
            this.startStatusPolling();
            this.initChunkUpload();
            this.loadUploadedChunks();
        },
        
        initChunkUpload: function() {
            $('#chunk-file').on('change', (e) => this.handleFileSelect(e));
            $('#chunk-content').on('input', (e) => this.handleJsonInput(e));
            $('#upload-chunk').on('click', () => this.uploadChunk());
        },

        handleFileSelect: function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    $('#chunk-content').val(e.target.result);
                    this.validateAndPreviewJson(e.target.result);
                };
                reader.readAsText(file);
            }
        },

        handleJsonInput: function(e) {
            this.validateAndPreviewJson(e.target.value);
        },

        validateAndPreviewJson: function(content) {
            if (!this.validateJson) {
                $('.chunk-preview').show();
                $('#chunk-preview-content').text(content);
                $('#upload-chunk').prop('disabled', false);
                return;
            }

            try {
                const jsonData = JSON.parse(content);
                $('.chunk-preview').show();
                $('#chunk-preview-content').text(JSON.stringify(jsonData, null, 2));
                $('#upload-chunk').prop('disabled', false);
            } catch (e) {
                $('.chunk-preview').hide();
                $('#upload-chunk').prop('disabled', true);
            }
        },

        uploadChunk: function() {
            const content = $('#chunk-content').val();
            let jsonData;

            try {
                jsonData = this.validateJson ? JSON.parse(content) : content;
            } catch (e) {
                if (this.validateJson) {
                    alert(apsTools.i18n.invalidJson);
                    return;
                }
                jsonData = content;
            }

            $.ajax({
                url: apsTools.restUrl + '/bloom/upload-chunk',
                method: 'POST',
                data: JSON.stringify(jsonData),
                contentType: 'application/json',
                beforeSend: (xhr) => {
                    xhr.setRequestHeader('X-WP-Nonce', apsTools.nonce);
                }
            })
            .done(response => {
                if (response.success) {
                    this.loadUploadedChunks();
                    $('#chunk-content').val('');
                    $('.chunk-preview').hide();
                }
            })
            .fail(error => {
                alert(error.responseJSON?.message || apsTools.i18n.error);
            });
        },

        loadUploadedChunks: function() {
            $.get(apsTools.restUrl + '/bloom/chunks')
                .done(response => {
                    this.renderChunks(response.data);
                });
        },

        renderChunks: function(chunks) {
            const html = chunks.map(chunk => `
                <div class="chunk-item">
                    <div class="chunk-sku">${chunk.sku}</div>
                    <div class="chunk-meta">
                        <div>Size: ${this.formatBytes(chunk.size)}</div>
                        <div>Uploaded: ${chunk.uploaded_at}</div>
                    </div>
                    <div class="chunk-actions">
                        <button class="button view-chunk" data-sku="${chunk.sku}">View</button>
                        <button class="button remove-chunk" data-sku="${chunk.sku}">Remove</button>
                    </div>
                </div>
            `).join('');

            $('#uploaded-chunks').html(html);
        },

        formatBytes: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        bindEvents: function() {
            $('#test-connection').on('click', () => this.testConnection());
            $('#sync-now').on('click', () => this.syncNow());
            $('#sync-history-list').on('click', '.view-details', (e) => {
                this.viewSyncDetails($(e.target).data('id'));
            });
        },

        updateStatus: function() {
            $.get(apsTools.restUrl + '/bloom/status')
                .done(response => {
                    this.renderStatus(response.data);
                });
        },

        renderStatus: function(status) {
            $('#bloom-status-icon')
                .removeClass()
                .addClass('status-' + status.connection);

            $('#bloom-status-text').text(status.message);
            $('#last-sync-time').text(status.last_sync || 'Never');
            $('#patterns-synced').text(status.patterns_synced || 0);
            $('#sync-success-rate').text((status.success_rate || 0) + '%');
            $('#sync-errors').text(status.errors || 0);

            $('#sync-now').prop('disabled', status.connection !== 'active');
        },

        loadSyncHistory: function() {
            $.get(apsTools.restUrl + '/bloom/sync-history')
                .done(response => {
                    this.renderSyncHistory(response.data);
                });
        },

        renderSyncHistory: function(history) {
            const template = _.template($('#sync-history-row-template').html());
            const html = history.map(item => template(item)).join('');
            $('#sync-history-list').html(html);
        },

        testConnection: function() {
            const $button = $('#test-connection');
            $button.prop('disabled', true)
                   .text('Testing...');

            $.post(apsTools.restUrl + '/bloom/test')
                .done(response => {
                    if (response.success) {
                        this.updateStatus();
                    }
                })
                .fail(error => {
                    alert(error.responseJSON?.message || apsTools.i18n.error);
                })
                .always(() => {
                    $button.prop('disabled', false)
                           .text('Test Connection');
                });
        },

        syncNow: function() {
            const $button = $('#sync-now');
            $button.prop('disabled', true)
                   .text('Syncing...');

            $.post(apsTools.restUrl + '/bloom/sync')
                .done(response => {
                    if (response.success) {
                        this.updateStatus();
                        this.loadSyncHistory();
                    }
                })
                .fail(error => {
                    alert(error.responseJSON?.message || apsTools.i18n.error);
                })
                .always(() => {
                    $button.prop('disabled', false)
                           .text('Sync Now');
                });
        },

        viewSyncDetails: function(id) {
            $.get(apsTools.restUrl + `/bloom/sync-details/${id}`)
                .done(response => {
                    if (response.success) {
                        this.showDetailsModal(response.data);
                    }
                });
        },

        showDetailsModal: function(details) {
            console.log(details);
        },

        startStatusPolling: function() {
            setInterval(() => this.updateStatus(), 30000);
        }
    };

    $(document).ready(() => BloomIntegration.init());

})(jQuery);