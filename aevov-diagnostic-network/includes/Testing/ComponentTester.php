<?php

namespace ADN\Testing;

/**
 * Component Tester
 * 
 * Comprehensive testing framework for system components.
 * Tests file existence, class availability, method functionality,
 * database connectivity, and integration points.
 */
class ComponentTester {
    
    private $test_results = [];
    private $test_history = [];
    
    /**
     * Test a single component
     */
    public function test_component($component) {
        $component_id = $component['id'] ?? $component['name'];
        
        $this->test_results[$component_id] = [
            'component' => $component,
            'timestamp' => current_time('mysql'),
            'tests' => [],
            'overall_status' => 'unknown',
            'issues' => [],
            'recommendations' => []
        ];
        
        // Run all test types
        $this->test_file_existence($component_id, $component);
        $this->test_class_availability($component_id, $component);
        $this->test_method_functionality($component_id, $component);
        $this->test_dependencies($component_id, $component);
        $this->test_database_connectivity($component_id, $component);
        $this->test_integration_points($component_id, $component);
        $this->test_configuration($component_id, $component);
        $this->test_performance($component_id, $component);
        
        // Calculate overall status
        $this->calculate_overall_status($component_id);
        
        // Store in history
        $this->store_test_history($component_id);
        
        $overall_status = $this->test_results[$component_id]['overall_status'];
        $this->test_results[$component_id]['success'] = in_array($overall_status, ['pass', 'warning']);
        
        return $this->test_results[$component_id];
    }
    
    /**
     * Test multiple components
     */
    public function test_components($components) {
        $results = [];
        
        foreach ($components as $component) {
            $results[] = $this->test_component($component);
        }
        
        return $results;
    }
    
    /**
     * Test file existence
     */
    private function test_file_existence($component_id, $component) {
        $test_name = 'file_existence';
        $start_time = microtime(true);
        
        try {
            $file_path = $this->resolve_file_path($component['file']);
            $exists = file_exists($file_path);
            
            $this->test_results[$component_id]['tests'][$test_name] = [
                'status' => $exists ? 'pass' : 'fail',
                'message' => $exists ? 'File exists' : 'File not found: ' . $file_path,
                'execution_time' => microtime(true) - $start_time,
                'details' => [
                    'file_path' => $file_path,
                    'file_size' => $exists ? filesize($file_path) : 0,
                    'last_modified' => $exists ? filemtime($file_path) : null
                ]
            ];
            
            if (!$exists) {
                $this->test_results[$component_id]['issues'][] = [
                    'type' => 'critical',
                    'message' => 'Component file is missing',
                    'file' => $file_path
                ];
            }
            
        } catch (Exception $e) {
            $this->test_results[$component_id]['tests'][$test_name] = [
                'status' => 'error',
                'message' => 'Error testing file existence: ' . $e->getMessage(),
                'execution_time' => microtime(true) - $start_time
            ];
        }
    }
    
    /**
     * Test class availability
     */
    private function test_class_availability($component_id, $component) {
        $test_name = 'class_availability';
        $start_time = microtime(true);
        
        try {
            $class_name = $component['class'] ?? $this->extract_class_name($component);
            
            if (!$class_name) {
                $this->test_results[$component_id]['tests'][$test_name] = [
                    'status' => 'skip',
                    'message' => 'No class specified for component',
                    'execution_time' => microtime(true) - $start_time
                ];
                return;
            }
            
            // Try to load the file first
            $file_path = $this->resolve_file_path($component['file']);
            if (file_exists($file_path)) {
                require_once $file_path;
            }
            
            $class_exists = class_exists($class_name);
            
            $details = [
                'class_name' => $class_name,
                'class_exists' => $class_exists
            ];
            
            if ($class_exists) {
                $reflection = new \ReflectionClass($class_name);
                $details['methods'] = array_map(function($method) {
                    return [
                        'name' => $method->getName(),
                        'public' => $method->isPublic(),
                        'static' => $method->isStatic()
                    ];
                }, $reflection->getMethods());
                $details['properties'] = array_map(function($prop) {
                    return [
                        'name' => $prop->getName(),
                        'public' => $prop->isPublic(),
                        'static' => $prop->isStatic()
                    ];
                }, $reflection->getProperties());
            }
            
            $this->test_results[$component_id]['tests'][$test_name] = [
                'status' => $class_exists ? 'pass' : 'fail',
                'message' => $class_exists ? 'Class is available' : 'Class not found: ' . $class_name,
                'execution_time' => microtime(true) - $start_time,
                'details' => $details
            ];
            
            if (!$class_exists) {
                $this->test_results[$component_id]['issues'][] = [
                    'type' => 'high',
                    'message' => 'Component class is not available',
                    'class' => $class_name
                ];
            }
            
        } catch (Exception $e) {
            $this->test_results[$component_id]['tests'][$test_name] = [
                'status' => 'error',
                'message' => 'Error testing class availability: ' . $e->getMessage(),
                'execution_time' => microtime(true) - $start_time
            ];
        }
    }
    
