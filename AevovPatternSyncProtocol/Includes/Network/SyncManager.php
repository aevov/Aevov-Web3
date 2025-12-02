<?php
/**
 * Network synchronization management
 * Handles site coordination and data synchronization
 * 
 * @package APS
 * @subpackage Network
 */

namespace APS\Network;

use APS\DB\MetricsDB;
use APS\Monitoring\AlertManager;
use APS\DB\NetworkCache;

class SyncManager {
    private $metrics;
    private $alert_manager;
    private $network_cache;
    private $sync_table;
    private $sync_interval = 300; // 5 minutes
    private $sync_batch_size = 100;
    private $retry_limit = 3;
    private $lock_timeout = 600; // 10 minutes
    
    public function __construct() {
        global $wpdb;
        $this->sync_table = $wpdb->prefix . 'aps_sync_log';
        
        $this->metrics = new MetricsDB();
        $this->alert_manager = new AlertManager();
        $this->network_cache = new NetworkCache();
        
        $this->init_hooks();
    }

    private function init_hooks() {
        if (function_exists('add_action')) {
            add_action('init', [$this, 'schedule_sync']);
            add_action('aps_network_sync', [$this, 'perform_network_sync']);
            add_action('aps_verify_sync', [$this, 'verify_sync_status']);
            add_action('aps_pattern_updated', [$this, 'handle_pattern_update']);
            add_action('aps_sync_patterns', [$this, 'sync_patterns']);
            add_action('aps_sync_metrics', [$this, 'sync_metrics']);
            add_action('wp_initialize_site', [$this, 'initialize_site_sync']);
            add_action('wp_uninitialize_site', [$this, 'cleanup_site_sync']);
        }
    }

