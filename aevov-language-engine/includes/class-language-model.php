<?php
namespace AevovLanguageEngine\NLP;

/**
 * Real N-gram Language Model
 *
 * Features:
 * - Bigram and Trigram model construction
 * - Probability calculation using Maximum Likelihood Estimation
 * - Text generation based on learned patterns
 * - Perplexity scoring for model evaluation
 * - Smoothing techniques (Laplace/Add-one smoothing)
 */
class LanguageModel {

    private $unigrams = [];
    private $bigrams = [];
    private $trigrams = [];
    private $vocabulary = [];
    private $total_tokens = 0;
    private $smoothing = 1.0; // Laplace smoothing parameter

    const START_TOKEN = '<START>';
    const END_TOKEN = '<END>';

    /**
     * Train the language model on a corpus
     *
     * @param array $corpus Array of sentences (strings)
     * @return void
     */
    public function train($corpus) {
        $this->unigrams = [];
        $this->bigrams = [];
        $this->trigrams = [];
        $this->vocabulary = [];
        $this->total_tokens = 0;

        foreach ($corpus as $sentence) {
            $tokens = $this->tokenize($sentence);

            // Add start and end tokens
            $padded_tokens = array_merge([self::START_TOKEN], $tokens, [self::END_TOKEN]);

            // Count unigrams
            foreach ($tokens as $token) {
                if (!isset($this->unigrams[$token])) {
                    $this->unigrams[$token] = 0;
                    $this->vocabulary[] = $token;
                }
                $this->unigrams[$token]++;
                $this->total_tokens++;
            }

            // Count bigrams
            for ($i = 0; $i < count($padded_tokens) - 1; $i++) {
                $bigram = $padded_tokens[$i] . ' ' . $padded_tokens[$i + 1];
                if (!isset($this->bigrams[$bigram])) {
                    $this->bigrams[$bigram] = 0;
                }
                $this->bigrams[$bigram]++;
            }

            // Count trigrams
            for ($i = 0; $i < count($padded_tokens) - 2; $i++) {
                $trigram = $padded_tokens[$i] . ' ' . $padded_tokens[$i + 1] . ' ' . $padded_tokens[$i + 2];
                if (!isset($this->trigrams[$trigram])) {
                    $this->trigrams[$trigram] = 0;
                }
                $this->trigrams[$trigram]++;
            }
        }

        // Add special tokens to vocabulary
        $this->vocabulary[] = self::START_TOKEN;
        $this->vocabulary[] = self::END_TOKEN;
        $this->vocabulary = array_unique($this->vocabulary);
    }

    /**
     * Calculate probability of a word given previous word (bigram probability)
     *
     * @param string $word Current word
     * @param string $prev_word Previous word
     * @return float Probability
     */
    public function bigram_probability($word, $prev_word) {
        $bigram = $prev_word . ' ' . $word;
        $bigram_count = isset($this->bigrams[$bigram]) ? $this->bigrams[$bigram] : 0;
        $prev_count = isset($this->unigrams[$prev_word]) ? $this->unigrams[$prev_word] : 0;

        // Add special handling for START token
        if ($prev_word === self::START_TOKEN) {
            $prev_count = count($this->bigrams); // Number of sentences
        }

        // Laplace smoothing
        $vocab_size = count($this->vocabulary);
        return ($bigram_count + $this->smoothing) / ($prev_count + $this->smoothing * $vocab_size);
    }

    /**
     * Calculate probability of a word given two previous words (trigram probability)
     *
     * @param string $word Current word
     * @param string $prev_word1 Previous word
     * @param string $prev_word2 Word before previous word
     * @return float Probability
     */
    public function trigram_probability($word, $prev_word1, $prev_word2) {
        $trigram = $prev_word2 . ' ' . $prev_word1 . ' ' . $word;
        $bigram = $prev_word2 . ' ' . $prev_word1;

        $trigram_count = isset($this->trigrams[$trigram]) ? $this->trigrams[$trigram] : 0;
        $bigram_count = isset($this->bigrams[$bigram]) ? $this->bigrams[$bigram] : 0;

        // Laplace smoothing
        $vocab_size = count($this->vocabulary);
        return ($trigram_count + $this->smoothing) / ($bigram_count + $this->smoothing * $vocab_size);
    }

