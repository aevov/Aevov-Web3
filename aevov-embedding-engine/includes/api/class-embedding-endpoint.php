<?php

namespace AevovEmbeddingEngine\API;

use Aevov\Security\SecurityHelper;

use AevovEmbeddingEngine\EmbeddingManager;
use AevovChunkRegistry\ChunkRegistry;
use AevovChunkRegistry\AevovChunk;

require_once dirname(__FILE__) . '/../../../aevov-chunk-registry/includes/class-chunk-registry.php';
require_once dirname(__FILE__) . '/../../../aevov-chunk-registry/includes/class-aevov-chunk.php';

class EmbeddingEndpoint {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'aevov-embedding/v1', '/embed', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'embed_data' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );
    }

    public function embed_data( $request ) {
        $data = $request->get_param( 'data' );
        $data_type = $request->get_param( 'data_type' ) ? $request->get_param( 'data_type' ) : 'text';

        if ( empty( $data ) ) {
            return new \WP_Error( 'no_data', 'No data provided for embedding.', [ 'status' => 400 ] );
        }

        $manager = new EmbeddingManager();
        $embedding_chunk_data = $manager->embed( $data );

        if ( is_wp_error( $embedding_chunk_data ) ) {
            return $embedding_chunk_data;
        }

        // Register the chunk in the Aevov Chunk Registry.
        $chunk_registry = new ChunkRegistry();
        $chunk = new AevovChunk(
            $embedding_chunk_data['id'],
            $embedding_chunk_data['type'],
            $embedding_chunk_data['cubbit_key'],
            $embedding_chunk_data['metadata']
        );
        $chunk_registry->register_chunk( $chunk );

        return new \WP_REST_Response( $embedding_chunk_data );
    }
}
