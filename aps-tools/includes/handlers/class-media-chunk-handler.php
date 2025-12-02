<?php
namespace APSTools\Handlers;

class MediaChunkHandler {
    private $tensor_storage;
    private $log_table = 'aps_chunk_processing_log';

    public function __construct() {
        $this->tensor_storage = \APSTools\Models\BloomTensorStorage::instance();
        
        // Only create table and add hooks if WordPress is loaded
        if (function_exists('add_action')) {
            $this->create_log_table();
            add_action('add_attachment', [$this, 'check_for_bloom_chunk']);
            add_action('admin_menu', [$this, 'add_reports_page']);
            add_action('aps_check_chunks', [$this, 'cron_check_chunks']);
            
            if (function_exists('wp_next_scheduled') && !wp_next_scheduled('aps_check_chunks')) {
                if (function_exists('wp_schedule_event')) {
                    wp_schedule_event(time(), 'hourly', 'aps_check_chunks');
                }
            }
        }
    }

    private function create_log_table() {
        global $wpdb;
        
        // Only create table if WordPress database is available
        if (!$wpdb) {
            return;
        }
        
        $table = $wpdb->prefix . $this->log_table;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            chunk_sku varchar(255) NOT NULL,
            status varchar(50) NOT NULL,
            message text,
            attachment_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY chunk_sku (chunk_sku),
            KEY status (status),
            KEY attachment_id (attachment_id)
        ) $charset_collate;";

        if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    public function check_for_bloom_chunk($attachment_id) {
    $file = get_attached_file($attachment_id);
    if (!$file || !file_exists($file)) {
        return;
    }

    if (pathinfo($file, PATHINFO_EXTENSION) !== 'json') {
        return;
    }

    try {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        if (!$data) {
            throw new \Exception('Invalid JSON content');
        }

        if (!$this->is_valid_chunk($data)) {
            throw new \Exception('Invalid chunk format');
        }

        $chunk_sku = $data['sku'] ?? sanitize_title(basename($file, '.json'));
        
        if ($this->chunk_exists($chunk_sku)) {
            throw new \Exception('Duplicate chunk SKU detected');
        }

        $model_id = isset($_POST['bloom_model_id']) ? intval($_POST['bloom_model_id']) : 0;

        if (!$model_id) {
            $model_id = $this->detect_model_from_filename($file);
        }

        update_post_meta($attachment_id, '_is_bloom_chunk', true);
        update_post_meta($attachment_id, '_tensor_sku', $chunk_sku);
        update_post_meta($attachment_id, '_tensor_type', $data['dtype'] ?? '');

        if ($model_id) {
            update_post_meta($attachment_id, '_parent_model', $model_id);
        }

        $this->tensor_storage->store_tensor_data($attachment_id, $file);
        $this->log_processing('success', $chunk_sku, 'Chunk processed successfully', $attachment_id);

    } catch (\Exception $e) {
        $this->log_processing('error', $data['sku'] ?? 'unknown', $e->getMessage(), $attachment_id);
    }
}

    private function log_processing($status, $sku, $message, $attachment_id = null) {
        global $wpdb;
        
        // Only log if WordPress database is available
        if (!$wpdb) {
            return;
        }
        
        $wpdb->insert(
            $wpdb->prefix . $this->log_table,
            [
                'chunk_sku' => $sku,
                'status' => $status,
                'message' => $message,
                'attachment_id' => $attachment_id
            ]
        );
    }

    public function add_reports_page() {
        add_submenu_page(
            'tools.php',
            'Chunk Processing Report',
            'Chunk Reports',
            'manage_options',
            'chunk-processing-report',
            [$this, 'render_reports_page']
        );
    }

    public function render_reports_page() {
        global $wpdb;
        $table = $wpdb->prefix . $this->log_table;
        $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100");

        ?>
        <div class="wrap">
            <h1>Chunk Processing Report</h1>
            
            <div class="tablenav top">
                <div class="tablenav-pages">
                    <span class="displaying-num">Last 100 entries</span>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Chunk SKU</th>
                        <th>Status</th>
                        <th>Message</th>
                        <th>Attachment</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log->chunk_sku); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($log->status); ?>">
                                    <?php echo esc_html($log->status); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log->message); ?></td>
                            <td>
                                <?php if ($log->attachment_id): ?>
                                    <a href="<?php echo esc_url(get_edit_post_link($log->attachment_id)); ?>" target="_blank">
                                        <?php echo esc_html(get_the_title($log->attachment_id)); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($log->created_at); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <style>
        .status-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-success { background: #d4edda; color: #155724; }
        .status-error { background: #f8d7da; color: #721c24; }
        .status-duplicate { background: #fff3cd; color: #856404; }
        .status-invalid { background: #f8d7da; color: #721c24; }
        </style>
        <?php
    }

    public function cron_check_chunks() {
        $chunks = get_posts([
            'post_type' => 'attachment',
            'meta_key' => '_is_bloom_chunk',
            'meta_value' => '1',
            'posts_per_page' => -1
        ]);

        foreach ($chunks as $chunk) {
            $sku = get_post_meta($chunk->ID, '_tensor_sku', true);
            if (!$sku) continue;

            if (!$this->verify_chunk_data($chunk->ID)) {
                $this->log_processing('error', $sku, 'Chunk data verification failed during cron check', $chunk->ID);
            }
        }
    }

    private function verify_chunk_data($attachment_id) {
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) return false;

        try {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            return $this->is_valid_chunk($data);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function chunk_exists($sku) {
        global $wpdb;
        
        // Return false if WordPress database is not available
        if (!$wpdb) {
            return false;
        }
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
            WHERE meta_key = '_tensor_sku' AND meta_value = %s",
            $sku
        )) > 0;
    }

    private function is_valid_chunk($data) {
        return is_array($data) && 
               isset($data['dtype']) && 
               isset($data['shape']) &&
               isset($data['data']);
    }

    private function detect_model_from_filename($file) {
        $filename = strtolower(basename($file));
        $models = $this->get_available_models();
        
        foreach ($models as $model) {
            $model_name = strtolower($model->post_title);
            if (strpos($filename, $model_name) !== false) {
                return $model->ID;
            }
        }
        
        return null;
    }

    private function get_available_models() {
        return get_posts([
            'post_type' => 'bloom_model',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
    }
}

// Only initialize if WordPress is loaded
if (function_exists('add_action')) {
    add_action('init', function() {
        new \APSTools\Handlers\MediaChunkHandler();
    });
}