<?php
/**
 * Debug Engine
 *
 * Comprehensive debugging system for Aevov AI Core:
 * - Multi-level logging (debug, info, warning, error, critical)
 * - Performance profiling and metrics
 * - Error tracking with stack traces
 * - Real-time debug dashboard data
 * - Query analysis and optimization hints
 *
 * @package AevovAICore
 */

namespace Aevov\AICore\Debug;

/**
 * Debug Engine Class
 */
class DebugEngine
{
    /**
     * Debug logger
     *
     * @var DebugLogger
     */
    private DebugLogger $logger;

    /**
     * Debug profiler
     *
     * @var DebugProfiler
     */
    private DebugProfiler $profiler;

    /**
     * Error handler
     *
     * @var ErrorHandler
     */
    private ErrorHandler $error_handler;

    /**
     * Debug enabled
     *
     * @var bool
     */
    private bool $enabled;

    /**
     * Debug level
     *
     * @var string
     */
    private string $debug_level;

    /**
     * Active profiles
     *
     * @var array
     */
    private array $active_profiles = [];

    /**
     * Metrics
     *
     * @var array
     */
    private array $metrics = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->enabled = (bool) get_option('aevov_debug_enabled', false);
        $this->debug_level = get_option('aevov_debug_level', 'info');

        $this->logger = new DebugLogger($this);
        $this->profiler = new DebugProfiler($this);
        $this->error_handler = new ErrorHandler($this);

