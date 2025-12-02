<?php
namespace AevovLanguageEngine\NLP;

/**
 * Real Semantic Analyzer
 *
 * Features:
 * - Sentiment analysis (positive/negative/neutral)
 * - Topic extraction using keyword frequency
 * - Keyword extraction
 * - Cosine similarity scoring
 */
class SemanticAnalyzer {

    // Sentiment lexicons (scored from -5 to +5)
    private $positive_words = [
        'excellent' => 5, 'amazing' => 5, 'wonderful' => 5, 'fantastic' => 5, 'outstanding' => 5,
        'great' => 4, 'good' => 3, 'nice' => 3, 'happy' => 4, 'love' => 4, 'best' => 4,
        'beautiful' => 4, 'perfect' => 5, 'awesome' => 4, 'brilliant' => 4, 'superb' => 5,
        'like' => 2, 'enjoy' => 3, 'pleasant' => 3, 'positive' => 3, 'success' => 4,
        'better' => 3, 'improved' => 3, 'effective' => 3, 'efficient' => 3, 'helpful' => 3,
        'interesting' => 2, 'useful' => 3, 'valuable' => 3, 'benefit' => 3, 'advantage' => 3
    ];

    private $negative_words = [
        'terrible' => -5, 'horrible' => -5, 'awful' => -5, 'worst' => -5, 'hate' => -5,
        'bad' => -3, 'poor' => -3, 'sad' => -4, 'angry' => -4, 'disappointed' => -4,
        'ugly' => -4, 'disgusting' => -5, 'pathetic' => -4, 'useless' => -4, 'failure' => -4,
        'dislike' => -2, 'problem' => -2, 'issue' => -2, 'error' => -3, 'wrong' => -3,
        'difficult' => -2, 'hard' => -2, 'negative' => -3, 'worse' => -3, 'lacking' => -3,
        'ineffective' => -3, 'inefficient' => -3, 'unhelpful' => -3, 'boring' => -2,
        'confusing' => -2, 'complicated' => -2, 'disadvantage' => -3
    ];

    // Intensifiers and negations
    private $intensifiers = [
        'very' => 1.5, 'really' => 1.5, 'extremely' => 2.0, 'absolutely' => 2.0,
        'completely' => 1.8, 'totally' => 1.8, 'highly' => 1.5, 'quite' => 1.3,
        'particularly' => 1.4, 'especially' => 1.4, 'incredibly' => 2.0
    ];

    private $negations = ['not', 'no', 'never', 'neither', 'nobody', 'nothing', 'nowhere', 'hardly', 'scarcely', 'barely', "n't"];

    /**
     * Analyze sentiment of text
     *
     * @param string $text Input text
     * @return array Sentiment analysis results
     */
    public function analyze_sentiment($text) {
        $words = $this->tokenize($text);
        $score = 0;
        $positive_count = 0;
        $negative_count = 0;
        $matched_words = [];

        for ($i = 0; $i < count($words); $i++) {
            $word = strtolower($words[$i]);
            $word_score = 0;
            $multiplier = 1.0;

            // Check for intensifier before this word
            if ($i > 0) {
                $prev_word = strtolower($words[$i - 1]);
                if (isset($this->intensifiers[$prev_word])) {
                    $multiplier = $this->intensifiers[$prev_word];
                }
            }

            // Check for negation (within 3 words before)
            $is_negated = false;
            for ($j = max(0, $i - 3); $j < $i; $j++) {
                if (in_array(strtolower($words[$j]), $this->negations)) {
                    $is_negated = true;
                    break;
                }
            }

            // Calculate sentiment
            if (isset($this->positive_words[$word])) {
                $word_score = $this->positive_words[$word] * $multiplier;
                $positive_count++;
            } else if (isset($this->negative_words[$word])) {
                $word_score = $this->negative_words[$word] * $multiplier;
                $negative_count++;
            }

            // Apply negation
            if ($is_negated && $word_score != 0) {
                $word_score = -$word_score * 0.8; // Negation reverses and slightly reduces intensity
            }

            if ($word_score != 0) {
                $matched_words[] = [
                    'word' => $word,
                    'score' => $word_score,
                    'negated' => $is_negated
                ];
            }

            $score += $word_score;
        }

        // Normalize score to -1 to +1 range
        $max_possible = count($words) * 5;
        $normalized_score = $max_possible > 0 ? $score / $max_possible : 0;

        // Determine sentiment category
        $sentiment = 'neutral';
        if ($normalized_score > 0.1) {
            $sentiment = 'positive';
        } else if ($normalized_score < -0.1) {
            $sentiment = 'negative';
        }

        // Calculate confidence based on number of sentiment words
        $confidence = min(1.0, (($positive_count + $negative_count) / max(1, count($words))) * 5);

        return [
            'sentiment' => $sentiment,
            'score' => $normalized_score,
            'raw_score' => $score,
            'confidence' => $confidence,
            'positive_words' => $positive_count,
            'negative_words' => $negative_count,
            'matched_words' => $matched_words,
            'word_count' => count($words)
        ];
    }

