<?php
/**
 * Plugin Activator for APS Tools
 *
 * Handles all activation tasks including database table creation,
 * default option setup, and system requirements validation.
 *
 * @package APSTools
 * @subpackage Core
 * @since 1.0.0
 */

namespace APSTools;

use APSTools\DB\APS_Bloom_Tensors_DB;

class APS_Tools_Activator {

    /**
     * Minimum PHP version required
     *
     * @var string
     */
    const MIN_PHP_VERSION = '7.4';

    /**
     * Minimum WordPress version required
     *
     * @var string
     */
    const MIN_WP_VERSION = '5.0';

    /**
     * Minimum MySQL version required
     *
     * @var string
     */
    const MIN_MYSQL_VERSION = '5.6';

    /**
     * Activate the plugin
     *
     * @return void
     */
    public static function activate() {
        // Check system requirements
        if (!self::check_system_requirements()) {
            deactivate_plugins(plugin_basename(APSTOOLS_FILE));
            wp_die(
                __('APS Tools requires PHP ' . self::MIN_PHP_VERSION . ' or higher, WordPress ' . self::MIN_WP_VERSION . ' or higher, and MySQL ' . self::MIN_MYSQL_VERSION . ' or higher.', 'aps-tools'),
                'Plugin Activation Error',
                ['response' => 200, 'back_link' => true]
            );
        }

        // Check required plugins
        if (!self::check_required_plugins()) {
            deactivate_plugins(plugin_basename(APSTOOLS_FILE));
            wp_die(
                __('APS Tools requires both "Aevov Pattern Sync Protocol" and "BLOOM Pattern Recognition System" plugins to be installed and activated.', 'aps-tools'),
                'Plugin Dependency Error',
                ['response' => 200, 'back_link' => true]
            );
        }

        // Create database tables
        self::create_database_tables();

        // Set default options
        self::set_default_options();

        // Create required directories
        self::create_required_directories();

        // Schedule cron jobs
        self::schedule_cron_jobs();

        // Set activation flag
        update_option('aps_tools_activated', true);
        update_option('aps_tools_activation_time', current_time('timestamp'));
        update_option('aps_tools_version', APSTOOLS_VERSION);

        // Flush rewrite rules
        flush_rewrite_rules();

        // Log activation
        self::log_activation();

        // Trigger activation action
        do_action('aps_tools_activated');
    }

