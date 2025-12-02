<?php
/**
 * System monitoring and health checks
 * 
 * @package APS
 * @subpackage Monitoring
 */

namespace APS\Monitoring;

use APS\DB\MetricsDB;
use APS\DB\MonitoringDB;
use APS\Integration\BloomIntegration;
use APS\Monitoring\AlertManager;

class SystemMonitor {
    private $metrics;
    private $alert_manager;
    private $monitoring_db;
    private $bloom_integration;
    private $health_status = [
        'system' => 'healthy',
        'network' => 'healthy',
        'processing' => 'healthy'
    ];

    private $thresholds = [
        'cpu_usage' => 85,         // Percentage
        'memory_usage' => 85,      // Percentage
        'disk_usage' => 90,        // Percentage
        'pattern_queue' => 1000,   // Number of items
        'sync_delay' => 300,       // Seconds
        'error_rate' => 5,         // Percentage
        'pattern_confidence' => 0.75
    ];

    public function __construct() {
        $this->metrics = new MetricsDB();
        $this->alert_manager = new AlertManager();
        $this->bloom_integration = new BloomIntegration();
        $this->monitoring_db = new MonitoringDB();
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('init', [$this, 'schedule_health_checks']);
        add_action('aps_system_health_check', [$this, 'perform_health_check']);
        add_action('aps_process_metrics', [$this, 'collect_and_store_metrics']);
        add_action('bloom_pattern_processed', [$this, 'monitor_pattern_processing']);
        add_action('cron_schedules', [$this, 'add_cron_schedules']);
    }

    public function add_cron_schedules($schedules) {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display'  => __('Every Minute')
        ];
        
        $schedules['every_five_minutes'] = [
            'interval' => 300,
            'display'  => __('Every Five Minutes')
        ];
    
