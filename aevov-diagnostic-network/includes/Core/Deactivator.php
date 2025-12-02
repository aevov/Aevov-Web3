<?php
/**
 * Aevov Diagnostic Network - Plugin Deactivator
 * 
 * Handles plugin deactivation tasks including cleanup,
 * scheduled event removal, and optional data removal.
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
 * Plugin Deactivator Class
 * 
 * Manages all deactivation procedures for the diagnostic network plugin
 */
class Deactivator {

    /**
     * Plugin deactivation handler
     * 
     * @since 1.0.0
     */
    public static function deactivate() {
        // Clear scheduled events
        self::clear_scheduled_events();
        
        // Clear transients and cache
        self::clear_cache();
        
        // Clean up temporary files
        self::cleanup_temp_files();
        
        // Update deactivation timestamp
        update_option('adn_deactivated_at', current_time('timestamp'));
        
        // Log deactivation
        error_log('Aevov Diagnostic Network deactivated');
    }

    /**
     * Complete plugin uninstall (called from uninstall.php)
     * 
     * @since 1.0.0
     */
    public static function uninstall() {
        // Check if user wants to keep data
        $settings = get_option('adn_settings', []);
        $keep_data = isset($settings['uninstall']['keep_data']) ? $settings['uninstall']['keep_data'] : false;

        if (!$keep_data) {
            // Remove database tables
            self::remove_database_tables();
            
            // Remove plugin options
            self::remove_plugin_options();
            
            // Remove uploaded files
            self::remove_uploaded_files();
        }

        // Always clear scheduled events and cache
        self::clear_scheduled_events();
        self::clear_cache();
        self::cleanup_temp_files();

        // Log uninstall
        error_log('Aevov Diagnostic Network uninstalled completely');
    }

    /**
     * Clear all scheduled events
     * 
     * @since 1.0.0
     */
    private static function clear_scheduled_events() {
        $scheduled_events = [
            'adn_health_check',
            'adn_component_discovery',
            'adn_cleanup_old_data',
            'adn_update_system_status'
        ];

        foreach ($scheduled_events as $event) {
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
            }
        }

