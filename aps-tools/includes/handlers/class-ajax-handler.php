<?php
namespace APSTools\Handlers;

class AjaxHandler {
    private $tensor_storage;
    private $batch_processor;

    public function __construct() {
        $this->tensor_storage = \APSTools\Models\BloomTensorStorage::instance();
        $this->batch_processor = new ChunkBatchProcessor();

        // Register AJAX actions
        add_action('wp_ajax_get_chunk_data', [$this, 'get_chunk_data']);
        add_action('wp_ajax_reprocess_chunk', [$this, 'reprocess_chunk']);
        add_action('wp_ajax_process_stored_chunks', [$this, 'process_stored_chunks']);
    }

    public function get_chunk_data() {
        check_ajax_referer('aps-tools-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $chunk_id = intval($_POST['chunk_id']);
        $chunk_data = $this->tensor_storage->get_tensor_data($chunk_id);

        if (!$chunk_data) {
            wp_send_json_error(['message' => 'Chunk data not found']);
        }

        wp_send_json_success($chunk_data);
    }

    public function reprocess_chunk() {
        check_ajax_referer('aps-tools-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $chunk_id = intval($_POST['chunk_id']);
        
        try {
            // Get chunk file path
            $file_path = get_post_meta($chunk_id, '_chunk_file', true);
            if (!$file_path || !file_exists($file_path)) {
                throw new \Exception('Chunk file not found');
            }

            // Process the chunk
            $result = $this->batch_processor->process_chunk_file($file_path);
            
            // Update status
            update_post_meta($chunk_id, '_status', 'processed');
            
            wp_send_json_success(['message' => 'Chunk reprocessed successfully']);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function process_stored_chunks() {
        check_ajax_referer('aps-tools-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        try {
            $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
            $result = $this->batch_processor->process_batch($offset);
            
            wp_send_json_success($result);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
}

// Initialize the handler
add_action('init', function() {
    new AjaxHandler();
});