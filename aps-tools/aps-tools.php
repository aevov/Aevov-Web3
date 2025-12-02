<?php
/**
 * Plugin Name: APS Tools
 * Description: Management interface for APS and BLOOM integration
 * Version: 1.0.0
 * Text Domain: aps-tools
 */

namespace APSTools;

class APSTools {
    private static $instance = null;
    private $admin;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

     private function __construct() {
        $this->define_constants();
        
        // Only add WordPress hooks if WordPress is loaded
        if (function_exists('add_action')) {
            add_action('plugins_loaded', [$this, 'initialize']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('wp_ajax_aps_start_scan', [$this, 'handle_start_scan']);
            add_action('wp_ajax_aps_get_scan_status', [$this, 'handle_get_scan_status']);
            add_action('wp_ajax_aps_stop_scan', [$this, 'handle_stop_scan']);
            add_action('wp_ajax_aps_get_models_by_category', [$this, 'handle_get_models']);
        add_action('wp_ajax_get_system_metrics', [$this, 'handle_get_system_metrics']);
            add_action('init', [$this, 'load_textdomain']); // Add this line
        }
    }

    public function load_textdomain() {
        load_plugin_textdomain('aps-tools', false, APSTOOLS_PATH . 'languages');
    }
    
    

    private function define_constants() {
        define('APSTOOLS_VERSION', '1.0.0');
        define('APSTOOLS_FILE', __FILE__);
        
        // Use WordPress functions only if available, otherwise use fallbacks
        if (function_exists('plugin_dir_path')) {
            define('APSTOOLS_PATH', plugin_dir_path(__FILE__));
        } else {
            define('APSTOOLS_PATH', dirname(__FILE__) . '/');
        }
        
        if (function_exists('plugin_dir_url')) {
            define('APSTOOLS_URL', plugin_dir_url(__FILE__));
        } else {
            define('APSTOOLS_URL', '');
        }
    }

    public function initialize() {
        if (!$this->check_dependencies()) {
            return;
        }

        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // Initialize handlers and services
        if (class_exists('\APSTools\Handlers\TableHandler')) {
            \APSTools\Handlers\TableHandler::instance();
        }
        if (class_exists('\APSTools\Handlers\PatternHandler')) {
            \APSTools\Handlers\PatternHandler::instance();
        }
        if (class_exists('\APSTools\Services\JsonScannerService')) {
            new \APSTools\Services\JsonScannerService();
        }
        if (class_exists('\APSTools\Handlers\ChunkBatchProcessor')) {
            $chunk_batch_processor = new \APSTools\Handlers\ChunkBatchProcessor();
            add_action('wp_ajax_generate_patterns_from_chunks', [$chunk_batch_processor, 'handle_pattern_generation']);
        }

        // Chat Endpoint
        if (class_exists('\APSTools\API\ChatEndpoint')) {
            new \APSTools\API\ChatEndpoint();
        }
    }

    private function check_dependencies() {
        if (!class_exists('APS\Core\APS_Core')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('APS Tools requires the Aevov Pattern Sync Protocol plugin to be installed and activated.', 'aps-tools') . '</p></div>';
            });
            return false;
        }

