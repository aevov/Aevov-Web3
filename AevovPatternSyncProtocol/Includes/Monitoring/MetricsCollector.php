<?php
/**
 * Metrics collection and storage system
 * 
 * @package APS
 * @subpackage Monitoring
 */

namespace APS\Monitoring;

class MetricsCollector {
    private $metrics_cache = [];
    private $cache_lifetime = 60;
    private $batch_size = 100;
    private $metrics_table;
    
    public function __construct() {
        global $wpdb;
        $this->metrics_table = $wpdb->prefix . 'aps_metrics';
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('aps_store_metrics_batch', [$this, 'store_metrics_batch']);
        add_action('aps_cleanup_metrics', [$this, 'cleanup_old_metrics']);
        add_action('shutdown', [$this, 'flush_metrics_cache']);
    }

    public function collect_system_metrics() {
        $metrics = [
            'timestamp' => time(),
            'type' => 'system',
            'data' => [
                'memory' => $this->collect_memory_metrics(),
                'cpu' => $this->collect_cpu_metrics(),
                'disk' => $this->collect_disk_metrics(),
                'network' => $this->collect_network_metrics()
            ]
        ];

        $this->store_metrics($metrics);
        return $metrics;
    }

    public function collect_pattern_metrics($pattern_data = null) {
        $metrics = [
            'timestamp' => time(),
            'type' => 'pattern',
            'data' => [
                'processing' => $this->collect_processing_metrics(),
                'distribution' => $this->collect_distribution_metrics(),
                'confidence' => $this->collect_confidence_metrics(),
                'pattern_specific' => $pattern_data ? $this->analyze_pattern($pattern_data) : null
            ]
        ];

        $this->store_metrics($metrics);
        return $metrics;
    }

    public function collect_bloom_metrics() {
        $metrics = [
            'timestamp' => time(),
            'type' => 'bloom',
            'data' => [
                'sync_status' => $this->get_bloom_sync_status(),
                'processing_stats' => $this->get_bloom_processing_stats(),
                'integration_health' => $this->check_bloom_integration_health()
            ]
        ];

        $this->store_metrics($metrics);
        return $metrics;
    }

    private function collect_memory_metrics() {
        $memory_limit = $this->parse_memory_limit(ini_get('memory_limit'));
        $memory_usage = memory_get_usage(true);
        $peak_usage = memory_get_peak_usage(true);

        return [
            'usage' => $memory_usage,
            'peak' => $peak_usage,
            'limit' => $memory_limit,
            'usage_percentage' => ($memory_usage / $memory_limit) * 100,
            'available' => $memory_limit - $memory_usage,
            'wordpress_usage' => $this->get_wordpress_memory_usage()
        ];
    }

    private function collect_cpu_metrics() {
        $load = sys_getloadavg();
        $processor_count = $this->get_processor_count();

        return [
            'load_1' => $load[0],
            'load_5' => $load[1],
            'load_15' => $load[2],
            'processor_count' => $processor_count,
            'usage_percentage' => ($load[0] / $processor_count) * 100,
            'process_stats' => $this->get_process_stats()
        ];
    }

    private function collect_disk_metrics() {
        $base_path = ABSPATH;
        $total = disk_total_space($base_path);
        $free = disk_free_space($base_path);
        $used = $total - $free;

        return [
            'total' => $total,
            'free' => $free,
            'used' => $used,
            'usage_percentage' => ($used / $total) * 100,
            'io_stats' => $this->get_disk_io_stats(),
            'upload_dir_stats' => $this->get_upload_dir_stats()
        ];
    }

    private function collect_network_metrics() {
        return [
            'active_connections' => $this->get_active_connections(),
            'bandwidth_usage' => $this->get_bandwidth_stats(),
            'latency' => $this->measure_network_latency(),
            'sites' => $this->get_site_connectivity()
        ];
    }

