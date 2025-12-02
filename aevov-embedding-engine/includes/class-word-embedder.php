<?php
/**
 * Word Embedder
 * Simplified Word2Vec-style embeddings using skip-gram model
 */

namespace AevovEmbeddingEngine;

class WordEmbedder {

    private $vocabulary = [];
    private $word_vectors = [];
    private $embedding_dim = 100;
    private $window_size = 5;
    private $learning_rate = 0.025;
    private $min_count = 5;

    /**
     * Train embeddings on corpus
     */
    public function train($documents, $epochs = 5) {
        // Build vocabulary
        $this->build_vocabulary($documents);

        // Initialize random vectors
        $this->initialize_vectors();

        // Train using skip-gram model
        for ($epoch = 0; $epoch < $epochs; $epoch++) {
            $this->train_epoch($documents);

            // Decay learning rate
            $this->learning_rate *= 0.9;
        }
    }

    /**
     * Build vocabulary from documents
     */
    private function build_vocabulary($documents) {
        $word_counts = [];

        foreach ($documents as $doc) {
            $tokens = $this->tokenize($doc);

            foreach ($tokens as $token) {
                if (!isset($word_counts[$token])) {
                    $word_counts[$token] = 0;
                }
                $word_counts[$token]++;
            }
        }

        // Filter by minimum count
        foreach ($word_counts as $word => $count) {
            if ($count >= $this->min_count) {
                $this->vocabulary[] = $word;
            }
        }

        $this->vocabulary = array_values($this->vocabulary);
    }

    /**
     * Initialize word vectors randomly
     */
    private function initialize_vectors() {
        foreach ($this->vocabulary as $word) {
            $vector = [];
            for ($i = 0; $i < $this->embedding_dim; $i++) {
                // Random initialization between -0.5 and 0.5
                $vector[] = (mt_rand() / mt_getrandmax()) - 0.5;
            }
            $this->word_vectors[$word] = $vector;
        }
    }

    /**
     * Train one epoch
     */
    private function train_epoch($documents) {
        foreach ($documents as $doc) {
            $tokens = $this->tokenize($doc);

            // Skip-gram: predict context words from target word
            for ($i = 0; $i < count($tokens); $i++) {
                $target_word = $tokens[$i];

                if (!isset($this->word_vectors[$target_word])) {
                    continue;
                }

                // Get context window
                $start = max(0, $i - $this->window_size);
                $end = min(count($tokens), $i + $this->window_size + 1);

                for ($j = $start; $j < $end; $j++) {
                    if ($i === $j) {
                        continue;
                    }

                    $context_word = $tokens[$j];

                    if (!isset($this->word_vectors[$context_word])) {
                        continue;
                    }

                    // Update vectors
                    $this->update_vectors($target_word, $context_word);
                }
            }
        }
    }

    /**
     * Update word vectors using gradient descent
     */
    private function update_vectors($target_word, $context_word) {
        $target_vec = $this->word_vectors[$target_word];
        $context_vec = $this->word_vectors[$context_word];

        // Calculate dot product (similarity)
        $dot_product = 0;
        for ($i = 0; $i < $this->embedding_dim; $i++) {
            $dot_product += $target_vec[$i] * $context_vec[$i];
        }

        // Sigmoid activation
        $sigmoid = 1.0 / (1.0 + exp(-$dot_product));

        // Gradient (simplified)
        $error = 1.0 - $sigmoid;

        // Update vectors
        for ($i = 0; $i < $this->embedding_dim; $i++) {
            $gradient = $error * $context_vec[$i];
            $this->word_vectors[$target_word][$i] += $this->learning_rate * $gradient;

            $gradient = $error * $target_vec[$i];
            $this->word_vectors[$context_word][$i] += $this->learning_rate * $gradient;
        }
    }

    /**
     * Get word vector
     */
    public function get_word_vector($word) {
        return $this->word_vectors[$word] ?? null;
    }

