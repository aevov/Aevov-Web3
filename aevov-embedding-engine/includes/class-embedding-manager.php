<?php
/**
 * Embedding Manager - Real NLP Embeddings
 * Uses TF-IDF, Word2Vec-style, and sentence embeddings
 */

namespace AevovEmbeddingEngine\Core;

use AevovChunkRegistry\ChunkRegistry;
use Exception;
use WP_Error;

require_once dirname(__FILE__) . '/class-tfidf-vectorizer.php';
require_once dirname(__FILE__) . '/class-word-embedder.php';
require_once dirname(__FILE__) . '/class-sentence-embedder.php';
require_once dirname(__FILE__) . '/../../../aevov-chunk-registry/includes/class-chunk-registry.php';

class EmbeddingManager {

    private $logger;
    private $tfidf_vectorizer;
    private $word_embedder;
    private $sentence_embedder;
    private $embedding_cache = [];

    public function __construct() {
        $this->logger = new class {
            public function info($message, $context = []) {
                error_log('[EmbeddingManager] ' . $message . ' ' . json_encode($context));
            }
            public function error($message, $context = []) {
                error_log('[EmbeddingManager ERROR] ' . $message . ' ' . json_encode($context));
            }
        };

        $this->tfidf_vectorizer = new \AevovEmbeddingEngine\TFIDFVectorizer();
        $this->word_embedder = new \AevovEmbeddingEngine\WordEmbedder();
        $this->sentence_embedder = new \AevovEmbeddingEngine\SentenceEmbedder(
            $this->word_embedder,
            $this->tfidf_vectorizer
        );
    }

    /**
     * Main embed function - routes to appropriate method
     */
    public function embed($data, $method = 'auto') {
        $this->logger->info('Embedding data', ['method' => $method]);

        try {
            // Determine data type
            if (is_string($data)) {
                $embedding = $this->embed_text($data, $method);
            } elseif (is_array($data)) {
                // Check if array of documents or single structured data
                if (isset($data[0]) && is_string($data[0])) {
                    $embedding = $this->embed_documents($data, $method);
                } else {
                    $embedding = $this->embed_structured($data, $method);
                }
            } else {
                throw new Exception('Unsupported data type for embedding');
            }

            $this->store_embedding($data, $embedding);

            $this->logger->info('Embedding completed successfully');

            return [
                'vector' => $embedding,
                'dimension' => count($embedding),
                'method' => $method,
                'metadata' => [
                    'data_type' => gettype($data),
                    'timestamp' => time(),
                ],
            ];

        } catch (Exception $e) {
            $this->logger->error('Embedding failed', ['error' => $e->getMessage()]);
            return new WP_Error('embedding_failed', $e->getMessage());
        }
    }

    /**
     * Embed single text string
     */
    private function embed_text($text, $method = 'auto') {
        // Check cache
        $cache_key = md5($text . $method);
        if (isset($this->embedding_cache[$cache_key])) {
            return $this->embedding_cache[$cache_key];
        }

        $embedding = null;

        switch ($method) {
            case 'tfidf':
                // Use TF-IDF
                $this->tfidf_vectorizer->fit([$text]);
                $vectors = $this->tfidf_vectorizer->transform([$text]);
                $embedding = $vectors[0];
                break;

            case 'word2vec':
            case 'average':
                // Average word embeddings
                $embedding = $this->sentence_embedder->embed_average($text);
                break;

            case 'tfidf_weighted':
                // TF-IDF weighted word embeddings
                $embedding = $this->sentence_embedder->embed_tfidf_weighted($text);
                break;

            case 'max_pooling':
                // Max pooling over word embeddings
                $embedding = $this->sentence_embedder->embed_max_pooling($text);
                break;

            case 'ngrams':
                // Character n-grams
                $embedding = $this->sentence_embedder->embed_ngrams($text);
                break;

            case 'auto':
            default:
                // Auto-select based on text length
                if (strlen($text) < 100) {
                    // Short text - use n-grams
                    $embedding = $this->sentence_embedder->embed_ngrams($text);
                } else {
                    // Long text - use TF-IDF
                    $this->tfidf_vectorizer->fit([$text]);
                    $vectors = $this->tfidf_vectorizer->transform([$text]);
                    $embedding = $vectors[0];
                }
                break;
        }

        // Cache result
        $this->embedding_cache[$cache_key] = $embedding;

        return $embedding;
    }

    /**
     * Embed multiple documents
     */
    private function embed_documents($documents, $method = 'tfidf') {
        if ($method === 'tfidf') {
            $this->tfidf_vectorizer->fit($documents);
            return $this->tfidf_vectorizer->transform($documents);
        } elseif ($method === 'word2vec' || $method === 'average') {
            return $this->sentence_embedder->batch_embed($documents, 'average');
        } else {
            return $this->sentence_embedder->batch_embed($documents, $method);
        }
    }