    /**
     * Calculate unigram probability
     *
     * @param string $word Word
     * @return float Probability
     */
    public function unigram_probability($word) {
        $count = isset($this->unigrams[$word]) ? $this->unigrams[$word] : 0;
        $vocab_size = count($this->vocabulary);

        // Laplace smoothing
        return ($count + $this->smoothing) / ($this->total_tokens + $this->smoothing * $vocab_size);
    }

    /**
     * Generate text using the language model
     *
     * @param int $max_length Maximum number of words to generate
     * @param string $start_word Optional starting word
     * @param bool $use_trigrams Use trigram model if true, bigram if false
     * @return string Generated text
     */
    public function generate($max_length = 20, $start_word = null, $use_trigrams = true) {
        $generated = [];

        if ($start_word) {
            $generated[] = $start_word;
        }

        if ($use_trigrams && count($generated) < 2) {
            // Need at least 2 words for trigram model, use bigram to start
            $word = $this->generate_next_word_bigram(
                count($generated) === 0 ? self::START_TOKEN : $generated[0]
            );
            if ($word !== self::END_TOKEN) {
                $generated[] = $word;
            }
        }

        while (count($generated) < $max_length) {
            if ($use_trigrams && count($generated) >= 2) {
                $word = $this->generate_next_word_trigram(
                    $generated[count($generated) - 1],
                    $generated[count($generated) - 2]
                );
            } else {
                $prev = count($generated) === 0 ? self::START_TOKEN : $generated[count($generated) - 1];
                $word = $this->generate_next_word_bigram($prev);
            }

            if ($word === self::END_TOKEN) {
                break;
            }

            $generated[] = $word;
        }

        return implode(' ', $generated);
    }

    /**
     * Generate next word using bigram model
     *
     * @param string $prev_word Previous word
     * @return string Next word
     */
    private function generate_next_word_bigram($prev_word) {
        $candidates = [];
        $probabilities = [];

        // Find all words that can follow prev_word
        foreach ($this->vocabulary as $word) {
            if ($word === self::START_TOKEN) {
                continue;
            }

            $prob = $this->bigram_probability($word, $prev_word);
            $candidates[] = $word;
            $probabilities[] = $prob;
        }

        if (empty($candidates)) {
            return self::END_TOKEN;
        }

        // Sample from distribution
        return $this->weighted_random_choice($candidates, $probabilities);
    }

    /**
     * Generate next word using trigram model
     *
     * @param string $prev_word1 Previous word
     * @param string $prev_word2 Word before previous
     * @return string Next word
     */
    private function generate_next_word_trigram($prev_word1, $prev_word2) {
        $candidates = [];
        $probabilities = [];

        // Find all words that can follow the bigram
        foreach ($this->vocabulary as $word) {
            if ($word === self::START_TOKEN) {
                continue;
            }

            $prob = $this->trigram_probability($word, $prev_word1, $prev_word2);
            $candidates[] = $word;
            $probabilities[] = $prob;
        }

        if (empty($candidates)) {
            return self::END_TOKEN;
        }

        // Sample from distribution
        return $this->weighted_random_choice($candidates, $probabilities);
    }