    /**
     * Get document embedding (average of word vectors)
     */
    public function embed_document($document) {
        $tokens = $this->tokenize($document);
        $vector_sum = array_fill(0, $this->embedding_dim, 0);
        $count = 0;

        foreach ($tokens as $token) {
            if (isset($this->word_vectors[$token])) {
                $word_vec = $this->word_vectors[$token];
                for ($i = 0; $i < $this->embedding_dim; $i++) {
                    $vector_sum[$i] += $word_vec[$i];
                }
                $count++;
            }
        }

        if ($count === 0) {
            return array_fill(0, $this->embedding_dim, 0);
        }

        // Average
        for ($i = 0; $i < $this->embedding_dim; $i++) {
            $vector_sum[$i] /= $count;
        }

        return $vector_sum;
    }

    /**
     * Find most similar words
     */
    public function most_similar($word, $top_k = 10) {
        if (!isset($this->word_vectors[$word])) {
            return [];
        }

        $target_vec = $this->word_vectors[$word];
        $similarities = [];

        foreach ($this->word_vectors as $other_word => $other_vec) {
            if ($other_word === $word) {
                continue;
            }

            $similarity = $this->cosine_similarity($target_vec, $other_vec);
            $similarities[$other_word] = $similarity;
        }

        arsort($similarities);

        return array_slice($similarities, 0, $top_k, true);
    }

    /**
     * Cosine similarity
     */
    private function cosine_similarity($vec1, $vec2) {
        $dot_product = 0;
        $norm1 = 0;
        $norm2 = 0;

        for ($i = 0; $i < count($vec1); $i++) {
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
     * Tokenize text
     */
    private function tokenize($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_filter($tokens, function($token) {
            return strlen($token) > 2;
        });
    }

    /**
     * Save embeddings
     */
    public function save($filepath) {
        $data = [
            'vocabulary' => $this->vocabulary,
            'word_vectors' => $this->word_vectors,
            'embedding_dim' => $this->embedding_dim,
        ];

        return file_put_contents($filepath, json_encode($data));
    }

    /**
     * Load embeddings
     */
    public function load($filepath) {
        $json = file_get_contents($filepath);
        $data = json_decode($json, true);

        $this->vocabulary = $data['vocabulary'];
        $this->word_vectors = $data['word_vectors'];
        $this->embedding_dim = $data['embedding_dim'];
    }

    /**
     * Get vocabulary size
     */
    public function vocabulary_size() {
        return count($this->vocabulary);
    }

    /**
     * Set embedding dimension
     */
    public function set_embedding_dim($dim) {
        $this->embedding_dim = $dim;
    }

    /**
     * Set window size
     */
    public function set_window_size($size) {
        $this->window_size = $size;
    }

    /**
     * Analogy task: king - man + woman = ?
     */
    public function analogy($word_a, $word_b, $word_c) {
        if (!isset($this->word_vectors[$word_a]) ||
            !isset($this->word_vectors[$word_b]) ||
            !isset($this->word_vectors[$word_c])) {
            return null;
        }

        $vec_a = $this->word_vectors[$word_a];
        $vec_b = $this->word_vectors[$word_b];
        $vec_c = $this->word_vectors[$word_c];

        // Compute: vec_b - vec_a + vec_c
        $target_vec = [];
        for ($i = 0; $i < $this->embedding_dim; $i++) {
            $target_vec[$i] = $vec_b[$i] - $vec_a[$i] + $vec_c[$i];
        }

        // Find most similar word to target_vec
        $similarities = [];
        foreach ($this->word_vectors as $word => $vec) {
            if (in_array($word, [$word_a, $word_b, $word_c])) {
                continue;
            }

            $similarity = $this->cosine_similarity($target_vec, $vec);
            $similarities[$word] = $similarity;
        }

        arsort($similarities);

        return array_slice($similarities, 0, 5, true);
    }
}
