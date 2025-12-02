(function($) {
    let $modelSelect = null;
    let $modelInfo = null;
    
    function initMediaUploader() {
        $modelSelect = $('#bloom-model-select');
        $modelInfo = $('#bloom-model-info');
        
        $modelSelect.on('change', handleModelChange);
        
        const uploader = wp.media.frames.file_frame;
        if (uploader) {
            uploader.uploader.uploader.param('bloom_model_id', $modelSelect.val());
        }
    }
    
    function handleModelChange() {
        const modelId = $modelSelect.val();
        if (!modelId) {
            $modelInfo.html('');
            return;
        }
        
        $.post(apsMediaConfig.ajaxUrl, {
            action: 'get_model_info',
            model_id: modelId,
            nonce: apsMediaConfig.nonce
        }, function(response) {
            if (response.success) {
                $modelInfo.html(`
                    <p>
                        <strong>${response.data.title}</strong><br>
                        Chunks: ${response.data.chunks}
                    </p>
                `);
            } else {
                $modelInfo.html(`<p class="error">${response.data}</p>`);
            }
        }).fail(function() {
            $modelInfo.html('<p class="error">An error occurred</p>');
        });
    }
    
    $(document).ready(function() {
        if (typeof wp !== 'undefined' && wp.media && wp.media.view && wp.media.view.UploaderInline) {
            wp.media.view.UploaderInline.prototype.ready = _.wrap(wp.media.view.UploaderInline.prototype.ready, function(originalReady) {
                originalReady.apply(this, arguments);
                initMediaUploader();
            });
        } else {
            initMediaUploader();
        }
    });
})(jQuery);