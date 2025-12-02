/**
 * Aevov Diagnostic Network - Comprehensive Testing JavaScript
 * Handles comprehensive feature testing with visual light indicators
 */

(function($) {
    'use strict';

    // Global Comprehensive Testing object
    window.ADNComprehensiveTesting = {
        
        // Configuration
        config: {
            updateInterval: 1000, // 1 second
            animationDuration: 500,
            testTimeout: 300000, // 5 minutes
            visualMode: true
        },
        
        // State
        state: {
            isRunning: false,
            currentSession: null,
            featureMap: {},
            componentGroups: {},
            visualIndicators: {},
            testResults: null,
            progressData: null
        },
        
        // DOM elements
        elements: {},
        
        /**
         * Initialize comprehensive testing
         */
        init: function() {
            this.bindEvents();
            this.loadTestingData();
            this.initializeVisualization();
            this.setupRealTimeUpdates();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Main controls
            $(document).on('click', '#adn-start-comprehensive-test', this.startComprehensiveTest.bind(this));
            $(document).on('click', '#adn-stop-comprehensive-test', this.stopComprehensiveTest.bind(this));
            $(document).on('click', '#adn-reset-test-view', this.resetTestView.bind(this));
            
            // Group controls
            $(document).on('change', '.adn-group-checkbox', this.updateGroupSelection.bind(this));
            $(document).on('click', '.adn-test-group-btn', this.testSingleGroup.bind(this));
            
            // Feature controls
            $(document).on('click', '.adn-feature-node', this.showFeatureDetails.bind(this));
            $(document).on('mouseenter', '.adn-feature-node', this.showFeatureTooltip.bind(this));
            $(document).on('mouseleave', '.adn-feature-node', this.hideFeatureTooltip.bind(this));
            
            // Modal controls
            $(document).on('click', '#adn-close-test-modal', this.closeTestModal.bind(this));
            $(document).on('click', '.adn-modal-backdrop', this.closeTestModal.bind(this));
        },

        /**
         * Load testing data from server
         */
        loadTestingData: function() {
            $.post(adnMapData.ajaxUrl, {
                action: 'adn_get_comprehensive_test_data',
                nonce: adnMapData.nonce
            }, (response) => {
                if (response.success) {
                    this.state.featureMap = response.data.feature_map;
                    this.state.componentGroups = response.data.component_groups;
                    this.state.visualIndicators = response.data.visual_indicators;
                    this.state.testResults = response.data.latest_results;
                    
                    this.renderTestingInterface();
                } else {
                    console.error('Failed to load testing data:', response.data.message);
                }
            });
        },

        /**
         * Initialize visualization
         */
        initializeVisualization: function() {
            // Create main testing container
            if (!$('#adn-comprehensive-testing-container').length) {
                $('body').append(`
                    <div id="adn-comprehensive-testing-container" class="adn-testing-container hidden">
                        <div class="adn-testing-header">
                            <h2>🧪 Comprehensive Feature Testing</h2>
                            <div class="adn-testing-controls">
                                <button id="adn-start-comprehensive-test" class="adn-btn adn-btn-primary">
                                    <span class="dashicons dashicons-controls-play"></span> Start Test
                                </button>
                                <button id="adn-stop-comprehensive-test" class="adn-btn adn-btn-secondary" disabled>
                                    <span class="dashicons dashicons-controls-pause"></span> Stop Test
                                </button>
                                <button id="adn-reset-test-view" class="adn-btn adn-btn-tertiary">
                                    <span class="dashicons dashicons-update"></span> Reset View
                                </button>
                            </div>
                        </div>
                        <div class="adn-testing-progress">
                            <div class="adn-progress-bar">
                                <div class="adn-progress-fill" style="width: 0%"></div>
                            </div>
                            <div class="adn-progress-text">Ready to start testing</div>
                        </div>
                        <div class="adn-testing-visualization">
                            <div id="adn-feature-map-container"></div>
                        </div>
                        <div class="adn-testing-sidebar">
                            <div class="adn-group-selection">
                                <h3>Component Groups</h3>
                                <div id="adn-group-checkboxes"></div>
                            </div>
                            <div class="adn-test-status">
                                <h3>Test Status</h3>
                                <div id="adn-status-summary"></div>
                            </div>
                        </div>
                    </div>
                `);
            }
            
            this.elements = {
                container: $('#adn-comprehensive-testing-container'),
                startBtn: $('#adn-start-comprehensive-test'),
                stopBtn: $('#adn-stop-comprehensive-test'),
                resetBtn: $('#adn-reset-test-view'),
                progressBar: $('.adn-progress-fill'),
                progressText: $('.adn-progress-text'),
                featureMapContainer: $('#adn-feature-map-container'),
                groupCheckboxes: $('#adn-group-checkboxes'),
                statusSummary: $('#adn-status-summary')
            };
        },

        /**
         * Render testing interface
         */
        renderTestingInterface: function() {
            if (!this.state.featureMap || Object.keys(this.state.featureMap).length === 0) {
                return;
            }
            
            this.renderGroupSelection();
            this.renderFeatureMap();
            this.renderStatusSummary();
            
            // Show the testing container
            this.elements.container.removeClass('hidden');
        },

        /**
         * Render group selection checkboxes
         */
        renderGroupSelection: function() {
            let html = '';
            
            for (const [groupId, group] of Object.entries(this.state.featureMap)) {
                const groupInfo = this.state.componentGroups[groupId] || {};
                const featureCount = Object.keys(group.features || {}).length;
                
                html += `
                    <div class="adn-group-item">
                        <label class="adn-group-label">
                            <input type="checkbox" class="adn-group-checkbox" value="${groupId}" checked>
                            <span class="adn-group-color" style="background-color: ${group.group_color}"></span>
                            <span class="adn-group-name">${group.group_name}</span>
                            <span class="adn-feature-count">(${featureCount} features)</span>
                        </label>
                        <button class="adn-test-group-btn adn-btn-small" data-group="${groupId}">
                            Test Group
                        </button>
                    </div>
                `;
            }
            
            this.elements.groupCheckboxes.html(html);
        },

        /**
         * Render feature map visualization
         */
        renderFeatureMap: function() {
            const container = this.elements.featureMapContainer;
            container.empty();
            
            // Create SVG for feature map
            const svg = d3.select(container[0])
                .append('svg')
                .attr('width', '100%')
                .attr('height', '600px')
                .attr('class', 'adn-feature-map-svg');
            
            // Create groups for different layers
            const groupsLayer = svg.append('g').attr('class', 'groups-layer');
            const connectionsLayer = svg.append('g').attr('class', 'connections-layer');
            const featuresLayer = svg.append('g').attr('class', 'features-layer');
            
            // Render component groups
            this.renderComponentGroups(groupsLayer);
            
            // Render features
            this.renderFeatures(featuresLayer);
            
            // Render connections
            this.renderConnections(connectionsLayer);
        },

        /**
         * Render component groups
         */
        renderComponentGroups: function(groupsLayer) {
            const groups = Object.entries(this.state.featureMap).map(([id, group]) => ({
                id: id,
                name: group.group_name,
                color: group.group_color,
                icon: group.group_icon,
                position: this.state.componentGroups[id]?.position || { x: 200, y: 200 },
                featureCount: Object.keys(group.features || {}).length
            }));
            
            const groupNodes = groupsLayer.selectAll('.adn-group-node')
                .data(groups)
                .enter()
                .append('g')
                .attr('class', 'adn-group-node')
                .attr('transform', d => `translate(${d.position.x}, ${d.position.y})`);
            
            // Group background circles
            groupNodes.append('circle')
                .attr('r', 80)
                .attr('fill', d => d.color)
                .attr('opacity', 0.2)
                .attr('stroke', d => d.color)
                .attr('stroke-width', 2);
            
            // Group icons
            groupNodes.append('text')
                .attr('text-anchor', 'middle')
                .attr('dy', '-20px')
                .attr('font-size', '24px')
                .text(d => d.icon);
            
            // Group names
            groupNodes.append('text')
                .attr('text-anchor', 'middle')
                .attr('dy', '10px')
                .attr('font-size', '12px')
                .attr('font-weight', 'bold')
                .text(d => d.name);
            
            // Feature count
            groupNodes.append('text')
                .attr('text-anchor', 'middle')
                .attr('dy', '25px')
                .attr('font-size', '10px')
                .attr('fill', '#666')
                .text(d => `${d.featureCount} features`);
        },

        /**
         * Render features
         */
        renderFeatures: function(featuresLayer) {
            const features = [];
            
            // Collect all features with positions
            for (const [groupId, group] of Object.entries(this.state.featureMap)) {
                const groupPosition = this.state.componentGroups[groupId]?.position || { x: 200, y: 200 };
                const groupFeatures = Object.entries(group.features || {});
                
                groupFeatures.forEach(([featureId, feature], index) => {
                    const angle = (index / groupFeatures.length) * 2 * Math.PI;
                    const radius = 60;
                    
                    features.push({
                        id: featureId,
                        groupId: groupId,
                        name: feature.name,
                        description: feature.description,
                        critical: feature.critical,
                        status: 'pending',
                        position: {
                            x: groupPosition.x + Math.cos(angle) * radius,
                            y: groupPosition.y + Math.sin(angle) * radius
                        }
                    });
                });
            }
            
            const featureNodes = featuresLayer.selectAll('.adn-feature-node')
                .data(features)
                .enter()
                .append('g')
                .attr('class', 'adn-feature-node')
                .attr('data-feature-id', d => d.id)
                .attr('transform', d => `translate(${d.position.x}, ${d.position.y})`)
                .style('cursor', 'pointer');
            
            // Feature circles
            featureNodes.append('circle')
                .attr('r', d => d.critical ? 12 : 8)
                .attr('fill', '#6c757d') // Default pending color
                .attr('stroke', '#fff')
                .attr('stroke-width', 2)
                .attr('class', 'adn-feature-circle');
            
            // Feature status indicators
            featureNodes.append('circle')
                .attr('r', 4)
                .attr('cx', 8)
                .attr('cy', -8)
                .attr('fill', '#6c757d')
                .attr('class', 'adn-status-indicator')
                .style('opacity', 0);
        },

        /**
         * Render connections
         */
        renderConnections: function(connectionsLayer) {
            // This would render dependency connections between features
            // For now, we'll keep it simple
        },

        /**
         * Render status summary
         */
        renderStatusSummary: function() {
            let html = `
                <div class="adn-status-item">
                    <span class="adn-status-label">Total Features:</span>
                    <span class="adn-status-value" id="adn-total-features">0</span>
                </div>
                <div class="adn-status-item">
                    <span class="adn-status-label">Tested:</span>
                    <span class="adn-status-value" id="adn-tested-features">0</span>
                </div>
                <div class="adn-status-item">
                    <span class="adn-status-label">Passed:</span>
                    <span class="adn-status-value adn-status-pass" id="adn-passed-features">0</span>
                </div>
                <div class="adn-status-item">
                    <span class="adn-status-label">Failed:</span>
                    <span class="adn-status-value adn-status-fail" id="adn-failed-features">0</span>
                </div>
                <div class="adn-status-item">
                    <span class="adn-status-label">Critical:</span>
                    <span class="adn-status-value adn-status-critical" id="adn-critical-features">0</span>
                </div>
            `;
            
            this.elements.statusSummary.html(html);
        },

        /**
         * Start comprehensive test
         */
        startComprehensiveTest: function() {
            if (this.state.isRunning) {
                return;
            }
            
            const selectedGroups = $('.adn-group-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedGroups.length === 0) {
                alert('Please select at least one component group to test.');
                return;
            }
            
            this.state.isRunning = true;
            this.elements.startBtn.prop('disabled', true);
            this.elements.stopBtn.prop('disabled', false);
            
            // Reset visual indicators
            this.resetVisualIndicators();
            
            // Start the test
            $.post(adnMapData.ajaxUrl, {
                action: 'adn_run_comprehensive_test',
                nonce: adnMapData.nonce,
                include_groups: selectedGroups,
                background: false
            }, (response) => {
                if (response.success) {
                    this.handleTestResults(response.data);
                } else {
                    console.error('Test failed:', response.data.message);
                    this.stopComprehensiveTest();
                }
            });
            
            // Start progress monitoring
            this.startProgressMonitoring();
        },

        /**
         * Stop comprehensive test
         */
        stopComprehensiveTest: function() {
            this.state.isRunning = false;
            this.elements.startBtn.prop('disabled', false);
            this.elements.stopBtn.prop('disabled', true);
            
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
            }
        },

        /**
         * Reset test view
         */
        resetTestView: function() {
            this.resetVisualIndicators();
            this.updateProgress(0, 'Ready to start testing');
            this.renderStatusSummary();
        },

        /**
         * Reset visual indicators
         */
        resetVisualIndicators: function() {
            $('.adn-feature-circle').attr('fill', '#6c757d');
            $('.adn-status-indicator').style('opacity', 0);
            $('.adn-group-node circle').attr('stroke', d => d.color);
        },

        /**
         * Start progress monitoring
         */
        startProgressMonitoring: function() {
            this.progressInterval = setInterval(() => {
                if (!this.state.isRunning) {
                    clearInterval(this.progressInterval);
                    return;
                }
                
                // Poll for progress updates
                $.post(adnMapData.ajaxUrl, {
                    action: 'adn_get_test_progress',
                    nonce: adnMapData.nonce
                }, (response) => {
                    if (response.success) {
                        this.updateProgressDisplay(response.data);
                    }
                });
            }, this.config.updateInterval);
        },

        /**
         * Update progress display
         */
        updateProgressDisplay: function(progressData) {
            if (!progressData) return;
            
            const percentage = progressData.progress_percentage || 0;
            const message = `Testing ${progressData.features_tested}/${progressData.total_features} features`;
            
            this.updateProgress(percentage, message);
            this.updateStatusCounts(progressData);
        },

        /**
         * Update progress bar
         */
        updateProgress: function(percentage, message) {
            this.elements.progressBar.css('width', percentage + '%');
            this.elements.progressText.text(message);
        },

        /**
         * Update status counts
         */
        updateStatusCounts: function(data) {
            $('#adn-total-features').text(data.total_features || 0);
            $('#adn-tested-features').text(data.features_tested || 0);
            $('#adn-passed-features').text(data.passed_features || 0);
            $('#adn-failed-features').text(data.failed_features || 0);
            $('#adn-critical-features').text(data.critical_features || 0);
        },

        /**
         * Handle test results
         */
        handleTestResults: function(results) {
            this.state.testResults = results;
            this.stopComprehensiveTest();
            
            // Update visual indicators based on results
            this.updateVisualIndicatorsFromResults(results);
            
            // Show completion message
            this.updateProgress(100, `Test completed: ${results.overall_status}`);
        },

        /**
         * Update visual indicators from results
         */
        updateVisualIndicatorsFromResults: function(results) {
            if (!results.group_results) return;
            
            results.group_results.forEach(groupResult => {
                if (groupResult.feature_results) {
                    groupResult.feature_results.forEach(featureResult => {
                        this.updateFeatureVisual(featureResult.feature_id, featureResult.status);
                    });
                }
            });
        },

        /**
         * Update feature visual indicator
         */
        updateFeatureVisual: function(featureId, status) {
            const colors = {
                'pass': '#28a745',
                'fail': '#dc3545',
                'warning': '#ffc107',
                'critical': '#e74c3c',
                'running': '#007bff',
                'pending': '#6c757d'
            };
            
            const color = colors[status] || colors.pending;
            
            $(`.adn-feature-node[data-feature-id="${featureId}"] .adn-feature-circle`)
                .attr('fill', color)
                .transition()
                .duration(this.config.animationDuration);
            
            $(`.adn-feature-node[data-feature-id="${featureId}"] .adn-status-indicator`)
                .attr('fill', color)
                .style('opacity', 1)
                .transition()
                .duration(this.config.animationDuration);
        },

        /**
         * Setup real-time updates
         */
        setupRealTimeUpdates: function() {
            // Listen for WordPress actions/hooks for real-time updates
            $(document).on('adn_visual_update', (event, data) => {
                this.updateFeatureVisual(data.component_id, data.status);
            });
            
            $(document).on('adn_test_progress_update', (event, data) => {
                this.updateProgressDisplay(data);
            });
        },

        /**
         * Show feature details
         */
        showFeatureDetails: function(event) {
            const featureId = $(event.currentTarget).data('feature-id');
            // Implementation for showing detailed feature information
        },

        /**
         * Show feature tooltip
         */
        showFeatureTooltip: function(event) {
            const featureId = $(event.currentTarget).data('feature-id');
            // Implementation for showing feature tooltip
        },

        /**
         * Hide feature tooltip
         */
        hideFeatureTooltip: function(event) {
            // Implementation for hiding feature tooltip
        },

        /**
         * Update group selection
         */
        updateGroupSelection: function(event) {
            // Implementation for updating group selection
        },

        /**
         * Test single group
         */
        testSingleGroup: function(event) {
            const groupId = $(event.currentTarget).data('group');
            // Implementation for testing a single group
        },

        /**
         * Close test modal
         */
        closeTestModal: function() {
            // Implementation for closing modal dialogs
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof adnMapData !== 'undefined') {
            ADNComprehensiveTesting.init();
        }
    });

})(jQuery);