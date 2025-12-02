<?php

namespace ADN\Core;

use ADN\AI\AIEngineManager;
use ADN\Testing\ComponentTester;
use ADN\Testing\ComprehensiveFeatureTester;
use ADN\Visualization\ArchitectureMap;
use ADN\Admin\DiagnosticAdmin;

/**
 * Main Diagnostic Network Class
 * 
 * Orchestrates the entire diagnostic system with AI-powered testing,
 * visual component mapping, and auto-fixing capabilities.
 */
class DiagnosticNetwork {
    
    private static $instance = null;
    private $ai_manager;
    private $component_tester;
    private $comprehensive_tester;
    private $architecture_map;
    private $admin;
    private $system_components = [];
    
    /**
     * Singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize the diagnostic network
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_components();
        $this->discover_system_components();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', [$this, 'init_system']);
        // REMOVED: admin_menu hook - handled by DiagnosticAdmin
        add_action('wp_ajax_adn_test_component', [$this, 'ajax_test_component']);
        add_action('wp_ajax_adn_auto_fix', [$this, 'ajax_auto_fix']);
        add_action('wp_ajax_adn_get_system_status', [$this, 'ajax_get_system_status']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // DIAGNOSTIC LOG: Hook registration
        error_log('ADN DEBUG: Core hooks registered');
    }
    
    /**
     * Load core components
     */
    private function load_components() {
        $this->ai_manager = new AIEngineManager();
        $this->component_tester = new ComponentTester();
        $this->comprehensive_tester = new ComprehensiveFeatureTester();
        $this->architecture_map = new ArchitectureMap();
        $this->admin = new DiagnosticAdmin();
        
        // DIAGNOSTIC LOG: Initialize admin interface
        error_log('ADN DEBUG: Initializing DiagnosticAdmin');
        $this->admin->init();
    }
    
    /**
     * Initialize the system
     */
    public function init_system() {
        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Initialize AI engines
        $this->ai_manager->initialize();
        
        // Start system monitoring
        $this->start_monitoring();
    }
    
    /**
     * Discover all Aevov system components
     */
    private function discover_system_components() {
        $this->system_components = [
            // AevovPatternSyncProtocol components
            'aps_core' => [
                'name' => 'APS Core',
                'type' => 'core',
                'group' => 'core',
                'class' => 'APS\\Analysis\\APS_Plugin',
                'file' => 'AevovPatternSyncProtocol/aevov-pattern-sync-protocol.php', // Corrected path
                'dependencies' => ['aps_loader', 'aps_metrics_db'],
                'tests' => ['plugin_activation', 'dependency_check', 'method_availability']
            ],
            'aps_loader' => [
                'name' => 'APS Loader',
                'type' => 'system',
                'group' => 'core',
                'class' => 'APS\\Core\\Loader',
                'file' => 'AevovPatternSyncProtocol/Includes/Core/Loader.php',
                'dependencies' => [],
                'tests' => ['file_exists', 'class_exists', 'autoload_test']
            ],
            'aps_metrics_db' => [
                'name' => 'APS Metrics Database',
                'type' => 'database',
                'group' => 'data',
                'class' => 'APS\\DB\\MetricsDB',
                'file' => 'AevovPatternSyncProtocol/Includes/DB/MetricsDB.php',
                'dependencies' => [],
                'tests' => ['database_connection', 'table_exists', 'method_availability']
            ],
            'aps_admin' => [
                'name' => 'APS Admin',
                'type' => 'interface',
                'group' => 'admin',
                'class' => 'APS\\Admin\\APS_Admin',
                'file' => 'AevovPatternSyncProtocol/Includes/Admin/APS_Admin.php',
                'dependencies' => ['aps_core'],
                'tests' => ['admin_interface', 'method_availability', 'hook_registration']
            ],
            'bloom_integration' => [
                'name' => 'BLOOM Integration',
                'type' => 'integration',
                'group' => 'integration',
                'class' => 'APS\\Integration\\BloomIntegration',
                'file' => 'AevovPatternSyncProtocol/Includes/Integration/BloomIntegration.php',
                'dependencies' => ['aps_core', 'bloom_pattern_recognition'],
                'tests' => ['connection_test', 'sync_test', 'method_compatibility']
            ],
            
            // BLOOM Pattern Recognition components
            'bloom_pattern_recognition' => [
                'name' => 'BLOOM Pattern Recognition',
                'type' => 'engine',
                'group' => 'processing',
                'class' => 'BLOOM\\Core',
                'file' => 'bloom-pattern-recognition/bloom-pattern-system.php', // Corrected path
                'dependencies' => [],
                'tests' => ['plugin_activation', 'pattern_processing', 'tensor_operations']
            ],
            'bloom_tensor_processor' => [
                'name' => 'BLOOM Tensor Processor',
                'type' => 'processor',
                'group' => 'processing',
                'class' => 'BLOOM\\Processing\\TensorProcessor',
                'file' => 'bloom-pattern-recognition/includes/processing/class-tensor-processor.php',
                'dependencies' => ['bloom_pattern_recognition'],
                'tests' => ['tensor_processing', 'memory_usage', 'performance']
            ],
            'bloom_network_manager' => [
                'name' => 'BLOOM Network Manager',
                'type' => 'manager',
                'group' => 'processing',
                'class' => 'BLOOM\\Network\\NetworkManager',
                'file' => 'bloom-pattern-recognition/includes/network/class-network-manager.php',
                'dependencies' => ['bloom_pattern_recognition'],
                'tests' => ['network_connectivity', 'message_queue', 'load_balancing']
            ],
            
            // APS Tools components
            'aps_tools' => [
                'name' => 'APS Tools',
                'type' => 'manager',
                'group' => 'admin',
                'class' => 'APSTools\\APSTools',
                'file' => 'aps-tools/aps-tools.php', // Corrected path
                'dependencies' => ['aps_core'],
                'tests' => ['plugin_activation', 'tool_availability', 'integration_test']
            ],
            
            // Onboarding System
            'aevov_onboarding' => [
                'name' => 'Aevov Onboarding System',
                'type' => 'interface',
                'group' => 'admin',
                'class' => 'AevovOnboarding\\AevovOnboardingSystem',
                'file' => 'aevov-onboarding-system/aevov-onboarding.php', // Corrected path
                'dependencies' => ['aps_core', 'bloom_pattern_recognition', 'aps_tools'],
                'tests' => ['onboarding_flow', 'step_validation', 'dependency_checking']
            ]
        ];
    }
    
