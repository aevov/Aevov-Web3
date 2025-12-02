<?php
/**
 * Process queue management for asynchronous pattern processing
 * 
 * @package APS
 * @subpackage Queue
 */

namespace APS\Queue;

use APS\DB\MetricsDB;
use APS\Monitoring\AlertManager;

class ProcessQueue {
    private $db;
    private $metrics;
    private $alert_manager;
    private $queue_table;
    private $batch_size = 50;
    private $max_attempts = 3;
    private $lock_timeout = 300; // 5 minutes
    
    private $processor_id;
    private $is_processing = false;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->queue_table = $wpdb->prefix . 'aps_process_queue';
        
        $this->metrics = new MetricsDB();
        $this->alert_manager = new AlertManager();
        $this->processor_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('aps_', true);
        
        $this->init_hooks();
    }

    private function init_hooks() {
        if (function_exists('add_action')) {
            add_action('init', [$this, 'schedule_processor']);
            add_action('aps_process_queue', [$this, 'process_queue']);
            add_action('aps_cleanup_queue', [$this, 'cleanup_queue']);
            add_action('aps_release_locks', [$this, 'release_stale_locks']);
            add_action('shutdown', [$this, 'handle_shutdown']);
        }
    }

    public function enqueue_job($job_data, $priority = 10) {
        $job = [
            'job_uuid' => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('job_', true), // Changed from job_id to job_uuid
            'job_type' => $job_data['type'],
            'job_data' => json_encode($job_data['data']) !== false ? json_encode($job_data['data']) : '', // Ensure job_data is a string
            'priority' => $priority,
            'status' => 'pending',
            // Removed site_id as it's not in the table schema
            'created_at' => function_exists('current_time') ? current_time('mysql', true) : date('Y-m-d H:i:s')
        ];

        $result = $this->db->insert(
            $this->queue_table,
            $job,
            ['%s', '%s', '%s', '%d', '%s', '%s'] // Removed %d for site_id
        );

        if ($result) {
            if (function_exists('do_action')) {
                do_action('aps_job_enqueued', $job);
            }
            return $job['job_uuid']; // Changed from job_id to job_uuid
        }

        return false;
    }

    public function process_queue() {
        if ($this->is_processing) {
            return;
        }

        $this->is_processing = true;

        try {
            $batch = $this->get_next_batch();
            
            if (empty($batch)) {
                return;
            }

            foreach ($batch as $job) {
                $this->process_job($job);
            }

        } catch (\Exception $e) {
            $this->alert_manager->trigger_alert('queue_processing_error', [
                'error' => $e->getMessage(),
                'processor_id' => $this->processor_id
            ]);
        } finally {
            $this->is_processing = false;
        }
    }

    private function process_job($job) {
        $start_time = microtime(true);

        try {
            // Lock the job
            if (!$this->lock_job($job->id)) {
                return;
            }

            // Update status to processing
            $this->update_job_status($job->id, 'processing');

            // Process based on job type
            $result = $this->handle_job($job);

            // Update job status based on result
            if ($result) {
                $this->complete_job($job->id);
            } else {
                $this->fail_job($job->id, $result);
            }

            // Record metrics
            $this->record_job_metrics($job, microtime(true) - $start_time);

        } catch (\Exception $e) {
            $this->handle_job_error($job, $e);
        } finally {
            // Release lock
            $this->unlock_job($job->id);
        }
    }

    private function handle_job($job) {
        $job_data = json_decode($job->job_data, true);
        
        switch ($job->job_type) {
            case 'pattern_analysis':
                return $this->handle_pattern_analysis($job_data);
            
            case 'pattern_distribution':
                return $this->handle_pattern_distribution($job_data);
            
            case 'pattern_sync':
                return $this->handle_pattern_sync($job_data);
            
            case 'bloom_integration':
                return $this->handle_bloom_integration($job_data);
            
            default:
                return function_exists('apply_filters') ? apply_filters('aps_process_job', false, $job) : false;
        }
    }

    private function handle_pattern_analysis($data) {
        try {
            $analyzer = new \APS\Analysis\PatternAnalyzer();
            $result = $analyzer->analyze_pattern($data);
            
            $min_confidence = function_exists('get_option') ? get_option('aps_min_confidence', 0.75) : 0.75;
            if ($result['confidence'] >= $min_confidence) {
                $pattern_id = $this->store_pattern_result($result);
                return $pattern_id ? true : 'Failed to store pattern';
            }
            
            return 'Pattern confidence too low';
            
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    private function handle_pattern_distribution($data) {
        try {
            $distributor = new \APS\Network\PatternDistributor();
            return $distributor->distribute_pattern($data);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    private function handle_pattern_sync($data) {
        try {
            $sync_manager = new \APS\Network\SyncManager();
            return $sync_manager->sync_patterns($data);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    private function handle_bloom_integration($data) {
        try {
            $bloom_integration = \APS\Integration\BloomIntegration::get_instance(); // Use get_instance() for singleton
            return $bloom_integration->handle_bloom_pattern($data); // Changed method call
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    private function get_next_batch() {
        return $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->queue_table} 
             WHERE status = 'pending' 
             AND attempts < %d 
             AND (processor_id IS NULL OR processor_id = %s) 
             ORDER BY priority DESC, created_at ASC 
             LIMIT %d",
            $this->max_attempts,
            $this->processor_id,
            $this->batch_size
        ));
    }

    private function lock_job($job_id) {
        return $this->db->update(
            $this->queue_table,
            [
                'processor_id' => $this->processor_id,
                'locked_at' => function_exists('current_time') ? current_time('mysql', true) : date('Y-m-d H:i:s')
            ],
            ['id' => $job_id],
            ['%s', '%s'],
            ['%d']
        ) !== false;
    }

    private function unlock_job($job_id) {
        return $this->db->update(
            $this->queue_table,
            ['processor_id' => null, 'locked_at' => null],
            ['id' => $job_id]
        );
    }

    private function update_job_status($job_id, $status) {
        return $this->db->update(
            $this->queue_table,
            [
                'status' => $status,
                'updated_at' => function_exists('current_time') ? current_time('mysql', true) : date('Y-m-d H:i:s')
            ],
            ['id' => $job_id]
        );
    }

    private function complete_job($job_id) {
        return $this->db->update(
            $this->queue_table,
            [
                'status' => 'completed',
                'completed_at' => function_exists('current_time') ? current_time('mysql', true) : date('Y-m-d H:i:s')
            ],
            ['id' => $job_id]
        );
    }

    private function fail_job($job_id, $error) {
        $job = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->queue_table} WHERE id = %d",
            $job_id
        ));

        if (!$job) {
            return false;
        }

        $attempts = $job->attempts + 1;
        $status = $attempts >= $this->max_attempts ? 'failed' : 'pending';

        return $this->db->update(
            $this->queue_table,
            [
                'status' => $status,
                'attempts' => $attempts,
                'error_log' => $error, // Changed from last_error to error_log
                'updated_at' => function_exists('current_time') ? current_time('mysql', true) : date('Y-m-d H:i:s')
            ],
            ['id' => $job_id]
        );
    }

    private function handle_job_error($job, \Exception $e) {
        $this->fail_job($job->id, $e->getMessage());
        
        $this->alert_manager->trigger_alert('job_processing_error', [
            'job_id' => $job->id,
            'job_type' => $job->job_type,
            'error' => $e->getMessage(),
            'attempts' => $job->attempts + 1
        ]);

        $this->metrics->record_metric('queue_errors', 1, [
            'job_type' => $job->job_type,
            'error_type' => get_class($e)
        ]);
    }

    public function cleanup_queue() {
        // Remove old completed jobs
        $this->db->query($this->db->prepare(
            "DELETE FROM {$this->queue_table} 
             WHERE status = 'completed' 
             AND completed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            7
        ));

        // Archive old failed jobs
        $this->db->query(
            "INSERT INTO {$this->queue_table}_archive 
             SELECT * FROM {$this->queue_table} 
             WHERE status = 'failed' 
             AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        $this->db->query(
            "DELETE FROM {$this->queue_table} 
             WHERE status = 'failed' 
             AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }

    private function release_stale_locks() {
        $current_time = function_exists('current_time') ? current_time('mysql', true) : date('Y-m-d H:i:s');
        $stale_time = date('Y-m-d H:i:s', time() - $this->lock_timeout);

        $query = $this->db->prepare(
            "UPDATE {$this->queue_table}
             SET processor_id = NULL, locked_at = NULL, updated_at = %s
             WHERE status = 'processing' AND locked_at < %s",
            $current_time,
            $stale_time
        );
        return $this->db->query($query);
    }

    private function record_job_metrics($job, $processing_time) {
        $this->metrics->record_metric('job_processing_time', $processing_time, [
            'job_type' => $job->job_type,
            'status' => $job->status
        ]);

        $this->metrics->record_metric('jobs_processed', 1, [
            'job_type' => $job->job_type,
            'status' => $job->status
        ]);
    }

    public function get_queue_stats() {
        return [
            'pending' => $this->db->get_var(
                "SELECT COUNT(*) FROM {$this->queue_table} WHERE status = 'pending'"
            ),
            'processing' => $this->db->get_var(
                "SELECT COUNT(*) FROM {$this->queue_table} WHERE status = 'processing'"
            ),
            'failed' => $this->db->get_var(
                "SELECT COUNT(*) FROM {$this->queue_table} WHERE status = 'failed'"
            ),
            'completed' => $this->db->get_var(
                "SELECT COUNT(*) FROM {$this->queue_table} WHERE status = 'completed'"
            ),
            'avg_processing_time' => $this->get_average_processing_time(),
            'error_rate' => $this->calculate_error_rate()
        ];
    }

    private function get_average_processing_time() {
        return $this->metrics->get_metric_average('job_processing_time');
    }

    private function calculate_error_rate() {
        $total = $this->metrics->get_metric_sum('jobs_processed');
        if (!$total) {
            return 0;
        }

        $errors = $this->metrics->get_metric_sum('queue_errors');
        return ($errors / $total) * 100;
    }

    public function handle_shutdown() {
        if ($this->is_processing) {
            $this->release_stale_locks();
        }
    }
}