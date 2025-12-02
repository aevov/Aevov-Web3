/**
 * Aevov Demo System JavaScript
 * Comprehensive client-side functionality for the demo system
 */

(function($) {
    'use strict';
    
    // Global demo system object
    window.adsDemo = {
        // Configuration
        config: {
            refreshInterval: 5000,
            maxRetries: 3,
            apiTimeout: 30000
        },
        
        // State management
        state: {
            isProcessing: false,
            currentWorkflow: null,
            monitoringActive: false,
            apiCalls: [],
            charts: {}
        },
        
        // Initialize the demo system
        init: function() {
            this.bindEvents();
            this.initializeCharts();
            this.setupWebSocket();
            console.log('Aevov Demo System initialized');
        },
        
        // Bind event handlers
        bindEvents: function() {
            // Model download events
            $(document).on('click', '.ads-download-model', this.handleModelDownload.bind(this));
            $(document).on('click', '.ads-delete-model', this.handleModelDelete.bind(this));
            
            // Workflow events
            $(document).on('click', '.ads-start-workflow', this.handleWorkflowStart.bind(this));
            $(document).on('click', '.ads-stop-workflow', this.handleWorkflowStop.bind(this));
            
            // System events
            $(document).on('click', '.ads-reset-system', this.handleSystemReset.bind(this));
            $(document).on('click', '.ads-refresh-status', this.refreshSystemStatus.bind(this));
            
            // API configuration events
            $(document).on('submit', '.ads-api-config-form', this.handleApiConfigSave.bind(this));
            $(document).on('click', '.ads-generate-typebot-config', this.generateTypebotConfig.bind(this));
            
            // Chat testing events
            $(document).on('click', '.ads-chat-send', this.handleChatSend.bind(this));
            $(document).on('keypress', '.ads-chat-input', this.handleChatKeypress.bind(this));
            
            // Quick action buttons
            $(document).on('click', '[onclick*="adsDemo."]', function(e) {
                e.preventDefault();
                var onclick = $(this).attr('onclick');
                if (onclick) {
                    eval(onclick);
                }
            });
        },
        
        // Initialize dashboard
        initDashboard: function() {
            // Only initialize if we're on the dashboard page
            if (!$('.ads-demo-container').length) return;
            
            this.refreshSystemStatus();
            this.loadRecentActivity();
            this.initializeCharts();
        },
        
        // Start monitoring
        startMonitoring: function() {
            // Only start monitoring if we're on the dashboard page and not already active
            if (this.state.monitoringActive || !$('.ads-demo-container').length) return;
            
            this.state.monitoringActive = true;
            this.monitoringInterval = setInterval(() => {
                this.updateMonitoringData();
            }, this.config.refreshInterval);
            
            console.log('Real-time monitoring started');
        },
        
        // Stop monitoring
        stopMonitoring: function() {
            if (this.monitoringInterval) {
                clearInterval(this.monitoringInterval);
                this.state.monitoringActive = false;
                console.log('Real-time monitoring stopped');
            }
        },
        
        // Initialize charts
        initializeCharts: function() {
            // Only initialize charts if Chart.js is available and containers exist
            if (typeof Chart === 'undefined') {
                console.log('Chart.js not loaded, skipping chart initialization');
                return;
            }
            
            // API Activity Chart
            const apiCtx = document.getElementById('ads-api-chart');
            if (apiCtx) {
                try {
                    this.state.charts.apiChart = new Chart(apiCtx, {
                        type: 'line',
                        data: {
                            labels: [],
                            datasets: [{
                                label: 'API Calls',
                                data: [],
                                borderColor: '#667eea',
                                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                } catch (error) {
                    console.log('Failed to initialize API chart:', error);
                }
            }
            
            // Initialize pipeline visualization
            this.initPipelineVisualization();
        },
        
        // Initialize pipeline visualization with D3.js
        initPipelineVisualization: function() {
            // Only initialize if D3.js is available and container exists
            if (typeof d3 === 'undefined') {
                console.log('D3.js not loaded, skipping pipeline visualization');
                return;
            }
            
            const container = d3.select('#ads-pipeline-visualization');
            if (container.empty()) return;
            
            try {
                const width = 400;
                const height = 200;
                
                const svg = container.append('svg')
                    .attr('width', width)
                    .attr('height', height);
            
            // Pipeline stages
            const stages = [
                { id: 'download', label: 'Download', x: 50, y: 100, status: 'inactive' },
                { id: 'chunk', label: 'Chunking', x: 150, y: 100, status: 'inactive' },
                { id: 'compare', label: 'Comparator', x: 250, y: 100, status: 'inactive' },
                { id: 'process', label: 'Processing', x: 350, y: 100, status: 'inactive' }
            ];
            
            // Draw connections
            svg.selectAll('.pipeline-connection')
                .data(stages.slice(0, -1))
                .enter()
                .append('line')
                .attr('class', 'pipeline-connection')
                .attr('x1', d => d.x + 25)
                .attr('y1', d => d.y)
                .attr('x2', (d, i) => stages[i + 1].x - 25)
                .attr('y2', d => d.y)
                .attr('stroke', '#e9ecef')
                .attr('stroke-width', 2);
            
            // Draw stage nodes
            const nodes = svg.selectAll('.pipeline-node')
                .data(stages)
                .enter()
                .append('g')
                .attr('class', 'pipeline-node')
                .attr('transform', d => `translate(${d.x}, ${d.y})`);
            
            nodes.append('circle')
                .attr('r', 20)
                .attr('fill', '#f8f9fa')
                .attr('stroke', '#e9ecef')
                .attr('stroke-width', 2);
            
            nodes.append('text')
                .attr('text-anchor', 'middle')
                .attr('dy', '0.35em')
                .attr('font-size', '10px')
                .attr('fill', '#666')
                .text(d => d.label.substring(0, 4));
            
                // Store reference for updates
                this.state.pipelineVisualization = { svg, stages, nodes };
            } catch (error) {
                console.log('Failed to initialize pipeline visualization:', error);
            }
        },
        
        // Update pipeline visualization
        updatePipelineVisualization: function(activeStage) {
            if (!this.state.pipelineVisualization) return;
            
            const { nodes, stages } = this.state.pipelineVisualization;
            
            // Update stage statuses
            stages.forEach(stage => {
                stage.status = stage.id === activeStage ? 'active' : 'inactive';
            });
            
            // Update visual representation
            nodes.select('circle')
                .transition()
                .duration(300)
                .attr('fill', d => d.status === 'active' ? '#667eea' : '#f8f9fa')
                .attr('stroke', d => d.status === 'active' ? '#5a6fd8' : '#e9ecef');
            
            nodes.select('text')
                .transition()
                .duration(300)
                .attr('fill', d => d.status === 'active' ? 'white' : '#666');
        },
        
        // Handle model download
        handleModelDownload: function(e) {
            e.preventDefault();
            
            if (this.state.isProcessing) {
                this.showNotification('Another operation is in progress', 'warning');
                return;
            }
            
            const modelKey = $(e.target).data('model-key') || this.getSelectedModel();
            if (!modelKey) {
                this.showNotification('Please select a model to download', 'error');
                return;
            }
            
            this.downloadModel(modelKey);
        },
        
        // Download model
        downloadModel: function(modelKey) {
            this.state.isProcessing = true;
            this.showLoadingOverlay('Downloading model...');
            
            $.ajax({
                url: adsDemo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ads_download_model',
                    model_key: modelKey,
                    nonce: adsDemo.nonce
                },
                timeout: this.config.apiTimeout,
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Model download started successfully!', 'success');
                        this.startDownloadProgress(modelKey);
                    } else {
                        this.showNotification('Download failed: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotification('Network error: ' + error, 'error');
                },
                complete: () => {
                    this.state.isProcessing = false;
                    this.hideLoadingOverlay();
                }
            });
        },
        
        // Start download progress monitoring
        startDownloadProgress: function(modelKey) {
            const progressInterval = setInterval(() => {
                $.ajax({
                    url: adsDemo.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ads_get_download_progress',
                        model_key: modelKey,
                        nonce: adsDemo.nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            const progress = response.data.progress;
                            this.updateDownloadProgress(modelKey, progress);
                            
                            if (progress >= 100 || response.data.status === 'completed') {
                                clearInterval(progressInterval);
                                this.showNotification('Model download completed!', 'success');
                                this.refreshSystemStatus();
                            }
                        }
                    }
                });
            }, 2000);
        },
        
        // Update download progress
        updateDownloadProgress: function(modelKey, progress) {
            const progressBar = $(`.ads-progress-bar[data-model="${modelKey}"] .ads-progress-fill`);
            const progressText = $(`.ads-progress-text[data-model="${modelKey}"]`);
            
            if (progressBar.length) {
                progressBar.css('width', progress + '%');
            }
            
            if (progressText.length) {
                progressText.text(`${progress}% complete`);
            }
        },
        
        // Handle workflow start
        handleWorkflowStart: function(e) {
            e.preventDefault();
            
            if (this.state.isProcessing) {
                this.showNotification('Another operation is in progress', 'warning');
                return;
            }
            
            const modelKey = $(e.target).data('model-key') || this.getSelectedModel();
            if (!modelKey) {
                this.showNotification('Please select a model for the workflow', 'error');
                return;
            }
            
            this.startWorkflow(modelKey);
        },
        
        // Start automated workflow
        startWorkflow: function(modelKey) {
            this.state.isProcessing = true;
            this.state.currentWorkflow = modelKey;
            this.showLoadingOverlay('Starting automated workflow...');
            
            $.ajax({
                url: adsDemo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ads_start_workflow',
                    model_key: modelKey,
                    nonce: adsDemo.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Workflow started successfully!', 'success');
                        this.startWorkflowMonitoring(response.data.execution_id);
                    } else {
                        this.showNotification('Workflow failed to start: ' + (response.data || 'Unknown error'), 'error');
                        this.state.isProcessing = false;
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotification('Network error: ' + error, 'error');
                    this.state.isProcessing = false;
                },
                complete: () => {
                    this.hideLoadingOverlay();
                }
            });
        },
        
        // Start workflow monitoring
        startWorkflowMonitoring: function(executionId) {
            const monitorInterval = setInterval(() => {
                $.ajax({
                    url: adsDemo.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ads_get_workflow_status',
                        execution_id: executionId,
                        nonce: adsDemo.nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            const status = response.data;
                            this.updateWorkflowStatus(status);
                            
                            if (status.completed || status.failed) {
                                clearInterval(monitorInterval);
                                this.state.isProcessing = false;
                                this.state.currentWorkflow = null;
                                
                                if (status.completed) {
                                    this.showNotification('Workflow completed successfully!', 'success');
                                } else {
                                    this.showNotification('Workflow failed: ' + status.error, 'error');
                                }
                            }
                        }
                    }
                });
            }, 3000);
        },
        
        // Update workflow status display
        updateWorkflowStatus: function(status) {
            // Update pipeline visualization
            this.updatePipelineVisualization(status.current_step);
            
            // Update workflow step cards
            $('.ads-workflow-step').removeClass('active completed failed');
            
            status.steps.forEach(step => {
                const stepElement = $(`.ads-workflow-step[data-step="${step.name}"]`);
                stepElement.addClass(step.status);
                
                if (step.status === 'active') {
                    stepElement.find('.ads-workflow-step-icon').html('<div class="ads-loading"></div>');
                } else if (step.status === 'completed') {
                    stepElement.find('.ads-workflow-step-icon').html('✓');
                } else if (step.status === 'failed') {
                    stepElement.find('.ads-workflow-step-icon').html('✗');
                }
            });
        },
        
        // Handle system reset
        handleSystemReset: function(e) {
            e.preventDefault();
            
            if (!confirm(adsDemo.i18n.confirm_reset)) {
                return;
            }
            
            this.resetSystem();
        },
        
        // Reset entire system
        resetSystem: function() {
            this.showLoadingOverlay('Resetting system...');
            
            $.ajax({
                url: adsDemo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ads_reset_system',
                    nonce: adsDemo.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('System reset completed successfully!', 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        this.showNotification('Reset failed: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotification('Network error: ' + error, 'error');
                },
                complete: () => {
                    this.hideLoadingOverlay();
                }
            });
        },
        
        // Generate Typebot configuration
        generateTypebotConfig: function() {
            this.showLoadingOverlay('Generating Typebot configuration...');
            
            $.ajax({
                url: adsDemo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ads_generate_typebot_config',
                    nonce: adsDemo.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displayTypebotConfig(response.data);
                        this.showNotification('Typebot configuration generated!', 'success');
                    } else {
                        this.showNotification('Configuration generation failed: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotification('Network error: ' + error, 'error');
                },
                complete: () => {
                    this.hideLoadingOverlay();
                }
            });
        },
        
        // Display Typebot configuration
        displayTypebotConfig: function(config) {
            const configContainer = $('#ads-typebot-config-display');
            if (configContainer.length) {
                configContainer.html(`
                    <div class="ads-config-section">
                        <h4>Typebot API Configuration</h4>
                        <div class="ads-form-group">
                            <label>Webhook URL:</label>
                            <input type="text" value="${config.webhook_url}" readonly class="ads-config-input">
                        </div>
                        <div class="ads-form-group">
                            <label>API Key:</label>
                            <input type="text" value="${config.api_key}" readonly class="ads-config-input">
                        </div>
                        <div class="ads-form-group">
                            <label>Model Endpoint:</label>
                            <input type="text" value="${config.model_endpoint}" readonly class="ads-config-input">
                        </div>
                        <button class="button button-primary" onclick="adsDemo.copyConfigToClipboard()">
                            Copy Configuration
                        </button>
                    </div>
                `);
            }
        },
        
        // Copy configuration to clipboard
        copyConfigToClipboard: function() {
            const config = {
                webhook_url: $('.ads-config-input').eq(0).val(),
                api_key: $('.ads-config-input').eq(1).val(),
                model_endpoint: $('.ads-config-input').eq(2).val()
            };
            
            navigator.clipboard.writeText(JSON.stringify(config, null, 2)).then(() => {
                this.showNotification('Configuration copied to clipboard!', 'success');
            }).catch(() => {
                this.showNotification('Failed to copy configuration', 'error');
            });
        },
        
        // Handle chat send
        handleChatSend: function(e) {
            e.preventDefault();
            
            const input = $('.ads-chat-input');
            const message = input.val().trim();
            
            if (!message) return;
            
            this.sendChatMessage(message);
            input.val('');
        },
        
        // Handle chat keypress
        handleChatKeypress: function(e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                this.handleChatSend(e);
            }
        },
        
        // Send chat message
        sendChatMessage: function(message) {
            const messagesContainer = $('.ads-chat-messages');
            
            // Add user message
            messagesContainer.append(`
                <div class="ads-chat-message ads-chat-user">
                    <strong>You:</strong> ${this.escapeHtml(message)}
                </div>
            `);
            
            // Add loading indicator
            messagesContainer.append(`
                <div class="ads-chat-message ads-chat-bot ads-chat-loading">
                    <strong>AI:</strong> <div class="ads-loading"></div>
                </div>
            `);
            
            messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
            
            // Send to API
            $.ajax({
                url: adsDemo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ads_test_chatbot',
                    message: message,
                    nonce: adsDemo.nonce
                },
                success: (response) => {
                    $('.ads-chat-loading').remove();
                    
                    if (response.success) {
                        messagesContainer.append(`
                            <div class="ads-chat-message ads-chat-bot">
                                <strong>AI:</strong> ${this.escapeHtml(response.data.response)}
                            </div>
                        `);
                        
                        // Log API call
                        this.logApiCall({
                            method: 'POST',
                            endpoint: '/chat',
                            request: { message: message },
                            response: response.data,
                            response_time: response.data.response_time || 0
                        });
                    } else {
                        messagesContainer.append(`
                            <div class="ads-chat-message ads-chat-error">
                                <strong>Error:</strong> ${this.escapeHtml(response.data || 'Unknown error')}
                            </div>
                        `);
                    }
                    
                    messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
                },
                error: (xhr, status, error) => {
                    $('.ads-chat-loading').remove();
                    messagesContainer.append(`
                        <div class="ads-chat-message ads-chat-error">
                            <strong>Network Error:</strong> ${this.escapeHtml(error)}
                        </div>
                    `);
                    messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
                }
            });
        },
        
        // Log API call for monitoring
        logApiCall: function(callData) {
            this.state.apiCalls.unshift({
                ...callData,
                timestamp: new Date(),
                id: Date.now()
            });
            
            // Keep only last 50 calls
            if (this.state.apiCalls.length > 50) {
                this.state.apiCalls = this.state.apiCalls.slice(0, 50);
            }
            
            this.updateApiMonitor();
            this.updateApiChart();
        },
        
        // Update API monitor display
        updateApiMonitor: function() {
            const monitorContainer = $('.ads-api-calls');
            if (!monitorContainer.length) return;
            
            const callsHtml = this.state.apiCalls.slice(0, 10).map(call => `
                <div class="ads-api-call">
                    <div class="ads-api-call-header">
                        <span class="ads-api-method ${call.method.toLowerCase()}">${call.method}</span>
                        <span class="ads-api-endpoint">${call.endpoint}</span>
                        <span class="ads-api-response-time">${call.response_time}ms</span>
                    </div>
                    <div class="ads-api-call-body">
                        <strong>Request:</strong> ${JSON.stringify(call.request, null, 2)}
                        <br><strong>Response:</strong> ${JSON.stringify(call.response, null, 2)}
                    </div>
                </div>
            `).join('');
            
            monitorContainer.html(callsHtml);
        },
        
        // Update API chart
        updateApiChart: function() {
            if (!this.state.charts.apiChart) return;
            
            const chart = this.state.charts.apiChart;
            const now = new Date();
            const timeLabels = [];
            const callCounts = [];
            
            // Generate last 10 minutes of data
            for (let i = 9; i >= 0; i--) {
                const time = new Date(now.getTime() - i * 60000);
                timeLabels.push(time.toLocaleTimeString());
                
                const count = this.state.apiCalls.filter(call => {
                    const callTime = new Date(call.timestamp);
                    return callTime >= time && callTime < new Date(time.getTime() + 60000);
                }).length;
                
                callCounts.push(count);
            }
            
            chart.data.labels = timeLabels;
            chart.data.datasets[0].data = callCounts;
            chart.update();
        },
        
        // Update monitoring data
        updateMonitoringData: function() {
            this.refreshSystemStatus();
            this.updateApiChart();
        },
        
        // Refresh system status
        refreshSystemStatus: function() {
            // Only refresh if system status container exists
            if (!$('.ads-status-indicators').length) return;
            
            $.ajax({
                url: adsDemo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ads_get_system_status',
                    nonce: adsDemo.nonce
                },
                timeout: 10000, // 10 second timeout
                success: (response) => {
                    if (response && response.success && response.data) {
                        this.updateSystemStatusDisplay(response.data);
                    }
                },
                error: (xhr, status, error) => {
                    console.log('System status refresh failed:', error);
                    // Don't show error to user, just log it
                }
            });
        },
        
        // Update system status display
        updateSystemStatusDisplay: function(status) {
            // Update status indicators
            Object.keys(status).forEach(key => {
                const indicator = $(`.ads-status-item[data-component="${key}"]`);
                if (indicator.length) {
                    indicator.removeClass('active inactive warning')
                           .addClass(status[key].status);
                    indicator.find('.ads-status-icon').text(status[key].icon);
                    indicator.find('.ads-status-label').text(status[key].label);
                }
            });
        },
        
        // Load recent activity
        loadRecentActivity: function() {
            // Only load if activity container exists
            if (!$('.ads-activity-list').length) return;
            
            $.ajax({
                url: adsDemo.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ads_get_recent_activity',
                    nonce: adsDemo.nonce
                },
                timeout: 10000, // 10 second timeout
                success: (response) => {
                    if (response && response.success && response.data) {
                        this.updateRecentActivityDisplay(response.data);
                    }
                },
                error: (xhr, status, error) => {
                    console.log('Recent activity load failed:', error);
                    // Don't show error to user, just log it
                }
            });
        },
        
        // Update recent activity display
        updateRecentActivityDisplay: function(activities) {
            const container = $('.ads-activity-list');
            if (!container.length) return;
            
            if (activities.length === 0) {
                container.html('<p class="ads-empty-state">No recent activity.</p>');
                return;
            }
            
            const activitiesHtml = activities.map(activity => `
                <div class="ads-activity-item">
                    <div class="ads-activity-info">
                        <strong>${this.escapeHtml(activity.model_name)}</strong>
                        <span class="ads-activity-step">${this.escapeHtml(activity.current_step)}</span>
                    </div>
                    <div class="ads-activity-status ${activity.status}">
                        ${this.escapeHtml(activity.status_label)}
                    </div>
                </div>
            `).join('');
            
            container.html(activitiesHtml);
        },
        
        // Get selected model
        getSelectedModel: function() {
            const selected = $('.ads-model-selector:checked').val() || 
                           $('.ads-model-dropdown').val() ||
                           $('.ads-model-card.selected').data('model-key');
            return selected;
        },
        
        // Quick demo function
        startQuickDemo: function() {
            if (this.state.isProcessing) {
                this.showNotification('Another operation is in progress', 'warning');
                return;
            }
            
            // Navigate to model management
            window.location.href = adsDemo.adminUrl + 'admin.php?page=ads-model-management&quick_demo=1';
        },
        
        // Utility functions
        showLoadingOverlay: function(message) {
            const overlay = $(`
                <div class="ads-loading-overlay">
                    <div class="ads-loading-content">
                        <div class="ads-loading"></div>
                        <p>${message || 'Processing...'}</p>
                    </div>
                </div>
            `);
            
            $('body').append(overlay);
        },
        
        hideLoadingOverlay: function() {
            $('.ads-loading-overlay').remove();
        },
        
        showNotification: function(message, type) {
            type = type || 'info';
            
            const notification = $(`
                <div class="ads-notification ${type}">
                    ${this.escapeHtml(message)}
                </div>
            `);
            
            $('body').append(notification);
            
            setTimeout(() => {
                notification.fadeOut(() => {
                    notification.remove();
                });
            }, 5000);
        },
        
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        setupWebSocket: function() {
            // WebSocket setup for real-time updates (if available)
            if (typeof WebSocket !== 'undefined' && adsDemo.websocketUrl) {
                try {
                    this.websocket = new WebSocket(adsDemo.websocketUrl);
                    
                    this.websocket.onmessage = (event) => {
                        const data = JSON.parse(event.data);
                        this.handleWebSocketMessage(data);
                    };
                    
                    this.websocket.onerror = (error) => {
                        console.log('WebSocket error:', error);
                    };
                } catch (error) {
                    console.log('WebSocket not available:', error);
                }
            }
        },
        
        handleWebSocketMessage: function(data) {
            switch (data.type) {
                case 'workflow_update':
                    this.updateWorkflowStatus(data.payload);
                    break;
                case 'download_progress':
                    this.updateDownloadProgress(data.payload.model_key, data.payload.progress);
                    break;
                case 'system_status':
                    this.updateSystemStatusDisplay(data.payload);
                    break;
                case 'api_call':
                    this.logApiCall(data.payload);
                    break;
            }
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        adsDemo.init();
    });
    
    // Global functions for backward compatibility
    window.startQuickDemo = function() {
        adsDemo.startQuickDemo();
    };
    
    window.downloadModel = function() {
        adsDemo.handleModelDownload({ preventDefault: function() {} });
    };
    
    window.startWorkflow = function() {
        adsDemo.handleWorkflowStart({ preventDefault: function() {} });
    };
    
    window.configureAPI = function() {
        window.location.href = adsDemo.adminUrl + 'admin.php?page=ads-api-configuration';
    };
    
    window.resetSystem = function() {
        adsDemo.handleSystemReset({ preventDefault: function() {} });
    };
    
})(jQuery);