        if (!class_exists('BLOOM_Pattern_System')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('APS Tools requires the BLOOM Pattern Recognition System plugin to be installed and activated.', 'aps-tools') . '</p></div>';
            });
            return false;
        }

        return true;
    }

    public function add_menu_pages() {
        add_menu_page(
            'Pattern System',
            'Pattern System',
            'manage_options',
            'aps-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-networking',
            30
        );

        add_submenu_page(
            'aps-dashboard',
            'System Status',
            'System Status',
            'manage_options',
            'aps-status',
            [$this, 'render_status']
        );
        
        add_submenu_page(
        'aps-dashboard',
        __('Directory Scanner', 'aps-tools'),
        __('Directory Scanner', 'aps-tools'),
       'manage_options',
       'aps-directory-scanner',
       [$this, 'render_directory_scanner']
       
       );
        
          add_submenu_page(
        'aps-dashboard',
        'Frontend Dashboard',
        'Frontend Dashboard',
        'manage_options',
        'aps-frontend',
        [$this, 'render_frontend_dashboard']
        );

        
        add_submenu_page(
       'aps-dashboard',
       'Stored BLOOM Chunks',
       'Stored Chunks',
       'manage_options',
       'aps-stored-chunks',
       [$this, 'render_stored_chunks']
        );
         
        add_submenu_page(
            'aps-stored-chunks', // Parent slug
            __('Import Media Chunks', 'aps-tools'),
            __('Import Media Chunks', 'aps-tools'),
            'manage_options',
            'chunk-csv-import',
            [$this, 'render_chunk_import_page']
        );
    
        
         add_submenu_page(
            'aps-dashboard',
            'System Settings',
            'System Settings',
            'manage_options',
           'apstools-settings',
            [$this, 'render_settings']
        );


        add_submenu_page(
            'aps-dashboard',
            'Pattern Analysis',
            'Pattern Analysis',
            'manage_options',
            'aps-analysis',
            [$this, 'render_analysis']
        );

        add_submenu_page(
            'aps-dashboard',
            'Pattern Comparison',
            'Pattern Comparison',
            'manage_options',
            'aps-comparison',
            [$this, 'render_comparison']
        );

        add_submenu_page(
            'aps-dashboard',
            'Pattern List',
            'Pattern List',
            'manage_options',
            'aps-patterns',
            [$this, 'render_patterns']
        );

        add_submenu_page(
            'aps-dashboard',
            'BLOOM Integration',
            'BLOOM Integration',
            'manage_options',
            'aps-bloom',
            [$this, 'render_bloom']
        );

        add_menu_page(
            'Aevov Unified Dashboard',
            'Aevov Dashboard',
            'manage_options',
            'aevov-dashboard',
            [$this, 'render_unified_dashboard'],
            'dashicons-dashboard',
            2
        );

        add_submenu_page(
            'aevov-dashboard',
            'Visualizations',
            'Visualizations',
            'manage_options',
            'aevov-visualizations',
            [$this, 'render_visualizations']
        );

        add_submenu_page(
            'aevov-dashboard',
            'Chat',
            'Chat',
            'manage_options',
            'aevov-chat',
            [$this, 'render_chat_page']
        );
    }

public function render_chunk_import_page() {
    \APSTools\Handlers\ChunkImportHandler::instance()->render_import_page();
}

