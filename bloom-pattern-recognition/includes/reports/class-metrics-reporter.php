<?php
/**
 * Reports on system metrics and performance
 */
class BLOOM_Metrics_Reporter {
    private $metrics_collector;
    private $cache;
    private $report_interval = 3600; // 1 hour
    
    public function __construct() {
        $this->metrics_collector = new BLOOM_Metrics_Collector();
        $this->cache = new BLOOM_Cache_Manager();
        
        add_action('bloom_generate_metrics_report', [$this, 'generate_report']);
        add_action('admin_post_download_metrics_report', [$this, 'download_report']);
    }

    public function generate_report() {
        $report = [
            'timestamp' => current_time('mysql'),
            'metrics' => [
                'system' => $this->collect_system_metrics(),
                'processing' => $this->collect_processing_metrics(),
                'network' => $this->collect_network_metrics()
            ],
            'analysis' => $this->analyze_metrics(),
            'alerts' => $this->get_active_alerts()
        ];

        $this->store_report($report);
        return $report;
    }

    private function collect_system_metrics() {
        return [
            'resources' => $this->metrics_collector->collect_resource_metrics(),
            'performance' => [
                'processing_time' => $this->get_average_processing_time(),
                'queue_size' => $this->get_queue_size(),
                'error_rate' => $this->calculate_error_rate()
            ],
            'storage' => [
                'pattern_count' => $this->count_total_patterns(),
                'tensor_chunks' => $this->count_tensor_chunks(),
                'disk_usage' => $this->get_disk_usage()
            ]
        ];
    }

    private function collect_processing_metrics() {
        global $wpdb;
        
        return [
            'patterns_per_hour' => $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}bloom_patterns 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            "),
            'average_confidence' => $wpdb->get_var("
                SELECT AVG(confidence) FROM {$wpdb->prefix}bloom_patterns
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            "),
            'processing_distribution' => $this->get_processing_distribution()
        ];
    }

    private function analyze_metrics() {
        $historic_data = $this->get_historic_metrics();
        
        return [
            'trends' => $this->analyze_metric_trends($historic_data),
            'anomalies' => $this->detect_anomalies($historic_data),
            'predictions' => $this->generate_predictions($historic_data)
        ];
    }

    private function store_report($report) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'bloom_metrics_reports',
            [
                'report_data' => json_encode($report),
                'created_at' => current_time('mysql')
            ]
        );

        // Cache for quick access
        $this->cache->set_cached('latest_metrics_report', $report, 3600);
    }

    public function download_report() {
        check_admin_referer('download_metrics_report');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'bloom-pattern-system'));
        }

        $report = $this->generate_report();
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="bloom_metrics_' . date('Y-m-d_H-i-s') . '.json"');
        echo json_encode($report, JSON_PRETTY_PRINT);
        exit;
    }
}