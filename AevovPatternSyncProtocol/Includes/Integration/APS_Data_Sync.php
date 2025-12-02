<?php

namespace APS\Integration;

class APS_Data_Sync {
    private $batch_size = 100;
    private $sync_interval = 300;
    private $retry_limit = 3;
    private $cache;
    private $pattern_bridge;
    
    public function __construct(APS_Cache $cache, APS_Pattern_Bridge $pattern_bridge) {
        $this->cache = $cache;
        $this->pattern_bridge = $pattern_bridge;
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('init', [$this, 'schedule_sync']);
        add_action('aps_sync_data', [$this, 'perform_sync']);
        add_action('aps_force_sync', [$this, 'force_sync']);
        add_action('aps_network_sync', [$this, 'sync_network']);
    }

    public function schedule_sync() {
        if (!wp_next_scheduled('aps_sync_data')) {
            wp_schedule_event(time(), 'five_minutes', 'aps_sync_data');
        }
    }

    public function perform_sync() {
        $lock_key = 'aps_sync_lock';
        if ($this->cache->get($lock_key)) return;
        
        $this->cache->set($lock_key, true, 300);
        
        try {
            $this->sync_patterns();
            $this->sync_tensors();
            $this->sync_metrics();
            $this->sync_network_state();
            $this->cleanup_sync_data();
        } catch (Exception $e) {
            $this->log_sync_error($e);
        } finally {
            $this->cache->delete($lock_key);
        }
    }

    public function sync_network() {
        $sites = get_sites(['fields' => 'ids']);
        $network_data = [];
        
        foreach ($sites as $site_id) {
            switch_to_blog($site_id);
            $network_data[$site_id] = $this->collect_site_data($site_id);
            restore_current_blog();
        }

        return $this->distribute_network_data($network_data);
    }

    public function force_sync($data_types = ['all']) {
        if (in_array('all', $data_types) || in_array('patterns', $data_types)) {
            $this->sync_patterns(true);
        }
        if (in_array('all', $data_types) || in_array('tensors', $data_types)) {
            $this->sync_tensors(true);
        }
        if (in_array('all', $data_types) || in_array('metrics', $data_types)) {
            $this->sync_metrics(true);
        }
    }

    private function sync_patterns($force = false) {
        global $wpdb;
        
        $last_sync = get_option('aps_last_pattern_sync', 0);
        $current_time = time();

        if (!$force && ($current_time - $last_sync) < $this->sync_interval) {
            return;
        }

        $patterns = $this->get_patterns_to_sync();
        
        foreach (array_chunk($patterns, $this->batch_size) as $batch) {
            $this->process_pattern_batch($batch);
        }

        update_option('aps_last_pattern_sync', $current_time);
    }

    private function sync_tensors($force = false) {
        global $wpdb;
        
        $last_sync = get_option('aps_last_tensor_sync', 0);
        $current_time = time();

        if (!$force && ($current_time - $last_sync) < $this->sync_interval) {
            return;
        }

        $tensors = $this->get_tensors_to_sync();
        
        foreach (array_chunk($tensors, $this->batch_size) as $batch) {
            $this->process_tensor_batch($batch);
        }

        update_option('aps_last_tensor_sync', $current_time);
    }

    private function sync_metrics($force = false) {
        $metrics_db = new MetricsDB();
        $last_sync = get_option('aps_last_metrics_sync', 0);
        $current_time = time();

        if (!$force && ($current_time - $last_sync) < $this->sync_interval) {
            return;
        }

        $metrics = $metrics_db->get_metrics_to_sync();
        
        foreach (array_chunk($metrics, $this->batch_size) as $batch) {
            $this->process_metrics_batch($batch);
        }

        update_option('aps_last_metrics_sync', $current_time);
    }

