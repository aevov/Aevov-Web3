<?php

namespace APSTools\Handlers;

class ChunkImportHandler {
    private static $instance = null;
    private $errors = [];
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Only add WordPress hooks if WordPress is loaded
        if (function_exists('add_action')) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('wp_ajax_aps_import_chunk_csv', [$this, 'handle_chunk_csv_import']);
        }
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'bloom_chunk_page_chunk-csv-import') {
            return;
        }

        wp_enqueue_script(
            'aps-chunk-import',
            APSTOOLS_URL . 'assets/js/chunk-import.js',
            ['jquery'],
            APSTOOLS_VERSION,
            true
        );

        wp_localize_script('aps-chunk-import', 'apsChunkImport', [
            'nonce' => wp_create_nonce('chunk-import-nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php')
        ]);
    }

    public function render_import_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Import Media Chunks from CSV', 'aps-tools'); ?></h1>
            
            <div class="import-instructions">
                <h3><?php _e('CSV Format:', 'aps-tools'); ?></h3>
                <p><?php _e('The CSV file should contain the following columns:', 'aps-tools'); ?></p>
                <ul>
                    <li><strong>ID</strong> - <?php _e('Media attachment ID', 'aps-tools'); ?></li>
                    <li><strong>Title</strong> - <?php _e('Chunk title (SKU)', 'aps-tools'); ?></li>
                    <li><strong>File Name</strong> - <?php _e('JSON file name', 'aps-tools'); ?></li>
                    <li><strong>URLs</strong> - <?php _e('Full URL to the JSON file', 'aps-tools'); ?></li>
                </ul>
            </div>

            <div class="import-form">
                <form method="post" enctype="multipart/form-data" id="chunk-import-form">
                    <?php wp_nonce_field('chunk-import', 'chunk-import-nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="csv_file"><?php _e('CSV File:', 'aps-tools'); ?></label>
                            </th>
                            <td>
                                <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="parent_model"><?php _e('Parent Model:', 'aps-tools'); ?></label>
                            </th>
                            <td>
                                <?php
                                $models = get_posts([
                                    'post_type' => 'bloom_model',
                                    'posts_per_page' => -1,
                                    'orderby' => 'title',
                                    'order' => 'ASC'
                                ]);
                                ?>
                                <select name="parent_model" id="parent_model" required>
                                    <option value=""><?php _e('Select a model', 'aps-tools'); ?></option>
                                    <?php foreach ($models as $model): ?>
                                        <option value="<?php echo esc_attr($model->ID); ?>">
                                            <?php echo esc_html($model->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <div class="import-actions">
                        <button type="submit" class="button button-primary" id="start-import">
                            <?php _e('Start Import', 'aps-tools'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <div id="import-progress" class="hidden">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div class="progress-status"></div>
            </div>

            <div id="import-results" class="hidden">
                <h3><?php _e('Import Results', 'aps-tools'); ?></h3>
                <div class="results-content"></div>
            </div>
        </div>
        <?php
    }

    public function handle_chunk_csv_import() {
        check_ajax_referer('chunk-import-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'aps-tools')]);
        }

        $file = $_FILES['csv_file'] ?? null;
        if (!$file) {
            wp_send_json_error(['message' => __('No file uploaded', 'aps-tools')]);
        }

        $parent_model = intval($_POST['parent_model']);
        if (!$parent_model) {
            wp_send_json_error(['message' => __('Parent model is required', 'aps-tools')]);
        }

        $results = $this->process_chunk_csv($file['tmp_name'], $parent_model);
        wp_send_json_success($results);
    }

    private function process_chunk_csv($file, $parent_model) {
        $results = [
            'success' => [],
            'errors' => [],
            'total' => 0
        ];

        if (($handle = fopen($file, "r")) !== false) {
            // Skip header row
            $headers = fgetcsv($handle);
            
            while (($row = fgetcsv($handle)) !== false) {
                $results['total']++;
                
                try {
                    $chunk_data = [
                        'id' => $row[0], // Media ID
                        'title' => $row[1], // Chunk title/SKU
                        'filename' => $row[2], // JSON filename
                        'url' => $row[3] // Full URL
                    ];

                    $chunk_id = $this->create_chunk_from_data($chunk_data, $parent_model);
                    $results['success'][] = sprintf(
                        __('Created chunk "%s" (ID: %d)', 'aps-tools'),
                        $chunk_data['title'],
                        $chunk_id
                    );

                } catch (\Exception $e) {
                    $results['errors'][] = sprintf(
                        __('Error processing "%s": %s', 'aps-tools'),
                        $chunk_data['title'] ?? $row[1],
                        $e->getMessage()
                    );
                }
            }
            fclose($handle);
        }

        return $results;
    }

    private function create_chunk_from_data($data, $parent_model) {
        // Validate media attachment exists
        $attachment = get_post($data['id']);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            throw new \Exception(__('Media attachment not found', 'aps-tools'));
        }

        // Validate tensor data
        $tensor_data = $this->get_tensor_data($data['url']);
        if (!$tensor_data) {
            throw new \Exception(__('Invalid tensor data in file', 'aps-tools'));
        }

        // Create chunk post
        $chunk_id = wp_insert_post([
            'post_title' => $data['title'],
            'post_type' => 'bloom_chunk',
            'post_status' => 'publish'
        ]);

        if (is_wp_error($chunk_id)) {
            throw new \Exception($chunk_id->get_error_message());
        }

        // Extract tensor name from data
        $tensor_name = key($tensor_data);
        $tensor = reset($tensor_data);

        // Update chunk metadata
        update_post_meta($chunk_id, '_parent_model', $parent_model);
        update_post_meta($chunk_id, '_tensor_name', $tensor_name);
        update_post_meta($chunk_id, '_chunk_sku', $data['title']);
        update_post_meta($chunk_id, '_tensor_dtype', $tensor['dtype']);
        update_post_meta($chunk_id, '_tensor_shape', implode(',', $tensor['shape']));
        update_post_meta($chunk_id, '_chunk_file', $data['filename']);
        update_post_meta($chunk_id, '_chunk_uploaded', current_time('mysql'));
        update_post_meta($chunk_id, '_media_attachment_id', $data['id']);

        return $chunk_id;
    }

    private function get_tensor_data($url) {
        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $content = wp_remote_retrieve_body($response);
        $data = json_decode($content, true);

        if (!$data) {
            throw new \Exception(__('Invalid JSON data', 'aps-tools'));
        }

        return $data;
    }
}