    /**
     * Calculate perplexity of a sentence
     * Lower perplexity = better fit to the model
     *
     * @param string $sentence Input sentence
     * @param bool $use_trigrams Use trigram model if true
     * @return float Perplexity score
     */
    public function perplexity($sentence, $use_trigrams = false) {
        $tokens = $this->tokenize($sentence);
        $padded_tokens = array_merge([self::START_TOKEN], $tokens, [self::END_TOKEN]);

        $log_prob = 0.0;
        $n = 0;

        if ($use_trigrams) {
            for ($i = 2; $i < count($padded_tokens); $i++) {
                $prob = $this->trigram_probability(
                    $padded_tokens[$i],
                    $padded_tokens[$i - 1],
                    $padded_tokens[$i - 2]
                );
                $log_prob += log($prob);
                $n++;
            }
        } else {
            for ($i = 1; $i < count($padded_tokens); $i++) {
                $prob = $this->bigram_probability(
                    $padded_tokens[$i],
                    $padded_tokens[$i - 1]
                );
                $log_prob += log($prob);
                $n++;
            }
        }

        // Perplexity = exp(-1/N * sum(log P(w_i)))
        return $n > 0 ? exp(-$log_prob / $n) : INF;
    }

    /**
     * Get the most likely next word(s) given context
     *
     * @param string $context Previous word(s)
     * @param int $top_k Number of top predictions to return
     * @return array Array of [word, probability] pairs
     */
    public function predict_next($context, $top_k = 5) {
        $tokens = $this->tokenize($context);
        $predictions = [];

        if (empty($tokens)) {
            $prev = self::START_TOKEN;
        } else {
            $prev = $tokens[count($tokens) - 1];
        }

        foreach ($this->vocabulary as $word) {
            if ($word === self::START_TOKEN) {
                continue;
            }

            $prob = $this->bigram_probability($word, $prev);
            $predictions[] = ['word' => $word, 'probability' => $prob];
        }

        // Sort by probability
        usort($predictions, function($a, $b) {
            return $b['probability'] <=> $a['probability'];
        });

        return array_slice($predictions, 0, $top_k);
    }

    /**
     * Get model statistics
     *
     * @return array Statistics about the trained model
     */
    public function get_stats() {
        return [
            'vocabulary_size' => count($this->vocabulary),
            'total_tokens' => $this->total_tokens,
            'unique_unigrams' => count($this->unigrams),
            'unique_bigrams' => count($this->bigrams),
            'unique_trigrams' => count($this->trigrams),
            'smoothing_parameter' => $this->smoothing
        ];
    }

    /**
     * Weighted random choice from array
     *
     * @param array $choices Array of choices
     * @param array $weights Array of weights (probabilities)
     * @return mixed Selected choice
     */
    private function weighted_random_choice($choices, $weights) {
        $total_weight = array_sum($weights);
        $random = mt_rand() / mt_getrandmax() * $total_weight;

        $cumulative = 0;
        for ($i = 0; $i < count($choices); $i++) {
            $cumulative += $weights[$i];
            if ($random <= $cumulative) {
                return $choices[$i];
            }
        }

        return $choices[count($choices) - 1];
    }

    /**
     * Simple tokenization
     *
     * @param string $text Input text
     * @return array Array of tokens
     */
    private function tokenize($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/', '', $text);
        return array_filter(explode(' ', $text));
    }

    /**
     * Save model to array (for persistence)
     *
     * @return array Model data
     */
    public function to_array() {
        return [
            'unigrams' => $this->unigrams,
            'bigrams' => $this->bigrams,
            'trigrams' => $this->trigrams,
            'vocabulary' => $this->vocabulary,
            'total_tokens' => $this->total_tokens,
            'smoothing' => $this->smoothing
        ];
    }

    /**
     * Load model from array
     *
     * @param array $data Model data
     * @return void
     */
    public function from_array($data) {
        $this->unigrams = $data['unigrams'] ?? [];
        $this->bigrams = $data['bigrams'] ?? [];
        $this->trigrams = $data['trigrams'] ?? [];
        $this->vocabulary = $data['vocabulary'] ?? [];
        $this->total_tokens = $data['total_tokens'] ?? 0;
        $this->smoothing = $data['smoothing'] ?? 1.0;
    }
}
