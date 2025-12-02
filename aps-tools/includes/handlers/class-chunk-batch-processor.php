<?php
namespace APSTools\Handlers;

class ChunkBatchProcessor {
    public function __construct() {
        // Only add WordPress hooks if WordPress is loaded
        if (function_exists('add_action')) {
            add_action('wp_ajax_process_chunk_batch', [$this, 'handle_batch_processing']);
            add_action('wp_ajax_reprocess_chunk', [$this, 'handle_chunk_reprocessing']);
        }
    }

    public function render_batch_button() {
        ?>
        <div class="batch-actions" style="margin: 1em 0;">
            <button type="button" class="button button-primary process-all-chunks">
                <?php _e('Process All Pending Chunks', 'aps-tools'); ?>
            </button>
            <button type="button" class="button generate-patterns">
                <?php _e('Generate Patterns', 'aps-tools'); ?>
            </button>
        </div>
        <div id="batch-processing-status"></div>
        <script>
        jQuery(document).ready(function($) {
            // Process all chunks
            $('.process-all-chunks').on('click', function() {
                var $button = $(this);
                var $status = $('#batch-processing-status');
                
                $button.prop('disabled', true);
                $status.html('Processing chunks...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'process_chunk_batch',
                        nonce: '<?php echo wp_create_nonce("aps-tools-nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.html('Successfully processed chunks. Reloading...');
                            location.reload();
                        } else {
                            $status.html('Error: ' + response.data.message);
                            $button.prop('disabled', false);
                        }
                    }
                });
            });

            // Generate patterns
            $('.generate-patterns').on('click', function() {
                var $button = $(this);
                var $status = $('#batch-processing-status');
                
                $button.prop('disabled', true);
                $status.html('Generating patterns...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'generate_patterns_from_chunks',
                        nonce: '<?php echo wp_create_nonce("aps-tools-nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.html('Successfully generated patterns: ' + response.data.count);
                        } else {
                            $status.html('Error: ' + response.data.message);
                        }
                        $button.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function handle_batch_processing() {
        check_ajax_referer('aps-tools-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        try {
            $chunks = \APSTools\Models\BloomTensorStorage::instance()->get_all_stored_chunks();
            $processed = 0;

            foreach ($chunks as $chunk) {
                if ($this->process_chunk($chunk['chunk_id'])) {
                    $processed++;
                }
            }

            wp_send_json_success([
                'message' => sprintf(__('Processed %d chunks', 'aps-tools'), $processed),
                'processed' => $processed
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function handle_chunk_reprocessing() {
        check_ajax_referer('aps-tools-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $chunk_id = intval($_POST['chunk_id']);
        
        try {
            if ($this->process_chunk($chunk_id)) {
                wp_send_json_success(['message' => 'Chunk reprocessed successfully']);
            } else {
                wp_send_json_error(['message' => 'Failed to reprocess chunk']);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function handle_pattern_generation() {
        check_ajax_referer('aps-tools-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        try {
            // Get all processed chunks
            $chunks = \APSTools\Models\BloomTensorStorage::instance()->get_all_stored_chunks();
            $chunk_ids = array_column($chunks, 'chunk_id');

            // Use the pattern generator from the main plugin
            if (class_exists('\APS\Pattern\PatternGenerator')) {
                $generator = new \APS\Pattern\PatternGenerator();
                $patterns = $generator->generate_patterns($chunk_ids);

                wp_send_json_success([
                    'message' => sprintf(__('Generated %d patterns', 'aps-tools'), count($patterns)),
                    'count' => count($patterns)
                ]);
            } else {
                throw new \Exception('Pattern generator not available');
            }

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

   private function process_chunk($chunk_id) {
    $storage = \APSTools\Models\BloomTensorStorage::instance();
    $chunk_data = $storage->get_tensor_data($chunk_id);

    if (!$chunk_data) {
        return false;
    }

    update_post_meta($chunk_id, '_status', 'processing');

    try {
        // Get the first tensor from the data (assuming it's the main tensor)
        $tensor_name = array_key_first($chunk_data);
        $tensor = $chunk_data[$tensor_name];

        // Validate tensor data structure
        if (!isset($tensor['sku']) || !isset($tensor['dtype']) || !isset($tensor['shape'])) {
            throw new \Exception('Invalid tensor data structure');
        }

        // Update chunk meta
        update_post_meta($chunk_id, '_chunk_sku', $tensor['sku']);
        update_post_meta($chunk_id, '_tensor_name', $tensor_name);
        update_post_meta($chunk_id, '_tensor_dtype', $tensor['dtype']);
        update_post_meta($chunk_id, '_tensor_shape', implode(',', $tensor['shape']));

        // Handle partial chunks if applicable
        if (isset($tensor['is_partial']) && $tensor['is_partial']) {
            update_post_meta($chunk_id, '_is_partial', true);
            update_post_meta($chunk_id, '_part_number', $tensor['part']);
            update_post_meta($chunk_id, '_total_parts', $tensor['total_parts']);
        }

        // Set parent model if available
        $model_id = $this->get_or_create_model($tensor_name);
        if ($model_id) {
            update_post_meta($chunk_id, '_parent_model', $model_id);
        }

        // Process tensor data dimensions
        $dimensions = array_product($tensor['shape']);
        update_post_meta($chunk_id, '_tensor_dimensions', $dimensions);

        // Calculate and store tensor statistics
        $stats = $this->calculate_tensor_stats($tensor);
        update_post_meta($chunk_id, '_tensor_stats', $stats);

        update_post_meta($chunk_id, '_status', 'processed');
        update_post_meta($chunk_id, '_processed_at', current_time('mysql'));
        
        return true;

    } catch (\Exception $e) {
        update_post_meta($chunk_id, '_status', 'failed');
        update_post_meta($chunk_id, '_error', $e->getMessage());
        return false;
    }
}

private function get_or_create_model($tensor_name) {
    // Check if model exists
    $existing_models = get_posts([
        'post_type' => 'bloom_model',
        'meta_key' => '_tensor_name',
        'meta_value' => $tensor_name,
        'posts_per_page' => 1
    ]);

    if (!empty($existing_models)) {
        return $existing_models[0]->ID;
    }

    // Create new model
    $model_id = wp_insert_post([
        'post_type' => 'bloom_model',
        'post_title' => $tensor_name,
        'post_status' => 'publish'
    ]);

    if ($model_id) {
        update_post_meta($model_id, '_tensor_name', $tensor_name);
        update_post_meta($model_id, '_created_at', current_time('mysql'));
    }

    return $model_id;
}

private function calculate_tensor_stats($tensor) {
    return [
        'size' => strlen(json_encode($tensor)),
        'dimensions' => count($tensor['shape']),
        'total_elements' => array_product($tensor['shape']),
        'dtype' => $tensor['dtype'],
        'is_partial' => isset($tensor['is_partial']) ? $tensor['is_partial'] : false,
        'calculated_at' => current_time('mysql')
    ];
}
}