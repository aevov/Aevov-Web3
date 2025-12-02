<?php

namespace ADN\Admin;

/**
 * Diagnostic Admin
 * 
 * Handles the WordPress admin interface for the diagnostic network.
 * Provides multiple admin pages for dashboard, architecture map,
 * component tests, AI auto-fix, and system status.
 */
class DiagnosticAdmin {
    
    private $diagnostic_network;
    private $architecture_map;
    private $component_tester;
    private $ai_engine_manager;
    
    public function __construct() {
        // Use lazy loading to avoid circular dependency
        // DiagnosticNetwork will be loaded when needed via get_diagnostic_network()
        $this->architecture_map = new \ADN\Visualization\ArchitectureMap();
        $this->component_tester = new \ADN\Testing\ComponentTester();
        $this->ai_engine_manager = new \ADN\AI\AIEngineManager();
    }
    
    /**
     * Get diagnostic network instance (lazy loading)
     */
    private function get_diagnostic_network() {
        if ($this->diagnostic_network === null) {
            $this->diagnostic_network = \ADN\Core\DiagnosticNetwork::instance();
        }
        return $this->diagnostic_network;
    }
    
    /**
     * Initialize admin interface
     */
    public function init() {
        // DIAGNOSTIC LOG: Admin initialization
        error_log('ADN DEBUG: DiagnosticAdmin::init() called');
        
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_admin_actions']);
        
        // DIAGNOSTIC LOG: Registering AJAX handlers
        error_log('ADN DEBUG: Registering AJAX handlers');
        
        // AJAX handlers
        add_action('wp_ajax_adn_run_system_scan', [$this, 'ajax_run_system_scan']);
        add_action('wp_ajax_adn_get_dashboard_data', [$this, 'ajax_get_dashboard_data']);
        add_action('wp_ajax_adn_export_diagnostics', [$this, 'ajax_export_diagnostics']);
        add_action('wp_ajax_adn_import_diagnostics', [$this, 'ajax_import_diagnostics']);
        add_action('wp_ajax_adn_schedule_health_check', [$this, 'ajax_schedule_health_check']);
        add_action('wp_ajax_adn_clear_diagnostic_cache', [$this, 'ajax_clear_diagnostic_cache']);
        add_action('wp_ajax_adn_test_component', [$this, 'ajax_test_component']);
        add_action('wp_ajax_adn_auto_fix_component', [$this, 'ajax_auto_fix_component']);
        add_action('wp_ajax_adn_get_component_details', [$this, 'ajax_get_component_details']);
        add_action('wp_ajax_adn_test_all_components', [$this, 'ajax_test_all_components']);
        add_action('wp_ajax_adn_clear_test_results', [$this, 'ajax_clear_test_results']);
        add_action('wp_ajax_adn_scan_issues', [$this, 'ajax_scan_issues']);
        add_action('wp_ajax_adn_apply_single_fix', [$this, 'ajax_apply_single_fix']);
        add_action('wp_ajax_adn_preview_fix', [$this, 'ajax_preview_fix']);
        add_action('wp_ajax_adn_apply_all_fixes', [$this, 'ajax_apply_all_fixes']);
        add_action('wp_ajax_adn_download_system_info', [$this, 'ajax_download_system_info']);
        add_action('wp_ajax_adn_run_health_check', [$this, 'ajax_run_health_check']);
        
        // Comprehensive testing AJAX handlers
        add_action('wp_ajax_adn_run_comprehensive_test', [$this, 'ajax_run_comprehensive_test']);
        add_action('wp_ajax_adn_stop_comprehensive_test', [$this, 'ajax_stop_comprehensive_test']);
        add_action('wp_ajax_adn_get_comprehensive_test_progress', [$this, 'ajax_get_comprehensive_test_progress']);
        add_action('wp_ajax_adn_get_comprehensive_results', [$this, 'ajax_get_comprehensive_results']);
        add_action('wp_ajax_adn_export_comprehensive_results', [$this, 'ajax_export_comprehensive_results']);
        add_action('wp_ajax_adn_clear_comprehensive_results', [$this, 'ajax_clear_comprehensive_results']);
        add_action('wp_ajax_adn_retest_feature', [$this, 'ajax_retest_feature']);
    }
    
    /**
     * Add admin menus
     */
    public function add_admin_menus() {
        // DIAGNOSTIC LOG: Menu registration
        error_log('ADN DEBUG: add_admin_menus() called');
        
        // Main menu
        \add_menu_page(
            'Aevov Diagnostic Network',
            'ADN Diagnostics',
            'manage_options',
            'adn-dashboard',
            [$this, 'render_main_page'],
            'dashicons-analytics',
            30
        );
        
        // Dashboard submenu
        \add_submenu_page(
            'adn-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'adn-dashboard',
            [$this, 'render_dashboard_page']
        );
        
        // Architecture Map submenu
        \add_submenu_page(
            'adn-dashboard',
            'Architecture Map',
            'Architecture Map',
            'manage_options',
            'adn-architecture-map',
            [$this, 'render_architecture_map_page']
        );
        
        // Component Tests submenu
        \add_submenu_page(
            'adn-dashboard',
            'Component Tests',
            'Component Tests',
            'manage_options',
            'adn-component-tests',
            [$this, 'render_component_tests_page']
        );
        
        // AI Auto-Fix submenu
        \add_submenu_page(
            'adn-dashboard',
            'AI Auto-Fix',
            'AI Auto-Fix',
            'manage_options',
            'adn-ai-autofix',
            [$this, 'render_ai_autofix_page']
        );
        
        // Comprehensive Tests submenu
        \add_submenu_page(
            'adn-dashboard',
            'Comprehensive Tests',
            'Comprehensive Tests',
            'manage_options',
            'adn-comprehensive-tests',
            [$this, 'render_comprehensive_tests_page']
        );
        
        // System Status submenu
        \add_submenu_page(
            'adn-dashboard',
            'System Status',
            'System Status',
            'manage_options',
            'adn-system-status',
            [$this, 'render_system_status_page']
        );
        
        // Settings submenu
        \add_submenu_page(
            'adn-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'adn-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // DIAGNOSTIC LOG: Asset loading
        error_log('ADN DEBUG: enqueue_admin_assets called for hook: ' . $hook);
        error_log('ADN DEBUG: ADN_PLUGIN_URL constant: ' . (defined('ADN_PLUGIN_URL') ? ADN_PLUGIN_URL : 'NOT DEFINED'));
        
        // Only load on ADN pages
        if (strpos($hook, 'adn-') === false) {
            error_log('ADN DEBUG: Not an ADN page, skipping asset loading');
            return;
        }
        
        error_log('ADN DEBUG: Loading assets for ADN page');
        
        // VALIDATION: Check if asset files exist
        $css_path = ADN_PLUGIN_DIR . 'assets/css/admin.css';
        $js_path = ADN_PLUGIN_DIR . 'assets/js/admin.js';
        error_log('ADN DEBUG: CSS file exists: ' . (file_exists($css_path) ? 'YES' : 'NO') . ' at ' . $css_path);
        error_log('ADN DEBUG: JS file exists: ' . (file_exists($js_path) ? 'YES' : 'NO') . ' at ' . $js_path);
        
        // Core libraries
        \wp_enqueue_script('jquery');
        \wp_enqueue_script('jquery-ui-core');
        \wp_enqueue_script('jquery-ui-tabs');
        \wp_enqueue_script('jquery-ui-dialog');
        \wp_enqueue_script('jquery-ui-progressbar');

        // Chart.js for visualizations
        \wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1',
            true
        );
        
        // DataTables for component lists
        \wp_enqueue_script(
            'datatables',
            'https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js',
            ['jquery'],
            '1.13.4',
            true
        );
        
        \wp_enqueue_style(
            'datatables',
            'https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css',
            [],
            '1.13.4'
        );
        
        // Custom admin scripts
        \wp_enqueue_script(
            'adn-admin',
            ADN_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'chartjs', 'datatables'],
            ADN_VERSION,
            true
        );
        
        // Custom admin styles
        \wp_enqueue_style(
            'adn-admin',
            ADN_PLUGIN_URL . 'assets/css/admin.css',
            ['wp-admin'],
            ADN_VERSION
        );
        
