<?php

/**
 * includes/core/class-aps-activator.php
 */

namespace APS\Core;

use APS\DB\MetricsDB;
use APS\DB\PatternDB;
use APS\DB\SyncLogDB;
use APS\DB\MonitoringDB;
use APS\DB\PatternCacheDB;
use APS\DB\APS_Comparison_DB;
use APS\DB\APS_Results_DB;
use APS\DB\APS_Queue_DB;

class APS_Activator {
    private static $logger;

    public static function activate($network_wide = false) {
        self::$logger = Logger::get_instance();
        self::$logger->info('APS Plugin activation started', ['network_wide' => $network_wide]);

        if ($network_wide && is_multisite()) {
            self::network_activate();
        } else {
            self::single_site_activate();
        }
        self::$logger->info('APS Plugin activation completed');
    }
    
    private static function single_site_activate() {
        try {
            self::$logger->info('Single site activation started');
            self::check_requirements();
            self::create_database_tables();
            self::set_default_options();
            self::create_required_directories();
            self::setup_scheduled_tasks();
            self::$logger->info('Single site activation completed successfully');
        } catch (\Exception $e) {
            self::$logger->error('Single site activation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Optionally, deactivate the plugin or show an admin notice
            deactivate_plugins(plugin_basename(AEVOV_PATTERN_SYNC_FILE));
            wp_die(
                __('APS Plugin activation failed: ', 'aps') . $e->getMessage(),
                'Plugin Activation Error',
                array('back_link' => true)
            );
        }
    }
    
    private static function network_activate() {
        self::$logger->info('Network activation started');
        $sites = get_sites(['fields' => 'ids']);
        
        foreach ($sites as $site_id) {
            self::$logger->info('Activating plugin for site', ['site_id' => $site_id]);
            switch_to_blog($site_id);
            try {
                self::single_site_activate();
            } catch (\Exception $e) {
                self::$logger->error('Failed to activate plugin for site', [
                    'site_id' => $site_id,
                    'error' => $e->getMessage()
                ]);
                // Continue to next site, but log the error
            }
            restore_current_blog();
        }
        self::$logger->info('Network activation completed');
    }

    private static function check_requirements() {
        self::$logger->info('Checking plugin requirements');
        if (!class_exists('BLOOM_Pattern_System')) { // Changed to BLOOM_Pattern_System
            $error_message = __('BLOOM Pattern Recognition System is required for this plugin. Please install and activate it.', 'aps');
            self::$logger->error('Requirement check failed: BLOOM_Pattern_System class not found');
            throw new \Exception($error_message);
        }
        self::$logger->info('Plugin requirements met');
    }

    private static function create_database_tables() {
        self::$logger->info('Creating database tables');
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        try {
            // Create metrics tables using the MetricsDB class
            $metrics_db = new MetricsDB();
            $metrics_db->create_tables();
            self::$logger->info('MetricsDB tables created');
            
            // Create pattern tables using the PatternDB class
            $pattern_db = new PatternDB();
            $pattern_db->create_tables();
            self::$logger->info('PatternDB tables created');
            
            // Create pattern cache table using the PatternCacheDB class
            $pattern_cache_db = new PatternCacheDB();
            $pattern_cache_db->create_table();
            self::$logger->info('PatternCacheDB table created');
            
            // Create sync log table using the SyncLogDB class
            $sync_log_db = new SyncLogDB();
            $sync_log_db->create_table();
            self::$logger->info('SyncLogDB table created');
 
            $monitoring_db = new MonitoringDB();
            $monitoring_db->create_tables();
            self::$logger->info('MonitoringDB tables created');
            
            $comparison_db = new APS_Comparison_DB();
            $comparison_db->create_tables();
            self::$logger->info('APS_Comparison_DB tables created');

            $results_db = new APS_Results_DB();
            $results_db->create_tables();
            self::$logger->info('APS_Results_DB tables created');

            $queue_db = new APS_Queue_DB();
            $queue_db->create_tables();
            self::$logger->info('APS_Queue_DB tables created');

            // Comparisons table
            $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aps_comparisons (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                comparison_uuid varchar(36) NOT NULL,
                comparison_type varchar(32) NOT NULL,
                items_data longtext NOT NULL,
                settings longtext,
                status varchar(20) NOT NULL DEFAULT 'pending',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY comparison_uuid (comparison_uuid),
                KEY status (status)
            ) $charset_collate;";
        
            // Results table
            $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aps_results (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                comparison_id bigint(20) NOT NULL,
                result_data longtext NOT NULL,
                match_score float NOT NULL,
                pattern_data longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY comparison_id (comparison_id)
            ) $charset_collate;";
        
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            self::$logger->info('Legacy comparison and results tables ensured');

            self::$logger->info('All database tables created successfully');
        } catch (\Exception $e) {
            self::$logger->error('Failed to create database tables', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    private static function set_default_options() {
        self::$logger->info('Setting default options');
        $default_options = array(
            'aps_version' => APS_VERSION,
            'comparison_cache_time' => 3600,
            'min_pattern_confidence' => 0.75,
            'sync_interval' => 300,
            'max_comparison_items' => 10,
            'enable_tensor_cache' => true,
            'aps_processor_id' => uniqid('aps_processor_') // Unique ID for this processor instance
        );
 
        foreach ($default_options as $option => $value) {
            try {
                if (get_option($option) === false) {
                    add_option($option, $value);
                    self::$logger->debug('Added default option', ['option' => $option, 'value' => $value]);
                } else {
                    self::$logger->debug('Option already exists, skipping default', ['option' => $option]);
                }
            } catch (\Exception $e) {
                self::$logger->error('Failed to set default option', [
                    'option' => $option,
                    'value' => $value,
                    'error' => $e->getMessage()
                ]);
            }
        }
        self::$logger->info('Default options set');
    }

    private static function create_required_directories() {
        self::$logger->info('Creating required directories');
        try {
            $upload_dir = wp_upload_dir();
            $aps_dir = $upload_dir['basedir'] . '/aps-cache';
 
            if (!file_exists($aps_dir)) {
                if (!wp_mkdir_p($aps_dir)) {
                    throw new \Exception("Failed to create directory: {$aps_dir}");
                }
                self::$logger->info('Created APS cache directory', ['path' => $aps_dir]);
            } else {
                self::$logger->info('APS cache directory already exists', ['path' => $aps_dir]);
            }
 
            // Create .htaccess to protect cache directory
            $htaccess = $aps_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                if (file_put_contents($htaccess, 'Deny from all') === false) {
                    throw new \Exception("Failed to create .htaccess file in {$aps_dir}");
                }
                self::$logger->info('Created .htaccess file in APS cache directory');
            } else {
                self::$logger->info('.htaccess file already exists in APS cache directory');
            }
            self::$logger->info('Required directories ensured');
        } catch (\Exception $e) {
            self::$logger->error('Failed to create required directories', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    private static function setup_scheduled_tasks() {
        self::$logger->info('Setting up scheduled tasks');
        $tasks = [
            'aps_process_queue' => ['interval' => 'minute', 'hook' => 'aps_process_queue'],
            'aps_sync_network' => ['interval' => 'five_minutes', 'hook' => 'aps_sync_network'],
            'aps_system_health_check' => ['interval' => 'hourly', 'hook' => 'aps_system_health_check'],
            'aps_process_metrics' => ['interval' => 'hourly', 'hook' => 'aps_process_metrics'],
            'aps_aggregate_metrics' => ['interval' => 'daily', 'hook' => 'aps_aggregate_metrics'],
            'aps_cleanup_metrics' => ['interval' => 'daily', 'hook' => 'aps_cleanup_metrics'],
            'aps_release_stale_locks' => ['interval' => 'hourly', 'hook' => 'aps_release_locks'], // New task
            'aps_cleanup_queue' => ['interval' => 'daily', 'hook' => 'aps_cleanup_queue'] // New task
        ];
 
        foreach ($tasks as $name => $config) {
            try {
                if (!wp_next_scheduled($config['hook'])) {
                    wp_schedule_event(time(), $config['interval'], $config['hook']);
                    self::$logger->info('Scheduled task', ['task' => $name, 'interval' => $config['interval']]);
                } else {
                    self::$logger->debug('Task already scheduled', ['task' => $name]);
                }
            } catch (\Exception $e) {
                self::$logger->error('Failed to schedule task', [
                    'task' => $name,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $aps_settings = get_option('aps_settings', []);
        $auto_cleanup_enabled = isset($aps_settings['auto_cleanup_enabled']) ? $aps_settings['auto_cleanup_enabled'] : true;

        if ($auto_cleanup_enabled) {
            if (!wp_next_scheduled('aps_cleanup_data')) {
                wp_schedule_event(time(), 'daily', 'aps_cleanup_data');
                self::$logger->info('Scheduled daily data cleanup');
            }
            if (!wp_next_scheduled('aps_optimize_tables')) {
                wp_schedule_event(time(), 'daily', 'aps_optimize_tables');
                self::$logger->info('Scheduled daily table optimization');
            }
        } else {
            wp_clear_scheduled_hook('aps_cleanup_data');
            wp_clear_scheduled_hook('aps_optimize_tables');
            self::$logger->info('Unscheduled daily data cleanup and table optimization');
        }

        self::$logger->info('Scheduled tasks setup complete');
    }
}