        // Clear all instances of recurring events
        wp_clear_scheduled_hook('adn_health_check');
        wp_clear_scheduled_hook('adn_component_discovery');
        wp_clear_scheduled_hook('adn_cleanup_old_data');
        wp_clear_scheduled_hook('adn_update_system_status');
    }

    /**
     * Clear transients and cache
     * 
     * @since 1.0.0
     */
    private static function clear_cache() {
        global $wpdb;

        // Clear plugin-specific transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_adn_%' 
             OR option_name LIKE '_transient_timeout_adn_%'"
        );

        // Clear site transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_site_transient_adn_%' 
             OR option_name LIKE '_site_transient_timeout_adn_%'"
        );

        // Clear object cache if available
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('adn');
        }

        // Clear any external cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Clean up temporary files
     * 
     * @since 1.0.0
     */
    private static function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $adn_dir = $upload_dir['basedir'] . '/adn-diagnostic-network';

        // Clean temp directory
        $temp_dir = $adn_dir . '/temp';
        if (is_dir($temp_dir)) {
            self::delete_directory_contents($temp_dir);
        }

        // Clean old log files (keep last 30 days)
        $logs_dir = $adn_dir . '/logs';
        if (is_dir($logs_dir)) {
            self::cleanup_old_logs($logs_dir, 30);
        }

        // Clean old backup files (keep last 7 days)
        $backups_dir = $adn_dir . '/backups';
        if (is_dir($backups_dir)) {
            self::cleanup_old_backups($backups_dir, 7);
        }
    }

    /**
     * Remove database tables
     * 
     * @since 1.0.0
     */
    private static function remove_database_tables() {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'adn_component_tests',
            $wpdb->prefix . 'adn_test_results',
            $wpdb->prefix . 'adn_ai_fixes',
            $wpdb->prefix . 'adn_health_log',
            $wpdb->prefix . 'adn_components'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }

    /**
     * Remove plugin options
     * 
     * @since 1.0.0
     */
    private static function remove_plugin_options() {
        $options = [
            'adn_settings',
            'adn_discovery_settings',
            'adn_system_status',
            'adn_ai_status',
            'adn_activated_at',
            'adn_deactivated_at',
            'adn_version'
        ];

        foreach ($options as $option) {
            delete_option($option);
        }

        // Remove any options that start with 'adn_'
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE 'adn_%'"
        );
    }

    /**
     * Remove uploaded files and directories
     * 
     * @since 1.0.0
     */
    private static function remove_uploaded_files() {
        $upload_dir = wp_upload_dir();
        $adn_dir = $upload_dir['basedir'] . '/adn-diagnostic-network';

        if (is_dir($adn_dir)) {
            self::delete_directory_recursively($adn_dir);
        }
    }

    /**
     * Delete directory contents but keep the directory
     * 
     * @since 1.0.0
     * @param string $dir Directory path
     */
    private static function delete_directory_contents($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                self::delete_directory_recursively($path);
            } else {
                unlink($path);
            }
        }
    }

    /**
     * Delete directory and all contents recursively
     * 
     * @since 1.0.0
     * @param string $dir Directory path
     */
    private static function delete_directory_recursively($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                self::delete_directory_recursively($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }

    /**
     * Clean up old log files
     * 
     * @since 1.0.0
     * @param string $logs_dir Logs directory path
     * @param int $days_to_keep Number of days to keep logs
     */
    private static function cleanup_old_logs($logs_dir, $days_to_keep = 30) {
        if (!is_dir($logs_dir)) {
            return;
        }

        $cutoff_time = time() - ($days_to_keep * 24 * 60 * 60);
        $files = glob($logs_dir . '/*.log');

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }

    /**
     * Clean up old backup files
     * 
     * @since 1.0.0
     * @param string $backups_dir Backups directory path
     * @param int $days_to_keep Number of days to keep backups
     */
    private static function cleanup_old_backups($backups_dir, $days_to_keep = 7) {
        if (!is_dir($backups_dir)) {
            return;
        }

        $cutoff_time = time() - ($days_to_keep * 24 * 60 * 60);
        $files = glob($backups_dir . '/*');

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }

    /**
     * Export plugin data before uninstall
     * 
     * @since 1.0.0
     * @return string|false Path to export file or false on failure
     */
    public static function export_data() {
        global $wpdb;

        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/adn-diagnostic-network/exports';
        
        if (!is_dir($export_dir)) {
            wp_mkdir_p($export_dir);
        }

        $export_file = $export_dir . '/adn-export-' . date('Y-m-d-H-i-s') . '.json';
        
        $export_data = [
            'version' => ADN_VERSION,
            'exported_at' => current_time('mysql'),
            'settings' => get_option('adn_settings', []),
            'discovery_settings' => get_option('adn_discovery_settings', []),
            'system_status' => get_option('adn_system_status', []),
            'ai_status' => get_option('adn_ai_status', [])
        ];

        // Export database tables
        $tables = [
            'components' => $wpdb->prefix . 'adn_components',
            'component_tests' => $wpdb->prefix . 'adn_component_tests',
            'test_results' => $wpdb->prefix . 'adn_test_results',
            'ai_fixes' => $wpdb->prefix . 'adn_ai_fixes',
            'health_log' => $wpdb->prefix . 'adn_health_log'
        ];

        foreach ($tables as $key => $table) {
            $results = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
            $export_data['tables'][$key] = $results;
        }

        $json_data = json_encode($export_data, JSON_PRETTY_PRINT);
        
        if (file_put_contents($export_file, $json_data)) {
            return $export_file;
        }

        return false;
    }

    /**
     * Create backup before major operations
     * 
     * @since 1.0.0
     * @param string $operation Operation name
     * @return string|false Path to backup file or false on failure
     */
    public static function create_backup($operation = 'manual') {
        global $wpdb;

        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/adn-diagnostic-network/backups';
        
        if (!is_dir($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }

        $backup_file = $backup_dir . '/adn-backup-' . $operation . '-' . date('Y-m-d-H-i-s') . '.sql';
        
        $backup_content = "-- Aevov Diagnostic Network Backup\n";
        $backup_content .= "-- Created: " . current_time('mysql') . "\n";
        $backup_content .= "-- Operation: $operation\n\n";

        $tables = [
            $wpdb->prefix . 'adn_components',
            $wpdb->prefix . 'adn_component_tests',
            $wpdb->prefix . 'adn_test_results',
            $wpdb->prefix . 'adn_ai_fixes',
            $wpdb->prefix . 'adn_health_log'
        ];

        foreach ($tables as $table) {
            // Get table structure
            $create_table = $wpdb->get_row("SHOW CREATE TABLE $table", ARRAY_N);
            if ($create_table) {
                $backup_content .= "DROP TABLE IF EXISTS `$table`;\n";
                $backup_content .= $create_table[1] . ";\n\n";
            }

            // Get table data
            $rows = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
            if ($rows) {
                foreach ($rows as $row) {
                    $values = array_map(function($value) use ($wpdb) {
                        return $wpdb->prepare('%s', $value);
                    }, array_values($row));
                    
                    $columns = '`' . implode('`, `', array_keys($row)) . '`';
                    $values_str = implode(', ', $values);
                    
                    $backup_content .= "INSERT INTO `$table` ($columns) VALUES ($values_str);\n";
                }
                $backup_content .= "\n";
            }
        }

        if (file_put_contents($backup_file, $backup_content)) {
            return $backup_file;
        }

        return false;
    }

    /**
     * Reset plugin to default state
     * 
     * @since 1.0.0
     */
    public static function reset_to_defaults() {
        // Create backup before reset
        self::create_backup('reset');

        // Clear all data
        self::remove_database_tables();
        self::remove_plugin_options();
        self::cleanup_temp_files();

        // Recreate with defaults (simulate activation)
        if (class_exists('ADN\Core\Activator')) {
            Activator::activate();
        }

        // Log reset
        error_log('Aevov Diagnostic Network reset to defaults');
    }

    /**
     * Get cleanup statistics
     * 
     * @since 1.0.0
     * @return array Cleanup statistics
     */
    public static function get_cleanup_stats() {
        global $wpdb;

        $stats = [
            'database' => [
                'tables' => 0,
                'rows' => 0,
                'size' => 0
            ],
            'files' => [
                'total_files' => 0,
                'total_size' => 0,
                'log_files' => 0,
                'backup_files' => 0,
                'temp_files' => 0
            ],
            'options' => 0,
            'transients' => 0
        ];

        // Database stats
        $tables = [
            $wpdb->prefix . 'adn_components',
            $wpdb->prefix . 'adn_component_tests',
            $wpdb->prefix . 'adn_test_results',
            $wpdb->prefix . 'adn_ai_fixes',
            $wpdb->prefix . 'adn_health_log'
        ];

        foreach ($tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            if ($table_exists) {
                $stats['database']['tables']++;
                $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                $stats['database']['rows'] += intval($row_count);
            }
        }

        // Options stats
        $options_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'adn_%'"
        );
        $stats['options'] = intval($options_count);

        // Transients stats
        $transients_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_adn_%' 
             OR option_name LIKE '_transient_timeout_adn_%'"
        );
        $stats['transients'] = intval($transients_count);

        // File stats
        $upload_dir = wp_upload_dir();
        $adn_dir = $upload_dir['basedir'] . '/adn-diagnostic-network';

        if (is_dir($adn_dir)) {
            $stats['files'] = self::get_directory_stats($adn_dir);
        }

        return $stats;
    }

    /**
     * Get directory statistics
     * 
     * @since 1.0.0
     * @param string $dir Directory path
     * @return array Directory statistics
     */
    private static function get_directory_stats($dir) {
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'log_files' => 0,
            'backup_files' => 0,
            'temp_files' => 0
        ];

        if (!is_dir($dir)) {
            return $stats;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $stats['total_files']++;
                $stats['total_size'] += $file->getSize();

                $extension = $file->getExtension();
                $path = $file->getPath();

                if ($extension === 'log' || strpos($path, '/logs') !== false) {
                    $stats['log_files']++;
                } elseif (strpos($path, '/backups') !== false) {
                    $stats['backup_files']++;
                } elseif (strpos($path, '/temp') !== false) {
                    $stats['temp_files']++;
                }
            }
        }

        return $stats;
    }
}