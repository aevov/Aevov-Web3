<?php
namespace AevovLanguageEngine\NLP;

/**
 * Real Intent Classifier using Naive Bayes
 *
 * Features:
 * - Naive Bayes classification algorithm
 * - Intent detection (question, command, statement, greeting, etc.)
 * - Entity extraction with slot filling
 * - Confidence scoring
 * - Trainable on custom data
 */
class IntentClassifier {

    private $word_probabilities = [];
    private $intent_priors = [];
    private $vocabulary = [];
    private $trained = false;

    // Entity extraction patterns
    private $entity_patterns = [
        'email' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
        'url' => '/\b(?:https?:\/\/)?(?:www\.)?[a-z0-9]+\.[a-z]{2,}(?:\/[^\s]*)?\b/i',
        'phone' => '/\b(?:\+\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}\b/',
        'number' => '/\b\d+(?:,\d{3})*(?:\.\d+)?\b/',
        'date' => '/\b(?:\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}|(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2},?\s+\d{4})\b/i',
        'time' => '/\b\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AaPp][Mm])?\b/',
        'currency' => '/\$\d+(?:,\d{3})*(?:\.\d{2})?/',
        'percentage' => '/\b\d+(?:\.\d+)?%\b/'
    ];

    // Pre-defined training data for common intents
    private $default_training = [
        'question' => [
            'what is your name',
            'how does this work',
            'why is this happening',
            'when will it be ready',
            'where can i find',
            'who is responsible',
            'which option is best',
            'can you help me',
            'do you know',
            'is it possible',
            'what are the benefits',
            'how do i do this',
            'tell me about',
            'explain this',
            'what does this mean'
        ],
        'command' => [
            'show me the results',
            'generate a report',
            'create a new document',
            'delete this file',
            'update the information',
            'process this request',
            'analyze the data',
            'calculate the total',
            'send me an email',
            'save this file',
            'open the application',
            'close the window',
            'start the process',
            'stop the execution',
            'run the analysis'
        ],
        'statement' => [
            'i think this is correct',
            'the system is working well',
            'this feature is useful',
            'i completed the task',
            'the data looks accurate',
            'everything seems fine',
            'i understand now',
            'that makes sense',
            'i agree with this',
            'this is helpful',
            'i like this approach',
            'the results are good',
            'this is what i needed'
        ],
        'greeting' => [
            'hello',
            'hi there',
            'good morning',
            'good afternoon',
            'good evening',
            'hey',
            'greetings',
            'howdy',
            'hi',
            'hello there'
        ],
        'farewell' => [
            'goodbye',
            'bye',
            'see you later',
            'see you',
            'take care',
            'farewell',
            'good night',
            'bye bye',
            'talk to you later',
            'catch you later'
        ]
    ];

    /**
     * Train the classifier on labeled data
     *
     * @param array $training_data Array of [text, intent] pairs
     * @return void
     */
    public function train($training_data = null) {
        if ($training_data === null) {
            $training_data = [];
            foreach ($this->default_training as $intent => $examples) {
                foreach ($examples as $example) {
                    $training_data[] = ['text' => $example, 'intent' => $intent];
                }
            }
        }

        $this->word_probabilities = [];
        $this->intent_priors = [];
        $this->vocabulary = [];

        // Count examples per intent
        $intent_counts = [];
        $word_counts = [];

        foreach ($training_data as $example) {
            $text = $example['text'];
            $intent = $example['intent'];

            if (!isset($intent_counts[$intent])) {
                $intent_counts[$intent] = 0;
                $word_counts[$intent] = [];
            }

            $intent_counts[$intent]++;

            // Tokenize and count words
            $words = $this->tokenize($text);
            foreach ($words as $word) {
                $word = strtolower($word);
                $this->vocabulary[] = $word;

                if (!isset($word_counts[$intent][$word])) {
                    $word_counts[$intent][$word] = 0;
                }
                $word_counts[$intent][$word]++;
            }
        }

        $this->vocabulary = array_unique($this->vocabulary);
        $total_examples = count($training_data);

        // Calculate prior probabilities P(intent)
        foreach ($intent_counts as $intent => $count) {
            $this->intent_priors[$intent] = $count / $total_examples;
        }

        // Calculate word probabilities P(word|intent) with Laplace smoothing
        $vocab_size = count($this->vocabulary);

        foreach ($word_counts as $intent => $words) {
            $total_words = array_sum($words);

            foreach ($this->vocabulary as $word) {
                $word_count = isset($words[$word]) ? $words[$word] : 0;
                // Laplace smoothing: (count + 1) / (total + vocab_size)
                $this->word_probabilities[$intent][$word] = ($word_count + 1) / ($total_words + $vocab_size);
            }
        }

        $this->trained = true;
    }

