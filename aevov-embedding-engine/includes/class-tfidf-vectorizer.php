<?php
/**
 * TF-IDF Vectorizer
 * Real TF-IDF (Term Frequency-Inverse Document Frequency) implementation
 */

namespace AevovEmbeddingEngine;

class TFIDFVectorizer {

    private $vocabulary = [];
    private $idf_scores = [];
    private $document_count = 0;
    private $max_features = 1000;
    private $min_df = 1; // Minimum document frequency
    private $max_df = 1.0; // Maximum document frequency (as ratio)

    /**
     * Fit the vectorizer on a corpus of documents
     */
    public function fit($documents) {
        $this->document_count = count($documents);
        $document_frequencies = [];

        // Count document frequencies
        foreach ($documents as $doc) {
            $tokens = $this->tokenize($doc);
            $unique_tokens = array_unique($tokens);

            foreach ($unique_tokens as $token) {
                if (!isset($document_frequencies[$token])) {
                    $document_frequencies[$token] = 0;
                }
                $document_frequencies[$token]++;
            }
        }

        // Filter by document frequency
        $max_df_count = $this->max_df * $this->document_count;
        $filtered_tokens = [];

        foreach ($document_frequencies as $token => $df) {
            if ($df >= $this->min_df && $df <= $max_df_count) {
                $filtered_tokens[$token] = $df;
            }
        }

        // Select top features by document frequency
        arsort($filtered_tokens);
        $this->vocabulary = array_slice(array_keys($filtered_tokens), 0, $this->max_features);
        $this->vocabulary = array_flip($this->vocabulary); // Token => Index

        // Calculate IDF scores
        foreach ($this->vocabulary as $token => $index) {
            $df = $filtered_tokens[$token];
            // IDF = log(N / df) where N is total documents
            $this->idf_scores[$token] = log($this->document_count / $df);
        }
    }

    /**
     * Transform documents to TF-IDF vectors
     */
    public function transform($documents) {
        $vectors = [];

        foreach ($documents as $doc) {
            $vectors[] = $this->vectorize_document($doc);
        }

        return $vectors;
    }

    /**
     * Fit and transform in one step
     */
    public function fit_transform($documents) {
        $this->fit($documents);
        return $this->transform($documents);
    }

    /**
     * Vectorize a single document
     */
    private function vectorize_document($document) {
        $tokens = $this->tokenize($document);
        $term_frequencies = array_count_values($tokens);
        $total_terms = count($tokens);

        // Initialize vector with zeros
        $vector = array_fill(0, count($this->vocabulary), 0.0);

        foreach ($term_frequencies as $token => $count) {
            if (isset($this->vocabulary[$token])) {
                $index = $this->vocabulary[$token];

                // TF = term_count / total_terms
                $tf = $count / $total_terms;

                // TF-IDF = TF * IDF
                $tfidf = $tf * $this->idf_scores[$token];

                $vector[$index] = $tfidf;
            }
        }

        return $vector;
    }

    /**
     * Get feature names (vocabulary)
     */
    public function get_feature_names() {
        return array_keys($this->vocabulary);
    }

    /**
     * Simple tokenization
     */
    private function tokenize($text) {
        // Convert to lowercase
        $text = strtolower($text);

        // Remove punctuation
        $text = preg_replace('/[^\w\s]/', ' ', $text);

        // Split on whitespace
        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove stop words
        $tokens = $this->remove_stop_words($tokens);

        return $tokens;
    }

    /**
     * Remove common stop words
     */
    private function remove_stop_words($tokens) {
        $stop_words = [
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
            'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the',
            'to', 'was', 'will', 'with', 'the', 'this', 'but', 'they', 'have',
            'had', 'what', 'when', 'where', 'who', 'which', 'why', 'how',
        ];

        $stop_words = array_flip($stop_words);

        return array_filter($tokens, function($token) use ($stop_words) {
            return !isset($stop_words[$token]) && strlen($token) > 2;
        });
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    public function cosine_similarity($vec1, $vec2) {
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
     * Find most similar documents
     */
    public function find_similar($query_vector, $document_vectors, $top_k = 5) {
        $similarities = [];

        foreach ($document_vectors as $index => $doc_vector) {
            $similarities[$index] = $this->cosine_similarity($query_vector, $doc_vector);
        }

        arsort($similarities);

        return array_slice($similarities, 0, $top_k, true);
    }

    /**
     * Set maximum features
     */
    public function set_max_features($max_features) {
        $this->max_features = $max_features;
    }

    /**
     * Set minimum document frequency
     */
    public function set_min_df($min_df) {
        $this->min_df = $min_df;
    }

    /**
     * Set maximum document frequency
     */
    public function set_max_df($max_df) {
        $this->max_df = $max_df;
    }

    /**
     * Get vocabulary size
     */
    public function vocabulary_size() {
        return count($this->vocabulary);
    }

    /**
     * Get IDF score for a term
     */
    public function get_idf($term) {
        return $this->idf_scores[$term] ?? 0.0;
    }
}
