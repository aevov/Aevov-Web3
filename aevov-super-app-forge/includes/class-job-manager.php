<?php

namespace AevovSuperAppForge;

use Exception;

/**
 * Job Manager for Super App Forge
 *
 * Manages forge jobs including creation, tracking, status updates,
 * and lifecycle management. Integrates with WebSocket server for
 * real-time updates.
 */
class JobManager {

    private $websocket_server;
    private $job_storage_prefix = 'aevov_forge_job_';

    /**
     * Initialize Job Manager
     *
     * @param WebSocketServer|null $websocket_server WebSocket server instance.
     */
    public function __construct( $websocket_server = null ) {
        $this->websocket_server = $websocket_server;
    }

    /**
     * Create a new forge job
     *
     * @param array $params Job parameters.
     * @return string|WP_Error Job ID or error.
     */
    public function create_job( $params ) {
        try {
            // Validate required parameters
            $validation = $this->validate_job_params($params);
            if (is_wp_error($validation)) {
                return $validation;
            }

            // Generate unique job ID
            $job_id = 'job_' . uniqid() . '_' . time();

            // Prepare job data
            $job_data = [
                'job_id' => $job_id,
                'params' => $params,
                'status' => 'pending',
                'progress' => 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
                'created_by' => get_current_user_id(),
                'app_type' => $params['app_type'] ?? 'unknown',
                'priority' => $params['priority'] ?? 'normal',
                'metadata' => $params['metadata'] ?? [],
                'steps' => $this->generate_job_steps($params),
                'current_step' => 0,
                'websocket_url' => $this->get_websocket_url(),
                'logs' => []
            ];

            // Store job
            $saved = update_option($this->job_storage_prefix . $job_id, $job_data);

            if (!$saved) {
                return new \WP_Error('job_creation_failed', 'Failed to store job data');
            }

            // Add to job queue
            $this->add_to_queue($job_id, $job_data['priority']);

            // Log creation
            $this->log_job_event($job_id, 'created', 'Job created successfully');

            // Notify via WebSocket if available
            if ($this->websocket_server) {
                $this->websocket_server->broadcast([
                    'type' => 'job_created',
                    'job_id' => $job_id,
                    'app_type' => $job_data['app_type']
                ]);
            }

            return $job_id;

        } catch (Exception $e) {
            error_log("Job creation failed: " . $e->getMessage());
            return new \WP_Error('job_creation_error', $e->getMessage());
        }
    }

    /**
     * Get job details
     *
     * @param string $job_id Job ID.
     * @return array|WP_Error Job data or error.
     */
    public function get_job( $job_id ) {
        $job_data = get_option($this->job_storage_prefix . $job_id, false);

        if (!$job_data) {
            return new \WP_Error('job_not_found', "Job {$job_id} not found");
        }

        // Add real-time status if available
        $job_data['is_active'] = $this->is_job_active($job_id);
        $job_data['runtime'] = $this->calculate_runtime($job_data);

        return $job_data;
    }

    /**
     * Update job status
     *
     * @param string $job_id Job ID.
     * @param string $status New status.
     * @param int $progress Progress percentage (0-100).
     * @param array $metadata Additional metadata.
     * @return bool Success status.
     */
    public function update_job_status( $job_id, $status, $progress = null, $metadata = [] ) {
        $job_data = $this->get_job($job_id);

        if (is_wp_error($job_data)) {
            return false;
        }

        $job_data['status'] = $status;
        $job_data['updated_at'] = current_time('mysql');

        if ($progress !== null) {
            $job_data['progress'] = max(0, min(100, $progress));
        }

        if (!empty($metadata)) {
            $job_data['metadata'] = array_merge($job_data['metadata'], $metadata);
        }

        // Handle status transitions
        switch ($status) {
            case 'running':
                if (!isset($job_data['started_at'])) {
                    $job_data['started_at'] = current_time('mysql');
                }
                break;

            case 'completed':
                $job_data['completed_at'] = current_time('mysql');
                $job_data['progress'] = 100;
                break;

            case 'failed':
                $job_data['failed_at'] = current_time('mysql');
                break;

            case 'cancelled':
                $job_data['cancelled_at'] = current_time('mysql');
                break;
        }

        // Save updated job
        $saved = update_option($this->job_storage_prefix . $job_id, $job_data);

        if ($saved) {
            // Log status change
            $this->log_job_event($job_id, 'status_changed', "Status changed to {$status}", [
                'new_status' => $status,
                'progress' => $progress
            ]);

            // Broadcast update via WebSocket
            if ($this->websocket_server) {
                $this->websocket_server->broadcast([
                    'type' => 'job_status_update',
                    'job_id' => $job_id,
                    'status' => $status,
                    'progress' => $job_data['progress']
                ]);
            }

            return true;
        }

        return false;
    }

