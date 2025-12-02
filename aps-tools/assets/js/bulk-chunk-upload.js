// assets/js/bulk-chunk-upload.js
(function($) {
    'use strict';

    const BulkChunkUpload = {
        files: [],
        fileData: [],
        uploading: false,

        init: function() {
            this.bindEvents();
            this.initDropZone();
        },

        bindEvents: function() {
            $('#chunk-files').on('change', (e) => this.handleFileSelect(e));
            $('#parent-model').on('change', () => this.validateForm());
            $('#start-upload').on('click', () => this.startUpload());
            $('#clear-files').on('click', () => this.clearFiles());
            
            $(document).on('click', '.remove-file', (e) => {
                const index = $(e.target).closest('.file-preview').data('index');
                this.removeFile(index);
            });
        },

        initDropZone: function() {
            const dropZone = $('#drop-zone');
            
            dropZone.on('dragover dragenter', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.addClass('drag-over');
            });

            dropZone.on('dragleave dragend drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.removeClass('drag-over');
            });

            dropZone.on('drop', (e) => {
                e.preventDefault();
                const files = e.originalEvent.dataTransfer.files;
                this.processFiles(files);
            });
        },

        handleFileSelect: function(e) {
            const files = e.target.files;
            this.processFiles(files);
        },

        processFiles: function(fileList) {
            const newFiles = Array.from(fileList).filter(file => 
                file.type === 'application/json' || file.name.endsWith('.json')
            );

            newFiles.forEach(file => {
                const reader = new FileReader();
                reader.onload = (e) => this.parseChunkFile(file, e.target.result);
                reader.readAsText(file);
            });
        },

        parseChunkFile: function(file, content) {
            try {
                const json = JSON.parse(content);
                const tensorName = Object.keys(json)[0];
                const tensorData = json[tensorName];

                if (this.validateChunkData(tensorData)) {
                    const fileIndex = this.files.length;
                    this.files.push(file);
                    this.fileData.push({
                        tensorName,
                        ...tensorData,
                        fileName: file.name
                    });
                    this.renderFilePreview(fileIndex);
                    this.validateForm();
                }
            } catch (e) {
                //console.error('Error parsing JSON:', e);
                //alert(`Error parsing ${file.name}: Invalid JSON format`);
            }
        },

        validateChunkData: function(data) {
            return data && data.sku && data.dtype && data.shape && data.data;
        },

        renderFilePreview: function(index) {
            const template = _.template($('#file-preview-template').html());
            const html = template({
                index,
                ...this.fileData[index]
            });
            
            $('#file-preview-list').append(html);
        },

        removeFile: function(index) {
            this.files.splice(index, 1);
            this.fileData.splice(index, 1);
            this.updateFilePreviews();
            this.validateForm();
        },

        updateFilePreviews: function() {
            $('#file-preview-list').empty();
            this.fileData.forEach((_, index) => this.renderFilePreview(index));
        },

        validateForm: function() {
            const valid = this.files.length > 0 && $('#parent-model').val();
            $('#start-upload').prop('disabled', !valid);
            $('#clear-files').prop('disabled', this.files.length === 0);
        },

        startUpload: function() {
            if (this.uploading) return;
            
            this.uploading = true;
            const modelId = $('#parent-model').val();
            
            $('#start-upload').prop('disabled', true);
            $('#clear-files').prop('disabled', true);
            
            this.files.forEach((file, index) => {
                this.uploadChunk(file, this.fileData[index], modelId, index);
            });
        },

        uploadChunk: function(file, fileData, modelId, index) {
            const formData = new FormData();
            formData.append('action', 'upload_bloom_chunk');
            formData.append('nonce', bloomChunkAdmin.nonce);
            formData.append('model_id', modelId);
            formData.append('tensor_name', fileData.tensorName);
            formData.append('chunk_file', file);
            formData.append('chunk_data', JSON.stringify(fileData));

            $.ajax({
                url: bloomChunkAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: () => {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            const percent = (e.loaded / e.total) * 100;
                            this.updateProgress(index, percent);
                        }
                    });
                    return xhr;
                }
            })
            .done((response) => {
                if (response.success) {
                    this.updateProgress(index, 100, 'complete');
                } else {
                    this.updateProgress(index, 100, 'error');
                    console.error('Upload failed:', response.data);
                }
            })
            .fail((xhr, status, error) => {
                this.updateProgress(index, 100, 'error');
                console.error('Upload error:', error);
            })
            .always(() => {
                if (this.isAllComplete()) {
                    this.uploading = false;
                    this.validateForm();
                }
            });
        },

        updateProgress: function(index, percent, status = '') {
            const $preview = $(`.file-preview[data-index="${index}"]`);
            const $status = $preview.find('.upload-status');
            
            if (!$status.find('.progress-bar').length) {
                $status.html('<div class="progress-bar"><div class="progress-bar-fill"></div></div>');
            }
            
            const $bar = $status.find('.progress-bar-fill');
            $bar.css('width', `${percent}%`);
            
            if (status) {
                $preview.addClass(`upload-${status}`);
            }
        },

        isAllComplete: function() {
            return $('.file-preview').toArray().every(el => 
                $(el).hasClass('upload-complete') || $(el).hasClass('upload-error')
            );
        },

        clearFiles: function() {
            this.files = [];
            this.fileData = [];
            $('#file-preview-list').empty();
            $('#chunk-files').val('');
            this.validateForm();
        }
    };

    $(document).ready(() => BulkChunkUpload.init());

})(jQuery);