<?php

namespace ADN\AI\Engines;

/**
 * Claude AI Engine
 * 
 * Integrates with Anthropic's Claude API for AI-powered diagnostics,
 * auto-fixing, and code analysis.
 */
class ClaudeEngine {
    
    private $api_key;
    private $api_url = 'https://api.anthropic.com/v1/messages';
    private $model = 'claude-3-sonnet-20240229';
    private $configured = false;
    private $available = false;
    
    /**
     * Configure the engine with API settings
     */
    public function configure($settings) {
        $this->api_key = $settings['api_key'] ?? '';
        $this->model = $settings['model'] ?? $this->model;
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
            $response = $this->make_request([
                'model' => $this->model,
                'max_tokens' => 10,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Test'
                    ]
                ]
            ]);
            
            return isset($response['content']);
        } catch (Exception $e) {
            error_log('Claude API connection test failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Auto-fix a component issue
     */
    public function auto_fix($context) {
        if (!$this->is_available()) {
            return ['success' => false, 'message' => 'Claude engine not available'];
        }
        
        $prompt = $this->build_autofix_prompt($context);
        
        try {
            $response = $this->make_request([
                'model' => $this->model,
                'max_tokens' => 4000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);
            
            if (!isset($response['content'][0]['text'])) {
                return ['success' => false, 'message' => 'Invalid response from Claude API'];
            }
            
            $result_text = $response['content'][0]['text'];
            $parsed_result = $this->parse_autofix_response($result_text);
            
            return [
                'success' => true,
                'fix' => $parsed_result,
                'confidence' => $this->calculate_confidence($result_text)
            ];
            
        } catch (Exception $e) {
            error_log('Claude auto-fix failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'API request failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate recommendations for system issues
     */
    public function generate_recommendations($context) {
        if (!$this->is_available()) {
            return ['success' => false, 'message' => 'Claude engine not available'];
        }
        
        $prompt = $this->build_recommendations_prompt($context);
        
        try {
            $response = $this->make_request([
                'model' => $this->model,
                'max_tokens' => 3000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);
            
            if (!isset($response['content'][0]['text'])) {
                return ['success' => false, 'message' => 'Invalid response from Claude API'];
            }
            
            $result_text = $response['content'][0]['text'];
            $recommendations = $this->parse_recommendations_response($result_text);
            
            return [
                'success' => true,
                'recommendations' => $recommendations
            ];
            
        } catch (Exception $e) {
            error_log('Claude recommendations failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'API request failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Analyze code for potential issues
     */
    public function analyze_code($context) {
        if (!$this->is_available()) {
            return ['success' => false, 'message' => 'Claude engine not available'];
        }
        
        $prompt = $this->build_analysis_prompt($context);
        
        try {
            $response = $this->make_request([
                'model' => $this->model,
                'max_tokens' => 3000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);
            
            if (!isset($response['content'][0]['text'])) {
                return ['success' => false, 'message' => 'Invalid response from Claude API'];
            }
            
            $result_text = $response['content'][0]['text'];
            $analysis = $this->parse_analysis_response($result_text);
            
            return [
                'success' => true,
                'analysis' => $analysis
            ];
            
        } catch (Exception $e) {
            error_log('Claude code analysis failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'API request failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate code for missing components
     */
    public function generate_code($context) {
        if (!$this->is_available()) {
            return ['success' => false, 'message' => 'Claude engine not available'];
        }
        
        $prompt = $this->build_generation_prompt($context);
        
        try {
            $response = $this->make_request([
                'model' => $this->model,
                'max_tokens' => 4000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);
            
            if (!isset($response['content'][0]['text'])) {
                return ['success' => false, 'message' => 'Invalid response from Claude API'];
            }
            
            $result_text = $response['content'][0]['text'];
            $generated = $this->parse_generation_response($result_text);
            
            return [
                'success' => true,
                'code' => $generated['code'],
                'filename' => $generated['filename'],
                'confidence' => $this->calculate_confidence($result_text)
            ];
            
        } catch (Exception $e) {
            error_log('Claude code generation failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'API request failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Build auto-fix prompt
     */
    private function build_autofix_prompt($context) {
        $prompt = "You are Kilo Code, an expert software debugger specializing in WordPress plugin development. ";
        $prompt .= "Analyze the following component issue and provide a specific, actionable fix.\n\n";
        
        $prompt .= "## Component Details\n";
        $prompt .= "- **Component**: " . $context['component']['name'] . "\n";
        $prompt .= "- **Issue Type**: " . $context['issue_type'] . "\n";
        $prompt .= "- **File**: " . $context['component']['file'] . "\n\n";
        
        if (!empty($context['file_content'])) {
            $prompt .= "## Current File Content\n";
            $prompt .= "```php\n" . $context['file_content'] . "\n```\n\n";
        }
        
        if (!empty($context['error_logs'])) {
            $prompt .= "## Recent Error Logs\n";
            $prompt .= "```\n" . implode("\n", array_slice($context['error_logs'], -5)) . "\n```\n\n";
        }
        
        $prompt .= "## System Environment\n";
        $prompt .= "- **WordPress Version**: " . ($context['system_info']['wordpress_version'] ?? 'Unknown') . "\n";
        $prompt .= "- **PHP Version**: " . ($context['system_info']['php_version'] ?? 'Unknown') . "\n";
        $prompt .= "- **Memory Limit**: " . ($context['system_info']['memory_limit'] ?? 'Unknown') . "\n\n";
        
        if (!empty($context['related_components'])) {
            $prompt .= "## Related Components\n";
            foreach ($context['related_components'] as $comp) {
                $prompt .= "- " . $comp['name'] . " (" . $comp['type'] . ")\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "## Required Output Format\n";
        $prompt .= "Provide your fix in the following JSON format:\n\n";
        $prompt .= "```json\n";
        $prompt .= "{\n";
        $prompt .= '  "type": "file_replacement|method_addition|configuration_change|dependency_fix",';
        $prompt .= "\n";
        $prompt .= '  "file_path": "relative/path/to/file.php",';
        $prompt .= "\n";
        $prompt .= '  "content": "complete file content or method code",';
        $prompt .= "\n";
        $prompt .= '  "class_name": "ClassName (for method_addition only)",';
        $prompt .= "\n";
        $prompt .= '  "method_code": "method code (for method_addition only)",';
        $prompt .= "\n";
        $prompt .= '  "option_name": "option_name (for configuration_change only)",';
        $prompt .= "\n";
        $prompt .= '  "option_value": "option_value (for configuration_change only)",';
        $prompt .= "\n";
        $prompt .= '  "explanation": "detailed explanation of the fix and why it resolves the issue"';
        $prompt .= "\n";
        $prompt .= "}\n";
        $prompt .= "```\n\n";
        
        $prompt .= "Focus on providing a complete, working solution that addresses the root cause of the issue.";
        
        return $prompt;
    }
    
    /**
     * Build recommendations prompt
     */
    private function build_recommendations_prompt($context) {
        $prompt = "You are a WordPress system administrator expert. ";
        $prompt .= "Analyze the following system issues and provide prioritized, actionable recommendations.\n\n";
        
        $prompt .= "## System Issues Detected\n";
        foreach ($context['issues'] as $issue) {
            $prompt .= "- **" . $issue['component'] . "**: " . $issue['issue'] . " (Severity: " . $issue['severity'] . ")\n";
        }
        
        $prompt .= "\n## System Environment\n";
        $prompt .= "- **WordPress Version**: " . ($context['system_info']['wordpress_version'] ?? 'Unknown') . "\n";
        $prompt .= "- **PHP Version**: " . ($context['system_info']['php_version'] ?? 'Unknown') . "\n";
        $prompt .= "- **Active Plugins**: " . count($context['system_info']['active_plugins'] ?? []) . "\n";
        $prompt .= "- **Multisite**: " . ($context['system_info']['multisite'] ? 'Yes' : 'No') . "\n";
        $prompt .= "- **Memory Limit**: " . ($context['system_info']['memory_limit'] ?? 'Unknown') . "\n\n";
        
        $prompt .= "## Required Output Format\n";
        $prompt .= "Provide recommendations in the following JSON format:\n\n";
        $prompt .= "```json\n";
        $prompt .= "[\n";
        $prompt .= "  {\n";
        $prompt .= '    "priority": "critical|high|medium|low",';
        $prompt .= "\n";
        $prompt .= '    "action": "specific action to take",';
        $prompt .= "\n";
        $prompt .= '    "description": "detailed description of the recommendation",';
        $prompt .= "\n";
        $prompt .= '    "component": "affected component name",';
        $prompt .= "\n";
        $prompt .= '    "estimated_time": "estimated time to implement",';
        $prompt .= "\n";
        $prompt .= '    "risk_level": "low|medium|high"';
        $prompt .= "\n";
        $prompt .= "  }\n";
        $prompt .= "]\n";
        $prompt .= "```\n\n";
        
        $prompt .= "Order recommendations by priority (critical first) and provide specific, actionable steps.";
        
        return $prompt;
    }
    
    /**
     * Build analysis prompt
     */
    private function build_analysis_prompt($context) {
        $prompt = "You are an expert code reviewer specializing in WordPress plugin security, performance, and best practices. ";
        $prompt .= "Perform a comprehensive analysis of the following code.\n\n";
        
        $prompt .= "## Component Information\n";
        $prompt .= "- **Component**: " . $context['component']['name'] . "\n";
        $prompt .= "- **File**: " . $context['component']['file'] . "\n";
        $prompt .= "- **Type**: " . ($context['component']['type'] ?? 'Unknown') . "\n\n";
        
        if (!empty($context['file_content'])) {
            $prompt .= "## Code to Analyze\n";
            $prompt .= "```php\n" . $context['file_content'] . "\n```\n\n";
        }
        
        if (!empty($context['dependencies'])) {
            $prompt .= "## Dependencies\n";
            foreach ($context['dependencies'] as $dep) {
                $prompt .= "- " . $dep . "\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "## Analysis Focus Areas\n";
        $prompt .= "1. **Security vulnerabilities** (SQL injection, XSS, CSRF, etc.)\n";
        $prompt .= "2. **Performance issues** (inefficient queries, memory usage, etc.)\n";
        $prompt .= "3. **WordPress coding standards** compliance\n";
        $prompt .= "4. **Error handling** and edge cases\n";
        $prompt .= "5. **Code maintainability** and documentation\n\n";
        
        $prompt .= "## Required Output Format\n";
        $prompt .= "```json\n";
        $prompt .= "{\n";
        $prompt .= '  "issues": [';
        $prompt .= "\n";
        $prompt .= '    {';
        $prompt .= "\n";
        $prompt .= '      "type": "security|performance|standards|bug|maintainability",';
        $prompt .= "\n";
        $prompt .= '      "severity": "critical|high|medium|low",';
        $prompt .= "\n";
        $prompt .= '      "line": 0,';
        $prompt .= "\n";
        $prompt .= '      "message": "detailed description of the issue",';
        $prompt .= "\n";
        $prompt .= '      "recommendation": "specific fix recommendation"';
        $prompt .= "\n";
        $prompt .= '    }';
        $prompt .= "\n";
        $prompt .= "  ],\n";
        $prompt .= '  "suggestions": [';
        $prompt .= "\n";
        $prompt .= '    {';
        $prompt .= "\n";
        $prompt .= '      "type": "optimization|refactoring|documentation|testing",';
        $prompt .= "\n";
        $prompt .= '      "message": "improvement suggestion",';
        $prompt .= "\n";
        $prompt .= '      "benefit": "expected benefit of implementing this suggestion"';
        $prompt .= "\n";
        $prompt .= '    }';
        $prompt .= "\n";
        $prompt .= "  ],\n";
        $prompt .= '  "confidence": 0.9,';
        $prompt .= "\n";
        $prompt .= '  "overall_quality": "excellent|good|fair|poor"';
        $prompt .= "\n";
        $prompt .= "}\n";
        $prompt .= "```";
        
        return $prompt;
    }
    
    /**
     * Build generation prompt
     */
    private function build_generation_prompt($context) {
        $prompt = "You are an expert WordPress plugin developer. ";
        $prompt .= "Generate a complete, production-ready code component based on the following specification.\n\n";
        
        $prompt .= "## Component Specification\n";
        foreach ($context['specification'] as $key => $value) {
            $prompt .= "- **" . ucfirst(str_replace('_', ' ', $key)) . "**: " . $value . "\n";
        }
        
        $prompt .= "\n## System Context\n";
        $prompt .= "- **Coding Standards**: " . $context['system_context']['coding_standards'] . "\n";
        $prompt .= "- **PHP Version**: " . $context['system_context']['php_version'] . "\n";
        $prompt .= "- **Framework**: " . $context['system_context']['framework'] . "\n\n";
        
        $prompt .= "## Namespace Patterns\n";
        foreach ($context['system_context']['namespace_patterns'] as $ns => $plugin) {
            $prompt .= "- **" . $ns . "** for " . $plugin . "\n";
        }
        
        $prompt .= "\n## Requirements\n";
        $prompt .= "1. Follow WordPress coding standards\n";
        $prompt .= "2. Include proper error handling\n";
        $prompt .= "3. Add comprehensive PHPDoc comments\n";
        $prompt .= "4. Implement security best practices\n";
        $prompt .= "5. Use appropriate WordPress hooks and filters\n";
        $prompt .= "6. Include input validation and sanitization\n\n";
        
        $prompt .= "## Required Output Format\n";
        $prompt .= "```json\n";
        $prompt .= "{\n";
        $prompt .= '  "filename": "suggested-filename.php",';
        $prompt .= "\n";
        $prompt .= '  "code": "complete PHP code with proper namespace, class structure, methods, and documentation"';
        $prompt .= "\n";
        $prompt .= "}\n";
        $prompt .= "```\n\n";
        
        $prompt .= "Generate complete, working code that can be used immediately in a WordPress environment.";
        
        return $prompt;
    }
    
    /**
     * Make API request to Claude
     */
    private function make_request($data) {
        $cache_key = 'adn_claude_' . md5(json_encode($data));
        $cached_response = get_transient($cache_key);

        if (false !== $cached_response) {
            return $cached_response;
        }

        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01'
            ],
            'body' => json_encode($data),
            'timeout' => 60
        ];
        
        $response = wp_remote_request($this->api_url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) ? 
                           $error_data['error']['message'] : 
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
     * Parse auto-fix response
     */
    private function parse_autofix_response($text) {
        // Extract JSON from response
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $text, $matches)) {
            $json = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        
        // Try without code blocks
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        
        // Fallback parsing
        return [
            'type' => 'file_replacement',
            'file_path' => 'unknown',
            'content' => $text,
            'explanation' => 'Auto-parsed response from Claude'
        ];
    }
    
    /**
     * Parse recommendations response
     */
    private function parse_recommendations_response($text) {
        // Extract JSON array from response
        if (preg_match('/```json\s*(\[.*?\])\s*```/s', $text, $matches)) {
            $json = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        
        // Try without code blocks
        if (preg_match('/\[.*\]/s', $text, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        
        // Fallback parsing
        return [
            [
                'priority' => 'medium',
                'action' => 'Review system manually',
                'description' => 'Claude response could not be parsed properly: ' . substr($text, 0, 200),
                'component' => 'system',
                'estimated_time' => '30 minutes',
                'risk_level' => 'low'
            ]
        ];
    }
    
    /**
     * Parse analysis response
     */
    private function parse_analysis_response($text) {
        // Extract JSON from response
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $text, $matches)) {
            $json = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        
        // Try without code blocks
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        
        // Fallback parsing
        return [
            'issues' => [],
            'suggestions' => [
                [
                    'type' => 'maintainability',
                    'message' => 'Claude analysis response could not be parsed',
                    'benefit' => 'Manual review recommended'
                ]
            ],
            'confidence' => 0.5,
            'overall_quality' => 'unknown'
        ];
    }
    
    /**
     * Parse generation response
     */
    private function parse_generation_response($text) {
        // Extract JSON from response
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $text, $matches)) {
            $json = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        
        // Try without code blocks
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        
        // Fallback parsing - extract PHP code if present
        $code = $text;
        if (preg_match('/```php\s*(.*?)\s*```/s', $text, $matches)) {
            $code = $matches[1];
        }
        
        return [
            'filename' => 'generated-code.php',
            'code' => "<?php\n// Generated by Claude\n" . $code
        ];
    }
    
    /**
     * Calculate confidence score based on response
     */
    private function calculate_confidence($text) {
        $confidence = 0.6; // Base confidence for Claude
        
        // Increase confidence for structured JSON responses
        if (strpos($text, '```json') !== false) {
            $confidence += 0.2;
        }
        
        // Increase confidence for code blocks
        if (strpos($text, '```php') !== false || strpos($text, '```') !== false) {
            $confidence += 0.1;
        }
        
        // Increase confidence for detailed explanations
        if (strlen($text) > 300) {
            $confidence += 0.1;
        }
        
        // Increase confidence for specific technical terms
        $technical_terms = ['WordPress', 'PHP', 'function', 'class', 'method', 'hook', 'filter'];
        $term_count = 0;
        foreach ($technical_terms as $term) {
            if (stripos($text, $term) !== false) {
                $term_count++;
            }
        }
        
        if ($term_count >= 3) {
            $confidence += 0.05;
        }
        
        return min($confidence, 1.0);
    }
}