    /**
     * Delete a job
     *
     * @param string $job_id Job ID.
     * @param bool $force Force deletion even if job is running.
     * @return bool Success status.
     */
    public function delete_job( $job_id, $force = false ) {
        $job_data = $this->get_job($job_id);

        if (is_wp_error($job_data)) {
            return false;
        }

        // Prevent deletion of running jobs unless forced
        if (!$force && $job_data['status'] === 'running') {
            error_log("Cannot delete running job {$job_id} without force flag");
            return false;
        }

        // Cancel if running
        if ($job_data['status'] === 'running') {
            $this->cancel_job($job_id);
        }

        // Remove from queue
        $this->remove_from_queue($job_id);

        // Delete job data
        $deleted = delete_option($this->job_storage_prefix . $job_id);

        // Delete job logs
        delete_option($this->job_storage_prefix . $job_id . '_logs');

        if ($deleted) {
            // Notify via WebSocket
            if ($this->websocket_server) {
                $this->websocket_server->broadcast([
                    'type' => 'job_deleted',
                    'job_id' => $job_id
                ]);
            }
        }

        return $deleted;
    }

    /**
     * Cancel a job
     *
     * @param string $job_id Job ID.
     * @return bool Success status.
     */
    public function cancel_job( $job_id ) {
        return $this->update_job_status($job_id, 'cancelled');
    }

