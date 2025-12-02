<?php
/**
 * Plugin Name: Aevov Core
 * Plugin URI: https://aevov.com/plugins/aevov-core
 * Description: Core infrastructure for the Aevov ecosystem - provides API key management, rate limiting, and shared utilities for all 34 Aevov plugins
 * Version: 1.0.0
 * Author: Aevov Team
 * Author URI: https://aevov.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: aevov-core
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.0
 *
 * @package AevovCore
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Define plugin constants
 */
define('AEVOV_CORE_VERSION', '1.0.0');
define('AEVOV_CORE_PLUGIN_FILE', __FILE__);
define('AEVOV_CORE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AEVOV_CORE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AEVOV_CORE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Aevov Core Plugin Class
 */
class AevovCore {

    /**
     * Plugin instance
     *
     * @var AevovCore
     */
    private static $instance = null;

    /**
     * Rate limiter instance
     *
     * @var \Aevov\Core\RateLimiter
     */
    private $rate_limiter = null;

    /**
     * Get plugin instance
     *
     * @return AevovCore
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Load dependencies
        $this->load_dependencies();

        // Initialize components
        add_action('plugins_loaded', [$this, 'init'], 5);

        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Admin interface
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
        }

        // REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Load API Key Manager
        require_once AEVOV_CORE_PLUGIN_DIR . 'includes/class-api-key-manager.php';

        // Load Rate Limiter
        require_once AEVOV_CORE_PLUGIN_DIR . 'includes/class-rate-limiter.php';
    }

    /**
     * Initialize plugin components
     */
    public function init() {
        // Initialize rate limiter
        $this->rate_limiter = new \Aevov\Core\RateLimiter([
            'strategy' => get_option('aevov_rate_limit_strategy', 'combined'),
            'use_redis' => get_option('aevov_rate_limit_use_redis', true),
            'redis_host' => getenv('WP_REDIS_HOST') ?: get_option('aevov_redis_host', 'redis'),
            'redis_port' => getenv('WP_REDIS_PORT') ?: get_option('aevov_redis_port', 6379),
            'enable_headers' => get_option('aevov_rate_limit_headers', true),
            'log_violations' => get_option('aevov_rate_limit_log', true),
        ]);

        // Fire action for other plugins
        do_action('aevov_core_loaded', $this);

        // Load text domain for translations
        load_plugin_textdomain('aevov-core', false, dirname(AEVOV_CORE_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        add_option('aevov_rate_limit_strategy', 'combined');
        add_option('aevov_rate_limit_use_redis', true);
        add_option('aevov_rate_limit_headers', true);
        add_option('aevov_rate_limit_log', true);
        add_option('aevov_redis_host', 'redis');
        add_option('aevov_redis_port', 6379);

        // Flush rewrite rules
        flush_rewrite_rules();

        error_log('[Aevov Core] Plugin activated - version ' . AEVOV_CORE_VERSION);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup scheduled events if any
        wp_clear_scheduled_hook('aevov_rate_limit_cleanup');

        // Flush rewrite rules
        flush_rewrite_rules();

        error_log('[Aevov Core] Plugin deactivated');
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Aevov Core', 'aevov-core'),
            __('Aevov Core', 'aevov-core'),
            'manage_options',
            'aevov-core',
            [$this, 'render_admin_page'],
            'dashicons-shield',
            30
        );

        add_submenu_page(
            'aevov-core',
            __('API Keys', 'aevov-core'),
            __('API Keys', 'aevov-core'),
            'manage_options',
            'aevov-core',
            [$this, 'render_admin_page']
        );

        add_submenu_page(
            'aevov-core',
            __('Rate Limiting', 'aevov-core'),
            __('Rate Limiting', 'aevov-core'),
            'manage_options',
            'aevov-core-rate-limits',
            [$this, 'render_rate_limits_page']
        );

        add_submenu_page(
            'aevov-core',
            __('System Status', 'aevov-core'),
            __('System Status', 'aevov-core'),
            'manage_options',
            'aevov-core-status',
            [$this, 'render_status_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Rate limiting settings
        register_setting('aevov_core_settings', 'aevov_rate_limit_strategy');
        register_setting('aevov_core_settings', 'aevov_rate_limit_use_redis');
        register_setting('aevov_core_settings', 'aevov_rate_limit_headers');
        register_setting('aevov_core_settings', 'aevov_rate_limit_log');
        register_setting('aevov_core_settings', 'aevov_redis_host');
        register_setting('aevov_core_settings', 'aevov_redis_port');
    }

    /**
     * Render main admin page (API Keys)
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'aevov-core'));
        }

        // Handle form submissions
        if (isset($_POST['aevov_save_api_key']) && wp_verify_nonce($_POST['aevov_api_key_nonce'], 'aevov_save_api_key')) {
            $this->handle_api_key_save();
        }

        if (isset($_POST['aevov_delete_api_key']) && wp_verify_nonce($_POST['aevov_api_key_nonce'], 'aevov_delete_api_key')) {
            $this->handle_api_key_delete();
        }

        // Get all stored API keys
        $stored_keys = \Aevov\Core\APIKeyManager::get_all_keys_masked();

        include AEVOV_CORE_PLUGIN_DIR . 'admin/api-keys-page.php';
    }

    /**
     * Render rate limits page
     */
    public function render_rate_limits_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'aevov-core'));
        }

        // Handle form submissions
        if (isset($_POST['aevov_save_rate_limits']) && check_admin_referer('aevov_rate_limits')) {
            update_option('aevov_rate_limit_strategy', sanitize_text_field($_POST['rate_limit_strategy']));
            update_option('aevov_rate_limit_use_redis', isset($_POST['use_redis']));
            update_option('aevov_rate_limit_headers', isset($_POST['enable_headers']));
            update_option('aevov_rate_limit_log', isset($_POST['log_violations']));
            update_option('aevov_redis_host', sanitize_text_field($_POST['redis_host']));
            update_option('aevov_redis_port', (int) $_POST['redis_port']);

            echo '<div class="notice notice-success"><p>' . __('Rate limiting settings saved.', 'aevov-core') . '</p></div>';
        }

        if (isset($_POST['aevov_clear_limits']) && check_admin_referer('aevov_clear_limits')) {
            $identifier = sanitize_text_field($_POST['identifier']);
            $cleared = $this->rate_limiter->clear_limits($identifier);
            echo '<div class="notice notice-success"><p>' . sprintf(__('Cleared %d rate limit entries.', 'aevov-core'), $cleared) . '</p></div>';
        }

        // Get statistics
        $stats = $this->rate_limiter->get_statistics();

        include AEVOV_CORE_PLUGIN_DIR . 'admin/rate-limits-page.php';
    }

    /**
     * Render system status page
     */
    public function render_status_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'aevov-core'));
        }

        // Gather system information
        $status = [
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'aevov_core_version' => AEVOV_CORE_VERSION,
            'redis_available' => class_exists('Redis'),
            'openssl_available' => function_exists('openssl_encrypt'),
            'curl_available' => function_exists('curl_init'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
        ];

        // Test Redis connection
        if ($status['redis_available']) {
            try {
                $redis = new Redis();
                $connected = $redis->connect(
                    get_option('aevov_redis_host', 'redis'),
                    (int) get_option('aevov_redis_port', 6379),
                    2.0
                );
                $status['redis_connected'] = $connected;
                if ($connected) {
                    $redis->ping();
                    $status['redis_ping'] = true;
                }
            } catch (Exception $e) {
                $status['redis_connected'] = false;
                $status['redis_error'] = $e->getMessage();
            }
        }

        // Count active Aevov plugins
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);
        $aevov_plugins = [];

        foreach ($all_plugins as $plugin_path => $plugin_data) {
            if (strpos($plugin_data['Name'], 'Aevov') !== false ||
                strpos($plugin_data['Name'], 'AROS') !== false ||
                strpos($plugin_data['Name'], 'Bloom') !== false) {
                $aevov_plugins[$plugin_path] = [
                    'name' => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'active' => in_array($plugin_path, $active_plugins),
                ];
            }
        }

        $status['aevov_plugins'] = $aevov_plugins;

        include AEVOV_CORE_PLUGIN_DIR . 'admin/status-page.php';
    }

    /**
     * Handle API key save
     */
    private function handle_api_key_save() {
        $plugin = sanitize_text_field($_POST['plugin_name']);
        $key_name = sanitize_text_field($_POST['key_name']);
        $api_key = sanitize_text_field($_POST['api_key']);

        // Validate key type if specified
        $key_type = $_POST['key_type'] ?? 'generic';
        $validation = \Aevov\Core\APIKeyManager::validate($api_key, $key_type);

        if (is_wp_error($validation)) {
            echo '<div class="notice notice-error"><p>' . esc_html($validation->get_error_message()) . '</p></div>';
            return;
        }

        // Store encrypted key
        $stored = \Aevov\Core\APIKeyManager::store($plugin, $key_name, $api_key);

        if ($stored) {
            echo '<div class="notice notice-success"><p>' . __('API key saved successfully.', 'aevov-core') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Failed to save API key.', 'aevov-core') . '</p></div>';
        }
    }

    /**
     * Handle API key delete
     */
    private function handle_api_key_delete() {
        $plugin = sanitize_text_field($_POST['plugin_name']);
        $key_name = sanitize_text_field($_POST['key_name']);

        $deleted = \Aevov\Core\APIKeyManager::delete($plugin, $key_name);

        if ($deleted) {
            echo '<div class="notice notice-success"><p>' . __('API key deleted successfully.', 'aevov-core') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Failed to delete API key.', 'aevov-core') . '</p></div>';
        }
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Endpoint for testing rate limiting
        register_rest_route('aevov/v1', '/core/test', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_test_endpoint'],
            'permission_callback' => '__return_true',
        ]);

        // Endpoint for system status (requires authentication)
        register_rest_route('aevov/v1', '/core/status', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_status_endpoint'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * REST API test endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function rest_test_endpoint($request) {
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Aevov Core is working correctly',
            'version' => AEVOV_CORE_VERSION,
            'timestamp' => current_time('mysql'),
        ], 200);
    }

    /**
     * REST API status endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function rest_status_endpoint($request) {
        $stats = $this->rate_limiter ? $this->rate_limiter->get_statistics() : null;

        return new \WP_REST_Response([
            'success' => true,
            'version' => AEVOV_CORE_VERSION,
            'php_version' => PHP_VERSION,
            'redis_available' => class_exists('Redis'),
            'rate_limit_stats' => $stats,
            'timestamp' => current_time('mysql'),
        ], 200);
    }

    /**
     * Get rate limiter instance
     *
     * @return \Aevov\Core\RateLimiter|null
     */
    public function get_rate_limiter() {
        return $this->rate_limiter;
    }
}

