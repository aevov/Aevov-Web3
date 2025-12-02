<?php
/**
 * Plugin Name: BLOOM Chunk Scanner
 * Description: Scans the Media Library for BLOOM JSON chunks and integrates with APS Tools
 * Version: 1.1
 */

// Initialization check
add_action('admin_notices', function() {
    if (get_current_screen()->id === 'toplevel_page_bloom-chunk-scanner') {
        if (!class_exists('BloomChunkScanner')) {
            ?>
            <div class="notice notice-error">
                <p><?php _e('BLOOM Chunk Scanner failed to initialize properly. Please deactivate and reactivate the plugin.'); ?></p>
            </div>
            <?php
        }
    }
});

// Dependency check on activation
register_activation_hook(__FILE__, function() {
    if (!class_exists('APSTools\APSTools')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This plugin requires APS Tools to be installed and activated. Please activate APS Tools and try again.');
    }
});

class BloomChunkScanner {
    private $scanning = false;
    private $progress = 0;
    private $total_files = 0;
    private $processed_files = 0;
    private $found_chunks = 0;
    private $last_scan = null;
    private $error_handler;

    public function __construct() {
        // Include the error handler from the bloom-pattern-recognition plugin
        $error_handler_file = WP_PLUGIN_DIR . '/bloom-pattern-recognition/includes/utilities/class-error-handler.php';
        if (file_exists($error_handler_file)) {
            require_once $error_handler_file;
            if (class_exists('BLOOM\Utilities\ErrorHandler')) {
                $this->error_handler = new \BLOOM\Utilities\ErrorHandler();
            }
        }

        // Core functionality
        add_action('admin_init', [$this, 'setup_database']);
        add_action('add_attachment', [$this, 'handle_new_upload']);
        add_filter('upload_size_limit', [$this, 'increase_upload_size']);
        add_filter('upload_mimes', [$this, 'allow_json_upload']);
        
        // Admin UI
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_start_chunk_scan', function() {
            check_ajax_referer('bloom-scanner-nonce', 'nonce');
            if ($this->error_handler) $this->error_handler->log_debug('start_chunk_scan request data:', $_POST);
            $this->ajax_start_scan();
        });

        add_action('wp_ajax_get_scan_progress', function() {
            check_ajax_referer('bloom-scanner-nonce', 'nonce');
            if ($this->error_handler) $this->error_handler->log_debug('get_scan_progress request data:', $_POST);
            $this->ajax_get_progress();
        });

        add_action('wp_ajax_get_scan_stats', function() {
            check_ajax_referer('bloom-scanner-nonce', 'nonce');
            if ($this->error_handler) $this->error_handler->log_debug('get_scan_stats request data:', $_POST);
            $this->ajax_get_stats();
        });

        // Integration with APS Tools
        add_action('aps_tools_init', [$this, 'integrate_with_aps_tools']);
        
        // Schedule regular scans
        if (!wp_next_scheduled('bloom_chunk_scanner_cron')) {
            wp_schedule_event(time(), 'hourly', 'bloom_chunk_scanner_cron');
        }
        add_action('bloom_chunk_scanner_cron', [$this, 'scheduled_scan']);
    }
    public function setup_database() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bloom_chunks (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sku varchar(255) NOT NULL,
            dtype varchar(50) NOT NULL,
            shape text NOT NULL,
            data longtext NOT NULL,
            file_path varchar(255) NOT NULL,
            upload_date datetime DEFAULT CURRENT_TIMESTAMP,
            processed_date datetime DEFAULT NULL,
            status varchar(50) DEFAULT 'pending',
            PRIMARY KEY  (id),
            UNIQUE KEY sku (sku)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Add options for tracking
        add_option('bloom_chunk_scanner_last_scan', '');
        add_option('bloom_chunk_scanner_stats', [
            'total_scanned' => 0,
            'chunks_found' => 0,
            'last_scan_duration' => 0
        ]);
    }

    public function handle_new_upload($attachment_id) {
        $file = get_attached_file($attachment_id);
        if ($this->is_json_file($file)) {
            $this->process_single_file($file);
            $this->notify_aps_tools($attachment_id);
        }
    }

    private function is_json_file($file) {
        return pathinfo($file, PATHINFO_EXTENSION) === 'json';
    }

    public function increase_upload_size() {
        return 1024 * 1024 * 100; // 100 MB
    }

    public function allow_json_upload($mimes) {
        $mimes['json'] = 'application/json';
        return $mimes;
    }

   public function add_admin_menu() {
    add_menu_page(
        'BLOOM Chunk Scanner',    // Page title
        'BLOOM Scanner',          // Menu title
        'manage_options',         // Capability
        'bloom-chunk-scanner',    // Menu slug
        [$this, 'render_admin_page'], // Callback function
        'dashicons-search',       // Icon (you can change this)
        30                        // Position
    );
}

    public function render_admin_page() {
        $stats = get_option('bloom_chunk_scanner_stats');
        $last_scan = get_option('bloom_chunk_scanner_last_scan');
        ?>
        <div class="wrap">
            <h1>BLOOM Chunk Scanner</h1>
            
            <div class="scanner-dashboard">
                <div class="scanner-stats">
                    <div class="stat-box">
                        <h3>Total Files Scanned</h3>
                        <span class="stat-value" id="total-scanned"><?php echo esc_html($stats['total_scanned']); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3>Chunks Found</h3>
                        <span class="stat-value" id="chunks-found"><?php echo esc_html($stats['chunks_found']); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3>Last Scan</h3>
                        <span class="stat-value" id="last-scan"><?php echo $last_scan ? esc_html(human_time_diff(strtotime($last_scan))) . ' ago' : 'Never'; ?></span>
                    </div>
                </div>

                <div class="scanner-controls">
                    <button id="start-scan" class="button button-primary">Start New Scan</button>
                    <div id="scan-progress" class="progress-bar" style="display: none;">
                        <div class="progress-bar-fill"></div>
                        <div class="progress-text">0%</div>
                    </div>
                </div>

                <div id="scan-log" class="scan-log"></div>
            </div>
        </div>
        <?php
    }