public function enqueue_assets($hook) {
    if (strpos($hook, 'aps-') === false) {
        return;
    }

    // Main admin styles
    wp_enqueue_style(
        'apstools-admin',
        APSTOOLS_URL . 'assets/css/admin.css',
        [],
        APSTOOLS_VERSION
    );

    // Main admin script
    wp_enqueue_script(
        'apstools-admin',
        APSTOOLS_URL . 'assets/js/admin.js',
        ['jquery', 'wp-api', 'underscore'],
        APSTOOLS_VERSION,
        true
    );

    // Unified Dashboard
    wp_enqueue_style(
        'apstools-unified-dashboard',
        APSTOOLS_URL . 'assets/css/unified-dashboard.css',
        [],
        APSTOOLS_VERSION
    );
    
    wp_enqueue_script(
    'aps-directory-scanner',
    APSTOOLS_URL . 'assets/js/directory-scanner.js',
    ['jquery'],
    APSTOOLS_VERSION,
    true
);
    

    // Handsontable
    wp_enqueue_script(
        'handsontable',
        'https://cdn.jsdelivr.net/npm/handsontable@latest/dist/handsontable.full.min.js',
        [],
        null
    );

    // Charts.js
    wp_enqueue_script(
        'apstools-charts',
        APSTOOLS_URL . 'assets/js/charts.js',
        ['jquery', 'chartjs'],
        APSTOOLS_VERSION,
        true
    );

    // Chart.js
    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js',
        [],
        null
    );

    wp_enqueue_style(
        'handsontable',
        'https://cdn.jsdelivr.net/npm/handsontable@latest/dist/handsontable.full.min.css',
        [],
        null
    );

    // Directory Scanner Script
    wp_enqueue_script(
        'aps-directory-scanner',
        APSTOOLS_URL . 'assets/js/directory-scanner.js',
        ['jquery', 'handsontable'],
        APSTOOLS_VERSION,
        true
    );

    wp_localize_script('aps-directory-scanner', 'apsTools', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aps-tools-nonce'),
        'i18n' => [
            'selectModel' => __('Select Parent Model', 'aps-tools'),
            'scanning' => __('Scanning...', 'aps-tools'),
            'errorStarting' => __('Error starting scan', 'aps-tools'),
            'errorLoading' => __('Error loading models', 'aps-tools'),
            'fillRequired' => __('Please fill in all required fields', 'aps-tools')
        ]
    ]);



    // Page-specific scripts
    if ($hook === 'pattern-system_page_aps-patterns') {
        wp_enqueue_script(
            'apstools-pattern-list',
            APSTOOLS_URL . 'assets/js/pattern-list.js',
            ['jquery', 'underscore'],
            APSTOOLS_VERSION,
            true
        );
    }

    if ($hook === 'pattern-system_page_aps-bloom') {
        wp_enqueue_script(
            'apstools-bloom-integration',
            APSTOOLS_URL . 'assets/js/bloom-integration.js',
            ['jquery', 'underscore'],
            APSTOOLS_VERSION,
            true
        );
    }

    // Settings page script
    if ($hook === 'pattern-system_page_aps-tools-settings') {
        wp_enqueue_script(
            'apstools-settings',
            APSTOOLS_URL . 'assets/js/settings.js',
            ['jquery', 'underscore'],
            APSTOOLS_VERSION,
            true
        );
    }
    
 // Directory Scanner specific script
    if ($hook === 'pattern-system_page_aps-directory-scanner') {
        wp_enqueue_script(
            'aps-directory-scanner',
            APSTOOLS_URL . 'assets/js/directory-scanner.js',
            ['jquery', 'underscore'],
            APSTOOLS_VERSION,
            true
        );

        wp_localize_script('aps-directory-scanner', 'apsTools', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aps-tools-nonce'),
            'i18n' => [
                'selectModel' => __('Select Parent Model', 'aps-tools'),
                'scanning' => __('Scanning...', 'aps-tools'),
                'errorStarting' => __('Error starting scan', 'aps-tools'),
                'errorLoading' => __('Error loading models', 'aps-tools'),
                'fillRequired' => __('Please fill in all required fields', 'aps-tools')
            ]
        ]);
    }
    if ($hook === 'aevov-dashboard_page_aevov-chat') {
        if (class_exists('AevovChatUI')) {
            \AevovChatUI::enqueue_scripts();
        }
    }
}

 public function register_settings() {
        register_setting('aps_settings', 'aps_validate_json', [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Enable or disable JSON validation for BLOOM chunks'
        ]);
        
        register_setting('aps_settings', 'aps_sync_interval', [
            'type' => 'integer',
            'default' => 300,
            'description' => 'Sync interval in seconds'
        ]);
    }

    public function register_rest_routes() {
        register_rest_route('aps-tools/v1', '/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_system_status'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('aps-tools/v1', '/metrics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_system_metrics'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('aps-tools/v1', '/analyze', [
            'methods' => 'POST',
            'callback' => [$this, 'analyze_pattern'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
        
           register_rest_route('aps-tools/v1', '/bloom/upload-chunk', [
        'methods' => 'POST',
        'callback' => [$this, 'handle_chunk_upload'],
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('aps-tools/v1', '/bloom/chunks', [
        'methods' => 'GET',
        'callback' => [$this, 'get_uploaded_chunks'],
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);

        register_rest_route('aps-tools/v1', '/compare', [
            'methods' => 'POST',
            'callback' => [$this, 'compare_patterns'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('aps-tools/v1', '/dashboard', [
            'methods' => 'GET',
            'callback' => [$this, 'get_dashboard_data'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('aps-tools/v1', '/activity-feed', [
            'methods' => 'GET',
            'callback' => [$this, 'get_activity_feed'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('aps-tools/v1', '/visualizations', [
            'methods' => 'GET',
            'callback' => [$this, 'get_visualizations_data'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('aps-tools/v1', '/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('aps-tools/v1', '/scan-directory', [
            'methods' => 'POST',
            'callback' => [$this, 'scan_directory'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('aps-tools/v1/analyze-pattern', [
            'methods' => 'POST',
            'callback' => [$this, 'analyze_pattern_endpoint'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('aps-tools/v1/compare-patterns', [
            'methods' => 'POST',
            'callback' => [$this, 'compare_patterns_endpoint'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
public function handle_chunk_upload($request) {
    $chunk_data = $request->get_json_params();
    
    if (!isset($chunk_data['sku'])) {
        return new \WP_Error('invalid_chunk', 'Invalid chunk data');
    }

    // Store the chunk
    $result = $this->store_chunk($chunk_data);
    
    if (is_wp_error($result)) {
        return $result;
    }

    return rest_ensure_response([
        'success' => true,
        'chunk' => $result
    ]);
}

private function store_chunk($chunk_data) {
    if (!class_exists('\APSTools\Models\BloomTensorStorage')) {
        return new \WP_Error('missing_dependency', 'BloomTensorStorage class is not available.');
    }

    $bloom_tensor_storage = \APSTools\Models\BloomTensorStorage::instance();
    $result = $bloom_tensor_storage->store_chunk_data($chunk_data);

    if (is_wp_error($result)) {
        return $result;
    }

    return [
        'sku' => $chunk_data['sku'],
        'size' => strlen(json_encode($chunk_data)), // Assuming chunk_data is JSON string
        'uploaded_at' => current_time('mysql')
    ];
}
    public function render_dashboard() {
        include APSTOOLS_PATH . 'templates/dashboard.php';
    }

    public function render_status() {
        include APSTOOLS_PATH . 'templates/status.php';
    }
  public function render_stored_chunks() {
    if (!class_exists('APSTools\Models\BloomTensorStorage')) {
        wp_die(__('Required BloomTensorStorage class is missing.', 'aps-tools'));
    }
    include APSTOOLS_PATH . 'templates/stored-chunks.php';
}

    public function render_analysis() {
        include APSTOOLS_PATH . 'templates/analysis.php';
    }

    public function render_comparison() {
        include APSTOOLS_PATH . 'templates/comparison.php';
    }

    public function render_patterns() {
        include APSTOOLS_PATH . 'templates/patterns.php';
    }

    public function render_bloom() {
        include APSTOOLS_PATH . 'templates/bloom.php';
    }

    public function render_unified_dashboard() {
        include APSTOOLS_PATH . 'templates/unified-dashboard.php';
    }

    public function render_visualizations() {
        include APSTOOLS_PATH . 'templates/visualizations.php';
    }

    public function render_chat_page() {
        if (class_exists('AevovChatUI')) {
            \AevovChatUI::render();
        }
    }

    public function get_system_status() {
        return rest_ensure_response([
            'cpu_usage' => $this->get_cpu_usage(),
            'memory_usage' => $this->get_memory_usage(),
            'queue_size' => $this->get_queue_size(),
            'pattern_count' => $this->get_pattern_count(),
            'bloom_status' => $this->get_bloom_status()
        ]);
    }

    public function get_system_metrics() {
        return rest_ensure_response([
            'processing_rate' => $this->get_processing_rate(),
            'success_rate' => $this->get_success_rate(),
            'distribution' => $this->get_pattern_distribution(),
            'bloom_sync' => $this->get_bloom_sync_status()
        ]);
    }

    public function analyze_pattern($request) {
        try {
            $pattern_data = $request->get_json_params();
            if (empty($pattern_data)) {
                return new \WP_Error('invalid_data', 'No pattern data provided');
            }

            $result = apply_filters('aps_pattern_analysis', $pattern_data);
            if (is_wp_error($result)) {
                return $result;
            }

            return rest_ensure_response([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return new \WP_Error('analysis_failed', $e->getMessage());
        }
    }

    public function compare_patterns($request) {
        try {
            $patterns = $request->get_json_params();
            if (empty($patterns) || !is_array($patterns)) {
                return new \WP_Error('invalid_data', 'Invalid pattern data');
            }

            $result = apply_filters('aps_pattern_comparison', $patterns);
            if (is_wp_error($result)) {
                return $result;
            }

            return rest_ensure_response([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return new \WP_Error('comparison_failed', $e->getMessage());
        }
    }

    private function get_cpu_usage() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0];
        }
        return 0;
    }
    
    public function render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    include APSTOOLS_PATH . 'templates/admin/settings.php';
}


    private function get_memory_usage() {
        return memory_get_usage(true);
    }

    private function get_queue_size() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aps_queue WHERE status = 'pending'"
        );
    }

    private function get_pattern_count() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aps_patterns"
        );
    }

    private function get_pattern_count_by_type($type) {
        global $wpdb;
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aps_patterns WHERE pattern_type = %s",
                $type
            )
        );
    }

    private function get_average_confidence() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT AVG(confidence) FROM {$wpdb->prefix}aps_patterns"
        );
    }

    private function get_pattern_type_distribution() {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT pattern_type, COUNT(*) as count FROM {$wpdb->prefix}aps_patterns GROUP BY pattern_type",
            ARRAY_A
        );

        $distribution = [];
        foreach ($results as $result) {
            $distribution[$result['pattern_type']] = $result['count'];
        }

        return $distribution;
    }

    private function get_recent_patterns() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT id, pattern_type, confidence, created_at FROM {$wpdb->prefix}aps_patterns ORDER BY created_at DESC LIMIT 10"
        );
    }

    public function get_dashboard_data() {
        return rest_ensure_response([
            'total_patterns' => $this->get_pattern_count(),
            'patterns_today' => $this->get_patterns_processed_today(),
            'system_health' => 'OK', // This is a placeholder
        ]);
    }

    private function get_patterns_processed_today() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aps_patterns WHERE DATE(created_at) = CURDATE()"
        );
    }

    public function get_activity_feed() {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}aps_sync_log ORDER BY id DESC LIMIT 20"
        );

        return rest_ensure_response($results);
    }

    public function get_visualizations_data() {
        return rest_ensure_response([
            'knowledge_graph' => $this->get_knowledge_graph_data(),
            'scatter_plot' => $this->get_scatter_plot_data(),
            'heat_map' => $this->get_heat_map_data(),
        ]);
    }

    private function get_knowledge_graph_data() {
        global $wpdb;
        $nodes = [];
        $edges = [];

        $patterns = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aps_patterns");
        foreach ($patterns as $pattern) {
            $nodes[] = [
                'id' => 'pattern-' . $pattern->id,
                'data' => ['label' => $pattern->pattern_type],
                'position' => ['x' => rand(0, 500), 'y' => rand(0, 500)],
            ];
        }

        $chunks = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aps_pattern_chunks");
        foreach ($chunks as $chunk) {
            $nodes[] = [
                'id' => 'chunk-' . $chunk->chunk_id,
                'data' => ['label' => 'Chunk ' . $chunk->chunk_id],
                'position' => ['x' => rand(0, 500), 'y' => rand(0, 500)],
            ];
            $edges[] = [
                'id' => 'edge-' . $chunk->pattern_id . '-' . $chunk->chunk_id,
                'source' => 'pattern-' . $chunk->pattern_id,
                'target' => 'chunk-' . $chunk->chunk_id,
            ];
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    private function get_scatter_plot_data() {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT p1.id as p1_id, p2.id as p2_id, c.similarity_score
            FROM {$wpdb->prefix}aps_comparisons c
            JOIN {$wpdb->prefix}aps_patterns p1 ON c.pattern1_id = p1.id
            JOIN {$wpdb->prefix}aps_patterns p2 ON c.pattern2_id = p2.id
            ORDER BY c.created_at DESC
            LIMIT 100"
        );

        $data = [];
        foreach ($results as $result) {
            $data[] = [
                'x' => $result->p1_id,
                'y' => $result->p2_id,
                'r' => $result->similarity_score * 10,
            ];
        }

        return $data;
    }

    private function get_heat_map_data() {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT DAYOFWEEK(created_at) as day, WEEK(created_at) as week, COUNT(*) as count
            FROM {$wpdb->prefix}aps_patterns
            GROUP BY day, week"
        );

        $data = [];
        foreach ($results as $result) {
            $data[] = [
                'x' => $result->day,
                'y' => $result->week,
                'v' => $result->count,
            ];
        }

        return $data;
    }

    public function search($request) {
        global $wpdb;
        $query = $request->get_param('q');
        $results = [];

        // Search for patterns
        $patterns = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aps_patterns WHERE pattern_type LIKE %s",
                '%' . $wpdb->esc_like($query) . '%'
            )
        );
        foreach ($patterns as $pattern) {
            $results[] = [
                'id' => 'pattern-' . $pattern->id,
                'title' => $pattern->pattern_type,
                'type' => 'pattern',
            ];
        }

        // Search for chunks
        $chunks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aps_pattern_chunks WHERE chunk_id LIKE %s",
                '%' . $wpdb->esc_like($query) . '%'
            )
        );
        foreach ($chunks as $chunk) {
            $results[] = [
                'id' => 'chunk-' . $chunk->chunk_id,
                'title' => 'Chunk ' . $chunk->chunk_id,
                'type' => 'chunk',
            ];
        }

        return rest_ensure_response($results);
    }

    public function scan_directory($request) {
        $directory = $request->get_param('directory');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $results = [];
        foreach ($files as $file) {
            if ($file->isDir()){
                continue;
            }
            $results[] = $this->pattern_model->create([
                'type' => 'file',
                'features' => [
                    'filename' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'size' => $file->getSize(),
                ],
                'confidence' => 1.0,
            ]);
        }
        return rest_ensure_response($results);
    }

    public function analyze_pattern_endpoint($request) {
        $pattern_id = $request->get_param('pattern_id');
        $pattern = $this->pattern_model->get($pattern_id);
        $analyzer = new \APS\Analysis\SymbolicPatternAnalyzer();
        $results = $analyzer->analyze_pattern($pattern->features);
        return rest_ensure_response($results);
    }

    public function compare_patterns_endpoint($request) {
        $pattern1_id = $request->get_param('pattern1_id');
        $pattern2_id = $request->get_param('pattern2_id');
        $pattern1 = $this->pattern_model->get($pattern1_id);
        $pattern2 = $this->pattern_model->get($pattern2_id);
        $comparator = new \APS\Comparison\APS_Comparator();
        $results = $comparator->compare_patterns([$pattern1->features, $pattern2->features]);
        return rest_ensure_response($results);
    }

    private function get_bloom_status() {
        if (!class_exists('\BLOOM\Integration\APSIntegration')) {
            return 'inactive';
        }
        return get_option('aps_bloom_status', 'active');
    }

    private function get_processing_rate() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aps_queue 
             WHERE status = 'completed' 
             AND completed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
    }

    private function get_success_rate() {
        global $wpdb;
        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aps_queue 
             WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        
        if (!$total) return 0;

        $success = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aps_queue 
             WHERE status = 'completed' 
             AND completed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );

        return ($success / $total) * 100;
    }
    
  public function render_frontend_dashboard() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'aps-tools'));
    }
    include APSTOOLS_PATH . 'templates/frontend-dashboard.php';
}

public function render_directory_scanner() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'aps-tools'));
    }
    include APSTOOLS_PATH . 'templates/directory-scanner.php';
}
    
    public function handle_start_scan() {
    check_ajax_referer('aps-tools-nonce', 'nonce');
    
    try {
        $directory = sanitize_text_field($_POST['directory']);
        $model_id = intval($_POST['model']);
        $category_id = intval($_POST['category']);
        $batch_size = intval($_POST['batch_size']);
        $recursive = (bool) $_POST['recursive'];

        $processor = new \APSTools\Scanner\BatchProcessor();
        $result = $processor->process_directory($directory, $model_id, $category_id, [
            'batch_size' => $batch_size,
            'recursive' => $recursive
        ]);

        wp_send_json_success($result);
    } catch (\Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

public function handle_get_scan_status() {
    check_ajax_referer('aps-tools-nonce', 'nonce');
    
    $processor = new \APSTools\Scanner\BatchProcessor();
    $status = $processor->get_status();
    wp_send_json_success($status);
}

public function handle_stop_scan() {
    check_ajax_referer('aps-tools-nonce', 'nonce');
    
    $processor = new \APSTools\Scanner\BatchProcessor();
    $processor->stop();
    wp_send_json_success();
}

public function handle_get_system_metrics() {
    check_ajax_referer('aps-tools-nonce', 'nonce');

    wp_send_json_success([
        'cpu_usage' => $this->get_cpu_usage(),
        'memory_usage' => $this->get_memory_usage(),
        'patterns_processed' => [
            'total' => $this->get_pattern_count(),
            'sequential' => $this->get_pattern_count_by_type('sequential'),
            'structural' => $this->get_pattern_count_by_type('structural'),
            'statistical' => $this->get_pattern_count_by_type('statistical'),
            'file' => $this->get_pattern_count_by_type('file'),
        ],
        'avg_confidence' => $this->get_average_confidence(),
        'pattern_types' => $this->get_pattern_type_distribution(),
        'recent_patterns' => $this->get_recent_patterns(),
    ]);
}
    

    private function get_pattern_distribution() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT pattern_type, COUNT(*) as count 
             FROM {$wpdb->prefix}aps_patterns 
             GROUP BY pattern_type"
        );
    }
    
    

    private function get_bloom_sync_status() {
        return [
            'last_sync' => get_option('aps_last_bloom_sync'),
            'sync_errors' => get_option('aps_bloom_sync_errors', 0),
            'patterns_synced' => get_option('aps_bloom_patterns_synced', 0)
        ];
    }

    public function render_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'aps-tools'));
        }
        include APSTOOLS_PATH . 'templates/settings.php';
    }
}

