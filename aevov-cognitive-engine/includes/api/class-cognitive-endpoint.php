<?php

namespace AevovCognitiveEngine\API;

use Aevov\Security\SecurityHelper;

use AevovCognitiveEngine\CognitiveConductor;

class CognitiveEndpoint {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'aevov-cognitive/v1', '/solve', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'solve_problem' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );
    }

    public function solve_problem( $request ) {
        $problem = $request->get_param( 'problem' );
        $conductor = new CognitiveConductor();
        $solution = $conductor->solve_problem( $problem );
        return new \WP_REST_Response( [ 'solution' => $solution ] );
    }
}
