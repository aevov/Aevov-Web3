<?php

namespace APS\Monitoring;

class APS_Performance_Monitor {
    private $metrics_db;
    private $cache;
    private $thresholds;
    private $check_interval = 60;
    private $alert_manager;

    public function __construct(
        APS_MetricsDB $metrics_db,
        APS_Cache $cache,
        APS_Alert_Manager $alert_manager
    ) {
        $this->metrics_db = $metrics_db;
        $this->cache = $cache;
        $this->alert_manager = $alert_manager;
        $this->init_thresholds();
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('aps_monitor_performance', [$this, 'collect_metrics']);
        add_action('aps_check_performance', [$this, 'check_thresholds']);
    }

    private function init_thresholds() {
        $this->thresholds = [
            'cpu_usage' => 85,
            'memory_usage' => 85,
            'disk_usage' => 90,
            'queue_size' => 1000,
            'error_rate' => 5,
            'response_time' => 1000,
            'pattern_success_rate' => 95,
            'sync_lag' => 300
        ];
    }

    public function collect_metrics() {
        $metrics = [
            'system' => $this->collect_system_metrics(),
            'processing' => $this->collect_processing_metrics(),
            'network' => $this->collect_network_metrics(),
            'patterns' => $this->collect_pattern_metrics()
        ];

        $this->store_metrics($metrics);
        $this->check_thresholds($metrics);
    }

    public function get_performance_report($timeframe = 'hour') {
        $cache_key = "performance_report_{$timeframe}";
        $report = $this->cache->get($cache_key);

        if ($report === false) {
            $report = $this->generate_performance_report($timeframe);
            $this->cache->set($cache_key, $report, 300);
        }

        return $report;
    }

    private function collect_system_metrics() {
        return [
            'cpu' => [
                'usage' => $this->get_cpu_usage(),
                'load' => sys_getloadavg(),
                'processes' => $this->get_process_count()
            ],
            'memory' => [
                'usage' => $this->get_memory_usage(),
                'available' => $this->get_available_memory(),
                'peak' => memory_get_peak_usage(true)
            ],
            'disk' => [
                'usage' => $this->get_disk_usage(),
                'io_stats' => $this->get_disk_io_stats(),
                'available' => disk_free_space('/')
            ],
            'network' => [
                'connections' => $this->get_network_connections(),
                'bandwidth' => $this->get_network_bandwidth(),
                'latency' => $this->get_network_latency()
            ]
        ];
    }

    private function collect_processing_metrics() {
        global $wpdb;

        return [
            'queue' => [
                'size' => $this->get_queue_size(),
                'processing_rate' => $this->calculate_processing_rate(),
                'error_rate' => $this->calculate_error_rate(),
                'average_time' => $this->get_average_processing_time()
            ],
            'patterns' => [
                'processed' => $this->get_processed_patterns_count(),
                'success_rate' => $this->get_pattern_success_rate(),
                'distribution' => $this->get_pattern_distribution()
            ],
            'cache' => [
                'hit_rate' => $this->get_cache_hit_rate(),
                'size' => $this->get_cache_size(),
                'efficiency' => $this->calculate_cache_efficiency()
            ]
        ];
    }

    private function collect_network_metrics() {
        return [
            'sites' => [
                'active' => $this->get_active_sites_count(),
                'response_times' => $this->get_site_response_times(),
                'health_status' => $this->get_site_health_status()
            ],
            'sync' => [
                'lag' => $this->calculate_sync_lag(),
                'success_rate' => $this->get_sync_success_rate(),
                'bandwidth_usage' => $this->get_sync_bandwidth_usage()
            ],
            'distribution' => [
                'balance' => $this->calculate_distribution_balance(),
                'efficiency' => $this->calculate_distribution_efficiency(),
                'coverage' => $this->get_distribution_coverage()
            ]
        ];
    }

