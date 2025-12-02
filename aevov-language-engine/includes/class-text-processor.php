<?php
namespace AevovLanguageEngine\NLP;

/**
 * Real Text Processor with NLP algorithms
 *
 * Features:
 * - Porter Stemmer algorithm (complete implementation)
 * - Lemmatization with irregular verb handling
 * - Stop word filtering
 * - TF-IDF calculation
 */
class TextProcessor {

    // Stop words list (common English words to filter)
    private $stop_words = [
        'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and', 'any', 'are', 'aren\'t', 'as', 'at',
        'be', 'because', 'been', 'before', 'being', 'below', 'between', 'both', 'but', 'by',
        'can\'t', 'cannot', 'could', 'couldn\'t',
        'did', 'didn\'t', 'do', 'does', 'doesn\'t', 'doing', 'don\'t', 'down', 'during',
        'each',
        'few', 'for', 'from', 'further',
        'had', 'hadn\'t', 'has', 'hasn\'t', 'have', 'haven\'t', 'having', 'he', 'he\'d', 'he\'ll', 'he\'s', 'her', 'here', 'here\'s', 'hers', 'herself', 'him', 'himself', 'his', 'how', 'how\'s',
        'i', 'i\'d', 'i\'ll', 'i\'m', 'i\'ve', 'if', 'in', 'into', 'is', 'isn\'t', 'it', 'it\'s', 'its', 'itself',
        'let\'s',
        'me', 'more', 'most', 'mustn\'t', 'my', 'myself',
        'no', 'nor', 'not',
        'of', 'off', 'on', 'once', 'only', 'or', 'other', 'ought', 'our', 'ours', 'ourselves', 'out', 'over', 'own',
        'same', 'shan\'t', 'she', 'she\'d', 'she\'ll', 'she\'s', 'should', 'shouldn\'t', 'so', 'some', 'such',
        'than', 'that', 'that\'s', 'the', 'their', 'theirs', 'them', 'themselves', 'then', 'there', 'there\'s', 'these', 'they', 'they\'d', 'they\'ll', 'they\'re', 'they\'ve', 'this', 'those', 'through', 'to', 'too',
        'under', 'until', 'up',
        'very',
        'was', 'wasn\'t', 'we', 'we\'d', 'we\'ll', 'we\'re', 'we\'ve', 'were', 'weren\'t', 'what', 'what\'s', 'when', 'when\'s', 'where', 'where\'s', 'which', 'while', 'who', 'who\'s', 'whom', 'why', 'why\'s', 'with', 'won\'t', 'would', 'wouldn\'t',
        'you', 'you\'d', 'you\'ll', 'you\'re', 'you\'ve', 'your', 'yours', 'yourself', 'yourselves'
    ];

    // Lemmatization dictionary for irregular verbs and common exceptions
    private $lemma_dict = [
        // Irregular verbs
        'was' => 'be', 'were' => 'be', 'am' => 'be', 'are' => 'be', 'is' => 'be', 'been' => 'be', 'being' => 'be',
        'had' => 'have', 'has' => 'have', 'having' => 'have',
        'did' => 'do', 'does' => 'do', 'doing' => 'do', 'done' => 'do',
        'went' => 'go', 'gone' => 'go', 'going' => 'go', 'goes' => 'go',
        'saw' => 'see', 'seen' => 'see', 'seeing' => 'see', 'sees' => 'see',
        'came' => 'come', 'coming' => 'come', 'comes' => 'come',
        'took' => 'take', 'taken' => 'take', 'taking' => 'take', 'takes' => 'take',
        'made' => 'make', 'making' => 'make', 'makes' => 'make',
        'got' => 'get', 'getting' => 'get', 'gets' => 'get', 'gotten' => 'get',
        'gave' => 'give', 'given' => 'give', 'giving' => 'give', 'gives' => 'give',
        'found' => 'find', 'finding' => 'find', 'finds' => 'find',
        'told' => 'tell', 'telling' => 'tell', 'tells' => 'tell',
        'thought' => 'think', 'thinking' => 'think', 'thinks' => 'think',
        'knew' => 'know', 'known' => 'know', 'knowing' => 'know', 'knows' => 'know',
        'felt' => 'feel', 'feeling' => 'feel', 'feels' => 'feel',
        'left' => 'leave', 'leaving' => 'leave', 'leaves' => 'leave',
        'ran' => 'run', 'running' => 'run', 'runs' => 'run',
        'spoke' => 'speak', 'spoken' => 'speak', 'speaking' => 'speak', 'speaks' => 'speak',
        'wrote' => 'write', 'written' => 'write', 'writing' => 'write', 'writes' => 'write',
        // Plural nouns
        'children' => 'child', 'people' => 'person', 'men' => 'man', 'women' => 'woman',
        'feet' => 'foot', 'teeth' => 'tooth', 'mice' => 'mouse', 'geese' => 'goose'
    ];

