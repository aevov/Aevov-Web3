/**
 * Aevov Diagnostic Network - Admin JavaScript
 * Handles all admin interface interactions and AJAX communications
 */

(function($) {
    'use strict';

    // Global ADN Admin object
    window.ADNAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initDashboard();
            this.initDataTables();
            this.initCharts();
            this.setupAutoRefresh();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Dashboard actions
            $(document).on('click', '#adn-run-system-scan', this.runSystemScan);
            $(document).on('click', '#adn-export-diagnostics', this.exportDiagnostics);
            $(document).on('click', '#adn-import-diagnostics', this.importDiagnostics);
            $(document).on('click', '#adn-schedule-health-check', this.scheduleHealthCheck);
            $(document).on('click', '#adn-clear-cache', this.clearCache);

            // Component testing actions
            $(document).on('click', '.adn-test-component', this.testComponent);
            $(document).on('click', '.adn-auto-fix-component', this.autoFixComponent);
            $(document).on('click', '.adn-component-details', this.showComponentDetails);
            $(document).on('click', '#adn-test-all-components', this.testAllComponents);
            $(document).on('click', '#adn-clear-test-results', this.clearTestResults);

            // AI Auto-fix actions
            $(document).on('click', '#adn-scan-issues', this.scanIssues);
            $(document).on('click', '.adn-apply-fix', this.applySingleFix);
            $(document).on('click', '.adn-preview-fix', this.previewFix);
            $(document).on('click', '#adn-apply-all-fixes', this.applyAllFixes);

            // System status actions
            $(document).on('click', '#adn-download-system-info', this.downloadSystemInfo);
            $(document).on('click', '#adn-run-health-check', this.runHealthCheck);

            // Modal handlers
            $(document).on('click', '.adn-modal-close', this.closeModal);
            $(document).on('click', '.adn-modal-overlay', this.closeModal);

            // Settings form
            $(document).on('submit', '#adn-settings-form', this.saveSettings);
        },

        /**
         * Initialize dashboard
         */
        initDashboard: function() {
            if ($('#adn-dashboard').length) {
                this.loadDashboardData();
                this.updateSystemHealth();
            }
        },

        /**
         * Initialize DataTables
         */
        initDataTables: function() {
            if ($('#adn-components-table').length) {
                $('#adn-components-table').DataTable({
                    responsive: true,
                    pageLength: 25,
                    order: [[2, 'desc']], // Sort by status
                    columnDefs: [
                        { orderable: false, targets: -1 } // Disable sorting on actions column
                    ]
                });
            }

            if ($('#adn-issues-table').length) {
                $('#adn-issues-table').DataTable({
                    responsive: true,
                    pageLength: 15,
                    order: [[1, 'desc']] // Sort by severity
                });
            }
        },

        /**
         * Initialize charts
         */
        initCharts: function() {
            this.initHealthChart();
            this.initComponentChart();
            this.initActivityChart();
        },

        /**
         * Initialize health score chart
         */
        initHealthChart: function() {
            const ctx = document.getElementById('adn-health-chart');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Healthy', 'Warning', 'Critical'],
                    datasets: [{
                        data: [85, 10, 5],
                        backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },

        /**
         * Initialize component status chart
         */
        initComponentChart: function() {
            const ctx = document.getElementById('adn-component-chart');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Passing', 'Warning', 'Failing', 'Unknown'],
                    datasets: [{
                        label: 'Components',
                        data: [12, 1, 2, 0],
                        backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#6c757d']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },

        /**
         * Initialize activity chart
         */
        initActivityChart: function() {
            const ctx = document.getElementById('adn-activity-chart');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Tests Run',
                        data: [12, 19, 3, 5, 2, 3, 9],
                        borderColor: '#007cba',
                        backgroundColor: 'rgba(0, 124, 186, 0.1)',
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
                    }
                }
            });
        },

        /**
         * Setup auto-refresh functionality
         */
        setupAutoRefresh: function() {
            // Auto-refresh dashboard every 30 seconds
            if ($('#adn-dashboard').length) {
                setInterval(() => {
                    this.loadDashboardData();
                }, 30000);
            }

            // Auto-refresh component status every 60 seconds
            if ($('#adn-components-table').length) {
                setInterval(() => {
                    this.refreshComponentStatus();
                }, 60000);
            }
        },

        /**
         * Load dashboard data via AJAX
         */
        loadDashboardData: function() {
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_get_dashboard_data',
                nonce: adnAdmin.nonce
            }, (response) => {
                if (response.success) {
                    this.updateDashboardUI(response.data);
                }
            });
        },

        /**
         * Update dashboard UI with new data
         */
        updateDashboardUI: function(data) {
            // Update health score
            $('#adn-health-score').text(data.health_score + '%');
            $('#adn-health-status').text(data.health_status);
            
            // Update component counts
            $('#adn-components-passing').text(data.components_passing);
            $('#adn-components-failing').text(data.components_failing);
            $('#adn-components-warning').text(data.components_warning);
            $('#adn-components-unknown').text(data.components_unknown);

            // Update health status class
            const healthClass = data.health_score >= 80 ? 'good' : 
                               data.health_score >= 60 ? 'warning' : 'critical';
            $('#adn-health-indicator').removeClass('good warning critical').addClass(healthClass);
        },

        /**
         * Update system health display
         */
        updateSystemHealth: function() {
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_run_health_check',
                nonce: adnAdmin.nonce
            }, (response) => {
                if (response.success) {
                    $('#adn-health-score').text(response.data.score + '%');
                    this.updateHealthIndicator(response.data.score);
                }
            });
        },

        /**
         * Update health indicator visual
         */
        updateHealthIndicator: function(score) {
            const indicator = $('#adn-health-indicator');
            indicator.removeClass('good warning critical');
            
            if (score >= 80) {
                indicator.addClass('good');
            } else if (score >= 60) {
                indicator.addClass('warning');
            } else {
                indicator.addClass('critical');
            }
        },

        /**
         * Refresh component status
         */
        refreshComponentStatus: function() {
            $('.adn-status-indicator').each(function() {
                const componentId = $(this).closest('tr').data('component-id');
                if (componentId) {
                    ADNAdmin.checkComponentStatus(componentId, $(this));
                }
            });
        },

        /**
         * Check individual component status
         */
        checkComponentStatus: function(componentId, statusElement) {
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_get_component_status',
                component_id: componentId,
                nonce: adnAdmin.nonce
            }, (response) => {
                if (response.success) {
                    const status = response.data.status;
                    statusElement.removeClass('status-pass status-fail status-warning status-unknown')
                                 .addClass('status-' + status)
                                 .text(status.charAt(0).toUpperCase() + status.slice(1));
                }
            });
        },

        /**
         * Run system scan
         */
        runSystemScan: function(e) {
            e.preventDefault();
            
            ADNAdmin.showProgressModal('Running System Scan', 'Scanning all components and dependencies...');
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_run_system_scan',
                nonce: adnAdmin.nonce
            }, (response) => {
                ADNAdmin.hideProgressModal();
                
                if (response.success) {
                    ADNAdmin.showNotification('System scan completed successfully!', 'success');
                    location.reload();
                } else {
                    ADNAdmin.showNotification('System scan failed: ' + response.data.message, 'error');
                }
            }).fail(() => {
                ADNAdmin.hideProgressModal();
                ADNAdmin.showNotification('System scan failed due to network error', 'error');
            });
        },

        /**
         * Export diagnostics
         */
        exportDiagnostics: function(e) {
            e.preventDefault();
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_export_diagnostics',
                nonce: adnAdmin.nonce
            }, (response) => {
                if (response.success) {
                    // Trigger download
                    const blob = new Blob([JSON.stringify(response.data, null, 2)], {
                        type: 'application/json'
                    });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'adn-diagnostics-' + new Date().toISOString().split('T')[0] + '.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    ADNAdmin.showNotification('Diagnostics exported successfully!', 'success');
                } else {
                    ADNAdmin.showNotification('Export failed: ' + response.data.message, 'error');
                }
            });
        },

        /**
         * Import diagnostics
         */
        importDiagnostics: function(e) {
            e.preventDefault();
            
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            input.onchange = function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            const data = JSON.parse(e.target.result);
                            ADNAdmin.processImportedDiagnostics(data);
                        } catch (error) {
                            ADNAdmin.showNotification('Invalid JSON file', 'error');
                        }
                    };
                    reader.readAsText(file);
                }
            };
            input.click();
        },

        /**
         * Process imported diagnostics
         */
        processImportedDiagnostics: function(data) {
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_import_diagnostics',
                data: JSON.stringify(data),
                nonce: adnAdmin.nonce
            }, (response) => {
                if (response.success) {
                    ADNAdmin.showNotification('Diagnostics imported successfully!', 'success');
                    location.reload();
                } else {
                    ADNAdmin.showNotification('Import failed: ' + response.data.message, 'error');
                }
            });
        },

        /**
         * Schedule health check
         */
        scheduleHealthCheck: function(e) {
            e.preventDefault();
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_schedule_health_check',
                nonce: adnAdmin.nonce
            }, (response) => {
                if (response.success) {
                    ADNAdmin.showNotification('Health check scheduled successfully!', 'success');
                } else {
                    ADNAdmin.showNotification('Scheduling failed: ' + response.data.message, 'error');
                }
            });
        },

        /**
         * Clear diagnostic cache
         */
        clearCache: function(e) {
            e.preventDefault();
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_clear_diagnostic_cache',
                nonce: adnAdmin.nonce
            }, (response) => {
                if (response.success) {
                    ADNAdmin.showNotification('Cache cleared successfully!', 'success');
                } else {
                    ADNAdmin.showNotification('Cache clear failed: ' + response.data.message, 'error');
                }
            });
        },

        /**
         * Test individual component
         */
        testComponent: function(e) {
            e.preventDefault();
            
            const componentId = $(this).data('component-id');
            const statusCell = $(this).closest('tr').find('.adn-status-indicator');
            
            statusCell.removeClass().addClass('adn-status-indicator status-testing').text('Testing...');
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_test_component',
                component_id: componentId,
                nonce: adnAdmin.nonce
            }, (response) => {
                if (response.success) {
                    const status = response.data.overall_status;
                    statusCell.removeClass().addClass('adn-status-indicator status-' + status)
                            .text(status.charAt(0).toUpperCase() + status.slice(1));
                    
                    // Update last tested
                    $(this).closest('tr').find('td:nth-child(4)').text('Just now');
                } else {
                    statusCell.removeClass().addClass('adn-status-indicator status-error').text('Error');
                    ADNAdmin.showNotification('Test failed: ' + response.data.message, 'error');
                }
            }).fail(() => {
                statusCell.removeClass().addClass('adn-status-indicator status-error').text('Error');
                ADNAdmin.showNotification('Test failed due to network error', 'error');
            });
        },

        /**
         * Auto-fix component
         */
        autoFixComponent: function(e) {
            e.preventDefault();
            
            const componentId = $(this).data('component-id');
            
            ADNAdmin.showProgressModal('Auto-Fixing Component', 'Applying AI-powered fixes...');
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_auto_fix_component',
                component_id: componentId,
                nonce: adnAdmin.nonce
            }, (response) => {
                ADNAdmin.hideProgressModal();
                
                if (response.success) {
                    ADNAdmin.showNotification('Auto-fix completed: ' + response.data.message, 'success');
                    // Re-test the component
                    ADNAdmin.testComponentById(componentId);
                } else {
                    ADNAdmin.showNotification('Auto-fix failed: ' + response.data.message, 'error');
                }
            }).fail(() => {
                ADNAdmin.hideProgressModal();
                ADNAdmin.showNotification('Auto-fix failed due to network error', 'error');
            });
        },

        /**
         * Test component by ID
         */
        testComponentById: function(componentId) {
            const row = $('tr[data-component-id="' + componentId + '"]');
            if (row.length) {
                row.find('.adn-test-component').trigger('click');
            }
        },

        /**
         * Show component details
         */
        showComponentDetails: function(e) {
            e.preventDefault();
            
            const componentId = $(this).data('component-id');
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_get_component_details',
                component_id: componentId,
                nonce: adnAdmin.nonce
            }, (response) => {
                if (response.success) {
                    $('#adn-component-details-title').text('Details: ' + response.data.name);
                    $('#adn-component-details-content').html(ADNAdmin.formatComponentDetails(response.data));
                    $('#adn-component-details-modal').show();
                }
            });
        },

        /**
         * Format component details for display
         */
        formatComponentDetails: function(data) {
            let html = '<div class="adn-component-details">';
            
            // Basic info
            html += '<div class="adn-detail-section">';
            html += '<h4>Basic Information</h4>';
            html += '<p><strong>Name:</strong> ' + data.name + '</p>';
            html += '<p><strong>Type:</strong> ' + data.type + '</p>';
            html += '<p><strong>Status:</strong> ' + data.status + '</p>';
            html += '<p><strong>File:</strong> ' + data.file + '</p>';
            html += '</div>';
            
            // Test results
            if (data.tests) {
                html += '<div class="adn-detail-section">';
                html += '<h4>Test Results</h4>';
                for (const [testName, testResult] of Object.entries(data.tests)) {
                    html += '<div class="adn-test-result">';
                    html += '<strong>' + testName + ':</strong> ';
                    html += '<span class="status-' + testResult.status + '">' + testResult.status + '</span>';
                    html += '<p>' + testResult.message + '</p>';
                    html += '</div>';
                }
                html += '</div>';
            }
            
            // Issues
            if (data.issues && data.issues.length > 0) {
                html += '<div class="adn-detail-section">';
                html += '<h4>Issues</h4>';
                data.issues.forEach(function(issue) {
                    html += '<div class="adn-issue issue-' + issue.type + '">';
                    html += '<strong>' + issue.type.toUpperCase() + ':</strong> ' + issue.message;
                    html += '</div>';
                });
                html += '</div>';
            }
            
            // Recommendations
            if (data.recommendations && data.recommendations.length > 0) {
                html += '<div class="adn-detail-section">';
                html += '<h4>Recommendations</h4>';
                html += '<ul>';
                data.recommendations.forEach(function(rec) {
                    html += '<li>' + rec + '</li>';
                });
                html += '</ul>';
                html += '</div>';
            }
            
            html += '</div>';
            return html;
        },

        /**
         * Test all components
         */
        testAllComponents: function(e) {
            e.preventDefault();
            
            ADNAdmin.showProgressModal('Testing All Components', 'This may take several minutes...');
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_test_all_components',
                nonce: adnAdmin.nonce
            }, (response) => {
                ADNAdmin.hideProgressModal();
                
                if (response.success) {
                    ADNAdmin.showNotification('All components tested successfully!', 'success');
                    location.reload();
                } else {
                    ADNAdmin.showNotification('Testing failed: ' + response.data.message, 'error');
                }
            }).fail(() => {
                ADNAdmin.hideProgressModal();
                ADNAdmin.showNotification('Testing failed due to network error', 'error');
            });
        },

        /**
         * Clear test results
         */
        clearTestResults: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear all test results?')) {
                return;
            }
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_clear_test_results',
                nonce: adnAdmin.nonce
            }, (response) => {
                if (response.success) {
                    ADNAdmin.showNotification('Test results cleared successfully!', 'success');
                    location.reload();
                } else {
                    ADNAdmin.showNotification('Failed to clear results: ' + response.data.message, 'error');
                }
            });
        },

        /**
         * Scan for issues
         */
        scanIssues: function(e) {
            e.preventDefault();
            
            ADNAdmin.showProgressModal('Scanning for Issues', 'AI engines are analyzing the system...');
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_scan_issues',
                nonce: adnAdmin.nonce
            }, (response) => {
                ADNAdmin.hideProgressModal();
                
                if (response.success) {
                    ADNAdmin.updateIssuesTable(response.data.issues);
                    ADNAdmin.updateFixesTable(response.data.fixes);
                    ADNAdmin.showNotification('Issue scan completed!', 'success');
                } else {
                    ADNAdmin.showNotification('Scan failed: ' + response.data.message, 'error');
                }
            }).fail(() => {
                ADNAdmin.hideProgressModal();
                ADNAdmin.showNotification('Scan failed due to network error', 'error');
            });
        },

        /**
         * Update issues table
         */
        updateIssuesTable: function(issues) {
            const tbody = $('#adn-issues-table tbody');
            tbody.empty();
            
            issues.forEach(function(issue) {
                const row = $('<tr>');
                row.append('<td>' + issue.component + '</td>');
                row.append('<td><span class="severity-' + issue.severity + '">' + issue.severity + '</span></td>');
                row.append('<td>' + issue.description + '</td>');
                row.append('<td>' + issue.recommendation + '</td>');
                tbody.append(row);
            });
        },

        /**
         * Update fixes table
         */
        updateFixesTable: function(fixes) {
            const container = $('#adn-fixes-container');
            container.empty();
            
            fixes.forEach(function(fix, index) {
                const fixCard = $('<div class="adn-fix-card">');
                fixCard.append('<h4>' + fix.title + '</h4>');
                fixCard.append('<p>' + fix.description + '</p>');
                fixCard.append('<div class="adn-fix-actions">');
                fixCard.find('.adn-fix-actions')
                       .append('<button class="button adn-preview-fix" data-fix-id="' + index + '">Preview</button>')
                       .append('<button class="button button-primary adn-apply-fix" data-fix-id="' + index + '">Apply Fix</button>');
                container.append(fixCard);
            });
        },

        /**
         * Apply single fix
         */
        applySingleFix: function(e) {
            e.preventDefault();
            
            const fixId = $(this).data('fix-id');
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_apply_single_fix',
                fix_id: fixId,
                nonce: adnAdmin.nonce
            }, (response) => {
                if (response.success) {
                    ADNAdmin.showNotification('Fix applied successfully!', 'success');
                    $(this).prop('disabled', true).text('Applied');
                } else {
                    ADNAdmin.showNotification('Fix failed: ' + response.data.message, 'error');
                }
            });
        },

        /**
         * Preview fix
         */
        previewFix: function(e) {
            e.preventDefault();
            
            const fixId = $(this).data('fix-id');
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_preview_fix',
                fix_id: fixId,
                nonce: adnAdmin.nonce
            }, (response) => {
                if (response.success) {
                    $('#adn-fix-preview-content').html('<pre>' + response.data.preview + '</pre>');
                    $('#adn-fix-preview-modal').show();
                } else {
                    ADNAdmin.showNotification('Preview failed: ' + response.data.message, 'error');
                }
            });
        },

        /**
         * Apply all fixes
         */
        applyAllFixes: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to apply all available fixes?')) {
                return;
            }
            
            ADNAdmin.showProgressModal('Applying All Fixes', 'This may take several minutes...');
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_apply_all_fixes',
                nonce: adnAdmin.nonce
            }, (response) => {
                ADNAdmin.hideProgressModal();
                
                if (response.success) {
                    const data = response.data;
                    ADNAdmin.showNotification(
                        'Fixes applied: ' + data.applied + ' successful, ' + 
                        data.failed + ' failed, ' + data.skipped + ' skipped', 
                        'success'
                    );
                } else {
                    ADNAdmin.showNotification('Apply all fixes failed: ' + response.data.message, 'error');
                }
            }).fail(() => {
                ADNAdmin.hideProgressModal();
                ADNAdmin.showNotification('Apply all fixes failed due to network error', 'error');
            });
        },

        /**
         * Download system info
         */
        downloadSystemInfo: function(e) {
            e.preventDefault();
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_download_system_info',
                nonce: adnAdmin.nonce
            }, (response) => {
                if (response.success) {
                    // Trigger download
                    const blob = new Blob([response.data.content], { type: 'text/plain' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'adn-system-info-' + new Date().toISOString().split('T')[0] + '.txt';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    ADNAdmin.showNotification('System info downloaded successfully!', 'success');
                } else {
                    ADNAdmin.showNotification('Download failed: ' + response.data.message, 'error');
                }
            });
        },

        /**
         * Run health check
         */
        runHealthCheck: function(e) {
            e.preventDefault();
            
            ADNAdmin.showProgressModal('Running Health Check', 'Analyzing system health...');
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_run_health_check',
                nonce: adnAdmin.nonce
            }, (response) => {
                ADNAdmin.hideProgressModal();
                
                if (response.success) {
                    ADNAdmin.updateHealthDisplay(response.data);
                    ADNAdmin.showNotification('Health check completed!', 'success');
                } else {
                    ADNAdmin.showNotification('Health check failed: ' + response.data.message, 'error');
                }
            }).fail(() => {
                ADNAdmin.hideProgressModal();
                ADNAdmin.showNotification('Health check failed due to network error', 'error');
            });
        },

        /**
         * Update health display
         */
        updateHealthDisplay: function(data) {
            $('#adn-health-score').text(data.score + '%');
            this.updateHealthIndicator(data.score);
            
            // Update health details if available
            if (data.details && $('#adn-health-details').length) {
                let html = '<ul>';
                data.details.forEach(function(detail) {
                    html += '<li class="health-' + detail.status + '">' + detail.message + '</li>';
                });
                html += '</ul>';
                $('#adn-health-details').html(html);
            }
        },

        /**
         * Save settings
         */
        saveSettings: function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_save_settings',
                settings: Object.fromEntries(formData),
                nonce: adnAdmin.nonce
            }, (response) => {
                if (response.success) {
                    ADNAdmin.showNotification('Settings saved successfully!', 'success');
                } else {
                    ADNAdmin.showNotification('Settings save failed: ' + response.data.message, 'error');
                }
            });
        },

        /**
         * Show progress modal
         */
        showProgressModal: function(title, message) {
            $('#adn-progress-modal-title').text(title);
            $('#adn-progress-modal-message').text(message);
            $('#adn-progress-modal').show();
        },

        /**
         * Hide progress modal
         */
        hideProgressModal: function() {
            $('#adn-progress-modal').hide();
        },

        /**
         * Close modal
         */
        closeModal: function(e) {
            if (e.target === this || $(e.target).hasClass('adn-modal-close')) {
                $(this).closest('.adn-modal').hide();
            }
        },

        /**
         * Show notification
         */
        showNotification: function(message, type) {
            const notification = $('<div class="adn-notification adn-notification-' + type + '">')
                .text(message)
                .appendTo('body');
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                notification.fadeOut(() => {
                    notification.remove();
                });
            }, 5000);
            
            // Allow manual close
            notification.on('click', function() {
                $(this).fadeOut(() => {
                    $(this).remove();
                });
            });
        },

        /**
         * Utility function to escape HTML
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        /**
         * Utility function to format bytes
         */
        formatBytes: function(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        },

        /**
         * Utility function to format time ago
         */
        timeAgo: function(date) {
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 60) return 'Just now';
            if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
            if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
            return Math.floor(diffInSeconds / 86400) + ' days ago';
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ADNAdmin.init();
    });

})(jQuery);