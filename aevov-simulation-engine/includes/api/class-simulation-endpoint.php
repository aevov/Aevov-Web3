<?php

namespace AevovSimulationEngine\API;

use Aevov\Security\SecurityHelper;

use AevovSimulationEngine\SimulationWeaver;
use AevovSimulationEngine\JobManager;

class SimulationEndpoint {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'aevov-sim/v1', '/start', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'start_simulation' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );

        register_rest_route( 'aevov-sim/v1', '/stop/(?P<job_id>[a-zA-Z0-9-]+)', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'stop_simulation' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );

        register_rest_route( 'aevov-sim/v1', '/interact/(?P<job_id>[a-zA-Z0-9-]+)', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'interact_with_simulation' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );

        register_rest_route( 'aevov-sim/v1', '/visualize', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'visualize_model' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );

        register_rest_route( 'aevov-sim/v1', '/visualize-memory', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'visualize_memory' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );
    }

    public function start_simulation( $request ) {
        $params = $request->get_params();
        $job_manager = new JobManager();
        $job_id = $job_manager->create_job( $params );

        // Simulate triggering the backend worker process
        $weaver = new SimulationWeaver();
        $initial_state = $weaver->get_initial_state( $params );

        $job_manager->update_job_status( $job_id, 'running', ['initial_state' => $initial_state] );

        $websocket_server = new \AevovSimulationEngine\WebSocketServer();
        $websocket_server->broadcast( [
            'job_id' => $job_id,
            'status' => 'started',
            'progress' => 0,
            'message' => 'Simulation started.',
            'data' => $initial_state
        ] );

        return new \WP_REST_Response( [ 'job_id' => $job_id, 'status' => 'started', 'data' => $initial_state ] );
    }

    public function stop_simulation( $request ) {
        $job_id = $request['job_id'];
        $job_manager = new JobManager();

        $job_data = $job_manager->get_job( $job_id );
        if ( ! $job_data ) {
            return new \WP_REST_Response( [ 'status' => 'error', 'message' => 'Job not found.' ], 404 );
        }

        $job_manager->update_job_status( $job_id, 'stopped', ['final_state' => 'Simulated final state.'] );

        $websocket_server = new \AevovSimulationEngine\WebSocketServer();
        $websocket_server->broadcast( [
            'job_id' => $job_id,
            'status' => 'stopped',
            'progress' => 100,
            'message' => 'Simulation stopped.',
            'data' => 'Simulated final state.'
        ] );

        return new \WP_REST_Response( [ 'job_id' => $job_id, 'status' => 'stopped' ] );
    }

    public function interact_with_simulation( $request ) {
        $job_id = $request['job_id'];
        $params = $request->get_params();
        $job_manager = new JobManager();

        $job_data = $job_manager->get_job( $job_id );
        if ( ! $job_data ) {
            return new \WP_REST_Response( [ 'status' => 'error', 'message' => 'Job not found.' ], 404 );
        }

        // Simulate interaction leading to a state change
        $interaction_result = [
            'message' => 'Interaction processed.',
            'effect' => 'Simulated state change based on interaction.',
            'params_received' => $params,
        ];
        $job_manager->update_job_status( $job_id, 'interacting', ['interaction_result' => $interaction_result] );

        $websocket_server = new \AevovSimulationEngine\WebSocketServer();
        $websocket_server->broadcast( [
            'job_id' => $job_id,
            'status' => 'interacting',
            'progress' => 50,
            'message' => 'Simulation interaction received.',
            'data' => $interaction_result
        ] );

        return new \WP_REST_Response( [ 'job_id' => $job_id, 'status' => 'interaction_received', 'data' => $interaction_result ] );
    }

    public function visualize_model( $request ) {
        $model_params = $request->get_param( 'model_params' ); // Changed from 'model' for clarity
        // Simulate a complex 3D model visualization data structure
        $visualization_data = [
            'nodes' => [
                ['id' => 'n1', 'label' => 'Neuron A', 'x' => rand(0,100), 'y' => rand(0,100), 'z' => rand(0,100)],
                ['id' => 'n2', 'label' => 'Neuron B', 'x' => rand(0,100), 'y' => rand(0,100), 'z' => rand(0,100)],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2', 'weight' => 0.5],
            ],
            'metadata' => ['model_type' => 'Simulated Neural Network', 'params' => $model_params],
            'description' => 'A simulated 3D visualization of a neural model.'
        ];
        return new \WP_REST_Response( [ 'visualization' => $visualization_data ] );
    }

    public function visualize_memory( $request ) {
        $memory_system_params = $request->get_param( 'memory_system_params' ); // Changed from 'memory_system' for clarity
        // Simulate a 3D memory system visualization data structure (e.g., hippocampus)
        $visualization_data = [
            'cells' => [
                ['id' => 'c1', 'type' => 'Pyramidal', 'location' => 'CA1', 'activity' => rand(0,1)],
                ['id' => 'c2', 'type' => 'Interneuron', 'location' => 'DG', 'activity' => rand(0,1)],
            ],
            'connections' => [
                ['from' => 'c1', 'to' => 'c2', 'strength' => 0.8],
            ],
            'metadata' => ['system_type' => 'Simulated Hippocampus', 'params' => $memory_system_params],
            'description' => 'A simulated 3D visualization of a memory system.'
        ];
        return new \WP_REST_Response( [ 'visualization' => $visualization_data ] );
    }
}