    /**
     * Apply Porter Stemmer algorithm
     * Complete implementation of the Porter stemming algorithm
     *
     * @param string $word Input word
     * @return string Stemmed word
     */
    public function stem($word) {
        $word = strtolower($word);

        // Words with 2 or fewer letters don't get stemmed
        if (strlen($word) <= 2) {
            return $word;
        }

        // Step 1a
        $word = $this->step1a($word);

        // Step 1b
        $word = $this->step1b($word);

        // Step 1c
        $word = $this->step1c($word);

        // Step 2
        $word = $this->step2($word);

        // Step 3
        $word = $this->step3($word);

        // Step 4
        $word = $this->step4($word);

        // Step 5a
        $word = $this->step5a($word);

        // Step 5b
        $word = $this->step5b($word);

        return $word;
    }

    /**
     * Porter Stemmer Step 1a
     */
    private function step1a($word) {
        if (substr($word, -4) === 'sses') {
            return substr($word, 0, -2);
        }
        if (substr($word, -3) === 'ies') {
            return substr($word, 0, -2);
        }
        if (substr($word, -2) === 'ss') {
            return $word;
        }
        if (substr($word, -1) === 's') {
            return substr($word, 0, -1);
        }
        return $word;
    }

    /**
     * Porter Stemmer Step 1b
     */
    private function step1b($word) {
        if (substr($word, -3) === 'eed') {
            $stem = substr($word, 0, -3);
            if ($this->measure($stem) > 0) {
                return $stem . 'ee';
            }
        } else if (substr($word, -2) === 'ed') {
            $stem = substr($word, 0, -2);
            if ($this->contains_vowel($stem)) {
                return $this->step1b_continuation($stem);
            }
        } else if (substr($word, -3) === 'ing') {
            $stem = substr($word, 0, -3);
            if ($this->contains_vowel($stem)) {
                return $this->step1b_continuation($stem);
            }
        }
        return $word;
    }

    /**
     * Porter Stemmer Step 1b continuation
     */
    private function step1b_continuation($word) {
        if (substr($word, -2) === 'at' || substr($word, -2) === 'bl' || substr($word, -2) === 'iz') {
            return $word . 'e';
        } else if ($this->ends_double_consonant($word) &&
                   !in_array(substr($word, -1), ['l', 's', 'z'])) {
            return substr($word, 0, -1);
        } else if ($this->measure($word) === 1 && $this->ends_cvc($word)) {
            return $word . 'e';
        }
        return $word;
    }

    /**
     * Porter Stemmer Step 1c
     */
    private function step1c($word) {
        if (substr($word, -1) === 'y' && $this->contains_vowel(substr($word, 0, -1))) {
            return substr($word, 0, -1) . 'i';
        }
        return $word;
    }