function APSTools() {
    return APSTools::instance();
}

// Global utility functions for APS Tools
function aps_tools_get_available_patterns() {
    global $wpdb;
    
    $patterns = $wpdb->get_results(
        "SELECT id, sku, pattern_type, created_at
         FROM {$wpdb->prefix}aps_patterns
         ORDER BY created_at DESC"
    );
    
    return $patterns ? $patterns : [];
}

function aps_tools_get_pattern_by_id($pattern_id) {
    global $wpdb;
    
    $pattern = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aps_patterns WHERE id = %d",
            $pattern_id
        )
    );
    
    return $pattern;
}

function aps_tools_analyze_pattern($pattern_data) {
    try {
        // Basic pattern analysis
        $analysis = [
            'pattern_type' => $pattern_data['pattern_type'],
            'data_length' => strlen($pattern_data['pattern_data']),
            'complexity_score' => aps_tools_calculate_complexity($pattern_data['pattern_data']),
            'analysis_timestamp' => current_time('mysql'),
            'status' => 'completed'
        ];
        
        // Apply filters to allow other plugins to extend analysis
        $analysis = apply_filters('aps_tools_pattern_analysis', $analysis, $pattern_data);
        
        return $analysis;
    } catch (Exception $e) {
        return new WP_Error('analysis_failed', $e->getMessage());
    }
}