    /**
     * Check system requirements
     *
     * @return bool True if all requirements met
     */
    private static function check_system_requirements() {
        global $wpdb;

        // Check PHP version
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            return false;
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), self::MIN_WP_VERSION, '<')) {
            return false;
        }

        // Check MySQL version
        $mysql_version = $wpdb->db_version();
        if (version_compare($mysql_version, self::MIN_MYSQL_VERSION, '<')) {
            return false;
        }

        // Check required PHP extensions
        $required_extensions = ['json', 'mysqli', 'curl'];
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if required plugins are active
     *
     * @return bool True if dependencies met
     */
    private static function check_required_plugins() {
        // Check if AevovPatternSyncProtocol is active
        $aps_active = class_exists('APS\Core\APS_Core') || is_plugin_active('AevovPatternSyncProtocol/aevov-pattern-sync-protocol.php');

        // Check if BLOOM Pattern Recognition is active
        $bloom_active = class_exists('BLOOM_Pattern_System') || is_plugin_active('bloom-pattern-recognition/bloom-pattern-recognition.php');

        return $aps_active && $bloom_active;
    }

    /**
     * Create all required database tables
     *
     * @return void
     */
    private static function create_database_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Initialize tensor database handler (creates tables automatically)
        if (class_exists('\APSTools\DB\APS_Bloom_Tensors_DB')) {
            new APS_Bloom_Tensors_DB();
        }

        // Create integration status table
        $integration_table = $wpdb->prefix . 'aps_integration_status';
        $sql_integration = "CREATE TABLE IF NOT EXISTS {$integration_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plugin_name VARCHAR(255) NOT NULL,
            plugin_version VARCHAR(50) NULL,
            status ENUM('active', 'inactive', 'error') DEFAULT 'inactive',
            last_check DATETIME DEFAULT CURRENT_TIMESTAMP,
            error_message TEXT NULL,
            metadata LONGTEXT NULL,
            UNIQUE KEY plugin_name (plugin_name)
        ) {$charset_collate};";

        // Create scan history table
        $scan_table = $wpdb->prefix . 'aps_scan_history';
        $sql_scan = "CREATE TABLE IF NOT EXISTS {$scan_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            scan_type VARCHAR(50) NOT NULL,
            directory_path VARCHAR(500) NULL,
            files_scanned INT DEFAULT 0,
            files_processed INT DEFAULT 0,
            files_failed INT DEFAULT 0,
            status ENUM('running', 'completed', 'failed', 'cancelled') DEFAULT 'running',
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL,
            error_log LONGTEXT NULL,
            metadata LONGTEXT NULL,
            INDEX scan_type (scan_type),
            INDEX status (status),
            INDEX started_at (started_at)
        ) {$charset_collate};";

        // Create activity log table
        $log_table = $wpdb->prefix . 'aps_activity_log';
        $sql_log = "CREATE TABLE IF NOT EXISTS {$log_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            action_type VARCHAR(100) NOT NULL,
            action_description TEXT NULL,
            entity_type VARCHAR(100) NULL,
            entity_id BIGINT UNSIGNED NULL,
            status ENUM('success', 'warning', 'error', 'info') DEFAULT 'info',
            metadata LONGTEXT NULL,
            ip_address VARCHAR(50) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX user_id (user_id),
            INDEX action_type (action_type),
            INDEX entity_type (entity_type),
            INDEX status (status),
            INDEX created_at (created_at)
        ) {$charset_collate};";

        // Create system metrics table
        $metrics_table = $wpdb->prefix . 'aps_system_metrics';
        $sql_metrics = "CREATE TABLE IF NOT EXISTS {$metrics_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            metric_type VARCHAR(100) NOT NULL,
            metric_value DECIMAL(20,4) NULL,
            metric_data LONGTEXT NULL,
            recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX metric_type (metric_type),
            INDEX recorded_at (recorded_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_integration);
        dbDelta($sql_scan);
        dbDelta($sql_log);
        dbDelta($sql_metrics);

        // Log table creation
        self::log_activity('database_tables_created', 'All APS Tools database tables created successfully', 'system');
    }

    /**
     * Set default plugin options
     *
     * @return void
     */
    private static function set_default_options() {
        $default_options = [
            'aps_tools_enable_logging' => true,
            'aps_tools_log_level' => 'info',
            'aps_tools_storage_type' => 'database',
            'aps_tools_max_upload_size' => 10485760, // 10MB
            'aps_tools_chunk_size' => 1048576, // 1MB
            'aps_tools_batch_size' => 50,
            'aps_tools_enable_cron' => true,
            'aps_tools_cron_interval' => 'hourly',
            'aps_tools_enable_metrics' => true,
            'aps_tools_metrics_retention' => 30, // days
            'aps_tools_enable_notifications' => true,
            'aps_tools_notification_email' => get_option('admin_email'),
            'aps_tools_enable_api' => true,
            'aps_tools_api_rate_limit' => 100, // requests per minute
            'aps_tools_enable_cache' => true,
            'aps_tools_cache_ttl' => 3600, // 1 hour
            'aps_tools_debug_mode' => false,
            'aps_tools_enable_compression' => true,
            'aps_tools_compression_level' => 6
        ];

        foreach ($default_options as $option_name => $option_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $option_value);
            }
        }

        // Set default integrations status
        self::update_integration_status('AevovPatternSyncProtocol', 'active');
        self::update_integration_status('BLOOM Pattern Recognition', 'active');
    }

    /**
     * Create required directories
     *
     * @return void
     */
    private static function create_required_directories() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];

        $directories = [
            $base_dir . '/bloom-chunks',
            $base_dir . '/bloom-tensors',
            $base_dir . '/aps-exports',
            $base_dir . '/aps-imports',
            $base_dir . '/aps-cache',
            $base_dir . '/aps-logs'
        ];

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);

                // Add index.php to prevent directory listing
                $index_file = $dir . '/index.php';
                if (!file_exists($index_file)) {
                    file_put_contents($index_file, '<?php // Silence is golden');
                }

                // Add .htaccess for security
                $htaccess_file = $dir . '/.htaccess';
                if (!file_exists($htaccess_file)) {
                    file_put_contents($htaccess_file, 'deny from all');
                }
            }
        }

        self::log_activity('directories_created', 'All required directories created successfully', 'system');
    }

    /**
     * Schedule cron jobs
     *
     * @return void
     */
    private static function schedule_cron_jobs() {
        // Schedule cleanup cron
        if (!wp_next_scheduled('aps_tools_cleanup')) {
            wp_schedule_event(time(), 'daily', 'aps_tools_cleanup');
        }

        // Schedule metrics collection
        if (!wp_next_scheduled('aps_tools_collect_metrics')) {
            wp_schedule_event(time(), 'hourly', 'aps_tools_collect_metrics');
        }

        // Schedule integration health check
        if (!wp_next_scheduled('aps_tools_health_check')) {
            wp_schedule_event(time(), 'hourly', 'aps_tools_health_check');
        }

        self::log_activity('cron_jobs_scheduled', 'All cron jobs scheduled successfully', 'system');
    }

    /**
     * Log activation event
     *
     * @return void
     */
    private static function log_activation() {
        global $wpdb;

        $log_data = [
            'plugin_version' => APSTOOLS_VERSION,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'mysql_version' => $wpdb->db_version(),
            'multisite' => is_multisite(),
            'site_url' => get_site_url(),
            'activation_time' => current_time('mysql')
        ];

        self::log_activity(
            'plugin_activated',
            'APS Tools plugin activated successfully',
            'plugin',
            null,
            'success',
            $log_data
        );
    }

    /**
     * Update integration status
     *
     * @param string $plugin_name Plugin name
     * @param string $status Status
     * @return void
     */
    private static function update_integration_status($plugin_name, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'aps_integration_status';

        $wpdb->replace(
            $table,
            [
                'plugin_name' => $plugin_name,
                'status' => $status,
                'last_check' => current_time('mysql')
            ],
            ['%s', '%s', '%s']
        );
    }

    /**
     * Log activity
     *
     * @param string $action_type Action type
     * @param string $description Description
     * @param string $entity_type Entity type
     * @param int $entity_id Entity ID
     * @param string $status Status
     * @param array $metadata Additional metadata
     * @return void
     */
    private static function log_activity($action_type, $description, $entity_type = null, $entity_id = null, $status = 'success', $metadata = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'aps_activity_log';

        $wpdb->insert(
            $table,
            [
                'user_id' => get_current_user_id(),
                'action_type' => $action_type,
                'action_description' => $description,
                'entity_type' => $entity_type,
                'entity_id' => $entity_id,
                'status' => $status,
                'metadata' => !empty($metadata) ? json_encode($metadata) : null,
                'ip_address' => self::get_client_ip()
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private static function get_client_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }
}
