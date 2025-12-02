<?php

/**
 * includes/db/class-comparison-db.php
 */
namespace APS\DB;

class APS_Comparison_DB extends APS_DB {
    public function __construct() {
        parent::__construct('aps_comparisons');
    }

    public function get_recent_comparisons($limit = 10) {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        );

        return $this->wpdb->get_results($query);
    }

    public function get_comparison_by_uuid($uuid) {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE comparison_uuid = %s",
            $uuid
        );

        return $this->wpdb->get_row($query);
    }

    public function get_comparisons_by_type($type, $limit = 10) {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE comparison_type = %s 
             ORDER BY created_at DESC 
             LIMIT %d",
            $type,
            $limit
        );

        return $this->wpdb->get_results($query);
    }
}