    /**
     * Test method functionality
     */
    private function test_method_functionality($component_id, $component) {
        $test_name = 'method_functionality';
        $start_time = microtime(true);
        
        try {
            $class_name = $component['class'] ?? $this->extract_class_name($component);
            
            if (!$class_name || !class_exists($class_name)) {
                $this->test_results[$component_id]['tests'][$test_name] = [
                    'status' => 'skip',
                    'message' => 'Class not available for method testing',
                    'execution_time' => microtime(true) - $start_time
                ];
                return;
            }
            
            $required_methods = $component['required_methods'] ?? $this->get_expected_methods($component);
            $method_results = [];
            $all_methods_available = true;
            
            foreach ($required_methods as $method) {
                $method_exists = method_exists($class_name, $method);
                $method_results[$method] = [
                    'exists' => $method_exists,
                    'callable' => $method_exists ? is_callable([$class_name, $method]) : false
                ];
                
                if (!$method_exists) {
                    $all_methods_available = false;
                    $this->test_results[$component_id]['issues'][] = [
                        'type' => 'high',
                        'message' => "Required method '{$method}' is missing",
                        'class' => $class_name,
                        'method' => $method
                    ];
                }
            }
            
            $this->test_results[$component_id]['tests'][$test_name] = [
                'status' => $all_methods_available ? 'pass' : 'fail',
                'message' => $all_methods_available ? 'All required methods available' : 'Some required methods are missing',
                'execution_time' => microtime(true) - $start_time,
                'details' => [
                    'required_methods' => $required_methods,
                    'method_results' => $method_results
                ]
            ];
            
        } catch (Exception $e) {
            $this->test_results[$component_id]['tests'][$test_name] = [
                'status' => 'error',
                'message' => 'Error testing method functionality: ' . $e->getMessage(),
                'execution_time' => microtime(true) - $start_time
            ];
        }
    }
    
    /**
     * Test dependencies
     */
    private function test_dependencies($component_id, $component) {
        $test_name = 'dependencies';
        $start_time = microtime(true);
        
        try {
            $dependencies = $component['dependencies'] ?? [];
            
            if (empty($dependencies)) {
                $this->test_results[$component_id]['tests'][$test_name] = [
                    'status' => 'pass',
                    'message' => 'No dependencies to test',
                    'execution_time' => microtime(true) - $start_time
                ];
                return;
            }
            
            $dependency_results = [];
            $all_dependencies_met = true;
            
            foreach ($dependencies as $dependency) {
                $dependency_met = $this->check_dependency($dependency);
                $dependency_results[$dependency] = $dependency_met;
                
                if (!$dependency_met) {
                    $all_dependencies_met = false;
                    $this->test_results[$component_id]['issues'][] = [
                        'type' => 'high',
                        'message' => "Dependency '{$dependency}' is not available",
                        'dependency' => $dependency
                    ];
                }
            }
            
            $this->test_results[$component_id]['tests'][$test_name] = [
                'status' => $all_dependencies_met ? 'pass' : 'fail',
                'message' => $all_dependencies_met ? 'All dependencies available' : 'Some dependencies are missing',
                'execution_time' => microtime(true) - $start_time,
                'details' => [
                    'dependencies' => $dependencies,
                    'dependency_results' => $dependency_results
                ]
            ];
            
        } catch (Exception $e) {
            $this->test_results[$component_id]['tests'][$test_name] = [
                'status' => 'error',
                'message' => 'Error testing dependencies: ' . $e->getMessage(),
                'execution_time' => microtime(true) - $start_time
            ];
        }
    }
    
