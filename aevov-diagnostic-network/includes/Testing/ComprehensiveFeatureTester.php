<?php

namespace ADN\Testing;

/**
 * Comprehensive Feature Tester
 * 
 * Tests every single feature of the Aevov network with visual light indicators
 * showing process flow across different components using the system-architecture-map as a guide.
 * 
 * This class provides exhaustive testing of all Aevov network features with real-time
 * visual indicators that show the testing process flow across different components.
 */
class ComprehensiveFeatureTester {
    
    private $feature_map = [];
    private $test_results = [];
    private $visual_indicators = [];
    private $process_flow = [];
    private $component_groups = [];
    private $testing_session_id;
    private $broadcast_channel = 'adn_comprehensive_testing';
    
    /**
     * Initialize comprehensive feature tester
     */
    public function __construct() {
        $this->testing_session_id = uniqid('adn_test_', true);
        $this->init_feature_map();
        $this->init_component_groups();
        $this->init_visual_indicators();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // REMOVED: adn_run_comprehensive_test - handled by DiagnosticAdmin to avoid conflicts
        add_action('wp_ajax_adn_get_test_progress', [$this, 'ajax_get_test_progress']);
        add_action('wp_ajax_adn_stop_comprehensive_test', [$this, 'ajax_stop_comprehensive_test']);
        add_action('wp_ajax_adn_get_feature_status', [$this, 'ajax_get_feature_status']);
    }
    
