<?php

namespace AevovTranscriptionEngine\API;

use Aevov\Security\SecurityHelper;

use AevovTranscriptionEngine\TranscriptionManager;
use AevovChunkRegistry\ChunkRegistry;
use AevovChunkRegistry\AevovChunk;

require_once dirname(__FILE__) . '/../../../aevov-chunk-registry/includes/class-chunk-registry.php';
require_once dirname(__FILE__) . '/../../../aevov-chunk-registry/includes/class-aevov-chunk.php';

class TranscriptionEndpoint {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'aevov-transcription/v1', '/transcribe', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'transcribe_audio' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );
    }

    public function transcribe_audio( $request ) {
        $files = $request->get_file_params();
        if ( empty( $files['audio'] ) ) {
            return new \WP_Error( 'no_audio_file', 'No audio file provided.', [ 'status' => 400 ] );
        }

        $audio_file = $files['audio'];
        $tmp_path = $audio_file['tmp_name'];

        $manager = new TranscriptionManager();
        $transcription_chunk_data = $manager->transcribe_audio( $tmp_path );

        if ( is_wp_error( $transcription_chunk_data ) ) {
            return $transcription_chunk_data;
        }

        // Register the chunk in the Aevov Chunk Registry.
        $chunk_registry = new ChunkRegistry();
        $chunk = new AevovChunk(
            $transcription_chunk_data['id'],
            $transcription_chunk_data['type'],
            $transcription_chunk_data['cubbit_key'],
            $transcription_chunk_data['metadata']
        );
        $chunk_registry->register_chunk( $chunk );

        return new \WP_REST_Response( $transcription_chunk_data );
    }
}