    /**
     * Extract topics from text using keyword frequency and co-occurrence
     *
     * @param string $text Input text
     * @param int $num_topics Number of topics to extract
     * @return array Topics with keywords
     */
    public function extract_topics($text, $num_topics = 3) {
        $words = $this->tokenize($text);
        $words = $this->filter_stop_words($words);

        // Calculate word frequencies
        $word_freq = [];
        foreach ($words as $word) {
            $word = strtolower($word);
            if (!isset($word_freq[$word])) {
                $word_freq[$word] = 0;
            }
            $word_freq[$word]++;
        }

        // Sort by frequency
        arsort($word_freq);

        // Calculate co-occurrence matrix for top words
        $top_words = array_slice(array_keys($word_freq), 0, min(20, count($word_freq)));
        $cooccurrence = [];

        for ($i = 0; $i < count($words) - 1; $i++) {
            $w1 = strtolower($words[$i]);
            $w2 = strtolower($words[$i + 1]);

            if (in_array($w1, $top_words) && in_array($w2, $top_words)) {
                $key = $w1 < $w2 ? $w1 . '|' . $w2 : $w2 . '|' . $w1;
                if (!isset($cooccurrence[$key])) {
                    $cooccurrence[$key] = 0;
                }
                $cooccurrence[$key]++;
            }
        }

        // Create topic clusters
        $topics = [];
        $used_words = [];
        $topic_id = 1;

        foreach ($top_words as $seed_word) {
            if (in_array($seed_word, $used_words)) {
                continue;
            }

            if (count($topics) >= $num_topics) {
                break;
            }

            // Find related words
            $related = [$seed_word];
            foreach ($cooccurrence as $pair => $count) {
                list($w1, $w2) = explode('|', $pair);
                if ($w1 === $seed_word && !in_array($w2, $related)) {
                    $related[] = $w2;
                } else if ($w2 === $seed_word && !in_array($w1, $related)) {
                    $related[] = $w1;
                }

                if (count($related) >= 5) {
                    break;
                }
            }

            $topics[] = [
                'topic_id' => $topic_id++,
                'keywords' => $related,
                'primary_keyword' => $seed_word,
                'frequency' => $word_freq[$seed_word]
            ];

            $used_words = array_merge($used_words, $related);
        }

        return $topics;
    }

    /**
     * Extract important keywords from text
     *
     * @param string $text Input text
     * @param int $top_k Number of keywords to extract
     * @return array Keywords with scores
     */
    public function extract_keywords($text, $top_k = 10) {
        $words = $this->tokenize($text);
        $words = $this->filter_stop_words($words);

        // Calculate word frequencies
        $word_freq = [];
        foreach ($words as $word) {
            $word = strtolower($word);
            if (!isset($word_freq[$word])) {
                $word_freq[$word] = 0;
            }
            $word_freq[$word]++;
        }

        // Boost capitalized words (likely proper nouns/important terms)
        $original_words = preg_split('/\s+/', $text);
        foreach ($original_words as $word) {
            if (ctype_upper($word[0]) && strlen($word) > 3) {
                $lower = strtolower($word);
                if (isset($word_freq[$lower])) {
                    $word_freq[$lower] *= 1.5;
                }
            }
        }

        // Calculate position-based importance (words earlier in text are more important)
        $position_weights = [];
        foreach ($words as $i => $word) {
            $word = strtolower($word);
            $position_weight = 1.0 + (0.5 * (1 - ($i / count($words))));
            if (!isset($position_weights[$word])) {
                $position_weights[$word] = 0;
            }
            $position_weights[$word] += $position_weight;
        }

        // Combine frequency and position
        $scores = [];
        foreach ($word_freq as $word => $freq) {
            $pos_weight = isset($position_weights[$word]) ? $position_weights[$word] : 1.0;
            $scores[$word] = $freq * ($pos_weight / count($words));
        }

        // Sort by score
        arsort($scores);

        $keywords = [];
        $rank = 1;
        foreach (array_slice($scores, 0, $top_k, true) as $word => $score) {
            $keywords[] = [
                'keyword' => $word,
                'score' => $score,
                'frequency' => $word_freq[$word],
                'rank' => $rank++
            ];
        }

        return $keywords;
    }

