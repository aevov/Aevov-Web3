<?php
/**
 * Admin metaboxes for APS Plugin
 * 
 * @package APS
 * @subpackage Admin
 */

namespace APS\Admin;

use APS\Core\Logger;

class APS_Metaboxes {
    private $logger;
    private $settings;
    private $metaboxes = [];
    
    public function __construct(APS_Settings $settings = null) {
        $this->logger = Logger::get_instance();
        $this->settings = $settings ?: new APS_Settings();
        $this->init_metaboxes();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        if (function_exists('add_action')) {
            add_action('add_meta_boxes', [$this, 'add_metaboxes']);
            add_action('save_post', [$this, 'save_metabox_data']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_metabox_scripts']);
        }
    }
    
    private function init_metaboxes() {
        $this->metaboxes = [
            'aps_pattern_info' => [
                'title' => function_exists('__') ? __('Pattern Information', 'aps') : 'Pattern Information',
                'callback' => [$this, 'render_pattern_info_metabox'],
                'screen' => 'aps_pattern',
                'context' => 'normal',
                'priority' => 'high'
            ],
            'aps_sync_status' => [
                'title' => function_exists('__') ? __('Sync Status', 'aps') : 'Sync Status',
                'callback' => [$this, 'render_sync_status_metabox'],
                'screen' => 'aps_pattern',
                'context' => 'side',
                'priority' => 'default'
            ],
            'aps_pattern_chunks' => [
                'title' => function_exists('__') ? __('Pattern Chunks', 'aps') : 'Pattern Chunks',
                'callback' => [$this, 'render_pattern_chunks_metabox'],
                'screen' => 'aps_pattern',
                'context' => 'normal',
                'priority' => 'default'
            ],
            'aps_bloom_integration' => [
                'title' => function_exists('__') ? __('BLOOM Integration', 'aps') : 'BLOOM Integration',
                'callback' => [$this, 'render_bloom_integration_metabox'],
                'screen' => 'aps_pattern',
                'context' => 'side',
                'priority' => 'default'
            ],
            'aps_pattern_metrics' => [
                'title' => function_exists('__') ? __('Pattern Metrics', 'aps') : 'Pattern Metrics',
                'callback' => [$this, 'render_pattern_metrics_metabox'],
                'screen' => 'aps_pattern',
                'context' => 'normal',
                'priority' => 'low'
            ]
        ];
    }
    
    public function add_metaboxes() {
        foreach ($this->metaboxes as $id => $metabox) {
            if (function_exists('add_meta_box')) {
                add_meta_box(
                    $id,
                    $metabox['title'],
                    $metabox['callback'],
                    $metabox['screen'],
                    $metabox['context'],
                    $metabox['priority']
                );
            }
        }
    }
    
    public function render_pattern_info_metabox($post) {
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field('aps_pattern_metabox', 'aps_pattern_nonce');
        }
        
        $pattern_id = function_exists('get_post_meta') ? get_post_meta($post->ID, '_aps_pattern_id', true) : '';
        $pattern_type = function_exists('get_post_meta') ? get_post_meta($post->ID, '_aps_pattern_type', true) : '';
        $confidence_score = function_exists('get_post_meta') ? get_post_meta($post->ID, '_aps_confidence_score', true) : '';
        $source = function_exists('get_post_meta') ? get_post_meta($post->ID, '_aps_source', true) : '';
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="aps_pattern_id">Pattern ID</label></th>';
        echo '<td><input type="text" id="aps_pattern_id" name="aps_pattern_id" value="' . esc_attr($pattern_id) . '" class="regular-text" readonly /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label for="aps_pattern_type">Pattern Type</label></th>';
        echo '<td>';
        echo '<select id="aps_pattern_type" name="aps_pattern_type">';
        echo '<option value="sequential"' . selected($pattern_type, 'sequential', false) . '>Sequential</option>';
        echo '<option value="statistical"' . selected($pattern_type, 'statistical', false) . '>Statistical</option>';
        echo '<option value="structural"' . selected($pattern_type, 'structural', false) . '>Structural</option>';
        echo '<option value="hybrid"' . selected($pattern_type, 'hybrid', false) . '>Hybrid</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label for="aps_confidence_score">Confidence Score</label></th>';
        echo '<td><input type="number" id="aps_confidence_score" name="aps_confidence_score" value="' . esc_attr($confidence_score) . '" step="0.01" min="0" max="1" class="small-text" /></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label for="aps_source">Source</label></th>';
        echo '<td><input type="text" id="aps_source" name="aps_source" value="' . esc_attr($source) . '" class="regular-text" /></td>';
        echo '</tr>';
        echo '</table>';
    }
    
