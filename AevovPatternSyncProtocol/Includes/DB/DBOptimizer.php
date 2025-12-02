<?php
/**
 * Database optimization and maintenance
 * 
 * @package APS
 * @subpackage DB
 */

namespace APS\DB;

class DBOptimizer {
    public function optimize_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'aps_patterns',
            $wpdb->prefix . 'aps_queue',
            $wpdb->prefix . 'aps_metrics',
            $wpdb->prefix . 'aps_metrics_aggregates',
            $wpdb->prefix . 'aps_alerts',
            $wpdb->prefix . 'aps_sync_log',
            $wpdb->prefix . 'aps_network_state',
            $wpdb->prefix . 'aps_patterns_cache',
            $wpdb->prefix . 'aps_symbolic_patterns',
            $wpdb->prefix . 'aps_pattern_relationships',
            $wpdb->prefix . 'aps_pattern_chunks',
            $wpdb->prefix . 'aps_health_log',
            $wpdb->prefix . 'aps_emergency_log',
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE $table");
        }
    }

    public function cleanup_data($days = 30) {
        global $wpdb;
        
        // Clean up old patterns
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}aps_patterns
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        // Clean up processed queue jobs
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}aps_queue
             WHERE status = 'completed'
             AND completed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        // Use MetricsDB for metrics cleanup to ensure both tables are handled
        $metrics_db = new MetricsDB();
        $metrics_db->cleanup_old_metrics($days);
        
        // Clean up pattern cache
        $pattern_cache_db = new PatternCacheDB();
        $pattern_cache_db->cleanup_expired();

        $monitoring_db = new MonitoringDB();
        $monitoring_db->cleanup_old_logs($days);
        
        // Clean up old alerts
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}aps_alerts
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        // Clean up old sync logs
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}aps_sync_log
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
}