    /**
     * Embed structured data (convert to text representation)
     */
    private function embed_structured($data, $method = 'auto') {
        // Convert structured data to text
        $text = $this->structured_to_text($data);
        return $this->embed_text($text, $method);
    }

    /**
     * Convert structured data to text representation
     */
    private function structured_to_text($data, $prefix = '') {
        $text_parts = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $text_parts[] = $this->structured_to_text($value, $prefix . $key . ' ');
            } else {
                $text_parts[] = $prefix . $key . ' ' . $value;
            }
        }

        return implode(' ', $text_parts);
    }

    /**
     * Train word embeddings on corpus
     */
    public function train_word_embeddings($documents, $epochs = 5, $embedding_dim = 100) {
        $this->logger->info('Training word embeddings', [
            'num_documents' => count($documents),
            'epochs' => $epochs,
            'embedding_dim' => $embedding_dim,
        ]);

        $this->word_embedder->set_embedding_dim($embedding_dim);
        $this->word_embedder->train($documents, $epochs);

        $this->logger->info('Word embedding training complete', [
            'vocabulary_size' => $this->word_embedder->vocabulary_size(),
        ]);

        return [
            'vocabulary_size' => $this->word_embedder->vocabulary_size(),
            'embedding_dim' => $embedding_dim,
            'epochs' => $epochs,
        ];
    }

    /**
     * Find similar documents
     */
    public function find_similar($query, $candidates, $top_k = 5, $method = 'auto') {
        $query_embedding = $this->embed_text($query, $method);

        $similarities = [];
        foreach ($candidates as $index => $candidate) {
            $candidate_embedding = $this->embed_text($candidate, $method);
            $similarity = $this->cosine_similarity($query_embedding, $candidate_embedding);
            $similarities[$index] = $similarity;
        }

        arsort($similarities);

        return array_slice($similarities, 0, $top_k, true);
    }

    /**
     * Compute semantic similarity
     */
    public function semantic_similarity($text1, $text2, $method = 'auto') {
        $vec1 = $this->embed_text($text1, $method);
        $vec2 = $this->embed_text($text2, $method);

        return $this->cosine_similarity($vec1, $vec2);
    }

    /**
     * Cosine similarity
     */
    private function cosine_similarity($vec1, $vec2) {
        $dot_product = 0;
        $norm1 = 0;
        $norm2 = 0;

        $min_len = min(count($vec1), count($vec2));

        for ($i = 0; $i < $min_len; $i++) {
            $dot_product += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }

        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);

        if ($norm1 == 0 || $norm2 == 0) {
            return 0.0;
        }

        return $dot_product / ($norm1 * $norm2);
    }

    /**
     * Store embedding in chunk registry
     */
    private function store_embedding($source, $embedding) {
        $source_hash = md5(json_encode($source));

        $pattern_data = [
            'type' => 'embedding',
            'features' => [
                'source_hash' => $source_hash,
                'embedding' => $embedding,
                'dimension' => count($embedding),
            ],
            'confidence' => 1.0,
            'metadata' => [
                'timestamp' => time(),
                'algorithm' => 'real_nlp_embeddings',
            ],
        ];

        $registry = new ChunkRegistry();
        $chunk = new \AevovChunkRegistry\AevovChunk(
            $source_hash,
            'embedding',
            '',
            $pattern_data
        );
        $registry->register_chunk($chunk);

        $this->logger->info('Embedding stored in registry', ['source_hash' => $source_hash]);
    }

    /**
     * Batch process multiple texts
     */
    public function batch_embed($texts, $method = 'auto') {
        $embeddings = [];

        foreach ($texts as $text) {
            $result = $this->embed($text, $method);
            if (!is_wp_error($result)) {
                $embeddings[] = $result['vector'];
            }
        }

        return $embeddings;
    }

    /**
     * Get word vector
     */
    public function get_word_vector($word) {
        return $this->word_embedder->get_word_vector($word);
    }

    /**
     * Find similar words
     */
    public function find_similar_words($word, $top_k = 10) {
        return $this->word_embedder->most_similar($word, $top_k);
    }

    /**
     * Word analogy task
     */
    public function word_analogy($word_a, $word_b, $word_c) {
        return $this->word_embedder->analogy($word_a, $word_b, $word_c);
    }

    /**
     * Save trained models
     */
    public function save_models($directory) {
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $this->word_embedder->save($directory . '/word_embeddings.json');

        $this->logger->info('Models saved', ['directory' => $directory]);

        return true;
    }

    /**
     * Load trained models
     */
    public function load_models($directory) {
        if (file_exists($directory . '/word_embeddings.json')) {
            $this->word_embedder->load($directory . '/word_embeddings.json');
            $this->logger->info('Models loaded', ['directory' => $directory]);
            return true;
        }

        return false;
    }

    /**
     * Clear cache
     */
    public function clear_cache() {
        $this->embedding_cache = [];
    }
}
