<?php

namespace AevovSimulationEngine;

class SimulationWeaver {

    public function __construct() {
        // We will add our hooks and filters here.
    }

    public function get_initial_state( $params ) {
        $sim_type = isset( $params['sim_type'] ) ? sanitize_text_field( $params['sim_type'] ) : 'default';
        $grid_size = isset( $params['grid_size'] ) ? intval( $params['grid_size'] ) : 10;

        $entities = [];
        // Generate a varying number of entities based on sim_type or random
        $num_entities = rand( 3, 7 );
        if ( $sim_type === 'complex' ) {
            $num_entities = rand( 8, 15 );
        }

        for ( $i = 1; $i <= $num_entities; $i++ ) {
            $entities[] = [
                'id' => $i,
                'x' => rand( 0, $grid_size - 1 ),
                'y' => rand( 0, $grid_size - 1 ),
                'type' => ( $i % 2 === 0 ) ? 'agent' : 'resource',
                'energy' => rand( 50, 100 ),
            ];
        }

        return [
            'sim_type' => $sim_type,
            'grid_size' => $grid_size,
            'entities' => $entities,
            'timestamp' => microtime(true),
            'status_message' => 'Initial state generated for ' . $sim_type . ' simulation.'
        ];
    }
}
