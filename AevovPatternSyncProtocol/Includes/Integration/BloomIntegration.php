<?php
/**
 * Integration with BLOOM Pattern Recognition System
 * Handles communication between APS and BLOOM plugins
 * 
 * @package APS
 * @subpackage Integration
 */

namespace APS\Integration;

use APS\DB\MetricsDB;
use APS\Monitoring\AlertManager;

use APS\DB\APS_Chunk_DB; // Import the new Chunk DB class
use APS\Queue\QueueManager; // Ensure QueueManager is imported

class BloomIntegration {
    private static $instance = null;
    private $metrics;
    private $alert_manager;
    private $chunk_db; // New property for APS_Chunk_DB
    private $queue_manager; // New property for QueueManager
    private $is_connected = false;
    private $connection_error = null;
    private $sync_interval = 300; // 5 minutes

    private function __construct() {
        $this->metrics = new MetricsDB();
        $this->alert_manager = new AlertManager();
        $this->chunk_db = new APS_Chunk_DB(); // Initialize APS_Chunk_DB
        $this->queue_manager = new QueueManager(); // Initialize QueueManager
        
        $this->init_hooks();
        $this->check_bloom_availability();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init_hooks() {
        // BLOOM Pattern hooks
        if (function_exists('add_action')) {
            add_action('bloom_pattern_processed', [$this, 'handle_bloom_pattern']);
            add_action('bloom_pattern_updated', [$this, 'sync_pattern']);
            add_action('admin_init', [$this, 'check_connection']);
            add_action('aps_sync_bloom', [$this, 'sync_with_bloom']);
            add_action('bloom_processing_error', [$this, 'handle_bloom_error']);
        }
        if (function_exists('add_filter')) {
            add_filter('bloom_pre_pattern_process', [$this, 'prepare_pattern_data']);
        }
    }

    private function check_bloom_availability() {
        // Initial availability check during construction
        $this->check_connection();
    }

    public function is_available() {
        return class_exists('\BLOOM\Core') && $this->is_connected;
    }

    public function check_connection() {
        if (!class_exists('\BLOOM\Core')) {
            $this->is_connected = false;
            $this->connection_error = 'BLOOM plugin not installed';
            error_log('APS BloomIntegration: BLOOM\Core class not found');
            return false;
        }

        try {
            // Use fully qualified namespace for BLOOM\Core
            $bloom_core = \BLOOM\Core::get_instance();
            
            if (!method_exists($bloom_core, 'get_system_status')) {
                $status = [
                    'active' => true,
                    'error' => null,
                    'version' => '1.0.0',
                    'message' => 'BLOOM Core available but using fallback status'
                ];
            } else {
                $status = $bloom_core->get_system_status();
            }
            
            $this->is_connected = $status['active'];
            $this->connection_error = $status['active'] ? null : $status['error'];

            $this->record_connection_status($status);
            return $status['active'];

        } catch (\Exception $e) {
            $this->is_connected = false;
            $this->connection_error = $e->getMessage();
            $this->alert_manager->trigger_alert('bloom_connection_error', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function analyze_pattern($pattern_data) {
        if (!$this->is_available()) {
            throw new \Exception('BLOOM integration not available');
        }

        try {
            $bloom_pattern = $this->convert_to_bloom_format($pattern_data);
            $bloom_core = \BLOOM\Core::get_instance();
            $result = $bloom_core->analyze_pattern($bloom_pattern);

            $this->record_analysis_metrics($result);
            
            return $this->convert_from_bloom_format($result);

        } catch (\Exception $e) {
            $this->handle_bloom_error($e);
            throw $e;
        }
    }

    public function sync_pattern($pattern_data) {
        if (!$this->is_available()) {
            return false;
        }

        try {
            $bloom_core = \BLOOM\Core::get_instance();
            $sync_result = $bloom_core->sync_pattern($pattern_data);

            $this->record_sync_metrics($sync_result);
            
            return $sync_result;

        } catch (\Exception $e) {
            $this->alert_manager->trigger_alert('bloom_sync_error', [
                'pattern_id' => $pattern_data['id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function handle_bloom_pattern($pattern_data) {
        try {
            // Check if the incoming pattern is a chunk
            if (isset($pattern_data['status']) && $pattern_data['status'] === 'chunked_and_stored' && isset($pattern_data['original_pattern_id'])) {
                // This is a notification that a large pattern has been chunked and stored in BLOOM
                // We need to fetch and reassemble it on the APS side
                $original_pattern_id = $pattern_data['original_pattern_id'];
                $tensor_sku = $pattern_data['tensor_sku'];
                $total_parts = $pattern_data['total_parts'];

                // Store the chunk information in APS_Chunk_DB
                foreach ($pattern_data['chunks_info'] as $chunk_info) {
                    $this->chunk_db->store_chunk([
                        'original_pattern_id' => $original_pattern_id,
                        'tensor_sku' => $tensor_sku,
                        'chunk_index' => $chunk_info['chunk_index'],
                        'data' => $chunk_info['data'], // This should be the base64 encoded chunk data
                        'chunk_size' => $chunk_info['chunk_size'],
                        'checksum' => $chunk_info['checksum'],
                        'site_id' => get_current_blog_id(), // Or the site_id from BLOOM if available
                        'status' => 'partial'
                    ]);
                }

                // Check if all chunks have been received
                $received_chunks = $this->chunk_db->get_chunks_by_original_pattern_id($original_pattern_id);
                if (count($received_chunks) === $total_parts) {
                    // All chunks received, reassemble the pattern
                    $reassembled_pattern = $this->chunk_db->reassemble_pattern($original_pattern_id);

                    if (!is_wp_error($reassembled_pattern)) {
                        // Queue the reassembled pattern for APS processing
                        $job_id = $this->queue_manager->enqueue_job([
                            'type' => 'pattern_analysis',
                            'data' => $reassembled_pattern
                        ]);
                        $this->record_pattern_metrics($reassembled_pattern, $job_id);
                        $this->metrics->flush_batch_data();
                        return true;
                    } else {
                        $this->handle_bloom_error(new \Exception($reassembled_pattern->get_error_message()));
                        return false;
                    }
                } else {
                    // Not all chunks received yet, wait for more
                    return true; // Indicate successful handling of this chunk
                }
            } else {
                // Handle regular (non-chunked) patterns
                $converted_pattern = $this->convert_from_bloom_format($pattern_data);
                
                // Queue for APS processing
                $job_id = $this->queue_manager->enqueue_job([
                    'type' => 'pattern_analysis',
                    'data' => $converted_pattern
                ]);

                $this->record_pattern_metrics($pattern_data, $job_id);
                $this->metrics->flush_batch_data(); // Explicitly flush metrics for testing
                
                return true; // Return true for success
            }

        } catch (\Exception $e) {
            error_log('BloomIntegration::handle_bloom_pattern error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            $this->handle_bloom_error($e);
            return false;
        }
    }

    public function sync_with_bloom() {
        if (!$this->is_available()) {
            return false;
        }

        try {
            $bloom_core = \BLOOM\Core::get_instance();
            $patterns = $bloom_core->get_recent_patterns();
            
            foreach ($patterns as $pattern) {
                $this->sync_pattern($pattern);
            }

            $this->update_sync_status('success');
            return true;

        } catch (\Exception $e) {
            $this->update_sync_status('error', $e->getMessage());
            return false;
        }
    }

    public function prepare_pattern_data($data) {
        // Convert APS format to BLOOM format if needed
        if (isset($data['aps_format']) && $data['aps_format']) {
            return $this->convert_to_bloom_format($data);
        }
        return $data;
    }

    private function convert_to_bloom_format($pattern_data) {
        return [
            'type' => $pattern_data['type'] ?? 'generic',
            'features' => $pattern_data['features'] ?? [],
            'metadata' => array_merge(
                $pattern_data['metadata'] ?? [],
                ['source' => 'aps']
            ),
            'confidence' => $pattern_data['confidence'] ?? 0,
            'timestamp' => time()
        ];
    }

    private function convert_from_bloom_format($bloom_pattern) {
        return [
            'type' => $bloom_pattern['type'] ?? 'generic',
            'hash' => $bloom_pattern['pattern_hash'] ?? null,
            'data' => [
                'features' => $bloom_pattern['features'],
                'metadata' => array_merge(
                    $bloom_pattern['metadata'] ?? [],
                    ['bloom_processed' => true]
                ),
            ],
            'confidence' => $bloom_pattern['confidence'],
            'relationships' => $bloom_pattern['relationships'] ?? [],
            'timestamp' => $bloom_pattern['timestamp'] ?? time()
        ];
    }

    private function record_connection_status($status) {
        $this->metrics->record_metric('bloom_connection', $status['active'] ? 1 : 0, [
            'error' => $status['error'] ?? null,
            'version' => $status['version'] ?? 'unknown'
        ]);
    }

    private function record_analysis_metrics($result) {
        $this->metrics->record_metric('bloom_analysis', 1, [
            'confidence' => $result['confidence'],
            'type' => $result['type'],
            'processing_time' => $result['metadata']['processing_time'] ?? 0
        ]);
    }

    private function record_sync_metrics($result) {
        $this->metrics->record_metric('bloom_sync', 1, [
            'success' => $result['success'] ? 1 : 0,
            'patterns_synced' => count($result['patterns'] ?? []),
            'sync_time' => $result['sync_time'] ?? 0
        ]);
    }

    private function record_pattern_metrics($pattern_data, $job_id) {
        $this->metrics->record_metric(
            'pattern_received', // Metric type
            'bloom_pattern_received', // Metric name
            1, // Metric value (always 1 for a received pattern)
            [
                'pattern_type' => $pattern_data['type'] ?? 'unknown',
                'confidence' => $pattern_data['confidence'] ?? 0,
                'job_id' => $job_id
            ]
        );
    }

    private function handle_bloom_error(\Exception $e) {
        $this->alert_manager->trigger_alert('bloom_processing_error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        $this->metrics->record_metric('bloom_errors', 1, [
            'error_type' => get_class($e),
            'error_message' => $e->getMessage()
        ]);
    }

    private function update_sync_status($status, $error = null) {
        if (function_exists('update_option') && function_exists('current_time')) {
            update_option('aps_bloom_sync_status', [
                'status' => $status,
                'last_sync' => current_time('mysql'),
                'error' => $error
            ]);
        }

        if ($error) {
            $this->alert_manager->trigger_alert('bloom_sync_status', [
                'status' => $status,
                'error' => $error
            ]);
        }
    }

    public function get_integration_status() {
        return [
            'connected' => $this->is_connected,
            'error' => $this->connection_error,
            'last_sync' => function_exists('get_option') ? get_option('aps_bloom_sync_status') : null,
            'metrics' => $this->get_integration_metrics()
        ];
    }

    private function get_integration_metrics() {
        return [
            'patterns_processed' => $this->metrics->get_metric_sum('bloom_pattern_received'),
            'sync_success_rate' => $this->calculate_sync_success_rate(),
            'average_confidence' => $this->metrics->get_metric_average('bloom_analysis', 'confidence'),
            'error_rate' => $this->calculate_error_rate()
        ];
    }

    private function calculate_sync_success_rate() {
        $total_syncs = $this->metrics->get_metric_sum('bloom_sync');
        if (!$total_syncs) {
            return 0;
        }

        $successful_syncs = $this->metrics->get_metric_sum('bloom_sync', ['success' => 1]);
        return ($successful_syncs / $total_syncs) * 100;
    }

    private function calculate_error_rate() {
        $total_operations = $this->metrics->get_metric_sum('bloom_analysis');
        if (!$total_operations) {
            return 0;
        }

        $errors = $this->metrics->get_metric_sum('bloom_errors');
        return ($errors / $total_operations) * 100;
    }

    public function cleanup() {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook('aps_sync_bloom');
        }
    }
}
