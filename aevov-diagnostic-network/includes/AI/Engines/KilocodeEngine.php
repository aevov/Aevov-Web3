<?php

namespace ADN\AI\Engines;

/**
 * Kilocode AI Engine
 * 
 * Integrates with Kilocode API for AI-powered diagnostics,
 * auto-fixing, and code analysis specialized in debugging.
 */
class KilocodeEngine {
    
    private $api_key;
    private $api_url = 'https://api.kilocode.dev/v1/debug';
    private $configured = false;
    private $available = false;
    
    /**
     * Configure the engine with API settings
     */
    public function configure($settings) {
        $this->api_key = $settings['api_key'] ?? '';
        $this->api_url = $settings['api_url'] ?? $this->api_url;
        $this->configured = !empty($this->api_key);
        $this->available = $this->test_connection();
    }
    
    /**
     * Check if engine is configured
     */
    public function is_configured() {
        return $this->configured;
    }
    
    /**
     * Check if engine is available
     */
    public function is_available() {
        return $this->available && $this->configured;
    }
    
    /**
     * Test API connection
     */
    private function test_connection() {
        if (!$this->configured) {
            return false;
        }
        
        try {
            $response = $this->make_request('ping', [
                'message' => 'Connection test'
            ]);
            
            return isset($response['status']) && $response['status'] === 'ok';
        } catch (Exception $e) {
            error_log('Kilocode API connection test failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Auto-fix a component issue
     */
    public function auto_fix($context) {
        if (!$this->is_available()) {
            return ['success' => false, 'message' => 'Kilocode engine not available'];
        }
        
        $payload = $this->build_autofix_payload($context);
        
        try {
            $response = $this->make_request('autofix', $payload);
            
            if (!isset($response['success']) || !$response['success']) {
                return [
                    'success' => false, 
                    'message' => $response['error'] ?? 'Unknown error from Kilocode API'
                ];
            }
            
            return [
                'success' => true,
                'fix' => $response['fix'],
                'confidence' => $response['confidence'] ?? 0.85
            ];
            
        } catch (Exception $e) {
            error_log('Kilocode auto-fix failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'API request failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate recommendations for system issues
     */
    public function generate_recommendations($context) {
        if (!$this->is_available()) {
            return ['success' => false, 'message' => 'Kilocode engine not available'];
        }
        
        $payload = $this->build_recommendations_payload($context);
        
        try {
            $response = $this->make_request('recommendations', $payload);
            
            if (!isset($response['success']) || !$response['success']) {
                return [
                    'success' => false, 
                    'message' => $response['error'] ?? 'Unknown error from Kilocode API'
                ];
            }
            
            return [
                'success' => true,
                'recommendations' => $response['recommendations']
            ];
            
        } catch (Exception $e) {
            error_log('Kilocode recommendations failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'API request failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Analyze code for potential issues
     */
    public function analyze_code($context) {
        if (!$this->is_available()) {
            return ['success' => false, 'message' => 'Kilocode engine not available'];
        }
        
        $payload = $this->build_analysis_payload($context);
        
        try {
            $response = $this->make_request('analyze', $payload);
            
            if (!isset($response['success']) || !$response['success']) {
                return [
                    'success' => false, 
                    'message' => $response['error'] ?? 'Unknown error from Kilocode API'
                ];
            }
            
            return [
                'success' => true,
                'analysis' => $response['analysis']
            ];
            
        } catch (Exception $e) {
            error_log('Kilocode code analysis failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'API request failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate code for missing components
     */
    public function generate_code($context) {
        if (!$this->is_available()) {
            return ['success' => false, 'message' => 'Kilocode engine not available'];
        }
        
        $payload = $this->build_generation_payload($context);
        
        try {
            $response = $this->make_request('generate', $payload);
            
            if (!isset($response['success']) || !$response['success']) {
                return [
                    'success' => false, 
                    'message' => $response['error'] ?? 'Unknown error from Kilocode API'
                ];
            }
            
            return [
                'success' => true,
                'code' => $response['code'],
                'filename' => $response['filename'],
                'confidence' => $response['confidence'] ?? 0.85
            ];
            
        } catch (Exception $e) {
            error_log('Kilocode code generation failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'API request failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Build auto-fix payload
     */
    private function build_autofix_payload($context) {
        return [
            'task' => 'debug_and_fix',
            'component' => [
                'name' => $context['component']['name'],
                'type' => $context['component']['type'] ?? 'unknown',
                'file' => $context['component']['file'],
                'content' => $context['file_content'] ?? null
            ],
            'issue' => [
                'type' => $context['issue_type'],
                'description' => $this->extract_issue_description($context),
                'severity' => $this->determine_severity($context)
            ],
            'environment' => [
                'platform' => 'WordPress',
                'php_version' => $context['system_info']['php_version'] ?? PHP_VERSION,
                'wp_version' => $context['system_info']['wordpress_version'] ?? 'unknown',
                'memory_limit' => $context['system_info']['memory_limit'] ?? ini_get('memory_limit')
            ],
            'context' => [
                'error_logs' => array_slice($context['error_logs'] ?? [], -10),
                'related_components' => $this->format_related_components($context['related_components'] ?? []),
                'dependencies' => $context['component']['dependencies'] ?? []
            ],
            'preferences' => [
                'fix_type' => 'comprehensive',
                'include_explanation' => true,
                'create_backup' => true,
                'validate_fix' => true
            ]
        ];
    }
    
    /**
     * Build recommendations payload
     */
    private function build_recommendations_payload($context) {
        return [
            'task' => 'system_analysis',
            'issues' => array_map(function($issue) {
                return [
                    'component' => $issue['component'],
                    'type' => $issue['issue'] ?? 'unknown',
                    'severity' => $issue['severity'],
                    'description' => $issue['description'] ?? $issue['issue'] ?? ''
                ];
            }, $context['issues']),
            'system' => [
                'platform' => 'WordPress',
                'php_version' => $context['system_info']['php_version'] ?? PHP_VERSION,
                'wp_version' => $context['system_info']['wordpress_version'] ?? 'unknown',
                'active_plugins' => count($context['system_info']['active_plugins'] ?? []),
                'multisite' => $context['system_info']['multisite'] ?? false,
                'memory_limit' => $context['system_info']['memory_limit'] ?? ini_get('memory_limit'),
                'max_execution_time' => $context['system_info']['max_execution_time'] ?? ini_get('max_execution_time')
            ],
            'preferences' => [
                'priority_order' => ['critical', 'high', 'medium', 'low'],
                'include_risk_assessment' => true,
                'include_time_estimates' => true,
                'focus_areas' => ['security', 'performance', 'stability']
            ]
        ];
    }
    
    /**
     * Build analysis payload
     */
    private function build_analysis_payload($context) {
        return [
            'task' => 'code_review',
            'component' => [
                'name' => $context['component']['name'],
                'type' => $context['component']['type'] ?? 'unknown',
                'file' => $context['component']['file'],
                'content' => $context['file_content'] ?? '',
                'language' => 'php'
            ],
            'analysis_scope' => [
                'security_vulnerabilities' => true,
                'performance_issues' => true,
                'code_quality' => true,
                'wordpress_standards' => true,
                'error_handling' => true,
                'documentation' => true
            ],
            'context' => [
                'dependencies' => $context['dependencies'] ?? [],
                'framework' => 'WordPress Plugin',
                'coding_standards' => 'WordPress'
            ],
            'preferences' => [
                'detailed_explanations' => true,
                'include_examples' => true,
                'severity_levels' => ['critical', 'high', 'medium', 'low', 'info']
            ]
        ];
    }
    
    /**
     * Build generation payload
     */
    private function build_generation_payload($context) {
        return [
            'task' => 'code_generation',
            'specification' => $context['specification'],
            'requirements' => [
                'language' => 'php',
                'framework' => 'WordPress Plugin',
                'coding_standards' => 'WordPress',
                'include_documentation' => true,
                'include_error_handling' => true,
                'include_security_measures' => true
            ],
            'context' => $context['system_context'],
            'preferences' => [
                'code_style' => 'professional',
                'comment_level' => 'comprehensive',
                'include_examples' => false,
                'optimize_for' => 'maintainability'
            ]
        ];
    }
    
    /**
     * Make API request to Kilocode
     */
    private function make_request($endpoint, $data) {
        $cache_key = 'adn_kilocode_' . md5($endpoint . json_encode($data));
        $cached_response = get_transient($cache_key);

        if (false !== $cached_response) {
            return $cached_response;
        }

        $url = rtrim($this->api_url, '/') . '/' . $endpoint;
        
        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
                'User-Agent' => 'Aevov-Diagnostic-Network/1.0'
            ],
            'body' => json_encode($data),
            'timeout' => 45
        ];
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']) ? 
                           (is_array($error_data['error']) ? $error_data['error']['message'] : $error_data['error']) : 
                           'HTTP ' . $status_code;
            throw new Exception('API error: ' . $error_message);
        }
        
        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from API');
        }
        
        set_transient($cache_key, $decoded, HOUR_IN_SECONDS); // Cache for 1 hour

        return $decoded;
    }
    
    /**
     * Extract issue description from context
     */
    private function extract_issue_description($context) {
        $descriptions = [];
        
        // Add issue type
        if (!empty($context['issue_type'])) {
            $descriptions[] = 'Issue type: ' . $context['issue_type'];
        }
        
        // Add recent error logs
        if (!empty($context['error_logs'])) {
            $recent_errors = array_slice($context['error_logs'], -3);
            $descriptions[] = 'Recent errors: ' . implode('; ', $recent_errors);
        }
        
        // Add component info
        if (!empty($context['component']['description'])) {
            $descriptions[] = 'Component: ' . $context['component']['description'];
        }
        
        return implode('. ', $descriptions);
    }
    
    /**
     * Determine issue severity
     */
    private function determine_severity($context) {
        $issue_type = strtolower($context['issue_type'] ?? '');
        
        // Critical issues
        if (strpos($issue_type, 'fatal') !== false || 
            strpos($issue_type, 'critical') !== false ||
            strpos($issue_type, 'security') !== false) {
            return 'critical';
        }
        
        // High priority issues
        if (strpos($issue_type, 'error') !== false || 
            strpos($issue_type, 'exception') !== false ||
            strpos($issue_type, 'dependency') !== false) {
            return 'high';
        }
        
        // Medium priority issues
        if (strpos($issue_type, 'warning') !== false || 
            strpos($issue_type, 'deprecated') !== false ||
            strpos($issue_type, 'performance') !== false) {
            return 'medium';
        }
        
        // Default to low
        return 'low';
    }
    
    /**
     * Format related components for API
     */
    private function format_related_components($components) {
        return array_map(function($comp) {
            return [
                'name' => $comp['name'] ?? 'unknown',
                'type' => $comp['type'] ?? 'unknown',
                'file' => $comp['file'] ?? '',
                'status' => $comp['status'] ?? 'unknown'
            ];
        }, $components);
    }
    
    /**
     * Perform diagnostic scan using Kilocode's specialized debugging
     */
    public function diagnostic_scan($components) {
        if (!$this->is_available()) {
            return ['success' => false, 'message' => 'Kilocode engine not available'];
        }
        
        $payload = [
            'task' => 'comprehensive_diagnostic',
            'components' => array_map(function($comp) {
                return [
                    'id' => $comp['id'] ?? uniqid(),
                    'name' => $comp['name'],
                    'type' => $comp['type'],
                    'file' => $comp['file'],
                    'dependencies' => $comp['dependencies'] ?? [],
                    'status' => $comp['status'] ?? 'unknown'
                ];
            }, $components),
            'scan_depth' => 'deep',
            'include_recommendations' => true,
            'focus_areas' => [
                'dependency_conflicts',
                'method_compatibility',
                'security_vulnerabilities',
                'performance_bottlenecks',
                'error_patterns'
            ]
        ];
        
        try {
            $response = $this->make_request('diagnostic', $payload);
            
            if (!isset($response['success']) || !$response['success']) {
                return [
                    'success' => false, 
                    'message' => $response['error'] ?? 'Diagnostic scan failed'
                ];
            }
            
            return [
                'success' => true,
                'results' => $response['results'],
                'summary' => $response['summary'] ?? [],
                'recommendations' => $response['recommendations'] ?? []
            ];
            
        } catch (Exception $e) {
            error_log('Kilocode diagnostic scan failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Diagnostic scan failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get system health score from Kilocode
     */
    public function get_health_score($system_data) {
        if (!$this->is_available()) {
            return ['success' => false, 'message' => 'Kilocode engine not available'];
        }
        
        $payload = [
            'task' => 'health_assessment',
            'system_data' => $system_data,
            'metrics' => [
                'component_health',
                'dependency_integrity',
                'performance_score',
                'security_rating',
                'maintainability_index'
            ]
        ];
        
        try {
            $response = $this->make_request('health', $payload);
            
            if (!isset($response['success']) || !$response['success']) {
                return [
                    'success' => false, 
                    'message' => $response['error'] ?? 'Health assessment failed'
                ];
            }
            
            return [
                'success' => true,
                'overall_score' => $response['overall_score'] ?? 0,
                'component_scores' => $response['component_scores'] ?? [],
                'recommendations' => $response['recommendations'] ?? [],
                'trends' => $response['trends'] ?? []
            ];
            
        } catch (Exception $e) {
            error_log('Kilocode health assessment failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Health assessment failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get debugging insights for specific errors
     */
    public function get_debug_insights($error_data) {
        if (!$this->is_available()) {
            return ['success' => false, 'message' => 'Kilocode engine not available'];
        }
        
        $payload = [
            'task' => 'debug_analysis',
            'error_data' => $error_data,
            'analysis_type' => 'comprehensive',
            'include_solutions' => true,
            'include_prevention' => true
        ];
        
        try {
            $response = $this->make_request('debug', $payload);
            
            if (!isset($response['success']) || !$response['success']) {
                return [
                    'success' => false, 
                    'message' => $response['error'] ?? 'Debug analysis failed'
                ];
            }
            
            return [
                'success' => true,
                'insights' => $response['insights'] ?? [],
                'root_cause' => $response['root_cause'] ?? 'Unknown',
                'solutions' => $response['solutions'] ?? [],
                'prevention_tips' => $response['prevention_tips'] ?? []
            ];
            
        } catch (Exception $e) {
            error_log('Kilocode debug insights failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Debug analysis failed: ' . $e->getMessage()];
        }
    }
}