        if ($this->enabled) {
            $this->error_handler->register();
        }
    }

    /**
     * Log message
     *
     * @param string $level Log level (debug, info, warning, error, critical)
     * @param string $component Component name
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public function log(string $level, string $component, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        if (!$this->should_log($level)) {
            return;
        }

        $this->logger->log($level, $component, $message, $context);
    }

    /**
     * Check if should log at this level
     *
     * @param string $level Log level
     * @return bool
     */
    private function should_log(string $level): bool
    {
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3, 'critical' => 4];

        $current_level = $levels[$this->debug_level] ?? 1;
        $message_level = $levels[$level] ?? 1;

        return $message_level >= $current_level;
    }

    /**
     * Start profiling
     *
     * @param string $profile_name Profile name
     * @param array $metadata Profile metadata
     * @return void
     */
    public function start_profile(string $profile_name, array $metadata = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->profiler->start($profile_name, $metadata);
    }

    /**
     * Stop profiling
     *
     * @param string $profile_name Profile name
     * @return array Profile results
     */
    public function stop_profile(string $profile_name): array
    {
        if (!$this->enabled) {
            return [];
        }

        return $this->profiler->stop($profile_name);
    }

    /**
     * Record metric
     *
     * @param string $metric_name Metric name
     * @param mixed $value Metric value
     * @param array $tags Tags
     * @return void
     */
    public function record_metric(string $metric_name, $value, array $tags = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->metrics[] = [
            'name' => $metric_name,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true)
        ];

        // Keep only last 1000 metrics in memory
        if (count($this->metrics) > 1000) {
            $this->metrics = array_slice($this->metrics, -1000);
        }
    }

    /**
     * Log API request
     *
     * @param string $provider Provider name
     * @param string $endpoint Endpoint
     * @param array $params Request parameters
     * @param float $start_time Start time
     * @return void
     */
    public function log_api_request(
        string $provider,
        string $endpoint,
        array $params,
        float $start_time
    ): void {
        if (!$this->enabled) {
            return;
        }

        $duration = microtime(true) - $start_time;

        $this->log('info', 'API', "Request to {$provider}", [
            'provider' => $provider,
            'endpoint' => $endpoint,
            'duration_ms' => (int) ($duration * 1000),
            'params' => $this->sanitize_params($params)
        ]);

        $this->record_metric('api_request_duration', $duration, [
            'provider' => $provider
        ]);
    }

    /**
     * Log API response
     *
     * @param string $provider Provider name
     * @param array $response Response data
     * @param bool $success Success status
     * @return void
     */
    public function log_api_response(string $provider, array $response, bool $success): void
    {
        if (!$this->enabled) {
            return;
        }

        $level = $success ? 'info' : 'error';

        $this->log($level, 'API', "Response from {$provider}", [
            'provider' => $provider,
            'success' => $success,
            'response_size' => strlen(wp_json_encode($response))
        ]);
    }

    /**
     * Log database query
     *
     * @param string $query SQL query
     * @param float $duration Query duration
     * @param int $rows Rows affected
     * @return void
     */
    public function log_query(string $query, float $duration, int $rows = 0): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->log('debug', 'Database', 'Query executed', [
            'query' => $query,
            'duration_ms' => (int) ($duration * 1000),
            'rows' => $rows
        ]);

        // Warn about slow queries
        if ($duration > 1.0) {
            $this->log('warning', 'Database', 'Slow query detected', [
                'query' => $query,
                'duration_ms' => (int) ($duration * 1000)
            ]);
        }

        $this->record_metric('db_query_duration', $duration);
    }

    /**
     * Log cache operation
     *
     * @param string $operation Operation (hit, miss, set, delete)
     * @param string $key Cache key
     * @param array $context Additional context
     * @return void
     */
    public function log_cache(string $operation, string $key, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->log('debug', 'Cache', "Cache {$operation}", array_merge([
            'key' => $key,
            'operation' => $operation
        ], $context));

        $this->record_metric('cache_operation', 1, [
            'operation' => $operation
        ]);
    }

    /**
     * Sanitize parameters (remove sensitive data)
     *
     * @param array $params Parameters
     * @return array Sanitized parameters
     */
    private function sanitize_params(array $params): array
    {
        $sensitive_keys = ['api_key', 'password', 'secret', 'token', 'auth'];

        $sanitized = $params;

        foreach ($sanitized as $key => $value) {
            foreach ($sensitive_keys as $sensitive) {
                if (stripos($key, $sensitive) !== false) {
                    $sanitized[$key] = '[REDACTED]';
                }
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_params($value);
            }
        }

        return $sanitized;
    }

    /**
     * Get recent logs
     *
     * @param int $limit Limit
     * @param array $filters Filters
     * @return array
     */
    public function get_recent_logs(int $limit = 100, array $filters = []): array
    {
        return $this->logger->get_recent($limit, $filters);
    }

    /**
     * Get metrics
     *
     * @param string|null $metric_name Specific metric name
     * @return array
     */
    public function get_metrics(?string $metric_name = null): array
    {
        if ($metric_name) {
            return array_filter($this->metrics, function ($metric) use ($metric_name) {
                return $metric['name'] === $metric_name;
            });
        }

        return $this->metrics;
    }

    /**
     * Get profile results
     *
     * @param string|null $profile_name Specific profile name
     * @return array
     */
    public function get_profiles(?string $profile_name = null): array
    {
        return $this->profiler->get_results($profile_name);
    }

    /**
     * Get system info
     *
     * @return array
     */
    public function get_system_info(): array
    {
        global $wpdb;

        return [
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'mysql_version' => $wpdb->db_version(),
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => size_format(memory_get_usage(true)),
            'peak_memory' => size_format(memory_get_peak_usage(true)),
            'max_execution_time' => ini_get('max_execution_time'),
            'timezone' => wp_timezone_string(),
            'debug_enabled' => $this->enabled,
            'debug_level' => $this->debug_level
        ];
    }

    /**
     * Get dashboard data
     *
     * @return array
     */
    public function get_dashboard_data(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aev_debug_logs';

        // Get error count by level
        $error_counts = $wpdb->get_results(
            "SELECT level, COUNT(*) as count
             FROM {$table}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY level",
            ARRAY_A
        );

        // Get slow queries
        $slow_queries = $wpdb->get_results(
            "SELECT *
             FROM {$table}
             WHERE component = 'Database'
             AND level = 'warning'
             AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
             ORDER BY created_at DESC
             LIMIT 10",
            ARRAY_A
        );

        // Calculate API metrics
        $api_metrics = [];
        foreach ($this->metrics as $metric) {
            if ($metric['name'] === 'api_request_duration') {
                $provider = $metric['tags']['provider'] ?? 'unknown';
                if (!isset($api_metrics[$provider])) {
                    $api_metrics[$provider] = [
                        'total_requests' => 0,
                        'total_duration' => 0,
                        'avg_duration' => 0
                    ];
                }
                $api_metrics[$provider]['total_requests']++;
                $api_metrics[$provider]['total_duration'] += $metric['value'];
            }
        }

        foreach ($api_metrics as $provider => &$metrics) {
            if ($metrics['total_requests'] > 0) {
                $metrics['avg_duration'] = $metrics['total_duration'] / $metrics['total_requests'];
            }
        }

        return [
            'system_info' => $this->get_system_info(),
            'error_counts' => $error_counts,
            'slow_queries' => $slow_queries,
            'api_metrics' => $api_metrics,
            'recent_logs' => $this->get_recent_logs(50),
            'active_profiles' => $this->profiler->get_active_profiles()
        ];
    }

    /**
     * Clear old logs
     *
     * @param int $days Days to keep
     * @return int Deleted count
     */
    public function clear_old_logs(int $days = 7): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aev_debug_logs';

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        $this->log('info', 'DebugEngine', 'Old logs cleared', [
            'deleted_count' => $deleted,
            'days' => $days
        ]);

        return $deleted;
    }

    /**
     * Enable debugging
     *
     * @return void
     */
    public function enable(): void
    {
        $this->enabled = true;
        update_option('aevov_debug_enabled', true);
        $this->error_handler->register();
    }

    /**
     * Disable debugging
     *
     * @return void
     */
    public function disable(): void
    {
        $this->enabled = false;
        update_option('aevov_debug_enabled', false);
    }

    /**
     * Set debug level
     *
     * @param string $level Debug level
     * @return void
     */
    public function set_level(string $level): void
    {
        $this->debug_level = $level;
        update_option('aevov_debug_level', $level);
    }

    /**
     * Is enabled
     *
     * @return bool
     */
    public function is_enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get logger
     *
     * @return DebugLogger
     */
    public function get_logger(): DebugLogger
    {
        return $this->logger;
    }

    /**
     * Get profiler
     *
     * @return DebugProfiler
     */
    public function get_profiler(): DebugProfiler
    {
        return $this->profiler;
    }

    /**
     * Get error handler
     *
     * @return ErrorHandler
     */
    public function get_error_handler(): ErrorHandler
    {
        return $this->error_handler;
    }
}