    /**
     * Porter Stemmer Step 2
     */
    private function step2($word) {
        $suffixes = [
            'ational' => 'ate', 'tional' => 'tion', 'enci' => 'ence', 'anci' => 'ance',
            'izer' => 'ize', 'abli' => 'able', 'alli' => 'al', 'entli' => 'ent',
            'eli' => 'e', 'ousli' => 'ous', 'ization' => 'ize', 'ation' => 'ate',
            'ator' => 'ate', 'alism' => 'al', 'iveness' => 'ive', 'fulness' => 'ful',
            'ousness' => 'ous', 'aliti' => 'al', 'iviti' => 'ive', 'biliti' => 'ble'
        ];

        foreach ($suffixes as $suffix => $replacement) {
            if (substr($word, -strlen($suffix)) === $suffix) {
                $stem = substr($word, 0, -strlen($suffix));
                if ($this->measure($stem) > 0) {
                    return $stem . $replacement;
                }
            }
        }
        return $word;
    }

    /**
     * Porter Stemmer Step 3
     */
    private function step3($word) {
        $suffixes = [
            'icate' => 'ic', 'ative' => '', 'alize' => 'al',
            'iciti' => 'ic', 'ical' => 'ic', 'ful' => '', 'ness' => ''
        ];

        foreach ($suffixes as $suffix => $replacement) {
            if (substr($word, -strlen($suffix)) === $suffix) {
                $stem = substr($word, 0, -strlen($suffix));
                if ($this->measure($stem) > 0) {
                    return $stem . $replacement;
                }
            }
        }
        return $word;
    }

    /**
     * Porter Stemmer Step 4
     */
    private function step4($word) {
        $suffixes = [
            'al', 'ance', 'ence', 'er', 'ic', 'able', 'ible', 'ant',
            'ement', 'ment', 'ent', 'ion', 'ou', 'ism', 'ate', 'iti', 'ous', 'ive', 'ize'
        ];

        foreach ($suffixes as $suffix) {
            if (substr($word, -strlen($suffix)) === $suffix) {
                $stem = substr($word, 0, -strlen($suffix));
                // Special case for 'ion'
                if ($suffix === 'ion' && (substr($stem, -1) === 's' || substr($stem, -1) === 't')) {
                    if ($this->measure($stem) > 1) {
                        return $stem;
                    }
                } else if ($this->measure($stem) > 1) {
                    return $stem;
                }
            }
        }
        return $word;
    }

    /**
     * Porter Stemmer Step 5a
     */
    private function step5a($word) {
        if (substr($word, -1) === 'e') {
            $stem = substr($word, 0, -1);
            $measure = $this->measure($stem);
            if ($measure > 1 || ($measure === 1 && !$this->ends_cvc($stem))) {
                return $stem;
            }
        }
        return $word;
    }

    /**
     * Porter Stemmer Step 5b
     */
    private function step5b($word) {
        if ($this->measure($word) > 1 && $this->ends_double_consonant($word) && substr($word, -1) === 'l') {
            return substr($word, 0, -1);
        }
        return $word;
    }

    /**
     * Calculate measure of word (number of VC sequences)
     */
    private function measure($word) {
        $measure = 0;
        $prev_was_vowel = false;

        for ($i = 0; $i < strlen($word); $i++) {
            $is_vowel = $this->is_vowel($word, $i);

            if (!$is_vowel && $prev_was_vowel) {
                $measure++;
            }

            $prev_was_vowel = $is_vowel;
        }

        return $measure;
    }

    /**
     * Check if position contains a vowel
     */
    private function is_vowel($word, $pos) {
        $char = $word[$pos];

        if (in_array($char, ['a', 'e', 'i', 'o', 'u'])) {
            return true;
        }

        if ($char === 'y' && $pos > 0 && !$this->is_vowel($word, $pos - 1)) {
            return true;
        }

        return false;
    }

