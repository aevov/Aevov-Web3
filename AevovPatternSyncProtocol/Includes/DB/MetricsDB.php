<?php
/**
 * Database handler for storing and retrieving metrics data
 * 
 * @package APS
 * @subpackage DB
 */

namespace APS\DB;

class MetricsDB {
    private $db;
    private $metrics_table;
    private $aggregates_table;
    private $batch_size = 100;
    private $batch_data = [];
    
    public function __construct($wpdb = null, $batch_size = null) {
        $this->db = $wpdb ?? $GLOBALS['wpdb'];
        $this->metrics_table = $this->db->prefix . 'aps_metrics';
        $this->aggregates_table = $this->db->prefix . 'aps_metrics_aggregates';
        
        if ($batch_size !== null) {
            $this->batch_size = $batch_size;
        }
        
        $this->init_hooks();
    }

    private function init_hooks() {
        if (function_exists('add_action')) {
            add_action('shutdown', [$this, 'flush_batch_data']);
            add_action('aps_aggregate_metrics', [$this, 'aggregate_metrics']);
            add_action('aps_cleanup_metrics', [$this, 'cleanup_old_metrics']);
        }
    }
    
    public function create_tables() {
        if (defined('ABSPATH')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        $charset_collate = $this->db->get_charset_collate();

        // Raw metrics table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->metrics_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            metric_type varchar(32) NOT NULL,
            metric_name varchar(64) NOT NULL,
            metric_value float NOT NULL,
            dimensions text DEFAULT NULL,
            site_id bigint(20) NOT NULL,
            processor_id varchar(36) DEFAULT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY metric_type (metric_type),
            KEY metric_name (metric_name),
            KEY site_id (site_id),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Aggregated metrics table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->aggregates_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            metric_type varchar(32) NOT NULL,
            metric_name varchar(64) NOT NULL,
            site_id bigint(20) NOT NULL,
            interval_start datetime NOT NULL,
            min_value float NOT NULL,
            max_value float NOT NULL,
            avg_value float NOT NULL,
            sum_value float NOT NULL,
            count_value int NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY metric_type (metric_type),
            KEY metric_name (metric_name),
            KEY site_id (site_id),
            KEY interval_start (interval_start)
        ) $charset_collate;";
        
        dbDelta($sql);
    }

    public function record_metric($type, $name, $value, $dimensions = [], $timestamp = null, $site_id = null) {
        $metric = [
            'metric_type' => $type,
            'metric_name' => $name,
            'metric_value' => $value,
            'dimensions' => $dimensions ? json_encode($dimensions) : null,
            'site_id' => $site_id ?? (function_exists('get_current_blog_id') ? get_current_blog_id() : 1),
            'processor_id' => function_exists('get_option') ? get_option('aps_processor_id') : null,
            'timestamp' => $timestamp ?? (function_exists('current_time') ? current_time('mysql', true) : date('Y-m-d H:i:s'))
        ];

        $this->batch_data[] = $metric;

        // Flush if batch size reached
        if (count($this->batch_data) >= $this->batch_size) {
            $this->flush_batch_data();
        }

        return true;
    }

    public function flush_batch_data() {
        if (empty($this->batch_data)) {
            return true;
        }

        $values = [];
        $place_holders = [];
        $query_data = [];

        foreach ($this->batch_data as $metric) {
            $place_holders[] = "(%s, %s, %f, %s, %d, %s)";
            array_push(
                $query_data,
                $metric['metric_type'],
                $metric['metric_name'],
                $metric['metric_value'],
                $metric['dimensions'],
                $metric['site_id'],
                $metric['timestamp']
            );
        }

        $query = $this->db->prepare(
            "INSERT INTO {$this->metrics_table} 
            (metric_type, metric_name, metric_value, dimensions, site_id, timestamp) 
            VALUES " . implode(', ', $place_holders),
            $query_data
        );

        error_log('MetricsDB: Executing query: ' . $query);
        $result = $this->db->query($query);
        error_log('MetricsDB: Query result: ' . ($result !== false ? 'Success' : 'Failure'));
        $this->batch_data = [];

        return $result !== false;
    }

