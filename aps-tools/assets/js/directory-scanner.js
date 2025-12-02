(function($) {
    'use strict';

    const DirectoryScanner = {
        isProcessing: false,
        updateInterval: null,
        tableHandler: null,

        init: function() {
            console.log('Initializing Directory Scanner...');
            this.bindEvents();
            this.initModelSelection();
            this.initTable();
        },

        bindEvents: function() {
            console.log('Binding events...');
            $('#start-scan').on('click', () => this.startScan());
            $('#stop-scan').on('click', () => this.stopScan());
            
            // Model Category change handler
            $('#model-category').on('change', (e) => {
                const categoryId = e.target.value;
                console.log('Category changed:', categoryId);
                if (categoryId) {
                    this.loadModels(categoryId);
                } else {
                    $('#parent-model').html('<option value="">' + apsTools.i18n.selectModel + '</option>').prop('disabled', true);
                }
            });
        },

        initModelSelection: function() {
            // Disable parent model select until category is chosen
            $('#parent-model').prop('disabled', true);

            // Handle initial category selection if present
            const initialCategory = $('#model-category').val();
            if (initialCategory) {
                this.loadModels(initialCategory);
            }
        },

        loadModels: function(categoryId) {
            if (!categoryId) {
                return;
            }

            console.log('Loading models for category:', categoryId);
            
            $.ajax({
                url: apsTools.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aps_get_models_by_category',
                    category: categoryId,
                    nonce: apsTools.nonce
                },
                beforeSend: () => {
                    $('#parent-model').prop('disabled', true);
                },
                success: (response) => {
                    console.log('Models response:', response);
                    if (response.success && response.data) {
                        const $select = $('#parent-model');
                        $select.html('<option value="">' + apsTools.i18n.selectModel + '</option>');
                        response.data.forEach(model => {
                            $select.append(`<option value="${model.ID}">${model.post_title}</option>`);
                        });
                        $select.prop('disabled', false);
                    } else {
                        console.error('Error loading models:', response);
                        this.showError('Error loading models');
                    }
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.error('AJAX error:', {textStatus, errorThrown, response: jqXHR.responseText});
                    this.showError('Error loading models');
                }
            });
        },

        initTable: function() {
            const container = document.getElementById('directory-data-table');
            if (!container) return;

            this.tableHandler = new Handsontable(container, {
                data: [],
                rowHeaders: true,
                colHeaders: ['ID', 'SKU', 'Type', 'Status', 'Progress'],
                columns: [
                    { data: 'id', type: 'numeric', readOnly: true },
                    { data: 'sku', type: 'text' },
                    { data: 'type', type: 'dropdown', source: ['tensor', 'pattern', 'hybrid'] },
                    { data: 'status', type: 'text', readOnly: true },
                    { data: 'progress', type: 'numeric', readOnly: true }
                ],
                minSpareRows: 1,
                width: '100%',
                height: 400,
                licenseKey: 'non-commercial-and-evaluation'
            });
        },

        updateTableData: function(data) {
            if (this.tableHandler) {
                this.tableHandler.loadData(data);
            }
        },

        startScan: function() {
            console.log('Starting scan validation...');
            const categoryId = $('#model-category').val();
            const modelId = $('#parent-model').val();
            const directoryPath = $('#directory-path').val().trim();
            const batchSize = parseInt($('#batch-size').val());
            const recursive = $('#recursive-scan').is(':checked');

            console.log('Values:', {categoryId, modelId, directoryPath, batchSize, recursive});

            if (!categoryId || !modelId || !directoryPath) {
                this.showError('Please fill in all required fields');
                return;
            }

            $.ajax({
                url: apsTools.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aps_start_scan',
                    directory: directoryPath,
                    category: categoryId,
                    model: modelId,
                    batch_size: batchSize,
                    recursive: recursive,
                    nonce: apsTools.nonce
                },
                beforeSend: () => {
                    console.log('Sending AJAX request...');
                    this.showProgress();
                    $('#start-scan').prop('disabled', true);
                },
                success: (response) => {
                    console.log('AJAX response:', response);
                    if (response.success) {
                        this.isProcessing = true;
                        this.startStatusUpdates();
                        this.updateDisplay(response.data);
                    } else {
                        this.showError(response.data.message);
                        this.hideProgress();
                        $('#start-scan').prop('disabled', false);
                    }
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.error('AJAX error:', {textStatus, errorThrown, response: jqXHR.responseText});
                    this.showError('Error starting scan');
                    this.hideProgress();
                    $('#start-scan').prop('disabled', false);
                    
                 $(document).ready(() => DirectoryScanner.init());
                }
            });
        },

        updateDisplay: function(data) {
            // Update progress info
            if (data.stats) {
                this.updateProgressDisplay(data.stats);
            }

            // Update table if available
            if (this.hot && data.files) {
                const tableData = data.files.map(file => ({
                    id: file.id || null,
                    sku: file.sku || this.getFileNameFromPath(file.path),
                    type: file.type || 'tensor',
                    status: file.status || 'pending',
                    progress: file.progress || 0
                }));
                this.hot.loadData(tableData);
            }

            // Update error display
            if (data.errors && data.errors.length > 0) {
                this.showErrors(data.errors);
            }
        },

        stopScan: function() {
            $.ajax({
                url: apsTools.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aps_stop_scan',
                    nonce: apsTools.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.isProcessing = false;
                        this.stopStatusUpdates();
                        $('#start-scan').prop('disabled', false);
                    }
                }
            });
        },

        updateStatus: function() {
            $.ajax({
                url: apsTools.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aps_get_scan_status',
                    nonce: apsTools.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateDisplay(response.data);
                        
                        if (!response.data.processing) {
                            this.isProcessing = false;
                            this.stopStatusUpdates();
                            $('#start-scan').prop('disabled', false);
                        }
                    }
                }
            });
        },

        updateProgressDisplay: function(stats) {
            $('.progress-fill').css('width', stats.progress + '%');
            $('#processed-count').text(stats.processed);
            $('#failed-count').text(stats.failed);
            $('#pending-count').text(stats.pending);
        },

        startStatusUpdates: function() {
            this.updateInterval = setInterval(() => this.updateStatus(), 2000);
        },

        stopStatusUpdates: function() {
            if (this.updateInterval) {
                clearInterval(this.updateInterval);
                this.updateInterval = null;
            }
        },

        handleTableChange: function(changes) {
            if (!changes) return;
            
            changes.forEach(([row, prop, oldValue, newValue]) => {
                if (oldValue === newValue) return;
                
                const rowData = this.hot.getSourceDataAtRow(row);
                console.log('Row data changed:', rowData);
                
                // Trigger any necessary updates based on changes
                if (prop === 'type') {
                    this.handleTypeChange(rowData);
                }
            });
        },

        handleTypeChange: function(rowData) {
            // Handle type changes if needed
            console.log('Type changed for row:', rowData);
        },

        showErrors: function(errors) {
            $('#error-section').show();
            const template = _.template($('#error-row-template').html() || '<tr><td><%= file %></td><td><%= error %></td></tr>');
            const $errorLog = $('#error-log');
            
            $errorLog.empty();
            errors.forEach(error => {
                $errorLog.append(template(error));
            });
        },

        showError: function(message) {
            console.error('Error:', message);
            let errorContainer = $('#scanner-error-container');
            if (errorContainer.length === 0) {
                $('.scanner-container').prepend(
                    '<div id="scanner-error-container" class="notice notice-error" style="display:none; margin: 10px 0;"></div>'
                );
                errorContainer = $('#scanner-error-container');
            }

            errorContainer
                .html('<p>' + message + '</p>')
                .fadeIn()
                .delay(5000)
                .fadeOut();
        },

        showProgress: function() {
            $('#progress-section').show();
        },

        hideProgress: function() {
            $('#progress-section').hide();
        },

        getFileNameFromPath: function(path) {
            return path.split('/').pop().replace('.json', '');
        }
    };

    $(document).ready(() => DirectoryScanner.init());

})(jQuery);