    private function collect_pattern_metrics() {
        return [
            'recognition' => [
                'accuracy' => $this->calculate_recognition_accuracy(),
                'confidence' => $this->get_average_confidence(),
                'processing_time' => $this->get_pattern_processing_time()
            ],
            'storage' => [
                'total_patterns' => $this->get_total_patterns(),
                'pattern_types' => $this->get_pattern_type_distribution(),
                'storage_efficiency' => $this->calculate_storage_efficiency()
            ],
            'optimization' => [
                'compression_ratio' => $this->get_compression_ratio(),
                'deduplication_rate' => $this->get_deduplication_rate(),
                'index_efficiency' => $this->calculate_index_efficiency()
            ]
        ];
    }

    private function store_metrics($metrics) {
        foreach ($metrics as $category => $category_metrics) {
            foreach ($category_metrics as $metric_name => $value) {
                if (is_array($value)) {
                    foreach ($value as $sub_metric => $sub_value) {
                        $this->metrics_db->record_metric(
                            $category,
                            "{$metric_name}_{$sub_metric}",
                            $sub_value
                        );
                    }
                } else {
                    $this->metrics_db->record_metric(
                        $category,
                        $metric_name,
                        $value
                    );
                }
            }
        }
    }

    private function check_thresholds($metrics) {
        $violations = [];

        foreach ($this->thresholds as $metric => $threshold) {
            $value = $this->get_metric_value($metrics, $metric);
            if ($value !== null && $this->is_threshold_violated($metric, $value, $threshold)) {
                $violations[] = [
                    'metric' => $metric,
                    'value' => $value,
                    'threshold' => $threshold,
                    'timestamp' => time()
                ];
            }
        }

        if (!empty($violations)) {
            $this->handle_threshold_violations($violations);
        }
    }

    private function get_metric_value($metrics, $metric) {
        $parts = explode('_', $metric);
        $value = $metrics;

        foreach ($parts as $part) {
            if (isset($value[$part])) {
                $value = $value[$part];
            } else {
                return null;
            }
        }

        return $value;
    }

    private function is_threshold_violated($metric, $value, $threshold) {
        switch ($metric) {
            case 'pattern_success_rate':
                return $value < $threshold;
            case 'response_time':
                return $value > $threshold;
            default:
                return $value > $threshold;
        }
    }

    private function handle_threshold_violations($violations) {
        foreach ($violations as $violation) {
            $this->alert_manager->trigger_alert(
                'performance_threshold',
                $violation['metric'],
                $violation
            );
        }

        if (count($violations) >= 3) {
            $this->trigger_emergency_response($violations);
        }
    }

    private function trigger_emergency_response($violations) {
        do_action('aps_emergency_response', $violations);
        $this->log_emergency_event($violations);
    }

    private function get_cpu_usage() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0] * 100;
        }
        return null;
    }

    private function get_memory_usage() {
        $memory_limit = ini_get('memory_limit');
        $memory_usage = memory_get_usage(true);
        $memory_limit_bytes = $this->convert_to_bytes($memory_limit);
        
        return ($memory_usage / $memory_limit_bytes) * 100;
    }

    private function convert_to_bytes($value) {
        $unit = strtolower(substr($value, -1));
        $value = (int)$value;
        
        switch($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    private function generate_performance_report($timeframe) {
        $end_time = time();
        $start_time = $this->get_start_time($timeframe);
        
        return [
            'metrics' => $this->get_metrics_for_timeframe($start_time, $end_time),
            'analysis' => $this->analyze_performance($start_time, $end_time),
            'recommendations' => $this->generate_recommendations($start_time, $end_time)
        ];
    }

    private function get_start_time($timeframe) {
        switch ($timeframe) {
            case 'hour':
                return strtotime('-1 hour');
            case 'day':
                return strtotime('-1 day');
            case 'week':
                return strtotime('-1 week');
            default:
                return strtotime('-1 hour');
        }
    }

    private function log_emergency_event($violations) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'aps_emergency_log',
            [
                'event_type' => 'threshold_violation',
                'event_data' => json_encode($violations),
                'severity' => 'critical',
                'created_at' => current_time('mysql')
            ]
        );
    }
}