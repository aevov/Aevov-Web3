<?php
/**
 * Implements the main plugin dashboard
 */
namespace BLOOM\Admin;

use BLOOM\Monitoring\MetricsCollector;
use BLOOM\Monitoring\SystemMonitor;
use BLOOM\Network\NetworkManager;
use BLOOM\Models\PatternModel;

class Dashboard {
    private $metrics_collector;
    private $system_monitor;
    private $network_manager;
    private $pattern_model;
    
    public function __construct() {
        $this->metrics_collector = new MetricsCollector();
        $this->system_monitor = new SystemMonitor();
        $this->network_manager = new NetworkManager();
        $this->pattern_model = new PatternModel();
        add_action('wp_ajax_bloom_get_dashboard_metrics', [$this, 'get_dashboard_metrics']); // Corrected AJAX action name
    }

    public function render() {
        // This method is typically used to include the view file
        // The actual data fetching is done via AJAX by dashboard.js
        include BLOOM_PATH . 'admin/views/dashboard.php';
    }

    public function get_dashboard_metrics() { // Renamed from get_dashboard_data
        check_ajax_referer('bloom-admin', 'nonce'); // Use the correct nonce name

        $summary_stats = $this->get_summary_stats();
        $pattern_distribution = $this->get_pattern_distribution();
        $processing_performance = $this->get_processing_performance();
        $network_health = $this->get_network_health();
        $recent_activity = $this->get_recent_activity();

        wp_send_json_success([
            'summary' => $summary_stats,
            'patterns' => $pattern_distribution,
            'performance' => $processing_performance,
            'network' => $network_health,
            'recent_activity' => $recent_activity
        ]);
    }

    private function get_summary_stats() {
        global $wpdb;
        $total_patterns = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bloom_patterns WHERE status = 'active'");
        $active_sites = is_multisite() ? count(get_sites(['fields' => 'ids'])) : 1;
        $avg_processing_time = $this->get_avg_processing_time();
        $success_rate = $this->calculate_success_rate();

        return [
            'total_patterns' => (int) $total_patterns,
            'active_sites' => (int) $active_sites,
            'avg_processing_time' => round($avg_processing_time, 2),
            'success_rate' => round($success_rate, 2)
        ];
    }

    private function get_pattern_distribution() {
        $pattern_stats = $this->pattern_model->get_pattern_statistics();
        $distribution = [
            'sequential_count' => 0,
            'structural_count' => 0,
            'statistical_count' => 0,
            'clustered_count' => 0 // New count for clustered patterns
        ];

        foreach ($pattern_stats as $stat) {
            switch ($stat['pattern_type']) {
                case 'sequential':
                    $distribution['sequential_count'] = (int) $stat['count'];
                    break;
                case 'structural':
                    $distribution['structural_count'] = (int) $stat['count'];
                    break;
                case 'statistical':
                    $distribution['statistical_count'] = (int) $stat['count'];
                    break;
                case 'cluster': // Assuming 'cluster' is a pattern type for clustered patterns
                    $distribution['clustered_count'] = (int) $stat['count'];
                    break;
            }
        }
        return $distribution;
    }

    private function get_processing_performance() {
        // This would typically fetch historical processing times from a metrics table
        // For now, return dummy data or fetch from a simple log
        $timestamps = [];
        $processing_times = [];

        // Example: Fetch last 10 processing times from a log or metrics table
        // For demonstration, generating dummy data
        for ($i = 0; $i < 10; $i++) {
            $timestamps[] = date('H:i:s', strtotime("-{$i} minutes"));
            $processing_times[] = rand(50, 500);
        }
        $timestamps = array_reverse($timestamps);
        $processing_times = array_reverse($processing_times);

        return [
            'timestamps' => $timestamps,
            'processing_times' => $processing_times
        ];
    }

    private function get_network_health() {
        $sites_data = [];
        if (is_multisite()) {
            $sites = get_sites(['fields' => 'ids']);
            foreach ($sites as $site_id) {
                switch_to_blog($site_id);
                $sites_data[] = [
                    'name' => get_bloginfo('name'),
                    'health_score' => rand(70, 100) // Dummy health score
                ];
                restore_current_blog();
            }
        } else {
            $sites_data[] = [
                'name' => get_bloginfo('name'),
                'health_score' => rand(70, 100)
            ];
        }
        return ['sites' => $sites_data];
    }

    private function get_recent_activity() {
        // This would fetch recent activity from a log or activity table
        // For demonstration, returning dummy data
        return [
            ['timestamp' => current_time('mysql'), 'message' => 'New pattern processed: ABCDEF'],
            ['timestamp' => current_time('mysql'), 'message' => 'System health check completed.'],
            ['timestamp' => current_time('mysql'), 'message' => 'Pattern sync with APS initiated.']
        ];
    }

    private function get_overall_status() {
        // Implement logic to determine overall system status
        // e.g., check critical metrics, recent errors, queue backlog
        return 'healthy'; // Placeholder
    }

    private function get_avg_processing_time() {
        // Fetch from metrics collector or calculate from logs
        return rand(100, 300); // Dummy value
    }

    private function calculate_success_rate() {
        // Fetch from metrics collector or calculate from logs
        return rand(90, 100); // Dummy value
    }

    private function get_queue_status() {
        // Fetch from queue manager
        return 'idle'; // Placeholder
    }
}