<?php

namespace AevovSuperAppForge\API;

use Aevov\Security\SecurityHelper;

use AevovSuperAppForge\JobManager;

class ApplicationEndpoint {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'aevov-super-app/v1', '/spawn', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'spawn_application' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );

        register_rest_route( 'aevov-super-app/v1', '/evolve/(?P<job_id>[a-zA-Z0-9-]+)', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'evolve_application' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );
    }

    public function spawn_application( $request ) {
        $params = $request->get_params();
        $job_manager = new JobManager();
        $job_id = $job_manager->create_job( $params );

        // This is where we would trigger the backend worker process.
        // For now, I'll just return the job ID and a dummy WebSocket URL.
        return new \WP_REST_Response( [ 'job_id' => $job_id, 'websocket_url' => 'ws://localhost:8080' ] );
    }

    public function evolve_application( $request ) {
        $job_id = $request['job_id'];
        $params = $request->get_params();

        // This is where we would send the evolution request to the backend worker.
        // For now, I'll just return a success message.
        return new \WP_REST_Response( [ 'status' => 'evolution_received' ] );
    }
}
