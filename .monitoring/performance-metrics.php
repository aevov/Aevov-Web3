<?php
/**
 * Performance Metrics Collection for Aevov Ecosystem
 *
 * Tracks performance metrics and sends them to monitoring services
 *
 * @package AevovMonitoring
 * @since 1.0.0
 */

namespace Aevov\Monitoring;

/**
 * Performance Metrics Collector
 *
 * Collects and reports performance metrics for Aevov plugins
 */
class Performance_Metrics {

    /**
     * Metrics storage
     */
    private $metrics = [];

    /**
     * Start times for timing operations
     */
    private $timers = [];

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Service configuration
     */
    private $config = [];

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Register shutdown hook to send metrics
        register_shutdown_function([$this, 'flush_metrics']);

        // Hook into WordPress performance points
        if (function_exists('add_action')) {
            add_action('init', [$this, 'record_init_time'], 999);
            add_action('wp_loaded', [$this, 'record_wp_loaded_time'], 999);
            add_action('shutdown', [$this, 'record_shutdown_time'], 1);
        }
    }

    /**
     * Initialize metrics collection
     *
     * @param array $config Configuration
     */
    public function init($config = []) {
        $this->config = array_merge([
            'service' => 'custom', // new_relic, datadog, custom
            'enabled' => true,
            'sample_rate' => 1.0, // Sample 100% of requests
            'endpoint' => '',
            'api_key' => '',
        ], $config);
    }

    /**
     * Start a timer
     *
     * @param string $name Timer name
     */
    public function start_timer($name) {
        $this->timers[$name] = microtime(true);
    }

    /**
     * Stop a timer and record metric
     *
     * @param string $name Timer name
     * @return float Duration in seconds
     */
    public function stop_timer($name) {
        if (!isset($this->timers[$name])) {
            return 0;
        }

        $duration = microtime(true) - $this->timers[$name];
        $this->record_metric("timer.{$name}", $duration, 'timing');

        unset($this->timers[$name]);

        return $duration;
    }

    /**
     * Record a metric
     *
     * @param string $name Metric name
     * @param mixed $value Metric value
     * @param string $type Metric type (gauge, counter, timing)
     */
    public function record_metric($name, $value, $type = 'gauge') {
        if (!$this->config['enabled']) {
            return;
        }

        // Sample rate check
        if ($this->config['sample_rate'] < 1.0 && rand(0, 100) / 100 > $this->config['sample_rate']) {
            return;
        }

        $this->metrics[] = [
            'name' => $name,
            'value' => $value,
            'type' => $type,
            'timestamp' => microtime(true),
            'tags' => $this->get_default_tags(),
        ];
    }

    /**
     * Increment a counter
     *
     * @param string $name Counter name
     * @param int $value Increment value
     */
    public function increment($name, $value = 1) {
        $this->record_metric($name, $value, 'counter');
    }

    /**
     * Record a gauge value
     *
     * @param string $name Gauge name
     * @param mixed $value Gauge value
     */
    public function gauge($name, $value) {
        $this->record_metric($name, $value, 'gauge');
    }

    /**
     * Record timing
     *
     * @param string $name Timing name
     * @param float $value Duration in seconds
     */
    public function timing($name, $value) {
        $this->record_metric($name, $value, 'timing');
    }

    /**
     * Record WordPress init time
     */
    public function record_init_time() {
        if (defined('AEVOV_START_TIME')) {
            $duration = microtime(true) - AEVOV_START_TIME;
            $this->timing('wordpress.init', $duration);
        }
    }

    /**
     * Record WordPress loaded time
     */
    public function record_wp_loaded_time() {
        if (defined('AEVOV_START_TIME')) {
            $duration = microtime(true) - AEVOV_START_TIME;
            $this->timing('wordpress.loaded', $duration);
        }
    }

    /**
     * Record shutdown metrics
     */
    public function record_shutdown_time() {
        // Record total execution time
        if (defined('AEVOV_START_TIME')) {
            $duration = microtime(true) - AEVOV_START_TIME;
            $this->timing('request.total', $duration);
        }

        // Record memory usage
        $this->gauge('memory.current', memory_get_usage(true));
        $this->gauge('memory.peak', memory_get_peak_usage(true));

        // Record database queries
        if (isset($GLOBALS['wpdb'])) {
            $this->gauge('database.queries', $GLOBALS['wpdb']->num_queries);
        }
    }

    /**
     * Get default tags for all metrics
     */
    private function get_default_tags() {
        return [
            'environment' => defined('WP_ENV') ? WP_ENV : 'production',
            'php_version' => PHP_VERSION,
            'wp_version' => function_exists('get_bloginfo') ? get_bloginfo('version') : 'unknown',
            'plugin' => 'aevov',
        ];
    }

    /**
     * Flush metrics to monitoring service
     */
    public function flush_metrics() {
        if (empty($this->metrics)) {
            return;
        }

        switch ($this->config['service']) {
            case 'new_relic':
                $this->send_to_new_relic();
                break;
            case 'datadog':
                $this->send_to_datadog();
                break;
            case 'prometheus':
                $this->send_to_prometheus();
                break;
            case 'custom':
                $this->send_to_custom();
                break;
        }

        // Clear metrics after sending
        $this->metrics = [];
    }

    /**
     * Send metrics to New Relic
     */
    private function send_to_new_relic() {
        if (!extension_loaded('newrelic')) {
            return;
        }

        foreach ($this->metrics as $metric) {
            switch ($metric['type']) {
                case 'timing':
                    newrelic_custom_metric('Custom/' . $metric['name'], $metric['value'] * 1000);
                    break;
                case 'counter':
                    newrelic_custom_metric('Custom/' . $metric['name'], $metric['value']);
                    break;
                case 'gauge':
                    newrelic_custom_metric('Custom/' . $metric['name'], $metric['value']);
                    break;
            }
        }
    }

    /**
     * Send metrics to Datadog
     */
    private function send_to_datadog() {
        if (!isset($this->config['datadog_host'])) {
            return;
        }

        // Use DogStatsD protocol (UDP)
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        foreach ($this->metrics as $metric) {
            $tags = [];
            foreach ($metric['tags'] as $key => $value) {
                $tags[] = "{$key}:{$value}";
            }
            $tag_string = implode(',', $tags);

            $statsd_format = sprintf(
                "%s:%s|%s|#%s",
                $metric['name'],
                $metric['value'],
                $this->get_statsd_type($metric['type']),
                $tag_string
            );

            socket_sendto(
                $socket,
                $statsd_format,
                strlen($statsd_format),
                0,
                $this->config['datadog_host'],
                $this->config['datadog_port'] ?? 8125
            );
        }

        socket_close($socket);
    }

    /**
     * Send metrics to Prometheus
     */
    private function send_to_prometheus() {
        // Prometheus typically scrapes metrics via HTTP endpoint
        // Store metrics in a shared location for the /metrics endpoint
        $metrics_file = sys_get_temp_dir() . '/aevov_metrics.json';

        $existing_metrics = [];
        if (file_exists($metrics_file)) {
            $existing_metrics = json_decode(file_get_contents($metrics_file), true) ?? [];
        }

        $existing_metrics = array_merge($existing_metrics, $this->metrics);

        file_put_contents($metrics_file, json_encode($existing_metrics));
    }

    /**
     * Send metrics to custom endpoint
     */
    private function send_to_custom() {
        if (empty($this->config['endpoint'])) {
            return;
        }

        do_action('aevov_flush_metrics', $this->metrics);

        // Send to custom API
        if (function_exists('wp_remote_post')) {
            wp_remote_post($this->config['endpoint'], [
                'body' => json_encode([
                    'metrics' => $this->metrics,
                    'timestamp' => time(),
                ]),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-API-Key' => $this->config['api_key'] ?? '',
                ],
                'timeout' => 5,
            ]);
        }
    }

    /**
     * Get StatsD metric type
     */
    private function get_statsd_type($type) {
        $type_map = [
            'counter' => 'c',
            'gauge' => 'g',
            'timing' => 'ms',
        ];

        return $type_map[$type] ?? 'g';
    }
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Start a performance timer
 *
 * @param string $name Timer name
 */
function aevov_start_timer($name) {
    Performance_Metrics::instance()->start_timer($name);
}

/**
 * Stop a performance timer
 *
 * @param string $name Timer name
 * @return float Duration in seconds
 */
function aevov_stop_timer($name) {
    return Performance_Metrics::instance()->stop_timer($name);
}

/**
 * Record a metric
 *
 * @param string $name Metric name
 * @param mixed $value Metric value
 */
function aevov_record_metric($name, $value) {
    Performance_Metrics::instance()->gauge($name, $value);
}

// ============================================================================
// Usage Examples
// ============================================================================

/*
// Initialize performance metrics
Performance_Metrics::instance()->init([
    'service' => 'datadog',
    'datadog_host' => 'localhost',
    'datadog_port' => 8125,
]);

// Time a code block
aevov_start_timer('expensive_operation');
// ... expensive code ...
$duration = aevov_stop_timer('expensive_operation');

// Record custom metrics
aevov_record_metric('active_users', 150);
Performance_Metrics::instance()->increment('api_calls');
Performance_Metrics::instance()->timing('database_query', 0.023);
*/