    public function get_metrics($type, $name = null, $start_time = null, $end_time = null, $site_id = null) {
        $where_clauses = ["metric_type = %s"];
        $query_params = [$type];

        if ($name) {
            $where_clauses[] = "metric_name = %s";
            $query_params[] = $name;
        }

        if ($start_time) {
            $where_clauses[] = "timestamp >= %s";
            $query_params[] = $start_time;
        }

        if ($end_time) {
            $where_clauses[] = "timestamp <= %s";
            $query_params[] = $end_time;
        }

        if ($site_id) {
            $where_clauses[] = "site_id = %d";
            $query_params[] = $site_id;
        }

        $query = $this->db->prepare(
            "SELECT * FROM {$this->metrics_table} 
             WHERE " . implode(' AND ', $where_clauses) . " 
             ORDER BY timestamp DESC",
            $query_params
        );

        return $this->db->get_results($query, ARRAY_A);
    }

    public function aggregate_metrics($interval = 'hour') {
        $aggregation_queries = [
            'hour' => $this->get_hourly_aggregation_query(),
            'day' => $this->get_daily_aggregation_query(),
            'week' => $this->get_weekly_aggregation_query(),
            'month' => $this->get_monthly_aggregation_query()
        ];

        if (!isset($aggregation_queries[$interval])) {
            return false;
        }

        $query = $aggregation_queries[$interval];
        $this->db->query($query);

        // Cleanup raw metrics after aggregation
        $this->cleanup_aggregated_metrics($interval);

        return true;
    }

    private function get_hourly_aggregation_query() {
        return "INSERT INTO {$this->aggregates_table} 
                (metric_type, metric_name, site_id, interval_start, 
                 min_value, max_value, avg_value, sum_value, count_value)
                SELECT 
                    metric_type,
                    metric_name,
                    site_id,
                    DATE_FORMAT(timestamp, '%Y-%m-%d %H:00:00') as interval_start,
                    MIN(metric_value) as min_value,
                    MAX(metric_value) as max_value,
                    AVG(metric_value) as avg_value,
                    SUM(metric_value) as sum_value,
                    COUNT(*) as count_value
                FROM {$this->metrics_table}
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                GROUP BY metric_type, metric_name, site_id, interval_start";
    }

    private function get_daily_aggregation_query() {
        return "INSERT INTO {$this->aggregates_table} 
                (metric_type, metric_name, site_id, interval_start,
                 min_value, max_value, avg_value, sum_value, count_value)
                SELECT 
                    metric_type,
                    metric_name,
                    site_id,
                    DATE(timestamp) as interval_start,
                    MIN(metric_value) as min_value,
                    MAX(metric_value) as max_value,
                    AVG(metric_value) as avg_value,
                    SUM(metric_value) as sum_value,
                    COUNT(*) as count_value
                FROM {$this->metrics_table}
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                GROUP BY metric_type, metric_name, site_id, interval_start";
    }

