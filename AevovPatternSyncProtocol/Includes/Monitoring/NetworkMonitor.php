<?php
/**
 * Network monitoring and site coordination
 * 
 * @package APS
 * @subpackage Monitoring
 */

namespace APS\Monitoring;

use APS\Core\Logger;
use APS\DB\NetworkCache;
use APS\DB\MetricsDB;
use APS\Monitoring\AlertManager;

class NetworkMonitor {
    private $logger;
    private $alert_manager;
    private $metrics_collector;
    private $network_cache;
    private $health_check_interval = 300; // 5 minutes
    private $site_timeout = 600; // 10 minutes

    public function __construct() {
        $this->logger = new Logger();
        $this->alert_manager = new AlertManager();
        $this->metrics_collector = new MetricsCollector();
        $this->network_cache = new NetworkCache();
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('init', [$this, 'schedule_health_checks']);
        add_action('aps_network_health_check', [$this, 'perform_network_health_check']);
        add_action('aps_sync_network_state', [$this, 'sync_network_state']);
        add_action('aps_site_status_update', [$this, 'handle_site_status_update']);
        add_action('aps_process_site_metrics', [$this, 'process_site_metrics']);
        add_action('aps_distribute_patterns', [$this, 'distribute_patterns']);
        add_action('aps_pattern_distributed', [$this, 'handle_pattern_distribution']);
        add_action('wp_initialize_site', [$this, 'handle_new_site'], 10, 2);
        add_action('wp_uninitialize_site', [$this, 'handle_site_deletion']);
    }

    public function schedule_health_checks() {
        if (!wp_next_scheduled('aps_network_health_check')) {
            wp_schedule_event(time(), 'five_minutes', 'aps_network_health_check');
        }
    }

    public function perform_network_health_check() {
        if (!is_multisite()) {
            return;
        }

        $network_status = $this->check_network_status();
        $this->update_network_health($network_status);
        
        if ($network_status['unhealthy_sites'] > 0) {
            $this->alert_manager->trigger_alert('network_health_issues', [
                'unhealthy_sites' => $network_status['unhealthy_sites'],
                'details' => $network_status['site_details']
            ]);
        }

        $this->handle_network_recovery($network_status);
    }

    private function check_network_status() {
        $sites = get_sites(['fields' => 'ids']);
        $status = [
            'total_sites' => count($sites),
            'active_sites' => 0,
            'unhealthy_sites' => 0,
            'sync_status' => [],
            'site_details' => []
        ];

        foreach ($sites as $site_id) {
            $site_status = $this->check_site_health($site_id);
            $status['site_details'][$site_id] = $site_status;
            
            if ($site_status['active']) {
                $status['active_sites']++;
            } else {
                $status['unhealthy_sites']++;
            }

            $status['sync_status'][$site_id] = $this->get_site_sync_status($site_id);
        }

        $status['network_load'] = $this->calculate_network_load();
        $status['pattern_distribution'] = $this->get_pattern_distribution();
        
        return $status;
    }

    private function check_site_health($site_id) {
        switch_to_blog($site_id);
        
        $health_status = [
            'active' => false,
            'last_ping' => get_option('aps_last_ping'),
            'processing_status' => get_option('aps_processing_status'),
            'error_count' => $this->get_site_error_count($site_id),
            'resource_usage' => $this->get_site_resource_usage($site_id),
            'pattern_stats' => $this->get_site_pattern_stats($site_id)
        ];

        $health_status['active'] = $this->is_site_active($health_status);
        
        restore_current_blog();
        return $health_status;
    }

    private function is_site_active($status) {
        if (!$status['last_ping']) {
            return false;
        }

        $last_ping_time = strtotime($status['last_ping']);
        if (time() - $last_ping_time > $this->site_timeout) {
            return false;
        }

        if ($status['error_count'] > 100) { // Threshold for errors
            return false;
        }

        return true;
    }

