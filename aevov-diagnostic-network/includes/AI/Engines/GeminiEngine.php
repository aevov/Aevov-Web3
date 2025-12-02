<?php

namespace ADN\AI\Engines;

/**
 * Gemini AI Engine
 * 
 * Integrates with Google's Gemini API for AI-powered diagnostics,
 * auto-fixing, and code analysis.
 */
class GeminiEngine {
    
    private $api_key;
    private $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';
    private $configured = false;
    private $available = false;
    
    /**
     * Configure the engine with API settings
     */
    public function configure($settings) {
        $this->api_key = $settings['api_key'] ?? '';
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
                'contents' => [
                    [
                        'parts' => [
                            ['text' => 'Test connection']
                        ]
                    ]
                ]
            ]);
            
            return isset($response['candidates']);
        } catch (Exception $e) {
            error_log('Gemini API connection test failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Auto-fix a component issue
     */
    public function auto_fix($context) {
        if (!$this->is_available()) {
            return ['success' => false, 'message' => 'Gemini engine not available'];
        }
        
        $prompt = $this->build_autofix_prompt($context);
        
        try {
            $response = $this->make_request([
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);
            
            if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                return ['success' => false, 'message' => 'Invalid response from Gemini API'];
            }
            
            $result_text = $response['candidates'][0]['content']['parts'][0]['text'];
            $parsed_result = $this->parse_autofix_response($result_text);
            
            return [
                'success' => true,
                'fix' => $parsed_result,
                'confidence' => $this->calculate_confidence($result_text)
            ];
            
        } catch (Exception $e) {
            error_log('Gemini auto-fix failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'API request failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate recommendations for system issues
     */
    public function generate_recommendations($context) {
        if (!$this->is_available()) {
            return ['success' => false, 'message' => 'Gemini engine not available'];
        }
        
        $prompt = $this->build_recommendations_prompt($context);
        
        try {
            $response = $this->make_request([
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);
            
            if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                return ['success' => false, 'message' => 'Invalid response from Gemini API'];
            }
            
            $result_text = $response['candidates'][0]['content']['parts'][0]['text'];
            $recommendations = $this->parse_recommendations_response($result_text);
            
            return [
                'success' => true,
                'recommendations' => $recommendations
            ];
            
        } catch (Exception $e) {
            error_log('Gemini recommendations failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'API request failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Analyze code for potential issues
     */
    public function analyze_code($context) {
        if (!$this->is_available()) {
            return ['success' => false, 'message' => 'Gemini engine not available'];
        }
        
        $prompt = $this->build_analysis_prompt($context);
        
        try {
            $response = $this->make_request([
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);
            
            if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                return ['success' => false, 'message' => 'Invalid response from Gemini API'];
            }
            
            $result_text = $response['candidates'][0]['content']['parts'][0]['text'];
            $analysis = $this->parse_analysis_response($result_text);
            
            return [
                'success' => true,
                'analysis' => $analysis
            ];
            
        } catch (Exception $e) {
            error_log('Gemini code analysis failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'API request failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate code for missing components
     */
    public function generate_code($context) {
        if (!$this->is_available()) {
            return ['success' => false, 'message' => 'Gemini engine not available'];
        }
        
        $prompt = $this->build_generation_prompt($context);
        
        try {
            $response = $this->make_request([
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);
            
            if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                return ['success' => false, 'message' => 'Invalid response from Gemini API'];
            }
            
            $result_text = $response['candidates'][0]['content']['parts'][0]['text'];
            $generated = $this->parse_generation_response($result_text);
            
            return [
                'success' => true,
                'code' => $generated['code'],
                'filename' => $generated['filename'],
                'confidence' => $this->calculate_confidence($result_text)
            ];
            
        } catch (Exception $e) {
            error_log('Gemini code generation failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'API request failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Build auto-fix prompt
     */
    private function build_autofix_prompt($context) {
        $prompt = "You are an expert WordPress plugin developer and debugger. ";
        $prompt .= "Analyze the following component issue and provide a specific fix.\n\n";
        
        $prompt .= "Component: " . $context['component']['name'] . "\n";
        $prompt .= "Issue Type: " . $context['issue_type'] . "\n";
        $prompt .= "File: " . $context['component']['file'] . "\n\n";
        
        if (!empty($context['file_content'])) {
            $prompt .= "Current File Content:\n```php\n" . $context['file_content'] . "\n```\n\n";
        }
        
        if (!empty($context['error_logs'])) {
            $prompt .= "Recent Error Logs:\n" . implode("\n", array_slice($context['error_logs'], -5)) . "\n\n";
        }
        
        $prompt .= "System Info:\n";
        $prompt .= "- WordPress Version: " . ($context['system_info']['wordpress_version'] ?? 'Unknown') . "\n";
        $prompt .= "- PHP Version: " . ($context['system_info']['php_version'] ?? 'Unknown') . "\n\n";
        
        $prompt .= "Please provide a fix in the following JSON format:\n";
        $prompt .= "{\n";
        $prompt .= '  "type": "file_replacement|method_addition|configuration_change|dependency_fix",';
        $prompt .= "\n";
        $prompt .= '  "file_path": "relative/path/to/file.php",';
        $prompt .= "\n";
        $prompt .= '  "content": "complete file content or method code",';
        $prompt .= "\n";
        $prompt .= '  "class_name": "ClassName (for method_addition)",';
        $prompt .= "\n";
        $prompt .= '  "method_code": "method code (for method_addition)",';
        $prompt .= "\n";
        $prompt .= '  "explanation": "brief explanation of the fix"';
        $prompt .= "\n}\n";
        
        return $prompt;
    }
    
    /**
     * Build recommendations prompt
     */
    private function build_recommendations_prompt($context) {
        $prompt = "You are an expert WordPress system administrator. ";
        $prompt .= "Analyze the following system issues and provide actionable recommendations.\n\n";
        
        $prompt .= "System Issues:\n";
        foreach ($context['issues'] as $issue) {
            $prompt .= "- " . $issue['component'] . ": " . $issue['issue'] . " (Severity: " . $issue['severity'] . ")\n";
        }
        
        $prompt .= "\nSystem Info:\n";
        $prompt .= "- WordPress Version: " . ($context['system_info']['wordpress_version'] ?? 'Unknown') . "\n";
        $prompt .= "- PHP Version: " . ($context['system_info']['php_version'] ?? 'Unknown') . "\n";
        $prompt .= "- Active Plugins: " . count($context['system_info']['active_plugins'] ?? []) . "\n\n";
        
        $prompt .= "Please provide recommendations in JSON format:\n";
        $prompt .= "[\n";
        $prompt .= "  {\n";
        $prompt .= '    "priority": "high|medium|low",';
        $prompt .= "\n";
        $prompt .= '    "action": "specific action to take",';
        $prompt .= "\n";
        $prompt .= '    "description": "detailed description",';
        $prompt .= "\n";
        $prompt .= '    "component": "affected component"';
        $prompt .= "\n";
        $prompt .= "  }\n";
        $prompt .= "]\n";
        
        return $prompt;
    }
    
    /**
     * Build analysis prompt
     */
    private function build_analysis_prompt($context) {
        $prompt = "You are an expert code reviewer specializing in WordPress plugins. ";
        $prompt .= "Analyze the following code for potential issues, bugs, and improvements.\n\n";
        
        $prompt .= "Component: " . $context['component']['name'] . "\n";
        $prompt .= "File: " . $context['component']['file'] . "\n\n";
        
        if (!empty($context['file_content'])) {
            $prompt .= "Code to Analyze:\n```php\n" . $context['file_content'] . "\n```\n\n";
        }
        
        $prompt .= "Dependencies:\n";
        foreach ($context['dependencies'] as $dep) {
            $prompt .= "- " . $dep . "\n";
        }
        
        $prompt .= "\nPlease provide analysis in JSON format:\n";
        $prompt .= "{\n";
        $prompt .= '  "issues": [';
        $prompt .= "\n";
        $prompt .= '    {"type": "bug|warning|style", "line": 0, "message": "description"}';
        $prompt .= "\n";
        $prompt .= "  ],\n";
        $prompt .= '  "suggestions": [';
        $prompt .= "\n";
        $prompt .= '    {"type": "performance|security|maintainability", "message": "suggestion"}';
        $prompt .= "\n";
        $prompt .= "  ],\n";
        $prompt .= '  "confidence": 0.8';
        $prompt .= "\n}\n";
        
        return $prompt;
    }
    
    /**
     * Build generation prompt
     */
    private function build_generation_prompt($context) {
        $prompt = "You are an expert WordPress plugin developer. ";
        $prompt .= "Generate the requested code component based on the specification.\n\n";
        
        $prompt .= "Specification:\n";
        foreach ($context['specification'] as $key => $value) {
            $prompt .= "- " . ucfirst($key) . ": " . $value . "\n";
        }
        
        $prompt .= "\nSystem Context:\n";
        foreach ($context['system_context']['namespace_patterns'] as $ns => $plugin) {
            $prompt .= "- Namespace " . $ns . " for " . $plugin . "\n";
        }
        
        $prompt .= "- Coding Standards: " . $context['system_context']['coding_standards'] . "\n";
        $prompt .= "- PHP Version: " . $context['system_context']['php_version'] . "\n";
        $prompt .= "- Framework: " . $context['system_context']['framework'] . "\n\n";
        
        $prompt .= "Please provide the generated code in JSON format:\n";
        $prompt .= "{\n";
        $prompt .= '  "filename": "suggested-filename.php",';
        $prompt .= "\n";
        $prompt .= '  "code": "complete PHP code with proper namespace and structure"';
        $prompt .= "\n}\n";
        
        return $prompt;
    }
    
    /**
     * Make API request to Gemini
     */
    private function make_request($data) {
        $cache_key = 'adn_gemini_' . md5(json_encode($data));
        $cached_response = get_transient($cache_key);

        if (false !== $cached_response) {
            return $cached_response;
        }

        $url = $this->api_url . '?key=' . $this->api_key;
        
        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ];
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('API request failed: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from API');
        }
        
        if (isset($decoded['error'])) {
            throw new Exception('API error: ' . $decoded['error']['message']);
        }
        
        set_transient($cache_key, $decoded, HOUR_IN_SECONDS); // Cache for 1 hour

        return $decoded;
    }
    
    /**
     * Parse auto-fix response
     */
    private function parse_autofix_response($text) {
        // Extract JSON from response
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
            'explanation' => 'Auto-parsed response'
        ];
    }
    
    /**
     * Parse recommendations response
     */
    private function parse_recommendations_response($text) {
        // Extract JSON array from response
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
                'description' => 'AI response could not be parsed properly',
                'component' => 'system'
            ]
        ];
    }
    
    /**
     * Parse analysis response
     */
    private function parse_analysis_response($text) {
        // Extract JSON from response
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
                    'message' => 'AI analysis response could not be parsed'
                ]
            ],
            'confidence' => 0.5
        ];
    }
    
    /**
     * Parse generation response
     */
    private function parse_generation_response($text) {
        // Extract JSON from response
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        
        // Fallback parsing
        return [
            'filename' => 'generated-code.php',
            'code' => "<?php\n// Generated code could not be parsed properly\n" . $text
        ];
    }
    
    /**
     * Calculate confidence score based on response
     */
    private function calculate_confidence($text) {
        $confidence = 0.5; // Base confidence
        
        // Increase confidence for structured responses
        if (strpos($text, '{') !== false && strpos($text, '}') !== false) {
            $confidence += 0.2;
        }
        
        // Increase confidence for code blocks
        if (strpos($text, '```') !== false) {
            $confidence += 0.1;
        }
        
        // Increase confidence for detailed explanations
        if (strlen($text) > 200) {
            $confidence += 0.1;
        }
        
        return min($confidence, 1.0);
    }
}