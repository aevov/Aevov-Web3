<?php

namespace APS\DB;

class APS_Queue_DB {
    private $wpdb;
    private $table_name;
    
    public function __construct($wpdb = null) {
        $this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
        $this->table_name = $this->wpdb->prefix . 'aps_process_queue'; // Changed table name
    }

    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            job_uuid varchar(36) NOT NULL,
            job_type varchar(32) NOT NULL,
            job_data longtext NOT NULL,
            priority int NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts int NOT NULL DEFAULT 0,
            last_attempt datetime DEFAULT NULL,
            error_log text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            processor_id varchar(36) DEFAULT NULL,
            locked_at datetime DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY job_uuid (job_uuid),
            KEY status (status),
            KEY priority (priority)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function enqueue_job($job_data, $priority = 0) {
        return $this->wpdb->insert(
            $this->table_name,
            [
                'job_uuid' => wp_generate_uuid4(),
                'job_type' => $job_data['type'],
                'job_data' => json_encode($job_data['data']),
                'priority' => $priority
            ],
            ['%s', '%s', '%s', '%d']
        );
    }

    public function get_next_job($processor_id, $types = []) {
        $type_condition = !empty($types) ? "AND job_type IN (" . implode(',', array_fill(0, count($types), '%s')) . ")" : "";
        
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE status = 'pending' 
             AND (processor_id IS NULL OR processor_id = %s) 
             {$type_condition}
             ORDER BY priority DESC, created_at ASC 
             LIMIT 1",
            array_merge([$processor_id], $types)
        );

        return $this->wpdb->get_row($query, ARRAY_A);
    }

    public function claim_job($job_id, $processor_id) {
        return $this->wpdb->update(
            $this->table_name,
            [
                'status' => 'processing',
                'processor_id' => $processor_id,
                'started_at' => current_time('mysql'),
                'attempts' => $this->wpdb->expr("attempts + 1")
            ],
            ['id' => $job_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    public function complete_job($job_id) {
        return $this->wpdb->update(
            $this->table_name,
            [
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            ],
            ['id' => $job_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    public function fail_job($job_id, $error) {
        $max_attempts = 3;
        $attempts = $this->get_job_attempts($job_id);

        $status = $attempts >= $max_attempts ? 'failed' : 'pending';
        $processor_id = $attempts >= $max_attempts ? null : $this->get_job_processor($job_id);

        return $this->wpdb->update(
            $this->table_name,
            [
                'status' => $status,
                'error_log' => $error,
                'processor_id' => $processor_id,
                'last_attempt' => current_time('mysql')
            ],
            ['id' => $job_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    public function get_queue_stats() {
        return [
            'pending' => $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'pending'"
            ),
            'processing' => $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'processing'"
            ),
            'failed' => $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'failed'"
            ),
            'completed' => $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'completed'"
            )
        ];
    }

    public function cleanup_completed_jobs($days = 7) {
        return $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_name} 
                 WHERE status = 'completed' 
                 AND completed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    public function reset_stalled_jobs($minutes = 30) {
        return $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table_name} 
                 SET status = 'pending', 
                     processor_id = NULL 
                 WHERE status = 'processing' 
                 AND started_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)",
                $minutes
            )
        );
    }

    private function get_job_attempts($job_id) {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT attempts FROM {$this->table_name} WHERE id = %d",
                $job_id
            )
        );
    }

    private function get_job_processor($job_id) {
        return $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT processor_id FROM {$this->table_name} WHERE id = %d",
                $job_id
            )
        );
    }
}