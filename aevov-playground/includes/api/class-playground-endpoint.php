<?php

namespace AevovPlayground\API;

use Aevov\Security\SecurityHelper;

use AevovChunkRegistry\ChunkRegistry;
use AevovChunkRegistry\AevovChunk;
use AevovCubbitCDN\AevovCubbitCDN;

require_once dirname(__FILE__) . '/../../../aevov-chunk-registry/includes/class-chunk-registry.php';
require_once dirname(__FILE__) . '/../../../aevov-chunk-registry/includes/class-aevov-chunk.php';
require_once dirname(__FILE__) . '/../../../aevov-cubbit-cdn/aevov-cubbit-cdn.php';

class PlaygroundEndpoint {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'aevov-playground/v1', '/proxy', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'proxy_request' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );

        register_rest_route( 'aevov-playground/v1', '/save-pattern', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'save_pattern' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );
    }

    public function proxy_request( $request ) {
        $engine = $request->get_param( 'engine' );
        $payload = $request->get_param( 'payload' );
        $pattern_jitter = $request->get_param( 'pattern_jitter' );
        $cross_modal_synesthesia = $request->get_param( 'cross_modal_synesthesia' );

        switch ( $engine ) {
            case 'language':
                $endpoint = '/aevov-language/v2/generate';
                break;
            case 'image':
                $endpoint = '/aevov-image/v1/generate';
                break;
            case 'music':
                $endpoint = '/aevov-music/v1/compose';
                break;
            case 'stream':
                $endpoint = '/aevov-stream/v1/start-session';
                break;
            case 'application':
                $endpoint = '/aevov-app/v1/spawn';
                break;
            case 'transcription':
                $endpoint = '/aevov-transcription/v1/transcribe';
                break;
            case 'embedding':
                $endpoint = '/aevov-embedding/v1/embed';
                break;
            default:
                return new \WP_Error( 'invalid_engine', 'Invalid engine.', [ 'status' => 400 ] );
        }

        $payload['pattern_jitter'] = $pattern_jitter;
        $payload['cross_modal_synesthesia'] = $cross_modal_synesthesia;

        $request = new \WP_REST_Request( 'POST', $endpoint );
        $request->set_body_params( $payload );
        $response = rest_do_request( $request );

        return $response;
    }

    public function save_pattern( $request ) {
        $workflow = $request->get_param( 'workflow' );

        if ( empty( $workflow ) ) {
            return new \WP_Error( 'no_workflow_data', 'No workflow data provided.', [ 'status' => 400 ] );
        }

        $pattern_id = 'workflow-pattern-' . uniqid();
        $cubbit_key = 'workflow-patterns/' . $pattern_id . '.json';

        $cdn = new AevovCubbitCDN();
        $upload_result = $cdn->upload_object( $cubbit_key, json_encode( $workflow ) );

        if ( is_wp_error( $upload_result ) ) {
            return new \WP_Error( 'cubbit_upload_failed', 'Failed to upload workflow data to Cubbit.', [ 'status' => 500 ] );
        }

        $chunk = new AevovChunk(
            $pattern_id,
            'workflow_pattern',
            $cubbit_key,
            [
                'created_by' => get_current_user_id(),
            ]
        );

        $chunk_registry = new ChunkRegistry();
        $chunk_registry->register_chunk( $chunk );

        return new \WP_REST_Response( [ 'pattern_id' => $pattern_id, 'cubbit_key' => $cubbit_key ] );
    }
}
