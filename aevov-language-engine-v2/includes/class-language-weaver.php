<?php
namespace AevovLanguageEngineV2\Core;

use AevovChunkRegistry\ChunkRegistry;
use AevovCubbitCDN\AevovCubbitCDN;
use AevovEmbeddingEngine\EmbeddingManager;
use Exception;
use WP_Error;

require_once dirname(__FILE__) . '/../../../aevov-chunk-registry/includes/class-chunk-registry.php';
require_once dirname(__FILE__) . '/../../../aevov-cubbit-cdn/aevov-cubbit-cdn.php';
require_once dirname(__FILE__) . '/../../../aevov-embedding-engine/includes/class-embedding-manager.php';

class LanguageWeaver {

    private $logger;
    private $embedding_manager;

    public function __construct() {
        $this->logger = new class {
            public function info($message, $context = []) { error_log('[INFO] ' . $message . ' ' . json_encode($context)); }
            public function error($message, $context = []) { error_log('[ERROR] ' . $message . ' ' . json_encode($context)); }
        };
        $this->embedding_manager = new EmbeddingManager();
    }

    public function get_playlist( $prompt, $model_hash ) {
        $this->logger->info('Generating playlist for prompt', ['prompt' => $prompt, 'model_hash' => $model_hash]);

        try {
            $metadata = $this->get_model_metadata( $model_hash );
            $playlist = $this->generate_playlist_from_metadata( $prompt, $metadata, $model_hash );
        } catch ( Exception $e ) {
            $this->logger->error('Playlist generation failed', ['error' => $e->getMessage()]);
            return new WP_Error( 'playlist_generation_failed', $e->getMessage() );
        }

        $this->logger->info('Playlist generated successfully', ['playlist_length' => count($playlist)]);
        return $playlist;
    }

    private function get_model_metadata( $model_hash ) {
        $cdn = new AevovCubbitCDN();
        $cubbit_key = "models/{$model_hash}/bloom_chunks_metadata.json";
        $metadata_json = $cdn->download_object( $cubbit_key );

        if ( is_wp_error( $metadata_json ) ) {
            throw new Exception('Failed to download model metadata from Cubbit: ' . $metadata_json->get_error_message());
        }

        $metadata = json_decode( $metadata_json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new Exception('Failed to parse model metadata JSON.');
        }

        return $metadata;
    }

    private function generate_playlist_from_metadata( $prompt, $metadata, $model_hash ) {
        $prompt_embedding = $this->embedding_manager->embed_text( $prompt );
        $chunk_registry = new ChunkRegistry();

        $chunk_similarities = [];
        foreach ( $metadata as $chunk_info ) {
            $chunk_keys_embedding = $this->embedding_manager->embed_text( implode(' ', $chunk_info['keys']) );
            $similarity = $this->cosine_similarity( $prompt_embedding, $chunk_keys_embedding );
            $chunk_similarities[] = [
                'filename' => $chunk_info['filename'],
                'similarity' => $similarity,
            ];
        }

        // Sort chunks by similarity to the prompt
        usort($chunk_similarities, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        // For now, we will just return the top N most similar chunks.
        // A more sophisticated implementation would also consider the dependencies between chunks.
        $top_n = 10; // Or some other heuristic
        $playlist = array_slice( $chunk_similarities, 0, $top_n );

        return $playlist;
    }

    private function cosine_similarity( $vec1, $vec2 ) {
        $dot_product = 0;
        $norm1 = 0;
        $norm2 = 0;
        for ( $i = 0; $i < count($vec1); $i++ ) {
            $dot_product += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }
        $norm1 = sqrt( $norm1 );
        $norm2 = sqrt( $norm2 );

        if ( $norm1 == 0 || $norm2 == 0 ) {
            return 0;
        }

        return $dot_product / ( $norm1 * $norm2 );
    }
}
