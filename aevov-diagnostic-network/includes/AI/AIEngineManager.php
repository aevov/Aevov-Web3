<?php

namespace ADN\AI;

use ADN\AI\Engines\GeminiEngine;
use ADN\AI\Engines\ClaudeEngine;
use ADN\AI\Engines\KilocodeEngine;

/**
 * AI Engine Manager
 * 
 * Manages multiple AI engines for auto-fixing, recommendations,
 * and intelligent system analysis.
 */
class AIEngineManager {
    
    private $engines = [];
    private $active_engine = 'gemini';
    private $fallback_order = ['gemini', 'claude', 'kilocode'];
    
    /**
     * Initialize AI engines
     */
    public function initialize() {
        $this->load_engines();
        $this->configure_engines();
    }
    
    /**
     * Load all available AI engines
     */
    private function load_engines() {
        // Initialize Gemini engine
        $this->engines['gemini'] = new GeminiEngine();
        
        // Initialize Claude engine
        $this->engines['claude'] = new ClaudeEngine();
        
        // Initialize Kilocode engine
        $this->engines['kilocode'] = new KilocodeEngine();
    }
    
    /**
     * Configure engines with API keys and settings
     */
    private function configure_engines() {
        $settings = get_option('adn_ai_settings', []);
        
        foreach ($this->engines as $name => $engine) {
            if (isset($settings[$name])) {
                $engine->configure($settings[$name]);
            }
        }
    }
    
    /**
     * Auto-fix a component using AI
     */
    public function auto_fix($component, $issue_type) {
        $context = $this->build_context($component, $issue_type);
        
        foreach ($this->fallback_order as $engine_name) {
            if (!isset($this->engines[$engine_name])) {
                continue;
            }
            
            $engine = $this->engines[$engine_name];
            
            if (!$engine->is_available()) {
                continue;
            }
            
            try {
                $result = $engine->auto_fix($context);
                
                if ($result['success']) {
                    // Apply the fix
                    $apply_result = $this->apply_fix($component, $result['fix']);
                    
                    return [
                        'success' => $apply_result['success'],
                        'message' => $apply_result['message'],
                        'engine_used' => $engine_name,
                        'fix_applied' => $result['fix'],
                        'confidence' => $result['confidence'] ?? 0.8
                    ];
                }
            } catch (Exception $e) {
                error_log("AI Engine {$engine_name} failed: " . $e->getMessage());
                continue;
            }
        }
        
        return [
            'success' => false,
            'message' => 'No AI engine could generate a fix for this issue',
            'engine_used' => null
        ];
    }
    
    /**
     * Generate recommendations for system issues
     */
    public function generate_recommendations($issues) {
        if (empty($issues)) {
            return [];
        }
        
        $context = [
            'type' => 'system_analysis',
            'issues' => $issues,
            'system_info' => $this->get_system_info()
        ];
        
        foreach ($this->fallback_order as $engine_name) {
            if (!isset($this->engines[$engine_name])) {
                continue;
            }
            
            $engine = $this->engines[$engine_name];
            
            if (!$engine->is_available()) {
                continue;
            }
            
            try {
                $result = $engine->generate_recommendations($context);
                
                if ($result['success']) {
                    return $result['recommendations'];
                }
            } catch (Exception $e) {
                error_log("AI Engine {$engine_name} failed: " . $e->getMessage());
                continue;
            }
        }
        
        return $this->get_fallback_recommendations($issues);
    }
    
    /**
     * Analyze component code for potential issues
     */
    public function analyze_component($component) {
        $context = [
            'type' => 'code_analysis',
            'component' => $component,
            'file_content' => $this->get_file_content($component['file']),
            'dependencies' => $component['dependencies'] ?? []
        ];
        
        foreach ($this->fallback_order as $engine_name) {
            if (!isset($this->engines[$engine_name])) {
                continue;
            }
            
            $engine = $this->engines[$engine_name];
            
            if (!$engine->is_available()) {
                continue;
            }
            
            try {
                $result = $engine->analyze_code($context);
                
                if ($result['success']) {
                    return $result['analysis'];
                }
            } catch (Exception $e) {
                error_log("AI Engine {$engine_name} failed: " . $e->getMessage());
                continue;
            }
        }
        
        return [
            'issues' => [],
            'suggestions' => [],
            'confidence' => 0.0
        ];
    }
    
