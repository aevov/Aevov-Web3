<?php
namespace APSTools\Scanner;

class BatchProcessor {
    private $scanner;
    private $bloom_integration;
    private $metrics;
    private $processing = false;
    private $current_batch = [];
    private $model_id;
    private $category_id;

    public function __construct() {
        $this->scanner = new DirectoryScanner();
        
        // Optional dependencies - only instantiate if classes exist
        if (class_exists('\APSTools\Integration\BloomIntegration')) {
            $this->bloom_integration = new \APSTools\Integration\BloomIntegration();
        }
        
        if (class_exists('\APSTools\Storage\MetricsDB')) {
            $this->metrics = new \APSTools\Storage\MetricsDB();
        }
        
        // Only add WordPress hooks if WordPress is loaded
        if (function_exists('add_action')) {
            add_action('aps_process_batch', [$this, 'process_next_batch']);
        }
    }

    /**
     * Start processing directory
     */
 public function process_directory($directory_path, $model_id, $category_id, $options = []) {
        if ($this->processing) {
            throw new \Exception('Processing already in progress');
        }

        $this->processing = true;
        $this->model_id = $model_id;
        $this->category_id = $category_id;
        
        try {
            // Validate directory
            if (!is_dir($directory_path)) {
                throw new \Exception('Invalid directory path');
            }

            // Scan directory
            $files = $this->scanner->scan_directory($directory_path, array_merge([
                'recursive' => true,
                'file_pattern' => '*.json'
            ], $options));
            
            // Store scan configuration (only if WordPress is available)
            if (function_exists('update_option')) {
                update_option('aps_scanner_config', [
                    'model_id' => $model_id,
                    'category_id' => $category_id,
                    'directory' => $directory_path,
                    'options' => $options,
                    'start_time' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
                    'total_files' => count($files)
                ]);
            }
            
            // Schedule first batch (only if WordPress is available)
            if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_single_event')) {
                if (!wp_next_scheduled('aps_process_batch')) {
                    wp_schedule_single_event(time(), 'aps_process_batch');
                }
            }
            
            return [
                'total_files' => count($files),
                'status' => 'started',
                'files' => $files
            ];

        } catch (\Exception $e) {
            $this->processing = false;
            throw $e;
        }
    }

    /**
     * Process next batch of files
     */
    public function process_next_batch() {
        if (!$this->processing) {
            return;
        }

        $batch = $this->scanner->get_batch();
        $this->current_batch = $batch;

        if (empty($batch)) {
            $this->complete_processing();
            return;
        }

        foreach ($batch as $file) {
            try {
                $tensor_data = $this->process_file($file['path']);
                
                // Create chunk for the model
                $this->create_model_chunk($tensor_data);
                
                $this->scanner->update_file_status($file['path'], 'completed');
                
                if ($this->metrics) {
                    $this->metrics->record_metric('file_processed', 1, [
                        'status' => 'success',
                        'file' => basename($file['path']),
                        'model_id' => $this->model_id
                    ]);
                }

            } catch (\Exception $e) {
                $this->scanner->update_file_status(
                    $file['path'], 
                    'failed',
                    $e->getMessage()
                );

                if ($this->metrics) {
                    $this->metrics->record_metric('file_processed', 1, [
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                        'file' => basename($file['path']),
                        'model_id' => $this->model_id
                    ]);
                }
            }
        }

        // Schedule next batch (only if WordPress is available)
        if (function_exists('wp_schedule_single_event')) {
            wp_schedule_single_event(time(), 'aps_process_batch');
        }
    }

    /**
     * Process individual file
     */
    private function process_file($file_path) {
        // Read and validate JSON
        $content = file_get_contents($file_path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON: ' . json_last_error_msg());
        }

        // Validate tensor data structure
        $this->validate_tensor_data($data);

        return $data;
    }

    /**
     * Create model chunk from tensor data
     */
    private function create_model_chunk($tensor_data) {
        $chunk_data = [
            'post_title' => $tensor_data['tensor_name'] ?? basename($file_path),
            'post_type' => 'bloom_chunk',
            'post_status' => 'publish',
            'meta_input' => [
                '_parent_model' => $this->model_id,
                '_tensor_name' => $tensor_data['tensor_name'] ?? '',
                '_tensor_dtype' => $tensor_data['dtype'] ?? '',
                '_tensor_shape' => implode(',', $tensor_data['shape'] ?? []),
                '_chunk_data' => json_encode($tensor_data)
            ]
        ];

        $chunk_id = null;
        if (function_exists('wp_insert_post')) {
            $chunk_id = wp_insert_post($chunk_data);
            
            if (is_wp_error($chunk_id)) {
                throw new \Exception($chunk_id->get_error_message());
            }
        }

        // Update model chunk count
        $this->update_model_chunk_count($this->model_id);
        
        return $chunk_id;
    }

    /**
     * Validate tensor data structure
     */
    private function validate_tensor_data($data) {
        $required_fields = ['dtype', 'shape', 'data'];
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        if (!is_array($data['shape'])) {
            throw new \Exception('Shape must be an array');
        }

        return true;
    }

    /**
     * Update model chunk count
     */
    private function update_model_chunk_count($model_id) {
        if (function_exists('get_posts') && function_exists('update_post_meta')) {
            $chunks = get_posts([
                'post_type' => 'bloom_chunk',
                'meta_key' => '_parent_model',
                'meta_value' => $model_id,
                'posts_per_page' => -1,
                'fields' => 'ids'
            ]);

            update_post_meta($model_id, '_uploaded_chunks', count($chunks));
        }
    }

    /**
     * Complete processing
     */
    private function complete_processing() {
        $this->processing = false;
        $this->current_batch = [];
        
        // Clear configuration (only if WordPress is available)
        if (function_exists('delete_option')) {
            delete_option('aps_scanner_config');
        }
        
        if (function_exists('do_action')) {
            do_action('aps_processing_complete', [
                'stats' => $this->scanner->get_scan_stats(),
                'errors' => $this->scanner->get_errors(),
                'model_id' => $this->model_id
            ]);
        }
    }

    /**
     * Get current processing status
     */
     public function get_status() {
        return [
            'processing' => $this->processing,
            'current_batch' => $this->current_batch,
            'stats' => $this->scanner ? $this->scanner->get_scan_stats() : [],
            'errors' => $this->scanner ? $this->scanner->get_errors() : [],
            'config' => function_exists('get_option') ? get_option('aps_scanner_config') : null,
            'files' => $this->scanner ? $this->scanner->get_all_files() : []
        ];
    }

    /**
     * Stop processing
     */
    public function stop() {
        $this->processing = false;
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook('aps_process_batch');
        }
        if (function_exists('delete_option')) {
            delete_option('aps_scanner_config');
        }
    }
}