<?php
namespace AevovLanguageEngine\NLP;

/**
 * Real NLP Tokenizer with actual language processing capabilities
 *
 * Features:
 * - Word tokenization with punctuation and contraction handling
 * - Sentence boundary detection
 * - Named Entity Recognition (basic)
 * - Part-of-Speech tagging
 */
class Tokenizer {

    private $sentence_enders = ['.', '!', '?', '...'];
    private $abbreviations = ['mr', 'mrs', 'ms', 'dr', 'prof', 'sr', 'jr', 'st', 'ave', 'inc', 'ltd', 'etc', 'vs', 'e.g', 'i.e'];

    // Named Entity Recognition patterns
    private $ner_patterns = [
        'PERSON' => [
            '/\b(Mr|Mrs|Ms|Dr|Prof)\.\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\b/',
            '/\b([A-Z][a-z]+)\s+([A-Z][a-z]+)\b/' // Basic name pattern
        ],
        'ORGANIZATION' => [
            '/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s+(Inc|Corp|LLC|Ltd|Company|Corporation)\b/',
            '/\b(Google|Microsoft|Apple|Amazon|Facebook|Meta|Tesla|Netflix)\b/'
        ],
        'LOCATION' => [
            '/\b(New York|Los Angeles|Chicago|Houston|Phoenix|San Francisco|London|Paris|Tokyo|Berlin)\b/',
            '/\b([A-Z][a-z]+),\s+([A-Z]{2})\b/' // City, STATE pattern
        ],
        'DATE' => [
            '/\b(\d{1,2}\/\d{1,2}\/\d{2,4})\b/',
            '/\b(January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4}\b/i',
            '/\b(\d{4}-\d{2}-\d{2})\b/'
        ],
        'NUMBER' => [
            '/\b(\d+(?:,\d{3})*(?:\.\d+)?)\b/'
        ]
    ];

    // POS tagging lexicon (simplified but functional)
    private $pos_lexicon = [
        'NN' => ['time', 'person', 'year', 'way', 'day', 'thing', 'man', 'world', 'life', 'hand', 'part', 'child', 'eye', 'woman', 'place', 'work', 'week', 'case', 'point', 'government', 'company'],
        'VB' => ['be', 'have', 'do', 'say', 'get', 'make', 'go', 'know', 'take', 'see', 'come', 'think', 'look', 'want', 'give', 'use', 'find', 'tell', 'ask', 'work', 'seem', 'feel', 'try', 'leave', 'call'],
        'JJ' => ['good', 'new', 'first', 'last', 'long', 'great', 'little', 'own', 'other', 'old', 'right', 'big', 'high', 'different', 'small', 'large', 'next', 'early', 'young', 'important', 'few', 'public', 'bad', 'same', 'able'],
        'RB' => ['not', 'so', 'up', 'out', 'just', 'now', 'how', 'then', 'more', 'also', 'here', 'well', 'only', 'very', 'even', 'back', 'there', 'down', 'still', 'in', 'as', 'too', 'when', 'never', 'really'],
        'IN' => ['of', 'in', 'to', 'for', 'with', 'on', 'at', 'from', 'by', 'about', 'as', 'into', 'like', 'through', 'after', 'over', 'between', 'out', 'against', 'during', 'without', 'before', 'under', 'around', 'among'],
        'DT' => ['the', 'a', 'an', 'this', 'that', 'these', 'those', 'my', 'your', 'his', 'her', 'its', 'our', 'their', 'some', 'any', 'each', 'every', 'either', 'neither', 'much', 'many'],
        'PRP' => ['i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them'],
        'CC' => ['and', 'or', 'but', 'nor', 'so', 'yet', 'for'],
        'MD' => ['can', 'could', 'may', 'might', 'must', 'shall', 'should', 'will', 'would']
    ];

    // POS tag descriptions
    private $pos_tags = [
        'NN' => 'Noun',
        'VB' => 'Verb',
        'JJ' => 'Adjective',
        'RB' => 'Adverb',
        'IN' => 'Preposition',
        'DT' => 'Determiner',
        'PRP' => 'Pronoun',
        'CC' => 'Conjunction',
        'MD' => 'Modal',
        'CD' => 'Cardinal number',
        'NNP' => 'Proper noun',
        'NNS' => 'Plural noun',
        'VBD' => 'Past tense verb',
        'VBG' => 'Gerund/present participle',
        'VBN' => 'Past participle',
        'VBP' => 'Present tense verb',
        'VBZ' => '3rd person singular verb'
    ];