    /**
     * Generate missing files or methods
     */
    public function generate_missing_component($component_spec) {
        $context = [
            'type' => 'code_generation',
            'specification' => $component_spec,
            'system_context' => $this->get_system_context()
        ];
        
        foreach ($this->fallback_order as $engine_name) {
            if (!isset($this->engines[$engine_name])) {
                continue;
            }
            
            $engine = $this->engines[$engine_name];
            
            if (!$engine->is_available()) {
                continue;
            }
            
            try {
                $result = $engine->generate_code($context);
                
                if ($result['success']) {
                    return [
                        'success' => true,
                        'code' => $result['code'],
                        'filename' => $result['filename'],
                        'engine_used' => $engine_name,
                        'confidence' => $result['confidence'] ?? 0.8
                    ];
                }
            } catch (Exception $e) {
                error_log("AI Engine {$engine_name} failed: " . $e->getMessage());
                continue;
            }
        }
        
        return [
            'success' => false,
            'message' => 'No AI engine could generate the requested component'
        ];
    }
    
    /**
     * Build context for AI analysis
     */
    private function build_context($component, $issue_type) {
        return [
            'component' => $component,
            'issue_type' => $issue_type,
            'file_content' => $this->get_file_content($component['file']),
            'error_logs' => $this->get_recent_error_logs(),
            'system_info' => $this->get_system_info(),
            'related_components' => $this->get_related_components($component)
        ];
    }
    