    /**
     * Classify text to determine intent
     *
     * @param string $text Input text
     * @return array Classification result with intent and confidence
     */
    public function classify($text) {
        if (!$this->trained) {
            $this->train();
        }

        $words = $this->tokenize($text);
        $scores = [];

        // Calculate score for each intent using Naive Bayes
        foreach ($this->intent_priors as $intent => $prior) {
            // Start with log prior probability
            $score = log($prior);

            // Add log probabilities for each word
            foreach ($words as $word) {
                $word = strtolower($word);
                if (isset($this->word_probabilities[$intent][$word])) {
                    $score += log($this->word_probabilities[$intent][$word]);
                } else {
                    // Unknown word - use smoothing
                    $vocab_size = count($this->vocabulary);
                    $score += log(1 / ($vocab_size + 1));
                }
            }

            $scores[$intent] = $score;
        }

        // Find maximum score (most likely intent)
        arsort($scores);
        $intents = array_keys($scores);
        $predicted_intent = $intents[0];

        // Convert log probabilities to actual probabilities for confidence
        // Normalize using softmax
        $max_score = max($scores);
        $exp_scores = [];
        foreach ($scores as $intent => $score) {
            $exp_scores[$intent] = exp($score - $max_score);
        }
        $sum_exp = array_sum($exp_scores);

        $probabilities = [];
        foreach ($exp_scores as $intent => $exp_score) {
            $probabilities[$intent] = $exp_score / $sum_exp;
        }

        $confidence = $probabilities[$predicted_intent];

        return [
            'intent' => $predicted_intent,
            'confidence' => $confidence,
            'all_intents' => $probabilities,
            'features' => $this->extract_features($text)
        ];
    }