    /**
     * Get all jobs
     *
     * @param array $filters Filters (status, app_type, user_id).
     * @return array Jobs.
     */
    public function get_all_jobs( $filters = [] ) {
        global $wpdb;

        $jobs = [];

        // Get all job options
        $job_keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",
                $this->job_storage_prefix . '%',
                $this->job_storage_prefix . '%_logs'
            )
        );

        foreach ($job_keys as $key) {
            $job_data = get_option($key);

            if (!$job_data) {
                continue;
            }

            // Apply filters
            if (!empty($filters['status']) && $job_data['status'] !== $filters['status']) {
                continue;
            }

            if (!empty($filters['app_type']) && $job_data['app_type'] !== $filters['app_type']) {
                continue;
            }

            if (!empty($filters['user_id']) && $job_data['created_by'] != $filters['user_id']) {
                continue;
            }

            $jobs[] = $job_data;
        }

        // Sort by creation date (newest first)
        usort($jobs, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $jobs;
    }

    /**
     * Get job queue
     *
     * @return array Job IDs in queue.
     */
    public function get_queue() {
        return get_option('aevov_forge_job_queue', []);
    }

    /**
     * Process next job in queue
     *
     * @return string|false Job ID or false if queue is empty.
     */
    public function process_next_job() {
        $queue = $this->get_queue();

        if (empty($queue)) {
            return false;
        }

        // Get highest priority job
        $job_id = array_shift($queue);
        update_option('aevov_forge_job_queue', $queue);

        // Start processing
        $this->update_job_status($job_id, 'running', 0);

        return $job_id;
    }

    /**
     * Advance job to next step
     *
     * @param string $job_id Job ID.
     * @return bool Success status.
     */
    public function advance_job_step( $job_id ) {
        $job_data = $this->get_job($job_id);

        if (is_wp_error($job_data)) {
            return false;
        }

        $current_step = $job_data['current_step'];
        $total_steps = count($job_data['steps']);

        if ($current_step >= $total_steps - 1) {
            // Job completed
            return $this->update_job_status($job_id, 'completed');
        }

        $job_data['current_step'] = $current_step + 1;
        $progress = (int) (($current_step + 1) / $total_steps * 100);

        update_option($this->job_storage_prefix . $job_id, $job_data);

        $this->update_job_status($job_id, 'running', $progress);

        $this->log_job_event($job_id, 'step_advanced', "Advanced to step " . ($current_step + 1), [
            'step' => $current_step + 1,
            'step_name' => $job_data['steps'][$current_step + 1]['name']
        ]);

        return true;
    }

    /**
     * Validate job parameters
     *
     * @param array $params Job parameters.
     * @return true|WP_Error True or error.
     */
    private function validate_job_params( $params ) {
        if (empty($params['app_type'])) {
            return new \WP_Error('invalid_params', 'app_type is required');
        }

        $valid_app_types = ['web', 'mobile', 'desktop', 'api', 'microservice'];
        if (!in_array($params['app_type'], $valid_app_types)) {
            return new \WP_Error('invalid_params', "Invalid app_type. Must be one of: " . implode(', ', $valid_app_types));
        }

        return true;
    }

    /**
     * Generate job steps based on parameters
     *
     * @param array $params Job parameters.
     * @return array Steps.
     */
    private function generate_job_steps( $params ) {
        $app_type = $params['app_type'] ?? 'web';

        $base_steps = [
            ['name' => 'initialize', 'description' => 'Initialize forge environment'],
            ['name' => 'analyze', 'description' => 'Analyze app requirements'],
            ['name' => 'scaffold', 'description' => 'Generate project scaffold'],
            ['name' => 'dependencies', 'description' => 'Install dependencies'],
            ['name' => 'configure', 'description' => 'Configure application'],
            ['name' => 'build', 'description' => 'Build application'],
            ['name' => 'test', 'description' => 'Run tests'],
            ['name' => 'package', 'description' => 'Package application'],
            ['name' => 'finalize', 'description' => 'Finalize and cleanup']
        ];

        // Add app-type specific steps
        if ($app_type === 'mobile') {
            array_splice($base_steps, 6, 0, [
                ['name' => 'sign', 'description' => 'Sign application']
            ]);
        }

        return $base_steps;
    }

    /**
     * Add job to queue
     *
     * @param string $job_id Job ID.
     * @param string $priority Priority level.
     */
    private function add_to_queue( $job_id, $priority = 'normal' ) {
        $queue = $this->get_queue();

        // Insert based on priority
        if ($priority === 'high') {
            array_unshift($queue, $job_id);
        } else {
            $queue[] = $job_id;
        }

        update_option('aevov_forge_job_queue', $queue);
    }

    /**
     * Remove job from queue
     *
     * @param string $job_id Job ID.
     */
    private function remove_from_queue( $job_id ) {
        $queue = $this->get_queue();
        $queue = array_filter($queue, function($id) use ($job_id) {
            return $id !== $job_id;
        });
        update_option('aevov_forge_job_queue', array_values($queue));
    }

    /**
     * Log job event
     *
     * @param string $job_id Job ID.
     * @param string $event Event type.
     * @param string $message Event message.
     * @param array $data Additional data.
     */
    private function log_job_event( $job_id, $event, $message, $data = [] ) {
        $logs_key = $this->job_storage_prefix . $job_id . '_logs';
        $logs = get_option($logs_key, []);

        $logs[] = [
            'timestamp' => current_time('mysql'),
            'event' => $event,
            'message' => $message,
            'data' => $data
        ];

        // Keep last 100 log entries
        $logs = array_slice($logs, -100);

        update_option($logs_key, $logs);
    }

    /**
     * Check if job is currently active
     *
     * @param string $job_id Job ID.
     * @return bool Active status.
     */
    private function is_job_active( $job_id ) {
        $job_data = get_option($this->job_storage_prefix . $job_id);

        if (!$job_data) {
            return false;
        }

        if ($job_data['status'] !== 'running') {
            return false;
        }

        // Check if updated recently (within 5 minutes)
        $last_update = strtotime($job_data['updated_at']);
        $now = time();

        return ($now - $last_update) < 300;
    }

    /**
     * Calculate job runtime
     *
     * @param array $job_data Job data.
     * @return int Runtime in seconds.
     */
    private function calculate_runtime( $job_data ) {
        if (empty($job_data['started_at'])) {
            return 0;
        }

        $end_time = $job_data['completed_at'] ?? $job_data['failed_at'] ?? current_time('mysql');

        return strtotime($end_time) - strtotime($job_data['started_at']);
    }

    /**
     * Get WebSocket URL
     *
     * @return string WebSocket URL.
     */
    private function get_websocket_url() {
        $host = get_option('aevov_websocket_host', 'localhost');
        $port = get_option('aevov_websocket_port', 8080);

        return "ws://{$host}:{$port}";
    }

    /**
     * Clean up old completed jobs
     *
     * @param int $days_old Delete jobs older than this many days.
     * @return int Number of jobs deleted.
     */
    public function cleanup_old_jobs( $days_old = 30 ) {
        $jobs = $this->get_all_jobs(['status' => 'completed']);
        $deleted = 0;
        $cutoff = strtotime("-{$days_old} days");

        foreach ($jobs as $job) {
            $completed_at = strtotime($job['completed_at'] ?? $job['created_at']);

            if ($completed_at < $cutoff) {
                if ($this->delete_job($job['job_id'], true)) {
                    $deleted++;
                }
            }
        }

        error_log("Cleaned up {$deleted} old forge jobs");

        return $deleted;
    }
}
