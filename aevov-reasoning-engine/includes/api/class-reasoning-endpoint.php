<?php

namespace AevovReasoningEngine\API;

use Aevov\Security\SecurityHelper;

use AevovPatternSyncProtocol\Comparison\APS_Comparator;

class ReasoningEndpoint {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'aevov-reasoning/v1', '/infer', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'make_inference' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );

        register_rest_route( 'aevov-reasoning/v1', '/find-analogy', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'find_analogy' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );
    }

    public function make_inference( $request ) {
        $pattern = $request->get_param( 'pattern' );
        $comparator = new APS_Comparator();
        $analogous_patterns = $comparator->find_analogous_patterns( $pattern );

        if (empty($analogous_patterns)) {
            return new \WP_REST_Response( [ 'inference' => 'No analogous patterns found.' ] );
        }

        // The first pattern is the most similar one
        $best_match = $analogous_patterns[0];

        // Generate an inference based on the best match
        $inference = 'Based on the best matching pattern (score: ' . $best_match['score'] . '), the inference is: ' . json_encode($best_match['chunk']->metadata);

        return new \WP_REST_Response( [ 'inference' => $inference, 'best_match' => $best_match ] );
    }

    public function find_analogy( $request ) {
        $pattern = $request->get_param( 'pattern' );
        $comparator = new APS_Comparator();
        $analogous_patterns = $comparator->find_analogous_patterns( $pattern );
        return new \WP_REST_Response( [ 'analogous_patterns' => $analogous_patterns ] );
    }
}