public function enqueue_assets($hook) {
    if ($hook !== 'toplevel_page_bloom-chunk-scanner') {
        return;
    }

    wp_enqueue_style(
        'bloom-scanner-admin',
        plugin_dir_url(__FILE__) . 'assets/css/scanner-admin.css',
        [],
        '1.1'
    );

    wp_enqueue_script(
        'bloom-scanner-admin',
        plugin_dir_url(__FILE__) . 'assets/js/scanner-admin.js',
        ['jquery'],
        '1.1',
        true
    );

    // Make sure nonce is fresh
    $nonce = wp_create_nonce('bloom-scanner-nonce');
    if ($this->error_handler) $this->error_handler->log_debug('Generated nonce:', ['nonce' => $nonce]);

    wp_localize_script('bloom-scanner-admin', 'bloomScanner', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => $nonce,
        'i18n' => [
            'scanStarted' => __('Scan started...'),
            'scanCompleted' => __('Scan completed successfully'),
            'scanError' => __('Error during scan'),
            'clearLog' => __('Log cleared'),
            'exportComplete' => __('Statistics exported successfully')
        ]
    ]);
}
    public function ajax_start_scan() {
    if ($this->error_handler) $this->error_handler->log_debug('AJAX scan request received');

    if (!check_ajax_referer('bloom-scanner-nonce', 'nonce', false)) {
        if ($this->error_handler) $this->error_handler->log_error('Nonce verification failed');
        wp_send_json_error('Security check failed');
        return;
    }

    try {
        if ($this->scanning) {
            if ($this->error_handler) $this->error_handler->log_warning('Scan already in progress');
            wp_send_json_error('Scan already in progress');
            return;
        }

        $this->scanning = true;
        $this->progress = 0;
        $start_time = microtime(true);

        if ($this->error_handler) $this->error_handler->log_info('Starting media library scan');
        $this->scan_media_library();
        
        $duration = microtime(true) - $start_time;
        $this->update_scan_stats($duration);

        $log_message = sprintf(
            'Scan complete. Found %d chunks in %d files.',
            $this->found_chunks,
            $this->processed_files
        );
        if ($this->error_handler) $this->error_handler->log_info($log_message);

        wp_send_json_success([
            'message' => $log_message
        ]);
    } catch (Exception $e) {
        if ($this->error_handler) $this->error_handler->log_error('Scan error', ['exception' => $e->getMessage()]);
        wp_send_json_error($e->getMessage());
    } finally {
        $this->scanning = false;
    }
}

   public function ajax_get_progress() {
    if ($this->error_handler) $this->error_handler->log_debug('Progress check started');
    try {
        wp_send_json_success([
            'scanning' => $this->scanning,
            'progress' => $this->progress,
            'processed' => $this->processed_files,
            'total' => $this->total_files,
            'found_chunks' => $this->found_chunks
        ]);
    } catch (Exception $e) {
        if ($this->error_handler) $this->error_handler->log_error('Progress check error', ['exception' => $e->getMessage()]);
        wp_send_json_error($e->getMessage());
    }
}

    public function ajax_get_stats() {
        if ($this->error_handler) $this->error_handler->log_debug('Stats request started');
        try {
            $stats = get_option('bloom_chunk_scanner_stats', [
                'total_scanned' => 0,
                'chunks_found' => 0,
                'last_scan_duration' => 0
            ]);
            
            $last_scan = get_option('bloom_chunk_scanner_last_scan', '');
            
            wp_send_json_success([
                'total_scanned' => $stats['total_scanned'],
                'chunks_found' => $stats['chunks_found'],
                'last_scan_duration' => $stats['last_scan_duration'],
                'last_scan' => $last_scan,
                'processed' => $this->processed_files,
                'found_chunks' => $this->found_chunks
            ]);
        } catch (Exception $e) {
            if ($this->error_handler) $this->error_handler->log_error('Stats request error', ['exception' => $e->getMessage()]);
            wp_send_json_error($e->getMessage());
        }
    }

    private function scan_media_library() {
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'application/json',
            'posts_per_page' => -1,
        ];

        $query = new WP_Query($args);
        $this->total_files = count($query->posts);
        $this->processed_files = 0;
        $this->found_chunks = 0;

        foreach ($query->posts as $post) {
            $file_path = get_attached_file($post->ID);
            if ($this->process_single_file($file_path)) {
                $this->found_chunks++;
            }
            
            $this->processed_files++;
            $this->progress = ($this->processed_files / $this->total_files) * 100;
            
            // Allow other processes to run
            if ($this->processed_files % 10 === 0) {
                sleep(1);
            }
        }

        $this->last_scan = current_time('mysql');
        update_option('bloom_chunk_scanner_last_scan', $this->last_scan);
    }

    private function process_single_file($file_path) {
        if (!file_exists($file_path)) {
            if ($this->error_handler) $this->error_handler->log_warning('File not found during scan', ['path' => $file_path]);
            return false;
        }

        $chunk_data = json_decode(file_get_contents($file_path), true);
        if (!$this->validate_chunk_data($chunk_data)) {
            if ($this->error_handler) $this->error_handler->log_warning('Invalid chunk data found', ['path' => $file_path]);
            return false;
        }

        return $this->save_chunk_to_db($chunk_data, $file_path);
    }

    private function validate_chunk_data($data) {
        return isset($data['sku']) && 
               isset($data['dtype']) && 
               isset($data['shape']) && 
               isset($data['data']);
    }

    private function save_chunk_to_db($data, $file_path) {
        global $wpdb;
        
        $result = $wpdb->replace(
            $wpdb->prefix . 'bloom_chunks',
            [
                'sku' => $data['sku'],
                'dtype' => $data['dtype'],
                'shape' => json_encode($data['shape']),
                'data' => json_encode($data['data']),
                'file_path' => $file_path,
                'processed_date' => current_time('mysql'),
                'status' => 'processed'
            ],
            [
                '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            ]
        );

        if ($result === false && $this->error_handler) {
            $this->error_handler->log_error('Failed to save chunk to DB', ['sku' => $data['sku'], 'error' => $wpdb->last_error]);
        }

        return $result !== false;
    }

    private function update_scan_stats($duration) {
        $stats = [
            'total_scanned' => $this->processed_files,
            'chunks_found' => $this->found_chunks,
            'last_scan_duration' => round($duration, 2)
        ];
        
        update_option('bloom_chunk_scanner_stats', $stats);
    }

    public function scheduled_scan() {
        if (!$this->scanning) {
            if ($this->error_handler) $this->error_handler->log_info('Starting scheduled scan.');
            $this->scan_media_library();
        }
    }

    private function notify_aps_tools($attachment_id) {
        do_action('bloom_chunk_found', $attachment_id);
    }

    public function integrate_with_aps_tools() {
        add_filter('aps_tools_chunk_sources', [$this, 'register_as_chunk_source']);
        add_action('aps_tools_process_chunks', [$this, 'provide_chunks_to_aps']);
    }

    public function register_as_chunk_source($sources) {
        $sources['media_library'] = [
            'name' => 'Media Library Scanner',
            'description' => 'Scans uploaded JSON files for BLOOM chunks',
            'last_scan' => $this->last_scan,
            'chunks_found' => $this->found_chunks
        ];
        return $sources;
    }

    public function provide_chunks_to_aps($callback) {
        global $wpdb;
        
        $chunks = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}bloom_chunks 
             WHERE status = 'processed'
             ORDER BY upload_date DESC"
        );

        foreach ($chunks as $chunk) {
            call_user_func($callback, json_decode($chunk->data, true));
        }
    }



    private function get_scanner_stats() {
        return [
            'total_scanned' => get_option('bloom_chunk_scanner_total_scanned', 0),
            'chunks_found' => get_option('bloom_chunk_scanner_chunks_found', 0),
            'last_scan' => get_option('bloom_chunk_scanner_last_scan', '')
        ];
    }


    // Ensure this runs at plugin activation
    public static function activate() {
        add_option('bloom_chunk_scanner_total_scanned', 0);
        add_option('bloom_chunk_scanner_chunks_found', 0);
        add_option('bloom_chunk_scanner_last_scan', '');
    }
}

// Initialize the scanner and register activation hook
register_activation_hook(__FILE__, ['BloomChunkScanner', 'activate']);
new BloomChunkScanner();