    // REMOVED: add_admin_menu method - handled by DiagnosticAdmin class
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('adn/v1', '/test-component/(?P<component>[a-zA-Z0-9_-]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_test_component'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
        
        register_rest_route('adn/v1', '/auto-fix/(?P<component>[a-zA-Z0-9_-]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_auto_fix'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
        
        register_rest_route('adn/v1', '/system-status', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_system_status'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
    
    /**
     * AJAX handler for component testing
     */
    public function ajax_test_component() {
        check_ajax_referer('adn_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $component_id = sanitize_text_field($_POST['component_id']);
        $result = $this->test_component($component_id);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for auto-fix
     */
    public function ajax_auto_fix() {
        check_ajax_referer('adn_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $component_id = sanitize_text_field($_POST['component_id']);
        $issue_type = sanitize_text_field($_POST['issue_type']);
        
        $result = $this->auto_fix_component($component_id, $issue_type);
        
        wp_send_json($result);
    }
    
    /**
     * Test a specific component
     */
    public function test_component($component_id) {
        if (!isset($this->system_components[$component_id])) {
            return [
                'success' => false,
                'message' => 'Component not found',
                'component_id' => $component_id
            ];
        }
        
        $component = $this->system_components[$component_id];
        return $this->component_tester->test_component($component);
    }
    
    /**
     * Auto-fix a component using AI
     */
    public function auto_fix_component($component_id, $issue_type) {
        if (!isset($this->system_components[$component_id])) {
            return [
                'success' => false,
                'message' => 'Component not found'
            ];
        }
        
        $component = $this->system_components[$component_id];
        return $this->ai_manager->auto_fix($component, $issue_type);
    }
    
    /**
     * Get comprehensive system status
     */
    public function get_system_status() {
        $status = [
            'overall_health' => 'unknown',
            'components' => [],
            'issues' => [],
            'recommendations' => [],
            'timestamp' => current_time('mysql')
        ];
        
        $healthy_count = 0;
        $total_count = count($this->system_components);
        
        foreach ($this->system_components as $id => $component) {
            $test_result = $this->component_tester->test_component($component);
            $status['components'][$id] = $test_result;
            
            if ($test_result['success'] ?? false) {
                $healthy_count++;
            } else {
                error_log("ADN DEBUG: Component '{$id}' test failed. Result: " . print_r($test_result, true));
                $status['issues'][] = [
                    'component' => $id,
                    'issue' => $test_result['message'] ?? 'Unknown issue',
                    'severity' => $test_result['severity'] ?? 'medium'
                ];
            }
        }
        
        // Calculate overall health
        $health_percentage = ($healthy_count / $total_count) * 100;
        if ($health_percentage >= 90) {
            $status['overall_health'] = 'excellent';
        } elseif ($health_percentage >= 75) {
            $status['overall_health'] = 'good';
        } elseif ($health_percentage >= 50) {
            $status['overall_health'] = 'fair';
        } else {
            $status['overall_health'] = 'poor';
        }
        
        // Generate AI recommendations
        $status['recommendations'] = $this->ai_manager->generate_recommendations($status['issues']);
        
        return $status;
    }
    
    /**
     * Start system monitoring
     */
    private function start_monitoring() {
        // Schedule regular health checks
        if (!wp_next_scheduled('adn_health_check')) {
            wp_schedule_event(time(), 'hourly', 'adn_health_check');
        }
        
        add_action('adn_health_check', [$this, 'perform_health_check']);
    }
    
    /**
     * Perform scheduled health check
     */
    public function perform_health_check() {
        $status = $this->get_system_status();
        
        // Store status in database
        update_option('adn_last_health_check', $status);
        
        // Send alerts if critical issues found
        $critical_issues = array_filter($status['issues'], function($issue) {
            return $issue['severity'] === 'critical';
        });
        
        if (!empty($critical_issues)) {
            $this->send_critical_alert($critical_issues);
        }
    }
    
    /**
     * Send critical alert
     */
    private function send_critical_alert($issues) {
        $admin_email = get_option('admin_email');
        $subject = 'Aevov System Critical Issues Detected';
        
        $message = "Critical issues have been detected in your Aevov system:\n\n";
        foreach ($issues as $issue) {
            $message .= "- Component: {$issue['component']}\n";
            $message .= "  Issue: {$issue['issue']}\n\n";
        }
        
        $message .= "Please check your Diagnostic Network dashboard for more details and auto-fix options.";
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        if (is_admin()) return;
        
        wp_enqueue_script(
            'adn-frontend',
            ADN_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            ADN_VERSION,
            true
        );
        
        wp_localize_script('adn-frontend', 'adn_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adn_nonce'),
            'rest_url' => rest_url('adn/v1/'),
            'rest_nonce' => wp_create_nonce('wp_rest')
        ]);
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'aevov-diagnostic-network') === false && strpos($hook, 'adn-') === false) {
            return;
        }
        
        wp_enqueue_script('d3', 'https://d3js.org/d3.v7.min.js', [], '7.0.0', true);
        
        wp_enqueue_script(
            'adn-admin',
            ADN_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'd3'],
            ADN_VERSION,
            true
        );
        
        wp_enqueue_script(
            'adn-comprehensive-testing',
            ADN_PLUGIN_URL . 'assets/js/comprehensive-testing.js',
            ['jquery', 'd3', 'adn-admin'],
            ADN_VERSION,
            true
        );
        
        wp_enqueue_style(
            'adn-admin',
            ADN_PLUGIN_URL . 'assets/css/admin.css',
            [],
            ADN_VERSION
        );
        
        wp_enqueue_style(
            'adn-comprehensive-testing',
            ADN_PLUGIN_URL . 'assets/css/comprehensive-testing.css',
            ['adn-admin'],
            ADN_VERSION
        );
        
        wp_localize_script('adn-admin', 'adn_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adn_nonce'),
            'rest_url' => rest_url('adn/v1/'),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'components' => $this->system_components
        ]);
    }
    
    /**
     * REST API handlers
     */
    public function rest_test_component($request) {
        $component_id = $request['component'];
        return $this->test_component($component_id);
    }
    
    public function rest_auto_fix($request) {
        $component_id = $request['component'];
        $issue_type = $request->get_param('issue_type') ?? 'general';
        return $this->auto_fix_component($component_id, $issue_type);
    }
    
    public function rest_get_system_status($request) {
        return $this->get_system_status();
    }
    
    /**
     * Get system components
     */
    public function get_system_components() {
        return $this->system_components;
    }
    
    /**
     * Get AI manager
     */
    public function get_ai_manager() {
        return $this->ai_manager;
    }
    
    /**
     * Get component tester
     */
    public function get_component_tester() {
        return $this->component_tester;
    }
    
    /**
     * Get architecture map
     */
    public function get_architecture_map() {
        return $this->architecture_map;
    }
    
    /**
     * Get comprehensive tester
     */
    public function get_comprehensive_tester() {
        return $this->comprehensive_tester;
    }
}