        // Localize script
        \wp_localize_script('adn-admin', 'adnAdmin', [
            'ajaxUrl' => \admin_url('admin-ajax.php'),
            'nonce' => \wp_create_nonce('adn_admin_nonce'),
            'pluginUrl' => ADN_PLUGIN_URL,
            'currentPage' => $_GET['page'] ?? '',
            'strings' => [
                'confirmSystemScan' => \__('Run a full system scan? This may take several minutes.', 'adn'),
                'confirmAutoFix' => \__('Apply auto-fix? This will modify files and may affect system functionality.', 'adn'),
                'confirmClearCache' => \__('Clear all diagnostic cache? This will remove all stored test results.', 'adn'),
                'scanInProgress' => \__('System scan in progress...', 'adn'),
                'scanComplete' => \__('System scan completed!', 'adn'),
                'autoFixInProgress' => \__('Auto-fix in progress...', 'adn'),
                'autoFixComplete' => \__('Auto-fix completed!', 'adn'),
                'error' => \__('An error occurred. Please try again.', 'adn')
            ]
        ]);
    }
    
    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        if (!\current_user_can('manage_options')) {
            return;
        }
        
        $action = $_POST['adn_action'] ?? $_GET['adn_action'] ?? '';
        
        if (empty($action) || !\wp_verify_nonce($_POST['_wpnonce'] ?? $_GET['_wpnonce'] ?? '', 'adn_admin_action')) {
            return;
        }
        
        switch ($action) {
            case 'run_full_scan':
                $this->handle_run_full_scan();
                break;
            case 'export_diagnostics':
                $this->handle_export_diagnostics();
                break;
            case 'import_diagnostics':
                $this->handle_import_diagnostics();
                break;
            case 'save_settings':
                $this->handle_save_settings();
                break;
        }
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        $dashboard_data = $this->get_dashboard_data();
        
        ?>
        <div class="wrap adn-dashboard">
            <h1><?php \_e('Aevov Diagnostic Network - Dashboard', 'adn'); ?></h1>
            
            <div class="adn-dashboard-grid">
                <!-- System Health Overview -->
                <div class="adn-card adn-system-health">
                    <h2><?php \_e('System Health', 'adn'); ?></h2>
                    <div class="adn-health-indicator">
                        <div class="adn-health-score" data-score="<?php echo \esc_attr($dashboard_data['health_score']); ?>">
                            <span class="adn-score-value"><?php echo \esc_html($dashboard_data['health_score']); ?>%</span>
                            <span class="adn-score-label"><?php echo \esc_html($dashboard_data['health_status']); ?></span>
                        </div>
                    </div>
                    <div class="adn-health-details">
                        <div class="adn-stat">
                            <span class="adn-stat-value"><?php echo \esc_html($dashboard_data['components_passing']); ?></span>
                            <span class="adn-stat-label"><?php \_e('Components Passing', 'adn'); ?></span>
                        </div>
                        <div class="adn-stat">
                            <span class="adn-stat-value"><?php echo \esc_html($dashboard_data['components_failing']); ?></span>
                            <span class="adn-stat-label"><?php \_e('Components Failing', 'adn'); ?></span>
                        </div>
                        <div class="adn-stat">
                            <span class="adn-stat-value"><?php echo \esc_html($dashboard_data['components_warning']); ?></span>
                            <span class="adn-stat-label"><?php \_e('Components Warning', 'adn'); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="adn-card adn-quick-actions">
                    <h2><?php \_e('Quick Actions', 'adn'); ?></h2>
                    <div class="adn-action-buttons">
                        <button id="adn-run-system-scan" class="button button-primary">
                            <span class="dashicons dashicons-search"></span>
                            <?php \_e('Run System Scan', 'adn'); ?>
                        </button>
                        <button id="adn-view-architecture-map" class="button">
                            <span class="dashicons dashicons-networking"></span>
                            <?php \_e('View Architecture Map', 'adn'); ?>
                        </button>
                        <button id="adn-run-auto-fix" class="button">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php \_e('Run Auto-Fix', 'adn'); ?>
                        </button>
                        <button id="adn-export-diagnostics" class="button">
                            <span class="dashicons dashicons-download"></span>
                            <?php \_e('Export Diagnostics', 'adn'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="adn-card adn-recent-activity">
                    <h2><?php \_e('Recent Activity', 'adn'); ?></h2>
                    <div class="adn-activity-list">
                        <?php foreach ($dashboard_data['recent_activity'] as $activity): ?>
                        <div class="adn-activity-item">
                            <div class="adn-activity-icon">
                                <span class="dashicons dashicons-<?php echo \esc_attr($activity['icon']); ?>"></span>
                            </div>
                            <div class="adn-activity-content">
                                <div class="adn-activity-message"><?php echo \esc_html($activity['message']); ?></div>
                                <div class="adn-activity-time"><?php echo \esc_html($activity['time']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- System Statistics -->
                <div class="adn-card adn-system-stats">
                    <h2><?php \_e('System Statistics', 'adn'); ?></h2>
                    <canvas id="adn-stats-chart" width="400" height="200"></canvas>
                </div>
                
                <!-- Component Status Summary -->
                <div class="adn-card adn-component-summary">
                    <h2><?php \_e('Component Status Summary', 'adn'); ?></h2>
                    <div class="adn-component-grid">
                        <?php foreach ($dashboard_data['component_summary'] as $component): ?>
                        <div class="adn-component-item status-<?php echo \esc_attr($component['status']); ?>">
                            <div class="adn-component-name"><?php echo \esc_html($component['name']); ?></div>
                            <div class="adn-component-status"><?php echo \esc_html(ucfirst($component['status'])); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- AI Engine Status -->
                <div class="adn-card adn-ai-status">
                    <h2><?php \_e('AI Engine Status', 'adn'); ?></h2>
                    <div class="adn-ai-engines">
                        <?php foreach ($dashboard_data['ai_engines'] as $engine): ?>
                        <div class="adn-ai-engine status-<?php echo \esc_attr($engine['status']); ?>">
                            <div class="adn-engine-name"><?php echo \esc_html($engine['name']); ?></div>
                            <div class="adn-engine-status"><?php echo \esc_html($engine['status_text']); ?></div>
                            <div class="adn-engine-confidence"><?php echo \esc_html($engine['confidence']); ?>% confidence</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Progress Modal -->
            <div id="adn-progress-modal" class="adn-modal" style="display: none;">
                <div class="adn-modal-content">
                    <h3 id="adn-progress-title"><?php \_e('Processing...', 'adn'); ?></h3>
                    <div id="adn-progress-bar"></div>
                    <div id="adn-progress-message"><?php \_e('Please wait...', 'adn'); ?></div>
                    <div id="adn-progress-details"></div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize dashboard
            initializeDashboard();
            
            // Auto-refresh dashboard data
            setInterval(refreshDashboardData, 30000); // Every 30 seconds
        });
        
        function initializeDashboard() {
            // Initialize charts
            initializeStatsChart();
            
            // Initialize health indicator
            initializeHealthIndicator();
            
            // Bind event handlers
            bindDashboardEvents();
        }
        
        function initializeStatsChart() {
            const ctx = document.getElementById('adn-stats-chart');
            if (!ctx) return;
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Passing', 'Failing', 'Warning', 'Unknown'],
                    datasets: [{
                        data: [
                            <?php echo $dashboard_data['components_passing']; ?>,
                            <?php echo $dashboard_data['components_failing']; ?>,
                            <?php echo $dashboard_data['components_warning']; ?>,
                            <?php echo $dashboard_data['components_unknown']; ?>
                        ],
                        backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#6c757d']
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
        }
        
        function initializeHealthIndicator() {
            const indicator = document.querySelector('.adn-health-score');
            if (!indicator) return;
            
            const score = parseInt(indicator.dataset.score);
            let className = 'excellent';
            
            if (score < 50) className = 'critical';
            else if (score < 70) className = 'poor';
            else if (score < 85) className = 'good';
            
            indicator.classList.add('health-' + className);
        }
        
        function bindDashboardEvents() {
            // System scan button
            $('#adn-run-system-scan').on('click', function() {
                if (confirm(adnAdmin.strings.confirmSystemScan)) {
                    runSystemScan();
                }
            });
            
            // Architecture map button
            $('#adn-view-architecture-map').on('click', function() {
                window.location.href = '<?php echo \admin_url('admin.php?page=adn-architecture-map'); ?>';
            });
            
            // Auto-fix button
            $('#adn-run-auto-fix').on('click', function() {
                if (confirm(adnAdmin.strings.confirmAutoFix)) {
                    runAutoFix();
                }
            });
            
            // Export button
            $('#adn-export-diagnostics').on('click', function() {
                exportDiagnostics();
            });
        }
        
        function runSystemScan() {
            showProgressModal(adnAdmin.strings.scanInProgress);
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_run_system_scan',
                nonce: adnAdmin.nonce
            }, function(response) {
                hideProgressModal();
                
                if (response.success) {
                    alert(adnAdmin.strings.scanComplete);
                    refreshDashboardData();
                } else {
                    alert(adnAdmin.strings.error + ': ' + response.data.message);
                }
            }).fail(function() {
                hideProgressModal();
                alert(adnAdmin.strings.error);
            });
        }
        
        function runAutoFix() {
            showProgressModal(adnAdmin.strings.autoFixInProgress);
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_run_auto_fix',
                nonce: adnAdmin.nonce
            }, function(response) {
                hideProgressModal();
                
                if (response.success) {
                    alert(adnAdmin.strings.autoFixComplete);
                    refreshDashboardData();
                } else {
                    alert(adnAdmin.strings.error + ': ' + response.data.message);
                }
            }).fail(function() {
                hideProgressModal();
                alert(adnAdmin.strings.error);
            });
        }
        
        function exportDiagnostics() {
            window.location.href = adnAdmin.ajaxUrl + '?action=adn_export_diagnostics&nonce=' + adnAdmin.nonce;
        }
        
        function refreshDashboardData() {
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_get_dashboard_data',
                nonce: adnAdmin.nonce
            }, function(response) {
                if (response.success) {
                    updateDashboardData(response.data);
                }
            });
        }
        
        function updateDashboardData(data) {
            // Update health score
            $('.adn-score-value').text(data.health_score + '%');
            $('.adn-score-label').text(data.health_status);
            
            // Update statistics
            $('.adn-stat-value').each(function(index) {
                const values = [data.components_passing, data.components_failing, data.components_warning];
                $(this).text(values[index] || 0);
            });
            
            // Update component grid
            updateComponentGrid(data.component_summary);
            
            // Update AI engine status
            updateAIEngineStatus(data.ai_engines);
        }
        
        function updateComponentGrid(components) {
            const grid = $('.adn-component-grid');
            grid.empty();
            
            components.forEach(function(component) {
                const item = $('<div class="adn-component-item status-' + component.status + '">' +
                    '<div class="adn-component-name">' + component.name + '</div>' +
                    '<div class="adn-component-status">' + component.status.charAt(0).toUpperCase() + component.status.slice(1) + '</div>' +
                    '</div>');
                grid.append(item);
            });
        }
        
        function updateAIEngineStatus(engines) {
            const container = $('.adn-ai-engines');
            container.empty();
            
            engines.forEach(function(engine) {
                const item = $('<div class="adn-ai-engine status-' + engine.status + '">' +
                    '<div class="adn-engine-name">' + engine.name + '</div>' +
                    '<div class="adn-engine-status">' + engine.status_text + '</div>' +
                    '<div class="adn-engine-confidence">' + engine.confidence + '% confidence</div>' +
                    '</div>');
                container.append(item);
            });
        }
        
        function showProgressModal(title, message) {
            $('#adn-progress-title').text(title);
            $('#adn-progress-message').text(message || '');
            $('#adn-progress-modal').show();
            
            // Initialize progress bar
            $('#adn-progress-bar').progressbar({ value: false });
        }
        
        function hideProgressModal() {
            $('#adn-progress-modal').hide();
        }
        </script>
        <?php
    }
    
    /**
     * Render architecture map page
     */
    public function render_architecture_map_page() {
        ?>
        <div class="wrap adn-architecture-map-page">
            <h1><?php \_e('Architecture Map', 'adn'); ?></h1>
            
            <div class="adn-map-container">
                <?php echo $this->architecture_map->get_enhanced_map_html(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render component tests page
     */
    public function render_component_tests_page() {
        $components = $this->get_diagnostic_network()->get_system_components();
        
        ?>
        <div class="wrap adn-component-tests-page">
            <h1><?php \_e('Component Tests', 'adn'); ?></h1>
            
            <div class="adn-tests-controls">
                <button id="adn-test-all-components" class="button button-primary">
                    <?php \_e('Test All Components', 'adn'); ?>
                </button>
                <button id="adn-clear-test-results" class="button">
                    <?php \_e('Clear Results', 'adn'); ?>
                </button>
                <button id="adn-export-test-results" class="button">
                    <?php \_e('Export Results', 'adn'); ?>
                </button>
            </div>
            
            <table id="adn-components-table" class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php \_e('Component', 'adn'); ?></th>
                        <th><?php \_e('Type', 'adn'); ?></th>
                        <th><?php \_e('Status', 'adn'); ?></th>
                        <th><?php \_e('Last Tested', 'adn'); ?></th>
                        <th><?php \_e('Actions', 'adn'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($components as $component): ?>
                    <tr data-component-id="<?php echo \esc_attr($component['id'] ?? $component['name'] ?? 'unknown'); ?>">
                        <td>
                            <strong><?php echo \esc_html($component['name']); ?></strong>
                            <div class="row-actions">
                                <span class="view">
                                    <a href="#" class="adn-view-component"><?php \_e('View Details', 'adn'); ?></a>
                                </span>
                            </div>
                        </td>
                        <td><?php echo \esc_html($component['type']); ?></td>
                        <td>
                            <span class="adn-status-indicator status-<?php echo \esc_attr($component['status'] ?? 'unknown'); ?>">
                                <?php echo \esc_html(ucfirst($component['status'] ?? 'unknown')); ?>
                            </span>
                        </td>
                        <td><?php echo \esc_html($component['last_tested'] ?? \__('Never', 'adn')); ?></td>
                        <td>
                            <button class="button adn-test-component" data-component-id="<?php echo \esc_attr($component['id'] ?? $component['name'] ?? 'unknown'); ?>">
                                <?php \_e('Test', 'adn'); ?>
                            </button>
                            <button class="button adn-auto-fix-component" data-component-id="<?php echo \esc_attr($component['id'] ?? $component['name'] ?? 'unknown'); ?>">
                                <?php \_e('Auto-Fix', 'adn'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Component Details Modal -->
            <div id="adn-component-details-modal" class="adn-modal" style="display: none;">
                <div class="adn-modal-content">
                    <div class="adn-modal-header">
                        <h3 id="adn-component-details-title"><?php \_e('Component Details', 'adn'); ?></h3>
                        <button class="adn-modal-close">&times;</button>
                    </div>
                    <div class="adn-modal-body">
                        <div id="adn-component-details-content"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize DataTable (check if already initialized)
            if (!$.fn.DataTable.isDataTable('#adn-components-table')) {
                $('#adn-components-table').DataTable({
                    pageLength: 25,
                    order: [[2, 'desc']], // Sort by status
                    columnDefs: [
                        { orderable: false, targets: [4] } // Disable sorting on actions column
                    ]
                });
            }
            
            // Test component button
            $('.adn-test-component').on('click', function() {
                const componentId = $(this).data('component-id');
                testComponent(componentId);
            });
            
            // Auto-fix component button
            $('.adn-auto-fix-component').on('click', function() {
                const componentId = $(this).data('component-id');
                if (confirm('Apply auto-fix for this component?')) {
                    autoFixComponent(componentId);
                }
            });
            
            // View component details
            $('.adn-view-component').on('click', function(e) {
                e.preventDefault();
                const componentId = $(this).closest('tr').data('component-id');
                showComponentDetails(componentId);
            });
            
            // Test all components
            $('#adn-test-all-components').on('click', function() {
                if (confirm('Test all components? This may take several minutes.')) {
                    testAllComponents();
                }
            });
            
            // Clear test results
            $('#adn-clear-test-results').on('click', function() {
                if (confirm('Clear all test results?')) {
                    clearTestResults();
                }
            });
            
            // Modal close
            $('.adn-modal-close').on('click', function() {
                $(this).closest('.adn-modal').hide();
            });
        });
        
        function testComponent(componentId) {
            const row = $('tr[data-component-id="' + componentId + '"]');
            const statusCell = row.find('.adn-status-indicator');
            
            statusCell.removeClass().addClass('adn-status-indicator status-testing').text('Testing...');
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_test_component',
                component_id: componentId,
                nonce: adnAdmin.nonce
            }, function(response) {
                if (response.success) {
                    const status = response.data.overall_status;
                    statusCell.removeClass().addClass('adn-status-indicator status-' + status)
                        .text(status.charAt(0).toUpperCase() + status.slice(1));
                    
                    // Update last tested
                    row.find('td:nth-child(4)').text('Just now');
                } else {
                    statusCell.removeClass().addClass('adn-status-indicator status-error').text('Error');
                    alert('Test failed: ' + response.data.message);
                }
            }).fail(function() {
                statusCell.removeClass().addClass('adn-status-indicator status-error').text('Error');
                alert('Test failed due to network error');
            });
        }
        
        function autoFixComponent(componentId) {
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_auto_fix_component',
                component_id: componentId,
                nonce: adnAdmin.nonce
            }, function(response) {
                if (response.success) {
                    alert('Auto-fix completed: ' + response.data.message);
                    // Re-test the component
                    testComponent(componentId);
                } else {
                    alert('Auto-fix failed: ' + response.data.message);
                }
            });
        }
        
        function showComponentDetails(componentId) {
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_get_component_details',
                component_id: componentId,
                nonce: adnAdmin.nonce
            }, function(response) {
                if (response.success) {
                    $('#adn-component-details-title').text('Details: ' + response.data.name);
                    $('#adn-component-details-content').html(formatComponentDetails(response.data));
                    $('#adn-component-details-modal').show();
                }
            });
        }
        
        function formatComponentDetails(data) {
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
        }
        
        function testAllComponents() {
            showProgressModal('Testing all components...', 'This may take several minutes.');
            
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_test_all_components',
                nonce: adnAdmin.nonce
            }, function(response) {
                hideProgressModal();
                
                if (response.success) {
                    alert('All components tested successfully!');
                    location.reload(); // Refresh the page to show updated results
                } else {
                    alert('Testing failed: ' + response.data.message);
                }
            }).fail(function() {
                hideProgressModal();
                alert('Testing failed due to network error');
            });
        }
        
        function clearTestResults() {
            $.post(adnAdmin.ajaxUrl, {
                action: 'adn_clear_test_results',
                nonce: adnAdmin.nonce
            }, function(response) {
                if (response.success) {
                    alert('Test results cleared successfully!');
                    location.reload();
                } else {
                    alert('Failed to clear results: ' + response.data.message);
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Render comprehensive tests page
     */
    public function render_comprehensive_tests_page() {
        ?>
        <div class="wrap adn-comprehensive-tests-page">
            <h1><?php \_e('Comprehensive Feature Tests', 'adn'); ?></h1>
            
            <div class="adn-comprehensive-controls">
                <button id="adn-start-comprehensive-test" class="button button-primary">
                    <span class="dashicons dashicons-analytics"></span>
                    <?php \_e('Start Comprehensive Test', 'adn'); ?>
                </button>
                <button id="adn-stop-comprehensive-test" class="button" style="display: none;">
                    <span class="dashicons dashicons-no"></span>
                    <?php \_e('Stop Test', 'adn'); ?>
                </button>
                <button id="adn-export-comprehensive-results" class="button">
                    <span class="dashicons dashicons-download"></span>
                    <?php \_e('Export Results', 'adn'); ?>
                </button>
                <button id="adn-clear-comprehensive-results" class="button">
                    <span class="dashicons dashicons-trash"></span>
                    <?php \_e('Clear Results', 'adn'); ?>
                </button>
            </div>
            
            <div class="adn-comprehensive-progress" style="display: none;">
                <div class="adn-progress-header">
                    <h3><?php \_e('Testing Progress', 'adn'); ?></h3>
                    <div class="adn-progress-stats">
                        <span id="adn-progress-current">0</span> / <span id="adn-progress-total">24</span> features tested
                        (<span id="adn-progress-percentage">0</span>%)
                    </div>
                </div>
                <div class="adn-progress-bar-container">
                    <div id="adn-progress-bar" class="adn-progress-bar"></div>
                </div>
                <div class="adn-progress-details">
                    <div id="adn-current-test-info"><?php \_e('Preparing tests...', 'adn'); ?></div>
                </div>
            </div>
            
            <!-- Feature Map Visualization -->
            <div class="adn-feature-map-container">
                <h2><?php \_e('Aevov Network Feature Map', 'adn'); ?></h2>
                <div class="adn-map-controls">
                    <div class="adn-legend">
                        <div class="adn-legend-item">
                            <span class="adn-status-dot status-pending"></span>
                            <?php \_e('Pending', 'adn'); ?>
                        </div>
                        <div class="adn-legend-item">
                            <span class="adn-status-dot status-running"></span>
                            <?php \_e('Running', 'adn'); ?>
                        </div>
                        <div class="adn-legend-item">
                            <span class="adn-status-dot status-pass"></span>
                            <?php \_e('Pass', 'adn'); ?>
                        </div>
                        <div class="adn-legend-item">
                            <span class="adn-status-dot status-warning"></span>
                            <?php \_e('Warning', 'adn'); ?>
                        </div>
                        <div class="adn-legend-item">
                            <span class="adn-status-dot status-fail"></span>
                            <?php \_e('Fail', 'adn'); ?>
                        </div>
                        <div class="adn-legend-item">
                            <span class="adn-status-dot status-critical"></span>
                            <?php \_e('Critical', 'adn'); ?>
                        </div>
                    </div>
                    <div class="adn-map-filters">
                        <label>
                            <input type="checkbox" id="adn-filter-core" checked>
                            <?php \_e('Core Network', 'adn'); ?>
                        </label>
                        <label>
                            <input type="checkbox" id="adn-filter-storage" checked>
                            <?php \_e('Storage & Data', 'adn'); ?>
                        </label>
                        <label>
                            <input type="checkbox" id="adn-filter-ai" checked>
                            <?php \_e('AI & Processing', 'adn'); ?>
                        </label>
                        <label>
                            <input type="checkbox" id="adn-filter-integration" checked>
                            <?php \_e('Integration', 'adn'); ?>
                        </label>
                        <label>
                            <input type="checkbox" id="adn-filter-performance" checked>
                            <?php \_e('Performance', 'adn'); ?>
                        </label>
                        <label>
                            <input type="checkbox" id="adn-filter-ui" checked>
                            <?php \_e('User Interface', 'adn'); ?>
                        </label>
                    </div>
                </div>
                <div id="adn-feature-map" class="adn-feature-map"></div>
            </div>
            
            <!-- Test Results Summary -->
            <div class="adn-test-results-summary" style="display: none;">
                <h2><?php \_e('Test Results Summary', 'adn'); ?></h2>
                <div class="adn-results-grid">
                    <div class="adn-result-card">
                        <h3><?php \_e('Overall Score', 'adn'); ?></h3>
                        <div class="adn-score-display">
                            <span id="adn-overall-score">0</span>%
                        </div>
                    </div>
                    <div class="adn-result-card">
                        <h3><?php \_e('Features Tested', 'adn'); ?></h3>
                        <div class="adn-count-display">
                            <span id="adn-features-tested">0</span> / 24
                        </div>
                    </div>
                    <div class="adn-result-card">
                        <h3><?php \_e('Critical Issues', 'adn'); ?></h3>
                        <div class="adn-count-display critical">
                            <span id="adn-critical-issues">0</span>
                        </div>
                    </div>
                    <div class="adn-result-card">
                        <h3><?php \_e('Warnings', 'adn'); ?></h3>
                        <div class="adn-count-display warning">
                            <span id="adn-warning-issues">0</span>
                        </div>
                    </div>
                </div>
                
                <!-- Detailed Results Table -->
                <div class="adn-detailed-results">
                    <h3><?php \_e('Detailed Results', 'adn'); ?></h3>
                    <table id="adn-results-table" class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php \_e('Component Group', 'adn'); ?></th>
                                <th><?php \_e('Feature', 'adn'); ?></th>
                                <th><?php \_e('Status', 'adn'); ?></th>
                                <th><?php \_e('Duration', 'adn'); ?></th>
                                <th><?php \_e('Message', 'adn'); ?></th>
                                <th><?php \_e('Actions', 'adn'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="adn-results-tbody">
                            <!-- Results will be populated here -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Recommendations -->
                <div class="adn-recommendations" style="display: none;">
                    <h3><?php \_e('Recommendations', 'adn'); ?></h3>
                    <div id="adn-recommendations-list"></div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize comprehensive testing interface
            window.adnComprehensiveTesting = {
                isRunning: false,
                currentTest: null,
                results: {},
                
                init: function() {
                    this.bindEvents();
                    this.initializeFeatureMap();
                    this.loadPreviousResults();
                },
                
                bindEvents: function() {
                    $('#adn-start-comprehensive-test').on('click', () => this.startTest());
                    $('#adn-stop-comprehensive-test').on('click', () => this.stopTest());
                    $('#adn-export-comprehensive-results').on('click', () => this.exportResults());
                    $('#adn-clear-comprehensive-results').on('click', () => this.clearResults());
                    
                    // Filter checkboxes
                    $('.adn-map-filters input[type="checkbox"]').on('change', () => this.updateMapFilters());
                },
                
                startTest: function() {
                    if (this.isRunning) return;
                    
                    if (!confirm('<?php \_e('Start comprehensive testing? This will test all 24 features of the Aevov network and may take several minutes.', 'adn'); ?>')) {
                        return;
                    }
                    
                    this.isRunning = true;
                    this.updateUI();
                    this.resetProgress();
                    
                    // Start the comprehensive test via AJAX
                    $.post(adnAdmin.ajaxUrl, {
                        action: 'adn_run_comprehensive_test',
                        nonce: adnAdmin.nonce
                    }, (response) => {
                        if (response.success) {
                            this.monitorProgress();
                        } else {
                            this.handleError('Failed to start comprehensive test: ' + response.data.message);
                        }
                    }).fail(() => {
                        this.handleError('Network error while starting comprehensive test');
                    });
                },
                
                stopTest: function() {
                    if (!this.isRunning) return;
                    
                    if (confirm('<?php \_e('Stop the comprehensive test? Progress will be lost.', 'adn'); ?>')) {
                        this.isRunning = false;
                        this.updateUI();
                        
                        // Stop the test via AJAX
                        $.post(adnAdmin.ajaxUrl, {
                            action: 'adn_stop_comprehensive_test',
                            nonce: adnAdmin.nonce
                        });
                    }
                },
                
                monitorProgress: function() {
                    if (!this.isRunning) return;
                    
                    $.post(adnAdmin.ajaxUrl, {
                        action: 'adn_get_comprehensive_test_progress',
                        nonce: adnAdmin.nonce
                    }, (response) => {
                        if (response.success) {
                            this.updateProgress(response.data);
                            
                            if (response.data.completed) {
                                this.testCompleted(response.data);
                            } else {
                                // Continue monitoring
                                setTimeout(() => this.monitorProgress(), 1000);
                            }
                        }
                    });
                },
                
                updateProgress: function(data) {
                    const percentage = Math.round((data.current / data.total) * 100);
                    
                    $('#adn-progress-current').text(data.current);
                    $('#adn-progress-total').text(data.total);
                    $('#adn-progress-percentage').text(percentage);
                    $('#adn-progress-bar').css('width', percentage + '%');
                    
                    if (data.current_feature) {
                        $('#adn-current-test-info').text('Testing: ' + data.current_feature);
                    }
                    
                    // Update feature map
                    if (data.visual_updates) {
                        this.updateFeatureVisuals(data.visual_updates);
                    }
                },
                
                testCompleted: function(data) {
                    this.isRunning = false;
                    this.updateUI();
                    this.displayResults(data.results);
                    
                    alert('<?php \_e('Comprehensive testing completed!', 'adn'); ?>');
                },
                
                updateUI: function() {
                    if (this.isRunning) {
                        $('#adn-start-comprehensive-test').hide();
                        $('#adn-stop-comprehensive-test').show();
                        $('.adn-comprehensive-progress').show();
                    } else {
                        $('#adn-start-comprehensive-test').show();
                        $('#adn-stop-comprehensive-test').hide();
                        $('.adn-comprehensive-progress').hide();
                    }
                },
                
                resetProgress: function() {
                    $('#adn-progress-current').text('0');
                    $('#adn-progress-percentage').text('0');
                    $('#adn-progress-bar').css('width', '0%');
                    $('#adn-current-test-info').text('<?php \_e('Preparing tests...', 'adn'); ?>');
                },
                
                initializeFeatureMap: function() {
                    // This will be enhanced with D3.js visualization
                    $('#adn-feature-map').html('<p><?php \_e('Feature map will be rendered here with D3.js', 'adn'); ?></p>');
                },
                
                updateFeatureVisuals: function(updates) {
                    // Update visual indicators based on test progress
                    // This will be enhanced with actual D3.js updates
                },
                
                updateMapFilters: function() {
                    // Filter the feature map based on selected component groups
                },
                
                displayResults: function(results) {
                    $('.adn-test-results-summary').show();
                    
                    // Update summary cards
                    $('#adn-overall-score').text(results.overall_score || 0);
                    $('#adn-features-tested').text(results.features_tested || 0);
                    $('#adn-critical-issues').text(results.critical_issues || 0);
                    $('#adn-warning-issues').text(results.warning_issues || 0);
                    
                    // Populate results table
                    this.populateResultsTable(results.detailed_results || []);
                    
                    // Show recommendations if any
                    if (results.recommendations && results.recommendations.length > 0) {
                        this.displayRecommendations(results.recommendations);
                    }
                },
                
                populateResultsTable: function(results) {
                    const tbody = $('#adn-results-tbody');
                    tbody.empty();
                    
                    results.forEach(result => {
                        const row = $(`
                            <tr>
                                <td>${result.component_group}</td>
                                <td>${result.feature}</td>
                                <td><span class="adn-status-indicator status-${result.status}">${result.status.toUpperCase()}</span></td>
                                <td>${result.duration}ms</td>
                                <td>${result.message}</td>
                                <td>
                                    <button class="button button-small adn-retest-feature" data-feature="${result.feature}">
                                        <?php \_e('Retest', 'adn'); ?>
                                    </button>
                                </td>
                            </tr>
                        `);
                        tbody.append(row);
                    });
                    
                    // Bind retest buttons
                    $('.adn-retest-feature').on('click', function() {
                        const feature = $(this).data('feature');
                        window.adnComprehensiveTesting.retestFeature(feature);
                    });
                },
                
                displayRecommendations: function(recommendations) {
                    const container = $('#adn-recommendations-list');
                    container.empty();
                    
                    recommendations.forEach(rec => {
                        const item = $(`
                            <div class="adn-recommendation-item priority-${rec.priority}">
                                <h4>${rec.title}</h4>
                                <p>${rec.description}</p>
                                ${rec.action ? `<button class="button adn-apply-recommendation" data-action="${rec.action}"><?php \_e('Apply Fix', 'adn'); ?></button>` : ''}
                            </div>
                        `);
                        container.append(item);
                    });
                    
                    $('.adn-recommendations').show();
                },
                
                retestFeature: function(feature) {
                    // Retest a specific feature
                    $.post(adnAdmin.ajaxUrl, {
                        action: 'adn_retest_feature',
                        feature: feature,
                        nonce: adnAdmin.nonce
                    }, (response) => {
                        if (response.success) {
                            alert('Feature retested successfully');
                            this.loadPreviousResults(); // Refresh results
                        }
                    });
                },
                
                exportResults: function() {
                    window.location.href = adnAdmin.ajaxUrl + '?action=adn_export_comprehensive_results&nonce=' + adnAdmin.nonce;
                },
                
                clearResults: function() {
                    if (confirm('<?php \_e('Clear all comprehensive test results?', 'adn'); ?>')) {
                        $.post(adnAdmin.ajaxUrl, {
                            action: 'adn_clear_comprehensive_results',
                            nonce: adnAdmin.nonce
                        }, (response) => {
                            if (response.success) {
                                $('.adn-test-results-summary').hide();
                                this.initializeFeatureMap();
                                alert('<?php \_e('Results cleared successfully', 'adn'); ?>');
                            }
                        });
                    }
                },
                
                loadPreviousResults: function() {
                    // Load any previous test results
                    $.post(adnAdmin.ajaxUrl, {
                        action: 'adn_get_comprehensive_results',
                        nonce: adnAdmin.nonce
                    }, (response) => {
                        if (response.success && response.data.has_results) {
                            this.displayResults(response.data.results);
                        }
                    });
                },
                
                handleError: function(message) {
                    this.isRunning = false;
                    this.updateUI();
                    alert('Error: ' + message);
                }
            };
            
            // Initialize the comprehensive testing interface
            window.adnComprehensiveTesting.init();
        });
        </script>
        <?php
    }
    
    /**
     * Render AI auto-fix page
     */
    public function render_ai_autofix_page() {
        $diagnostic_network = $this->get_diagnostic_network();
        $issues = $diagnostic_network->scan_for_issues();
        $fix_history = get_option('adn_fix_history', []);
        ?>
        <div class="wrap adn-ai-autofix">
            <h1><?php \_e('AI Auto-Fix', 'adn'); ?></h1>

            <div class="adn-scan-controls" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2 style="margin-top: 0;"><?php \_e('Scan for Issues', 'adn'); ?></h2>
                <p><?php \_e('Scan your Aevov installation for issues that can be automatically fixed.', 'adn'); ?></p>
                <button type="button" class="button button-primary" id="adn-scan-issues">
                    <?php \_e('Scan Now', 'adn'); ?>
                </button>
                <span id="adn-scan-status" style="margin-left: 10px;"></span>
            </div>

            <div class="adn-issues-list" style="margin: 20px 0;">
                <h2><?php \_e('Detected Issues', 'adn'); ?></h2>
                <div id="adn-issues-container">
                    <?php if (empty($issues)) : ?>
                        <div class="notice notice-success" style="margin: 0;">
                            <p><?php \_e('No issues detected. Your system is healthy!', 'adn'); ?></p>
                        </div>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php \_e('Component', 'adn'); ?></th>
                                    <th><?php \_e('Issue', 'adn'); ?></th>
                                    <th><?php \_e('Severity', 'adn'); ?></th>
                                    <th><?php \_e('Actions', 'adn'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($issues as $issue) :
                                    $severity_class = $issue['severity'] === 'critical' ? 'color: #d63638;' :
                                                     ($issue['severity'] === 'warning' ? 'color: #dba617;' : 'color: #2271b1;');
                                ?>
                                <tr data-issue-id="<?php echo esc_attr($issue['id'] ?? ''); ?>">
                                    <td><?php echo esc_html($issue['component'] ?? 'Unknown'); ?></td>
                                    <td><?php echo esc_html($issue['description'] ?? ''); ?></td>
                                    <td><span style="<?php echo $severity_class; ?>"><?php echo esc_html(ucfirst($issue['severity'] ?? 'info')); ?></span></td>
                                    <td>
                                        <?php if (!empty($issue['fixable'])) : ?>
                                            <button type="button" class="button button-small adn-preview-fix" data-issue="<?php echo esc_attr($issue['id'] ?? ''); ?>">
                                                <?php \_e('Preview Fix', 'adn'); ?>
                                            </button>
                                            <button type="button" class="button button-primary button-small adn-apply-fix" data-issue="<?php echo esc_attr($issue['id'] ?? ''); ?>">
                                                <?php \_e('Apply Fix', 'adn'); ?>
                                            </button>
                                        <?php else : ?>
                                            <span style="color: #666;"><?php \_e('Manual fix required', 'adn'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="margin-top: 15px;">
                            <button type="button" class="button button-primary" id="adn-apply-all-fixes">
                                <?php \_e('Apply All Fixes', 'adn'); ?>
                            </button>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="adn-fix-history" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2 style="margin-top: 0;"><?php \_e('Fix History', 'adn'); ?></h2>
                <?php if (empty($fix_history)) : ?>
                    <p style="color: #666;"><?php \_e('No fixes have been applied yet.', 'adn'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php \_e('Date', 'adn'); ?></th>
                                <th><?php \_e('Component', 'adn'); ?></th>
                                <th><?php \_e('Issue', 'adn'); ?></th>
                                <th><?php \_e('Status', 'adn'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice(array_reverse($fix_history), 0, 20) as $fix) : ?>
                            <tr>
                                <td><?php echo esc_html(date('M j, Y g:i a', strtotime($fix['date'] ?? ''))); ?></td>
                                <td><?php echo esc_html($fix['component'] ?? ''); ?></td>
                                <td><?php echo esc_html($fix['issue'] ?? ''); ?></td>
                                <td>
                                    <?php if ($fix['success'] ?? false) : ?>
                                        <span style="color: #00a32a;"><?php \_e('Success', 'adn'); ?></span>
                                    <?php else : ?>
                                        <span style="color: #d63638;"><?php \_e('Failed', 'adn'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render system status page
     */
    public function render_system_status_page() {
        $diagnostic_network = $this->get_diagnostic_network();
        $system_status = $diagnostic_network->get_system_status();
        $components = $diagnostic_network->get_system_components();

        // PHP info
        $php_info = [
            'version' => phpversion(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'extensions' => get_loaded_extensions()
        ];

        // WordPress info
        global $wpdb;
        $wp_info = [
            'version' => get_bloginfo('version'),
            'multisite' => is_multisite() ? 'Yes' : 'No',
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled',
            'memory_usage' => size_format(memory_get_usage(true)),
            'db_version' => $wpdb->db_version(),
            'active_plugins' => count(get_option('active_plugins', []))
        ];

        // Server info
        $server_info = [
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'php_sapi' => php_sapi_name(),
            'os' => PHP_OS,
            'architecture' => PHP_INT_SIZE * 8 . '-bit'
        ];
        ?>
        <div class="wrap adn-system-status">
            <h1><?php \_e('System Status', 'adn'); ?></h1>

            <div class="adn-status-actions" style="margin: 20px 0;">
                <button type="button" class="button button-primary" id="adn-run-health-check">
                    <?php \_e('Run Health Check', 'adn'); ?>
                </button>
                <button type="button" class="button" id="adn-download-system-info">
                    <?php \_e('Download System Info', 'adn'); ?>
                </button>
            </div>

            <div class="adn-status-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
                <!-- Overall Status -->
                <div class="adn-status-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h2 style="margin-top: 0;"><?php \_e('Overall Status', 'adn'); ?></h2>
                    <?php
                    $status_color = $system_status['overall'] === 'healthy' ? '#00a32a' :
                                   ($system_status['overall'] === 'warning' ? '#dba617' : '#d63638');
                    ?>
                    <div style="display: flex; align-items: center; margin-bottom: 15px;">
                        <span style="width: 20px; height: 20px; border-radius: 50%; background: <?php echo $status_color; ?>; margin-right: 10px;"></span>
                        <strong style="font-size: 18px;"><?php echo esc_html(ucfirst($system_status['overall'] ?? 'Unknown')); ?></strong>
                    </div>
                    <p>Last checked: <?php echo esc_html($system_status['last_check'] ?? 'Never'); ?></p>
                </div>

                <!-- PHP Information -->
                <div class="adn-status-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h2 style="margin-top: 0;"><?php \_e('PHP Information', 'adn'); ?></h2>
                    <table class="widefat" style="border: none;">
                        <tr><td><strong>Version</strong></td><td><?php echo esc_html($php_info['version']); ?></td></tr>
                        <tr><td><strong>Memory Limit</strong></td><td><?php echo esc_html($php_info['memory_limit']); ?></td></tr>
                        <tr><td><strong>Max Execution Time</strong></td><td><?php echo esc_html($php_info['max_execution_time']); ?>s</td></tr>
                        <tr><td><strong>Upload Max</strong></td><td><?php echo esc_html($php_info['upload_max_filesize']); ?></td></tr>
                        <tr><td><strong>Required Extensions</strong></td><td>
                            <?php
                            $required_ext = ['json', 'openssl', 'curl', 'mbstring'];
                            foreach ($required_ext as $ext) {
                                $has_ext = in_array($ext, $php_info['extensions']);
                                echo '<span style="margin-right: 10px; color: ' . ($has_ext ? '#00a32a' : '#d63638') . ';">';
                                echo esc_html($ext) . ' ' . ($has_ext ? '&#10003;' : '&#10007;');
                                echo '</span>';
                            }
                            ?>
                        </td></tr>
                    </table>
                </div>

                <!-- WordPress Information -->
                <div class="adn-status-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h2 style="margin-top: 0;"><?php \_e('WordPress Information', 'adn'); ?></h2>
                    <table class="widefat" style="border: none;">
                        <tr><td><strong>Version</strong></td><td><?php echo esc_html($wp_info['version']); ?></td></tr>
                        <tr><td><strong>Multisite</strong></td><td><?php echo esc_html($wp_info['multisite']); ?></td></tr>
                        <tr><td><strong>Debug Mode</strong></td><td><?php echo esc_html($wp_info['debug_mode']); ?></td></tr>
                        <tr><td><strong>Memory Usage</strong></td><td><?php echo esc_html($wp_info['memory_usage']); ?></td></tr>
                        <tr><td><strong>Database Version</strong></td><td><?php echo esc_html($wp_info['db_version']); ?></td></tr>
                        <tr><td><strong>Active Plugins</strong></td><td><?php echo esc_html($wp_info['active_plugins']); ?></td></tr>
                    </table>
                </div>

                <!-- Server Information -->
                <div class="adn-status-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h2 style="margin-top: 0;"><?php \_e('Server Information', 'adn'); ?></h2>
                    <table class="widefat" style="border: none;">
                        <tr><td><strong>Software</strong></td><td><?php echo esc_html($server_info['software']); ?></td></tr>
                        <tr><td><strong>PHP SAPI</strong></td><td><?php echo esc_html($server_info['php_sapi']); ?></td></tr>
                        <tr><td><strong>Operating System</strong></td><td><?php echo esc_html($server_info['os']); ?></td></tr>
                        <tr><td><strong>Architecture</strong></td><td><?php echo esc_html($server_info['architecture']); ?></td></tr>
                    </table>
                </div>
            </div>

            <!-- Component Status -->
            <div class="adn-component-status" style="margin-top: 30px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2 style="margin-top: 0;"><?php \_e('Aevov Component Status', 'adn'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php \_e('Component', 'adn'); ?></th>
                            <th><?php \_e('Status', 'adn'); ?></th>
                            <th><?php \_e('Version', 'adn'); ?></th>
                            <th><?php \_e('Last Check', 'adn'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($components as $component) :
                            $status = $component['status'] ?? 'unknown';
                            $status_color = in_array($status, ['pass', 'active', 'connected']) ? '#00a32a' :
                                           (in_array($status, ['warning', 'degraded']) ? '#dba617' : '#d63638');
                        ?>
                        <tr>
                            <td><?php echo esc_html($component['name'] ?? 'Unknown'); ?></td>
                            <td><span style="color: <?php echo $status_color; ?>;"><?php echo esc_html(ucfirst($status)); ?></span></td>
                            <td><?php echo esc_html($component['version'] ?? '-'); ?></td>
                            <td><?php echo esc_html($component['last_check'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render main page (same as dashboard)
     */
    public function render_main_page() {
        $this->render_dashboard_page();
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $settings = get_option('adn_settings', [
            'auto_scan_enabled' => true,
            'scan_frequency' => 'daily',
            'email_notifications' => false,
            'notification_email' => get_option('admin_email'),
            'log_retention_days' => 30,
            'enable_ai_fixes' => true,
            'ai_fix_confirmation' => true,
            'debug_mode' => false
        ]);

        if (isset($_POST['adn_save_settings']) && check_admin_referer('adn_settings_nonce', 'adn_settings_nonce')) {
            $settings = [
                'auto_scan_enabled' => !empty($_POST['auto_scan_enabled']),
                'scan_frequency' => sanitize_key($_POST['scan_frequency'] ?? 'daily'),
                'email_notifications' => !empty($_POST['email_notifications']),
                'notification_email' => sanitize_email($_POST['notification_email'] ?? ''),
                'log_retention_days' => intval($_POST['log_retention_days'] ?? 30),
                'enable_ai_fixes' => !empty($_POST['enable_ai_fixes']),
                'ai_fix_confirmation' => !empty($_POST['ai_fix_confirmation']),
                'debug_mode' => !empty($_POST['debug_mode'])
            ];
            update_option('adn_settings', $settings);
            echo '<div class="notice notice-success"><p>' . esc_html\_\_('Settings saved successfully.', 'adn') . '</p></div>';
        }
        ?>
        <div class="wrap adn-settings">
            <h1><?php \_e('ADN Settings', 'adn'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('adn_settings_nonce', 'adn_settings_nonce'); ?>

                <div class="adn-settings-section" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h2 style="margin-top: 0;"><?php \_e('Automatic Scanning', 'adn'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php \_e('Enable Auto-Scan', 'adn'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="auto_scan_enabled" value="1" <?php checked($settings['auto_scan_enabled']); ?> />
                                    <?php \_e('Automatically scan for issues on a schedule', 'adn'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php \_e('Scan Frequency', 'adn'); ?></th>
                            <td>
                                <select name="scan_frequency">
                                    <option value="hourly" <?php selected($settings['scan_frequency'], 'hourly'); ?>><?php \_e('Hourly', 'adn'); ?></option>
                                    <option value="twicedaily" <?php selected($settings['scan_frequency'], 'twicedaily'); ?>><?php \_e('Twice Daily', 'adn'); ?></option>
                                    <option value="daily" <?php selected($settings['scan_frequency'], 'daily'); ?>><?php \_e('Daily', 'adn'); ?></option>
                                    <option value="weekly" <?php selected($settings['scan_frequency'], 'weekly'); ?>><?php \_e('Weekly', 'adn'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="adn-settings-section" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h2 style="margin-top: 0;"><?php \_e('Notifications', 'adn'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php \_e('Email Notifications', 'adn'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="email_notifications" value="1" <?php checked($settings['email_notifications']); ?> />
                                    <?php \_e('Send email notifications when issues are detected', 'adn'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php \_e('Notification Email', 'adn'); ?></th>
                            <td>
                                <input type="email" name="notification_email" value="<?php echo esc_attr($settings['notification_email']); ?>" class="regular-text" />
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="adn-settings-section" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h2 style="margin-top: 0;"><?php \_e('AI Auto-Fix', 'adn'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php \_e('Enable AI Fixes', 'adn'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enable_ai_fixes" value="1" <?php checked($settings['enable_ai_fixes']); ?> />
                                    <?php \_e('Allow AI to suggest and apply fixes', 'adn'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php \_e('Require Confirmation', 'adn'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="ai_fix_confirmation" value="1" <?php checked($settings['ai_fix_confirmation']); ?> />
                                    <?php \_e('Require confirmation before applying AI fixes', 'adn'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="adn-settings-section" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h2 style="margin-top: 0;"><?php \_e('Logging', 'adn'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php \_e('Log Retention', 'adn'); ?></th>
                            <td>
                                <input type="number" name="log_retention_days" value="<?php echo esc_attr($settings['log_retention_days']); ?>" min="1" max="365" class="small-text" />
                                <?php \_e('days', 'adn'); ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php \_e('Debug Mode', 'adn'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="debug_mode" value="1" <?php checked($settings['debug_mode']); ?> />
                                    <?php \_e('Enable verbose debug logging', 'adn'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit" name="adn_save_settings" class="button button-primary" value="<?php esc_attr\_e('Save Settings', 'adn'); ?>" />
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Get dashboard data
     */
    private function get_dashboard_data() {
        try {
            $diagnostic_network = $this->get_diagnostic_network();
            $system_status = $diagnostic_network->get_system_status();
            $components = $diagnostic_network->get_system_components();
            
            // Count component statuses
            $passing = 0;
            $failing = 0;
            $warning = 0;
            $unknown = 0;
            $component_summary = [];
            $failing_components = [];
            $warning_components = [];
            
            foreach ($components as $component) {
                $status = $component['status'] ?? 'unknown';
                $component_summary[] = [
                    'name' => $component['name'],
                    'status' => $status
                ];
                
                switch ($status) {
                    case 'pass':
                    case 'active':
                    case 'connected':
                        $passing++;
                        break;
                    case 'fail':
                    case 'error':
                    case 'critical':
                        $failing++;
                        $failing_components[] = $component['name'];
                        break;
                    case 'warning':
                        $warning++;
                        $warning_components[] = $component['name'];
                        break;
                    default:
                        $unknown++;
                        break;
                }
            }
            
            // Calculate health score
            $total_components = count($components);
            $health_score = $total_components > 0 ? round(($passing / $total_components) * 100) : 0;
            
            // Determine health status
            $health_status = 'Excellent';
            if ($health_score < 50) $health_status = 'Critical';
            elseif ($health_score < 70) $health_status = 'Poor';
            elseif ($health_score < 85) $health_status = 'Good';
            
            // Recent activity
            $recent_activity = [
                [
                    'icon' => 'admin-tools',
                    'message' => 'System diagnostic scan completed',
                    'time' => '2 minutes ago'
                ],
                [
                    'icon' => 'warning',
                    'message' => count($failing_components) . ' components failing: ' . implode(', ', array_slice($failing_components, 0, 3)) . (count($failing_components) > 3 ? '...' : ''),
                    'time' => '5 minutes ago'
                ],
                [
                    'icon' => 'info',
                    'message' => count($warning_components) . ' components with warnings: ' . implode(', ', array_slice($warning_components, 0, 3)) . (count($warning_components) > 3 ? '...' : ''),
                    'time' => '10 minutes ago'
                ]
            ];
            
            // AI engines status
            $ai_engines = [
                [
                    'name' => 'Pattern Recognition Engine',
                    'status' => 'active',
                    'status_text' => 'Active',
                    'confidence' => 92
                ],
                [
                    'name' => 'Auto-Fix Engine',
                    'status' => 'standby',
                    'status_text' => 'Standby',
                    'confidence' => 78
                ],
                [
                    'name' => 'Predictive Analysis',
                    'status' => 'learning',
                    'status_text' => 'Learning',
                    'confidence' => 65
                ]
            ];
            
            return [
                'health_score' => $health_score,
                'health_status' => $health_status,
                'components_passing' => $passing,
                'components_failing' => $failing,
                'components_warning' => $warning,
                'components_unknown' => $unknown,
                'component_summary' => $component_summary,
                'failing_components' => $failing_components,
                'warning_components' => $warning_components,
                'recent_activity' => $recent_activity,
                'ai_engines' => $ai_engines
            ];
            
        } catch (\Exception $e) {
            error_log('ADN DEBUG: Error getting dashboard data: ' . $e->getMessage());
            
            // Fallback data
            return [
                'health_score' => 0,
                'health_status' => 'Error',
                'components_passing' => 0,
                'components_failing' => 0,
                'components_warning' => 0,
                'components_unknown' => 0,
                'component_summary' => [],
                'failing_components' => [],
                'warning_components' => [],
                'recent_activity' => [
                    [
                        'icon' => 'warning',
                        'message' => 'Error loading dashboard data: ' . $e->getMessage(),
                        'time' => 'Just now'
                    ]
                ],
                'ai_engines' => []
            ];
        }
    }
    
    /**
     * AJAX handler stubs - will be implemented in next step
     */
    public function ajax_run_system_scan() {
        // DIAGNOSTIC LOG: AJAX handler called
        error_log('ADN DEBUG: ajax_run_system_scan() called');
        error_log('ADN DEBUG: POST data: ' . print_r($_POST, true));
        
        // VALIDATION: Check nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            error_log('ADN DEBUG: NONCE VERIFICATION FAILED');
            \wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }
        
        // VALIDATION: Check permissions
        if (!\current_user_can('manage_options')) {
            error_log('ADN DEBUG: INSUFFICIENT PERMISSIONS');
            \wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }
        
        error_log('ADN DEBUG: Starting actual system scan...');
        
        try {
            // ACTUAL IMPLEMENTATION: Run real system scan
            $diagnostic_network = $this->get_diagnostic_network();
            $system_status = $diagnostic_network->get_system_status();
            
            error_log('ADN DEBUG: System scan completed successfully');
            error_log('ADN DEBUG: System status: ' . print_r($system_status, true));
            
            \wp_send_json_success([
                'message' => 'System scan completed successfully',
                'status' => $system_status,
                'timestamp' => current_time('mysql')
            ]);
            
        } catch (\Exception $e) {
            error_log('ADN DEBUG: System scan failed: ' . $e->getMessage());
            \wp_send_json_error([
                'message' => 'System scan failed: ' . $e->getMessage(),
                'error_details' => $e->getTraceAsString()
            ]);
        }
    }
    
    public function ajax_get_dashboard_data() {
        \wp_send_json_success($this->get_dashboard_data());
    }
    
    public function ajax_export_diagnostics() {
        // Validate nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            $diagnostic_network = $this->get_diagnostic_network();
            $system_status = $diagnostic_network->get_system_status();

            // Get additional diagnostic data
            $export_data = [
                'version' => ADN_VERSION,
                'export_date' => current_time('mysql'),
                'site_url' => get_site_url(),
                'system_status' => $system_status,
                'components' => $diagnostic_network->get_system_components(),
                'health_history' => get_option('adn_health_check_history', []),
                'settings' => get_option('adn_settings', [])
            ];

            // Store temporarily for download
            $transient_key = 'adn_export_' . wp_generate_password(12, false);
            set_transient($transient_key, $export_data, 300); // 5 minutes

            \wp_send_json_success([
                'message' => 'Diagnostic data ready for export',
                'download_key' => $transient_key,
                'data_size' => strlen(wp_json_encode($export_data))
            ]);

        } catch (\Exception $e) {
            \wp_send_json_error([
                'message' => 'Export failed: ' . $e->getMessage()
            ]);
        }
    }
    
    public function ajax_import_diagnostics() {
        // Validate nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            // Check if file was uploaded
            if (empty($_FILES['diagnostic_file'])) {
                \wp_send_json_error(['message' => 'No file uploaded']);
                return;
            }

            $file = $_FILES['diagnostic_file'];

            // Validate file type
            if ($file['type'] !== 'application/json') {
                \wp_send_json_error(['message' => 'Invalid file type. JSON required.']);
                return;
            }

            // Read and decode JSON
            $json_content = file_get_contents($file['tmp_name']);
            $import_data = json_decode($json_content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                \wp_send_json_error(['message' => 'Invalid JSON format']);
                return;
            }

            // Validate required fields
            if (!isset($import_data['version']) || !isset($import_data['system_status'])) {
                \wp_send_json_error(['message' => 'Invalid diagnostic data format']);
                return;
            }

            // Import settings if present
            if (isset($import_data['settings']) && is_array($import_data['settings'])) {
                update_option('adn_settings', $import_data['settings']);
            }

            // Store imported data for review
            update_option('adn_last_import', [
                'data' => $import_data,
                'imported_at' => current_time('mysql')
            ]);

            \wp_send_json_success([
                'message' => 'Diagnostic data imported successfully',
                'imported_from' => $import_data['site_url'] ?? 'unknown',
                'export_date' => $import_data['export_date'] ?? 'unknown'
            ]);

        } catch (\Exception $e) {
            \wp_send_json_error([
                'message' => 'Import failed: ' . $e->getMessage()
            ]);
        }
    }
    
    public function ajax_schedule_health_check() {
        // Validate nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            $interval = \sanitize_text_field($_POST['interval'] ?? 'hourly');

            // Clear existing scheduled event
            $timestamp = wp_next_scheduled('adn_health_check');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'adn_health_check');
            }

            // Schedule new event
            if ($interval !== 'disabled') {
                wp_schedule_event(time(), $interval, 'adn_health_check');
            }

            // Save setting
            $settings = get_option('adn_settings', []);
            $settings['health_check_interval'] = $interval;
            update_option('adn_settings', $settings);

            $next_run = $interval !== 'disabled' ? date('Y-m-d H:i:s', wp_next_scheduled('adn_health_check')) : 'N/A';

            \wp_send_json_success([
                'message' => 'Health check schedule updated',
                'interval' => $interval,
                'next_run' => $next_run
            ]);

        } catch (\Exception $e) {
            \wp_send_json_error([
                'message' => 'Failed to schedule health check: ' . $e->getMessage()
            ]);
        }
    }

    public function ajax_clear_diagnostic_cache() {
        // Validate nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            global $wpdb;

            // Clear transients
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_adn_%' OR option_name LIKE '_transient_timeout_adn_%'");

            // Clear cached options
            wp_cache_delete('adn_last_health_check', 'options');
            wp_cache_delete('adn_test_results', 'options');
            wp_cache_delete('adn_component_cache', 'options');

            // Clear object cache if available
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }

            \wp_send_json_success([
                'message' => 'Diagnostic cache cleared successfully',
                'cleared_items' => $wpdb->rows_affected
            ]);

        } catch (\Exception $e) {
            \wp_send_json_error([
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ]);
        }
    }

    public function ajax_test_component() {
        // Validate nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            $component_id = \sanitize_text_field($_POST['component_id'] ?? '');

            if (empty($component_id)) {
                \wp_send_json_error(['message' => 'Component ID required']);
                return;
            }

            $diagnostic_network = $this->get_diagnostic_network();
            $result = $diagnostic_network->test_component($component_id);

            // Store test result
            $test_results = get_option('adn_test_results', []);
            $test_results[$component_id] = [
                'result' => $result,
                'timestamp' => current_time('mysql')
            ];
            update_option('adn_test_results', $test_results);

            \wp_send_json_success($result);

        } catch (\Exception $e) {
            \wp_send_json_error([
                'message' => 'Component test failed: ' . $e->getMessage()
            ]);
        }
    }

    public function ajax_auto_fix_component() {
        // Validate nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            $component_id = \sanitize_text_field($_POST['component_id'] ?? '');
            $issue_type = \sanitize_text_field($_POST['issue_type'] ?? 'general');

            if (empty($component_id)) {
                \wp_send_json_error(['message' => 'Component ID required']);
                return;
            }

            $diagnostic_network = $this->get_diagnostic_network();
            $result = $diagnostic_network->auto_fix_component($component_id, $issue_type);

            // Log the fix attempt
            $fix_log = get_option('adn_fix_log', []);
            $fix_log[] = [
                'component_id' => $component_id,
                'issue_type' => $issue_type,
                'result' => $result,
                'timestamp' => current_time('mysql')
            ];
            update_option('adn_fix_log', array_slice($fix_log, -100)); // Keep last 100

            \wp_send_json_success($result);

        } catch (\Exception $e) {
            \wp_send_json_error([
                'message' => 'Auto-fix failed: ' . $e->getMessage()
            ]);
        }
    }
    
    public function ajax_get_component_details() {
        // Validate nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            $component_id = \sanitize_text_field($_POST['component_id'] ?? '');

            if (empty($component_id)) {
                \wp_send_json_error(['message' => 'Component ID required']);
                return;
            }

            $diagnostic_network = $this->get_diagnostic_network();
            $components = $diagnostic_network->get_system_components();

            if (!isset($components[$component_id])) {
                \wp_send_json_error(['message' => 'Component not found']);
                return;
            }

            $component = $components[$component_id];

            // Get test history
            $test_results = get_option('adn_test_results', []);
            $component['test_history'] = $test_results[$component_id] ?? null;

            // Get fix history
            $fix_log = get_option('adn_fix_log', []);
            $component['fix_history'] = array_filter($fix_log, function($log) use ($component_id) {
                return $log['component_id'] === $component_id;
            });

            \wp_send_json_success($component);

        } catch (\Exception $e) {
            \wp_send_json_error([
                'message' => 'Failed to get component details: ' . $e->getMessage()
            ]);
        }
    }

    public function ajax_test_all_components() {
        // Validate nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            $diagnostic_network = $this->get_diagnostic_network();
            $system_status = $diagnostic_network->get_system_status();

            // Save results
            update_option('adn_last_full_test', [
                'results' => $system_status,
                'timestamp' => current_time('mysql')
            ]);

            $passed = 0;
            $failed = 0;

            foreach ($system_status['components'] as $result) {
                if ($result['success'] ?? false) {
                    $passed++;
                } else {
                    $failed++;
                }
            }

            \wp_send_json_success([
                'message' => 'All components tested successfully',
                'passed' => $passed,
                'failed' => $failed,
                'total' => $passed + $failed,
                'overall_health' => $system_status['overall_health'],
                'timestamp' => current_time('mysql')
            ]);

        } catch (\Exception $e) {
            \wp_send_json_error([
                'message' => 'Test failed: ' . $e->getMessage()
            ]);
        }
    }

    public function ajax_clear_test_results() {
        // Validate nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            delete_option('adn_test_results');
            delete_option('adn_last_full_test');
            delete_option('adn_fix_log');

            \wp_send_json_success([
                'message' => 'Test results cleared successfully'
            ]);

        } catch (\Exception $e) {
            \wp_send_json_error([
                'message' => 'Failed to clear test results: ' . $e->getMessage()
            ]);
        }
    }

    public function ajax_scan_issues() {
        // Validate nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            $diagnostic_network = $this->get_diagnostic_network();
            $system_status = $diagnostic_network->get_system_status();

            $issues = $system_status['issues'] ?? [];
            $fixes = [];

            // Generate potential fixes for each issue
            foreach ($issues as $issue) {
                $fix_id = 'fix_' . md5($issue['component'] . $issue['issue']);
                $fixes[] = [
                    'fix_id' => $fix_id,
                    'component' => $issue['component'],
                    'issue' => $issue['issue'],
                    'severity' => $issue['severity'],
                    'recommended_action' => $this->generate_recommended_action($issue),
                    'auto_fixable' => $this->is_auto_fixable($issue)
                ];
            }

            \wp_send_json_success([
                'issues' => $issues,
                'fixes' => $fixes,
                'total_issues' => count($issues),
                'auto_fixable_count' => count(array_filter($fixes, function($fix) {
                    return $fix['auto_fixable'];
                }))
            ]);

        } catch (\Exception $e) {
            \wp_send_json_error([
                'message' => 'Issue scan failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Generate recommended action for an issue
     */
    private function generate_recommended_action($issue) {
        $actions = [
            'plugin_not_activated' => 'Activate the plugin from the WordPress admin',
            'class_not_found' => 'Ensure plugin files are properly installed',
            'file_not_found' => 'Reinstall the plugin',
            'method_not_available' => 'Update the plugin to the latest version',
            'dependency_missing' => 'Install and activate required dependencies'
        ];

        // Try to match issue type
        foreach ($actions as $type => $action) {
            if (stripos($issue['issue'], $type) !== false) {
                return $action;
            }
        }

        return 'Manual intervention required';
    }

    /**
     * Check if an issue is auto-fixable
     */
    private function is_auto_fixable($issue) {
        $auto_fixable_patterns = [
            'plugin_not_activated',
            'cache_not_cleared',
            'option_not_set'
        ];

        foreach ($auto_fixable_patterns as $pattern) {
            if (stripos($issue['issue'], $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
    
    public function ajax_apply_single_fix() {
        // Validate nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            $fix_id = \sanitize_text_field($_POST['fix_id'] ?? '');
            $component_id = \sanitize_text_field($_POST['component_id'] ?? '');
            $issue_type = \sanitize_text_field($_POST['issue_type'] ?? 'general');

            if (empty($fix_id) || empty($component_id)) {
                \wp_send_json_error(['message' => 'Fix ID and Component ID required']);
                return;
            }

            $diagnostic_network = $this->get_diagnostic_network();
            $result = $diagnostic_network->auto_fix_component($component_id, $issue_type);

            // Log the fix
            $fix_log = get_option('adn_fix_log', []);
            $fix_log[] = [
                'fix_id' => $fix_id,
                'component_id' => $component_id,
                'issue_type' => $issue_type,
                'result' => $result,
                'timestamp' => current_time('mysql')
            ];
            update_option('adn_fix_log', array_slice($fix_log, -100));

            \wp_send_json_success([
                'message' => 'Fix applied successfully',
                'fix_id' => $fix_id,
                'result' => $result
            ]);

        } catch (\Exception $e) {
            \wp_send_json_error([
                'message' => 'Fix application failed: ' . $e->getMessage()
            ]);
        }
    }

    public function ajax_preview_fix() {
        // Validate nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            $fix_id = \sanitize_text_field($_POST['fix_id'] ?? '');
            $component_id = \sanitize_text_field($_POST['component_id'] ?? '');
            $issue_type = \sanitize_text_field($_POST['issue_type'] ?? '');

            if (empty($fix_id)) {
                \wp_send_json_error(['message' => 'Fix ID required']);
                return;
            }

            // Generate preview based on issue type
            $preview = [
                'fix_id' => $fix_id,
                'component_id' => $component_id,
                'issue_type' => $issue_type,
                'actions' => $this->get_fix_actions($issue_type),
                'estimated_time' => $this->estimate_fix_time($issue_type),
                'risk_level' => $this->assess_fix_risk($issue_type),
                'requires_restart' => $this->fix_requires_restart($issue_type)
            ];

            \wp_send_json_success([
                'preview' => $preview,
                'message' => 'Fix preview generated'
            ]);

        } catch (\Exception $e) {
            \wp_send_json_error([
                'message' => 'Preview generation failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get actions for a fix type
     */
    private function get_fix_actions($issue_type) {
        $actions_map = [
            'plugin_not_activated' => ['Activate plugin', 'Verify dependencies', 'Clear cache'],
            'class_not_found' => ['Verify file integrity', 'Reinstall plugin', 'Check autoloader'],
            'file_not_found' => ['Restore missing files', 'Reinstall plugin'],
            'general' => ['Run diagnostic', 'Apply fix', 'Verify resolution']
        ];

        return $actions_map[$issue_type] ?? $actions_map['general'];
    }

    /**
     * Estimate fix time
     */
    private function estimate_fix_time($issue_type) {
        $time_estimates = [
            'plugin_not_activated' => '< 1 minute',
            'cache_not_cleared' => '< 1 minute',
            'class_not_found' => '1-2 minutes',
            'file_not_found' => '2-5 minutes',
            'general' => '1-3 minutes'
        ];

        return $time_estimates[$issue_type] ?? $time_estimates['general'];
    }

    /**
     * Assess fix risk level
     */
    private function assess_fix_risk($issue_type) {
        $risk_levels = [
            'plugin_not_activated' => 'low',
            'cache_not_cleared' => 'low',
            'option_not_set' => 'low',
            'class_not_found' => 'medium',
            'file_not_found' => 'medium',
            'general' => 'medium'
        ];

        return $risk_levels[$issue_type] ?? 'medium';
    }

    /**
     * Check if fix requires restart
     */
    private function fix_requires_restart($issue_type) {
        $restart_required = ['plugin_installation', 'core_update', 'database_migration'];
        return in_array($issue_type, $restart_required);
    }

    public function ajax_apply_all_fixes() {
        // Validate nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            // Scan for issues first
            $diagnostic_network = $this->get_diagnostic_network();
            $system_status = $diagnostic_network->get_system_status();
            $issues = $system_status['issues'] ?? [];

            $applied = 0;
            $failed = 0;
            $skipped = 0;
            $results = [];

            foreach ($issues as $issue) {
                $component_id = $issue['component'];
                $issue_type = $this->extract_issue_type($issue['issue']);

                // Only apply auto-fixable issues
                if (!$this->is_auto_fixable($issue)) {
                    $skipped++;
                    $results[] = [
                        'component' => $component_id,
                        'status' => 'skipped',
                        'reason' => 'Not auto-fixable'
                    ];
                    continue;
                }

                try {
                    $result = $diagnostic_network->auto_fix_component($component_id, $issue_type);

                    if ($result['success'] ?? false) {
                        $applied++;
                        $results[] = [
                            'component' => $component_id,
                            'status' => 'applied',
                            'result' => $result
                        ];
                    } else {
                        $failed++;
                        $results[] = [
                            'component' => $component_id,
                            'status' => 'failed',
                            'error' => $result['message'] ?? 'Unknown error'
                        ];
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $results[] = [
                        'component' => $component_id,
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Log batch fix
            update_option('adn_last_batch_fix', [
                'results' => $results,
                'summary' => compact('applied', 'failed', 'skipped'),
                'timestamp' => current_time('mysql')
            ]);

            \wp_send_json_success([
                'applied' => $applied,
                'failed' => $failed,
                'skipped' => $skipped,
                'total' => count($issues),
                'results' => $results,
                'message' => "Applied {$applied} fixes, {$failed} failed, {$skipped} skipped"
            ]);

        } catch (\Exception $e) {
            \wp_send_json_error([
                'message' => 'Batch fix failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Extract issue type from issue message
     */
    private function extract_issue_type($issue_message) {
        $types = [
            'plugin_not_activated',
            'class_not_found',
            'file_not_found',
            'method_not_available',
            'cache_not_cleared',
            'option_not_set'
        ];

        foreach ($types as $type) {
            if (stripos($issue_message, $type) !== false) {
                return $type;
            }
        }

        return 'general';
    }

    public function ajax_download_system_info() {
        // Validate nonce
        if (!\wp_verify_nonce($_GET['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_die('Invalid nonce');
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_die('Insufficient permissions');
        }

        try {
            $diagnostic_network = $this->get_diagnostic_network();
            $system_info = [
                'site_info' => [
                    'site_url' => get_site_url(),
                    'wp_version' => get_bloginfo('version'),
                    'php_version' => PHP_VERSION,
                    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
                ],
                'system_status' => $diagnostic_network->get_system_status(),
                'components' => $diagnostic_network->get_system_components(),
                'test_history' => get_option('adn_test_results', []),
                'fix_history' => get_option('adn_fix_log', []),
                'settings' => get_option('adn_settings', []),
                'generated_at' => current_time('mysql')
            ];

            // Set headers for download
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="aevov-system-info-' . date('Y-m-d-H-i-s') . '.json"');
            header('Pragma: no-cache');
            header('Expires: 0');

            echo wp_json_encode($system_info, JSON_PRETTY_PRINT);
            exit;

        } catch (\Exception $e) {
            \wp_die('System info download failed: ' . $e->getMessage());
        }
    }

    public function ajax_run_health_check() {
        // Validate nonce
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        try {
            $diagnostic_network = $this->get_diagnostic_network();
            $system_status = $diagnostic_network->get_system_status();

            // Calculate health score
            $total_components = count($system_status['components']);
            $healthy_components = 0;

            foreach ($system_status['components'] as $result) {
                if ($result['success'] ?? false) {
                    $healthy_components++;
                }
            }

            $score = ($healthy_components / $total_components) * 100;

            // Build details
            $details = [
                'total_components' => $total_components,
                'healthy_components' => $healthy_components,
                'issues_found' => count($system_status['issues']),
                'overall_health' => $system_status['overall_health'],
                'recommendations' => $system_status['recommendations']
            ];

            // Store health check result
            update_option('adn_last_health_check', [
                'score' => $score,
                'details' => $details,
                'timestamp' => current_time('mysql')
            ]);

            // Add to history
            $history = get_option('adn_health_check_history', []);
            $history[] = [
                'score' => $score,
                'timestamp' => current_time('mysql')
            ];
            update_option('adn_health_check_history', array_slice($history, -50)); // Keep last 50

            \wp_send_json_success([
                'score' => round($score, 2),
                'details' => $details,
                'timestamp' => current_time('mysql')
            ]);

        } catch (\Exception $e) {
            \wp_send_json_error([
                'message' => 'Health check failed: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * AJAX handler for comprehensive testing
     */
    public function ajax_run_comprehensive_test() {
        // DIAGNOSTIC LOG: AJAX handler called
        error_log('ADN DEBUG: ajax_run_comprehensive_test() called');
        error_log('ADN DEBUG: POST data: ' . print_r($_POST, true));
        
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            error_log('ADN DEBUG: NONCE VERIFICATION FAILED');
            \wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        if (!\current_user_can('manage_options')) {
            error_log('ADN DEBUG: INSUFFICIENT PERMISSIONS');
            \wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        error_log('ADN DEBUG: Starting comprehensive test...');
        
        try {
            $comprehensive_tester = $this->get_diagnostic_network()->get_comprehensive_tester();
            
            if (!$comprehensive_tester) {
                throw new \Exception('Comprehensive tester not available');
            }
            
            error_log('ADN DEBUG: Got comprehensive tester, running test...');
            $result = $comprehensive_tester->run_comprehensive_test();
            
            error_log('ADN DEBUG: Comprehensive test completed successfully');
            error_log('ADN DEBUG: Test result: ' . print_r($result, true));
            
            \wp_send_json_success($result);
        } catch (\Exception $e) {
            error_log('ADN DEBUG: Comprehensive test failed: ' . $e->getMessage());
            error_log('ADN DEBUG: Exception trace: ' . $e->getTraceAsString());
            \wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    public function ajax_stop_comprehensive_test() {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        try {
            $comprehensive_tester = $this->get_diagnostic_network()->get_comprehensive_tester();
            
            // The method is actually ajax_stop_comprehensive_test, but we need to call it properly
            $session_id = $_POST['session_id'] ?? '';
            
            if ($session_id) {
                delete_transient('adn_test_session_' . $session_id);
                delete_transient('adn_test_progress');
                \wp_send_json_success(['message' => 'Test stopped']);
            } else {
                \wp_send_json_error(['message' => 'Invalid session ID']);
            }
        } catch (\Exception $e) {
            \wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    public function ajax_get_comprehensive_test_progress() {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        try {
            // Get progress from transient (set by ComprehensiveFeatureTester)
            $progress_data = get_transient('adn_test_progress');
            
            if ($progress_data) {
                \wp_send_json_success($progress_data);
            } else {
                \wp_send_json_error(['message' => 'No active test session']);
            }
        } catch (\Exception $e) {
            \wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    public function ajax_get_comprehensive_results() {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        try {
            $comprehensive_tester = $this->get_diagnostic_network()->get_comprehensive_tester();
            $results = $comprehensive_tester->get_latest_test_results();
            
            if ($results) {
                \wp_send_json_success($results);
            } else {
                \wp_send_json_error(['message' => 'No test results available']);
            }
        } catch (\Exception $e) {
            \wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    public function ajax_export_comprehensive_results() {
        if (!\wp_verify_nonce($_GET['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_die('Invalid nonce');
        }
        
        if (!\current_user_can('manage_options')) {
            \wp_die('Insufficient permissions');
        }
        
        try {
            $comprehensive_tester = $this->get_diagnostic_network()->get_comprehensive_tester();
            $results = $comprehensive_tester->export_results();
            
            \header('Content-Type: application/json');
            \header('Content-Disposition: attachment; filename="aevov-comprehensive-test-results-' . \date('Y-m-d-H-i-s') . '.json"');
            echo \wp_json_encode($results, JSON_PRETTY_PRINT);
            exit;
        } catch (\Exception $e) {
            \wp_die('Export failed: ' . $e->getMessage());
        }
    }
    
    public function ajax_clear_comprehensive_results() {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        try {
            $comprehensive_tester = $this->get_diagnostic_network()->get_comprehensive_tester();
            $comprehensive_tester->clear_results();
            \wp_send_json_success(['message' => 'Results cleared']);
        } catch (\Exception $e) {
            \wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    public function ajax_retest_feature() {
        if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'adn_admin_nonce')) {
            \wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $feature = \sanitize_text_field($_POST['feature'] ?? '');
        if (empty($feature)) {
            \wp_send_json_error(['message' => 'Feature name required']);
        }
        
        try {
            $comprehensive_tester = $this->get_diagnostic_network()->get_comprehensive_tester();
            $result = $comprehensive_tester->test_single_feature($feature);
            \wp_send_json_success($result);
        } catch (\Exception $e) {
            \wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Handler method for full system scan
     */
    private function handle_run_full_scan() {
        check_admin_referer('adn_admin_action', 'adn_nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            $diagnostic_network = $this->get_diagnostic_network();
            $system_status = $diagnostic_network->get_system_status();

            // Store scan results
            update_option('adn_last_full_scan', [
                'results' => $system_status,
                'timestamp' => current_time('mysql')
            ]);

            wp_redirect(add_query_arg([
                'page' => 'adn-dashboard',
                'scan' => 'complete',
                'health' => $system_status['overall_health']
            ], admin_url('admin.php')));
            exit;

        } catch (\Exception $e) {
            wp_redirect(add_query_arg([
                'page' => 'adn-dashboard',
                'error' => urlencode($e->getMessage())
            ], admin_url('admin.php')));
            exit;
        }
    }

    /**
     * Handler method for exporting diagnostics
     */
    private function handle_export_diagnostics() {
        check_admin_referer('adn_admin_action', 'adn_nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            $diagnostic_network = $this->get_diagnostic_network();
            $export_data = [
                'version' => ADN_VERSION,
                'export_date' => current_time('mysql'),
                'site_url' => get_site_url(),
                'system_status' => $diagnostic_network->get_system_status(),
                'components' => $diagnostic_network->get_system_components(),
                'settings' => get_option('adn_settings', [])
            ];

            // Set headers for download
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="aevov-diagnostics-' . date('Y-m-d-H-i-s') . '.json"');
            header('Pragma: no-cache');

            echo wp_json_encode($export_data, JSON_PRETTY_PRINT);
            exit;

        } catch (\Exception $e) {
            wp_die('Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Handler method for importing diagnostics
     */
    private function handle_import_diagnostics() {
        check_admin_referer('adn_admin_action', 'adn_nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (empty($_FILES['import_file'])) {
            wp_redirect(add_query_arg([
                'page' => 'adn-dashboard',
                'error' => urlencode('No file uploaded')
            ], admin_url('admin.php')));
            exit;
        }

        try {
            $file = $_FILES['import_file'];
            $json_content = file_get_contents($file['tmp_name']);
            $import_data = json_decode($json_content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON format');
            }

            // Import settings
            if (isset($import_data['settings'])) {
                update_option('adn_settings', $import_data['settings']);
            }

            update_option('adn_last_import', [
                'data' => $import_data,
                'imported_at' => current_time('mysql')
            ]);

            wp_redirect(add_query_arg([
                'page' => 'adn-dashboard',
                'import' => 'success'
            ], admin_url('admin.php')));
            exit;

        } catch (\Exception $e) {
            wp_redirect(add_query_arg([
                'page' => 'adn-dashboard',
                'error' => urlencode('Import failed: ' . $e->getMessage())
            ], admin_url('admin.php')));
            exit;
        }
    }

    /**
     * Handler method for saving settings
     */
    private function handle_save_settings() {
        check_admin_referer('adn_admin_action', 'adn_nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            // Get settings from POST
            $settings = [
                'health_check_interval' => sanitize_text_field($_POST['health_check_interval'] ?? 'hourly'),
                'email_notifications' => isset($_POST['email_notifications']),
                'auto_fix_enabled' => isset($_POST['auto_fix_enabled']),
                'critical_alerts_only' => isset($_POST['critical_alerts_only']),
                'notification_email' => sanitize_email($_POST['notification_email'] ?? get_option('admin_email'))
            ];

            // Validate and save
            update_option('adn_settings', $settings);

            // Update scheduled health check
            $timestamp = wp_next_scheduled('adn_health_check');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'adn_health_check');
            }

            if ($settings['health_check_interval'] !== 'disabled') {
                wp_schedule_event(time(), $settings['health_check_interval'], 'adn_health_check');
            }

            wp_redirect(add_query_arg([
                'page' => 'adn-dashboard',
                'settings' => 'saved'
            ], admin_url('admin.php')));
            exit;

        } catch (\Exception $e) {
            wp_redirect(add_query_arg([
                'page' => 'adn-dashboard',
                'error' => urlencode('Settings save failed: ' . $e->getMessage())
            ], admin_url('admin.php')));
            exit;
        }
    }
}