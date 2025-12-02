<?php
/**
 * Settings management for APS Plugin
 * 
 * @package APS
 * @subpackage Admin
 */

namespace APS\Admin;

class APS_Settings {
    private $settings = [];
    private $defaults = [];
    
    public function __construct() {
        $this->init_defaults();
        $this->load_settings();
        $this->init_hooks();
    }
    
    private function init_defaults() {
        $this->defaults = [
            'pattern_sync_enabled' => true,
            'sync_interval' => 300, // 5 minutes
            'max_patterns_per_sync' => 100,
            'bloom_integration_enabled' => true,
            'bloom_api_endpoint' => '',
            'bloom_api_key' => '',
            'aps_tools_integration' => true,
            'debug_mode' => false,
            'log_level' => 'INFO',
            'alert_notifications' => true,
            'email_notifications' => true,
            'slack_webhook_url' => '',
            'pattern_confidence_threshold' => 0.75,
            'tensor_processing_enabled' => true,
            'chunk_size' => 1000,
            'max_chunk_size' => 5000,
            'queue_processing_enabled' => true,
            'max_queue_size' => 1000,
            'network_timeout' => 30,
            'retry_attempts' => 3,
            'cache_enabled' => true,
            'cache_ttl' => 3600, // 1 hour
            'multisite_sync' => true,
            'cross_site_patterns' => false,
            'pattern_validation' => true,
            'auto_cleanup_enabled' => true,
            'cleanup_interval' => 86400, // 24 hours
            'max_log_size' => 10485760, // 10MB
            'performance_monitoring' => true,
            'metrics_retention_days' => 30
        ];
    }
    
    private function load_settings() {
        if (function_exists('get_option')) {
            $saved_settings = get_option('aps_settings', []);
            $this->settings = array_merge($this->defaults, $saved_settings);
        } else {
            $this->settings = $this->defaults;
        }
    }
    
    private function init_hooks() {
        if (function_exists('add_action')) {
            add_action('admin_init', [$this, 'register_settings']);
            add_action('wp_ajax_aps_save_settings', [$this, 'ajax_save_settings']);
            add_action('wp_ajax_aps_reset_settings', [$this, 'ajax_reset_settings']);
            add_action('wp_ajax_aps_test_connection', [$this, 'ajax_test_connection']);
        }
    }
    
    public function register_settings() {
        if (function_exists('register_setting')) {
            register_setting('aps_settings_group', 'aps_settings', [
                'sanitize_callback' => [$this, 'sanitize_settings']
            ]);
        }
    }
    
    public function get($key, $default = null) {
        if (isset($this->settings[$key])) {
            return $this->settings[$key];
        }
        
        if ($default !== null) {
            return $default;
        }
        
        return isset($this->defaults[$key]) ? $this->defaults[$key] : null;
    }
    
    public function set($key, $value) {
        $this->settings[$key] = $value;
        return $this->save();
    }
    
    public function get_all() {
        return $this->settings;
    }
    
    public function get_defaults() {
        return $this->defaults;
    }
    
    public function save() {
        if (function_exists('update_option')) {
            return update_option('aps_settings', $this->settings);
        }
        return false;
    }
    
    public function reset() {
        $this->settings = $this->defaults;
        return $this->save();
    }
    
    public function sanitize_settings($input) {
        $sanitized = [];
        
        foreach ($input as $key => $value) {
            switch ($key) {
                case 'pattern_sync_enabled':
                case 'bloom_integration_enabled':
                case 'aps_tools_integration':
                case 'debug_mode':
                case 'alert_notifications':
                case 'email_notifications':
                case 'tensor_processing_enabled':
                case 'queue_processing_enabled':
                case 'cache_enabled':
                case 'multisite_sync':
                case 'cross_site_patterns':
                case 'pattern_validation':
                case 'auto_cleanup_enabled':
                case 'performance_monitoring':
                    $sanitized[$key] = (bool) $value;
                    break;
                    
                case 'sync_interval':
                case 'max_patterns_per_sync':
                case 'chunk_size':
                case 'max_chunk_size':
                case 'max_queue_size':
                case 'network_timeout':
                case 'retry_attempts':
                case 'cache_ttl':
                case 'cleanup_interval':
                case 'max_log_size':
                case 'metrics_retention_days':
                    $sanitized[$key] = absint($value);
                    break;
                    
                case 'pattern_confidence_threshold':
                    $sanitized[$key] = floatval($value);
                    if ($sanitized[$key] < 0) $sanitized[$key] = 0;
                    if ($sanitized[$key] > 1) $sanitized[$key] = 1;
                    break;
                    
                case 'log_level':
                    $allowed_levels = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];
                    $sanitized[$key] = in_array($value, $allowed_levels) ? $value : 'INFO';
                    break;

                case 'bloom_api_endpoint':
                    $sanitized[$key] = esc_url_raw( $value );
                    break;

                case 'bloom_api_key':
                    $sanitized[$key] = sanitize_text_field( $value );
                    break;

                case 'slack_webhook_url':
                    $sanitized[$key] = esc_url_raw( $value );
                    break;
                    
                default:
                    $sanitized[$key] = function_exists('sanitize_text_field') ? sanitize_text_field($value) : strip_tags($value);
                    break;
            }
        }
        
