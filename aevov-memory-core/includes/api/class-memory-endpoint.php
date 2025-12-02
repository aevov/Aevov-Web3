<?php

namespace AevovMemoryCore\API;

use AevovMemoryCore\MemoryManager;
use Aevov\Security\SecurityHelper;

class MemoryEndpoint {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'aevov-memory/v1', '/memory', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'write_to_memory' ],
            'permission_callback' => [ SecurityHelper::class, 'can_edit_aevov' ],
        ] );

        register_rest_route( 'aevov-memory/v1', '/memory/(?P<address>[a-zA-Z0-9-]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'read_from_memory' ],
            'permission_callback' => [ SecurityHelper::class, 'can_read_aevov' ],
        ] );
    }

    public function write_to_memory( $request ) {
        // Sanitize inputs
        $address = SecurityHelper::sanitize_text( $request->get_param( 'address' ) );
        $data = $request->get_param( 'data' );

        // Validate required parameters
        if ( empty( $address ) ) {
            return new \WP_Error( 'missing_address', 'Memory address is required', [ 'status' => 400 ] );
        }

        if ( empty( $data ) ) {
            return new \WP_Error( 'missing_data', 'Data is required', [ 'status' => 400 ] );
        }

        $memory_manager = new MemoryManager();

        try {
            $result = $memory_manager->write_to_memory( $address, $data );

            // Log security event
            SecurityHelper::log_security_event( 'memory_write', [
                'address' => $address,
                'data_size' => strlen( json_encode( $data ) )
            ] );

            return new \WP_REST_Response( [ 'success' => $result, 'address' => $address ] );
        } catch ( \Exception $e ) {
            return new \WP_Error( 'write_failed', $e->getMessage(), [ 'status' => 500 ] );
        }
    }

    public function read_from_memory( $request ) {
        // Sanitize input
        $address = SecurityHelper::sanitize_text( $request['address'] );

        // Validate required parameter
        if ( empty( $address ) ) {
            return new \WP_Error( 'missing_address', 'Memory address is required', [ 'status' => 400 ] );
        }

        $memory_manager = new MemoryManager();

        try {
            $data = $memory_manager->read_from_memory( $address );

            if ( $data === null ) {
                return new \WP_Error( 'not_found', 'Memory address not found', [ 'status' => 404 ] );
            }

            return new \WP_REST_Response( [ 'data' => $data, 'address' => $address ] );
        } catch ( \Exception $e ) {
            return new \WP_Error( 'read_failed', $e->getMessage(), [ 'status' => 500 ] );
        }
    }
}