    private function process_pattern_batch($patterns) {
        foreach ($patterns as $pattern) {
            $retry_count = 0;
            while ($retry_count < $this->retry_limit) {
                try {
                    $adapted_pattern = $this->pattern_bridge->adapt_pattern($pattern);
                    $this->store_pattern($adapted_pattern);
                    $this->update_pattern_sync_status($pattern['id'], 'synced');
                    break;
                } catch (Exception $e) {
                    $retry_count++;
                    if ($retry_count >= $this->retry_limit) {
                        $this->log_sync_error($e, [
                            'pattern_id' => $pattern['id'],
                            'retry_count' => $retry_count
                        ]);
                    }
                    sleep(1);
                }
            }
        }
    }

    private function process_tensor_batch($tensors) {
        foreach ($tensors as $tensor) {
            $retry_count = 0;
            while ($retry_count < $this->retry_limit) {
                try {
                    $this->store_tensor($tensor);
                    $this->update_tensor_sync_status($tensor['id'], 'synced');
                    break;
                } catch (Exception $e) {
                    $retry_count++;
                    if ($retry_count >= $this->retry_limit) {
                        $this->log_sync_error($e, [
                            'tensor_id' => $tensor['id'],
                            'retry_count' => $retry_count
                        ]);
                    }
                    sleep(1);
                }
            }
        }
    }

    private function process_metrics_batch($metrics) {
        foreach ($metrics as $metric) {
            try {
                $this->store_metric($metric);
            } catch (Exception $e) {
                $this->log_sync_error($e, [
                    'metric_id' => $metric['id']
                ]);
            }
        }
    }

    private function store_pattern($pattern) {
        global $wpdb;
        
        $wpdb->replace(
            $wpdb->prefix . 'aps_patterns',
            [
                'pattern_hash' => $pattern['hash'],
                'pattern_type' => $pattern['type'],
                'pattern_data' => json_encode($pattern['data']),
                'metadata' => json_encode($pattern['metadata']),
                'confidence' => $pattern['confidence'],
                'sync_status' => 'synced',
                'updated_at' => current_time('mysql')
            ]
        );
    }

    private function store_tensor($tensor) {
        global $wpdb;
        
        $wpdb->replace(
            $wpdb->prefix . 'aps_tensors',
            [
                'tensor_id' => $tensor['id'],
                'tensor_data' => json_encode($tensor['data']),
                'metadata' => json_encode($tensor['metadata']),
                'sync_status' => 'synced',
                'updated_at' => current_time('mysql')
            ]
        );
    }

    private function store_metric($metric) {
        global $wpdb;
        
        $wpdb->replace(
            $wpdb->prefix . 'aps_metrics',
            [
                'metric_type' => $metric['type'],
                'metric_name' => $metric['name'],
                'metric_value' => $metric['value'],
                'dimensions' => json_encode($metric['dimensions']),
                'timestamp' => $metric['timestamp']
            ]
        );
    }

    private function collect_site_data($site_id) {
        return [
            'patterns' => $this->get_site_patterns($site_id),
            'tensors' => $this->get_site_tensors($site_id),
            'metrics' => $this->get_site_metrics($site_id),
            'status' => $this->get_site_status($site_id)
        ];
    }

    private function distribute_network_data($network_data) {
        $sites = array_keys($network_data);
        
        foreach ($sites as $site_id) {
            switch_to_blog($site_id);
            try {
                $this->update_site_data($site_id, $network_data);
            } catch (Exception $e) {
                $this->log_sync_error($e, ['site_id' => $site_id]);
            }
            restore_current_blog();
        }

        return true;
    }

    private function cleanup_sync_data() {
        global $wpdb;
        
        $wpdb->query("DELETE FROM {$wpdb->prefix}aps_sync_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $wpdb->query("OPTIMIZE TABLE {$wpdb->prefix}aps_sync_log");
    }

    private function log_sync_error($error, $context = []) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'aps_sync_log',
            [
                'sync_type' => 'error',
                'sync_data' => json_encode([
                    'message' => $error->getMessage(),
                    'trace' => $error->getTraceAsString(),
                    'context' => $context
                ]),
                'status' => 'error',
                'created_at' => current_time('mysql')
            ]
        );
    }
}