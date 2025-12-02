<?php
/**
 * Sentence Embedder
 * Generate sentence-level embeddings using various methods
 */

namespace AevovEmbeddingEngine;

class SentenceEmbedder {

    private $word_embedder;
    private $tfidf_vectorizer;
    private $embedding_dim = 100;

    public function __construct($word_embedder = null, $tfidf_vectorizer = null) {
        $this->word_embedder = $word_embedder;
        $this->tfidf_vectorizer = $tfidf_vectorizer;
    }

    /**
     * Average word embeddings (simple but effective)
     */
    public function embed_average($sentence) {
        if (!$this->word_embedder) {
            return null;
        }

        $tokens = $this->tokenize($sentence);
        $vector_sum = array_fill(0, $this->embedding_dim, 0);
        $count = 0;

        foreach ($tokens as $token) {
            $word_vec = $this->word_embedder->get_word_vector($token);
            if ($word_vec) {
                for ($i = 0; $i < $this->embedding_dim; $i++) {
                    $vector_sum[$i] += $word_vec[$i];
                }
                $count++;
            }
        }

        if ($count === 0) {
            return array_fill(0, $this->embedding_dim, 0);
        }

        // Normalize
        for ($i = 0; $i < $this->embedding_dim; $i++) {
            $vector_sum[$i] /= $count;
        }

        return $vector_sum;
    }

    /**
     * Weighted average using TF-IDF scores
     */
    public function embed_tfidf_weighted($sentence) {
        if (!$this->word_embedder || !$this->tfidf_vectorizer) {
            return null;
        }

        $tokens = $this->tokenize($sentence);
        $vector_sum = array_fill(0, $this->embedding_dim, 0);
        $weight_sum = 0;

        foreach ($tokens as $token) {
            $word_vec = $this->word_embedder->get_word_vector($token);
            $idf_score = $this->tfidf_vectorizer->get_idf($token);

            if ($word_vec && $idf_score > 0) {
                for ($i = 0; $i < $this->embedding_dim; $i++) {
                    $vector_sum[$i] += $word_vec[$i] * $idf_score;
                }
                $weight_sum += $idf_score;
            }
        }

        if ($weight_sum === 0) {
            return array_fill(0, $this->embedding_dim, 0);
        }

        // Normalize
        for ($i = 0; $i < $this->embedding_dim; $i++) {
            $vector_sum[$i] /= $weight_sum;
        }

        return $vector_sum;
    }

    /**
     * SIF (Smooth Inverse Frequency) embedding
     * Reweights word embeddings by word frequency
     */
    public function embed_sif($sentence, $word_frequencies, $alpha = 1e-3) {
        if (!$this->word_embedder) {
            return null;
        }

        $tokens = $this->tokenize($sentence);
        $vector_sum = array_fill(0, $this->embedding_dim, 0);

        foreach ($tokens as $token) {
            $word_vec = $this->word_embedder->get_word_vector($token);
            $freq = $word_frequencies[$token] ?? 1e-5;

            if ($word_vec) {
                // SIF weight: a / (a + p(w))
                $weight = $alpha / ($alpha + $freq);

                for ($i = 0; $i < $this->embedding_dim; $i++) {
                    $vector_sum[$i] += $word_vec[$i] * $weight;
                }
            }
        }

        return $vector_sum;
    }

    /**
     * Universal Sentence Encoder-style (simplified)
     * Uses max pooling over word embeddings
     */
    public function embed_max_pooling($sentence) {
        if (!$this->word_embedder) {
            return null;
        }

        $tokens = $this->tokenize($sentence);
        $max_vec = array_fill(0, $this->embedding_dim, -PHP_FLOAT_MAX);

        foreach ($tokens as $token) {
            $word_vec = $this->word_embedder->get_word_vector($token);

            if ($word_vec) {
                for ($i = 0; $i < $this->embedding_dim; $i++) {
                    $max_vec[$i] = max($max_vec[$i], $word_vec[$i]);
                }
            }
        }

        // Replace remaining -INF with 0
        for ($i = 0; $i < $this->embedding_dim; $i++) {
            if ($max_vec[$i] === -PHP_FLOAT_MAX) {
                $max_vec[$i] = 0;
            }
        }

        return $max_vec;
    }

    /**
     * Concatenate different pooling strategies
     */
    public function embed_multi_pooling($sentence) {
        $avg = $this->embed_average($sentence);
        $max = $this->embed_max_pooling($sentence);

        // Concatenate average and max pooling
        return array_merge($avg, $max);
    }

