<?php

namespace AevovSuperAppForge\API;

use Aevov\Security\SecurityHelper;

use AevovSuperAppForge\SuperAppWeaver;

class SimulationEndpoint {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'aevov-super-app/v1', '/simulate', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'simulate_generation' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );
    }

    public function simulate_generation( $request ) {
        $uad = $request->get_param( 'uad' );
        $weaver = new SuperAppWeaver();
        $ticks = $weaver->simulate_generation( $uad );
        return new \WP_REST_Response( $ticks );
    }
}