    /**
     * Test database connectivity
     */
    private function test_database_connectivity($component_id, $component) {
        $test_name = 'database_connectivity';
        $start_time = microtime(true);
        
        try {
            // Skip if component doesn't use database
            if (!$this->component_uses_database($component)) {
                $this->test_results[$component_id]['tests'][$test_name] = [
                    'status' => 'skip',
                    'message' => 'Component does not use database',
                    'execution_time' => microtime(true) - $start_time
                ];
                return;
            }
            
            global $wpdb;
            
            // Test basic database connection
            $db_connected = false;
            if ($wpdb && is_object($wpdb) && method_exists($wpdb, 'check_connection')) {
                $db_connected = $wpdb->check_connection();
            } elseif ($wpdb && is_object($wpdb)) {
                // Fallback: try a simple query to test connection
                try {
                    $wpdb->get_var("SELECT 1");
                    $db_connected = true;
                } catch (Exception $e) {
                    $db_connected = false;
                }
            }
            
            $details = [
                'db_connected' => $db_connected,
                'db_version' => $wpdb->db_version(),
                'tables_exist' => []
            ];
            
            // Check component-specific tables
            $required_tables = $this->get_component_tables($component);
            $all_tables_exist = true;
            
            foreach ($required_tables as $table) {
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
                $details['tables_exist'][$table] = $table_exists;
                
                if (!$table_exists) {
                    $all_tables_exist = false;
                    $this->test_results[$component_id]['issues'][] = [
                        'type' => 'medium',
                        'message' => "Required table '{$table}' does not exist",
                        'table' => $table
                    ];
                }
            }
            
            $overall_status = $db_connected && $all_tables_exist ? 'pass' : 'fail';
            
            $this->test_results[$component_id]['tests'][$test_name] = [
                'status' => $overall_status,
                'message' => $overall_status === 'pass' ? 'Database connectivity OK' : 'Database connectivity issues detected',
                'execution_time' => microtime(true) - $start_time,
                'details' => $details
            ];
            
        } catch (Exception $e) {
            $this->test_results[$component_id]['tests'][$test_name] = [
                'status' => 'error',
                'message' => 'Error testing database connectivity: ' . $e->getMessage(),
                'execution_time' => microtime(true) - $start_time
            ];
        }
    }
    
    /**
     * Test integration points
     */
    private function test_integration_points($component_id, $component) {
        $test_name = 'integration_points';
        $start_time = microtime(true);
        
        try {
            $integration_points = $this->get_integration_points($component);
            
            if (empty($integration_points)) {
                $this->test_results[$component_id]['tests'][$test_name] = [
                    'status' => 'pass',
                    'message' => 'No integration points to test',
                    'execution_time' => microtime(true) - $start_time
                ];
                return;
            }
            
            $integration_results = [];
            $all_integrations_working = true;
            
            foreach ($integration_points as $point) {
                $integration_working = $this->test_integration_point($point);
                $integration_results[$point['name']] = $integration_working;
                
                if (!$integration_working) {
                    $all_integrations_working = false;
                    $this->test_results[$component_id]['issues'][] = [
                        'type' => 'medium',
                        'message' => "Integration point '{$point['name']}' is not working",
                        'integration_point' => $point['name']
                    ];
                }
            }
            
            $this->test_results[$component_id]['tests'][$test_name] = [
                'status' => $all_integrations_working ? 'pass' : 'fail',
                'message' => $all_integrations_working ? 'All integration points working' : 'Some integration points have issues',
                'execution_time' => microtime(true) - $start_time,
                'details' => [
                    'integration_points' => $integration_points,
                    'integration_results' => $integration_results
                ]
            ];
            
        } catch (Exception $e) {
            $this->test_results[$component_id]['tests'][$test_name] = [
                'status' => 'error',
                'message' => 'Error testing integration points: ' . $e->getMessage(),
                'execution_time' => microtime(true) - $start_time
            ];
        }
    }
    