    public function schedule_sync() {
        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event')) {
            if (!wp_next_scheduled('aps_network_sync')) {
                wp_schedule_event(time(), 'five_minutes', 'aps_network_sync');
            }
        }
    }

    public function perform_network_sync() {
        $sync_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('sync_', true);
        $start_time = microtime(true);

        try {
            // Acquire network lock
            if (!$this->acquire_sync_lock($sync_id)) {
                return false;
            }

            // Get network state
            $network_state = $this->collect_network_state();

            // Perform sync operations
            $sync_result = $this->sync_network($network_state);

            // Verify sync
            $verification = $this->verify_sync_status($sync_id);

            // Record metrics
            $this->record_sync_metrics($sync_result, microtime(true) - $start_time);

            return $sync_result;

        } catch (\Exception $e) {
            $this->handle_sync_error($e, $sync_id);
            return false;
        } finally {
            // Release lock
            $this->release_sync_lock($sync_id);
        }
    }

    private function sync_network($network_state) {
        $sites = function_exists('get_sites') ? get_sites(['fields' => 'ids']) : [1];
        $sync_operations = [];

        foreach ($sites as $site_id) {
            if (function_exists('switch_to_blog')) {
                switch_to_blog($site_id);
            }
            
            try {
                // Sync patterns
                $pattern_sync = $this->sync_patterns($site_id);
                
                // Sync metrics
                $metrics_sync = $this->sync_metrics($site_id);
                
                // Update site state
                $state_sync = $this->sync_site_state($site_id, $network_state);
                
                $sync_operations[$site_id] = [
                    'patterns' => $pattern_sync,
                    'metrics' => $metrics_sync,
                    'state' => $state_sync,
                    'timestamp' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
                ];
                
            } catch (\Exception $e) {
                $this->handle_site_sync_error($site_id, $e);
                $sync_operations[$site_id] = [
                    'error' => $e->getMessage(),
                    'timestamp' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
                ];
            }
            
            if (function_exists('restore_current_blog')) {
                restore_current_blog();
            }
        }

        return $sync_operations;
    }

    public function sync_patterns($site_id = null) {
        $site_id = $site_id ?? (function_exists('get_current_blog_id') ? get_current_blog_id() : 1);
        $patterns_to_sync = $this->get_patterns_to_sync($site_id);
        $sync_results = [];

        foreach (array_chunk($patterns_to_sync, $this->sync_batch_size) as $batch) {
            try {
                $batch_result = $this->sync_pattern_batch($batch, $site_id);
                $sync_results = array_merge($sync_results, $batch_result);
            } catch (\Exception $e) {
                $this->alert_manager->trigger_alert('pattern_sync_error', [
                    'site_id' => $site_id,
                    'error' => $e->getMessage(),
                    'batch_size' => count($batch)
                ]);
            }
        }

        return $sync_results;
    }

    private function sync_pattern_batch($patterns, $site_id) {
        $results = [];
        
        foreach ($patterns as $pattern) {
            try {
                $sync_result = $this->sync_single_pattern($pattern, $site_id);
                $results[$pattern['pattern_hash']] = [
                    'status' => 'success',
                    'timestamp' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
                ];
            } catch (\Exception $e) {
                $results[$pattern['pattern_hash']] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'timestamp' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
                ];
            }
        }

        return $results;
    }

    public function sync_metrics($site_id = null) {
        $site_id = $site_id ?? (function_exists('get_current_blog_id') ? get_current_blog_id() : 1);
        $metrics_to_sync = $this->get_metrics_to_sync($site_id);

        try {
            $this->store_metrics_batch($metrics_to_sync, $site_id);
            return [
                'status' => 'success',
                'metrics_synced' => count($metrics_to_sync),
                'timestamp' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            $this->alert_manager->trigger_alert('metrics_sync_error', [
                'site_id' => $site_id,
                'error' => $e->getMessage()
            ]);
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
            ];
        }
    }

    public function verify_sync_status($sync_id) {
        $sync_data = $this->get_sync_data($sync_id);
        if (!$sync_data) {
            throw new \Exception('Sync data not found');
        }

        $verification_results = [];
        $sites = function_exists('get_sites') ? get_sites(['fields' => 'ids']) : [1];

        foreach ($sites as $site_id) {
            if (function_exists('switch_to_blog')) {
                switch_to_blog($site_id);
            }
            
            try {
                $site_verification = $this->verify_site_sync($sync_data, $site_id);
                $verification_results[$site_id] = [
                    'verified' => true,
                    'details' => $site_verification
                ];
            } catch (\Exception $e) {
                $verification_results[$site_id] = [
                    'verified' => false,
                    'error' => $e->getMessage()
                ];
            }
            
            if (function_exists('restore_current_blog')) {
                restore_current_blog();
            }
        }

        $this->update_sync_verification($sync_id, $verification_results);
        return $verification_results;
    }

    public function handle_pattern_update($pattern_data) {
        try {
            // Store update in sync queue
            $this->queue_pattern_sync($pattern_data);

            // Notify other sites if needed
            if ($this->should_notify_sites($pattern_data)) {
                $this->notify_sites_of_update($pattern_data);
            }

            $this->record_pattern_update_metrics($pattern_data);

        } catch (\Exception $e) {
            $this->alert_manager->trigger_alert('pattern_update_sync_error', [
                'pattern_hash' => $pattern_data['pattern_hash'],
                'error' => $e->getMessage()
            ]);
        }
    }

    private function collect_network_state() {
        return [
            'sites' => $this->collect_site_states(),
            'patterns' => $this->collect_pattern_states(),
            'metrics' => $this->collect_metric_states(),
            'timestamp' => time()
        ];
    }

    private function acquire_sync_lock($sync_id) {
        global $wpdb;
        
        $lock_result = $wpdb->insert(
            $this->sync_table,
            [
                'sync_id' => $sync_id,
                'status' => 'locked',
                'locked_at' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
                'site_id' => function_exists('get_current_blog_id') ? get_current_blog_id() : 1
            ]
        );

        return $lock_result !== false;
    }

    private function release_sync_lock($sync_id) {
        global $wpdb;
        
        return $wpdb->update(
            $this->sync_table,
            [
                'status' => 'completed',
                'completed_at' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
            ],
            ['sync_id' => $sync_id]
        );
    }

    private function record_sync_metrics($sync_result, $duration) {
        $successful_sites = count(array_filter($sync_result, function($result) {
            return !isset($result['error']);
        }));

        $this->metrics->record_metric('network_sync', 1, [
            'successful_sites' => $successful_sites,
            'total_sites' => count($sync_result),
            'duration' => $duration
        ]);

        $this->metrics->record_metric('sync_duration', $duration);
    }

    private function handle_sync_error(\Exception $e, $sync_id) {
        $this->alert_manager->trigger_alert('network_sync_error', [
            'sync_id' => $sync_id,
            'error' => $e->getMessage()
        ]);

        $this->metrics->record_metric('sync_errors', 1, [
            'error_type' => get_class($e)
        ]);
    }

    public function get_sync_stats() {
        return [
            'last_sync' => $this->get_last_sync_time(),
            'sync_success_rate' => $this->calculate_sync_success_rate(),
            'average_sync_duration' => $this->metrics->get_metric_average('sync_duration'),
            'error_rate' => $this->calculate_error_rate(),
            'site_stats' => $this->get_site_sync_stats()
        ];
    }

    private function calculate_sync_success_rate() {
        $total_syncs = $this->metrics->get_metric_sum('network_sync');
        if (!$total_syncs) {
            return 0;
        }

        $successful_syncs = $this->metrics->get_metric_sum('network_sync', [
            'condition' => 'successful_sites = total_sites'
        ]);

        return ($successful_syncs / $total_syncs) * 100;
    }

    private function calculate_error_rate() {
        $total_syncs = $this->metrics->get_metric_sum('network_sync');
        if (!$total_syncs) {
            return 0;
        }

        $errors = $this->metrics->get_metric_sum('sync_errors');
        return ($errors / $total_syncs) * 100;
    }
}