    /**
     * Check if word contains a vowel
     */
    private function contains_vowel($word) {
        for ($i = 0; $i < strlen($word); $i++) {
            if ($this->is_vowel($word, $i)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if word ends with double consonant
     */
    private function ends_double_consonant($word) {
        if (strlen($word) < 2) {
            return false;
        }

        $last = substr($word, -1);
        $second_last = substr($word, -2, 1);

        return $last === $second_last && !$this->is_vowel($word, strlen($word) - 1);
    }

    /**
     * Check if word ends with CVC pattern (consonant-vowel-consonant)
     * where the last consonant is not w, x, or y
     */
    private function ends_cvc($word) {
        if (strlen($word) < 3) {
            return false;
        }

        $last = strlen($word) - 1;

        return !$this->is_vowel($word, $last) &&
               $this->is_vowel($word, $last - 1) &&
               !$this->is_vowel($word, $last - 2) &&
               !in_array($word[$last], ['w', 'x', 'y']);
    }

    /**
     * Lemmatize word (convert to base form)
     *
     * @param string $word Input word
     * @return string Lemmatized word
     */
    public function lemmatize($word) {
        $lower = strtolower($word);

        // Check dictionary first
        if (isset($this->lemma_dict[$lower])) {
            return $this->lemma_dict[$lower];
        }

        // Apply rule-based lemmatization
        // Remove common suffixes
        if (substr($lower, -3) === 'ing' && strlen($lower) > 5) {
            return substr($lower, 0, -3);
        }
        if (substr($lower, -2) === 'ed' && strlen($lower) > 4) {
            return substr($lower, 0, -2);
        }
        if (substr($lower, -1) === 's' && strlen($lower) > 3 && substr($lower, -2) !== 'ss') {
            return substr($lower, 0, -1);
        }

        return $lower;
    }

    /**
     * Remove stop words from array of words
     *
     * @param array $words Array of words
     * @return array Filtered array
     */
    public function remove_stop_words($words) {
        return array_filter($words, function($word) {
            return !in_array(strtolower($word), $this->stop_words);
        });
    }

    /**
     * Calculate TF (Term Frequency)
     *
     * @param array $words Array of words
     * @return array Associative array of word => frequency
     */
    public function calculate_tf($words) {
        $total = count($words);
        $tf = [];

        foreach ($words as $word) {
            $word = strtolower($word);
            if (!isset($tf[$word])) {
                $tf[$word] = 0;
            }
            $tf[$word]++;
        }

        // Normalize by total words
        foreach ($tf as $word => $count) {
            $tf[$word] = $count / $total;
        }

        return $tf;
    }

    /**
     * Calculate IDF (Inverse Document Frequency)
     *
     * @param array $documents Array of documents (each document is an array of words)
     * @return array Associative array of word => IDF score
     */
    public function calculate_idf($documents) {
        $total_docs = count($documents);
        $doc_count = [];

        // Count how many documents contain each word
        foreach ($documents as $doc) {
            $unique_words = array_unique(array_map('strtolower', $doc));
            foreach ($unique_words as $word) {
                if (!isset($doc_count[$word])) {
                    $doc_count[$word] = 0;
                }
                $doc_count[$word]++;
            }
        }

        // Calculate IDF
        $idf = [];
        foreach ($doc_count as $word => $count) {
            $idf[$word] = log($total_docs / $count);
        }

        return $idf;
    }

    /**
     * Calculate TF-IDF scores
     *
     * @param array $words Current document words
     * @param array $idf Pre-calculated IDF scores
     * @return array Associative array of word => TF-IDF score
     */
    public function calculate_tfidf($words, $idf) {
        $tf = $this->calculate_tf($words);
        $tfidf = [];

        foreach ($tf as $word => $tf_score) {
            $idf_score = isset($idf[$word]) ? $idf[$word] : 0;
            $tfidf[$word] = $tf_score * $idf_score;
        }

        // Sort by score
        arsort($tfidf);

        return $tfidf;
    }

    /**
     * Get top keywords from text using TF-IDF
     *
     * @param string $text Input text
     * @param array $corpus Array of all documents (for IDF calculation)
     * @param int $top_k Number of keywords to return
     * @return array Top keywords
     */
    public function extract_keywords($text, $corpus, $top_k = 10) {
        $words = preg_split('/\s+/', strtolower($text));
        $words = $this->remove_stop_words($words);

        $idf = $this->calculate_idf($corpus);
        $tfidf = $this->calculate_tfidf($words, $idf);

        return array_slice($tfidf, 0, $top_k, true);
    }
}
