<?php

namespace AevovApplicationForge;

class JobManager {

    public function create_job( $params ) {
        $job_id = 'aevov_forge_job_' . uniqid();
        $job_data = [
            'job_id' => $job_id,
            'params' => $params,
            'status' => 'pending', // Initial status
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ];
        update_option( $job_id, $job_data );
        return $job_id;
    }

    public function get_job( $job_id ) {
        // This is a placeholder.
        return [
            'job_id' => $job_id,
            'params' => [],
            'status' => 'running',
            'websocket_url' => 'ws://localhost:8080'
        ];
    }

    public function delete_job( $job_id ) {
        return delete_option( $job_id );
    }
}