    /**
     * Tokenize text into words with proper handling of punctuation and contractions
     *
     * @param string $text Input text
     * @return array Array of word tokens
     */
    public function tokenize_words($text) {
        // Handle contractions specially
        $contractions = [
            "n't" => " not",
            "'re" => " are",
            "'ve" => " have",
            "'ll" => " will",
            "'d" => " would",
            "'m" => " am",
            "'s" => " is"
        ];

        $text = str_replace(array_keys($contractions), array_values($contractions), $text);

        // Tokenize: separate words from punctuation
        // Match word characters, numbers, or punctuation
        preg_match_all('/\b[\w]+\b|[^\s\w]/', $text, $matches);

        $tokens = [];
        foreach ($matches[0] as $token) {
            $token = trim($token);
            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    /**
     * Split text into sentences using proper boundary detection
     *
     * @param string $text Input text
     * @return array Array of sentences
     */
    public function tokenize_sentences($text) {
        $sentences = [];
        $current_sentence = '';

        // Split by potential sentence boundaries
        $parts = preg_split('/([.!?]+(?:\s+|$))/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 0; $i < count($parts); $i++) {
            $part = trim($parts[$i]);

            if (empty($part)) {
                continue;
            }

            $current_sentence .= $part;

            // Check if this is a sentence boundary
            if (preg_match('/[.!?]+/', $part)) {
                // Check for abbreviations
                $words = explode(' ', trim($current_sentence));
                $last_word = strtolower(str_replace('.', '', end($words)));

                // If it's not an abbreviation, it's a real sentence boundary
                if (!in_array($last_word, $this->abbreviations)) {
                    $sentences[] = trim($current_sentence);
                    $current_sentence = '';
                } else {
                    $current_sentence .= ' ';
                }
            } else {
                $current_sentence .= ' ';
            }
        }

        // Add any remaining text
        if (!empty(trim($current_sentence))) {
            $sentences[] = trim($current_sentence);
        }

        return $sentences;
    }

    /**
     * Perform Named Entity Recognition on text
     *
     * @param string $text Input text
     * @return array Array of entities with their types and positions
     */
    public function recognize_entities($text) {
        $entities = [];

        foreach ($this->ner_patterns as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $entities[] = [
                            'text' => $match[0],
                            'type' => $type,
                            'start' => $match[1],
                            'end' => $match[1] + strlen($match[0])
                        ];
                    }
                }
            }
        }

        // Sort by position
        usort($entities, function($a, $b) {
            return $a['start'] - $b['start'];
        });

        // Remove duplicates (keep first occurrence)
        $unique_entities = [];
        $used_positions = [];

        foreach ($entities as $entity) {
            $key = $entity['start'] . '-' . $entity['end'];
            if (!isset($used_positions[$key])) {
                $unique_entities[] = $entity;
                $used_positions[$key] = true;
            }
        }

        return $unique_entities;
    }

    /**
     * Tag words with their parts of speech
     *
     * @param array $tokens Array of word tokens
     * @return array Array of [word, tag] pairs
     */
    public function tag_pos($tokens) {
        $tagged = [];

        foreach ($tokens as $i => $token) {
            $lower_token = strtolower($token);
            $tag = 'NN'; // Default to noun

            // Check lexicon
            foreach ($this->pos_lexicon as $pos => $words) {
                if (in_array($lower_token, $words)) {
                    $tag = $pos;
                    break;
                }
            }

            // Rule-based refinements
            $tag = $this->refine_pos_tag($token, $lower_token, $tag, $tokens, $i);

            $tagged[] = [$token, $tag];
        }

        return $tagged;
    }

    /**
     * Refine POS tags using rules
     *
     * @param string $token Original token
     * @param string $lower_token Lowercase token
     * @param string $current_tag Current POS tag
     * @param array $tokens All tokens
     * @param int $index Current index
     * @return string Refined POS tag
     */
    private function refine_pos_tag($token, $lower_token, $current_tag, $tokens, $index) {
        // Numbers
        if (is_numeric($token)) {
            return 'CD';
        }

        // Capitalized words (not at start of sentence) are likely proper nouns
        if ($index > 0 && ctype_upper($token[0]) && $current_tag === 'NN') {
            return 'NNP';
        }

        // Plural nouns
        if ($current_tag === 'NN' && substr($lower_token, -1) === 's' && strlen($lower_token) > 2) {
            return 'NNS';
        }

        // Verb forms
        if ($current_tag === 'VB') {
            // Past tense (regular)
            if (substr($lower_token, -2) === 'ed') {
                return 'VBD';
            }
            // Gerund/present participle
            if (substr($lower_token, -3) === 'ing') {
                return 'VBG';
            }
            // 3rd person singular
            if (substr($lower_token, -1) === 's' && !in_array($lower_token, ['is', 'was', 'has'])) {
                return 'VBZ';
            }
            // Present tense
            if ($index > 0) {
                $prev = strtolower($tokens[$index - 1]);
                if (in_array($prev, ['i', 'you', 'we', 'they'])) {
                    return 'VBP';
                }
            }
        }

        // Adverbs often end in -ly
        if (substr($lower_token, -2) === 'ly') {
            return 'RB';
        }

        // Adjectives ending in common suffixes
        $adj_suffixes = ['able', 'ible', 'ful', 'less', 'ous', 'ive', 'ish'];
        foreach ($adj_suffixes as $suffix) {
            if (substr($lower_token, -strlen($suffix)) === $suffix) {
                return 'JJ';
            }
        }

        return $current_tag;
    }

    /**
     * Get full analysis of text
     *
     * @param string $text Input text
     * @return array Complete linguistic analysis
     */
    public function analyze($text) {
        $sentences = $this->tokenize_sentences($text);
        $words = $this->tokenize_words($text);
        $pos_tags = $this->tag_pos($words);
        $entities = $this->recognize_entities($text);

        return [
            'original' => $text,
            'sentences' => $sentences,
            'sentence_count' => count($sentences),
            'words' => $words,
            'word_count' => count($words),
            'pos_tags' => $pos_tags,
            'entities' => $entities,
            'entity_count' => count($entities),
            'stats' => [
                'avg_sentence_length' => count($sentences) > 0 ? count($words) / count($sentences) : 0,
                'unique_words' => count(array_unique(array_map('strtolower', $words))),
                'lexical_diversity' => count($words) > 0 ? count(array_unique(array_map('strtolower', $words))) / count($words) : 0
            ]
        ];
    }

    /**
     * Get POS tag description
     *
     * @param string $tag POS tag
     * @return string Description
     */
    public function get_tag_description($tag) {
        return isset($this->pos_tags[$tag]) ? $this->pos_tags[$tag] : 'Unknown';
    }
}