function aps_tools_compare_patterns($patterns, $comparison_type) {
    try {
        if (count($patterns) < 2) {
            return new WP_Error('insufficient_patterns', 'At least 2 patterns required for comparison');
        }
        
        $pattern1 = $patterns[0];
        $pattern2 = $patterns[1];
        
        $comparison = [
            'comparison_type' => $comparison_type,
            'pattern1_id' => $pattern1->id,
            'pattern2_id' => $pattern2->id,
            'similarity_score' => aps_tools_calculate_similarity($pattern1, $pattern2),
            'differences' => aps_tools_find_differences($pattern1, $pattern2),
            'comparison_timestamp' => current_time('mysql')
        ];
        
        // Apply filters to allow other plugins to extend comparison
        $comparison = apply_filters('aps_tools_pattern_comparison', $comparison, $patterns, $comparison_type);
        
        return $comparison;
    } catch (Exception $e) {
        return new WP_Error('comparison_failed', $e->getMessage());
    }
}

function aps_tools_calculate_complexity($data) {
    // Simple complexity calculation based on data characteristics
    $length = strlen($data);
    $unique_chars = count(array_unique(str_split($data)));
    $entropy = 0;
    
    // Calculate basic entropy
    $char_counts = array_count_values(str_split($data));
    foreach ($char_counts as $count) {
        $probability = $count / $length;
        $entropy -= $probability * log($probability, 2);
    }
    
    return [
        'length' => $length,
        'unique_characters' => $unique_chars,
        'entropy' => round($entropy, 4),
        'complexity_rating' => aps_tools_rate_complexity($entropy, $unique_chars, $length)
    ];
}