    /**
     * Calculate cosine similarity between two texts
     *
     * @param string $text1 First text
     * @param string $text2 Second text
     * @return float Similarity score (0 to 1)
     */
    public function cosine_similarity($text1, $text2) {
        $vec1 = $this->text_to_vector($text1);
        $vec2 = $this->text_to_vector($text2);

        // Calculate dot product
        $dot_product = 0;
        foreach ($vec1 as $word => $count1) {
            if (isset($vec2[$word])) {
                $dot_product += $count1 * $vec2[$word];
            }
        }

        // Calculate magnitudes
        $mag1 = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $vec1)));
        $mag2 = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $vec2)));

        if ($mag1 == 0 || $mag2 == 0) {
            return 0;
        }

        return $dot_product / ($mag1 * $mag2);
    }

    /**
     * Calculate Jaccard similarity between two texts
     *
     * @param string $text1 First text
     * @param string $text2 Second text
     * @return float Similarity score (0 to 1)
     */
    public function jaccard_similarity($text1, $text2) {
        $words1 = array_unique($this->tokenize($text1));
        $words2 = array_unique($this->tokenize($text2));

        $intersection = count(array_intersect($words1, $words2));
        $union = count(array_unique(array_merge($words1, $words2)));

        return $union > 0 ? $intersection / $union : 0;
    }

    /**
     * Find most similar sentences in a corpus to a query
     *
     * @param string $query Query text
     * @param array $corpus Array of sentences
     * @param int $top_k Number of results to return
     * @return array Most similar sentences with scores
     */
    public function find_similar($query, $corpus, $top_k = 5) {
        $results = [];

        foreach ($corpus as $i => $sentence) {
            $similarity = $this->cosine_similarity($query, $sentence);
            $results[] = [
                'index' => $i,
                'sentence' => $sentence,
                'similarity' => $similarity
            ];
        }

        // Sort by similarity
        usort($results, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return array_slice($results, 0, $top_k);
    }

    /**
     * Convert text to word frequency vector
     *
     * @param string $text Input text
     * @return array Word frequency vector
     */
    private function text_to_vector($text) {
        $words = $this->tokenize($text);
        $vector = [];

        foreach ($words as $word) {
            $word = strtolower($word);
            if (!isset($vector[$word])) {
                $vector[$word] = 0;
            }
            $vector[$word]++;
        }

        return $vector;
    }

    /**
     * Simple tokenization
     *
     * @param string $text Input text
     * @return array Array of tokens
     */
    private function tokenize($text) {
        $text = preg_replace('/[^\w\s\']/', ' ', $text);
        return array_filter(preg_split('/\s+/', $text));
    }

    /**
     * Filter stop words
     *
     * @param array $words Array of words
     * @return array Filtered words
     */
    private function filter_stop_words($words) {
        $stop_words = [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
            'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'be',
            'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will',
            'would', 'should', 'could', 'may', 'might', 'can', 'this', 'that',
            'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they'
        ];

        return array_filter($words, function($word) use ($stop_words) {
            return !in_array(strtolower($word), $stop_words) && strlen($word) > 2;
        });
    }

    /**
     * Analyze semantic richness of text
     *
     * @param string $text Input text
     * @return array Semantic analysis metrics
     */
    public function analyze_semantic_richness($text) {
        $words = $this->tokenize($text);
        $unique_words = array_unique(array_map('strtolower', $words));

        $lexical_diversity = count($words) > 0 ? count($unique_words) / count($words) : 0;

        // Calculate average word length (proxy for complexity)
        $total_length = array_sum(array_map('strlen', $words));
        $avg_word_length = count($words) > 0 ? $total_length / count($words) : 0;

        // Count multi-syllable words (approximation)
        $complex_words = 0;
        foreach ($words as $word) {
            if ($this->estimate_syllables($word) > 2) {
                $complex_words++;
            }
        }
        $complexity_ratio = count($words) > 0 ? $complex_words / count($words) : 0;

        return [
            'total_words' => count($words),
            'unique_words' => count($unique_words),
            'lexical_diversity' => $lexical_diversity,
            'avg_word_length' => $avg_word_length,
            'complex_words' => $complex_words,
            'complexity_ratio' => $complexity_ratio,
            'readability_estimate' => $this->estimate_readability($words)
        ];
    }

    /**
     * Estimate syllables in a word (approximation)
     *
     * @param string $word Word
     * @return int Estimated syllable count
     */
    private function estimate_syllables($word) {
        $word = strtolower($word);
        $syllables = 0;
        $vowels = ['a', 'e', 'i', 'o', 'u', 'y'];
        $prev_was_vowel = false;

        for ($i = 0; $i < strlen($word); $i++) {
            $is_vowel = in_array($word[$i], $vowels);
            if ($is_vowel && !$prev_was_vowel) {
                $syllables++;
            }
            $prev_was_vowel = $is_vowel;
        }

        // Adjust for silent 'e'
        if (substr($word, -1) === 'e') {
            $syllables--;
        }

        return max(1, $syllables);
    }

    /**
     * Estimate readability (simplified Flesch-Kincaid)
     *
     * @param array $words Array of words
     * @return string Readability level
     */
    private function estimate_readability($words) {
        $total_syllables = 0;
        foreach ($words as $word) {
            $total_syllables += $this->estimate_syllables($word);
        }

        $avg_syllables = count($words) > 0 ? $total_syllables / count($words) : 0;

        if ($avg_syllables < 1.3) {
            return 'Very Easy';
        } else if ($avg_syllables < 1.5) {
            return 'Easy';
        } else if ($avg_syllables < 1.7) {
            return 'Medium';
        } else if ($avg_syllables < 2.0) {
            return 'Difficult';
        } else {
            return 'Very Difficult';
        }
    }
}