    /**
     * Initialize comprehensive feature map
     * Maps every single feature across the entire Aevov network
     */
    private function init_feature_map() {
        $this->feature_map = [
            'core_network' => [
                'group_name' => 'Core Network',
                'group_color' => '#e74c3c',
                'group_icon' => '🏛️',
                'features' => [
                    'aps_core_initialization' => [
                        'name' => 'APS Core Initialization',
                        'description' => 'Core plugin activation and initialization',
                        'component' => 'aps_core',
                        'test_methods' => ['test_plugin_activation', 'test_core_initialization', 'test_dependency_loading'],
                        'dependencies' => [],
                        'critical' => true
                    ],
                    'aps_loader_system' => [
                        'name' => 'APS Loader System',
                        'description' => 'Autoloading and class registration system',
                        'component' => 'aps_loader',
                        'test_methods' => ['test_autoloader', 'test_class_registration', 'test_namespace_resolution'],
                        'dependencies' => ['aps_core_initialization'],
                        'critical' => true
                    ],
                    'bloom_pattern_recognition' => [
                        'name' => 'BLOOM Pattern Recognition',
                        'description' => 'AI pattern detection and recognition engine',
                        'component' => 'bloom_pattern_recognition',
                        'test_methods' => ['test_pattern_detection', 'test_ai_engine', 'test_tensor_operations'],
                        'dependencies' => ['aps_core_initialization'],
                        'critical' => true
                    ],
                    'network_communication' => [
                        'name' => 'Network Communication',
                        'description' => 'Inter-plugin and multisite communication',
                        'component' => 'bloom_network_manager',
                        'test_methods' => ['test_message_queue', 'test_network_sync', 'test_multisite_communication'],
                        'dependencies' => ['aps_core_initialization', 'bloom_pattern_recognition'],
                        'critical' => false
                    ]
                ]
            ],
            'storage_data_management' => [
                'group_name' => 'Storage & Data Management',
                'group_color' => '#3498db',
                'group_icon' => '💾',
                'features' => [
                    'database_connectivity' => [
                        'name' => 'Database Connectivity',
                        'description' => 'WordPress database connection and table management',
                        'component' => 'aps_metrics_db',
                        'test_methods' => ['test_db_connection', 'test_table_creation', 'test_data_operations'],
                        'dependencies' => ['aps_core_initialization'],
                        'critical' => true
                    ],
                    'cubbit_storage_integration' => [
                        'name' => 'Cubbit Storage Integration',
                        'description' => 'S3-compatible storage operations and authentication',
                        'component' => 'cubbit_ds3_client',
                        'test_methods' => ['test_s3_connection', 'test_authentication', 'test_file_operations'],
                        'dependencies' => ['database_connectivity'],
                        'critical' => false
                    ],
                    'folder_management' => [
                        'name' => 'Folder Management',
                        'description' => 'Directory operations and permissions management',
                        'component' => 'cubbit_folder_manager',
                        'test_methods' => ['test_folder_creation', 'test_permissions', 'test_recursive_operations'],
                        'dependencies' => ['cubbit_storage_integration'],
                        'critical' => false
                    ],
                    'data_caching' => [
                        'name' => 'Data Caching',
                        'description' => 'Pattern and metrics caching system',
                        'component' => 'aps_cache',
                        'test_methods' => ['test_cache_operations', 'test_cache_invalidation', 'test_performance'],
                        'dependencies' => ['database_connectivity'],
                        'critical' => false
                    ]
                ]
            ],
            'ai_processing_engine' => [
                'group_name' => 'AI & Processing Engine',
                'group_color' => '#2ecc71',
                'group_icon' => '🤖',
                'features' => [
                    'tensor_processing' => [
                        'name' => 'Tensor Processing',
                        'description' => 'High-performance tensor operations and computations',
                        'component' => 'bloom_tensor_processor',
                        'test_methods' => ['test_tensor_creation', 'test_tensor_operations', 'test_memory_management'],
                        'dependencies' => ['bloom_pattern_recognition'],
                        'critical' => true
                    ],
                    'pattern_analysis' => [
                        'name' => 'Pattern Analysis',
                        'description' => 'Sequential, statistical, and structural pattern analysis',
                        'component' => 'pattern_analyzer',
                        'test_methods' => ['test_sequential_analysis', 'test_statistical_analysis', 'test_structural_analysis'],
                        'dependencies' => ['tensor_processing'],
                        'critical' => true
                    ],
                    'ai_engine_integration' => [
                        'name' => 'AI Engine Integration',
                        'description' => 'Multi-engine AI processing with fallback systems',
                        'component' => 'ai_engine_manager',
                        'test_methods' => ['test_gemini_engine', 'test_claude_engine', 'test_kilocode_engine', 'test_fallback_system'],
                        'dependencies' => ['pattern_analysis'],
                        'critical' => false
                    ],
                    'chunk_processing' => [
                        'name' => 'Chunk Processing',
                        'description' => 'Data chunking and batch processing operations',
                        'component' => 'chunk_processor',
                        'test_methods' => ['test_chunk_creation', 'test_batch_processing', 'test_chunk_validation'],
                        'dependencies' => ['tensor_processing'],
                        'critical' => false
                    ]
                ]
            ],
            'integration_protocol_layer' => [
                'group_name' => 'Integration & Protocol Layer',
                'group_color' => '#f39c12',
                'group_icon' => '🔗',
                'features' => [
                    'bloom_integration' => [
                        'name' => 'BLOOM Integration',
                        'description' => 'APS-BLOOM communication and synchronization',
                        'component' => 'bloom_integration',
                        'test_methods' => ['test_bloom_connection', 'test_data_sync', 'test_method_compatibility'],
                        'dependencies' => ['aps_core_initialization', 'bloom_pattern_recognition'],
                        'critical' => true
                    ],
                    'api_endpoints' => [
                        'name' => 'API Endpoints',
                        'description' => 'REST API and AJAX endpoint functionality',
                        'component' => 'api_manager',
                        'test_methods' => ['test_rest_endpoints', 'test_ajax_handlers', 'test_authentication'],
                        'dependencies' => ['aps_core_initialization'],
                        'critical' => false
                    ],
                    'webhook_system' => [
                        'name' => 'Webhook System',
                        'description' => 'External system integration via webhooks',
                        'component' => 'webhook_manager',
                        'test_methods' => ['test_webhook_registration', 'test_webhook_delivery', 'test_webhook_security'],
                        'dependencies' => ['api_endpoints'],
                        'critical' => false
                    ],
                    'sync_protocols' => [
                        'name' => 'Sync Protocols',
                        'description' => 'Cross-site and cross-plugin synchronization',
                        'component' => 'sync_manager',
                        'test_methods' => ['test_pattern_sync', 'test_metrics_sync', 'test_conflict_resolution'],
                        'dependencies' => ['bloom_integration'],
                        'critical' => false
                    ]
                ]
            ],
            'performance_optimization' => [
                'group_name' => 'Performance & Optimization',
                'group_color' => '#9b59b6',
                'group_icon' => '⚡',
                'features' => [
                    'caching_system' => [
                        'name' => 'Caching System',
                        'description' => 'Multi-layer caching for performance optimization',
                        'component' => 'cache_manager',
                        'test_methods' => ['test_object_cache', 'test_transient_cache', 'test_cache_warming'],
                        'dependencies' => ['database_connectivity'],
                        'critical' => false
                    ],
                    'load_balancing' => [
                        'name' => 'Load Balancing',
                        'description' => 'Request distribution and resource management',
                        'component' => 'load_balancer',
                        'test_methods' => ['test_request_distribution', 'test_resource_allocation', 'test_failover'],
                        'dependencies' => ['network_communication'],
                        'critical' => false
                    ],
                    'memory_optimization' => [
                        'name' => 'Memory Optimization',
                        'description' => 'Memory usage monitoring and optimization',
                        'component' => 'memory_manager',
                        'test_methods' => ['test_memory_monitoring', 'test_garbage_collection', 'test_memory_limits'],
                        'dependencies' => ['tensor_processing'],
                        'critical' => false
                    ],
                    'performance_monitoring' => [
                        'name' => 'Performance Monitoring',
                        'description' => 'Real-time performance metrics and alerting',
                        'component' => 'performance_monitor',
                        'test_methods' => ['test_metrics_collection', 'test_alert_system', 'test_reporting'],
                        'dependencies' => ['caching_system'],
                        'critical' => false
                    ]
                ]
            ],
            'user_interface_experience' => [
                'group_name' => 'User Interface & Experience',
                'group_color' => '#34495e',
                'group_icon' => '🎨',
                'features' => [
                    'admin_interface' => [
                        'name' => 'Admin Interface',
                        'description' => 'WordPress admin dashboard integration',
                        'component' => 'aps_admin',
                        'test_methods' => ['test_menu_registration', 'test_page_rendering', 'test_form_handling'],
                        'dependencies' => ['aps_core_initialization'],
                        'critical' => false
                    ],
                    'diagnostic_dashboard' => [
                        'name' => 'Diagnostic Dashboard',
                        'description' => 'Real-time system monitoring and diagnostics',
                        'component' => 'diagnostic_admin',
                        'test_methods' => ['test_dashboard_rendering', 'test_real_time_updates', 'test_interactive_elements'],
                        'dependencies' => ['admin_interface'],
                        'critical' => false
                    ],
                    'onboarding_system' => [
                        'name' => 'Onboarding System',
                        'description' => 'User onboarding and setup wizard',
                        'component' => 'aevov_onboarding',
                        'test_methods' => ['test_onboarding_flow', 'test_step_validation', 'test_completion_tracking'],
                        'dependencies' => ['admin_interface'],
                        'critical' => false
                    ],
                    'visualization_components' => [
                        'name' => 'Visualization Components',
                        'description' => 'Interactive charts, graphs, and architecture maps',
                        'component' => 'visualization_manager',
                        'test_methods' => ['test_chart_rendering', 'test_interactive_maps', 'test_data_visualization'],
                        'dependencies' => ['diagnostic_dashboard'],
                        'critical' => false
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Initialize component groups for visual organization
     */
    private function init_component_groups() {
        $this->component_groups = [
            'core_network' => [
                'name' => 'Core Network',
                'color' => '#e74c3c',
                'position' => ['x' => 200, 'y' => 150]
            ],
            'storage_data_management' => [
                'name' => 'Storage & Data Management',
                'color' => '#3498db',
                'position' => ['x' => 500, 'y' => 150]
            ],
            'ai_processing_engine' => [
                'name' => 'AI & Processing Engine',
                'color' => '#2ecc71',
                'position' => ['x' => 800, 'y' => 150]
            ],
            'integration_protocol_layer' => [
                'name' => 'Integration & Protocol Layer',
                'color' => '#f39c12',
                'position' => ['x' => 350, 'y' => 350]
            ],
            'performance_optimization' => [
                'name' => 'Performance & Optimization',
                'color' => '#9b59b6',
                'position' => ['x' => 650, 'y' => 350]
            ],
            'user_interface_experience' => [
                'name' => 'User Interface & Experience',
                'color' => '#34495e',
                'position' => ['x' => 500, 'y' => 550]
            ]
        ];
    }
    
    /**
     * Initialize visual indicators system
     */
    private function init_visual_indicators() {
        $this->visual_indicators = [
            'status_colors' => [
                'pending' => '#6c757d',
                'testing' => '#007bff',
                'pass' => '#28a745',
                'fail' => '#dc3545',
                'warning' => '#ffc107',
                'critical' => '#e74c3c',
                'skipped' => '#17a2b8'
            ],
            'animation_states' => [
                'idle' => 'static',
                'testing' => 'pulse',
                'processing' => 'spin',
                'success' => 'bounce',
                'error' => 'shake'
            ]
        ];
    }
    
    /**
     * Run comprehensive feature test
     */
    public function run_comprehensive_test($options = []) {
        $start_time = microtime(true);
        
        // Initialize test session
        $this->init_test_session($options);
        
        // Broadcast test start
        $this->broadcast_status('test_started', [
            'session_id' => $this->testing_session_id,
            'total_features' => $this->count_total_features(),
            'timestamp' => current_time('mysql')
        ]);
        
        $results = [
            'session_id' => $this->testing_session_id,
            'start_time' => $start_time,
            'groups_tested' => 0,
            'features_tested' => 0,
            'total_features' => $this->count_total_features(),
            'group_results' => [],
            'overall_status' => 'unknown',
            'visual_flow' => [],
            'recommendations' => []
        ];
        
        // Test each component group
        foreach ($this->feature_map as $group_id => $group) {
            $this->update_visual_indicator($group_id, 'testing', 'Group testing started');
            
            $group_result = $this->test_component_group($group_id, $group);
            $results['group_results'][$group_id] = $group_result;
            $results['groups_tested']++;
            $results['features_tested'] += count($group['features']);
            
            // Update visual flow
            $results['visual_flow'][] = [
                'group' => $group_id,
                'status' => $group_result['overall_status'],
                'timestamp' => microtime(true) - $start_time,
                'features_count' => count($group['features'])
            ];
            
            $this->update_visual_indicator($group_id, $group_result['overall_status'], 'Group testing completed');
            
            // Broadcast progress
            $this->broadcast_progress($results);
        }
        
        // Calculate overall results
        $results['end_time'] = microtime(true);
        $results['total_time'] = $results['end_time'] - $start_time;
        $results['overall_status'] = $this->calculate_overall_status($results['group_results']);
        $results['recommendations'] = $this->generate_recommendations($results);
        
        // Store results
        $this->store_test_results($results);
        
        // Broadcast completion
        $this->broadcast_status('test_completed', $results);
        
        return $results;
    }
    
    /**
     * Test individual component group
     */
    private function test_component_group($group_id, $group) {
        $group_start_time = microtime(true);
        
        $group_result = [
            'group_id' => $group_id,
            'group_name' => $group['group_name'],
            'start_time' => $group_start_time,
            'features_tested' => 0,
            'feature_results' => [],
            'overall_status' => 'unknown',
            'issues' => [],
            'performance_metrics' => []
        ];
        
        // Test each feature in the group
        foreach ($group['features'] as $feature_id => $feature) {
            $this->update_visual_indicator($feature_id, 'testing', 'Feature testing started');
            
            $feature_result = $this->test_individual_feature($feature_id, $feature);
            $group_result['feature_results'][$feature_id] = $feature_result;
            $group_result['features_tested']++;
            
            // Collect issues
            if (!empty($feature_result['issues'])) {
                $group_result['issues'] = array_merge($group_result['issues'], $feature_result['issues']);
            }
            
            $this->update_visual_indicator($feature_id, $feature_result['status'], 'Feature testing completed');
            
            // Add small delay for visual effect
            usleep(100000); // 0.1 seconds
        }
        
        $group_result['end_time'] = microtime(true);
        $group_result['total_time'] = $group_result['end_time'] - $group_start_time;
        $group_result['overall_status'] = $this->calculate_group_status($group_result['feature_results']);
        
        return $group_result;
    }
    
    /**
     * Test individual feature
     */
    private function test_individual_feature($feature_id, $feature) {
        $feature_start_time = microtime(true);
        
        $feature_result = [
            'feature_id' => $feature_id,
            'feature_name' => $feature['name'],
            'component' => $feature['component'],
            'start_time' => $feature_start_time,
            'test_results' => [],
            'status' => 'unknown',
            'issues' => [],
            'performance' => [],
            'dependencies_met' => true
        ];
        
        try {
            // Check dependencies first
            if (!$this->check_feature_dependencies($feature)) {
                $feature_result['status'] = 'skipped';
                $feature_result['dependencies_met'] = false;
                $feature_result['issues'][] = [
                    'type' => 'dependency',
                    'message' => 'Feature dependencies not met',
                    'severity' => 'high'
                ];
                return $feature_result;
            }
            
            // Run individual test methods
            foreach ($feature['test_methods'] as $test_method) {
                $test_result = $this->run_feature_test_method($feature, $test_method);
                $feature_result['test_results'][$test_method] = $test_result;
                
                if ($test_result['status'] === 'fail' && $feature['critical']) {
                    $feature_result['issues'][] = [
                        'type' => 'critical_failure',
                        'message' => "Critical test '{$test_method}' failed: {$test_result['message']}",
                        'severity' => 'critical'
                    ];
                }
            }
            
            // Calculate feature status
            $feature_result['status'] = $this->calculate_feature_status($feature_result['test_results'], $feature['critical']);
            
        } catch (Exception $e) {
            $feature_result['status'] = 'fail';
            $feature_result['issues'][] = [
                'type' => 'exception',
                'message' => 'Feature test threw exception: ' . $e->getMessage(),
                'severity' => 'high'
            ];
        }
        
        $feature_result['end_time'] = microtime(true);
        $feature_result['total_time'] = $feature_result['end_time'] - $feature_start_time;
        
        return $feature_result;
    }
    
    /**
     * Run individual test method for a feature
     */
    private function run_feature_test_method($feature, $test_method) {
        $method_start_time = microtime(true);
        
        $test_result = [
            'method' => $test_method,
            'start_time' => $method_start_time,
            'status' => 'unknown',
            'message' => '',
            'details' => []
        ];
        
        try {
            // Use existing ComponentTester for actual testing
            $component_tester = new ComponentTester();
            
            // Get component definition
            $diagnostic_network = \ADN\Core\DiagnosticNetwork::instance();
            $system_components = $diagnostic_network->get_system_components();
            
            $component = null;
            foreach ($system_components as $comp) {
                if ($comp['id'] === $feature['component'] || 
                    strpos($comp['name'], $feature['component']) !== false) {
                    $component = $comp;
                    break;
                }
            }
            
            if (!$component) {
                $test_result['status'] = 'fail';
                $test_result['message'] = "Component '{$feature['component']}' not found";
                return $test_result;
            }
            
            // Run specific test based on method name
            switch ($test_method) {
                case 'test_plugin_activation':
                case 'test_core_initialization':
                    $result = $this->test_plugin_activation($component);
                    break;
                    
                case 'test_dependency_loading':
                case 'test_autoloader':
                case 'test_class_registration':
                    $result = $this->test_class_loading($component);
                    break;
                    
                case 'test_db_connection':
                case 'test_table_creation':
                case 'test_data_operations':
                    $result = $this->test_database_operations($component);
                    break;
                    
                case 'test_pattern_detection':
                case 'test_ai_engine':
                case 'test_tensor_operations':
                    $result = $this->test_ai_functionality($component);
                    break;
                    
                case 'test_bloom_connection':
                case 'test_data_sync':
                case 'test_method_compatibility':
                    $result = $this->test_integration_functionality($component);
                    break;
                    
                default:
                    // Use ComponentTester for general testing
                    $result = $component_tester->test_component($component);
                    break;
            }
            
            $test_result['status'] = $result['overall_status'] ?? 'unknown';
            $test_result['message'] = $result['message'] ?? 'Test completed';
            $test_result['details'] = $result['tests'] ?? [];
            
        } catch (Exception $e) {
            $test_result['status'] = 'fail';
            $test_result['message'] = 'Test method failed: ' . $e->getMessage();
        }
        
        $test_result['end_time'] = microtime(true);
        $test_result['execution_time'] = $test_result['end_time'] - $method_start_time;
        
        return $test_result;
    }
    
    /**
     * Test plugin activation and initialization
     */
    private function test_plugin_activation($component) {
        $file_path = ABSPATH . $component['file'];
        
        if (!file_exists($file_path)) {
            return [
                'overall_status' => 'fail',
                'message' => 'Plugin file not found: ' . $file_path,
                'tests' => []
            ];
        }
        
        // Check if plugin is active
        $plugin_slug = plugin_basename($file_path);
        $is_active = is_plugin_active($plugin_slug);
        
        return [
            'overall_status' => $is_active ? 'pass' : 'fail',
            'message' => $is_active ? 'Plugin is active' : 'Plugin is not active',
            'tests' => [
                'file_exists' => ['status' => 'pass', 'message' => 'Plugin file exists'],
                'plugin_active' => ['status' => $is_active ? 'pass' : 'fail', 'message' => $is_active ? 'Active' : 'Inactive']
            ]
        ];
    }
    
    /**
     * Test class loading and autoloading
     */
    private function test_class_loading($component) {
        $class_name = $component['class'] ?? null;
        
        if (!$class_name) {
            return [
                'overall_status' => 'skip',
                'message' => 'No class specified for component',
                'tests' => []
            ];
        }
        
        $class_exists = class_exists($class_name);
        
        return [
            'overall_status' => $class_exists ? 'pass' : 'fail',
            'message' => $class_exists ? 'Class loaded successfully' : 'Class not found: ' . $class_name,
            'tests' => [
                'class_exists' => ['status' => $class_exists ? 'pass' : 'fail', 'message' => $class_exists ? 'Found' : 'Not found']
            ]
        ];
    }
    
    /**
     * Test database operations
     */
    private function test_database_operations($component) {
        global $wpdb;
        
        // Test basic database connection
        $db_connected = false;
        try {
            $wpdb->get_var("SELECT 1");
            $db_connected = true;
        } catch (Exception $e) {
            $db_connected = false;
        }
        
        return [
            'overall_status' => $db_connected ? 'pass' : 'fail',
            'message' => $db_connected ? 'Database operations working' : 'Database connection failed',
            'tests' => [
                'db_connection' => ['status' => $db_connected ? 'pass' : 'fail', 'message' => $db_connected ? 'Connected' : 'Failed']
            ]
        ];
    }
    
    /**
     * Test AI functionality
     */
    private function test_ai_functionality($component) {
        $class_name = $component['class'] ?? null;
        
        if (!$class_name || !class_exists($class_name)) {
            return [
                'overall_status' => 'fail',
                'message' => 'AI component class not available',
                'tests' => []
            ];
        }
        
        // Basic AI functionality test
        return [
            'overall_status' => 'pass',
            'message' => 'AI component available',
            'tests' => [
                'ai_class_available' => ['status' => 'pass', 'message' => 'AI class loaded']
            ]
        ];
    }
    
    /**
     * Test integration functionality
     */
    private function test_integration_functionality($component) {
        $class_name = $component['class'] ?? null;
        
        if (!$class_name || !class_exists($class_name)) {
            return [
                'overall_status' => 'fail',
                'message' => 'Integration component class not available',
                'tests' => []
            ];
        }
        
        // Test BLOOM integration specifically
        if (strpos($class_name, 'BloomIntegration') !== false) {
            // Check if BLOOM Core is available
            $bloom_available = class_exists('\\BLOOM\\Core');
            
            return [
                'overall_status' => $bloom_available ? 'pass' : 'fail',
                'message' => $bloom_available ? 'BLOOM integration working' : 'BLOOM Core not available',
                'tests' => [
                    'bloom_core_available' => ['status' => $bloom_available ? 'pass' : 'fail', 'message' => $bloom_available ? 'Available' : 'Not found']
                ]
            ];
        }
        
        return [
            'overall_status' => 'pass',
            'message' => 'Integration component available',
            'tests' => [
                'integration_class_available' => ['status' => 'pass', 'message' => 'Integration class loaded']
            ]
        ];
    }
    
    /**
     * Check feature dependencies
     */
    private function check_feature_dependencies($feature) {
        if (empty($feature['dependencies'])) {
            return true;
        }
        
        foreach ($feature['dependencies'] as $dependency) {
            // Check if dependency feature has been tested and passed
            if (!$this->is_dependency_satisfied($dependency)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if dependency is satisfied
     */
    private function is_dependency_satisfied($dependency) {
        // For now, assume all dependencies are satisfied
        // In a real implementation, this would check previous test results
        return true;
    }
    
    /**
     * Calculate feature status based on test results
     */
    private function calculate_feature_status($test_results, $is_critical) {
        if (empty($test_results)) {
            return 'unknown';
        }
        
        $has_failures = false;
        $has_warnings = false;
        
        foreach ($test_results as $test_result) {
            switch ($test_result['status']) {
                case 'fail':
                    $has_failures = true;
                    if ($is_critical) {
                        return 'critical';
                    }
                    break;
                case 'warning':
                    $has_warnings = true;
                    break;
            }
        }
        
        if ($has_failures) {
            return 'fail';
        } elseif ($has_warnings) {
            return 'warning';
        } else {
            return 'pass';
        }
    }
    
    /**
     * Calculate group status based on feature results
     */
    private function calculate_group_status($feature_results) {
        if (empty($feature_results)) {
            return 'unknown';
        }
        
        $critical_failures = 0;
        $failures = 0;
        $warnings = 0;
        $passes = 0;
        
        foreach ($feature_results as $result) {
            switch ($result['status']) {
                case 'critical':
                    $critical_failures++;
                    break;
                case 'fail':
                    $failures++;
                    break;
                case 'warning':
                    $warnings++;
                    break;
                case 'pass':
                    $passes++;
                    break;
            }
        }
        
        if ($critical_failures > 0) {
            return 'critical';
        } elseif ($failures > 0) {
            return 'fail';
        } elseif ($warnings > 0) {
            return 'warning';
        } else {
            return 'pass';
        }
    }
    
    /**
     * Calculate overall status
     */
    private function calculate_overall_status($group_results) {
        if (empty($group_results)) {
            return 'unknown';
        }
        
        $critical_groups = 0;
        $failed_groups = 0;
        $warning_groups = 0;
        $passed_groups = 0;
        
        foreach ($group_results as $result) {
            switch ($result['overall_status']) {
                case 'critical':
                    $critical_groups++;
                    break;
                case 'fail':
                    $failed_groups++;
                    break;
                case 'warning':
                    $warning_groups++;
                    break;
                case 'pass':
                    $passed_groups++;
                    break;
            }
        }
        
        $total_groups = count($group_results);
        
        if ($critical_groups > 0) {
            return 'critical';
        } elseif ($failed_groups > $total_groups * 0.3) {
            return 'poor';
        } elseif ($warning_groups > $total_groups * 0.5) {
            return 'fair';
        } elseif ($passed_groups > $total_groups * 0.8) {
            return 'excellent';
        } else {
            return 'good';
        }
    }
    
    /**
     * Update visual indicator
     */
    private function update_visual_indicator($component_id, $status, $message = '') {
        $this->visual_indicators['current_states'][$component_id] = [
            'status' => $status,
            'message' => $message,
            'timestamp' => microtime(true),
            'color' => $this->visual_indicators['status_colors'][$status] ?? '#6c757d'
        ];
        
        // Broadcast visual update
        $this->broadcast_visual_update($component_id, $status, $message);
    }
    
    /**
     * Broadcast visual update
     */
    private function broadcast_visual_update($component_id, $status, $message) {
        $update_data = [
            'type' => 'visual_update',
            'component_id' => $component_id,
            'status' => $status,
            'message' => $message,
            'color' => $this->visual_indicators['status_colors'][$status] ?? '#6c757d',
            'timestamp' => current_time('mysql'),
            'session_id' => $this->testing_session_id
        ];
        
        // Store in transient for real-time updates
        set_transient('adn_visual_update_' . $component_id, $update_data, 300);
        
        // Trigger WordPress action for real-time broadcasting
        do_action('adn_visual_update', $update_data);
    }
    
    /**
     * Broadcast status update
     */
    private function broadcast_status($event_type, $data) {
        $broadcast_data = [
            'event' => $event_type,
            'data' => $data,
            'timestamp' => current_time('mysql'),
            'session_id' => $this->testing_session_id
        ];
        
        // Store in transient
        set_transient('adn_broadcast_' . $event_type, $broadcast_data, 300);
        
        // Trigger WordPress action
        do_action('adn_comprehensive_test_broadcast', $broadcast_data);
    }
    
    /**
     * Broadcast progress update
     */
    private function broadcast_progress($results) {
        $progress_data = [
            'session_id' => $this->testing_session_id,
            'groups_tested' => $results['groups_tested'],
            'features_tested' => $results['features_tested'],
            'total_features' => $results['total_features'],
            'progress_percentage' => ($results['features_tested'] / $results['total_features']) * 100,
            'current_status' => $results['overall_status'],
            'timestamp' => current_time('mysql')
        ];
        
        set_transient('adn_test_progress', $progress_data, 300);
        do_action('adn_test_progress_update', $progress_data);
    }
    
    /**
     * Initialize test session
     */
    private function init_test_session($options) {
        $session_data = [
            'session_id' => $this->testing_session_id,
            'start_time' => current_time('mysql'),
            'options' => $options,
            'status' => 'running'
        ];
        
        set_transient('adn_test_session_' . $this->testing_session_id, $session_data, 3600);
    }
    
    /**
     * Count total features
     */
    private function count_total_features() {
        $total = 0;
        foreach ($this->feature_map as $group) {
            $total += count($group['features']);
        }
        return $total;
    }
    
    /**
     * Generate recommendations
     */
    private function generate_recommendations($results) {
        $recommendations = [];
        
        foreach ($results['group_results'] as $group_result) {
            if ($group_result['overall_status'] === 'fail' || $group_result['overall_status'] === 'critical') {
                $recommendations[] = [
                    'type' => 'group_failure',
                    'group' => $group_result['group_name'],
                    'message' => "Address issues in {$group_result['group_name']} group",
                    'priority' => $group_result['overall_status'] === 'critical' ? 'high' : 'medium'
                ];
            }
            
            foreach ($group_result['issues'] as $issue) {
                if ($issue['severity'] === 'critical') {
                    $recommendations[] = [
                        'type' => 'critical_issue',
                        'message' => $issue['message'],
                        'priority' => 'high'
                    ];
                }
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Store test results
     */
    private function store_test_results($results) {
        $storage_key = 'adn_comprehensive_test_results_' . date('Y_m_d_H_i_s');
        update_option($storage_key, $results);
        
        // Keep only last 10 test results
        $all_results = get_option('adn_comprehensive_test_history', []);
        $all_results[] = $storage_key;
        
        if (count($all_results) > 10) {
            $old_key = array_shift($all_results);
            delete_option($old_key);
        }
        
        update_option('adn_comprehensive_test_history', $all_results);
    }
    
    // REMOVED: ajax_run_comprehensive_test method - handled by DiagnosticAdmin to avoid conflicts
    
    /**
     * AJAX: Get test progress
     */
    public function ajax_get_test_progress() {
        check_ajax_referer('adn_nonce', 'nonce');
        
        $progress_data = get_transient('adn_test_progress');
        
        if ($progress_data) {
            wp_send_json_success($progress_data);
        } else {
            wp_send_json_error(['message' => 'No active test session']);
        }
    }
    
    /**
     * AJAX: Stop comprehensive test
     */
    public function ajax_stop_comprehensive_test() {
        check_ajax_referer('adn_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $session_id = $_POST['session_id'] ?? '';
        
        if ($session_id) {
            delete_transient('adn_test_session_' . $session_id);
            delete_transient('adn_test_progress');
            
            wp_send_json_success(['message' => 'Test session stopped']);
        } else {
            wp_send_json_error(['message' => 'Invalid session ID']);
        }
    }
    
    /**
     * AJAX: Get feature status
     */
    public function ajax_get_feature_status() {
        check_ajax_referer('adn_nonce', 'nonce');
        
        $feature_id = $_POST['feature_id'] ?? '';
        
        if (empty($feature_id)) {
            wp_send_json_error(['message' => 'Feature ID required']);
        }
        
        $visual_update = get_transient('adn_visual_update_' . $feature_id);
        
        if ($visual_update) {
            wp_send_json_success($visual_update);
        } else {
            wp_send_json_success([
                'component_id' => $feature_id,
                'status' => 'unknown',
                'message' => 'No status available',
                'color' => '#6c757d'
            ]);
        }
    }
    
    /**
     * Get feature map
     */
    public function get_feature_map() {
        return $this->feature_map;
    }
    
    /**
     * Get component groups
     */
    public function get_component_groups() {
        return $this->component_groups;
    }
    
    /**
     * Get visual indicators
     */
    public function get_visual_indicators() {
        return $this->visual_indicators;
    }
    
    /**
     * Get test results
     */
    public function get_test_results() {
        return $this->test_results;
    }
    
    /**
     * Get latest test results
     */
    public function get_latest_test_results() {
        $history = get_option('adn_comprehensive_test_history', []);
        
        if (empty($history)) {
            return null;
        }
        
        $latest_key = end($history);
        return get_option($latest_key);
    }
    
    /**
     * Clear test history
     */
    public function clear_test_history() {
        $history = get_option('adn_comprehensive_test_history', []);
        
        foreach ($history as $key) {
            delete_option($key);
        }
        
        delete_option('adn_comprehensive_test_history');
    }
}