    /**
     * Test configuration
     */
    private function test_configuration($component_id, $component) {
        $test_name = 'configuration';
        $start_time = microtime(true);
        
        try {
            $required_config = $this->get_required_configuration($component);
            
            if (empty($required_config)) {
                $this->test_results[$component_id]['tests'][$test_name] = [
                    'status' => 'pass',
                    'message' => 'No configuration requirements',
                    'execution_time' => microtime(true) - $start_time
                ];
                return;
            }
            
            $config_results = [];
            $all_config_valid = true;
            
            foreach ($required_config as $config_key => $config_requirement) {
                $config_valid = $this->validate_configuration($config_key, $config_requirement);
                $config_results[$config_key] = $config_valid;
                
                if (!$config_valid) {
                    $all_config_valid = false;
                    $this->test_results[$component_id]['issues'][] = [
                        'type' => 'medium',
                        'message' => "Configuration '{$config_key}' is invalid or missing",
                        'config_key' => $config_key
                    ];
                }
            }
            
            $this->test_results[$component_id]['tests'][$test_name] = [
                'status' => $all_config_valid ? 'pass' : 'fail',
                'message' => $all_config_valid ? 'Configuration is valid' : 'Configuration issues detected',
                'execution_time' => microtime(true) - $start_time,
                'details' => [
                    'required_config' => $required_config,
                    'config_results' => $config_results
                ]
            ];
            
        } catch (Exception $e) {
            $this->test_results[$component_id]['tests'][$test_name] = [
                'status' => 'error',
                'message' => 'Error testing configuration: ' . $e->getMessage(),
                'execution_time' => microtime(true) - $start_time
            ];
        }
    }
    
    /**
     * Test performance
     */
    private function test_performance($component_id, $component) {
        $test_name = 'performance';
        $start_time = microtime(true);
        
        try {
            $performance_metrics = [];
            
            // Test memory usage
            $memory_before = memory_get_usage();
            $this->simulate_component_load($component);
            $memory_after = memory_get_usage();
            $performance_metrics['memory_usage'] = $memory_after - $memory_before;
            
            // Test load time
            $load_start = microtime(true);
            $this->simulate_component_operation($component);
            $load_time = microtime(true) - $load_start;
            $performance_metrics['load_time'] = $load_time;
            
            // Evaluate performance
            $performance_issues = [];
            
            if ($performance_metrics['memory_usage'] > 1048576) { // 1MB
                $performance_issues[] = 'High memory usage detected';
            }
            
            if ($performance_metrics['load_time'] > 1.0) { // 1 second
                $performance_issues[] = 'Slow load time detected';
            }
            
            $performance_ok = empty($performance_issues);
            
            $this->test_results[$component_id]['tests'][$test_name] = [
                'status' => $performance_ok ? 'pass' : 'warning',
                'message' => $performance_ok ? 'Performance is acceptable' : 'Performance issues detected',
                'execution_time' => microtime(true) - $start_time,
                'details' => [
                    'metrics' => $performance_metrics,
                    'issues' => $performance_issues
                ]
            ];
            
            if (!$performance_ok) {
                foreach ($performance_issues as $issue) {
                    $this->test_results[$component_id]['issues'][] = [
                        'type' => 'low',
                        'message' => $issue,
                        'category' => 'performance'
                    ];
                }
            }
            
        } catch (Exception $e) {
            $this->test_results[$component_id]['tests'][$test_name] = [
                'status' => 'error',
                'message' => 'Error testing performance: ' . $e->getMessage(),
                'execution_time' => microtime(true) - $start_time
            ];
        }
    }
    
