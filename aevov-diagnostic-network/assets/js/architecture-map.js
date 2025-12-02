/**
 * Aevov Diagnostic Network - Architecture Map JavaScript
 * Handles interactive architecture visualization and component testing
 */

(function($) {
    'use strict';

    // Global Architecture Map object
    window.ADNArchitectureMap = {
        
        /**
         * Initialize architecture map
         */
        init: function() {
            this.bindEvents();
            this.loadArchitectureData();
            this.initializeVisualization();
            this.setupRealTimeUpdates();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Map controls
            $(document).on('click', '#adn-refresh-map', this.refreshMap);
            $(document).on('click', '#adn-reset-zoom', this.resetZoom);
            $(document).on('click', '#adn-toggle-labels', this.toggleLabels);
            $(document).on('change', '#adn-filter-status', this.filterByStatus);

            // Component interactions
            $(document).on('click', '.adn-component-node', this.handleComponentClick);
            $(document).on('mouseenter', '.adn-component-node', this.showComponentTooltip);
            $(document).on('mouseleave', '.adn-component-node', this.hideComponentTooltip);

            // Connection testing
            $(document).on('click', '.adn-connection-line', this.testConnection);
        },

        /**
         * Load architecture data from server
         */
        loadArchitectureData: function() {
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_get_architecture_data',
                nonce: adnAdmin.nonce
            }, (response) => {
                if (response.success) {
                    this.architectureData = response.data;
                    this.renderArchitecture();
                } else {
                    console.error('Failed to load architecture data:', response.data.message);
                }
            });
        },

        /**
         * Initialize D3.js visualization
         */
        initializeVisualization: function() {
            const container = d3.select('#adn-architecture-container');
            const width = container.node().getBoundingClientRect().width;
            const height = 600;

            // Create SVG
            this.svg = container.append('svg')
                .attr('width', width)
                .attr('height', height)
                .attr('class', 'adn-architecture-svg');

            // Create zoom behavior
            this.zoom = d3.zoom()
                .scaleExtent([0.1, 3])
                .on('zoom', (event) => {
                    this.svg.select('.adn-main-group')
                        .attr('transform', event.transform);
                });

            this.svg.call(this.zoom);

            // Create main group for all elements
            this.mainGroup = this.svg.append('g')
                .attr('class', 'adn-main-group');

            // Create groups for different layers
            this.connectionsGroup = this.mainGroup.append('g').attr('class', 'connections');
            this.componentsGroup = this.mainGroup.append('g').attr('class', 'components');
            this.labelsGroup = this.mainGroup.append('g').attr('class', 'labels');
        },

        /**
         * Render architecture visualization
         */
        renderArchitecture: function() {
            if (!this.architectureData) return;

            const components = this.architectureData.components;
            const connections = this.architectureData.connections;

            // Calculate positions using force simulation
            this.simulation = d3.forceSimulation(components)
                .force('link', d3.forceLink(connections).id(d => d.id).distance(150))
                .force('charge', d3.forceManyBody().strength(-300))
                .force('center', d3.forceCenter(400, 300))
                .force('collision', d3.forceCollide().radius(50));

            // Render connections
            this.renderConnections(connections);
            
            // Render components
            this.renderComponents(components);
            
            // Render labels
            this.renderLabels(components);

            // Start simulation
            this.simulation.on('tick', () => {
                this.updatePositions();
            });
        },

        /**
         * Render connection lines
         */
        renderConnections: function(connections) {
            const lines = this.connectionsGroup.selectAll('.adn-connection-line')
                .data(connections)
                .enter()
                .append('line')
                .attr('class', 'adn-connection-line')
                .attr('stroke', '#ccc')
                .attr('stroke-width', 2)
                .attr('data-source', d => d.source.id || d.source)
                .attr('data-target', d => d.target.id || d.target)
                .style('cursor', 'pointer');
        },

        /**
         * Render component nodes
         */
        renderComponents: function(components) {
            const nodes = this.componentsGroup.selectAll('.adn-component-node')
                .data(components)
                .enter()
                .append('g')
                .attr('class', 'adn-component-node')
                .attr('data-component-id', d => d.id)
                .style('cursor', 'pointer')
                .call(d3.drag()
                    .on('start', this.dragStarted.bind(this))
                    .on('drag', this.dragged.bind(this))
                    .on('end', this.dragEnded.bind(this)));

            // Add circles for components
            nodes.append('circle')
                .attr('r', 25)
                .attr('fill', d => this.getComponentColor(d.status))
                .attr('stroke', '#fff')
                .attr('stroke-width', 3);

            // Add icons
            nodes.append('text')
                .attr('text-anchor', 'middle')
                .attr('dy', '0.35em')
                .attr('font-family', 'dashicons')
                .attr('font-size', '20px')
                .attr('fill', '#fff')
                .text(d => this.getComponentIcon(d.type));

            // Add status indicators
            nodes.append('circle')
                .attr('class', 'adn-status-dot')
                .attr('r', 6)
                .attr('cx', 18)
                .attr('cy', -18)
                .attr('fill', d => this.getStatusColor(d.status))
                .attr('stroke', '#fff')
                .attr('stroke-width', 2);
        },

        /**
         * Render component labels
         */
        renderLabels: function(components) {
            const labels = this.labelsGroup.selectAll('.adn-component-label')
                .data(components)
                .enter()
                .append('text')
                .attr('class', 'adn-component-label')
                .attr('text-anchor', 'middle')
                .attr('dy', '45px')
                .attr('font-size', '12px')
                .attr('font-weight', 'bold')
                .attr('fill', '#333')
                .text(d => d.name);
        },

        /**
         * Update positions during simulation
         */
        updatePositions: function() {
            // Update connection lines
            this.connectionsGroup.selectAll('.adn-connection-line')
                .attr('x1', d => d.source.x)
                .attr('y1', d => d.source.y)
                .attr('x2', d => d.target.x)
                .attr('y2', d => d.target.y);

            // Update component nodes
            this.componentsGroup.selectAll('.adn-component-node')
                .attr('transform', d => `translate(${d.x},${d.y})`);

            // Update labels
            this.labelsGroup.selectAll('.adn-component-label')
                .attr('x', d => d.x)
                .attr('y', d => d.y);
        },

        /**
         * Get component color based on status
         */
        getComponentColor: function(status) {
            const colors = {
                'pass': '#28a745',
                'fail': '#dc3545',
                'warning': '#ffc107',
                'unknown': '#6c757d',
                'testing': '#007bff'
            };
            return colors[status] || colors.unknown;
        },

        /**
         * Get status indicator color
         */
        getStatusColor: function(status) {
            return this.getComponentColor(status);
        },

        /**
         * Get component icon based on type
         */
        getComponentIcon: function(type) {
            const icons = {
                'plugin': '\uf106', // dashicons-admin-plugins
                'database': '\uf472', // dashicons-database
                'api': '\uf103', // dashicons-admin-links
                'service': '\uf111', // dashicons-admin-settings
                'integration': '\uf504', // dashicons-networking
                'storage': '\uf322', // dashicons-portfolio
                'ai': '\uf319' // dashicons-lightbulb
            };
            return icons[type] || icons.service;
        },

        /**
         * Handle component click
         */
        handleComponentClick: function(event) {
            const componentId = $(this).data('component-id');
            ADNArchitectureMap.testComponent(componentId);
        },

        /**
         * Test individual component
         */
        testComponent: function(componentId) {
            // Update visual state
            const node = this.componentsGroup.select(`[data-component-id="${componentId}"]`);
            node.select('circle').attr('fill', this.getComponentColor('testing'));
            node.select('.adn-status-dot').attr('fill', this.getComponentColor('testing'));

            // Send test request
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_test_component',
                component_id: componentId,
                nonce: adnAdmin.nonce
            }, (response) => {
                if (response.success) {
                    const status = response.data.overall_status;
                    this.updateComponentStatus(componentId, status);
                    
                    // Show test results
                    this.showTestResults(componentId, response.data);
                } else {
                    this.updateComponentStatus(componentId, 'fail');
                    console.error('Component test failed:', response.data.message);
                }
            }).fail(() => {
                this.updateComponentStatus(componentId, 'fail');
                console.error('Component test failed due to network error');
            });
        },

        /**
         * Update component visual status
         */
        updateComponentStatus: function(componentId, status) {
            const node = this.componentsGroup.select(`[data-component-id="${componentId}"]`);
            node.select('circle').attr('fill', this.getComponentColor(status));
            node.select('.adn-status-dot').attr('fill', this.getStatusColor(status));
        },

        /**
         * Show component tooltip
         */
        showComponentTooltip: function(event) {
            const componentId = $(this).data('component-id');
            const component = ADNArchitectureMap.findComponent(componentId);
            
            if (!component) return;

            const tooltip = $('<div class="adn-tooltip">')
                .html(`
                    <strong>${component.name}</strong><br>
                    Type: ${component.type}<br>
                    Status: <span class="status-${component.status}">${component.status}</span><br>
                    Last tested: ${component.last_tested || 'Never'}
                `)
                .appendTo('body');

            const rect = this.getBoundingClientRect();
            tooltip.css({
                position: 'absolute',
                top: rect.top - tooltip.outerHeight() - 10,
                left: rect.left + (rect.width / 2) - (tooltip.outerWidth() / 2),
                zIndex: 10000
            });
        },

        /**
         * Hide component tooltip
         */
        hideComponentTooltip: function() {
            $('.adn-tooltip').remove();
        },

        /**
         * Find component by ID
         */
        findComponent: function(componentId) {
            if (!this.architectureData || !this.architectureData.components) return null;
            return this.architectureData.components.find(c => c.id === componentId);
        },

        /**
         * Test connection between components
         */
        testConnection: function(event) {
            const sourceId = $(this).data('source');
            const targetId = $(this).data('target');
            
            // Visual feedback
            $(this).attr('stroke', '#007bff').attr('stroke-width', 4);
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_test_connection',
                source_id: sourceId,
                target_id: targetId,
                nonce: adnAdmin.nonce
            }, (response) => {
                const color = response.success ? '#28a745' : '#dc3545';
                $(this).attr('stroke', color);
                
                setTimeout(() => {
                    $(this).attr('stroke', '#ccc').attr('stroke-width', 2);
                }, 2000);
            }).fail(() => {
                $(this).attr('stroke', '#dc3545');
                setTimeout(() => {
                    $(this).attr('stroke', '#ccc').attr('stroke-width', 2);
                }, 2000);
            });
        },

        /**
         * Show test results modal
         */
        showTestResults: function(componentId, results) {
            const component = this.findComponent(componentId);
            if (!component) return;

            let html = `<div class="adn-test-results">`;
            html += `<h4>Test Results for ${component.name}</h4>`;
            html += `<div class="adn-overall-status status-${results.overall_status}">`;
            html += `Overall Status: ${results.overall_status.toUpperCase()}`;
            html += `</div>`;

            if (results.tests) {
                html += `<div class="adn-individual-tests">`;
                html += `<h5>Individual Tests:</h5>`;
                for (const [testName, testResult] of Object.entries(results.tests)) {
                    html += `<div class="adn-test-item">`;
                    html += `<strong>${testName}:</strong> `;
                    html += `<span class="status-${testResult.status}">${testResult.status}</span>`;
                    if (testResult.message) {
                        html += `<p>${testResult.message}</p>`;
                    }
                    html += `</div>`;
                }
                html += `</div>`;
            }

            html += `</div>`;

            // Show in modal
            $('#adn-test-results-content').html(html);
            $('#adn-test-results-modal').show();
        },

        /**
         * Refresh architecture map
         */
        refreshMap: function(e) {
            e.preventDefault();
            ADNArchitectureMap.loadArchitectureData();
        },

        /**
         * Reset zoom level
         */
        resetZoom: function(e) {
            e.preventDefault();
            ADNArchitectureMap.svg.transition()
                .duration(750)
                .call(ADNArchitectureMap.zoom.transform, d3.zoomIdentity);
        },

        /**
         * Toggle component labels
         */
        toggleLabels: function(e) {
            e.preventDefault();
            const labels = ADNArchitectureMap.labelsGroup.selectAll('.adn-component-label');
            const isVisible = labels.style('display') !== 'none';
            labels.style('display', isVisible ? 'none' : 'block');
            $(this).text(isVisible ? 'Show Labels' : 'Hide Labels');
        },

        /**
         * Filter components by status
         */
        filterByStatus: function() {
            const selectedStatus = $(this).val();
            
            ADNArchitectureMap.componentsGroup.selectAll('.adn-component-node')
                .style('opacity', function() {
                    if (selectedStatus === 'all') return 1;
                    const componentId = d3.select(this).attr('data-component-id');
                    const component = ADNArchitectureMap.findComponent(componentId);
                    return component && component.status === selectedStatus ? 1 : 0.3;
                });

            ADNArchitectureMap.labelsGroup.selectAll('.adn-component-label')
                .style('opacity', function(d) {
                    if (selectedStatus === 'all') return 1;
                    return d.status === selectedStatus ? 1 : 0.3;
                });
        },

        /**
         * Setup real-time updates
         */
        setupRealTimeUpdates: function() {
            // Update component statuses every 30 seconds
            setInterval(() => {
                this.updateComponentStatuses();
            }, 30000);
        },

        /**
         * Update component statuses
         */
        updateComponentStatuses: function() {
            if (!this.architectureData || !this.architectureData.components) return;

            this.architectureData.components.forEach(component => {
                $.post(adnAdmin.ajaxUrl, {
                    action: 'adn_get_component_status',
                    component_id: component.id,
                    nonce: adnAdmin.nonce
                }, (response) => {
                    if (response.success && response.data.status !== component.status) {
                        component.status = response.data.status;
                        this.updateComponentStatus(component.id, response.data.status);
                    }
                });
            });
        },

        /**
         * Drag event handlers
         */
        dragStarted: function(event, d) {
            if (!event.active) this.simulation.alphaTarget(0.3).restart();
            d.fx = d.x;
            d.fy = d.y;
        },

        dragged: function(event, d) {
            d.fx = event.x;
            d.fy = event.y;
        },

        dragEnded: function(event, d) {
            if (!event.active) this.simulation.alphaTarget(0);
            d.fx = null;
            d.fy = null;
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#adn-architecture-container').length) {
            ADNArchitectureMap.init();
        }
    });

})(jQuery);