    public function sync_network_state() {
        $network_state = [
            'timestamp' => time(),
            'sites' => $this->get_network_sites_state(),
            'pattern_distribution' => $this->get_pattern_distribution(),
            'load_distribution' => $this->get_load_distribution(),
            'sync_metrics' => $this->get_sync_metrics()
        ];

        $this->network_cache->set('network_state', $network_state, 300);
        $this->distribute_network_state($network_state);
    }

    private function distribute_network_state($state) {
        $sites = get_sites(['fields' => 'ids']);
        
        foreach ($sites as $site_id) {
            switch_to_blog($site_id);
            update_option('aps_network_state', $state);
            restore_current_blog();
        }
    }

    public function get_network_health() {
        $cached_health = $this->network_cache->get('network_health');
        if ($cached_health) {
            return $cached_health;
        }

        $health = [
            'status' => $this->calculate_overall_health(),
            'sites' => $this->get_site_health_matrix(),
            'distribution' => $this->get_distribution_health(),
            'sync' => $this->get_sync_health(),
            'timestamp' => time()
        ];

        $this->network_cache->set('network_health', $health, 60);
        return $health;
    }

    private function calculate_overall_health() {
        $site_metrics = $this->get_aggregated_site_metrics();
        $distribution_metrics = $this->get_distribution_metrics();
        $sync_metrics = $this->get_sync_metrics();

        $health_scores = [
            'sites' => $this->calculate_site_health_score($site_metrics),
            'distribution' => $this->calculate_distribution_score($distribution_metrics),
            'sync' => $this->calculate_sync_score($sync_metrics)
        ];

        $overall_score = array_sum($health_scores) / count($health_scores);
        
        return [
            'score' => $overall_score,
            'status' => $this->get_health_status($overall_score),
            'components' => $health_scores
        ];
    }

    public function get_active_sites() {
        $sites = get_sites([
            'fields' => 'ids',
            'active' => true
        ]);

        $active_sites = [];
        foreach ($sites as $site_id) {
            $site_status = $this->check_site_health($site_id);
            if ($site_status['active']) {
                $active_sites[] = [
                    'id' => $site_id,
                    'url' => get_site_url($site_id),
                    'status' => $site_status
                ];
            }
        }

        return $active_sites;
    }

    public function get_site_statuses() {
        $sites = get_sites(['fields' => 'all']);
        $statuses = [];

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            $statuses[$site->blog_id] = [
                'url' => $site->domain . $site->path,
                'last_active' => get_option('aps_last_activity'),
                'patterns_processed' => $this->get_patterns_processed($site->blog_id),
                'current_load' => $this->get_site_load($site->blog_id),
                'health_status' => $this->get_site_health_status($site->blog_id)
            ];
            
            restore_current_blog();
        }

