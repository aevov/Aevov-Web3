<?php
namespace APSTools\Services;

require_once dirname( __FILE__ ) . '/../class-aps-tools-logger.php';

class MediaMonitor {
    private $processor;
    private $logger;

    public function __construct() {
        $this->processor = new \APSTools\Handlers\ChunkBatchProcessor();
        $this->logger = new \APS_Tools_Logger();
        
        // Only add WordPress hooks if WordPress is loaded
        if (function_exists('add_action')) {
            // Monitor new uploads
            add_action('add_attachment', [$this, 'handle_new_upload']);
            
            // Monitor media updates
            add_action('attachment_updated', [$this, 'handle_media_update'], 10, 3);
            
            // Add scheduled scanning
            add_action('init', [$this, 'schedule_scan']);
            add_action('aps_scan_media_library', [$this, 'scan_media_library']);
        }
    }

    public function schedule_scan() {
        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event')) {
            if (!wp_next_scheduled('aps_scan_media_library')) {
                wp_schedule_event(time(), 'hourly', 'aps_scan_media_library');
            }
        }
    }

    public function handle_new_upload($attachment_id) {
        if (!function_exists('get_post')) {
            return;
        }
        
        $file = get_post($attachment_id);
        
        // Check if this is a JSON file
        if ($file && $file->post_mime_type !== 'application/json') {
            return;
        }

        $this->process_media_file($attachment_id);
    }

    public function handle_media_update($post_id, $post_after, $post_before) {
        if (!function_exists('get_post_type') || !function_exists('get_post_mime_type')) {
            return;
        }
        
        if (get_post_type($post_id) !== 'attachment') {
            return;
        }

        if (get_post_mime_type($post_id) === 'application/json') {
            $this->process_media_file($post_id);
        }
    }

    public function scan_media_library() {
        if (!function_exists('get_posts')) {
            return;
        }
        
        $json_files = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'application/json',
            'posts_per_page' => -1,
            'post_status' => 'inherit'
        ]);

        foreach ($json_files as $file) {
            $this->process_media_file($file->ID);
        }
    }

    private function process_media_file($attachment_id) {
        // Only process if WordPress functions are available
        if (!function_exists('wp_get_attachment_url') || !function_exists('get_attached_file')) {
            return;
        }
        
        // Get file URL and content
        $file_url = wp_get_attachment_url($attachment_id);
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            return;
        }

        try {
            // Read and validate JSON
            $content = file_get_contents($file_path);
            $json_data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON file');
            }

            // Create or update chunk
            $chunk_id = $this->create_or_update_chunk($attachment_id, $json_data);
            
            if ($chunk_id) {
                // Process the chunk
                $this->processor->process_chunk($chunk_id);
                
                // Update attachment metadata (only if WordPress is available)
                if (function_exists('update_post_meta')) {
                    update_post_meta($attachment_id, '_aps_processed', true);
                    update_post_meta($attachment_id, '_aps_chunk_id', $chunk_id);
                }
            }

        } catch (\Exception $e) {
            $this->logger->error('APS Media Processing Error', ['message' => $e->getMessage(), 'attachment_id' => $attachment_id]);
            if (function_exists('update_post_meta')) {
                update_post_meta($attachment_id, '_aps_error', $e->getMessage());
            }
        }
    }

    private function create_or_update_chunk($attachment_id, $json_data) {
        $tensor_name = array_key_first($json_data);
        $tensor_data = $json_data[$tensor_name];

        // Check for existing chunk
        $existing_chunk = $this->get_existing_chunk($tensor_data['sku']);
        
        if ($existing_chunk) {
            return $existing_chunk;
        }

        // Create new chunk post (only if WordPress is available)
        if (!function_exists('wp_insert_post')) {
            return null;
        }
        
        $chunk_id = wp_insert_post([
            'post_type' => 'bloom_chunk',
            'post_title' => $tensor_data['sku'],
            'post_status' => 'publish'
        ]);

        if (is_wp_error($chunk_id)) {
            throw new \Exception($chunk_id->get_error_message());
        }

        // Link to attachment (only if WordPress is available)
        if (function_exists('update_post_meta') && function_exists('wp_get_attachment_url')) {
            update_post_meta($chunk_id, '_attachment_id', $attachment_id);
            update_post_meta($chunk_id, '_chunk_file', wp_get_attachment_url($attachment_id));
        }

        return $chunk_id;
    }

    private function get_existing_chunk($sku) {
        if (!function_exists('get_posts')) {
            return null;
        }
        
        $chunks = get_posts([
            'post_type' => 'bloom_chunk',
            'meta_key' => '_chunk_sku',
            'meta_value' => $sku,
            'posts_per_page' => 1
        ]);

        return !empty($chunks) ? $chunks[0]->ID : null;
    }
}
