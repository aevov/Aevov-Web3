<?php
/**
 * BLOOM Bulk Upload Manager
 */
namespace APSTools\Models;

class BloomBulkUploadManager {
    private static $instance = null;
    private $upload_errors = [];

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Only add WordPress hooks if WordPress is loaded
        if (function_exists('add_action')) {
            add_action('admin_menu', [$this, 'add_bulk_upload_page']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_bulk_upload_assets']);
            add_action('wp_ajax_upload_bloom_chunk', [$this, 'handle_chunk_upload']);
            add_action('admin_notices', [$this, 'display_upload_notices']);
        }
    }

    public function add_bulk_upload_page() {
        add_submenu_page(
            'edit.php?post_type=bloom_chunk',
            __('Bulk Upload Chunks', 'aps-tools'),
            __('Bulk Upload', 'aps-tools'),
            'manage_options',
            'bloom-bulk-upload',
            [$this, 'render_bulk_upload_page']
        );
    }

    public function enqueue_bulk_upload_assets($hook) {
        if ($hook !== 'bloom_chunk_page_bloom-bulk-upload') {
            return;
        }

        wp_enqueue_style(
            'bloom-bulk-upload',
            APSTOOLS_URL . 'assets/css/bulk-upload.css',
            [],
            APSTOOLS_VERSION
        );

        wp_enqueue_script(
            'bloom-bulk-upload',
            APSTOOLS_URL . 'assets/js/bulk-chunk-upload.js',
            ['jquery', 'underscore'],
            APSTOOLS_VERSION,
            true
        );

        wp_localize_script('bloom-bulk-upload', 'bloomChunkAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bloom-bulk-upload'),
            'maxFileSize' => wp_max_upload_size(),
            'maxFiles' => 50, // Maximum files per upload
            'i18n' => [
                'uploadError' => __('Error uploading chunk', 'aps-tools'),
                'invalidJson' => __('Invalid JSON file', 'aps-tools'),
                'uploadComplete' => __('Upload complete', 'aps-tools'),
                'fileTooLarge' => __('File is too large', 'aps-tools'),
                'processingFile' => __('Processing file', 'aps-tools'),
                'uploadingFile' => __('Uploading file', 'aps-tools'),
                'tooManyFiles' => __('Too many files selected', 'aps-tools'),
                'selectModel' => __('Please select a parent model', 'aps-tools'),
                'confirmClear' => __('Are you sure you want to clear all files?', 'aps-tools')
            ]
        ]);
    }

    public function render_bulk_upload_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'aps-tools'));
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Bulk Upload Chunks', 'aps-tools'); ?></h1>
            <a href="<?php echo admin_url('edit.php?post_type=bloom_chunk'); ?>" class="page-title-action">
                <?php _e('← Back to Chunks', 'aps-tools'); ?>
            </a>
            <hr class="wp-header-end">

            <div class="bulk-upload-container">
                <div class="bulk-upload-header">
                    <p class="description">
                        <?php _e('Upload multiple BLOOM chunk files at once. Files must be in JSON format and contain valid tensor data.', 'aps-tools'); ?>
                    </p>
                </div>

                <div class="upload-zone">
                    <div id="drop-zone" class="drop-zone">
                        <div class="drop-zone-content">
                            <span class="dashicons dashicons-upload"></span>
                            <p><?php _e('Drag and drop JSON chunk files here', 'aps-tools'); ?></p>
                            <p><?php _e('or', 'aps-tools'); ?></p>
                            <input type="file" id="chunk-files" multiple accept=".json" class="file-input" />
                            <label for="chunk-files" class="button button-primary">
                                <?php _e('Select Files', 'aps-tools'); ?>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="model-selection">
                    <label for="parent-model"><?php _e('Parent Model:', 'aps-tools'); ?></label>
                    <select id="parent-model" required>
                        <option value=""><?php _e('Select a model', 'aps-tools'); ?></option>
                        <?php
                        $models = get_posts([
                            'post_type' => 'bloom_model',
                            'posts_per_page' => -1,
                            'orderby' => 'title',
                            'order' => 'ASC'
                        ]);
                        foreach ($models as $model): ?>
                            <option value="<?php echo esc_attr($model->ID); ?>">
                                <?php echo esc_html($model->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="file-preview-list" class="file-preview-list"></div>

                <div class="upload-actions">
                    <button id="start-upload" class="button button-primary" disabled>
                        <?php _e('Upload All', 'aps-tools'); ?>
                    </button>
                    <button id="clear-files" class="button button-secondary" disabled>
                        <?php _e('Clear All', 'aps-tools'); ?>
                    </button>
                </div>

                <div id="upload-progress" class="upload-progress"></div>
            </div>
        </div>

        <script type="text/template" id="file-preview-template">
            <div class="file-preview" data-index="<%- index %>">
                <div class="file-preview-header">
                    <span class="file-name"><%- fileName %></span>
                    <button type="button" class="remove-file button-link">
                        <span class="dashicons dashicons-dismiss"></span>
                    </button>
                </div>
                <div class="file-details">
                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Tensor:', 'aps-tools'); ?></span>
                        <span class="detail-value"><%- tensorName %></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><?php _e('SKU:', 'aps-tools'); ?></span>
                        <span class="detail-value"><%- sku %></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Shape:', 'aps-tools'); ?></span>
                        <span class="detail-value"><%- shape.join(' × ') %></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Type:', 'aps-tools'); ?></span>
                        <span class="detail-value"><%- dtype %></span>
                    </div>
                    <% if (isPartial) { %>
                    <div class="detail-row">
                        <span class="detail-label"><?php _e('Part:', 'aps-tools'); ?></span>
                        <span class="detail-value"><%- partNumber + 1 %> of <%- totalParts %></span>
                    </div>
                    <% } %>
                </div>
                <div class="upload-status"></div>
            </div>
        </script>
        <?php
    }

    public function handle_chunk_upload() {
        check_ajax_referer('bloom-bulk-upload', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'aps-tools')]);
        }

        $model_id = intval($_POST['model_id']);
        $tensor_name = sanitize_text_field($_POST['tensor_name']);
        $chunk_data = json_decode(stripslashes($_POST['chunk_data']), true);

        if (!$model_id || !$tensor_name || !$chunk_data) {
            wp_send_json_error(['message' => __('Invalid request data', 'aps-tools')]);
        }

        try {
            // Create new chunk post
            $chunk_post = [
                'post_title' => $tensor_name,
                'post_type' => 'bloom_chunk',
                'post_status' => 'publish'
            ];

            $chunk_id = wp_insert_post($chunk_post);

            if (is_wp_error($chunk_id)) {
                throw new Exception($chunk_id->get_error_message());
            }

            // Handle file upload
            if (!isset($_FILES['chunk_file'])) {
                throw new Exception(__('No file uploaded', 'aps-tools'));
            }

            $upload_dir = $this->get_upload_dir();
            $file = $_FILES['chunk_file'];
            $chunk_sku = $chunk_data['sku'];
            $filename = $upload_dir . '/' . sanitize_file_name($chunk_sku . '.json');

            if (!move_uploaded_file($file['tmp_name'], $filename)) {
                throw new Exception(__('Failed to save chunk file', 'aps-tools'));
            }

            // Update chunk metadata
            $this->update_chunk_metadata($chunk_id, $model_id, $tensor_name, $chunk_data, $filename);

            // Update model's chunk count
            $this->update_model_chunk_count($model_id);

            wp_send_json_success([
                'chunk_id' => $chunk_id,
                'message' => sprintf(
                    __('Chunk "%s" uploaded successfully', 'aps-tools'),
                    $tensor_name
                )
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    private function update_chunk_metadata($chunk_id, $model_id, $tensor_name, $chunk_data, $filename) {
        $metadata = [
            '_parent_model' => $model_id,
            '_tensor_name' => $tensor_name,
            '_chunk_sku' => $chunk_data['sku'],
            '_tensor_dtype' => $chunk_data['dtype'],
            '_tensor_shape' => implode(',', $chunk_data['shape']),
            '_chunk_file' => $filename,
            '_chunk_uploaded' => current_time('mysql')
        ];

        if (isset($chunk_data['is_partial']) && $chunk_data['is_partial']) {
            $metadata['_is_partial'] = true;
            $metadata['_part_number'] = $chunk_data['part'];
            $metadata['_total_parts'] = $chunk_data['total_parts'];
        }

        foreach ($metadata as $key => $value) {
            update_post_meta($chunk_id, $key, $value);
        }
    }
    
    private function store_chunk($chunk_data) {
    global $wpdb;
    
    $result = $wpdb->insert(
        $wpdb->prefix . 'aps_chunks',
        [
            'sku' => $chunk_data['sku'],
            'chunk_data' => json_encode($chunk_data),
            'uploaded_at' => current_time('mysql')
        ]
    );

    if ($result === false) {
        return new \WP_Error('db_error', 'Failed to store chunk');
    }

    return [
        'sku' => $chunk_data['sku'],
        'size' => strlen(json_encode($chunk_data)),
        'uploaded_at' => current_time('mysql')
    ];
}

    private function update_model_chunk_count($model_id) {
        $uploaded_chunks = get_posts([
            'post_type' => 'bloom_chunk',
            'meta_key' => '_parent_model',
            'meta_value' => $model_id,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);

        update_post_meta($model_id, '_uploaded_chunks', count($uploaded_chunks));
    }

    private function get_upload_dir() {
        $upload_dir = wp_upload_dir();
        $chunks_dir = $upload_dir['basedir'] . '/bloom-chunks';
        
        if (!file_exists($chunks_dir)) {
            wp_mkdir_p($chunks_dir);
        }
        
        return $chunks_dir;
    }

    public function display_upload_notices() {
        if (!empty($this->upload_errors)) {
            foreach ($this->upload_errors as $error) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
            }
            $this->upload_errors = [];
        }
    }
}

// Initialize the class - only if WordPress is loaded
if (function_exists('add_action')) {
    add_action('plugins_loaded', function() {
        BloomBulkUploadManager::instance();
    });
}