<?php
/**
 * Plugin Name: Aevov AI Core
 * Plugin URI: https://aevov.com/ai-core
 * Description: Unified AI provider system with DeepSeek, MiniMax support, .aev model framework, and comprehensive debugging engine for the entire Aevov ecosystem.
 * Version: 1.0.0
 * Author: Aevov Team
 * Author URI: https://aevov.com
 * License: MIT
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Text Domain: aevov-ai-core
 *
 * @package AevovAICore
 */

namespace Aevov\AICore;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('AEVOV_AI_CORE_VERSION', '1.0.0');
define('AEVOV_AI_CORE_PATH', plugin_dir_path(__FILE__));
define('AEVOV_AI_CORE_URL', plugin_dir_url(__FILE__));

/**
 * Main AI Core Plugin Class
 *
 * Provides:
 * - Unified AI provider interface (DeepSeek, MiniMax, OpenAI, Anthropic)
 * - .aev model framework (custom model format)
 * - Model extraction and conversion
 * - Comprehensive debugging engine
 * - Integration with all Aevov plugins
 */
class AICore
{
    /**
     * Singleton instance
     *
     * @var AICore|null
     */
    private static ?AICore $instance = null;

    /**
     * Provider manager
     *
     * @var Providers\ProviderManager|null
     */
    private ?Providers\ProviderManager $provider_manager = null;

    /**
     * Model manager
     *
     * @var Models\ModelManager|null
     */
    private ?Models\ModelManager $model_manager = null;

    /**
     * Debug engine
     *
     * @var Debug\DebugEngine|null
     */
    private ?Debug\DebugEngine $debug_engine = null;

