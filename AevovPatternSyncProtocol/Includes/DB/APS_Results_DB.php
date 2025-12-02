<?php

/**
 * includes/db/class-results-db.php
 */
namespace APS\DB;

class APS_Results_DB extends APS_DB {
    public function __construct() {
        parent::__construct('aps_results');
    }

    public function get_results_for_comparison($comparison_id) {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE comparison_id = %d 
             ORDER BY match_score DESC",
            $comparison_id
        );

        return $this->wpdb->get_results($query);
    }

    public function get_top_matches($comparison_id, $limit = 5) {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE comparison_id = %d 
             ORDER BY match_score DESC 
             LIMIT %d",
            $comparison_id,
            $limit
        );

        return $this->wpdb->get_results($query);
    }

    public function store_batch_results($results) {
        $values = [];
        $placeholders = [];
        $format = '(%d, %s, %f, %s, %s)';

        foreach ($results as $result) {
            $placeholders[] = $format;
            $values[] = $result['comparison_id'];
            $values[] = $result['result_data'];
            $values[] = $result['match_score'];
            $values[] = $result['pattern_data'];
            $values[] = current_time('mysql');
        }

        $query = $this->wpdb->prepare(
            "INSERT INTO {$this->table_name} 
             (comparison_id, result_data, match_score, pattern_data, created_at) 
             VALUES " . implode(', ', $placeholders),
            $values
        );

        return $this->wpdb->query($query);
    }
}