<?php
/**
 * Handles plugin deactivation and cleanup
 * 
 * @package APS
 * @subpackage Core
 */

namespace APS\Core;

class Deactivator {
    public static function deactivate() {
        // Remove scheduled tasks
        wp_clear_scheduled_hook('aps_process_queue');
        wp_clear_scheduled_hook('aps_sync_network');
        wp_clear_scheduled_hook('aps_system_health_check');
        wp_clear_scheduled_hook('aps_process_metrics');
        wp_clear_scheduled_hook('aps_aggregate_metrics');
        wp_clear_scheduled_hook('aps_cleanup_metrics');
        wp_clear_scheduled_hook('aps_cleanup_network_cache');
        wp_clear_scheduled_hook('aps_cleanup_alerts');
        wp_clear_scheduled_hook('aps_hourly_alert_cleanup');
        wp_clear_scheduled_hook('aps_cleanup_monitoring_logs');
    
        // Clean up database tables
        global $wpdb;
        
        // Pattern-related tables (drop in correct order due to foreign key constraints)
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_symbolic_patterns");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_pattern_relationships");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_pattern_chunks");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_patterns");
        
        // Other tables
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_queue");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_metrics");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_metrics_aggregates");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_alerts");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_sync_log");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_network_state");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_patterns_cache");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_comparisons");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_results");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_health_log");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_emergency_log");
    
        // Clear plugin options and caches
        delete_option('aps_version');
        delete_option('aps_settings');
        delete_option('aps_suppressed_alerts');
        delete_option('aps_bloom_sync_status');
        wp_cache_flush();
    }
}