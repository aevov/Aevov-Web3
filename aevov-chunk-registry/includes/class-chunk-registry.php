<?php

namespace AevovChunkRegistry;

class ChunkRegistry {

    public function register_chunk( AevovChunk $chunk ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aevov_chunks';

        $embedding_engine = new \AevovEmbeddingEngine\EmbeddingManager();
        $embedding = $embedding_engine->get_embedding($chunk->metadata);

        $data = [
            'chunk_id' => $chunk->id,
            'type' => $chunk->type,
            'cubbit_key' => $chunk->cubbit_key,
            'metadata' => json_encode($chunk->metadata),
            'dependencies' => json_encode($chunk->dependencies),
            'embedding' => json_encode($embedding)
        ];

        $wpdb->insert($table_name, $data);

        return $wpdb->insert_id;
    }

    public function get_chunk( $chunk_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aevov_chunks';

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE chunk_id = %s", $chunk_id)
        );

        if ($row) {
            return new AevovChunk(
                $row->chunk_id,
                $row->type,
                $row->cubbit_key,
                json_decode($row->metadata, true),
                json_decode($row->dependencies, true)
            );
        }

        return null;
    }

    public function delete_chunk( $chunk_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aevov_chunks';

        return $wpdb->delete($table_name, ['chunk_id' => $chunk_id]);
    }
    public function find_similar_chunks( $pattern, $top_n = 5 ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aevov_chunks';

        $embedding_engine = new \AevovEmbeddingEngine\EmbeddingManager();
        $pattern_embedding = $embedding_engine->get_embedding($pattern->metadata);

        $all_chunks = $wpdb->get_results( "SELECT * FROM $table_name" );

        $similarities = [];
        foreach ($all_chunks as $chunk) {
            $chunk_embedding = json_decode($chunk->embedding, true);
            $similarity = $this->cosine_similarity($pattern_embedding, $chunk_embedding);
            $similarities[$chunk->chunk_id] = $similarity;
        }

        arsort($similarities);

        $top_chunks = array_slice($similarities, 0, $top_n, true);

        $chunks = [];
        foreach ($top_chunks as $chunk_id => $score) {
            $chunks[] = [
                'chunk' => $this->get_chunk($chunk_id),
                'score' => $score
            ];
        }

        return $chunks;
    }

    private function cosine_similarity(array $vec1, array $vec2): float {
        $dot_product = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;
        $count = count($vec1);

        if ($count !== count($vec2)) {
            return 0.0; // Vectors must have the same dimension
        }

        for ($i = 0; $i < $count; $i++) {
            $dot_product += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }

        if ($norm1 == 0.0 || $norm2 == 0.0) {
            return 0.0; // Avoid division by zero
        }

        return $dot_product / (sqrt($norm1) * sqrt($norm2));
    }
}