    public function render_sync_status_metabox($post) {
        $sync_status = function_exists('get_post_meta') ? get_post_meta($post->ID, '_aps_sync_status', true) : 'pending';
        $last_sync = function_exists('get_post_meta') ? get_post_meta($post->ID, '_aps_last_sync', true) : '';
        $sync_sites = function_exists('get_post_meta') ? get_post_meta($post->ID, '_aps_sync_sites', true) : [];
        
        echo '<div class="aps-sync-status">';
        echo '<p><strong>Status:</strong> ';
        
        switch ($sync_status) {
            case 'synced':
                echo '<span class="aps-status-synced">✓ Synced</span>';
                break;
            case 'pending':
                echo '<span class="aps-status-pending">⏳ Pending</span>';
                break;
            case 'error':
                echo '<span class="aps-status-error">✗ Error</span>';
                break;
            default:
                echo '<span class="aps-status-unknown">? Unknown</span>';
        }
        
        echo '</p>';
        
        if ($last_sync) {
            echo '<p><strong>Last Sync:</strong><br>' . esc_html($last_sync) . '</p>';
        }
        
        if (!empty($sync_sites)) {
            echo '<p><strong>Synced Sites:</strong></p>';
            echo '<ul>';
            foreach ($sync_sites as $site_id) {
                $site_name = function_exists('get_blog_details') ? get_blog_details($site_id)->blogname : "Site {$site_id}";
                echo '<li>' . esc_html($site_name) . '</li>';
            }
            echo '</ul>';
        }
        
        echo '<p>';
        echo '<button type="button" class="button button-secondary" id="aps-force-sync">Force Sync</button>';
        echo '</p>';
        
        echo '</div>';
    }
    
