<?php
/**
 * Plugin Name: APS Chunk Uploader
 * Description: Enhances the media uploader for APS Tools to allow model selection and chunk validation
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: aps-chunk-uploader
 * Requires PHP: 7.4
 */

namespace APSChunkUploader;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ChunkUploaderPlugin {
    private $dependencies_loaded = false;
    
    public function __construct() {
        add_action('plugins_loaded', [$this, 'init'], 20);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    public function init() {
        // Check dependencies first
        if (!$this->check_dependencies()) {
            add_action('admin_notices', [$this, 'dependency_notice']);
            return;
        }
        
        $this->dependencies_loaded = true;
        
        // Initialize hooks only after dependencies are confirmed
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_filter('plupload_init', [$this, 'modify_upload_params']);
        add_action('wp_ajax_aps_get_model_info', [$this, 'get_model_info']);
        add_action('pre-upload-ui', [$this, 'render_model_selector']);
    }
    
    private function check_dependencies() {
        // Multiple methods to check if APS Tools is active
        $aps_tools_active = false;
        
        // Method 1: Check if function exists
        if (function_exists('aps_tools_get_available_patterns')) {
            $aps_tools_active = true;
        }
        
        // Method 2: Check if class exists
        if (!$aps_tools_active && class_exists('APSTools\\APSTools')) {
            $aps_tools_active = true;
        }
        
        // Method 3: Check if plugin is active (with proper path)
        if (!$aps_tools_active && function_exists('is_plugin_active')) {
            if (is_plugin_active('aps-tools/aps-tools.php')) {
                $aps_tools_active = true;
            }
        }
        
        // Method 4: Check if plugin file exists and is loaded
        if (!$aps_tools_active) {
            $plugin_file = WP_PLUGIN_DIR . '/aps-tools/aps-tools.php';
            if (file_exists($plugin_file) && in_array($plugin_file, get_included_files())) {
                $aps_tools_active = true;
            }
        }
        
        if (!$aps_tools_active) {
            return false;
        }
        
        // Multiple methods to check if BLOOM Pattern Recognition is active
        $bloom_active = false;
        
        // Method 1: Check if namespace/class exists
        if (class_exists('BLOOM\\Core\\BloomPatternSystem') || class_exists('BLOOM_APS_Integration')) {
            $bloom_active = true;
        }
        
        // Method 2: Check if plugin is active (try multiple possible paths)
        if (!$bloom_active && function_exists('is_plugin_active')) {
            $possible_paths = [
                'bloom-pattern-recognition/bloom-pattern-system.php',
                'bloom-pattern-recognition/bloom-pattern-recognition.php',
                'bloom-pattern-recognition/index.php'
            ];
            
            foreach ($possible_paths as $path) {
                if (is_plugin_active($path)) {
                    $bloom_active = true;
                    break;
                }
            }
        }
        
        // Method 3: Check if plugin files exist and are loaded
        if (!$bloom_active) {
            $possible_files = [
                WP_PLUGIN_DIR . '/bloom-pattern-recognition/bloom-pattern-system.php',
                WP_PLUGIN_DIR . '/bloom-pattern-recognition/bloom-pattern-recognition.php',
                WP_PLUGIN_DIR . '/bloom-pattern-recognition/index.php'
            ];
            
            $included_files = get_included_files();
            foreach ($possible_files as $file) {
                if (file_exists($file) && in_array($file, $included_files)) {
                    $bloom_active = true;
                    break;
                }
            }
        }
        
        if (!$bloom_active) {
            return false;
        }
        
        // Check if bloom_model post type exists (this is a good indicator)
        if (!post_type_exists('bloom_model')) {
            // Give it a moment for post types to register
            if (did_action('init')) {
                return false;
            }
        }
        
        return true;
    }
    
    public function dependency_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('APS Chunk Uploader Error:', 'aps-chunk-uploader'); ?></strong>
                <?php _e('This plugin requires APS Tools and BLOOM Pattern Recognition to be active.', 'aps-chunk-uploader'); ?>
            </p>
        </div>
        <?php
    }
    