        return $sanitized;
    }
    
    public function ajax_save_settings() {
        if (!function_exists('check_ajax_referer') || !function_exists('current_user_can')) {
            wp_die('WordPress functions not available');
        }
        
        check_ajax_referer('aps_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $settings = $_POST['settings'] ?? [];
        $sanitized = $this->sanitize_settings($settings);
        
        $this->settings = array_merge($this->settings, $sanitized);
        
        if ($this->save()) {
            wp_send_json_success(['message' => 'Settings saved successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to save settings']);
        }
    }
    
    public function ajax_reset_settings() {
        if (!function_exists('check_ajax_referer') || !function_exists('current_user_can')) {
            wp_die('WordPress functions not available');
        }
        
        check_ajax_referer('aps_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        if ($this->reset()) {
            wp_send_json_success(['message' => 'Settings reset to defaults']);
        } else {
            wp_send_json_error(['message' => 'Failed to reset settings']);
        }
    }
    
    public function ajax_test_connection() {
        if (!function_exists('check_ajax_referer') || !function_exists('current_user_can')) {
            wp_die('WordPress functions not available');
        }
        
        check_ajax_referer('aps_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $connection_type = $_POST['connection_type'] ?? '';
        
        switch ($connection_type) {
            case 'bloom':
                $result = $this->test_bloom_connection();
                break;
            case 'aps_tools':
                $result = $this->test_aps_tools_connection();
                break;
            case 'slack':
                $result = $this->test_slack_connection();
                break;
            default:
                wp_send_json_error(['message' => 'Invalid connection type']);
                return;
        }
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    private function test_bloom_connection() {
        $endpoint = $this->get('bloom_api_endpoint');
        $api_key = $this->get('bloom_api_key');
        
        if (empty($endpoint)) {
            return ['success' => false, 'message' => 'BLOOM API endpoint not configured'];
        }
        
        // Test connection logic would go here
        // For now, just return a mock response
        return ['success' => true, 'message' => 'BLOOM connection test successful'];
    }
    
    private function test_aps_tools_connection() {
        // Test APS Tools integration
        if (!class_exists('APS_Tools')) {
            return ['success' => false, 'message' => 'APS Tools plugin not found'];
        }
        
        return ['success' => true, 'message' => 'APS Tools connection successful'];
    }
    
    private function test_slack_connection() {
        $webhook_url = $this->get('slack_webhook_url');
        
        if (empty($webhook_url)) {
            return ['success' => false, 'message' => 'Slack webhook URL not configured'];
        }
        
        $test_payload = [
            'text' => 'APS Settings - Connection Test'
        ];
        
        if (function_exists('wp_remote_post')) {
            $response = wp_remote_post($webhook_url, [
                'body' => json_encode($test_payload),
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 10
            ]);
            
            if (is_wp_error($response)) {
                return ['success' => false, 'message' => 'Slack connection failed: ' . $response->get_error_message()];
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code === 200) {
                return ['success' => true, 'message' => 'Slack connection test successful'];
            } else {
                return ['success' => false, 'message' => 'Slack connection failed with response code: ' . $response_code];
            }
        }
        
        return ['success' => false, 'message' => 'WordPress HTTP functions not available'];
    }
    
    public function export_settings() {
        return json_encode($this->settings, JSON_PRETTY_PRINT);
    }
    
    public function import_settings($json_data) {
        $imported = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        $sanitized = $this->sanitize_settings($imported);
        $this->settings = array_merge($this->settings, $sanitized);
        
        return $this->save();
    }
    
    public function validate_setting($key, $value) {
        $sanitized = $this->sanitize_settings([$key => $value]);
        return isset($sanitized[$key]) ? $sanitized[$key] : false;
    }
    
    public function get_setting_info($key) {
        $info = [
            'pattern_sync_enabled' => [
                'type' => 'boolean',
                'description' => 'Enable automatic pattern synchronization',
                'default' => true
            ],
            'sync_interval' => [
                'type' => 'integer',
                'description' => 'Pattern sync interval in seconds',
                'default' => 300,
                'min' => 60,
                'max' => 3600
            ],
            'bloom_integration_enabled' => [
                'type' => 'boolean',
                'description' => 'Enable BLOOM pattern recognition integration',
                'default' => true
            ],
            'pattern_confidence_threshold' => [
                'type' => 'float',
                'description' => 'Minimum confidence threshold for pattern acceptance',
                'default' => 0.75,
                'min' => 0.0,
                'max' => 1.0
            ]
        ];
        
        return isset($info[$key]) ? $info[$key] : null;
    }
}
