<?php
namespace APSTools\Handlers;

class ChunkBatchProcessor {
    public function __construct() {
        add_action('wp_ajax_process_chunk_batch', [$this, 'handle_batch_processing']);
        add_action('wp_ajax_reprocess_chunk', [$this, 'handle_chunk_reprocessing']);
    }

    public function render_batch_button() {
        ?>
        <div class="batch-actions" style="margin: 1em 0;">
            <button type="button" class="button button-primary process-all-chunks">
                <?php _e('Process All Pending Chunks', 'aps-tools'); ?>
            </button>
        </div>
        
        <div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <?php 
    // Render the table
    $table_handler = \APSTools\Handlers\TableHandler::instance();
    $table_handler->render_table();

    // Render pattern generation controls
    $pattern_handler = \APSTools\Handlers\PatternHandler::instance();
    $pattern_handler->render_pattern_controls();
    ?>
</div>
        <div id="batch-processing-status"></div>
        <script>
        jQuery(document).ready(function($) {
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
                    },
                    error: function() {
                        $status.html('Error processing chunks');
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

    private function process_chunk($chunk_id) {
        $storage = \APSTools\Models\BloomTensorStorage::instance();
        $chunk_data = $storage->get_tensor_data($chunk_id);

        if (!$chunk_data) {
            return false;
        }

        // Update chunk status
        update_post_meta($chunk_id, '_status', 'processing');

        try {
            // Call Aevov Pattern Sync-protocol's API
            $response = wp_remote_post(rest_url('aps/v1/patterns/process-chunk'), [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-WP-Nonce' => wp_create_nonce('wp_rest')
                ],
                'body' => wp_json_encode([
                    'chunk_id' => $chunk_id,
                    'tensor_data' => $chunk_data
                ])
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $result = json_decode(wp_remote_retrieve_body($response), true);
            
            if (!empty($result['success'])) {
                update_post_meta($chunk_id, '_status', 'processed');
                return true;
            }

            throw new \Exception($result['message'] ?? 'Processing failed');

        } catch (\Exception $e) {
            update_post_meta($chunk_id, '_status', 'failed');
            update_post_meta($chunk_id, '_error', $e->getMessage());
            return false;
        }
    }
}