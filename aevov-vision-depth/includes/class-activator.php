<?php
/**
 * Plugin Activator
 *
 * @package AevovVisionDepth
 * @since 1.0.0
 */

namespace AevovVisionDepth;

class Activator {

    /**
     * Activate the plugin
     *
     * @return void
     */
    public static function activate() {
        // Create database tables
        Database::create_tables();

        // Set default options
        self::set_default_options();

        // Schedule cron jobs
        self::schedule_cron_jobs();

        // Create necessary directories
        self::create_directories();

        // Set activation flag
        update_option('avd_activated', true);
        update_option('avd_activation_time', current_time('timestamp'));
        update_option('avd_version', AVD_VERSION);

        // Flush rewrite rules
        flush_rewrite_rules();

        // Log activation
        self::log_activation();
    }

    /**
     * Deactivate the plugin
     *
     * @return void
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('avd_auto_delete_old_data');
        wp_clear_scheduled_hook('avd_process_scrape_queue');

        // Flush rewrite rules
        flush_rewrite_rules();

        // Log deactivation
        self::log_deactivation();
    }

    /**
     * Set default plugin options
     *
     * @return void
     */
    private static function set_default_options() {
        $defaults = [
            // Privacy settings
            'avd_default_privacy_mode' => 'balanced',
            'avd_require_consent' => true,
            'avd_data_retention_days' => 7,
            'avd_enable_encryption' => true,
            'avd_enable_anonymization' => true,

            // Scraper settings
            'avd_enable_scraper' => true,
            'avd_max_retries' => 3,
            'avd_timeout_seconds' => 30,
            'avd_user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'avd_follow_redirects' => true,
            'avd_max_redirects' => 5,

            // Rate limiting
            'avd_rate_limit_per_second' => 10,
            'avd_rate_limit_per_minute' => 100,
            'avd_rate_limit_per_hour' => 1000,

            // Integration settings
            'avd_enable_aps_integration' => true,
            'avd_enable_bloom_integration' => true,
            'avd_enable_aps_tools_integration' => true,

            // Reward settings
            'avd_enable_rewards' => true,
            'avd_reward_per_scrape' => 0.001, // AevCoin per scrape
            'avd_reward_per_pattern' => 0.01, // AevCoin per unique pattern

            // Dashboard settings
            'avd_enable_monitoring' => true,
            'avd_show_stats' => true,
        ];

        foreach ($defaults as $option_name => $option_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $option_value);
            }
        }
    }

    /**
     * Schedule cron jobs
     *
     * @return void
     */
    private static function schedule_cron_jobs() {
        if (!wp_next_scheduled('avd_auto_delete_old_data')) {
            wp_schedule_event(time(), 'daily', 'avd_auto_delete_old_data');
        }

        if (!wp_next_scheduled('avd_process_scrape_queue')) {
            wp_schedule_event(time(), 'hourly', 'avd_process_scrape_queue');
        }
    }

    /**
     * Create necessary directories
     *
     * @return void
     */
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];

        $directories = [
            $base_dir . '/vision-depth-cache',
            $base_dir . '/vision-depth-exports',
            $base_dir . '/vision-depth-logs',
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
    }

    /**
     * Log activation event
     *
     * @return void
     */
    private static function log_activation() {
        global $wpdb;

        $table = $wpdb->prefix . 'avd_activity_log';
        $wpdb->insert(
            $table,
            [
                'user_id' => get_current_user_id(),
                'action_type' => 'plugin_activated',
                'action_description' => 'Vision Depth plugin activated',
                'metadata' => json_encode([
                    'version' => AVD_VERSION,
                    'php_version' => PHP_VERSION,
                    'wp_version' => get_bloginfo('version'),
                ]),
                'ip_address' => self::get_client_ip(),
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Log deactivation event
     *
     * @return void
     */
    private static function log_deactivation() {
        global $wpdb;

        $table = $wpdb->prefix . 'avd_activity_log';
        $wpdb->insert(
            $table,
            [
                'user_id' => get_current_user_id(),
                'action_type' => 'plugin_deactivated',
                'action_description' => 'Vision Depth plugin deactivated',
                'ip_address' => self::get_client_ip(),
            ],
            ['%d', '%s', '%s', '%s']
        );
    }

    /**
     * Get client IP address
     *
     * @return string
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