    /**
     * Get singleton instance
     *
     * @return AICore
     */
    public static function get_instance(): AICore
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_components();
        $this->register_hooks();
    }

    /**
     * Load required files
     *
     * @return void
     */
    private function load_dependencies(): void
    {
        // Providers
        require_once AEVOV_AI_CORE_PATH . 'includes/providers/interface-ai-provider.php';
        require_once AEVOV_AI_CORE_PATH . 'includes/providers/class-provider-manager.php';
        require_once AEVOV_AI_CORE_PATH . 'includes/providers/class-deepseek-provider.php';
        require_once AEVOV_AI_CORE_PATH . 'includes/providers/class-minimax-provider.php';
        require_once AEVOV_AI_CORE_PATH . 'includes/providers/class-openai-provider.php';
        require_once AEVOV_AI_CORE_PATH . 'includes/providers/class-anthropic-provider.php';

        // Models
        require_once AEVOV_AI_CORE_PATH . 'includes/models/class-model-manager.php';
        require_once AEVOV_AI_CORE_PATH . 'includes/models/class-aev-model.php';
        require_once AEVOV_AI_CORE_PATH . 'includes/models/class-model-extractor.php';
        require_once AEVOV_AI_CORE_PATH . 'includes/models/class-model-converter.php';

        // Debug
        require_once AEVOV_AI_CORE_PATH . 'includes/debug/class-debug-engine.php';
        require_once AEVOV_AI_CORE_PATH . 'includes/debug/class-debug-logger.php';
        require_once AEVOV_AI_CORE_PATH . 'includes/debug/class-debug-profiler.php';
        require_once AEVOV_AI_CORE_PATH . 'includes/debug/class-error-handler.php';
    }

    /**
     * Initialize components
     *
     * @return void
     */
    private function init_components(): void
    {
        // Initialize debug engine FIRST (to catch everything)
        $this->debug_engine = new Debug\DebugEngine();

        // Initialize providers
        $this->provider_manager = new Providers\ProviderManager($this->debug_engine);

        // Initialize models
        $this->model_manager = new Models\ModelManager($this->debug_engine);

        // Register default providers
        $this->register_providers();
    }

    /**
     * Register AI providers
     *
     * @return void
     */
    private function register_providers(): void
    {
        $this->provider_manager->register('deepseek', new Providers\DeepSeekProvider());
        $this->provider_manager->register('minimax', new Providers\MiniMaxProvider());
        $this->provider_manager->register('openai', new Providers\OpenAIProvider());
        $this->provider_manager->register('anthropic', new Providers\AnthropicProvider());
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    private function register_hooks(): void
    {
        // Activation
        register_activation_hook(__FILE__, [$this, 'activate']);

        // Admin
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // AJAX handlers
        add_action('wp_ajax_aevov_test_provider', [$this, 'ajax_test_provider']);
        add_action('wp_ajax_aevov_test_providers', [$this, 'ajax_test_providers']);
        add_action('wp_ajax_aevov_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_aevov_extract_model', [$this, 'ajax_extract_model']);
        add_action('wp_ajax_aevov_export_model', [$this, 'ajax_export_model']);
        add_action('wp_ajax_aevov_delete_model', [$this, 'ajax_delete_model']);
        add_action('wp_ajax_aevov_get_logs', [$this, 'ajax_get_logs']);
        add_action('wp_ajax_aevov_clear_logs', [$this, 'ajax_clear_logs']);

        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Integration hooks
        add_action('plugins_loaded', [$this, 'integrate_with_plugins'], 20);
    }

    /**
     * Plugin activation
     *
     * @return void
     */
    public function activate(): void
    {
        $this->create_tables();
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     *
     * @return void
     */
    private function create_tables(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tables = [
            // AI models
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aev_models (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                model_id varchar(64) NOT NULL,
                name varchar(255) NOT NULL,
                version varchar(20) NOT NULL,
                base_provider varchar(50) NOT NULL,
                base_model varchar(100) NOT NULL,
                metadata longtext,
                training_data longtext,
                parameters longtext,
                system_prompt text,
                fine_tuning_config longtext,
                metrics longtext,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY model_id (model_id),
                KEY base_provider (base_provider)
            ) $charset_collate;",

            // Model usage logs
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aev_model_usage (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                model_id varchar(64) NOT NULL,
                provider varchar(50) NOT NULL,
                input_tokens int DEFAULT 0,
                output_tokens int DEFAULT 0,
                cost decimal(10,6) DEFAULT 0,
                latency int DEFAULT 0,
                success tinyint(1) DEFAULT 1,
                error_message text,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY model_id (model_id),
                KEY provider (provider),
                KEY created_at (created_at)
            ) $charset_collate;",

            // Debug logs
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aev_debug_logs (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                level varchar(20) NOT NULL,
                component varchar(100) NOT NULL,
                message text NOT NULL,
                context longtext,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY level (level),
                KEY component (component),
                KEY created_at (created_at)
            ) $charset_collate;",

            // Performance metrics
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aev_performance (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                operation varchar(100) NOT NULL,
                duration_ms int NOT NULL,
                memory_used bigint,
                peak_memory bigint,
                metadata longtext,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY operation (operation),
                KEY created_at (created_at)
            ) $charset_collate;"
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($tables as $table_sql) {
            dbDelta($table_sql);
        }
    }

    /**
     * Register admin menu
     *
     * @return void
     */
    public function register_admin_menu(): void
    {
        add_menu_page(
            __('AI Core', 'aevov-ai-core'),
            __('AI Core', 'aevov-ai-core'),
            'manage_options',
            'aevov-ai-core',
            [$this, 'render_dashboard'],
            'dashicons-code-standards',
            25
        );

        add_submenu_page(
            'aevov-ai-core',
            __('Providers', 'aevov-ai-core'),
            __('Providers', 'aevov-ai-core'),
            'manage_options',
            'aevov-ai-providers',
            [$this, 'render_providers_page']
        );

        add_submenu_page(
            'aevov-ai-core',
            __('Models', 'aevov-ai-core'),
            __('Models', 'aevov-ai-core'),
            'manage_options',
            'aevov-ai-models',
            [$this, 'render_models_page']
        );

        add_submenu_page(
            'aevov-ai-core',
            __('Debug Console', 'aevov-ai-core'),
            __('Debug', 'aevov-ai-core'),
            'manage_options',
            'aevov-ai-debug',
            [$this, 'render_debug_page']
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current page hook
     * @return void
     */
    public function enqueue_admin_assets(string $hook): void
    {
        if (strpos($hook, 'aevov-ai') === false) {
            return;
        }

        wp_enqueue_style(
            'aevov-ai-core-admin',
            AEVOV_AI_CORE_URL . 'admin/assets/admin.css',
            [],
            AEVOV_AI_CORE_VERSION
        );

        wp_enqueue_script(
            'aevov-ai-core-admin',
            AEVOV_AI_CORE_URL . 'admin/assets/admin.js',
            ['jquery', 'wp-api'],
            AEVOV_AI_CORE_VERSION,
            true
        );

        wp_localize_script('aevov-ai-core-admin', 'aevovAICore', [
            'apiUrl' => rest_url('aevov-ai-core/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'debugEnabled' => $this->debug_engine->is_enabled()
        ]);
    }

    /**
     * Register REST routes
     *
     * @return void
     */
    public function register_rest_routes(): void
    {
        // Provider routes
        register_rest_route('aevov-ai-core/v1', '/providers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_providers'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route('aevov-ai-core/v1', '/providers/(?P<provider>[a-z]+)/complete', [
            'methods' => 'POST',
            'callback' => [$this, 'complete'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        // Model routes
        register_rest_route('aevov-ai-core/v1', '/models', [
            'methods' => 'GET',
            'callback' => [$this, 'get_models'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route('aevov-ai-core/v1', '/models/extract', [
            'methods' => 'POST',
            'callback' => [$this, 'extract_model'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        // Debug routes
        register_rest_route('aevov-ai-core/v1', '/debug/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_debug_logs'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route('aevov-ai-core/v1', '/debug/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_debug_stats'],
            'permission_callback' => [$this, 'check_permission']
        ]);
    }

    /**
     * Check permission
     *
     * @return bool
     */
    public function check_permission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Integrate with other Aevov plugins
     *
     * @return void
     */
    public function integrate_with_plugins(): void
    {
        // Hook into Language Engine
        add_filter('aevov_language_engine_providers', [$this->provider_manager, 'get_providers']);
        add_filter('aevov_language_engine_complete', [$this, 'handle_language_completion'], 10, 2);

        // Hook into Image Engine
        add_filter('aevov_image_engine_providers', [$this->provider_manager, 'get_providers']);

        // Hook into Cognitive Engine
        add_filter('aevov_cognitive_providers', [$this->provider_manager, 'get_providers']);

        // Hook into all plugins for debugging
        add_action('aevov_error', [$this->debug_engine, 'log_error'], 10, 3);
        add_action('aevov_performance_metric', [$this->debug_engine, 'log_metric'], 10, 4);
    }

    /**
     * Handle language completion
     *
     * @param string $result Current result
     * @param array $args Arguments
     * @return string Completion result
     */
    public function handle_language_completion(string $result, array $args): string
    {
        $provider = $args['provider'] ?? 'deepseek';
        $model = $args['model'] ?? null;

        try {
            return $this->provider_manager->complete($provider, $args);
        } catch (\Exception $e) {
            $this->debug_engine->log_error('Language Engine', $e->getMessage(), $e);
            return $result;
        }
    }

    /**
     * Render dashboard page
     *
     * @return void
     */
    public function render_dashboard(): void
    {
        require_once AEVOV_AI_CORE_PATH . 'admin/templates/dashboard.php';
    }

    /**
     * Render providers page
     *
     * @return void
     */
    public function render_providers_page(): void
    {
        require_once AEVOV_AI_CORE_PATH . 'admin/templates/providers.php';
    }

    /**
     * Render models page
     *
     * @return void
     */
    public function render_models_page(): void
    {
        require_once AEVOV_AI_CORE_PATH . 'admin/templates/models.php';
    }

    /**
     * Render debug page
     *
     * @return void
     */
    public function render_debug_page(): void
    {
        require_once AEVOV_AI_CORE_PATH . 'admin/templates/debug.php';
    }

    /**
     * AJAX: Test provider
     *
     * @return void
     */
    public function ajax_test_provider(): void
    {
        check_ajax_referer('aevov_test_provider', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $provider = sanitize_text_field($_POST['provider'] ?? '');

        try {
            $result = $this->provider_manager->test_provider($provider);
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Test all providers
     *
     * @return void
     */
    public function ajax_test_providers(): void
    {
        check_ajax_referer('aevov_test_providers', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $results = [];
        $providers = $this->provider_manager->get_providers();

        foreach ($providers as $provider) {
            try {
                $results[$provider] = $this->provider_manager->test_provider($provider);
            } catch (\Exception $e) {
                $results[$provider] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        wp_send_json_success(['results' => $results, 'message' => 'Tests complete']);
    }

    /**
     * AJAX: Clear cache
     *
     * @return void
     */
    public function ajax_clear_cache(): void
    {
        check_ajax_referer('aevov_clear_cache', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $this->provider_manager->clear_cache();
        wp_send_json_success(['message' => 'Cache cleared successfully']);
    }

    /**
     * AJAX: Extract model
     *
     * @return void
     */
    public function ajax_extract_model(): void
    {
        check_ajax_referer('aevov_extract_model', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $model_name = sanitize_text_field($_POST['model_name'] ?? '');
        $base_provider = sanitize_text_field($_POST['base_provider'] ?? 'deepseek');
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');

        try {
            $filters = [];
            if ($start_date) $filters['start_date'] = $start_date;
            if ($end_date) $filters['end_date'] = $end_date;

            $config = [
                'name' => $model_name,
                'base_provider' => $base_provider
            ];

            $model = $this->model_manager->extract_from_database($filters, $config);
            $this->model_manager->save_model($model);

            wp_send_json_success(['message' => 'Model extracted successfully', 'model_id' => $model->get_id()]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Export model
     *
     * @return void
     */
    public function ajax_export_model(): void
    {
        check_ajax_referer('aevov_export_model', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }

        $model_id = sanitize_text_field($_GET['model_id'] ?? '');

        try {
            $model = $this->model_manager->load_model($model_id);

            if (!$model) {
                wp_die('Model not found');
            }

            $filename = sanitize_file_name($model->get_name()) . '.aev';

            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo wp_json_encode($model->to_array(), JSON_PRETTY_PRINT);
            exit;
        } catch (\Exception $e) {
            wp_die('Export failed: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Delete model
     *
     * @return void
     */
    public function ajax_delete_model(): void
    {
        check_ajax_referer('aevov_delete_model', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $model_id = sanitize_text_field($_POST['model_id'] ?? '');

        try {
            $result = $this->model_manager->delete_model($model_id);

            if ($result) {
                wp_send_json_success(['message' => 'Model deleted']);
            } else {
                wp_send_json_error(['message' => 'Delete failed']);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Get logs
     *
     * @return void
     */
    public function ajax_get_logs(): void
    {
        check_ajax_referer('aevov_get_logs', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $limit = intval($_GET['limit'] ?? 50);
        $filters = [];

        if (!empty($_GET['level'])) {
            $filters['level'] = sanitize_text_field($_GET['level']);
        }

        if (!empty($_GET['component'])) {
            $filters['component'] = sanitize_text_field($_GET['component']);
        }

        $logs = $this->debug_engine->get_recent_logs($limit, $filters);

        wp_send_json_success(['logs' => $logs]);
    }

    /**
     * AJAX: Clear logs
     *
     * @return void
     */
    public function ajax_clear_logs(): void
    {
        check_ajax_referer('aevov_clear_logs', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $days = intval($_POST['days'] ?? 7);
        $deleted = $this->debug_engine->clear_old_logs($days);

        wp_send_json_success(['message' => "Cleared {$deleted} log entries"]);
    }

    /**
     * REST: Get providers
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function get_providers(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'providers' => $this->provider_manager->get_all_providers()
        ]);
    }

    /**
     * REST: Complete
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function complete(\WP_REST_Request $request): \WP_REST_Response
    {
        $provider = $request->get_param('provider');
        $params = $request->get_json_params();

        try {
            $result = $this->provider_manager->complete($provider, $params);

            return new \WP_REST_Response([
                'success' => true,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * REST: Get models
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function get_models(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'models' => $this->model_manager->get_all_models()
        ]);
    }

    /**
     * REST: Extract model
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function extract_model(\WP_REST_Request $request): \WP_REST_Response
    {
        $source = $request->get_param('source');
        $format = $request->get_param('format');

        try {
            $model = $this->model_manager->extract_model($source, $format);

            return new \WP_REST_Response([
                'success' => true,
                'model' => $model
            ]);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * REST: Get debug logs
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function get_debug_logs(\WP_REST_Request $request): \WP_REST_Response
    {
        $limit = $request->get_param('limit') ?? 100;
        $level = $request->get_param('level');

        return new \WP_REST_Response([
            'success' => true,
            'logs' => $this->debug_engine->get_logs($limit, $level)
        ]);
    }

    /**
     * REST: Get debug stats
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function get_debug_stats(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'stats' => $this->debug_engine->get_stats()
        ]);
    }

    /**
     * Get provider manager
     *
     * @return Providers\ProviderManager
     */
    public function get_provider_manager(): Providers\ProviderManager
    {
        return $this->provider_manager;
    }

    /**
     * Get model manager
     *
     * @return Models\ModelManager
     */
    public function get_model_manager(): Models\ModelManager
    {
        return $this->model_manager;
    }

    /**
     * Get debug engine
     *
     * @return Debug\DebugEngine
     */
    public function get_debug_engine(): Debug\DebugEngine
    {
        return $this->debug_engine;
    }
}

// Initialize plugin
$GLOBALS['aevov_ai_core'] = AICore::get_instance();