    /**
     * Calculate overall status for component
     */
    private function calculate_overall_status($component_id) {
        $tests = $this->test_results[$component_id]['tests'];
        $critical_failures = 0;
        $failures = 0;
        $warnings = 0;
        $passes = 0;
        $errors = 0;
        
        foreach ($tests as $test) {
            switch ($test['status']) {
                case 'pass':
                    $passes++;
                    break;
                case 'fail':
                    $failures++;
                    if (in_array($test, ['file_existence', 'class_availability'])) {
                        $critical_failures++;
                    }
                    break;
                case 'warning':
                    $warnings++;
                    break;
                case 'error':
                    $errors++;
                    break;
            }
        }
        
        if ($critical_failures > 0 || $errors > 0) {
            $status = 'critical';
        } elseif ($failures > 0) {
            $status = 'fail';
        } elseif ($warnings > 0) {
            $status = 'warning';
        } else {
            $status = 'pass';
        }
        
        $this->test_results[$component_id]['overall_status'] = $status;
        $this->test_results[$component_id]['summary'] = [
            'total_tests' => count($tests),
            'passes' => $passes,
            'failures' => $failures,
            'warnings' => $warnings,
            'errors' => $errors,
            'critical_failures' => $critical_failures
        ];
    }
    
    /**
     * Store test history
     */
    private function store_test_history($component_id) {
        $history_key = 'adn_test_history_' . $component_id;
        $history = get_option($history_key, []);
        
        $history[] = [
            'timestamp' => current_time('mysql'),
            'overall_status' => $this->test_results[$component_id]['overall_status'],
            'summary' => $this->test_results[$component_id]['summary'],
            'issues_count' => count($this->test_results[$component_id]['issues'])
        ];
        
        // Keep only last 50 test results
        if (count($history) > 50) {
            $history = array_slice($history, -50);
        }
        
        update_option($history_key, $history);
    }
    
    /**
     * Helper methods
     */
    
    private function resolve_file_path($file) {
        if (strpos($file, '/') === 0) {
            return $file; // Absolute path
        }
        
        // Assume relative paths are relative to the plugins directory
        return WP_PLUGIN_DIR . '/' . $file;
    }
    
    private function extract_class_name($component) {
        // Try to extract class name from file content or component type
        $type = $component['type'] ?? '';
        $name = $component['name'] ?? '';
        
        // Common patterns
        $patterns = [
            'APS\\' . ucfirst($type) . '\\' . str_replace(' ', '', $name),
            'BLOOM\\' . ucfirst($type) . '\\' . str_replace(' ', '', $name),
            'ADN\\' . ucfirst($type) . '\\' . str_replace(' ', '', $name)
        ];
        
        foreach ($patterns as $pattern) {
            if (class_exists($pattern)) {
                return $pattern;
            }
        }
        
        return null;
    }
    
    private function get_expected_methods($component) {
        $type = $component['type'] ?? '';
        
        $common_methods = ['__construct'];
        
        switch ($type) {
            case 'core':
                return array_merge($common_methods, ['initialize', 'get_instance']);
            case 'admin':
                return array_merge($common_methods, ['init', 'render']);
            case 'integration':
                return array_merge($common_methods, ['connect', 'sync']);
            case 'processing':
                return array_merge($common_methods, ['process', 'validate']);
            default:
                return $common_methods;
        }
    }
    