        return $schedules;  
    }

    public function schedule_health_checks() {
        if (!wp_next_scheduled('aps_system_health_check')) {
            wp_schedule_event(time(), 'every_minute', 'aps_system_health_check');
        }
        if (!wp_next_scheduled('aps_process_metrics')) {
            wp_schedule_event(time(), 'every_five_minutes', 'aps_process_metrics');
        }
    }

    public function perform_health_check() {
        try {
            $system_metrics = $this->collect_system_metrics();
            $this->analyze_system_health($system_metrics);
            
            // Log health status
            $this->monitoring_db->log_health_status([
                'metrics' => $system_metrics,
                'status' => $this->health_status,
                'timestamp' => current_time('mysql')
            ]);
            
        } catch (\Exception $e) {
            $this->monitoring_db->log_emergency_event(
                'health_check_failed',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]
            );
            $this->alert_manager->trigger_alert('system_check_failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function collect_and_store_metrics() {
        $metrics = [
            'system' => $this->collect_system_metrics(),
            'processing' => $this->collect_processing_metrics(),
            'network' => $this->collect_network_metrics(),
            'timestamp' => time()
        ];

        foreach ($metrics as $type => $data) {
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $this->metrics->record_metric($type, $key, $value);
                }
            }
        }

        return $metrics;
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

    private function get_network_connections() {
        if (function_exists('exec')) {
            $output = [];
            exec('netstat -an | grep ESTABLISHED | wc -l', $output);
            return (int)($output[0] ?? 0);
        }
        return 0;
    }

    private function get_network_bandwidth() {
        $stats = [];
        if (is_readable('/proc/net/dev')) {
            $stats = file_get_contents('/proc/net/dev');
        }
        return $stats;
    }

    private function get_network_latency() {
        $endpoints = ['localhost']; 
        $latencies = [];
        
        foreach ($endpoints as $endpoint) {
            try {
                $start = microtime(true);
                $response = wp_remote_get($endpoint, ['timeout' => 2]); // 2 second timeout
                if (!is_wp_error($response)) {
                    $latencies[] = microtime(true) - $start;
                }
            } catch (\Exception $e) {
                // Log the error
                error_log('Error in get_network_latency: ' . $e->getMessage());
            }
        }
        
        return !empty($latencies) ? array_sum($latencies) / count($latencies) : 0;
    }

    private function get_process_count() {
        if (function_exists('exec')) {
            $output = [];
            exec('ps aux | grep php | wc -l', $output);
            return (int)($output[0] ?? 0);
        }
        return 0;
    }

    private function get_disk_io_stats() {
        if (is_readable('/proc/diskstats')) {
            return file_get_contents('/proc/diskstats');
        }
        return null;
    }

    private function collect_processing_metrics() {
        global $wpdb;

        return [
            'queue_size' => $this->get_queue_size(),
            'processing_rate' => $this->calculate_processing_rate(),
            'error_rate' => $this->calculate_error_rate(),
            'pattern_count' => $this->get_pattern_count(),
            'sync_status' => $this->get_sync_status()
        ];
    }

    private function collect_network_metrics() {
        return [
            'active_sites' => $this->get_active_sites_count(),
            'sync_delay' => $this->get_sync_delay(),
            'distribution' => $this->get_pattern_distribution(),
            'site_health' => $this->get_site_health_status()
        ];
    }

    private function analyze_system_health($metrics) {
        // Check CPU usage
        if ($metrics['cpu']['usage'] > $this->thresholds['cpu_usage']) {
            $this->health_status['system'] = 'warning';
            $this->alert_manager->trigger_alert('high_cpu_usage', [
                'current' => $metrics['cpu']['usage'],
                'threshold' => $this->thresholds['cpu_usage']
            ]);
        }

        // Check memory usage
        if ($metrics['memory']['usage'] > $this->thresholds['memory_usage']) {
            $this->health_status['system'] = 'warning';
            $this->alert_manager->trigger_alert('high_memory_usage', [
                'current' => $metrics['memory']['usage'],
                'threshold' => $this->thresholds['memory_usage']
            ]);
        }

        // Check disk usage
        if ($metrics['disk']['usage'] > $this->thresholds['disk_usage']) {
            $this->health_status['system'] = 'warning';
            $this->alert_manager->trigger_alert('high_disk_usage', [
                'current' => $metrics['disk']['usage'],
                'threshold' => $this->thresholds['disk_usage']
            ]);
        }

        // Check processing metrics
        $processing_metrics = $this->collect_processing_metrics();
        if ($processing_metrics['queue_size'] > $this->thresholds['pattern_queue']) {
            $this->health_status['processing'] = 'warning';
            $this->alert_manager->trigger_alert('large_queue_size', [
                'current' => $processing_metrics['queue_size'],
                'threshold' => $this->thresholds['pattern_queue']
            ]);
        }

        if ($processing_metrics['error_rate'] > $this->thresholds['error_rate']) {
            $this->health_status['processing'] = 'critical';
            $this->alert_manager->trigger_alert('high_error_rate', [
                'current' => $processing_metrics['error_rate'],
                'threshold' => $this->thresholds['error_rate']
            ]);
        }
    }

    private function check_bloom_integration() {
        if (!$this->bloom_integration->is_connected()) {
            $this->health_status['processing'] = 'critical';
            $this->alert_manager->trigger_alert('bloom_connection_failed', [
                'error' => 'Connection to BLOOM system failed',
                'timestamp' => current_time('mysql'),
                'last_successful_connection' => $this->get_last_successful_connection()
            ]);
        }
    }

    private function get_last_successful_connection() {
        return get_option('aps_last_bloom_connection', '');
    }

    private function store_health_status() {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'aps_health_log',
            [
                'status_data' => json_encode($this->health_status),
                'created_at' => current_time('mysql')
            ]
        );

        wp_cache_set('aps_health_status', $this->health_status, '', 60);
    }

    public function monitor_pattern_processing($pattern_data) {
        if ($pattern_data['confidence'] < $this->thresholds['pattern_confidence']) {
            $this->alert_manager->trigger_alert('low_confidence_pattern', [
                'pattern_id' => $pattern_data['id'],
                'confidence' => $pattern_data['confidence'],
                'threshold' => $this->thresholds['pattern_confidence']
            ]);
        }

        $this->metrics->record_metric('pattern_processing', 1, [
            'confidence' => $pattern_data['confidence'],
            'type' => $pattern_data['type']
        ]);
    }

    private function get_cpu_usage() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0] * 100;
        }
        return 0;
    }

    private function get_memory_usage() {
        $memory_limit = $this->parse_memory_limit(ini_get('memory_limit'));
        $memory_usage = memory_get_usage(true);
        return ($memory_usage / $memory_limit) * 100;
    }

    private function get_disk_usage() {
        $total_space = disk_total_space(ABSPATH);
        $free_space = disk_free_space(ABSPATH);
        return $total_space > 0 ? (($total_space - $free_space) / $total_space) * 100 : 0;
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

    public function get_system_status() {
        return [
            'health_status' => $this->health_status,
            'metrics' => $this->collect_and_store_metrics(),
            'thresholds' => $this->thresholds,
            'last_check' => current_time('mysql')
        ];
    }
}