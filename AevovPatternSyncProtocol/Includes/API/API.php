<?php
/**
 * Core API functionality and route registration
 * 
 * @package APS
 * @subpackage API
 */

namespace APS\API;

use APS\DB\MetricsDB;
use APS\Monitoring\AlertManager;
use APS\API\RateLimiter;

class API {
    private static $instance = null;
    private $metrics;
    private $alert_manager;
    private $namespace = 'aps/v1';
    private $endpoints = [];
    
    private function __construct() {
        $this->metrics = new MetricsDB();
        $this->alert_manager = new AlertManager();
        
        $this->init_endpoints();
        $this->init_hooks();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init_endpoints() {
        $this->endpoints = [
            // Pattern endpoints
            new Endpoints\PatternEndpoint($this->namespace),
            new Endpoints\PatternAnalysisEndpoint($this->namespace),
            new Endpoints\PatternComparisonEndpoint($this->namespace),
            new Endpoints\PatternDistributionEndpoint($this->namespace),
            
            // System endpoints
            new Endpoints\SystemStatusEndpoint($this->namespace),
            new Endpoints\MetricsEndpoint($this->namespace),
            new Endpoints\NetworkEndpoint($this->namespace),
            
            // Integration endpoints
            new Endpoints\BloomEndpoint($this->namespace),
            
            // Admin endpoints
            new Endpoints\AdminEndpoint($this->namespace),

            // Blockchain/Decentralized endpoints
            new Endpoints\DistributedLedgerEndpoint($this->namespace),
            new Endpoints\ConsensusMechanismEndpoint($this->namespace)
        ];
    }

    private function init_hooks() {
        if (function_exists('add_action') && function_exists('add_filter')) {
            add_action('rest_api_init', [$this, 'register_routes']);
            add_filter('rest_pre_dispatch', [$this, 'handle_rate_limiting'], 10, 3);
            add_filter('rest_authentication_errors', [$this, 'handle_authentication']);
            add_action('rest_api_init', [$this, 'register_api_fields']);
        }
    }

    public function register_routes() {
        foreach ($this->endpoints as $endpoint) {
            $endpoint->register_routes();
        }

        // Register core plugin info endpoint
        if (function_exists('register_rest_route')) {
            register_rest_route($this->namespace, '/info', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_plugin_info'],
                'permission_callback' => [$this, 'check_read_permission']
            ]
            ]);
        }
    }

    public function handle_rate_limiting($response, $handler, $request) {
        $rate_limiter = new RateLimiter();
        
        // Add rate limit headers to response
        if (function_exists('rest_get_server')) {
            $headers = $rate_limiter->get_rate_limit_headers($request);
            foreach ($headers as $header => $value) {
                rest_get_server()->send_header($header, $value);
            }
        }
        
        if (!$rate_limiter->check_limit($request)) {
            return new \WP_Error(
                'rate_limit_exceeded',
                'API rate limit exceeded',
                ['status' => 429]
            );
        }
        
        return $response;
    }

    public function handle_authentication($error) {
        // Allow public endpoints to pass through
        if ($this->is_public_endpoint()) {
            return $error;
        }

        // Check for API key authentication
        $api_key = $this->get_api_key();
        if (!$api_key) {
            return new \WP_Error(
                'rest_forbidden',
                'API key required',
                ['status' => 401]
            );
        }

        // Validate API key
        if (!$this->validate_api_key($api_key)) {
            return new \WP_Error(
                'rest_forbidden',
                'Invalid API key',
                ['status' => 401]
            );
        }

        return $error;
    }

    public function register_api_fields() {
        // Register pattern fields
        if (function_exists('register_rest_field')) {
            register_rest_field('aps_pattern', 'features', [
            'get_callback' => [$this, 'get_pattern_features'],
            'schema' => [
                'description' => 'Pattern features and analysis data',
                'type' => 'object'
            ]
            ]);

            // Register metrics fields
            register_rest_field('aps_metrics', 'data', [
            'get_callback' => [$this, 'get_metrics_data'],
            'schema' => [
                'description' => 'Detailed metrics data',
                'type' => 'object'
            ]
            ]);
        }
    }

    public function get_plugin_info() {
        return [
            'version' => APS_VERSION,
            'bloom_integration' => class_exists('BLOOM_Core'),
            'network_mode' => function_exists('is_multisite') ? is_multisite() : false,
            'endpoints' => $this->get_available_endpoints(),
            'features' => $this->get_enabled_features()
        ];
    }

    private function get_available_endpoints() {
        $endpoints = [];
        foreach ($this->endpoints as $endpoint) {
            $endpoints[] = $endpoint->get_endpoint_info();
        }
        return $endpoints;
    }

    private function get_enabled_features() {
        return [
            'pattern_analysis' => true,
            'network_distribution' => function_exists('is_multisite') ? is_multisite() : false,
            'bloom_integration' => class_exists('BLOOM_Core'),
            'metrics_collection' => true,
            'advanced_comparison' => true
        ];
    }

    public function get_pattern_features($pattern) {
        if (!isset($pattern['pattern_hash'])) {
            return null;
        }

        global $wpdb;
        $features = $wpdb->get_var($wpdb->prepare(
            "SELECT pattern_data FROM {$wpdb->prefix}aps_patterns 
             WHERE pattern_hash = %s",
            $pattern['pattern_hash']
        ));

        return $features ? json_decode($features, true) : null;
    }

    public function get_metrics_data($metrics) {
        if (!isset($metrics['metric_id'])) {
            return null;
        }

        return $this->metrics->get_metric_details($metrics['metric_id']);
    }

    private function get_api_key() {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function validate_api_key($api_key) {
        $valid_keys = function_exists('get_option') ? get_option('aps_api_keys', []) : [];
        return in_array($api_key, $valid_keys);
    }

    private function is_public_endpoint() {
        $current_endpoint = $this->get_current_endpoint();
        $public_endpoints = [
            '/aps/v1/info',
            '/aps/v1/status'
        ];
        
        return in_array($current_endpoint, $public_endpoints);
    }

    private function get_current_endpoint() {
        $request_uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($request_uri, PHP_URL_PATH);
        return $path;
    }

    public function check_read_permission() {
        return true;
    }

    public function check_write_permission() {
        return function_exists('current_user_can') ? current_user_can('manage_options') : false;
    }

    public function check_admin_permission() {
        return function_exists('current_user_can') ? current_user_can('manage_options') : false;
    }
}