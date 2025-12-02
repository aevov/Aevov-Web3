<?php
namespace APSTools\Handlers;

class TableHandler {
    private static $instance = null;
    private $data = [];
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Only add WordPress hooks if WordPress is loaded
        if (function_exists('add_action')) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_handsontable']);
            add_action('wp_ajax_aps_save_table_data', [$this, 'save_table_data']);
            add_action('wp_ajax_aps_get_table_data', [$this, 'get_table_data']);
        }
    }

    public function enqueue_handsontable($hook) {
        if (strpos($hook, 'aps-') === false) {
            return;
        }

        // Enqueue HandsontableJS 
        wp_enqueue_script(
            'handsontable',
            'https://cdn.jsdelivr.net/npm/handsontable@latest/dist/handsontable.full.min.js',
            [],
            null
        );

        wp_enqueue_style(
            'handsontable',
            'https://cdn.jsdelivr.net/npm/handsontable@latest/dist/handsontable.full.min.css',
            [],
            null
        );

        // Enqueue custom handler
        wp_enqueue_script(
            'aps-table-handler',
            APSTOOLS_URL . 'assets/js/table-handler.js',
            ['handsontable', 'jquery'],
            APSTOOLS_VERSION,
            true
        );

        wp_localize_script('aps-table-handler', 'apsTable', [
            'nonce' => wp_create_nonce('aps-table-nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php')
        ]);
    }

    public function save_table_data() {
        check_ajax_referer('aps-table-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $data = json_decode(stripslashes($_POST['data']), true);
        $this->data = $data;
        
        update_option('aps_table_data', $data);
        wp_send_json_success();
    }

    public function get_table_data() {
        check_ajax_referer('aps-table-nonce', 'nonce');
        
        $data = get_option('aps_table_data', []);
        wp_send_json_success($data);
    }

    public function render_table($id = 'aps-data-table') {
        ?>
        <div id="<?php echo esc_attr($id); ?>-container" class="aps-table-container">
            <div id="<?php echo esc_attr($id); ?>" class="hot-table"></div>
            <div class="table-actions">
                <button type="button" class="button save-table" data-table="<?php echo esc_attr($id); ?>">
                    <?php _e('Save Changes', 'aps-tools'); ?>
                </button>
                <button type="button" class="button process-selected" data-table="<?php echo esc_attr($id); ?>">
                    <?php _e('Process Selected', 'aps-tools'); ?>
                </button>
            </div>
        </div>
        <?php
    }
}