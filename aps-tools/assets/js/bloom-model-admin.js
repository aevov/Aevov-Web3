// assets/js/bloom-model-admin.js
(function($) {
    'use strict';

    const BloomModelAdmin = {
        init: function() {
            this.bindEvents();
            this.initChunkUpload();
            this.initChunkPreview();
        },

        bindEvents: function() {
            $('#parent_model').on('change', this.handleModelChange);
            $('#chunk_sku').on('change', this.validateSKU);
            $('#chunk_order').on('change', this.validateOrder);
        },

        initChunkUpload: function() {
            const $fileInput = $('#chunk_data');
            const $preview = $('#chunk_preview');

            $fileInput.on('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;

                if (file.size > 100 * 1024 * 1024) { // 100MB limit
                    alert(bloomModelAdmin.i18n.fileTooLarge);
                    $fileInput.val('');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        const json = JSON.parse(e.target.result);
                        BloomModelAdmin.displayChunkPreview(json);
                        
                        // Auto-fill SKU if available
                        if (json.sku && !$('#chunk_sku').val()) {
                            $('#chunk_sku').val(json.sku);
                        }
                        
                        // Update size
                        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                        $('#chunk_size').val(sizeMB);
                        
                    } catch (error) {
                        alert(bloomModelAdmin.i18n.invalidJson);
                        $fileInput.val('');
                        $preview.empty();
                    }
                };

                reader.readAsText(file);
            });
        },

        initChunkPreview: function() {
            const chunkId = $('#post_ID').val();
            if (chunkId) {
                $.get(bloomModelAdmin.ajaxUrl, {
                    action: 'get_chunk_data',
                    nonce: bloomModelAdmin.nonce,
                    chunk_id: chunkId
                }, function(response) {
                    if (response.success && response.data) {
                        BloomModelAdmin.displayChunkPreview(response.data);
                    }
                });
            }
        },

        displayChunkPreview: function(data) {
            const $preview = $('#chunk_preview');
            const preview = {
                sku: data.sku || 'N/A',
                dtype: data.dtype || 'N/A',
                shape: Array.isArray(data.shape) ? data.shape.join(' × ') : 'N/A',
                dataSize: data.data ? this.formatBytes(data.data.length * 0.75) : '0 B' // Base64 decode size estimate
            };

            $preview.html(`
                <div class="chunk-preview-data">
                    <div class="preview-row">
                        <span class="preview-label">SKU:</span>
                        <span class="preview-value">${preview.sku}</span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-label">Data Type:</span>
                        <span class="preview-value">${preview.dtype}</span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-label">Shape:</span>
                        <span class="preview-value">${preview.shape}</span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-label">Data Size:</span>
                        <span class="preview-value">${preview.dataSize}</span>
                    </div>
                </div>
            `);
        },

        handleModelChange: function(e) {
            const modelId = $(this).val();
            if (!modelId) return;

            $.get(bloomModelAdmin.ajaxUrl, {
                action: 'get_next_chunk_order',
                nonce: bloomModelAdmin.nonce,
                model_id: modelId
            }, function(response) {
                if (response.success) {
                    $('#chunk_order').val(response.data.next_order);
                }
            });
        },

        validateSKU: function() {
            const sku = $(this).val();
            if (!sku) return;

            $.get(bloomModelAdmin.ajaxUrl, {
                action: 'validate_chunk_sku',
                nonce: bloomModelAdmin.nonce,
                sku: sku
            }, function(response) {
                if (!response.success) {
                    alert(response.data.message);
                    $('#chunk_sku').val('');
                }
            });
        },

        validateOrder: function() {
            const order = $(this).val();
            const modelId = $('#parent_model').val();
            if (!order || !modelId) return;

            $.get(bloomModelAdmin.ajaxUrl, {
                action: 'validate_chunk_order',
                nonce: bloomModelAdmin.nonce,
                order: order,
                model_id: modelId
            }, function(response) {
                if (!response.success) {
                    alert(response.data.message);
                    $('#chunk_order').val('');
                }
            });
        },

        formatBytes: function(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    };

    $(document).ready(function() {
        BloomModelAdmin.init();
    });

})(jQuery);