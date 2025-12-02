<?php

namespace AevovApplicationForge\API;

use Aevov\Security\SecurityHelper;

use AevovApplicationForge\ApplicationWeaver;
use AevovApplicationForge\JobManager;

class ApplicationEndpoint {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'aevov-app/v1', '/spawn', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'spawn_application' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );

        register_rest_route( 'aevov-app/v1', '/evolve/(?P<job_id>[a-zA-Z0-9-]+)', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'evolve_application' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );
    }

    public function spawn_application( $request ) {
        $params = $request->get_params();
        $job_manager = new JobManager();
        $job_id = $job_manager->create_job( $params );

        // Simulate triggering the backend worker process
        // In a real system, this would queue a job for a background worker.
        $weaver = new ApplicationWeaver();
        $genesis_state = $weaver->get_genesis_state( $params );

        $job_manager->update_job_status( $job_id, 'spawned', ['result' => $genesis_state] );

        $websocket_server = new \AevovApplicationForge\WebSocketServer();
        $websocket_server->broadcast( [
            'job_id' => $job_id,
            'status' => 'spawned',
            'progress' => 100,
            'message' => 'Application spawned successfully!',
            'data' => $genesis_state
        ] );

        return new \WP_REST_Response( [ 'job_id' => $job_id, 'status' => 'spawned', 'data' => $genesis_state ] );
    }

    public function evolve_application( $request ) {
        $job_id = $request['job_id'];
        $params = $request->get_params();
        $job_manager = new JobManager();

        $job_data = $job_manager->get_job( $job_id );
        if ( ! $job_data ) {
            return new \WP_REST_Response( [ 'status' => 'error', 'message' => 'Job not found.' ], 404 );
        }

        // Simulate sending the evolution request to the backend worker.
        // In a real system, this would queue an evolution task.
        $weaver = new ApplicationWeaver();
        $evolved_state = $weaver->get_genesis_state( $params ); // For simulation, re-using get_genesis_state with new params

        $job_manager->update_job_status( $job_id, 'evolved', ['result' => $evolved_state] );

        $websocket_server = new \AevovApplicationForge\WebSocketServer();
        $websocket_server->broadcast( [
            'job_id' => $job_id,
            'status' => 'evolved',
            'progress' => 100,
            'message' => 'Application evolved successfully!',
            'data' => $evolved_state
        ] );

        return new \WP_REST_Response( [ 'job_id' => $job_id, 'status' => 'evolved', 'data' => $evolved_state ] );
    }
}
