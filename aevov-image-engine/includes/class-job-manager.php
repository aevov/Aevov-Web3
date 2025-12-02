<?php

namespace AevovImageEngine;

class JobManager {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aevov_image_jobs';
    }

    public function create_job( $params ) {
        global $wpdb;
        $job_id = wp_generate_uuid4();
        $wpdb->insert(
            $this->table_name,
            [
                'job_id' => $job_id,
                'user_id' => get_current_user_id(),
                'params' => json_encode( $params ),
                'status' => 'queued',
                'image_url' => '',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ]
        );
        return $job_id;
    }

    public function get_job( $job_id ) {
        global $wpdb;
        $job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE job_id = %s", $job_id ) );
        if ( $job ) {
            $job->params = json_decode( $job->params, true );
        }
        return $job;
    }

    public function update_job( $job_id, $data ) {
        global $wpdb;
        $wpdb->update(
            $this->table_name,
            [
                'status' => isset( $data['status'] ) ? $data['status'] : null,
                'image_url' => isset( $data['image_url'] ) ? $data['image_url'] : null,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'job_id' => $job_id ]
        );
    }

    public function delete_job( $job_id ) {
        global $wpdb;
        $wpdb->delete( $this->table_name, [ 'job_id' => $job_id ] );
    }

    /**
     * Get all jobs with optional filtering
     *
     * @param array $args Query arguments
     * @return array Jobs
     */
    public function get_jobs( $args = [] ) {
        global $wpdb;

        $defaults = [
            'status' => null,
            'user_id' => null,
            'limit' => 50,
            'offset' => 0,
            'order_by' => 'created_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args( $args, $defaults );

        $sql = "SELECT * FROM {$this->table_name} WHERE 1=1";
        $prepare_args = [];

        if ( $args['status'] ) {
            $sql .= " AND status = %s";
            $prepare_args[] = $args['status'];
        }

        if ( $args['user_id'] ) {
            $sql .= " AND user_id = %d";
            $prepare_args[] = $args['user_id'];
        }

        $sql .= " ORDER BY {$args['order_by']} {$args['order']}";
        $sql .= " LIMIT %d OFFSET %d";
        $prepare_args[] = $args['limit'];
        $prepare_args[] = $args['offset'];

        if ( ! empty( $prepare_args ) ) {
            $sql = $wpdb->prepare( $sql, $prepare_args );
        }

        $jobs = $wpdb->get_results( $sql );

        foreach ( $jobs as &$job ) {
            $job->params = json_decode( $job->params, true );
        }

        return $jobs;
    }

    /**
     * Get active jobs (queued or processing)
     *
     * @param int $limit Maximum number of jobs
     * @return array Active jobs
     */
    public function get_active_jobs( $limit = 20 ) {
        global $wpdb;

        $jobs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE status IN ('queued', 'processing')
             ORDER BY created_at DESC
             LIMIT %d",
            $limit
        ));

        foreach ( $jobs as &$job ) {
            $job->params = json_decode( $job->params, true );
        }

        return $jobs;
    }

    /**
     * Get job counts by status
     *
     * @return array Status counts
     */
    public function get_job_counts() {
        global $wpdb;

        $counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->table_name} GROUP BY status",
            ARRAY_A
        );

        $result = [
            'queued' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'total' => 0
        ];

        foreach ( $counts as $row ) {
            $result[ $row['status'] ] = (int) $row['count'];
            $result['total'] += (int) $row['count'];
        }

        return $result;
    }

    /**
     * Create the jobs table if it doesn't exist
     *
     * @return void
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_id VARCHAR(64) NOT NULL UNIQUE,
            user_id BIGINT UNSIGNED NOT NULL,
            params LONGTEXT NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'queued',
            image_url VARCHAR(500) NULL,
            error_message TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX status (status),
            INDEX user_id (user_id),
            INDEX created_at (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