    public function render_pattern_chunks_metabox($post) {
        $chunks_data = function_exists('get_post_meta') ? get_post_meta($post->ID, '_aps_chunks_data', true) : [];
        
        echo '<div class="aps-chunks-container">';
        
        if (empty($chunks_data)) {
            echo '<p>No chunks data available.</p>';
        } else {
            echo '<div class="aps-chunks-summary">';
            echo '<p><strong>Total Chunks:</strong> ' . count($chunks_data) . '</p>';
            
            $total_tokens = 0;
            foreach ($chunks_data as $chunk) {
                if (isset($chunk['sequence'])) {
                    $total_tokens += count($chunk['sequence']);
                }
            }
            echo '<p><strong>Total Tokens:</strong> ' . $total_tokens . '</p>';
            echo '</div>';
            
            echo '<div class="aps-chunks-list">';
            echo '<h4>Chunk Details:</h4>';
            
            foreach ($chunks_data as $index => $chunk) {
                echo '<div class="aps-chunk-item">';
                echo '<h5>Chunk ' . ($index + 1) . '</h5>';
                echo '<ul>';
                echo '<li><strong>Chunk ID:</strong> ' . (isset($chunk['chunk_id']) ? esc_html($chunk['chunk_id']) : 'N/A') . '</li>';
                echo '<li><strong>Size:</strong> ' . (isset($chunk['chunk_size']) ? esc_html($chunk['chunk_size']) : 'N/A') . '</li>';
                echo '<li><strong>Overlap:</strong> ' . (isset($chunk['overlap']) ? esc_html($chunk['overlap']) : 'N/A') . '</li>';
                echo '<li><strong>Sequence Length:</strong> ' . (isset($chunk['sequence']) ? count($chunk['sequence']) : 'N/A') . '</li>';
                echo '</ul>';
                echo '</div>';
            }
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    public function render_bloom_integration_metabox($post) {
        $bloom_processed = function_exists('get_post_meta') ? get_post_meta($post->ID, '_aps_bloom_processed', true) : false;
        $bloom_response = function_exists('get_post_meta') ? get_post_meta($post->ID, '_aps_bloom_response', true) : '';
        $bloom_timestamp = function_exists('get_post_meta') ? get_post_meta($post->ID, '_aps_bloom_timestamp', true) : '';
        
        echo '<div class="aps-bloom-status">';
        echo '<p><strong>BLOOM Status:</strong> ';
        
        if ($bloom_processed) {
            echo '<span class="aps-status-processed">✓ Processed</span>';
        } else {
            echo '<span class="aps-status-pending">⏳ Not Processed</span>';
        }
        
        echo '</p>';
        
        if ($bloom_timestamp) {
            echo '<p><strong>Processed At:</strong><br>' . esc_html($bloom_timestamp) . '</p>';
        }
        
        if ($bloom_response) {
            $response_data = json_decode($bloom_response, true);
            if ($response_data) {
                echo '<div class="aps-bloom-response">';
                echo '<h4>BLOOM Response:</h4>';
                echo '<ul>';
                
                if (isset($response_data['confidence'])) {
                    echo '<li><strong>Confidence:</strong> ' . esc_html($response_data['confidence']) . '</li>';
                }
                
                if (isset($response_data['classification'])) {
                    echo '<li><strong>Classification:</strong> ' . esc_html($response_data['classification']) . '</li>';
                }
                
                if (isset($response_data['features'])) {
                    echo '<li><strong>Features:</strong> ' . count($response_data['features']) . ' detected</li>';
                }
                
                echo '</ul>';
                echo '</div>';
            }
        }
        
        echo '<p>';
        echo '<button type="button" class="button button-secondary" id="aps-reprocess-bloom">Reprocess with BLOOM</button>';
        echo '</p>';
        
        echo '</div>';
    }
    
    public function render_pattern_metrics_metabox($post) {
        $metrics = function_exists('get_post_meta') ? get_post_meta($post->ID, '_aps_pattern_metrics', true) : [];
        
        echo '<div class="aps-metrics-container">';
        
        if (empty($metrics)) {
            echo '<p>No metrics data available.</p>';
        } else {
            echo '<table class="widefat">';
            echo '<thead><tr><th>Metric</th><th>Value</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($metrics as $key => $value) {
                echo '<tr>';
                echo '<td>' . esc_html(ucwords(str_replace('_', ' ', $key))) . '</td>';
                echo '<td>' . esc_html($value) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        }
        
        echo '</div>';
    }
    
    public function save_metabox_data($post_id) {
        if (!function_exists('wp_verify_nonce') || !function_exists('current_user_can')) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['aps_pattern_nonce'] ?? '', 'aps_pattern_metabox')) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        $fields_to_save = [
            'aps_pattern_type' => '_aps_pattern_type',
            'aps_confidence_score' => '_aps_confidence_score',
            'aps_source' => '_aps_source'
        ];
        
        foreach ($fields_to_save as $field => $meta_key) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                
                if ($field === 'aps_confidence_score') {
                    $value = floatval($value);
                    $value = max(0, min(1, $value)); // Clamp between 0 and 1
                }
                
                if (function_exists('update_post_meta')) {
                    update_post_meta($post_id, $meta_key, $value);
                }
            }
        }
        
        $this->logger->info("Pattern metabox data saved for post {$post_id}");
    }
    
    public function enqueue_metabox_scripts($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }
        
        global $post;
        if (!$post || $post->post_type !== 'aps_pattern') {
            return;
        }
        
        if (function_exists('wp_enqueue_script') && function_exists('wp_enqueue_style')) {
            wp_enqueue_script(
                'aps-metaboxes',
                plugin_dir_url(__FILE__) . '../../assets/js/metaboxes.js',
                ['jquery'],
                '1.0.0',
                true
            );
            
            wp_enqueue_style(
                'aps-metaboxes',
                plugin_dir_url(__FILE__) . '../../assets/css/metaboxes.css',
                [],
                '1.0.0'
            );
            
            if (function_exists('wp_localize_script')) {
                wp_localize_script('aps-metaboxes', 'apsMetaboxes', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('aps_metabox_ajax'),
                    'postId' => $post->ID
                ]);
            }
        }
    }
    
    public function add_custom_metabox($id, $title, $callback, $screen = 'aps_pattern', $context = 'normal', $priority = 'default') {
        $this->metaboxes[$id] = [
            'title' => $title,
            'callback' => $callback,
            'screen' => $screen,
            'context' => $context,
            'priority' => $priority
        ];
        
        return true;
    }
    
    public function remove_metabox($id) {
        if (isset($this->metaboxes[$id])) {
            unset($this->metaboxes[$id]);
            return true;
        }
        
        return false;
    }
    
    public function get_metaboxes() {
        return $this->metaboxes;
    }
}