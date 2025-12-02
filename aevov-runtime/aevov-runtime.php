<?php
/**
 * Plugin Name: AevRT - Aevov Runtime
 * Plugin URI: https://aevov.com/plugins/aevrt
 * Description: Ultra-low-latency AI inference runtime inspired by TileRT. Optimizes LLM, image, and audio processing through tile-based task decomposition, intelligent scheduling, and AevIP distributed processing.
 * Version: 1.0.0
 * Author: Aevov Team
 * Author URI: https://aevov.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: aevov-runtime
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.0
 *
 * Based on TileRT (https://github.com/tile-ai/tilert)
 * Adapted for PHP/WordPress with AevIP distributed processing
 *
 * @package AevovRuntime
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Define plugin constants
 */
define('AEVRT_VERSION', '1.0.0');
define('AEVRT_PLUGIN_FILE', __FILE__);
define('AEVRT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AEVRT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AEVRT_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main AevRT Plugin Class
 *
 * Provides TileRT-inspired runtime optimization:
 * - Tile-based task decomposition for AI inference
 * - Ultra-low-latency request scheduling
 * - Intelligent resource allocation
 * - AevIP multi-node coordination (replaces multi-GPU)
 * - Compute/I/O overlap optimization
 * - Priority queue management
 * - Performance metrics and monitoring
 */
class AevovRuntime {

    /**
     * Plugin instance
     *
     * @var AevovRuntime
     */
    private static $instance = null;

    /**
     * Task scheduler
     *
     * @var TileScheduler
     */
    private $scheduler;

    /**
     * Task executor
     *
     * @var TaskExecutor
     */
    private $executor;

    /**
     * Performance optimizer
     *
     * @var RuntimeOptimizer
     */
    private $optimizer;

    /**
     * AevIP coordinator
     *
     * @var AevIPCoordinator
     */
    private $aevip;

    /**
     * Performance metrics
     *
     * @var array
     */
    private $metrics = [];

    /**
     * Get singleton instance
     *
     * @return AevovRuntime
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
        $this->init_components();
    }

    /**
     * Load dependencies
     */
    private function load_dependencies() {
        // Core components
        require_once AEVRT_PLUGIN_DIR . 'includes/scheduler/class-tile-scheduler.php';
        require_once AEVRT_PLUGIN_DIR . 'includes/executor/class-task-executor.php';
        require_once AEVRT_PLUGIN_DIR . 'includes/executor/class-inference-engine.php';
        require_once AEVRT_PLUGIN_DIR . 'includes/optimizer/class-runtime-optimizer.php';
        require_once AEVRT_PLUGIN_DIR . 'includes/optimizer/class-latency-analyzer.php';

        // Integrations
        require_once AEVRT_PLUGIN_DIR . 'includes/integrations/class-aevip-coordinator.php';
        require_once AEVRT_PLUGIN_DIR . 'includes/integrations/class-language-engine-adapter.php';
        require_once AEVRT_PLUGIN_DIR . 'includes/integrations/class-image-engine-adapter.php';
        require_once AEVRT_PLUGIN_DIR . 'includes/integrations/class-music-engine-adapter.php';

        // API
        require_once AEVRT_PLUGIN_DIR . 'includes/api/class-runtime-endpoint.php';
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation/deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Admin
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Intercept AI engine requests for optimization
        add_filter('aevov_language_before_inference', [$this, 'optimize_language_request'], 10, 2);
        add_filter('aevov_image_before_generation', [$this, 'optimize_image_request'], 10, 2);
        add_filter('aevov_music_before_generation', [$this, 'optimize_music_request'], 10, 2);

        // Performance monitoring
        add_action('shutdown', [$this, 'record_request_metrics']);

        // Scheduled optimization
        add_action('aevrt_optimize_runtime', [$this, 'run_optimization']);
    }

    /**
     * Initialize components
     */
    private function init_components() {
        $this->scheduler = new \AevovRuntime\TileScheduler();
        $this->executor = new \AevovRuntime\TaskExecutor();
        $this->optimizer = new \AevovRuntime\RuntimeOptimizer();
        $this->aevip = new \AevovRuntime\AevIPCoordinator();

        // Initialize AevIP if enabled
        if (get_option('aevrt_enable_aevip', true)) {
            $this->aevip->init();
        }

        error_log('[AevRT] Runtime initialized - Low-latency mode active');
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();

        // Schedule optimization tasks
        if (!wp_next_scheduled('aevrt_optimize_runtime')) {
            wp_schedule_event(time(), 'hourly', 'aevrt_optimize_runtime');
        }

        // Set default options
        add_option('aevrt_enable_aevip', true);
        add_option('aevrt_tile_size', 256); // Tile size for task decomposition
        add_option('aevrt_max_latency_ms', 100); // Target max latency
        add_option('aevrt_enable_prefetch', true);
        add_option('aevrt_enable_caching', true);
        add_option('aevrt_priority_queue', true);

        error_log('[AevRT] Plugin activated - version ' . AEVRT_VERSION);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        wp_clear_scheduled_hook('aevrt_optimize_runtime');
        error_log('[AevRT] Plugin deactivated');
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Task execution log
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aevrt_tasks (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            task_id varchar(100) NOT NULL UNIQUE,
            task_type enum('language','image','music','embedding','reasoning') NOT NULL,
            priority int(11) DEFAULT 0,
            status enum('queued','scheduled','executing','completed','failed') DEFAULT 'queued',
            tile_count int(11) DEFAULT 1,
            assigned_node varchar(100),
            input_size int(11),
            output_size int(11),
            latency_ms float,
            started_at datetime,
            completed_at datetime,
            created_at datetime NOT NULL,
            metadata longtext,
            PRIMARY KEY  (id),
            KEY task_id (task_id),
            KEY task_type (task_type),
            KEY status (status),
            KEY priority (priority),
            KEY latency_ms (latency_ms)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Performance metrics
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aevrt_metrics (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            metric_type varchar(50) NOT NULL,
            value float NOT NULL,
            node_id varchar(100),
            task_type varchar(50),
            timestamp datetime NOT NULL,
            metadata longtext,
            PRIMARY KEY  (id),
            KEY metric_type (metric_type),
            KEY timestamp (timestamp),
            KEY node_id (node_id)
        ) $charset_collate;";

        dbDelta($sql);

        // Node performance tracking
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aevrt_nodes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            node_id varchar(100) NOT NULL UNIQUE,
            node_type varchar(50) NOT NULL,
            status enum('active','busy','offline') DEFAULT 'active',
            current_load float DEFAULT 0,
            avg_latency_ms float,
            total_tasks int(11) DEFAULT 0,
            success_rate float DEFAULT 100,
            last_heartbeat datetime,
            capabilities longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY node_id (node_id),
            KEY status (status),
            KEY avg_latency_ms (avg_latency_ms)
        ) $charset_collate;";

        dbDelta($sql);

        error_log('[AevRT] Database tables created');
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'AevRT Runtime',
            'AevRT',
            'manage_options',
            'aevrt',
            [$this, 'render_dashboard'],
            'dashicons-performance',
            81
        );

        add_submenu_page(
            'aevrt',
            'Performance Metrics',
            'Metrics',
            'manage_options',
            'aevrt-metrics',
            [$this, 'render_metrics_page']
        );

        add_submenu_page(
            'aevrt',
            'Task Queue',
            'Task Queue',
            'manage_options',
            'aevrt-queue',
            [$this, 'render_queue_page']
        );

        add_submenu_page(
            'aevrt',
            'Nodes',
            'Nodes',
            'manage_options',
            'aevrt-nodes',
            [$this, 'render_nodes_page']
        );

        add_submenu_page(
            'aevrt',
            'Settings',
            'Settings',
            'manage_options',
            'aevrt-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        $endpoint = new \AevovRuntime\RuntimeEndpoint();
        $endpoint->register_routes();
    }

    /**
     * Optimize language inference request (TileRT-inspired)
     *
     * @param array $request Request data
     * @param object $engine Engine instance
     * @return array Optimized request
     */
    public function optimize_language_request($request, $engine) {
        $start_time = microtime(true);

        // Create task
        $task = [
            'type' => 'language',
            'priority' => $request['priority'] ?? 0,
            'input' => $request['prompt'] ?? $request['messages'] ?? '',
            'model' => $request['model'] ?? 'gpt-3.5-turbo',
            'max_tokens' => $request['max_tokens'] ?? 1000,
            'stream' => $request['stream'] ?? false
        ];

        // Decompose into tiles if large request
        $tiles = $this->scheduler->decompose_task($task);

        // Schedule tiles for optimal execution
        $schedule = $this->scheduler->create_schedule($tiles, [
            'target_latency' => get_option('aevrt_max_latency_ms', 100),
            'enable_prefetch' => get_option('aevrt_enable_prefetch', true),
            'use_aevip' => get_option('aevrt_enable_aevip', true)
        ]);

        // Execute with optimization
        $result = $this->executor->execute_schedule($schedule);

        // Record metrics
        $latency = (microtime(true) - $start_time) * 1000;
        $this->record_task_metrics('language', $latency, count($tiles));

        // Return optimized request (with routing hints)
        $request['_aevrt_optimized'] = true;
        $request['_aevrt_node'] = $result['optimal_node'] ?? null;
        $request['_aevrt_cache_key'] = $result['cache_key'] ?? null;

        return $request;
    }

    /**
     * Optimize image generation request
     *
     * @param array $request Request data
     * @param object $engine Engine instance
     * @return array Optimized request
     */
    public function optimize_image_request($request, $engine) {
        $start_time = microtime(true);

        $task = [
            'type' => 'image',
            'priority' => $request['priority'] ?? 0,
            'prompt' => $request['prompt'] ?? '',
            'size' => $request['size'] ?? '1024x1024',
            'n' => $request['n'] ?? 1
        ];

        // Image generation can be parallelized across AevIP nodes
        if ($task['n'] > 1 && get_option('aevrt_enable_aevip', true)) {
            $tiles = array_map(function($i) use ($task) {
                return array_merge($task, ['n' => 1, 'batch_index' => $i]);
            }, range(0, $task['n'] - 1));
        } else {
            $tiles = [$task];
        }

        $schedule = $this->scheduler->create_schedule($tiles, [
            'target_latency' => get_option('aevrt_max_latency_ms', 100) * 10, // Images take longer
            'use_aevip' => get_option('aevrt_enable_aevip', true)
        ]);

        $result = $this->executor->execute_schedule($schedule);

        $latency = (microtime(true) - $start_time) * 1000;
        $this->record_task_metrics('image', $latency, count($tiles));

        $request['_aevrt_optimized'] = true;
        $request['_aevrt_distribution'] = $result['distribution'] ?? null;

        return $request;
    }

    /**
     * Optimize music generation request
     *
     * @param array $request Request data
     * @param object $engine Engine instance
     * @return array Optimized request
     */
    public function optimize_music_request($request, $engine) {
        $start_time = microtime(true);

        $task = [
            'type' => 'music',
            'priority' => $request['priority'] ?? 0,
            'prompt' => $request['prompt'] ?? '',
            'duration' => $request['duration'] ?? 30
        ];

        // Music can be split into segments for parallel generation
        $segment_duration = 10; // 10 second segments
        $num_segments = ceil($task['duration'] / $segment_duration);

        if ($num_segments > 1 && get_option('aevrt_enable_aevip', true)) {
            $tiles = array_map(function($i) use ($task, $segment_duration) {
                return array_merge($task, [
                    'segment' => $i,
                    'duration' => $segment_duration,
                    'offset' => $i * $segment_duration
                ]);
            }, range(0, $num_segments - 1));
        } else {
            $tiles = [$task];
        }

        $schedule = $this->scheduler->create_schedule($tiles);
        $result = $this->executor->execute_schedule($schedule);

        $latency = (microtime(true) - $start_time) * 1000;
        $this->record_task_metrics('music', $latency, count($tiles));

        $request['_aevrt_optimized'] = true;
        $request['_aevrt_segments'] = $result['segments'] ?? null;

        return $request;
    }

    /**
     * Record task metrics
     *
     * @param string $task_type Task type
     * @param float $latency_ms Latency in milliseconds
     * @param int $tile_count Number of tiles
     */
    private function record_task_metrics($task_type, $latency_ms, $tile_count) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'aevrt_metrics',
            [
                'metric_type' => 'task_latency',
                'value' => $latency_ms,
                'task_type' => $task_type,
                'timestamp' => current_time('mysql'),
                'metadata' => json_encode([
                    'tile_count' => $tile_count,
                    'aevip_enabled' => get_option('aevrt_enable_aevip', true)
                ])
            ],
            ['%s', '%f', '%s', '%s', '%s']
        );

        // Update in-memory metrics
        if (!isset($this->metrics[$task_type])) {
            $this->metrics[$task_type] = [
                'count' => 0,
                'total_latency' => 0,
                'min_latency' => PHP_FLOAT_MAX,
                'max_latency' => 0
            ];
        }

        $this->metrics[$task_type]['count']++;
        $this->metrics[$task_type]['total_latency'] += $latency_ms;
        $this->metrics[$task_type]['min_latency'] = min($this->metrics[$task_type]['min_latency'], $latency_ms);
        $this->metrics[$task_type]['max_latency'] = max($this->metrics[$task_type]['max_latency'], $latency_ms);
    }

    /**
     * Record request-level metrics on shutdown
     */
    public function record_request_metrics() {
        // Record overall request performance
        $execution_time = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000;

        if ($execution_time > 0) {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'aevrt_metrics',
                [
                    'metric_type' => 'request_time',
                    'value' => $execution_time,
                    'timestamp' => current_time('mysql'),
                    'metadata' => json_encode([
                        'url' => $_SERVER['REQUEST_URI'] ?? '',
                        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
                    ])
                ],
                ['%s', '%f', '%s', '%s']
            );
        }
    }

    /**
     * Run periodic optimization
     */
    public function run_optimization() {
        error_log('[AevRT] Running periodic optimization...');

        // Analyze recent performance
        $analysis = $this->optimizer->analyze_performance();

        // Optimize tile sizes based on performance
        if ($analysis['avg_latency'] > get_option('aevrt_max_latency_ms', 100)) {
            $new_tile_size = $this->optimizer->calculate_optimal_tile_size($analysis);
            update_option('aevrt_tile_size', $new_tile_size);
        }

        // Update node performance rankings
        $this->aevip->update_node_rankings();

        // Clean old metrics (keep last 30 days)
        $this->cleanup_old_metrics(30);

        error_log('[AevRT] Optimization complete');
    }

    /**
     * Cleanup old metrics
     *
     * @param int $days Days to keep
     */
    private function cleanup_old_metrics($days) {
        global $wpdb;

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}aevrt_metrics
             WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        if ($deleted) {
            error_log("[AevRT] Cleaned up {$deleted} old metric records");
        }
    }

    /**
     * Get current metrics
     *
     * @return array Metrics
     */
    public function get_metrics() {
        return $this->metrics;
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        include AEVRT_PLUGIN_DIR . 'admin/dashboard.php';
    }

    /**
     * Render metrics page
     */
    public function render_metrics_page() {
        include AEVRT_PLUGIN_DIR . 'admin/metrics.php';
    }

    /**
     * Render queue page
     */
    public function render_queue_page() {
        include AEVRT_PLUGIN_DIR . 'admin/queue.php';
    }

    /**
     * Render nodes page
     */
    public function render_nodes_page() {
        include AEVRT_PLUGIN_DIR . 'admin/nodes.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include AEVRT_PLUGIN_DIR . 'admin/settings.php';
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'aevrt') === false) {
            return;
        }

        wp_enqueue_style(
            'aevrt-admin',
            AEVRT_PLUGIN_URL . 'assets/css/admin.css',
            [],
            AEVRT_VERSION
        );

        wp_enqueue_script(
            'aevrt-admin',
            AEVRT_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            AEVRT_VERSION,
            true
        );

        wp_localize_script('aevrt-admin', 'aevrt', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('aevrt/v1'),
            'nonce' => wp_create_nonce('aevrt'),
            'metrics' => $this->get_metrics()
        ]);
    }
}

/**
 * Initialize plugin
 */
function aevrt() {
    return AevovRuntime::instance();
}

// Kick off the plugin
aevrt();
