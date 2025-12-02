<?php
/**
 * Collects system metrics for monitoring
 */
namespace BLOOM\Monitoring;

class MetricsCollector {
    private $metrics_cache = [];
    private $cache_duration = 60; // 1 minute cache

    public function __construct() {
        $this->init_metrics_collection();
    }

    private function init_metrics_collection() {
        // Initialize metrics collection hooks
        add_action('bloom_collect_metrics', [$this, 'collect_system_metrics']);
        add_action('http_api_debug', [$this, 'track_external_requests'], 10, 5);
        add_action('rest_api_init', [$this, 'track_api_calls']);
        set_error_handler([$this, 'error_handler']);
    }

    /**
     * Collect comprehensive system metrics
     * @return array System metrics
     */
    public function collect_system_metrics() {
        $cache_key = 'bloom_system_metrics';
        
        // Check cache first
        if (isset($this->metrics_cache[$cache_key]) && 
            (time() - $this->metrics_cache[$cache_key]['timestamp']) < $this->cache_duration) {
            return $this->metrics_cache[$cache_key]['data'];
        }

        $metrics = [
            'memory' => $this->get_memory_metrics(),
            'performance' => $this->get_performance_metrics(),
            'database' => $this->get_database_metrics(),
            'network' => $this->get_network_metrics(),
            'errors' => $this->get_error_metrics(),
            'timestamp' => time()
        ];

        // Cache the metrics
        $this->metrics_cache[$cache_key] = [
            'data' => $metrics,
            'timestamp' => time()
        ];

        return $metrics;
    }

    /**
     * Get memory usage metrics
     * @return array Memory metrics
     */
    private function get_memory_metrics() {
        return [
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
            'limit' => $this->get_memory_limit(),
            'usage_percentage' => $this->calculate_memory_percentage()
        ];
    }

    /**
     * Get performance metrics
     * @return array Performance metrics
     */
    private function get_performance_metrics() {
        global $wpdb;
        
        return [
            'query_count' => $wpdb->num_queries ?? 0,
            'load_time' => $this->get_page_load_time(),
            'cpu_usage' => $this->estimate_cpu_usage(),
            'active_plugins' => count(get_option('active_plugins', []))
        ];
    }

    /**
     * Get database metrics
     * @return array Database metrics
     */
    private function get_database_metrics() {
        global $wpdb;
        
        $metrics = [
            'queries' => $wpdb->num_queries ?? 0,
            'connection_status' => 'active'
        ];

        // Check database connection
        try {
            $wpdb->get_var("SELECT 1");
        } catch (\Exception $e) {
            $metrics['connection_status'] = 'error';
            $metrics['error'] = $e->getMessage();
        }

        return $metrics;
    }

    /**
     * Get network metrics
     * @return array Network metrics
     */
    private function get_network_metrics() {
        return [
            'external_requests' => $this->count_external_requests(),
            'api_calls' => $this->count_api_calls(),
            'network_latency' => $this->measure_network_latency()
        ];
    }

    /**
     * Get error metrics
     * @return array Error metrics
     */
    private function get_error_metrics() {
        return [
            'php_errors' => $this->count_php_errors(),
            'wordpress_errors' => $this->count_wordpress_errors(),
            'plugin_errors' => $this->count_plugin_errors(),
            'error_rate' => $this->calculate_error_rate()
        ];
    }

