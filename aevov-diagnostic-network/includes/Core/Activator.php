<?php
/**
 * Aevov Diagnostic Network - Plugin Activator
 * 
 * Handles plugin activation tasks including database setup,
 * option initialization, and system preparation.
 * 
 * @package AevovDiagnosticNetwork
 * @subpackage Core
 * @since 1.0.0
 */

namespace ADN\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Activator Class
 * 
 * Manages all activation procedures for the diagnostic network plugin
 */
class Activator {

    /**
     * Plugin activation handler
     * 
     * @since 1.0.0
     */
    public static function activate() {
        // Create database tables
        self::create_database_tables();
        
        // Initialize plugin options
        self::initialize_options();
        
        // Set up scheduled events
        self::setup_scheduled_events();
        
        // Create necessary directories
        self::create_directories();
        
        // Set activation timestamp
        update_option('adn_activated_at', current_time('timestamp'));
        
        // Set plugin version
        update_option('adn_version', ADN_VERSION);
        
        // Log activation
        error_log('Aevov Diagnostic Network activated successfully');
    }

    /**
     * Create database tables
     * 
     * @since 1.0.0
     */
    private static function create_database_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Component tests table
        $table_name = $wpdb->prefix . 'adn_component_tests';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            component_id varchar(100) NOT NULL,
            component_name varchar(255) NOT NULL,
            component_type varchar(50) NOT NULL,
            test_type varchar(50) NOT NULL,
            test_status varchar(20) NOT NULL,
            test_message text,
            test_data longtext,
            execution_time float,
            tested_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY component_id (component_id),
            KEY component_type (component_type),
            KEY test_type (test_type),
            KEY test_status (test_status),
            KEY tested_at (tested_at)
        ) $charset_collate;";

        // Test results table
        $table_name = $wpdb->prefix . 'adn_test_results';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            component_id varchar(100) NOT NULL,
            overall_status varchar(20) NOT NULL,
            total_tests int(11) NOT NULL DEFAULT 0,
            passed_tests int(11) NOT NULL DEFAULT 0,
            failed_tests int(11) NOT NULL DEFAULT 0,
            warning_tests int(11) NOT NULL DEFAULT 0,
            test_summary longtext,
            performance_metrics longtext,
            recommendations longtext,
            tested_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY component_id (component_id),
            KEY overall_status (overall_status),
            KEY tested_at (tested_at)
        ) $charset_collate;";

        // AI fix history table
        $table_name = $wpdb->prefix . 'adn_ai_fixes';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            component_id varchar(100) NOT NULL,
            issue_description text NOT NULL,
            ai_engine varchar(50) NOT NULL,
            fix_type varchar(50) NOT NULL,
            fix_description text,
            fix_code longtext,
            files_modified longtext,
            fix_status varchar(20) NOT NULL,
            fix_result text,
            applied_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY component_id (component_id),
            KEY ai_engine (ai_engine),
            KEY fix_type (fix_type),
            KEY fix_status (fix_status),
            KEY applied_at (applied_at)
        ) $charset_collate;";

        // System health log table
        $table_name = $wpdb->prefix . 'adn_health_log';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            health_score int(11) NOT NULL,
            total_components int(11) NOT NULL,
            healthy_components int(11) NOT NULL,
            warning_components int(11) NOT NULL,
            failed_components int(11) NOT NULL,
            critical_issues longtext,
            recommendations longtext,
            system_metrics longtext,
            logged_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY health_score (health_score),
            KEY logged_at (logged_at)
        ) $charset_collate;";

        // Component registry table
        $table_name = $wpdb->prefix . 'adn_components';
        $sql .= "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            component_id varchar(100) NOT NULL,
            component_name varchar(255) NOT NULL,
            component_type varchar(50) NOT NULL,
            component_path varchar(500),
            component_class varchar(255),
            dependencies longtext,
            configuration longtext,
            status varchar(20) NOT NULL DEFAULT 'unknown',
            last_tested datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY component_type (component_type),
            KEY status (status),
            KEY last_tested (last_tested)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Add unique key for component_id if it doesn't exist
        $adn_components_table = $wpdb->prefix . 'adn_components';
        $index_exists = $wpdb->get_var("SHOW KEYS FROM `$adn_components_table` WHERE Key_name = 'component_id' AND Non_unique = 0");
        if (!$index_exists) {
            $wpdb->query("ALTER TABLE `$adn_components_table` ADD UNIQUE KEY `component_id` (`component_id`)");
        }

        // Verify tables were created
        $tables = [
            'adn_component_tests',
            'adn_test_results',
            'adn_ai_fixes',
            'adn_health_log',
            'adn_components'
        ];

        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                error_log("Failed to create table: $table_name");
            }
        }
    }

    /**
     * Initialize plugin options
     * 
     * @since 1.0.0
     */
    private static function initialize_options() {
        // Default plugin settings
        $default_settings = [
            'ai_engines' => [
                'gemini' => [
                    'enabled' => false,
                    'api_key' => '',
                    'model' => 'gemini-pro',
                    'priority' => 1
                ],
                'claude' => [
                    'enabled' => false,
                    'api_key' => '',
                    'model' => 'claude-3-sonnet-20240229',
                    'priority' => 2
                ],
                'kilocode' => [
                    'enabled' => false,
                    'api_key' => '',
                    'endpoint' => '',
                    'priority' => 3
                ]
            ],
            'testing' => [
                'auto_test_interval' => 3600, // 1 hour
                'test_timeout' => 30,
                'max_concurrent_tests' => 5,
                'enable_performance_tests' => true,
                'enable_integration_tests' => true
            ],
            'auto_fix' => [
                'enabled' => false,
                'backup_before_fix' => true,
                'max_fixes_per_hour' => 10,
                'require_approval' => true,
                'notification_email' => get_option('admin_email')
            ],
            'monitoring' => [
                'health_check_interval' => 300, // 5 minutes
                'alert_threshold' => 70,
                'enable_email_alerts' => false,
                'enable_dashboard_notifications' => true
            ],
            'visualization' => [
                'auto_refresh_interval' => 30,
                'show_performance_metrics' => true,
                'show_dependency_graph' => true,
                'color_scheme' => 'default'
            ]
        ];

        add_option('adn_settings', $default_settings);

        // Component discovery settings
        $discovery_settings = [
            'scan_paths' => [
                WP_PLUGIN_DIR,
                WP_CONTENT_DIR . '/themes'
            ],
            'exclude_patterns' => [
                '*/node_modules/*',
                '*/vendor/*',
                '*/.git/*',
                '*/cache/*'
            ],
            'include_patterns' => [
                '*.php',
                '*.js',
                '*.css'
            ],
            'auto_discovery' => true,
            'discovery_interval' => 86400 // 24 hours
        ];

        add_option('adn_discovery_settings', $discovery_settings);

        // System status cache
        add_option('adn_system_status', [
            'overall_health' => 0,
            'total_components' => 0,
            'healthy_components' => 0,
            'warning_components' => 0,
            'failed_components' => 0,
            'last_updated' => 0
        ]);

        // AI engine status
        add_option('adn_ai_status', [
            'gemini' => ['status' => 'inactive', 'last_used' => 0],
            'claude' => ['status' => 'inactive', 'last_used' => 0],
            'kilocode' => ['status' => 'inactive', 'last_used' => 0]
        ]);
    }

    /**
     * Set up scheduled events
     * 
     * @since 1.0.0
     */
    private static function setup_scheduled_events() {
        // Schedule health checks
        if (!wp_next_scheduled('adn_health_check')) {
            wp_schedule_event(time(), 'hourly', 'adn_health_check');
        }

        // Schedule component discovery
        if (!wp_next_scheduled('adn_component_discovery')) {
            wp_schedule_event(time(), 'daily', 'adn_component_discovery');
        }

        // Schedule cleanup tasks
        if (!wp_next_scheduled('adn_cleanup_old_data')) {
            wp_schedule_event(time(), 'weekly', 'adn_cleanup_old_data');
        }

        // Schedule system status update
        if (!wp_next_scheduled('adn_update_system_status')) {
            wp_schedule_event(time(), 'adn_five_minutes', 'adn_update_system_status');
        }
    }

    /**
     * Create necessary directories
     * 
     * @since 1.0.0
     */
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        $adn_dir = $upload_dir['basedir'] . '/adn-diagnostic-network';

        // Create main directory
        if (!file_exists($adn_dir)) {
            wp_mkdir_p($adn_dir);
        }

        // Create subdirectories
        $subdirs = [
            'logs',
            'backups',
            'exports',
            'temp',
            'ai-fixes'
        ];

        foreach ($subdirs as $subdir) {
            $dir_path = $adn_dir . '/' . $subdir;
            if (!file_exists($dir_path)) {
                wp_mkdir_p($dir_path);
            }

            // Add index.php for security
            $index_file = $dir_path . '/index.php';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, '<?php // Silence is golden');
            }
        }

        // Create .htaccess for security
        $htaccess_file = $adn_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "# Aevov Diagnostic Network Security\n";
            $htaccess_content .= "Options -Indexes\n";
            $htaccess_content .= "<Files *.log>\n";
            $htaccess_content .= "    Order allow,deny\n";
            $htaccess_content .= "    Deny from all\n";
            $htaccess_content .= "</Files>\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }
    }

    /**
     * Add custom cron schedules
     * 
     * @since 1.0.0
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public static function add_cron_schedules($schedules) {
        $schedules['adn_five_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'aevov-diagnostic-network')
        ];

        $schedules['adn_fifteen_minutes'] = [
            'interval' => 900,
            'display' => __('Every 15 Minutes', 'aevov-diagnostic-network')
        ];

        return $schedules;
    }

    /**
     * Check system requirements
     * 
     * @since 1.0.0
     * @return bool|WP_Error True if requirements met, WP_Error otherwise
     */
    public static function check_requirements() {
        $errors = [];

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $errors[] = sprintf(
                __('PHP version 7.4 or higher is required. Current version: %s', 'aevov-diagnostic-network'),
                PHP_VERSION
            );
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '5.0', '<')) {
            $errors[] = sprintf(
                __('WordPress version 5.0 or higher is required. Current version: %s', 'aevov-diagnostic-network'),
                $wp_version
            );
        }

        // Check required PHP extensions
        $required_extensions = ['json', 'curl', 'mbstring'];
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = sprintf(
                    __('Required PHP extension missing: %s', 'aevov-diagnostic-network'),
                    $extension
                );
            }
        }

        // Check file permissions
        $upload_dir = wp_upload_dir();
        if (!is_writable($upload_dir['basedir'])) {
            $errors[] = __('Upload directory is not writable', 'aevov-diagnostic-network');
        }

        if (!empty($errors)) {
            return new \WP_Error('requirements_not_met', implode('<br>', $errors));
        }

        return true;
    }

    /**
     * Populate initial component data
     * 
     * @since 1.0.0
     */
    private static function populate_initial_components() {
        global $wpdb;

        // Define core Aevov components
        $components = [
            [
                'component_id' => 'aevov-pattern-sync-protocol',
                'component_name' => 'Aevov Pattern Sync Protocol',
                'component_type' => 'plugin',
                'component_path' => 'AevovPatternSyncProtocol/AevovPatternSyncProtocol.php',
                'component_class' => 'APS\\Analysis\\APS_Plugin'
            ],
            [
                'component_id' => 'bloom-pattern-recognition',
                'component_name' => 'Bloom Pattern Recognition',
                'component_type' => 'plugin',
                'component_path' => 'bloom-pattern-recognition/bloom-pattern-recognition.php',
                'component_class' => 'BloomPatternRecognition\\Core\\Plugin'
            ],
            [
                'component_id' => 'aps-tools',
                'component_name' => 'APS Tools',
                'component_type' => 'plugin',
                'component_path' => 'aps-tools/aps-tools.php',
                'component_class' => 'APSTools\\Core\\Plugin'
            ],
            [
                'component_id' => 'aevov-onboarding',
                'component_name' => 'Aevov Onboarding System',
                'component_type' => 'plugin',
                'component_path' => 'aevov-onboarding/aevov-onboarding.php',
                'component_class' => 'AevovOnboarding\\Core\\Plugin'
            ]
        ];

        $table_name = $wpdb->prefix . 'adn_components';

        foreach ($components as $component) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE component_id = %s",
                $component['component_id']
            ));

            if (!$existing) {
                $wpdb->insert(
                    $table_name,
                    [
                        'component_id' => $component['component_id'],
                        'component_name' => $component['component_name'],
                        'component_type' => $component['component_type'],
                        'component_path' => $component['component_path'],
                        'component_class' => $component['component_class'],
                        'status' => 'unknown'
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%s']
                );
            }
        }
    }
}