        return $statuses;
    }

    public function get_network_topology() {
        return [
            'sites' => $this->get_active_sites(),
            'connections' => $this->get_site_connections(),
            'distribution' => $this->get_pattern_distribution(),
            'load_balancing' => $this->get_load_balancing_status()
        ];
    }

    public function get_sync_metrics() {
        global $wpdb;
        
        return [
            'last_sync' => get_option('aps_last_network_sync'),
            'sync_frequency' => get_option('aps_sync_frequency'),
            'sync_success_rate' => $this->calculate_sync_success_rate(),
            'pattern_distribution' => $this->get_pattern_distribution_metrics(),
            'sync_delays' => $this->get_sync_delays()
        ];
    }

    public function get_distribution_metrics() {
        global $wpdb;
        
        $metrics = [
            'pattern_counts' => $wpdb->get_results(
                "SELECT blog_id, COUNT(*) as count 
                 FROM {$wpdb->prefix}aps_patterns 
                 GROUP BY blog_id",
                ARRAY_A
            ),
            'distribution_balance' => $this->calculate_distribution_balance(),
            'site_workloads' => $this->get_site_workloads()
        ];

        return $metrics;
    }

    private function get_site_workloads() {
        $sites = get_sites(['fields' => 'ids']);
        $workloads = [];

        foreach ($sites as $site_id) {
            switch_to_blog($site_id);
            
            $workloads[$site_id] = [
                'queue_size' => $this->get_queue_size($site_id),
                'processing_rate' => $this->get_processing_rate($site_id),
                'resource_usage' => $this->get_resource_usage($site_id),
                'pattern_count' => $this->get_pattern_count($site_id)
            ];
            
            restore_current_blog();
        }

        return $workloads;
    }

    public function handle_site_status_update($status_data) {
        $site_id = get_current_blog_id();
        $this->network_cache->set("site_status_{$site_id}", $status_data, 300);
        
        if ($status_data['status'] === 'error') {
            $this->alert_manager->trigger_alert('site_status_error', [
                'site_id' => $site_id,
                'error' => $status_data['error']
            ]);
        }

        do_action('aps_site_status_updated', $site_id, $status_data);
    }

    public function handle_pattern_distribution($pattern_data) {
        $distribution = [
            'pattern_id' => $pattern_data['id'],
            'source_site' => get_current_blog_id(),
            'target_sites' => $this->get_target_sites_for_pattern($pattern_data),
            'timestamp' => time()
        ];

        $this->distribute_pattern($distribution);
    }

    private function distribute_pattern($distribution) {
        foreach ($distribution['target_sites'] as $site_id) {
            switch_to_blog($site_id);
            
            try {
                $this->store_distributed_pattern($distribution['pattern_id']);
                $this->update_distribution_metrics($distribution);
            } catch (\Exception $e) {
                $this->alert_manager->trigger_alert('pattern_distribution_failed', [
                    'pattern_id' => $distribution['pattern_id'],
                    'site_id' => $site_id,
                    'error' => $e->getMessage()
                ]);
            }
            
            restore_current_blog();
        }
    }

    private function handle_network_recovery($status) {
        if ($status['unhealthy_sites'] > 0) {
            foreach ($status['site_details'] as $site_id => $site_status) {
                if (!$site_status['active']) {
                    $this->attempt_site_recovery($site_id, $site_status);
                }
            }
        }
    }

    private function attempt_site_recovery($site_id, $status) {
        switch_to_blog($site_id);
        
        try {
            // Reset processing status
            update_option('aps_processing_status', 'active');
            
            // Clear error logs
            $this->clear_site_errors($site_id);
            
            // Redistribute patterns if needed
            if ($status['pattern_stats']['count'] === 0) {
                $this->redistribute_patterns_to_site($site_id);
            }
            
            $this->alert_manager->trigger_alert('site_recovery_attempted', [
                'site_id' => $site_id,
                'success' => true
            ]);
        } catch (\Exception $e) {
            $this->alert_manager->trigger_alert('site_recovery_failed', [
                'site_id' => $site_id,
                'error' => $e->getMessage()
            ]);
        }
        
        restore_current_blog();
    }

    public function handle_new_site($site, $args) {
        $site_id = $site->blog_id;
        
        switch_to_blog($site_id);
        
        // Initialize site options
        update_option('aps_processing_status', 'active');
        update_option('aps_last_ping', current_time('mysql'));
        
        // Set up initial pattern distribution
        $this->initialize_site_patterns($site_id);
        
        // Update network topology
        $this->update_network_topology();
        
        restore_current_blog();
    }

    public function handle_site_deletion($site_id) {
        // Redistribute patterns from the deleted site
        $this->redistribute_site_patterns($site_id);
        
        // Update network topology
        $this->update_network_topology();
        
        // Clean up site-specific cache
        $this->network_cache->delete("site_status_{$site_id}");
    }

    private function update_network_topology() {
        $topology = $this->get_network_topology();
        $this->network_cache->set('network_topology', $topology, 300);
        
        do_action('aps_network_topology_updated', $topology);
    }
}