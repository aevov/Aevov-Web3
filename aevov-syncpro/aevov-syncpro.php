<?php
/**
 * Plugin Name: AevSyncPro
 * Description: Intelligent AI-powered workflow orchestration with full Aevov ecosystem context. Generates ready-to-use configurations through natural language workflows.
 * Version: 1.0.0
 * Author: Aevov Team
 * Text Domain: aevov-syncpro
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

namespace AevovSyncPro;

if (!defined('ABSPATH')) {
    exit;
}

define('AEVOV_SYNCPRO_VERSION', '1.0.0');
define('AEVOV_SYNCPRO_PATH', plugin_dir_path(__FILE__));
define('AEVOV_SYNCPRO_URL', plugin_dir_url(__FILE__));

/**
 * Main AevSyncPro Class
 *
 * Orchestrates intelligent workflow building with complete system context,
 * real-time database synchronization, and configuration generation.
 */
class AevSyncPro {

    private static ?AevSyncPro $instance = null;

    private Api\SyncProController $api_controller;
    private Api\WorkflowIntegration $workflow_integration;
    private Providers\SystemContextProvider $context_provider;
    private Sync\DatabaseSyncController $db_sync;
    private Generators\ConfigurationGenerator $config_generator;
    private Providers\AIOrchestrator $ai_orchestrator;