    public function activate() {
        // During activation, be more lenient with dependency checking
        // since plugins may not be fully loaded yet
        $aps_tools_exists = false;
        $bloom_exists = false;
        
        // Check if APS Tools plugin file exists
        if (file_exists(WP_PLUGIN_DIR . '/aps-tools/aps-tools.php')) {
            $aps_tools_exists = true;
        }
        
        // Check if BLOOM plugin files exist
        $bloom_files = [
            WP_PLUGIN_DIR . '/bloom-pattern-recognition/bloom-pattern-system.php',
            WP_PLUGIN_DIR . '/bloom-pattern-recognition/bloom-pattern-recognition.php',
            WP_PLUGIN_DIR . '/bloom-pattern-recognition/index.php'
        ];
        
        foreach ($bloom_files as $file) {
            if (file_exists($file)) {
                $bloom_exists = true;
                break;
            }
        }
        
        if (!$aps_tools_exists || !$bloom_exists) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('APS Chunk Uploader requires APS Tools and BLOOM Pattern Recognition plugins to be installed.', 'aps-chunk-uploader'));
        }
    }
    
    public function deactivate() {
        // Clean up any temporary data if needed
    }

    public function enqueue_scripts($hook) {
        if (!$this->dependencies_loaded) {
            return;
        }
        
        if (!in_array($hook, ['media-new.php', 'upload.php'])) {
            return;
        }

        // Check if script file exists before enqueuing
        $script_path = plugin_dir_path(__FILE__) . 'assets/js/chunk-uploader.js';
        if (!file_exists($script_path)) {
            // Create inline script as fallback
            $this->enqueue_inline_script();
            return;
        }

        wp_enqueue_script(
            'aps-chunk-uploader',
            plugins_url('assets/js/chunk-uploader.js', __FILE__),
            ['jquery', 'plupload-handlers'],
            '1.0.0',
            true
        );

        wp_localize_script('aps-chunk-uploader', 'apsChunkUploader', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aps-chunk-uploader'),
            'i18n' => [
                'selectModel' => __('Please select a model first', 'aps-chunk-uploader'),
                'uploadError' => __('Upload error occurred', 'aps-chunk-uploader'),
                'modelInfo' => __('Model information loaded', 'aps-chunk-uploader')
            ]
        ]);
    }
    
    private function enqueue_inline_script() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#aps-model-select').on('change', function() {
                var modelId = $(this).val();
                if (modelId) {
                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'aps_get_model_info',
                        model_id: modelId,
                        nonce: '<?php echo wp_create_nonce('aps-chunk-uploader'); ?>'
                    }, function(response) {
                        if (response.success) {
                            $('#aps-model-info').html('<p><strong>' + response.data.title + '</strong> - ' + response.data.chunks + ' chunks</p>');
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }

    public function modify_upload_params($params) {
        if (!$this->dependencies_loaded) {
            return $params;
        }
        
        $params['multipart_params']['bloom_model_id'] = isset($_POST['bloom_model_id']) ? intval($_POST['bloom_model_id']) : 0;
        return $params;
    }

    public function get_model_info() {
        if (!$this->dependencies_loaded) {
            wp_send_json_error(__('Dependencies not loaded', 'aps-chunk-uploader'));
        }
        
        check_ajax_referer('aps-chunk-uploader', 'nonce');
        
        $model_id = isset($_POST['model_id']) ? intval($_POST['model_id']) : 0;
        
        if (!$model_id) {
            wp_send_json_error(__('Invalid model ID', 'aps-chunk-uploader'));
        }

        $model = get_post($model_id);

        if (!$model || $model->post_type !== 'bloom_model') {
            wp_send_json_error(__('Model not found or invalid type', 'aps-chunk-uploader'));
        }

        wp_send_json_success([
            'title' => $model->post_title,
            'chunks' => $this->get_model_chunk_count($model_id),
            'status' => get_post_meta($model_id, '_model_status', true) ?: 'unknown'
        ]);
    }

    private function get_model_chunk_count($model_id) {
        global $wpdb;
        
        // Try multiple methods to count chunks
        $count = 0;
        
        // Method 1: Check postmeta for parent model relationship
        $count = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = '_parent_model' AND meta_value = %d",
            $model_id
        ));
        
        // Method 2: If no results, try checking for bloom_chunk posts
        if ($count === 0) {
            $count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = 'bloom_chunk'
                 AND pm.meta_key = '_model_id'
                 AND pm.meta_value = %d",
                $model_id
            ));
        }
        
        // Method 3: Check custom table if it exists
        if ($count === 0) {
            $table_name = $wpdb->prefix . 'bloom_chunks';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            
            if ($table_exists) {
                $count = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE model_id = %d",
                    $model_id
                ));
            }
        }
        
        return $count;
    }

    public function render_model_selector() {
        if (!$this->dependencies_loaded) {
            echo '<div class="notice notice-warning"><p>' . __('Model selector unavailable - dependencies not loaded.', 'aps-chunk-uploader') . '</p></div>';
            return;
        }
        
        $models = $this->get_available_models();
        
        if (empty($models)) {
            ?>
            <div class="aps-model-selection" style="margin: 10px 0;">
                <p class="description" style="color: #d63638;">
                    <?php _e('No BLOOM models found. Please create models first using the BLOOM Pattern Recognition plugin.', 'aps-chunk-uploader'); ?>
                </p>
            </div>
            <?php
            return;
        }
        ?>
        <div class="aps-model-selection" style="margin: 10px 0; padding: 15px; background: #f8f9fa; border: 1px solid #e2e4e7; border-radius: 4px;">
            <label for="aps-model-select" style="font-weight: 600;">
                <?php _e('Select BLOOM Model:', 'aps-chunk-uploader'); ?>
            </label>
            <select id="aps-model-select" name="bloom_model_id" style="margin-left: 10px; min-width: 200px;">
                <option value=""><?php _e('Select Model', 'aps-chunk-uploader'); ?></option>
                <?php foreach ($models as $model): ?>
                    <option value="<?php echo esc_attr($model->ID); ?>">
                        <?php echo esc_html($model->post_title); ?>
                        (ID: <?php echo esc_html($model->ID); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description" style="margin-top: 8px;">
                <?php _e('Select the model for uploaded BLOOM chunks. This will associate uploaded files with the selected model.', 'aps-chunk-uploader'); ?>
            </p>
            <div id="aps-model-info" style="margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 3px; display: none;"></div>
        </div>
        <?php
    }

    private function get_available_models() {
        if (!post_type_exists('bloom_model')) {
            return [];
        }
        
        $models = get_posts([
            'post_type' => 'bloom_model',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish'
        ]);
        
        return is_array($models) ? $models : [];
    }
}

new ChunkUploaderPlugin();