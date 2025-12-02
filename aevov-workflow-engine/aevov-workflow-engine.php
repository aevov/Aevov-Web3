<?php
/**
 * Plugin Name: Aevov Workflow Engine
 * Plugin URI: https://aevov.com/workflow-engine
 * Description: Visual workflow builder for all Aevov AI capabilities. Build, execute, and manage AI workflows with a drag-and-drop interface.
 * Version: 1.0.0
 * Author: Aevov
 * Author URI: https://aevov.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: aevov-workflow-engine
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

namespace AevovWorkflowEngine;

if (!defined('ABSPATH')) {
    exit;
}

define('AEVOV_WORKFLOW_ENGINE_VERSION', '1.0.0');
define('AEVOV_WORKFLOW_ENGINE_PATH', plugin_dir_path(__FILE__));
define('AEVOV_WORKFLOW_ENGINE_URL', plugin_dir_url(__FILE__));
define('AEVOV_WORKFLOW_ENGINE_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'AevovWorkflowEngine\\';
    $base_dir = AEVOV_WORKFLOW_ENGINE_PATH . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Main Plugin Class
 */
final class WorkflowEngine {

    private static ?WorkflowEngine $instance = null;
    private ?API\GatewayController $gateway = null;
    private ?Admin\AdminController $admin = null;

    public static function get_instance(): WorkflowEngine {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks(): void {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'init']);
        add_action('init', [$this, 'load_textdomain']);
    }

    public function init(): void {
        // Initialize API Gateway
        $this->gateway = new API\GatewayController();

        // Initialize Admin Interface
        if (is_admin()) {
            $this->admin = new Admin\AdminController();
        }

        // Initialize REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Add CORS headers for standalone mode
        add_filter('rest_pre_serve_request', [$this, 'add_cors_headers'], 10, 4);
    }

    public function load_textdomain(): void {
        load_plugin_textdomain(
            'aevov-workflow-engine',
            false,
            dirname(AEVOV_WORKFLOW_ENGINE_BASENAME) . '/languages'
        );
    }

    public function register_rest_routes(): void {
        $this->gateway->register_routes();
    }

    public function add_cors_headers($served, $result, $request, $server) {
        $origin = get_http_origin();
        $allowed_origins = $this->get_allowed_origins();

        if (in_array($origin, $allowed_origins) || $this->is_standalone_mode()) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Aevov-Token');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        }

        return $served;
    }

    private function get_allowed_origins(): array {
        $origins = [
            'http://localhost:3000',
            'http://localhost:3001',
            'http://127.0.0.1:3000',
            home_url(),
        ];

        return apply_filters('aevov_workflow_allowed_origins', $origins);
    }

    private function is_standalone_mode(): bool {
        return defined('AEVOV_WORKFLOW_STANDALONE') && AEVOV_WORKFLOW_STANDALONE;
    }

    public function activate(): void {
        $this->create_tables();
        $this->set_default_options();
        flush_rewrite_rules();
    }

    public function deactivate(): void {
        flush_rewrite_rules();
    }

    private function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = [];

        // Workflows table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aevov_workflows (
            id VARCHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            workflow_data LONGTEXT NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            is_template TINYINT(1) DEFAULT 0,
            is_published TINYINT(1) DEFAULT 0,
            version INT DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_template (is_template),
            KEY updated_at (updated_at)
        ) {$charset};";

        // Workflow executions table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aevov_workflow_executions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workflow_id VARCHAR(36) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            status ENUM('pending', 'running', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            inputs LONGTEXT,
            outputs LONGTEXT,
            execution_log LONGTEXT,
            started_at DATETIME,
            completed_at DATETIME,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY workflow_id (workflow_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};";

        // Workflow schedules table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aevov_workflow_schedules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workflow_id VARCHAR(36) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            schedule_type ENUM('once', 'recurring', 'cron') NOT NULL,
            schedule_config TEXT,
            next_run DATETIME,
            last_run DATETIME,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY workflow_id (workflow_id),
            KEY next_run (next_run),
            KEY is_active (is_active)
        ) {$charset};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        foreach ($sql as $query) {
            dbDelta($query);
        }
    }

    private function set_default_options(): void {
        $defaults = [
            'aevov_workflow_engine_version' => AEVOV_WORKFLOW_ENGINE_VERSION,
            'aevov_workflow_max_execution_time' => 300,
            'aevov_workflow_max_nodes' => 100,
            'aevov_workflow_enable_scheduling' => true,
            'aevov_workflow_enable_templates' => true,
            'aevov_workflow_standalone_port' => 3000,
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    public function get_gateway(): ?API\GatewayController {
        return $this->gateway;
    }

    public function get_admin(): ?Admin\AdminController {
        return $this->admin;
    }
}

// Initialize plugin
WorkflowEngine::get_instance();