    /**
     * Apply a fix generated by AI
     */
    private function apply_fix($component, $fix) {
        try {
            switch ($fix['type']) {
                case 'file_replacement':
                    return $this->apply_file_replacement($fix);
                    
                case 'method_addition':
                    return $this->apply_method_addition($fix);
                    
                case 'configuration_change':
                    return $this->apply_configuration_change($fix);
                    
                case 'dependency_fix':
                    return $this->apply_dependency_fix($fix);
                    
                default:
                    return [
                        'success' => false,
                        'message' => 'Unknown fix type: ' . $fix['type']
                    ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to apply fix: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Apply file replacement fix
     */
    private function apply_file_replacement($fix) {
        $file_path = ABSPATH . $fix['file_path'];
        
        // Create backup
        $backup_path = $file_path . '.backup.' . time();
        if (file_exists($file_path)) {
            copy($file_path, $backup_path);
        }
        
        // Write new content
        $result = file_put_contents($file_path, $fix['content']);
        
        if ($result === false) {
            return [
                'success' => false,
                'message' => 'Failed to write file: ' . $fix['file_path']
            ];
        }
        
        return [
            'success' => true,
            'message' => 'File replaced successfully',
            'backup_created' => $backup_path
        ];
    }
    
    /**
     * Apply method addition fix
     */
    private function apply_method_addition($fix) {
        $file_path = ABSPATH . $fix['file_path'];
        
        if (!file_exists($file_path)) {
            return [
                'success' => false,
                'message' => 'Target file does not exist: ' . $fix['file_path']
            ];
        }
        
        $content = file_get_contents($file_path);
        
        // Create backup
        $backup_path = $file_path . '.backup.' . time();
        copy($file_path, $backup_path);
        
        // Find insertion point
        $insertion_point = $this->find_method_insertion_point($content, $fix['class_name']);
        
        if ($insertion_point === false) {
            return [
                'success' => false,
                'message' => 'Could not find insertion point in class'
            ];
        }
        
        // Insert method
        $new_content = substr($content, 0, $insertion_point) . 
                      "\n" . $fix['method_code'] . "\n" . 
                      substr($content, $insertion_point);
        
        $result = file_put_contents($file_path, $new_content);
        
        if ($result === false) {
            return [
                'success' => false,
                'message' => 'Failed to write updated file'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Method added successfully',
            'backup_created' => $backup_path
        ];
    }
    
    /**
     * Apply configuration change fix
     */
    private function apply_configuration_change($fix) {
        switch ($fix['config_type']) {
            case 'wp_option':
                update_option($fix['option_name'], $fix['option_value']);
                break;
                
            case 'constant':
                // Constants can't be changed at runtime, log for manual intervention
                error_log("Manual intervention required: Define constant {$fix['constant_name']} = {$fix['constant_value']}");
                return [
                    'success' => false,
                    'message' => 'Constants require manual intervention. Check error log for details.'
                ];
                
            default:
                return [
                    'success' => false,
                    'message' => 'Unknown configuration type: ' . $fix['config_type']
                ];
        }
        
        return [
            'success' => true,
            'message' => 'Configuration updated successfully'
        ];
    }
    
    /**
     * Apply dependency fix
     */
    private function apply_dependency_fix($fix) {
        // This would typically involve plugin activation/deactivation
        // or composer dependency management
        
        return [
            'success' => false,
            'message' => 'Dependency fixes require manual intervention'
        ];
    }
    
    /**
     * Get file content safely
     */
    private function get_file_content($file_path) {
        $full_path = ABSPATH . $file_path;
        
        if (!file_exists($full_path)) {
            return null;
        }
        
        return file_get_contents($full_path);
    }
    
    /**
     * Get recent error logs
     */
    private function get_recent_error_logs() {
        $log_file = ini_get('error_log');
        
        if (!$log_file || !file_exists($log_file)) {
            return [];
        }
        
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        // Get last 50 lines
        return array_slice($lines, -50);
    }
    
    /**
     * Get system information
     */
    private function get_system_info() {
        global $wp_version;
        
        return [
            'wordpress_version' => $wp_version,
            'php_version' => PHP_VERSION,
            'active_plugins' => get_option('active_plugins', []),
            'theme' => get_option('stylesheet'),
            'multisite' => \is_multisite(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ];
    }
    
    /**
     * Get related components
     */
    private function get_related_components($component) {
        $diagnostic_network = \ADN\Core\DiagnosticNetwork::instance();
        $all_components = $diagnostic_network->get_system_components();
        
        $related = [];
        
        foreach ($all_components as $id => $comp) {
            if (in_array($component['name'], $comp['dependencies'] ?? []) ||
                in_array($comp['name'], $component['dependencies'] ?? [])) {
                $related[] = $comp;
            }
        }
        
        return $related;
    }
    
    /**
     * Get system context for code generation
     */
    private function get_system_context() {
        return [
            'namespace_patterns' => [
                'APS\\' => 'AevovPatternSyncProtocol',
                'BLOOM\\' => 'bloom-pattern-recognition',
                'ADN\\' => 'aevov-diagnostic-network'
            ],
            'coding_standards' => 'WordPress',
            'php_version' => PHP_VERSION,
            'framework' => 'WordPress Plugin'
        ];
    }
    
    /**
     * Find method insertion point in class
     */
    private function find_method_insertion_point($content, $class_name) {
        // Find the last method in the class
        $pattern = '/class\s+' . preg_quote($class_name) . '.*?\{(.*)\}/s';
        
        if (!preg_match($pattern, $content, $matches)) {
            return false;
        }
        
        $class_content = $matches[1];
        
        // Find the last closing brace of a method
        $last_method_end = strrpos($class_content, '}');
        
        if ($last_method_end === false) {
            // No methods found, insert after class opening brace
            return strpos($content, '{', strpos($content, 'class ' . $class_name)) + 1;
        }
        
        // Calculate position in original content
        $class_start = strpos($content, $matches[0]);
        $class_body_start = strpos($content, '{', $class_start) + 1;
        
        return $class_body_start + $last_method_end + 1;
    }
    
    /**
     * Get fallback recommendations when AI engines fail
     */
    private function get_fallback_recommendations($issues) {
        $recommendations = [];
        
        foreach ($issues as $issue) {
            switch ($issue['severity']) {
                case 'critical':
                    $recommendations[] = [
                        'priority' => 'high',
                        'action' => 'Immediate attention required for ' . $issue['component'],
                        'description' => 'Critical issue detected: ' . $issue['issue']
                    ];
                    break;
                    
                case 'high':
                    $recommendations[] = [
                        'priority' => 'medium',
                        'action' => 'Review and fix ' . $issue['component'],
                        'description' => 'High priority issue: ' . $issue['issue']
                    ];
                    break;
                    
                default:
                    $recommendations[] = [
                        'priority' => 'low',
                        'action' => 'Monitor ' . $issue['component'],
                        'description' => 'Issue detected: ' . $issue['issue']
                    ];
                    break;
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Get available engines
     */
    public function get_available_engines() {
        $available = [];
        
        foreach ($this->engines as $name => $engine) {
            $available[$name] = [
                'name' => $name,
                'available' => $engine->is_available(),
                'configured' => $engine->is_configured()
            ];
        }
        
        return $available;
    }
    
    /**
     * Set active engine
     */
    public function set_active_engine($engine_name) {
        if (isset($this->engines[$engine_name])) {
            $this->active_engine = $engine_name;
            return true;
        }
        
        return false;
    }
    
    /**
     * Get active engine
     */
    public function get_active_engine() {
        return $this->active_engine;
    }
}