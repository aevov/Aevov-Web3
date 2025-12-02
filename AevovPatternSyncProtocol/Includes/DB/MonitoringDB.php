<?php
/**
 * Manages monitoring-related database tables
 *
 * @package APS
 * @subpackage DB
 */

namespace APS\DB;

class MonitoringDB extends APS_DB {
    private $health_log_table;
    private $emergency_log_table;
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->health_log_table = $wpdb->prefix . 'aps_health_log';
        $this->emergency_log_table = $wpdb->prefix . 'aps_emergency_log';
    }

    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = [
            "CREATE TABLE IF NOT EXISTS `{$this->health_log_table}` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `status_data` longtext NOT NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `created_at` (`created_at`)
            ) $charset_collate;",

            "CREATE TABLE IF NOT EXISTS `{$this->emergency_log_table}` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `event_type` varchar(50) NOT NULL,
                `event_data` longtext NOT NULL,
                `severity` varchar(20) NOT NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `event_type` (`event_type`),
                KEY `created_at` (`created_at`)
            ) $charset_collate;"
        ];

        foreach ($sql as $query) {
            dbDelta($query);
        }
    }

    public function get_health_logs($limit = 100, $offset = 0) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->health_log_table} 
                 ORDER BY created_at DESC 
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );
    }

    public function get_emergency_logs($severity = null, $limit = 100) {
        $sql = "SELECT * FROM {$this->emergency_log_table}";
        $params = [];

        if ($severity) {
            $sql .= " WHERE severity = %s";
            $params[] = $severity;
        }

        $sql .= " ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, ...$params),
            ARRAY_A
        );
    }

    public function cleanup_old_logs($days = 30) {
        // Clean up health logs
        $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->health_log_table} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        // Clean up emergency logs after 90 days
        $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->emergency_log_table} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            90
        ));
    }
}