function aps_tools_rate_complexity($entropy, $unique_chars, $length) {
    $score = ($entropy * 0.4) + (($unique_chars / $length) * 0.6);
    
    if ($score > 0.8) return 'high';
    if ($score > 0.5) return 'medium';
    return 'low';
}

function aps_tools_calculate_similarity($pattern1, $pattern2) {
    // Simple similarity calculation
    $data1 = isset($pattern1->pattern_data) ? $pattern1->pattern_data : '';
    $data2 = isset($pattern2->pattern_data) ? $pattern2->pattern_data : '';
    
    if (empty($data1) || empty($data2)) {
        return 0;
    }
    
    // Calculate Levenshtein distance for similarity
    $distance = levenshtein(substr($data1, 0, 255), substr($data2, 0, 255));
    $max_length = max(strlen($data1), strlen($data2));
    
    if ($max_length == 0) return 100;
    
    $similarity = (1 - ($distance / $max_length)) * 100;
    return round($similarity, 2);
}

function aps_tools_find_differences($pattern1, $pattern2) {
    $differences = [];
    
    // Compare basic properties
    if ($pattern1->pattern_type !== $pattern2->pattern_type) {
        $differences[] = [
            'field' => 'pattern_type',
            'pattern1' => $pattern1->pattern_type,
            'pattern2' => $pattern2->pattern_type
        ];
    }
    
    if (isset($pattern1->sku) && isset($pattern2->sku) && $pattern1->sku !== $pattern2->sku) {
        $differences[] = [
            'field' => 'sku',
            'pattern1' => $pattern1->sku,
            'pattern2' => $pattern2->sku
        ];
    }
    
    return $differences;
}

