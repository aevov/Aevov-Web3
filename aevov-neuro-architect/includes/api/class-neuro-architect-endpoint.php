<?php

namespace AevovNeuroArchitect\API;

use Aevov\Security\SecurityHelper;

use AevovPatternSyncProtocol\Includes\Comparison\APS_Comparator;

class NeuroArchitectEndpoint {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'aevov-neuro/v1', '/compose', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'compose_model' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ] );
    }

    public function compose_model( $request ) {
        $blueprint = $request->get_param( 'blueprint' );
        $comparator = new APS_Comparator();
        $model = $comparator->compose_model( $blueprint );
        return new \WP_REST_Response( $model );
    }
}