    /**
     * Extract entities from text
     *
     * @param string $text Input text
     * @return array Extracted entities
     */
    public function extract_entities($text) {
        $entities = [];

        foreach ($this->entity_patterns as $type => $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $entities[] = [
                        'type' => $type,
                        'value' => $match[0],
                        'start' => $match[1],
                        'end' => $match[1] + strlen($match[0])
                    ];
                }
            }
        }

        // Sort by position
        usort($entities, function($a, $b) {
            return $a['start'] - $b['start'];
        });

        return $entities;
    }

    /**
     * Extract features from text for classification
     *
     * @param string $text Input text
     * @return array Features
     */
    private function extract_features($text) {
        $features = [];

        // Question words
        $question_words = ['what', 'when', 'where', 'why', 'how', 'who', 'which', 'whose', 'whom'];
        $has_question_word = false;
        foreach ($question_words as $qw) {
            if (stripos($text, $qw) !== false) {
                $has_question_word = true;
                $features['question_word'] = $qw;
                break;
            }
        }

        // Ends with question mark
        $features['has_question_mark'] = substr(trim($text), -1) === '?';

        // Starts with imperative verb
        $imperative_verbs = ['show', 'tell', 'give', 'create', 'delete', 'update', 'generate', 'process', 'analyze', 'calculate', 'send', 'open', 'close', 'start', 'stop'];
        $first_word = strtolower(trim(explode(' ', trim($text))[0]));
        $features['starts_with_imperative'] = in_array($first_word, $imperative_verbs);

        // Modal verbs (can, will, should, etc.)
        $modal_verbs = ['can', 'could', 'will', 'would', 'should', 'may', 'might', 'must'];
        $features['has_modal'] = false;
        foreach ($modal_verbs as $modal) {
            if (preg_match('/\b' . $modal . '\b/i', $text)) {
                $features['has_modal'] = true;
                break;
            }
        }

        // Length features
        $words = $this->tokenize($text);
        $features['word_count'] = count($words);
        $features['is_short'] = count($words) <= 3;

        // Greeting/farewell words
        $greetings = ['hello', 'hi', 'hey', 'greetings', 'good morning', 'good afternoon', 'good evening'];
        $farewells = ['goodbye', 'bye', 'see you', 'farewell', 'good night'];

        $lower_text = strtolower($text);
        foreach ($greetings as $greeting) {
            if (strpos($lower_text, $greeting) !== false) {
                $features['is_greeting'] = true;
                break;
            }
        }

        foreach ($farewells as $farewell) {
            if (strpos($lower_text, $farewell) !== false) {
                $features['is_farewell'] = true;
                break;
            }
        }

        return $features;
    }

    /**
     * Perform slot filling for a given intent
     *
     * @param string $text Input text
     * @param array $slots Slot definitions
     * @return array Filled slots
     */
    public function fill_slots($text, $slots) {
        $filled = [];

        foreach ($slots as $slot_name => $slot_config) {
            $type = $slot_config['type'];
            $required = isset($slot_config['required']) ? $slot_config['required'] : false;

            $value = null;

            // Try to extract based on type
            if ($type === 'entity') {
                $entities = $this->extract_entities($text);
                foreach ($entities as $entity) {
                    if ($entity['type'] === $slot_config['entity_type']) {
                        $value = $entity['value'];
                        break;
                    }
                }
            } else if ($type === 'keyword') {
                // Extract specific keyword
                if (isset($slot_config['pattern'])) {
                    if (preg_match($slot_config['pattern'], $text, $matches)) {
                        $value = $matches[1] ?? $matches[0];
                    }
                }
            } else if ($type === 'any') {
                // Extract any text after slot name
                if (isset($slot_config['trigger'])) {
                    $trigger = $slot_config['trigger'];
                    if (preg_match('/' . preg_quote($trigger, '/') . '\s+(.+?)(?:\s+(?:and|or|but|,)|$)/i', $text, $matches)) {
                        $value = $matches[1];
                    }
                }
            }

            $filled[$slot_name] = [
                'value' => $value,
                'required' => $required,
                'filled' => $value !== null
            ];
        }

        return $filled;
    }

    /**
     * Complete analysis combining intent classification and entity extraction
     *
     * @param string $text Input text
     * @return array Complete analysis
     */
    public function analyze($text) {
        $classification = $this->classify($text);
        $entities = $this->extract_entities($text);

        return [
            'text' => $text,
            'intent' => $classification['intent'],
            'confidence' => $classification['confidence'],
            'all_intents' => $classification['all_intents'],
            'entities' => $entities,
            'features' => $classification['features']
        ];
    }

    /**
     * Add training examples for a specific intent
     *
     * @param string $intent Intent name
     * @param array $examples Array of example texts
     * @return void
     */
    public function add_training_examples($intent, $examples) {
        if (!isset($this->default_training[$intent])) {
            $this->default_training[$intent] = [];
        }
        $this->default_training[$intent] = array_merge($this->default_training[$intent], $examples);
        $this->trained = false; // Mark for retraining
    }

    /**
     * Get classification confidence threshold recommendation
     *
     * @param float $confidence Confidence score
     * @return string Recommendation
     */
    public function get_confidence_level($confidence) {
        if ($confidence >= 0.9) {
            return 'very_high';
        } else if ($confidence >= 0.7) {
            return 'high';
        } else if ($confidence >= 0.5) {
            return 'medium';
        } else if ($confidence >= 0.3) {
            return 'low';
        } else {
            return 'very_low';
        }
    }

    /**
     * Simple tokenization
     *
     * @param string $text Input text
     * @return array Array of tokens
     */
    private function tokenize($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        return array_filter(explode(' ', $text));
    }

    /**
     * Get model statistics
     *
     * @return array Statistics
     */
    public function get_stats() {
        return [
            'trained' => $this->trained,
            'vocabulary_size' => count($this->vocabulary),
            'intents' => array_keys($this->intent_priors),
            'intent_count' => count($this->intent_priors),
            'entity_types' => array_keys($this->entity_patterns),
            'training_examples' => array_sum(array_map('count', $this->default_training))
        ];
    }

    /**
     * Save model to array
     *
     * @return array Model data
     */
    public function to_array() {
        return [
            'word_probabilities' => $this->word_probabilities,
            'intent_priors' => $this->intent_priors,
            'vocabulary' => $this->vocabulary,
            'trained' => $this->trained,
            'default_training' => $this->default_training
        ];
    }

    /**
     * Load model from array
     *
     * @param array $data Model data
     * @return void
     */
    public function from_array($data) {
        $this->word_probabilities = $data['word_probabilities'] ?? [];
        $this->intent_priors = $data['intent_priors'] ?? [];
        $this->vocabulary = $data['vocabulary'] ?? [];
        $this->trained = $data['trained'] ?? false;
        $this->default_training = $data['default_training'] ?? $this->default_training;
    }
}