/**
 * Initialize the plugin
 *
 * @return AevovCore
 */
function aevov_core() {
    return AevovCore::get_instance();
}

// Start the plugin
aevov_core();

/**
 * Helper functions for other plugins to use
 */

/**
 * Store an API key securely
 *
 * @param string $plugin_name Plugin name
 * @param string $key_name Key name (e.g., 'openai', 'stability')
 * @param string $api_key API key to store
 * @return bool Success status
 */
function aevov_store_api_key($plugin_name, $key_name, $api_key) {
    return \Aevov\Core\APIKeyManager::store($plugin_name, $key_name, $api_key);
}

/**
 * Retrieve an API key
 *
 * @param string $plugin_name Plugin name
 * @param string $key_name Key name
 * @return string Decrypted API key
 */
function aevov_get_api_key($plugin_name, $key_name) {
    return \Aevov\Core\APIKeyManager::retrieve($plugin_name, $key_name);
}

/**
 * Delete an API key
 *
 * @param string $plugin_name Plugin name
 * @param string $key_name Key name
 * @return bool Success status
 */
function aevov_delete_api_key($plugin_name, $key_name) {
    return \Aevov\Core\APIKeyManager::delete($plugin_name, $key_name);
}

/**
 * Get the rate limiter instance
 *
 * @return \Aevov\Core\RateLimiter|null
 */
function aevov_get_rate_limiter() {
    return aevov_core()->get_rate_limiter();
}

/**
 * Clear rate limits for an identifier
 *
 * @param string $identifier Identifier to clear
 * @return int Number of keys cleared
 */
function aevov_clear_rate_limits($identifier) {
    $limiter = aevov_get_rate_limiter();
    return $limiter ? $limiter->clear_limits($identifier) : 0;
}