$GLOBALS['aps_tools'] = APSTools();

require_once APSTOOLS_PATH . 'includes/models/class-bloom-model-manager.php';

// Add to plugin activation hook - only if WordPress is loaded
if (function_exists('wp_upload_dir') && function_exists('wp_mkdir_p')) {
    $upload_dir = wp_upload_dir();
    wp_mkdir_p($upload_dir['basedir'] . '/bloom-chunks');
}

// Add to your existing includes/requires
require_once APSTOOLS_PATH . 'includes/class-bloom-bulk-upload-manager.php';
require_once APSTOOLS_PATH . 'includes/class-aps-tools-frontend.php';
require_once APSTOOLS_PATH . 'includes/scanner/class-directory-scanner.php';
require_once APSTOOLS_PATH . 'includes/models/class-bloom-tensor-storage.php';
require_once APSTOOLS_PATH . 'includes/scanner/class-batch-processor.php';
require_once APSTOOLS_PATH . 'includes/handlers/class-table-handler.php';
require_once APSTOOLS_PATH . 'includes/handlers/class-media-chunk-handler.php';
require_once APSTOOLS_PATH . 'includes/services/class-media-monitor.php';

// Integration components
require_once APSTOOLS_PATH . 'includes/integrations/class-cubbit-integration-protocol.php';
require_once APSTOOLS_PATH . 'includes/ai/class-xai-engine.php';
require_once APSTOOLS_PATH . 'includes/api/class-chat-endpoint.php';
require_once dirname(__FILE__) . '/../aevov-cognitive-engine/includes/class-cognitive-conductor.php';
require_once dirname(__FILE__) . '/../aevov-chat-ui/aevov-chat-ui.php';