    /**
     * Embed using n-gram features
     */
    public function embed_ngrams($sentence, $n = 3) {
        $chars = str_split(strtolower($sentence));
        $ngrams = [];

        for ($i = 0; $i <= count($chars) - $n; $i++) {
            $ngram = implode('', array_slice($chars, $i, $n));
            $ngrams[] = $ngram;
        }

        // Hash n-grams to fixed dimension
        $vector = array_fill(0, $this->embedding_dim, 0);

        foreach ($ngrams as $ngram) {
            $hash = crc32($ngram) % $this->embedding_dim;
            $vector[abs($hash)]++;
        }

        // Normalize
        $sum = array_sum($vector);
        if ($sum > 0) {
            for ($i = 0; $i < $this->embedding_dim; $i++) {
                $vector[$i] /= $sum;
            }
        }

        return $vector;
    }

    /**
     * Compute semantic similarity between sentences
     */
    public function similarity($sentence1, $sentence2, $method = 'average') {
        $vec1 = null;
        $vec2 = null;

        switch ($method) {
            case 'average':
                $vec1 = $this->embed_average($sentence1);
                $vec2 = $this->embed_average($sentence2);
                break;

            case 'tfidf_weighted':
                $vec1 = $this->embed_tfidf_weighted($sentence1);
                $vec2 = $this->embed_tfidf_weighted($sentence2);
                break;

            case 'max_pooling':
                $vec1 = $this->embed_max_pooling($sentence1);
                $vec2 = $this->embed_max_pooling($sentence2);
                break;

            case 'ngrams':
                $vec1 = $this->embed_ngrams($sentence1);
                $vec2 = $this->embed_ngrams($sentence2);
                break;
        }

        if (!$vec1 || !$vec2) {
            return 0.0;
        }

        return $this->cosine_similarity($vec1, $vec2);
    }

    /**
     * Cosine similarity
     */
    private function cosine_similarity($vec1, $vec2) {
        $dot_product = 0;
        $norm1 = 0;
        $norm2 = 0;

        for ($i = 0; $i < min(count($vec1), count($vec2)); $i++) {
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
     * Batch embed multiple sentences
     */
    public function batch_embed($sentences, $method = 'average') {
        $embeddings = [];

        foreach ($sentences as $sentence) {
            switch ($method) {
                case 'average':
                    $embeddings[] = $this->embed_average($sentence);
                    break;

                case 'tfidf_weighted':
                    $embeddings[] = $this->embed_tfidf_weighted($sentence);
                    break;

                case 'max_pooling':
                    $embeddings[] = $this->embed_max_pooling($sentence);
                    break;

                case 'ngrams':
                    $embeddings[] = $this->embed_ngrams($sentence);
                    break;

                default:
                    $embeddings[] = $this->embed_average($sentence);
            }
        }

        return $embeddings;
    }

    /**
     * Find most similar sentences
     */
    public function find_most_similar($query, $candidates, $top_k = 5, $method = 'average') {
        $query_vec = null;

        switch ($method) {
            case 'average':
                $query_vec = $this->embed_average($query);
                break;
            case 'tfidf_weighted':
                $query_vec = $this->embed_tfidf_weighted($query);
                break;
            case 'max_pooling':
                $query_vec = $this->embed_max_pooling($query);
                break;
            case 'ngrams':
                $query_vec = $this->embed_ngrams($query);
                break;
        }

        if (!$query_vec) {
            return [];
        }

        $similarities = [];

        foreach ($candidates as $index => $candidate) {
            $candidate_vec = null;

            switch ($method) {
                case 'average':
                    $candidate_vec = $this->embed_average($candidate);
                    break;
                case 'tfidf_weighted':
                    $candidate_vec = $this->embed_tfidf_weighted($candidate);
                    break;
                case 'max_pooling':
                    $candidate_vec = $this->embed_max_pooling($candidate);
                    break;
                case 'ngrams':
                    $candidate_vec = $this->embed_ngrams($candidate);
                    break;
            }

            if ($candidate_vec) {
                $similarities[$index] = $this->cosine_similarity($query_vec, $candidate_vec);
            }
        }

        arsort($similarities);

        return array_slice($similarities, 0, $top_k, true);
    }

    /**
     * Set embedding dimension
     */
    public function set_embedding_dim($dim) {
        $this->embedding_dim = $dim;
    }
}
