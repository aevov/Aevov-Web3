<?php

namespace ADN\Visualization;

/**
 * Architecture Map
 * 
 * Integrates with system-architecture-map.html to provide visual
 * component mapping with click handlers for testing and real-time
 * status updates.
 */
class ArchitectureMap {
    
    private $map_file_path;
    private $component_statuses = [];
    private $test_results = [];
    
    public function __construct() {
        // Try multiple possible locations for the architecture map
        $possible_paths = [
            ABSPATH . 'system-architecture-map.html',
            dirname(ABSPATH) . '/system-architecture-map.html',
            ADN_PLUGIN_DIR . '../system-architecture-map.html',
            ADN_PLUGIN_DIR . '../../system-architecture-map.html'
        ];
        
        $this->map_file_path = null;
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $this->map_file_path = $path;
                break;
            }
        }
        
        // If no file found, we'll use the fallback
        if (!$this->map_file_path) {
            error_log('ADN DEBUG: system-architecture-map.html not found in any expected location');
        } else {
            error_log('ADN DEBUG: Found architecture map at: ' . $this->map_file_path);
        }
        
        $this->load_component_statuses();
    }
    
    /**
     * Initialize architecture map integration
     */
    public function init() {
        add_action('wp_ajax_adn_get_component_status', [$this, 'ajax_get_component_status']);
        add_action('wp_ajax_adn_test_component', [$this, 'ajax_test_component']);
        add_action('wp_ajax_adn_get_map_data', [$this, 'ajax_get_map_data']);
        add_action('wp_ajax_adn_update_component_status', [$this, 'ajax_update_component_status']);
        // REMOVED: adn_run_comprehensive_test - handled by DiagnosticAdmin to avoid conflicts
        add_action('wp_ajax_adn_get_comprehensive_test_data', [$this, 'ajax_get_comprehensive_test_data']);
        
        // Enqueue scripts and styles for map integration
        add_action('admin_enqueue_scripts', [$this, 'enqueue_map_assets']);
    }
    
    /**
     * Enqueue map assets
     */
    public function enqueue_map_assets($hook) {
        if (strpos($hook, 'adn-architecture-map') === false) {
            return;
        }
        
        // D3.js for visualization
        wp_enqueue_script('d3js', 'https://d3js.org/d3.v7.min.js', [], '7.0.0', true);
        
        // Custom map integration script
        wp_enqueue_script(
            'adn-architecture-map',
            ADN_PLUGIN_URL . 'assets/js/architecture-map.js',
            ['jquery', 'd3js'],
            ADN_VERSION,
            true
        );
        
        // Map styles
        wp_enqueue_style(
            'adn-architecture-map',
            ADN_PLUGIN_URL . 'assets/css/architecture-map.css',
            [],
            ADN_VERSION
        );
        
        // Localize script with AJAX data
        wp_localize_script('adn-architecture-map', 'adnMapData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adn_map_nonce'),
            'components' => $this->get_all_components(),
            'statuses' => $this->component_statuses
        ]);
    }
    
    /**
     * Get enhanced map HTML with diagnostic integration
     */
    public function get_enhanced_map_html() {
        $original_html = $this->get_original_map_html();
        
        if (!$original_html) {
            return $this->generate_fallback_map();
        }
        
        // Enhance the original HTML with diagnostic features
        $enhanced_html = $this->enhance_map_html($original_html);
        
        return $enhanced_html;
    }
    
    /**
     * Get original map HTML
     */
    private function get_original_map_html() {
        if (!file_exists($this->map_file_path)) {
            return false;
        }
        
        return file_get_contents($this->map_file_path);
    }
    
    /**
     * Enhance map HTML with diagnostic features
     */
    private function enhance_map_html($html) {
        // Add diagnostic overlay container
        $diagnostic_overlay = '
        <div id="adn-diagnostic-overlay" class="adn-overlay hidden">
            <div class="adn-overlay-content">
                <div class="adn-overlay-header">
                    <h3 id="adn-component-title">Component Diagnostics</h3>
                    <button id="adn-close-overlay" class="adn-close-btn">&times;</button>
                </div>
                <div class="adn-overlay-body">
                    <div id="adn-component-status" class="adn-status-section">
                        <h4>Current Status</h4>
                        <div id="adn-status-indicator" class="adn-status-indicator"></div>
                        <div id="adn-status-message" class="adn-status-message"></div>
                    </div>
                    <div id="adn-component-tests" class="adn-tests-section">
                        <h4>Test Results</h4>
                        <div id="adn-test-results" class="adn-test-results"></div>
                    </div>
                    <div id="adn-component-actions" class="adn-actions-section">
                        <h4>Actions</h4>
                        <button id="adn-run-test" class="adn-btn adn-btn-primary">Run Test</button>
                        <button id="adn-auto-fix" class="adn-btn adn-btn-secondary">Auto Fix</button>
                        <button id="adn-view-logs" class="adn-btn adn-btn-tertiary">View Logs</button>
                    </div>
                    <div id="adn-component-recommendations" class="adn-recommendations-section">
                        <h4>Recommendations</h4>
                        <div id="adn-recommendations-list" class="adn-recommendations-list"></div>
                    </div>
                </div>
            </div>
        </div>';
        
        // Add real-time status indicators
        $status_indicators = '
        <div id="adn-status-bar" class="adn-status-bar">
            <div class="adn-status-item">
                <span class="adn-status-label">System Health:</span>
                <span id="adn-system-health" class="adn-status-value">Loading...</span>
            </div>
            <div class="adn-status-item">
                <span class="adn-status-label">Active Tests:</span>
                <span id="adn-active-tests" class="adn-status-value">0</span>
            </div>
            <div class="adn-status-item">
                <span class="adn-status-label">Last Update:</span>
                <span id="adn-last-update" class="adn-status-value">Never</span>
            </div>
        </div>';
        
        // Add diagnostic controls
        $diagnostic_controls = '
        <div id="adn-diagnostic-controls" class="adn-diagnostic-controls">
            <div class="adn-control-group">
                <label for="adn-auto-refresh">Auto Refresh:</label>
                <input type="checkbox" id="adn-auto-refresh" checked>
                <select id="adn-refresh-interval">
                    <option value="5000">5 seconds</option>
                    <option value="10000" selected>10 seconds</option>
                    <option value="30000">30 seconds</option>
                    <option value="60000">1 minute</option>
                </select>
            </div>
            <div class="adn-control-group">
                <button id="adn-test-all" class="adn-btn adn-btn-primary">Test All Components</button>
                <button id="adn-refresh-map" class="adn-btn adn-btn-secondary">Refresh Map</button>
                <button id="adn-export-results" class="adn-btn adn-btn-tertiary">Export Results</button>
            </div>
        </div>';
        
        // Inject enhancements into the HTML
        $enhanced_html = str_replace('</body>', $diagnostic_overlay . $status_indicators . $diagnostic_controls . '</body>', $html);
        
        // Add diagnostic CSS and JS integration
        $diagnostic_integration = '
        <style>
            /* Diagnostic overlay styles */
            .adn-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .adn-overlay.hidden {
                display: none;
            }
            
            .adn-overlay-content {
                background: white;
                border-radius: 8px;
                width: 90%;
                max-width: 800px;
                max-height: 90%;
                overflow-y: auto;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            }
            
            .adn-overlay-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px;
                border-bottom: 1px solid #eee;
                background: #f8f9fa;
                border-radius: 8px 8px 0 0;
            }
            
            .adn-overlay-header h3 {
                margin: 0;
                color: #333;
            }
            
            .adn-close-btn {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #666;
                padding: 0;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .adn-close-btn:hover {
                color: #333;
            }
            
            .adn-overlay-body {
                padding: 20px;
            }
            
            .adn-status-section,
            .adn-tests-section,
            .adn-actions-section,
            .adn-recommendations-section {
                margin-bottom: 20px;
            }
            
            .adn-status-section h4,
            .adn-tests-section h4,
            .adn-actions-section h4,
            .adn-recommendations-section h4 {
                margin: 0 0 10px 0;
                color: #333;
                font-size: 16px;
            }
            
            .adn-status-indicator {
                display: inline-block;
                width: 12px;
                height: 12px;
                border-radius: 50%;
                margin-right: 8px;
            }
            
            .adn-status-indicator.pass { background: #28a745; }
            .adn-status-indicator.fail { background: #dc3545; }
            .adn-status-indicator.warning { background: #ffc107; }
            .adn-status-indicator.unknown { background: #6c757d; }
            
            .adn-btn {
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                margin-right: 8px;
                margin-bottom: 8px;
                font-size: 14px;
            }
            
            .adn-btn-primary {
                background: #007cba;
                color: white;
            }
            
            .adn-btn-secondary {
                background: #6c757d;
                color: white;
            }
            
            .adn-btn-tertiary {
                background: #e9ecef;
                color: #333;
            }
            
            .adn-btn:hover {
                opacity: 0.9;
            }
            
            /* Status bar styles */
            .adn-status-bar {
                position: fixed;
                top: 32px;
                right: 20px;
                background: white;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 10px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                z-index: 1000;
                display: flex;
                gap: 20px;
                font-size: 12px;
            }
            
            .adn-status-item {
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            
            .adn-status-label {
                font-weight: bold;
                color: #666;
                margin-bottom: 2px;
            }
            
            .adn-status-value {
                color: #333;
            }
            
            /* Diagnostic controls styles */
            .adn-diagnostic-controls {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: white;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 15px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                z-index: 1000;
            }
            
            .adn-control-group {
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 12px;
            }
            
            .adn-control-group:last-child {
                margin-bottom: 0;
            }
            
            .adn-control-group label {
                font-weight: bold;
                color: #666;
            }
            
            .adn-control-group input,
            .adn-control-group select {
                font-size: 12px;
                padding: 2px 4px;
            }
            
            /* Component status indicators on map */
            .component-node {
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .component-node:hover {
                stroke-width: 3px;
                filter: brightness(1.1);
            }
            
            .component-node.status-pass {
                fill: #28a745;
                stroke: #1e7e34;
            }
            
            .component-node.status-fail {
                fill: #dc3545;
                stroke: #c82333;
            }
            
            .component-node.status-warning {
                fill: #ffc107;
                stroke: #e0a800;
            }
            
            .component-node.status-unknown {
                fill: #6c757d;
                stroke: #545b62;
            }
            
            .component-node.status-testing {
                fill: #17a2b8;
                stroke: #138496;
                animation: pulse 1s infinite;
            }
            
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.5; }
                100% { opacity: 1; }
            }
        </style>
        
        <script>
            // Enhanced component click handlers
            document.addEventListener("DOMContentLoaded", function() {
                // Initialize diagnostic map integration
                if (typeof initializeDiagnosticMap === "function") {
                    initializeDiagnosticMap();
                }
                
                // Add click handlers to existing components
                d3.selectAll(".component-node").on("click", function(event, d) {
                    const componentId = d3.select(this).attr("data-component-id") || d.id;
                    showComponentDiagnostics(componentId);
                });
                
                // Auto-refresh functionality
                let autoRefreshInterval;
                const autoRefreshCheckbox = document.getElementById("adn-auto-refresh");
                const refreshIntervalSelect = document.getElementById("adn-refresh-interval");
                
                function startAutoRefresh() {
                    if (autoRefreshInterval) {
                        clearInterval(autoRefreshInterval);
                    }
                    
                    const interval = parseInt(refreshIntervalSelect.value);
                    autoRefreshInterval = setInterval(updateComponentStatuses, interval);
                }
                
                if (autoRefreshCheckbox) {
                    autoRefreshCheckbox.addEventListener("change", function() {
                        if (this.checked) {
                            startAutoRefresh();
                        } else {
                            clearInterval(autoRefreshInterval);
                        }
                    });
                }
                
                if (refreshIntervalSelect) {
                    refreshIntervalSelect.addEventListener("change", function() {
                        if (autoRefreshCheckbox.checked) {
                            startAutoRefresh();
                        }
                    });
                }
                
                // Start auto-refresh if enabled
                if (autoRefreshCheckbox && autoRefreshCheckbox.checked) {
                    startAutoRefresh();
                }
                
                // Initial status update
                updateComponentStatuses();
            });
            
            function showComponentDiagnostics(componentId) {
                const overlay = document.getElementById("adn-diagnostic-overlay");
                const title = document.getElementById("adn-component-title");
                
                if (overlay && title) {
                    title.textContent = "Diagnostics: " + componentId;
                    overlay.classList.remove("hidden");
                    
                    // Load component data
                    loadComponentDiagnostics(componentId);
                }
            }
            
            function loadComponentDiagnostics(componentId) {
                // Show loading state
                const statusIndicator = document.getElementById("adn-status-indicator");
                const statusMessage = document.getElementById("adn-status-message");
                const testResults = document.getElementById("adn-test-results");
                
                if (statusIndicator) statusIndicator.className = "adn-status-indicator unknown";
                if (statusMessage) statusMessage.textContent = "Loading...";
                if (testResults) testResults.innerHTML = "<p>Loading test results...</p>";
                
                // AJAX call to get component status
                jQuery.post(adnMapData.ajaxUrl, {
                    action: "adn_get_component_status",
                    component_id: componentId,
                    nonce: adnMapData.nonce
                }, function(response) {
                    if (response.success) {
                        updateDiagnosticDisplay(response.data);
                    } else {
                        if (statusMessage) statusMessage.textContent = "Error loading diagnostics";
                    }
                });
            }
            
            function updateDiagnosticDisplay(data) {
                const statusIndicator = document.getElementById("adn-status-indicator");
                const statusMessage = document.getElementById("adn-status-message");
                const testResults = document.getElementById("adn-test-results");
                const recommendationsList = document.getElementById("adn-recommendations-list");
                
                if (statusIndicator) {
                    statusIndicator.className = "adn-status-indicator " + data.overall_status;
                }
                
                if (statusMessage) {
                    statusMessage.textContent = data.status_message || "Status unknown";
                }
                
                if (testResults && data.tests) {
                    let html = "<div class=\\"adn-test-grid\\">";
                    for (const [testName, testResult] of Object.entries(data.tests)) {
                        html += `
                            <div class="adn-test-item">
                                <div class="adn-test-name">${testName}</div>
                                <div class="adn-test-status adn-status-${testResult.status}">${testResult.status}</div>
                                <div class="adn-test-message">${testResult.message}</div>
                            </div>
                        `;
                    }
                    html += "</div>";
                    testResults.innerHTML = html;
                }
                
                if (recommendationsList && data.recommendations) {
                    let html = "";
                    data.recommendations.forEach(rec => {
                        html += `<div class="adn-recommendation">${rec}</div>`;
                    });
                    recommendationsList.innerHTML = html || "<p>No recommendations available</p>";
                }
            }
            
            function updateComponentStatuses() {
                // Update system health indicator
                jQuery.post(adnMapData.ajaxUrl, {
                    action: "adn_get_map_data",
                    nonce: adnMapData.nonce
                }, function(response) {
                    if (response.success) {
                        updateMapStatuses(response.data);
                        updateStatusBar(response.data);
                    }
                });
            }
            
            function updateMapStatuses(data) {
                // Update component nodes on the map
                if (data.components) {
                    data.components.forEach(component => {
                        const node = d3.select(`[data-component-id="${component.id}"]`);
                        if (!node.empty()) {
                            node.attr("class", `component-node status-${component.status}`);
                        }
                    });
                }
            }
            
            function updateStatusBar(data) {
                const systemHealth = document.getElementById("adn-system-health");
                const activeTests = document.getElementById("adn-active-tests");
                const lastUpdate = document.getElementById("adn-last-update");
                
                if (systemHealth) {
                    systemHealth.textContent = data.system_health || "Unknown";
                    systemHealth.className = "adn-status-value status-" + (data.system_health_status || "unknown");
                }
                
                if (activeTests) {
                    activeTests.textContent = data.active_tests || "0";
                }
                
                if (lastUpdate) {
                    lastUpdate.textContent = new Date().toLocaleTimeString();
                }
            }
            
            // Close overlay handler
            document.addEventListener("click", function(e) {
                if (e.target.id === "adn-close-overlay" || e.target.id === "adn-diagnostic-overlay") {
                    document.getElementById("adn-diagnostic-overlay").classList.add("hidden");
                }
            });
            
            // Action button handlers
            document.addEventListener("click", function(e) {
                const componentTitle = document.getElementById("adn-component-title");
                const componentId = componentTitle ? componentTitle.textContent.replace("Diagnostics: ", "") : "";
                
                if (e.target.id === "adn-run-test" && componentId) {
                    runComponentTest(componentId);
                } else if (e.target.id === "adn-auto-fix" && componentId) {
                    runAutoFix(componentId);
                } else if (e.target.id === "adn-test-all") {
                    runAllTests();
                } else if (e.target.id === "adn-refresh-map") {
                    updateComponentStatuses();
                }
            });
            
            function runComponentTest(componentId) {
                // Update component to testing state
                const node = d3.select(`[data-component-id="${componentId}"]`);
                if (!node.empty()) {
                    node.attr("class", "component-node status-testing");
                }
                
                jQuery.post(adnMapData.ajaxUrl, {
                    action: "adn_test_component",
                    component_id: componentId,
                    nonce: adnMapData.nonce
                }, function(response) {
                    if (response.success) {
                        updateDiagnosticDisplay(response.data);
                        // Update map status
                        if (!node.empty()) {
                            node.attr("class", `component-node status-${response.data.overall_status}`);
                        }
                    }
                });
            }
            
            function runAutoFix(componentId) {
                if (confirm("Are you sure you want to run auto-fix for this component? This may modify files.")) {
                    jQuery.post(adnMapData.ajaxUrl, {
                        action: "adn_auto_fix_component",
                        component_id: componentId,
                        nonce: adnMapData.nonce
                    }, function(response) {
                        if (response.success) {
                            alert("Auto-fix completed: " + response.data.message);
                            loadComponentDiagnostics(componentId);
                        } else {
                            alert("Auto-fix failed: " + response.data.message);
                        }
                    });
                }
            }
            
            function runAllTests() {
                if (confirm("Run tests for all components? This may take a while.")) {
                    // Update all nodes to testing state
                    d3.selectAll(".component-node").attr("class", "component-node status-testing");
                    
                    jQuery.post(adnMapData.ajaxUrl, {
                        action: "adn_test_all_components",
                        nonce: adnMapData.nonce
                    }, function(response) {
                        if (response.success) {
                            updateMapStatuses(response.data);
                            alert("All tests completed!");
                        }
                    });
                }
            }
        </script>';
        
        $enhanced_html = str_replace('</head>', $diagnostic_integration . '</head>', $enhanced_html);
        
        return $enhanced_html;
    }
    
    /**
     * Generate fallback map if original doesn't exist
     */
    private function generate_fallback_map() {
        $components = $this->get_all_components();
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Aevov System Architecture Map</title>
            <script src="https://d3js.org/d3.v7.min.js"></script>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                #map-container { width: 100%; height: 600px; border: 1px solid #ddd; }
                .component-node { cursor: pointer; }
                .component-label { font-size: 12px; text-anchor: middle; }
            </style>
        </head>
        <body>
            <h1>Aevov System Architecture Map</h1>
            <div id="map-container"></div>
            
            <script>
                const components = ' . json_encode($components) . ';
                
                const svg = d3.select("#map-container")
                    .append("svg")
                    .attr("width", "100%")
                    .attr("height", "100%");
                
                const width = 800;
                const height = 600;
                
                // Create force simulation
                const simulation = d3.forceSimulation(components)
                    .force("link", d3.forceLink().id(d => d.id))
                    .force("charge", d3.forceManyBody().strength(-300))
                    .force("center", d3.forceCenter(width / 2, height / 2));
                
                // Create nodes
                const node = svg.selectAll(".component-node")
                    .data(components)
                    .enter().append("circle")
                    .attr("class", "component-node")
                    .attr("data-component-id", d => d.id)
                    .attr("r", 20)
                    .attr("fill", d => d.color || "#69b3a2");
                
                // Create labels
                const label = svg.selectAll(".component-label")
                    .data(components)
                    .enter().append("text")
                    .attr("class", "component-label")
                    .text(d => d.name);
                
                // Update positions
                simulation.on("tick", () => {
                    node
                        .attr("cx", d => d.x)
                        .attr("cy", d => d.y);
                    
                    label
                        .attr("x", d => d.x)
                        .attr("y", d => d.y + 30);
                });
            </script>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * AJAX: Get component status
     */
    public function ajax_get_component_status() {
        check_ajax_referer('adn_map_nonce', 'nonce');
        
        $component_id = sanitize_text_field($_POST['component_id'] ?? '');
        
        if (empty($component_id)) {
            wp_send_json_error(['message' => 'Component ID required']);
        }
        
        // Get component test results
        $tester = new \ADN\Testing\ComponentTester();
        $component = $this->get_component_by_id($component_id);
        
        if (!$component) {
            wp_send_json_error(['message' => 'Component not found']);
        }
        
        $results = $tester->test_component($component);
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX: Test component
     */
    public function ajax_test_component() {
        check_ajax_referer('adn_map_nonce', 'nonce');
        
        $component_id = sanitize_text_field($_POST['component_id'] ?? '');
        
        if (empty($component_id)) {
            wp_send_json_error(['message' => 'Component ID required']);
        }
        
        $component = $this->get_component_by_id($component_id);
        
        if (!$component) {
            wp_send_json_error(['message' => 'Component not found']);
        }
        
        // Run comprehensive test
        $tester = new \ADN\Testing\ComponentTester();
        $results = $tester->test_component($component);
        
        // Update component status
        $this->update_component_status($component_id, $results['overall_status']);
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX: Get map data
     */
    public function ajax_get_map_data() {
        check_ajax_referer('adn_map_nonce', 'nonce');
        
        $components = $this->get_all_components();
        $system_health = $this->calculate_system_health();
        
        // Add current status to each component
        foreach ($components as &$component) {
            $component['status'] = $this->component_statuses[$component['id']] ?? 'unknown';
        }
        
        wp_send_json_success([
            'components' => $components,
            'system_health' => $system_health['status'],
            'system_health_status' => $system_health['level'],
            'active_tests' => $this->get_active_tests_count(),
            'timestamp' => current_time('mysql')
        ]);
    }
    
    /**
     * AJAX: Update component status
     */
    public function ajax_update_component_status() {
        check_ajax_referer('adn_map_nonce', 'nonce');
        
        $component_id = sanitize_text_field($_POST['component_id'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        if (empty($component_id) || empty($status)) {
            wp_send_json_error(['message' => 'Component ID and status required']);
        }
        
        $this->update_component_status($component_id, $status);
        
        wp_send_json_success(['message' => 'Status updated']);
    }
    
    /**
     * Get all components for mapping
     */
    private function get_all_components() {
        $diagnostic_network = \ADN\Core\DiagnosticNetwork::instance();
        return $diagnostic_network->get_system_components();
    }
    
    /**
     * Get component by ID
     */
    private function get_component_by_id($component_id) {
        $components = $this->get_all_components();
        
        foreach ($components as $component) {
            if ($component['id'] === $component_id) {
                return $component;
            }
        }
        
        return null;
    }
    
    /**
     * Load component statuses from database
     */
    private function load_component_statuses() {
        $this->component_statuses = \get_option('adn_component_statuses', []);
    }
    
    /**
     * Update component status
     */
    private function update_component_status($component_id, $status) {
        $this->component_statuses[$component_id] = $status;
        \update_option('adn_component_statuses', $this->component_statuses);
    }
    
    /**
     * Calculate overall system health
     */
    private function calculate_system_health() {
        $statuses = array_values($this->component_statuses);
        
        if (empty($statuses)) {
            return ['status' => 'Unknown', 'level' => 'unknown'];
        }
        
        $critical_count = count(array_filter($statuses, function($s) { return $s === 'critical'; }));
        $fail_count = count(array_filter($statuses, function($s) { return $s === 'fail'; }));
        $warning_count = count(array_filter($statuses, function($s) { return $s === 'warning'; }));
        $pass_count = count(array_filter($statuses, function($s) { return $s === 'pass'; }));
        
        $total = count($statuses);
        
        if ($critical_count > 0) {
            return ['status' => 'Critical', 'level' => 'critical'];
        } elseif ($fail_count > $total * 0.3) {
            return ['status' => 'Poor', 'level' => 'fail'];
        } elseif ($warning_count > $total * 0.5) {
            return ['status' => 'Fair', 'level' => 'warning'];
        } elseif ($pass_count > $total * 0.8) {
            return ['status' => 'Excellent', 'level' => 'pass'];
        } else {
            return ['status' => 'Good', 'level' => 'pass'];
        }
    }
    
    /**
     * Get active tests count
     */
    private function get_active_tests_count() {
        // Get count of currently running tests
        $active_tests = get_transient('adn_active_tests');
        return is_array($active_tests) ? count($active_tests) : 0;
    }
    
    /**
     * Get map file path
     */
    public function get_map_file_path() {
        return $this->map_file_path;
    }
    
    /**
     * Set map file path
     */
    public function set_map_file_path($path) {
        $this->map_file_path = $path;
    }
    
    /**
     * Get component statuses
     */
    public function get_component_statuses() {
        return $this->component_statuses;
    }
    
    /**
     * Clear all component statuses
     */
    public function clear_component_statuses() {
        $this->component_statuses = [];
        delete_option('adn_component_statuses');
    }
    
    // REMOVED: ajax_run_comprehensive_test method - handled by DiagnosticAdmin to avoid conflicts
    
    /**
     * AJAX: Get comprehensive test data
     */
    public function ajax_get_comprehensive_test_data() {
        check_ajax_referer('adn_map_nonce', 'nonce');
        
        $comprehensive_tester = new \ADN\Testing\ComprehensiveFeatureTester();
        
        $data = [
            'feature_map' => $comprehensive_tester->get_feature_map(),
            'component_groups' => $comprehensive_tester->get_component_groups(),
            'visual_indicators' => $comprehensive_tester->get_visual_indicators(),
            'latest_results' => $comprehensive_tester->get_latest_test_results()
        ];
        
        wp_send_json_success($data);
    }
}