    private function get_active_connections() {
        if (function_exists('exec')) {
            $output = [];
            exec('netstat -an | grep ESTABLISHED | wc -l', $output);
            return (int)$output[0];
        }
        return 0;
    }

    private function get_bandwidth_stats() {
        if (function_exists('exec')) {
            $output = [];
            exec("cat /proc/net/dev", $output);

            $bandwidth = [];
            foreach ($output as $line) {
                if (strpos($line, ':') !== false) {
                    list($interface, $data) = explode(':', $line, 2);
                    $values = preg_split('/\s+/', trim($data));

                    $bandwidth[trim($interface)] = [
                        'received_bytes' => (int)$values[0],
                        'transmitted_bytes' => (int)$values[8]
                    ];
                }
            }
            return $bandwidth;
        }
        return null;
    }

    private function measure_network_latency() {
        if (function_exists('exec')) {
            $output = [];
            exec("ping -c 1 google.com | grep 'time='", $output);

            if (!empty($output)) {
                preg_match('/time=([\d.]+) ms/', $output[0], $matches);
                return isset($matches[1]) ? (float)$matches[1] : null;
            }            
        }
        return null;
    }

    private function get_site_connectivity() {
        $sites = ['https://google.com', 'https://wordpress.org'];
        $results = [];

        foreach ($sites as $site) {
            $ch = curl_init($site);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_RETURN, true);

            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $results[$site] = ($http_code >= 200 && $http_code < 400) ? 'reachable' : 'unreachable';
        }
        return $results;
    }

    private function collect_processing_metrics() {
        global $wpdb;

        $processing_stats = [
            'total_patterns' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aps_patterns"),
            'pending_patterns' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aps_pattern_queue WHERE status = 'pending'"
            ),
            'processing_rate' => $this->calculate_processing_rate(),
            'error_rate' => $this->calculate_error_rate(),
            'average_processing_time' => $this->get_average_processing_time()
        ];

        return $processing_stats;
    }

    private function collect_distribution_metrics() {
        global $wpdb;

        return [
            'by_type' => $wpdb->get_results(
                "SELECT pattern_type, COUNT(*) as count 
                 FROM {$wpdb->prefix}aps_patterns 
                 GROUP BY pattern_type",
                ARRAY_A
            ),
            'by_confidence' => $this->get_confidence_distribution(),
            'by_site' => $this->get_site_distribution(),
            'processing_distribution' => $this->get_processing_distribution()
        ];
    }

    private function collect_confidence_metrics() {
        global $wpdb;

        return [
            'average_confidence' => (float)$wpdb->get_var(
                "SELECT AVG(confidence) FROM {$wpdb->prefix}aps_patterns"
            ),
            'confidence_trend' => $this->calculate_confidence_trend(),
            'low_confidence_patterns' => $this->get_low_confidence_patterns(),
            'confidence_by_type' => $this->get_confidence_by_type()
        ];
    }

    private function store_metrics($metrics) {
        global $wpdb;

        $wpdb->insert(
            $this->metrics_table,
            [
                'metric_type' => $metrics['type'],
                'metric_data' => json_encode($metrics['data']),
                'timestamp' => date('Y-m-d H:i:s', $metrics['timestamp']),
                'site_id' => get_current_blog_id()
            ],
            [
                '%s',
                '%s',
                '%s',
                '%d'
            ]
        );

        $this->cache_metrics($metrics);
    }

    private function cache_metrics($metrics) {
        $cache_key = "aps_metrics_{$metrics['type']}_" . get_current_blog_id();
        wp_cache_set($cache_key, $metrics, '', $this->cache_lifetime);
        $this->metrics_cache[$metrics['type']] = $metrics;
    }

    public function get_current_metrics($type = null) {
        if ($type) {
            return $this->metrics_cache[$type] ?? $this->load_metrics($type);
        }
        return $this->metrics_cache;
    }

    private function load_metrics($type) {
        $cache_key = "aps_metrics_{$type}_" . get_current_blog_id();
        $metrics = wp_cache_get($cache_key);

        if (!$metrics) {
            global $wpdb;
            $metrics = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$this->metrics_table} 
                     WHERE metric_type = %s 
                     AND site_id = %d 
                     ORDER BY timestamp DESC 
                     LIMIT 1",
                    $type,
                    get_current_blog_id()
                ),
                ARRAY_A
            );

            if ($metrics) {
                $metrics['data'] = json_decode($metrics['metric_data'], true);
                $this->cache_metrics($metrics);
            }
        }

        return $metrics;
    }

    public function get_metrics_history($type, $duration = '1 hour') {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->metrics_table} 
                 WHERE metric_type = %s 
                 AND site_id = %d 
                 AND timestamp >= DATE_SUB(NOW(), INTERVAL %s) 
                 ORDER BY timestamp ASC",
                $type,
                get_current_blog_id(),
                $duration
            ),
            ARRAY_A
        );
    }

    public function cleanup_old_metrics($days = 30) {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->metrics_table} 
                 WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    private function parse_memory_limit($limit) {
        $value = (int)$limit;
        
        switch (strtolower(substr($limit, -1))) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    private function get_processor_count() {
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            return count($matches[0]);
        }
        return 1;
    }

    private function get_process_stats() {
        if (function_exists('exec')) {
            $output = [];
            exec('ps aux | grep php', $output);
            return [
                'total_processes' => count($output),
                'wordpress_processes' => count(array_filter($output, function($line) {
                    return strpos($line, 'wordpress') !== false;
                }))
            ];
        }
        return null;
    }

    private function get_disk_io_stats() {
        if (is_readable('/proc/diskstats')) {
            $stats = file_get_contents('/proc/diskstats');
            // Process disk stats
            return $stats;
        }
        return null;
    }

    private function get_upload_dir_stats() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];

        return [
            'total_files' => $this->count_files($base_dir),
            'total_size' => $this->get_directory_size($base_dir),
            'free_space' => disk_free_space($base_dir)
        ];
    }

    private function get_wordpress_memory_usage() {
        global $wpdb;

        return [
            'database_size' => $this->get_database_size(),
            'object_cache_size' => $this->get_object_cache_size(),
            'transient_size' => $this->get_transients_size()
        ];
    }

    private function get_database_size() {
        global $wpdb;
        
        $size = $wpdb->get_row("SELECT SUM(data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
        return $size ? $size->size : 0;
    }

    private function get_object_cache_size() {
        global $wp_object_cache;
        
        if (is_object($wp_object_cache) && isset($wp_object_cache->cache)) {
            return strlen(serialize($wp_object_cache->cache));
        }
        return 0;
    }

    private function calculate_processing_rate() {
        global $wpdb;
        
        $processed = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aps_pattern_log 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)"
        );

        return $processed;
    }

    private function calculate_error_rate() {
        global $wpdb;
        
        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aps_pattern_log 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );

        if (!$total) {
            return 0;
        }

        $errors = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aps_pattern_log 
             WHERE status = 'error' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );

        return ($errors / $total) * 100;
    }

    public function record_pattern_metric($pattern_data) {
        $metrics = [
            'timestamp' => time(),
            'type' => 'pattern_processing',
            'data' => [
                'pattern_id' => $pattern_data['id'],
                'processing_time' => $pattern_data['processing_time'],
                'confidence' => $pattern_data['confidence'],
                'pattern_type' => $pattern_data['type'],
                'tensor_size' => $pattern_data['tensor_size']
            ]
        ];

        $this->store_metrics($metrics);
    }

    public function flush_metrics_cache() {
        foreach ($this->metrics_cache as $type => $metrics) {
            $cache_key = "aps_metrics_{$type}_" . get_current_blog_id();
            wp_cache_delete($cache_key);
        }
        $this->metrics_cache = [];
    }
}