    /**
     * Singleton instance
     */
    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies(): void {
        // Core classes
        require_once AEVOV_SYNCPRO_PATH . 'includes/providers/class-system-context-provider.php';
        require_once AEVOV_SYNCPRO_PATH . 'includes/providers/class-ai-orchestrator.php';
        require_once AEVOV_SYNCPRO_PATH . 'includes/sync/class-database-sync-controller.php';
        require_once AEVOV_SYNCPRO_PATH . 'includes/generators/class-configuration-generator.php';
        require_once AEVOV_SYNCPRO_PATH . 'includes/api/class-syncpro-controller.php';
        require_once AEVOV_SYNCPRO_PATH . 'includes/api/class-workflow-integration.php';
        require_once AEVOV_SYNCPRO_PATH . 'includes/templates/class-workflow-templates.php';

        // Initialize components
        $this->context_provider = new Providers\SystemContextProvider();
        $this->ai_orchestrator = new Providers\AIOrchestrator($this->context_provider);
        $this->db_sync = new Sync\DatabaseSyncController();
        $this->config_generator = new Generators\ConfigurationGenerator($this->context_provider);
        $this->api_controller = new Api\SyncProController(
            $this->context_provider,
            $this->ai_orchestrator,
            $this->db_sync,
            $this->config_generator
        );

        // Initialize workflow engine integration
        $this->workflow_integration = new Api\WorkflowIntegration(
            $this->context_provider,
            $this->ai_orchestrator,
            $this->db_sync,
            $this->config_generator
        );
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        add_action('rest_api_init', [$this->api_controller, 'register_routes']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('init', [$this, 'register_post_types']);

        // Hook into workflow execution for real-time sync
        add_action('aevov_workflow_node_executed', [$this->db_sync, 'on_node_executed'], 10, 3);
        add_action('aevov_workflow_completed', [$this->db_sync, 'on_workflow_completed'], 10, 2);

        // Register AevSyncPro as a workflow capability
        add_filter('aevov_workflow_capabilities', [$this, 'register_capabilities']);

        // Activation/Deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    /**
     * Register custom post types for configurations
     */
    public function register_post_types(): void {
        register_post_type('aevsync_config', [
            'labels' => [
                'name' => __('SyncPro Configurations', 'aevov-syncpro'),
                'singular_name' => __('Configuration', 'aevov-syncpro'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'custom-fields'],
            'capability_type' => 'post',
        ]);

        register_post_type('aevsync_template', [
            'labels' => [
                'name' => __('SyncPro Templates', 'aevov-syncpro'),
                'singular_name' => __('Template', 'aevov-syncpro'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor', 'custom-fields'],
        ]);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu(): void {
        add_menu_page(
            __('AevSyncPro', 'aevov-syncpro'),
            __('AevSyncPro', 'aevov-syncpro'),
            'manage_options',
            'aevov-syncpro',
            [$this, 'render_admin_page'],
            'dashicons-controls-repeat',
            30
        );

        add_submenu_page(
            'aevov-syncpro',
            __('Dashboard', 'aevov-syncpro'),
            __('Dashboard', 'aevov-syncpro'),
            'manage_options',
            'aevov-syncpro',
            [$this, 'render_admin_page']
        );

        add_submenu_page(
            'aevov-syncpro',
            __('Configurations', 'aevov-syncpro'),
            __('Configurations', 'aevov-syncpro'),
            'manage_options',
            'aevov-syncpro-configs',
            [$this, 'render_configs_page']
        );

        add_submenu_page(
            'aevov-syncpro',
            __('Templates', 'aevov-syncpro'),
            __('Templates', 'aevov-syncpro'),
            'manage_options',
            'aevov-syncpro-templates',
            [$this, 'render_templates_page']
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts(string $hook): void {
        if (strpos($hook, 'aevov-syncpro') === false) {
            return;
        }

        wp_enqueue_script(
            'aevov-syncpro-app',
            AEVOV_SYNCPRO_URL . 'build/index.js',
            ['wp-element', 'wp-components'],
            AEVOV_SYNCPRO_VERSION,
            true
        );

        wp_enqueue_style(
            'aevov-syncpro-styles',
            AEVOV_SYNCPRO_URL . 'build/index.css',
            [],
            AEVOV_SYNCPRO_VERSION
        );

        wp_localize_script('aevov-syncpro-app', 'aevSyncProConfig', [
            'apiUrl' => rest_url('aevov-syncpro/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'systemContext' => $this->context_provider->get_full_context(),
            'capabilities' => $this->context_provider->get_available_capabilities(),
        ]);
    }

    /**
     * Render admin pages
     */
    public function render_admin_page(): void {
        echo '<div id="aevov-syncpro-root" class="wrap"></div>';
    }

    public function render_configs_page(): void {
        echo '<div id="aevov-syncpro-configs" class="wrap"></div>';
    }

    public function render_templates_page(): void {
        echo '<div id="aevov-syncpro-templates" class="wrap"></div>';
    }

    /**
     * Register AevSyncPro capabilities for workflow engine
     */
    public function register_capabilities(array $capabilities): array {
        $capabilities['syncpro'] = [
            'type' => 'syncpro',
            'label' => 'AevSyncPro',
            'category' => 'capability',
            'description' => 'AI-powered configuration generation and system orchestration',
            'icon' => 'Sparkles',
            'color' => '#8B5CF6',
            'inputs' => [
                ['id' => 'prompt', 'type' => 'string', 'label' => 'Configuration Prompt'],
                ['id' => 'context', 'type' => 'object', 'label' => 'Additional Context'],
            ],
            'outputs' => [
                ['id' => 'configuration', 'type' => 'object', 'label' => 'Generated Configuration'],
                ['id' => 'sync_results', 'type' => 'array', 'label' => 'Sync Results'],
            ],
            'configFields' => [
                [
                    'name' => 'mode',
                    'label' => 'Generation Mode',
                    'type' => 'select',
                    'options' => [
                        ['value' => 'generate', 'label' => 'Generate New Configuration'],
                        ['value' => 'modify', 'label' => 'Modify Existing'],
                        ['value' => 'analyze', 'label' => 'Analyze & Recommend'],
                    ],
                    'default' => 'generate',
                ],
                [
                    'name' => 'target',
                    'label' => 'Target System',
                    'type' => 'select',
                    'options' => $this->get_target_options(),
                    'default' => 'all',
                ],
                [
                    'name' => 'auto_apply',
                    'label' => 'Auto-Apply Configuration',
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
            'available' => true,
        ];

        return $capabilities;
    }

    /**
     * Get target system options
     */
    private function get_target_options(): array {
        return [
            ['value' => 'all', 'label' => 'All Systems'],
            ['value' => 'ai_engines', 'label' => 'AI Engines'],
            ['value' => 'storage', 'label' => 'Storage Systems'],
            ['value' => 'workflows', 'label' => 'Workflows'],
            ['value' => 'patterns', 'label' => 'Pattern Recognition'],
            ['value' => 'memory', 'label' => 'Memory Core'],
            ['value' => 'security', 'label' => 'Security Settings'],
            ['value' => 'network', 'label' => 'Network/Meshcore'],
        ];
    }

    /**
     * Plugin activation
     */
    public function activate(): void {
        $this->create_tables();
        $this->install_default_templates();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void {
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Configuration history table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aevsync_config_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            config_id BIGINT UNSIGNED NOT NULL,
            config_type VARCHAR(50) NOT NULL,
            config_data LONGTEXT NOT NULL,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            applied_by BIGINT UNSIGNED,
            workflow_id VARCHAR(36),
            execution_id BIGINT UNSIGNED,
            rollback_data LONGTEXT,
            status ENUM('pending', 'applied', 'rolled_back', 'failed') DEFAULT 'pending',
            INDEX idx_config_id (config_id),
            INDEX idx_workflow_id (workflow_id),
            INDEX idx_applied_at (applied_at)
        ) $charset_collate;";

        // Sync operations table
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aevsync_operations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            operation_type VARCHAR(50) NOT NULL,
            target_system VARCHAR(100) NOT NULL,
            target_entity VARCHAR(255),
            operation_data LONGTEXT NOT NULL,
            result_data LONGTEXT,
            status ENUM('pending', 'in_progress', 'completed', 'failed') DEFAULT 'pending',
            workflow_execution_id BIGINT UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME,
            error_message TEXT,
            INDEX idx_status (status),
            INDEX idx_target (target_system, target_entity),
            INDEX idx_workflow_execution (workflow_execution_id)
        ) $charset_collate;";

        // Context cache table (for performance)
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aevsync_context_cache (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cache_key VARCHAR(255) UNIQUE NOT NULL,
            context_data LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            INDEX idx_expires (expires_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Install default workflow templates
     */
    private function install_default_templates(): void {
        $templates = [
            [
                'title' => 'Complete System Setup',
                'description' => 'Full Aevo ecosystem configuration from scratch',
                'workflow' => $this->get_system_setup_template(),
            ],
            [
                'title' => 'AI Engine Configuration',
                'description' => 'Configure all AI engines with optimal settings',
                'workflow' => $this->get_ai_config_template(),
            ],
            [
                'title' => 'Storage & Memory Setup',
                'description' => 'Configure AevStore, Memory Core, and pattern storage',
                'workflow' => $this->get_storage_template(),
            ],
            [
                'title' => 'Security Hardening',
                'description' => 'Apply security best practices across the system',
                'workflow' => $this->get_security_template(),
            ],
            [
                'title' => 'Performance Optimization',
                'description' => 'Optimize system performance and caching',
                'workflow' => $this->get_performance_template(),
            ],
        ];

        foreach ($templates as $template) {
            $exists = get_posts([
                'post_type' => 'aevsync_template',
                'title' => $template['title'],
                'posts_per_page' => 1,
            ]);

            if (empty($exists)) {
                wp_insert_post([
                    'post_type' => 'aevsync_template',
                    'post_title' => $template['title'],
                    'post_status' => 'publish',
                    'meta_input' => [
                        'description' => $template['description'],
                        'workflow_data' => wp_json_encode($template['workflow']),
                        'is_default' => true,
                    ],
                ]);
            }
        }
    }

    /**
     * Template definitions
     */
    private function get_system_setup_template(): array {
        return [
            'id' => 'tpl_system_setup',
            'name' => 'Complete System Setup',
            'nodes' => [
                [
                    'id' => 'input_1',
                    'type' => 'input',
                    'position' => ['x' => 100, 'y' => 100],
                    'data' => ['label' => 'User Requirements', 'inputType' => 'text'],
                ],
                [
                    'id' => 'syncpro_1',
                    'type' => 'syncpro',
                    'position' => ['x' => 300, 'y' => 100],
                    'data' => ['label' => 'Analyze Requirements', 'mode' => 'analyze'],
                ],
                [
                    'id' => 'syncpro_2',
                    'type' => 'syncpro',
                    'position' => ['x' => 500, 'y' => 100],
                    'data' => ['label' => 'Generate Configs', 'mode' => 'generate', 'target' => 'all'],
                ],
                [
                    'id' => 'output_1',
                    'type' => 'output',
                    'position' => ['x' => 700, 'y' => 100],
                    'data' => ['label' => 'Configuration Bundle'],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'input_1', 'target' => 'syncpro_1'],
                ['id' => 'e2', 'source' => 'syncpro_1', 'target' => 'syncpro_2'],
                ['id' => 'e3', 'source' => 'syncpro_2', 'target' => 'output_1'],
            ],
        ];
    }

    private function get_ai_config_template(): array {
        return [
            'id' => 'tpl_ai_config',
            'name' => 'AI Engine Configuration',
            'nodes' => [
                [
                    'id' => 'input_1',
                    'type' => 'input',
                    'position' => ['x' => 100, 'y' => 200],
                    'data' => ['label' => 'AI Requirements'],
                ],
                [
                    'id' => 'cognitive_1',
                    'type' => 'cognitive',
                    'position' => ['x' => 300, 'y' => 100],
                    'data' => ['label' => 'Analyze Use Cases'],
                ],
                [
                    'id' => 'syncpro_1',
                    'type' => 'syncpro',
                    'position' => ['x' => 500, 'y' => 200],
                    'data' => ['label' => 'Configure Engines', 'mode' => 'generate', 'target' => 'ai_engines'],
                ],
                [
                    'id' => 'output_1',
                    'type' => 'output',
                    'position' => ['x' => 700, 'y' => 200],
                    'data' => ['label' => 'AI Configuration'],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'input_1', 'target' => 'cognitive_1'],
                ['id' => 'e2', 'source' => 'cognitive_1', 'target' => 'syncpro_1'],
                ['id' => 'e3', 'source' => 'syncpro_1', 'target' => 'output_1'],
            ],
        ];
    }

    private function get_storage_template(): array {
        return [
            'id' => 'tpl_storage',
            'name' => 'Storage & Memory Setup',
            'nodes' => [],
            'edges' => [],
        ];
    }

    private function get_security_template(): array {
        return [
            'id' => 'tpl_security',
            'name' => 'Security Hardening',
            'nodes' => [],
            'edges' => [],
        ];
    }

    private function get_performance_template(): array {
        return [
            'id' => 'tpl_performance',
            'name' => 'Performance Optimization',
            'nodes' => [],
            'edges' => [],
        ];
    }

    /**
     * Getters for components
     */
    public function get_context_provider(): Providers\SystemContextProvider {
        return $this->context_provider;
    }

    public function get_ai_orchestrator(): Providers\AIOrchestrator {
        return $this->ai_orchestrator;
    }

    public function get_db_sync(): Sync\DatabaseSyncController {
        return $this->db_sync;
    }

    public function get_config_generator(): Generators\ConfigurationGenerator {
        return $this->config_generator;
    }
}

/**
 * Initialize AevSyncPro
 */
function aevov_syncpro(): AevSyncPro {
    return AevSyncPro::instance();
}

// Initialize on plugins loaded
add_action('plugins_loaded', 'AevovSyncPro\\aevov_syncpro');
