<?php
/**
 * Handles system monitoring and health checks
 */
// includes/monitoring/class-system-monitor.php
namespace BLOOM\Monitoring;

use BLOOM\Network\NetworkManager; // Assuming NetworkManager exists

class SystemMonitor {
    private $metrics_collector;
    private $network_manager;
    private $alert_thresholds;
    private $logger; // Assuming a Logger class exists

    public function __construct() {
        $this->metrics_collector = new MetricsCollector();
        $this->network_manager = new NetworkManager(); // Instantiate NetworkManager
        $this->logger = \BLOOM\Core\Logger::get_instance(); // Assuming a global Logger instance
        $this->init_thresholds();
        $this->init_monitoring();
        add_action('wp_ajax_bloom_get_monitor_metrics', [$this, 'get_monitor_metrics']); // AJAX handler
    }

    public function init_monitoring() {
        add_action('bloom_system_check', [$this, 'perform_system_check']);
        add_action('bloom_health_alert', [$this, 'process_health_alert']);
        // Schedule event only if not already scheduled
        if (!wp_next_scheduled('bloom_system_check')) {
            wp_schedule_event(time(), 'minute', 'bloom_system_check');
        }
    }

    public function perform_system_check() {
        $system_state = $this->metrics_collector->collect_system_metrics();
        $health_status = $this->check_system_health($system_state);
        // Assuming process_health_alert handles logging/notifications
        // $this->process_health_alert($health_status);
        return $health_status;
    }

    public function get_system_health() {
        $system_state = $this->metrics_collector->collect_system_metrics();
        return $this->check_system_health($system_state);
    }

    private function check_system_health($state) {
        $health_status = [
            'active' => true,
            'error' => null,
            'version' => BLOOM_VERSION,
            'timestamp' => time(),
            'components' => [
                'core' => 'active',
                'network' => 'active',
                'processing' => 'active',
                'storage' => 'active'
            ],
            'metrics' => $state ?? []
        ];

        // Check for any critical issues based on thresholds
        if (isset($state['cpu_usage']) && $state['cpu_usage'] > $this->alert_thresholds['cpu']) {
            $health_status['active'] = false;
            $health_status['error'] = 'High CPU usage detected';
            $health_status['components']['cpu'] = 'critical';
        }
        if (isset($state['memory_usage']) && $state['memory_usage'] > $this->alert_thresholds['memory']) {
            $health_status['active'] = false;
            $health_status['error'] = 'High memory usage detected';
            $health_status['components']['memory'] = 'critical';
        }
        // Add more checks for other metrics and components

        return $health_status;
    }

    private function init_thresholds() {
        $this->alert_thresholds = [
            'cpu' => 0.85,
            'memory' => 0.85,
            'disk' => 0.90,
            'queue_size' => 1000,
            'error_rate' => 0.05
        ];
    }

    // New AJAX handler for monitor metrics
    public function get_monitor_metrics() {
        check_ajax_referer('bloom-admin', 'nonce');

        $system_metrics = $this->metrics_collector->collect_system_metrics();
        $network_metrics = $this->network_manager->get_network_status(); // Assuming this method exists

        $status_indicators = [
            'system' => $this->get_component_status_indicator($system_metrics, 'system'),
            'network' => $this->get_component_status_indicator($network_metrics, 'network'),
            'processing' => $this->get_component_status_indicator($system_metrics, 'processing') // Assuming processing status is part of system metrics
        ];

        wp_send_json_success([
            'cpu' => [
                'usage' => $system_metrics['cpu_usage'] ?? 0
            ],
            'memory' => [
                'usage' => $system_metrics['memory_usage'] ?? 0
            ],
            'network' => [
                'sites' => $network_metrics['sites'] ?? [], // Assuming sites and latency are returned
            ],
            'status' => $status_indicators,
            'events' => $this->get_recent_events()
        ]);
    }

    private function get_component_status_indicator($metrics, $component_type) {
        // Implement logic to determine 'healthy', 'warning', 'critical' based on metrics and thresholds
        // For now, a simple placeholder
        if ($component_type === 'system' && isset($metrics['cpu_usage']) && $metrics['cpu_usage'] > $this->alert_thresholds['cpu']) {
            return 'critical';
        }
        if ($component_type === 'system' && isset($metrics['memory_usage']) && $metrics['memory_usage'] > $this->alert_thresholds['memory']) {
            return 'critical';
        }
        // Add more complex logic based on network_metrics, processing status etc.
        return 'healthy';
    }

    private function get_recent_events() {
        // This would fetch recent events from a log or activity table
        // For demonstration, returning dummy data
        return [
            ['timestamp' => current_time('mysql'), 'message' => 'CPU usage spiked to 90%.'],
            ['timestamp' => current_time('mysql'), 'message' => 'Network latency increased to 200ms.'],
            ['timestamp' => current_time('mysql'), 'message' => 'New pattern processed successfully.']
        ];
    }
}