    private function check_dependency($dependency) {
        // Map component IDs to their main class names
        $component_class_map = [
            'aps_core' => 'APS\\Analysis\\APS_Plugin',
            'bloom_pattern_recognition' => 'BLOOM\\Core', // Corrected to BLOOM\Core
            'aps_tools' => 'APSTools\\APSTools',
            'aevov_onboarding' => 'AevovOnboarding\\AevovOnboardingSystem',
            'aps_loader' => 'APS\\Core\\Loader',
            'aps_metrics_db' => 'APS\\DB\\MetricsDB',
            // Add other component IDs and their main classes as needed
        ];

        error_log("ADN DEBUG: check_dependency called for: {$dependency}");

        // Check if the dependency is a known component ID and its class exists
        if (isset($component_class_map[$dependency])) {
            $class_to_check = $component_class_map[$dependency];
            $class_exists_result = class_exists($class_to_check);
            error_log("ADN DEBUG: Checking component ID '{$dependency}'. Class '{$class_to_check}' exists: " . ($class_exists_result ? 'true' : 'false'));
            return $class_exists_result;
        }
        
        // Fallback to checking if it's a plugin basename
        if (strpos($dependency, '/') !== false) {
            $is_active = is_plugin_active($dependency);
            error_log("ADN DEBUG: Checking plugin basename '{$dependency}'. Is active: " . ($is_active ? 'true' : 'false'));
            return $is_active;
        }
        
        // Fallback to checking if it's a class (for non-component classes)
        if (class_exists($dependency)) {
            error_log("ADN DEBUG: Checking class '{$dependency}'. Class exists: true");
            return true;
        }
        
        // Fallback to checking if it's a function
        if (function_exists($dependency)) {
            error_log("ADN DEBUG: Checking function '{$dependency}'. Function exists: true");
            return true;
        }
        
        error_log("ADN DEBUG: Dependency '{$dependency}' not found by any method.");
        return false;
    }
    
    private function component_uses_database($component) {
        $type = $component['type'] ?? '';
        $db_types = ['db', 'model', 'storage', 'metrics'];
        
        return in_array($type, $db_types) || 
               strpos(strtolower($component['name']), 'db') !== false ||
               strpos(strtolower($component['name']), 'database') !== false;
    }
    
    private function get_component_tables($component) {
        global $wpdb;
        
        $component_name = strtolower($component['name']);
        $tables = [];
        
        // Common table patterns
        if (strpos($component_name, 'aps') !== false) {
            $tables[] = $wpdb->prefix . 'aps_patterns';
            $tables[] = $wpdb->prefix . 'aps_metrics';
        }
        
        if (strpos($component_name, 'bloom') !== false) {
            $tables[] = $wpdb->prefix . 'bloom_patterns';
            $tables[] = $wpdb->prefix . 'bloom_chunks';
        }
        
        return $tables;
    }
    
    private function get_integration_points($component) {
        $type = $component['type'] ?? '';
        $points = [];
        
        if ($type === 'integration') {
            $points[] = ['name' => 'api_connection', 'type' => 'api'];
            $points[] = ['name' => 'data_sync', 'type' => 'sync'];
        }
        
        return $points;
    }
    
    private function test_integration_point($point) {
        // Simulate integration point testing
        return true; // Placeholder
    }
    
    private function get_required_configuration($component) {
        $type = $component['type'] ?? '';
        $config = [];
        
        if ($type === 'integration') {
            $config['api_key'] = ['required' => true, 'type' => 'string'];
            $config['api_url'] = ['required' => true, 'type' => 'url'];
        }
        
        return $config;
    }
    
    private function validate_configuration($key, $requirement) {
        $value = get_option($key);
        
        if ($requirement['required'] && empty($value)) {
            return false;
        }
        
        if (!empty($value) && isset($requirement['type'])) {
            switch ($requirement['type']) {
                case 'url':
                    return filter_var($value, FILTER_VALIDATE_URL) !== false;
                case 'email':
                    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                case 'int':
                    return is_numeric($value);
                default:
                    return true;
            }
        }
        
        return true;
    }
    
    private function simulate_component_load($component) {
        // Simulate loading component for performance testing
        $file_path = $this->resolve_file_path($component['file']);
        if (file_exists($file_path)) {
            include_once $file_path;
        }
    }
    
    private function simulate_component_operation($component) {
        // Simulate component operation for performance testing
        usleep(rand(1000, 10000)); // Random delay to simulate work
    }
    
    /**
     * Get test results
     */
    public function get_test_results() {
        return $this->test_results;
    }
    
    /**
     * Get test history for a component
     */
    public function get_test_history($component_id) {
        $history_key = 'adn_test_history_' . $component_id;
        return get_option($history_key, []);
    }
    
    /**
     * Clear test results
     */
    public function clear_test_results() {
        $this->test_results = [];
    }
}