    public function get_aggregated_metrics($type, $name, $interval = 'hour', $limit = 24) {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->aggregates_table} 
             WHERE metric_type = %s 
             AND metric_name = %s 
             AND interval_start >= DATE_SUB(NOW(), INTERVAL %d {$interval})
             ORDER BY interval_start DESC",
            $type,
            $name,
            $limit
        );

        return $this->db->get_results($query, ARRAY_A);
    }

    public function get_performance_metrics($hours = 24) {
        return $this->get_metrics(
            'performance', 
            null, 
            date('Y-m-d H:i:s', strtotime("-{$hours} hours"))
        );
    }

    public function get_network_metrics($hours = 24) {
        return $this->get_metrics(
            'network', 
            null, 
            date('Y-m-d H:i:s', strtotime("-{$hours} hours"))
        );
    }

    public function get_pattern_metrics($pattern_id = null, $hours = 24) {
        $dimensions = $pattern_id ? ['pattern_id' => $pattern_id] : null;
        return $this->get_metrics(
            'pattern',
            null,
            date('Y-m-d H:i:s', strtotime("-{$hours} hours")),
            null,
            null,
            $dimensions
        );
    }

    public function cleanup_old_metrics($days = 30) {
        // Clean up raw metrics
        $this->db->query($this->db->prepare(
            "DELETE FROM {$this->metrics_table} 
             WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        // Clean up aggregates
        $this->db->query($this->db->prepare(
            "DELETE FROM {$this->aggregates_table} 
             WHERE interval_start < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }

    public function get_metric_stats() {
        return [
            'total_metrics' => $this->db->get_var("SELECT COUNT(*) FROM {$this->metrics_table}"),
            'total_aggregates' => $this->db->get_var("SELECT COUNT(*) FROM {$this->aggregates_table}"),
            'oldest_metric' => $this->db->get_var("SELECT MIN(timestamp) FROM {$this->metrics_table}"),
            'newest_metric' => $this->db->get_var("SELECT MAX(timestamp) FROM {$this->metrics_table}"),
            'storage_size' => $this->get_storage_size()
        ];
    }

    private function get_storage_size() {
        $tables = [$this->metrics_table, $this->aggregates_table];
        $total_size = 0;

        foreach ($tables as $table) {
            $size = $this->db->get_row($this->db->prepare(
                "SELECT 
                    data_length + index_length as size,
                    table_rows
                FROM information_schema.tables
                WHERE table_schema = %s
                AND table_name = %s",
                DB_NAME,
                $table
            ));

            if ($size) {
                $total_size += $size->size;
            }
        }

        return $total_size;
    }

    public function optimize_tables() {
        $this->db->query("OPTIMIZE TABLE {$this->metrics_table}, {$this->aggregates_table}");
    }

    private function cleanup_aggregated_metrics($interval) {
        $cleanup_intervals = [
            'hour' => '2 HOUR',
            'day' => '2 DAY',
            'week' => '2 WEEK',
            'month' => '2 MONTH'
        ];

        if (isset($cleanup_intervals[$interval])) {
            $this->db->query($this->db->prepare(
                "DELETE FROM {$this->metrics_table} 
                 WHERE timestamp < DATE_SUB(NOW(), INTERVAL %s)",
                $cleanup_intervals[$interval]
            ));
        }
    }

    /**
     * Get sum of metric values
     */
    public function get_metric_sum($metric_name, $filters = [], $hours = 24) {
        $where_clauses = ["metric_name = %s"];
        $query_params = [$metric_name];
        
        // Add time filter
        $where_clauses[] = "timestamp >= %s";
        $query_params[] = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        // Add dimension filters
        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                $where_clauses[] = "JSON_EXTRACT(dimensions, '$.{$key}') = %s";
                $query_params[] = $value;
            }
        }
        
        $query = $this->db->prepare(
            "SELECT COALESCE(SUM(metric_value), 0) as total
             FROM {$this->metrics_table}
             WHERE " . implode(' AND ', $where_clauses),
            $query_params
        );
        
        $result = $this->db->get_var($query);
        return floatval($result);
    }

    /**
     * Get average of metric values
     */
    public function get_metric_average($metric_name, $dimension_key = null, $hours = 24) {
        $where_clauses = ["metric_name = %s"];
        $query_params = [$metric_name];
        
        // Add time filter
        $where_clauses[] = "timestamp >= %s";
        $query_params[] = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        $select_field = $dimension_key ?
            "AVG(CAST(JSON_EXTRACT(dimensions, '$.{$dimension_key}') AS DECIMAL(10,2)))" :
            "AVG(metric_value)";
        
        $query = $this->db->prepare(
            "SELECT COALESCE({$select_field}, 0) as average
             FROM {$this->metrics_table}
             WHERE " . implode(' AND ', $where_clauses),
            $query_params
        );
        
        $result = $this->db->get_var($query);
        return floatval($result);
    }

    /**
     * Get count of metric records
     */
    public function get_metric_count($metric_name, $filters = [], $hours = 24) {
        $where_clauses = ["metric_name = %s"];
        $query_params = [$metric_name];
        
        // Add time filter
        $where_clauses[] = "timestamp >= %s";
        $query_params[] = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        // Add dimension filters
        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                $where_clauses[] = "JSON_EXTRACT(dimensions, '$.{$key}') = %s";
                $query_params[] = $value;
            }
        }
        
        $query = $this->db->prepare(
            "SELECT COUNT(*) as count
             FROM {$this->metrics_table}
             WHERE " . implode(' AND ', $where_clauses),
            $query_params
        );
        
        $result = $this->db->get_var($query);
        return intval($result);
    }
}