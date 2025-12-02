<?php
namespace APSTools\Scanner;

/**
 * Handles directory scanning operations
 */
class DirectoryScanner {
    private $files = [];
    private $batch_size = 50;
    private $processed_files = [];
    private $errors = [];
    
    /**
     * Scan directory for JSON files
     */
    public function scan_directory($directory_path, $options = []) {
        $options = wp_parse_args($options, [
            'recursive' => true,
            'file_pattern' => '*.json',
            'batch_size' => $this->batch_size
        ]);

        if (!is_dir($directory_path)) {
            throw new \Exception('Invalid directory path');
        }

        $this->batch_size = $options['batch_size'];
        $this->files = [];
        
        $iterator = $options['recursive'] 
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory_path))
            : new \DirectoryIterator($directory_path);

        foreach ($iterator as $file) {
            if ($file->isFile() && $this->matches_pattern($file->getPathname(), $options['file_pattern'])) {
                $this->files[] = [
                    'path' => $file->getPathname(),
                    'status' => 'pending'
                ];
            }
        }

        return $this->files;
    }

    /**
     * Get next batch of files to process
     */
    public function get_batch() {
        $pending_files = array_filter($this->files, function($file) {
            return $file['status'] === 'pending';
        });

        return array_slice($pending_files, 0, $this->batch_size);
    }

    /**
     * Update file processing status
     */
    public function update_file_status($file_path, $status, $error = '') {
        foreach ($this->files as &$file) {
            if ($file['path'] === $file_path) {
                $file['status'] = $status;
                if ($status === 'completed') {
                    $this->processed_files[] = $file_path;
                } elseif ($status === 'failed') {
                    $this->errors[] = [
                        'file' => basename($file_path),
                        'error' => $error
                    ];
                }
                break;
            }
        }
    }

    /**
     * Get scan statistics
     */
    public function get_scan_stats() {
        $total = count($this->files);
        $processed = count($this->processed_files);
        $failed = count($this->errors);
        
        return [
            'total' => $total,
            'processed' => $processed,
            'failed' => $failed,
            'pending' => $total - ($processed + $failed),
            'progress' => $total > 0 ? round(($processed + $failed) / $total * 100) : 0
        ];
    }

    /**
     * Get processing errors
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Check if file matches pattern
     */
    private function matches_pattern($filename, $pattern) {
        return fnmatch($pattern, basename($filename));
    }
}

/**
 * Handles AJAX requests for the directory scanner
 */
class ScannerAjaxHandler {
    private $batch_processor;
    
    public function __construct() {
        $this->batch_processor = new BatchProcessor();
        
        add_action('wp_ajax_aps_start_scan', [$this, 'handle_start_scan']);
        add_action('wp_ajax_aps_stop_scan', [$this, 'handle_stop_scan']);
        add_action('wp_ajax_aps_get_scan_status', [$this, 'handle_get_status']);
        add_action('wp_ajax_aps_get_models_by_category', [$this, 'handle_get_models']);
    }

    public function handle_start_scan() {
        check_ajax_referer('aps-tools-nonce', 'nonce');
        
        try {
            $directory = sanitize_text_field($_POST['directory']);
            $model_id = intval($_POST['model']);
            $category_id = intval($_POST['category']);
            $options = [
                'batch_size' => intval($_POST['batch_size']),
                'recursive' => (bool) $_POST['recursive']
            ];

            $result = $this->batch_processor->process_directory($directory, $model_id, $category_id, $options);
            wp_send_json_success($result);
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function handle_stop_scan() {
        check_ajax_referer('aps-tools-nonce', 'nonce');
        
        $this->batch_processor->stop();
        wp_send_json_success();
    }

    public function handle_get_status() {
        check_ajax_referer('aps-tools-nonce', 'nonce');
        
        $status = $this->batch_processor->get_status();
        wp_send_json_success($status);
    }
    
    public function handle_get_models() {
        check_ajax_referer('aps-tools-nonce', 'nonce');
        
        $category_id = intval($_POST['category']);
        
        $models = get_posts([
            'post_type' => 'bloom_model',
            'tax_query' => [[
                'taxonomy' => 'model_category',
                'field' => 'term_id',
                'terms' => $category_id
            ]],
            'posts_per_page' => -1
        ]);
        
        wp_send_json_success($models);
    }
}

/**
 * Manages admin interface for directory scanner
 */
class ScannerAdmin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_menu_page() {
        add_submenu_page(
            'aps-dashboard',
            __('Directory Scanner', 'aps-tools'),
            __('Directory Scanner', 'aps-tools'),
            'manage_options',
            'aps-directory-scanner',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        include APSTOOLS_PATH . 'templates/admin/directory-scanner.php';
    }
    
    public function get_all_files() {
    return $this->files;
}

    public function enqueue_assets($hook) {
        if ($hook !== 'pattern-system_page_aps-directory-scanner') {
            return;
        }

        wp_enqueue_script(
            'aps-directory-scanner',
            APSTOOLS_URL . 'assets/js/directory-scanner.js',
            ['jquery', 'underscore'],
            APSTOOLS_VERSION,
            true
        );

        wp_localize_script('aps-directory-scanner', 'apsScanner', [
            'nonce' => wp_create_nonce('aps-tools-nonce'),
            'i18n' => [
                'error' => __('Error', 'aps-tools'),
                'scanning' => __('Scanning...', 'aps-tools'),
                'complete' => __('Scan complete', 'aps-tools')
            ]
        ]);
    }
}