    /**
     * Get memory limit in bytes
     * @return int Memory limit
     */
    private function get_memory_limit() {
        $limit = ini_get('memory_limit');
        if ($limit == -1) {
            return PHP_INT_MAX;
        }
        
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit)-1]);
        $limit = (int) $limit;
        
        switch($last) {
            case 'g': $limit *= 1024;
            case 'm': $limit *= 1024;
            case 'k': $limit *= 1024;
        }
        
        return $limit;
    }

    /**
     * Calculate memory usage percentage
     * @return float Memory usage percentage
     */
    private function calculate_memory_percentage() {
        $current = memory_get_usage(true);
        $limit = $this->get_memory_limit();
        
        if ($limit == PHP_INT_MAX) {
            return 0; // Unlimited memory
        }
        
        return ($current / $limit) * 100;
    }

    /**
     * Get page load time
     * @return float Load time in seconds
     */
    private function get_page_load_time() {
        if (defined('WP_START_TIMESTAMP')) {
            return microtime(true) - WP_START_TIMESTAMP;
        }
        return 0;
    }

    /**
     * Estimate CPU usage (basic implementation)
     * @return float Estimated CPU usage percentage
     */
    private function estimate_cpu_usage() {
        // Basic CPU estimation based on load time and memory usage
        $load_time = $this->get_page_load_time();
        $memory_percent = $this->calculate_memory_percentage();
        
        // Simple heuristic: combine load time and memory usage
        return min(($load_time * 10) + ($memory_percent * 0.5), 100);
    }

    /**
     * Count external requests (placeholder)
     * @return int Number of external requests
     */
    public function track_external_requests($response, $type, $class, $args, $url) {
        $requests = get_transient('bloom_external_requests') ?: [];
        $requests[] = [
            'url' => $url,
            'timestamp' => time()
        ];
        set_transient('bloom_external_requests', $requests, HOUR_IN_SECONDS);
    }

    private function count_external_requests() {
        $requests = get_transient('bloom_external_requests') ?: [];
        // Filter requests from the last hour
        $recent_requests = array_filter($requests, function($req) {
            return (time() - $req['timestamp']) < HOUR_IN_SECONDS;
        });
        return count($recent_requests);
    }

    /**
     * Count API calls (placeholder)
     * @return int Number of API calls
     */
    public function track_api_calls() {
        $requests = get_transient('bloom_api_calls') ?: [];
        $requests[] = [
            'route' => $_SERVER['REQUEST_URI'],
            'timestamp' => time()
        ];
        set_transient('bloom_api_calls', $requests, HOUR_IN_SECONDS);
    }

    private function count_api_calls() {
        $requests = get_transient('bloom_api_calls') ?: [];
        // Filter requests from the last hour
        $recent_requests = array_filter($requests, function($req) {
            return (time() - $req['timestamp']) < HOUR_IN_SECONDS;
        });
        return count($recent_requests);
    }

    /**
     * Measure network latency (placeholder)
     * @return float Network latency in milliseconds
     */
    private function measure_network_latency() {
        // Basic latency test to WordPress.org
        $start = microtime(true);
        $response = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/', [
            'timeout' => 5,
            'sslverify' => false
        ]);
        $end = microtime(true);
        
        if (is_wp_error($response)) {
            return -1; // Error
        }
        
        return ($end - $start) * 1000; // Convert to milliseconds
    }

    /**
     * Count PHP errors (placeholder)
     * @return int Number of PHP errors
     */
    public function error_handler($errno, $errstr, $errfile, $errline) {
        $errors = get_transient('bloom_php_errors') ?: [];
        $errors[] = [
            'errno' => $errno,
            'errstr' => $errstr,
            'errfile' => $errfile,
            'errline' => $errline,
            'timestamp' => time()
        ];
        set_transient('bloom_php_errors', $errors, HOUR_IN_SECONDS);
        return false; // Let the default error handler run
    }

    private function count_php_errors() {
        $errors = get_transient('bloom_php_errors') ?: [];
        // Filter errors from the last hour
        $recent_errors = array_filter($errors, function($err) {
            return (time() - $err['timestamp']) < HOUR_IN_SECONDS;
        });
        return count($recent_errors);
    }

    /**
     * Count WordPress errors (placeholder)
     * @return int Number of WordPress errors
     */
    private function count_wordpress_errors() {
        $errors = get_transient('bloom_php_errors') ?: [];
        $wordpress_errors = array_filter($errors, function($err) {
            return (time() - $err['timestamp']) < HOUR_IN_SECONDS && strpos($err['errfile'], ABSPATH . 'wp-admin') !== false || strpos($err['errfile'], ABSPATH . 'wp-includes') !== false;
        });
        return count($wordpress_errors);
    }

    /**
     * Count plugin errors (placeholder)
     * @return int Number of plugin errors
     */
    private function count_plugin_errors() {
        $errors = get_transient('bloom_php_errors') ?: [];
        $plugin_errors = array_filter($errors, function($err) {
            return (time() - $err['timestamp']) < HOUR_IN_SECONDS && strpos($err['errfile'], WP_PLUGIN_DIR) !== false;
        });
        return count($plugin_errors);
    }

    /**
     * Calculate error rate
     * @return float Error rate percentage
     */
    private function calculate_error_rate() {
        $total_errors = $this->count_php_errors() + $this->count_wordpress_errors() + $this->count_plugin_errors();
        $total_requests = max(1, $this->count_external_requests() + $this->count_api_calls());
        
        return ($total_errors / $total_requests) * 100;
    }

    /**
     * Clear metrics cache
     */
    public function clear_cache() {
        $this->metrics_cache = [];
    }
}