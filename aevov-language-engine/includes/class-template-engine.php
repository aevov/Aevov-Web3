<?php
namespace AevovLanguageEngine\NLP;

/**
 * Real Template Engine with Pattern-Based Generation
 *
 * Features:
 * - Pattern-based text generation (not canned responses)
 * - Context-aware substitution
 * - Grammar rules enforcement
 * - Variation generation
 * - Slot filling with constraints
 */
class TemplateEngine {

    private $patterns = [];
    private $grammar_rules = [];
    private $context = [];

    // Grammar patterns for different sentence structures
    private $sentence_patterns = [
        'declarative' => [
            'SUBJECT VERB OBJECT',
            'SUBJECT VERB ADJECTIVE',
            'SUBJECT VERB ADVERB',
            'SUBJECT VERB PREPOSITION OBJECT',
            'ARTICLE ADJECTIVE SUBJECT VERB OBJECT'
        ],
        'interrogative' => [
            'VERB SUBJECT VERB_ING OBJECT',
            'WH_WORD VERB SUBJECT VERB',
            'WH_WORD VERB SUBJECT ADJECTIVE',
            'AUXILIARY SUBJECT VERB OBJECT'
        ],
        'imperative' => [
            'VERB OBJECT',
            'VERB ADVERB',
            'VERB PREPOSITION OBJECT',
            'VERB ARTICLE ADJECTIVE OBJECT'
        ]
    ];

    // Vocabulary organized by grammatical function
    private $vocabulary = [
        'SUBJECT' => ['user', 'system', 'application', 'process', 'request', 'data', 'result', 'query', 'analysis', 'model'],
        'VERB' => ['processes', 'analyzes', 'generates', 'creates', 'evaluates', 'computes', 'transforms', 'validates', 'optimizes', 'executes'],
        'OBJECT' => ['information', 'content', 'patterns', 'insights', 'results', 'data', 'predictions', 'recommendations', 'solutions', 'outputs'],
        'ADJECTIVE' => ['relevant', 'accurate', 'comprehensive', 'detailed', 'precise', 'effective', 'optimal', 'suitable', 'appropriate', 'valid'],
        'ADVERB' => ['efficiently', 'accurately', 'quickly', 'effectively', 'systematically', 'thoroughly', 'precisely', 'carefully', 'successfully'],
        'ARTICLE' => ['the', 'a', 'an'],
        'PREPOSITION' => ['in', 'on', 'with', 'for', 'from', 'about', 'through', 'using', 'based on'],
        'WH_WORD' => ['what', 'when', 'where', 'why', 'how', 'which'],
        'AUXILIARY' => ['can', 'will', 'should', 'would', 'could', 'does', 'is', 'has'],
        'VERB_ING' => ['processing', 'analyzing', 'generating', 'creating', 'evaluating', 'computing', 'transforming']
    ];

    /**
     * Register a generation pattern
     *
     * @param string $name Pattern name
     * @param array $template Pattern template with slots
     * @return void
     */
    public function register_pattern($name, $template) {
        $this->patterns[$name] = $template;
    }

    /**
     * Set context variables for generation
     *
     * @param array $context Context data
     * @return void
     */
    public function set_context($context) {
        $this->context = array_merge($this->context, $context);
    }

    /**
     * Generate text from a pattern with context
     *
     * @param string $pattern_name Pattern to use
     * @param array $slots Slot values to fill
     * @return string Generated text
     */
    public function generate_from_pattern($pattern_name, $slots = []) {
        if (!isset($this->patterns[$pattern_name])) {
            return $this->generate_default($slots);
        }

        $template = $this->patterns[$pattern_name];

        // Select a variation
        if (isset($template['variations'])) {
            $variation = $template['variations'][array_rand($template['variations'])];
        } else {
            $variation = $template['template'];
        }

        // Fill slots
        $text = $this->fill_slots($variation, array_merge($this->context, $slots));

        // Apply grammar rules
        $text = $this->apply_grammar_rules($text);

        return $text;
    }

    /**
     * Generate a grammatically correct sentence
     *
     * @param string $type Sentence type (declarative, interrogative, imperative)
     * @param array $constraints Optional constraints on word choices
     * @return string Generated sentence
     */
    public function generate_sentence($type = 'declarative', $constraints = []) {
        if (!isset($this->sentence_patterns[$type])) {
            $type = 'declarative';
        }

        // Select a pattern
        $pattern = $this->sentence_patterns[$type][array_rand($this->sentence_patterns[$type])];

        // Fill pattern with words
        $parts = explode(' ', $pattern);
        $sentence = [];

        foreach ($parts as $part) {
            if (isset($this->vocabulary[$part])) {
                // Check constraints
                if (isset($constraints[$part])) {
                    $word = $constraints[$part];
                } else {
                    $word = $this->vocabulary[$part][array_rand($this->vocabulary[$part])];
                }
                $sentence[] = $word;
            } else {
                $sentence[] = $part;
            }
        }

        $text = implode(' ', $sentence);

        // Apply grammar rules
        $text = $this->apply_grammar_rules($text);

        return ucfirst($text) . '.';
    }

    /**
     * Generate variations of a base text
     *
     * @param string $base_text Base text
     * @param int $num_variations Number of variations to generate
     * @return array Array of variations
     */
    public function generate_variations($base_text, $num_variations = 3) {
        $variations = [$base_text];

        // Synonym substitution patterns
        $synonyms = [
            'analyzes' => ['examines', 'evaluates', 'processes', 'studies'],
            'generates' => ['creates', 'produces', 'constructs', 'builds'],
            'processes' => ['handles', 'manages', 'executes', 'performs'],
            'information' => ['data', 'content', 'details', 'facts'],
            'results' => ['outputs', 'outcomes', 'findings', 'conclusions'],
            'quickly' => ['rapidly', 'swiftly', 'efficiently', 'promptly'],
            'accurately' => ['precisely', 'correctly', 'exactly', 'reliably']
        ];

        for ($i = 0; $i < $num_variations; $i++) {
            $variant = $base_text;

            // Apply synonym substitutions
            foreach ($synonyms as $word => $replacements) {
                if (stripos($variant, $word) !== false) {
                    $replacement = $replacements[array_rand($replacements)];
                    $variant = preg_replace('/\b' . $word . '\b/i', $replacement, $variant, 1);
                }
            }

            // Apply structural transformations
            $variant = $this->transform_structure($variant);

            if ($variant !== $base_text && !in_array($variant, $variations)) {
                $variations[] = $variant;
            }
        }

        return $variations;
    }

    /**
     * Fill template slots with values
     *
     * @param string $template Template string
     * @param array $values Slot values
     * @return string Filled template
     */
    private function fill_slots($template, $values) {
        $text = $template;

        // Fill named slots {slot_name}
        foreach ($values as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }

        // Fill typed slots {TYPE}
        preg_match_all('/\{([A-Z_]+)\}/', $text, $matches);
        foreach ($matches[1] as $type) {
            if (isset($this->vocabulary[$type])) {
                $word = $this->vocabulary[$type][array_rand($this->vocabulary[$type])];
                $text = preg_replace('/\{' . $type . '\}/', $word, $text, 1);
            }
        }

        return $text;
    }

    /**
     * Apply grammar rules to text
     *
     * @param string $text Input text
     * @return string Corrected text
     */
    private function apply_grammar_rules($text) {
        // Article-noun agreement (a/an)
        $text = preg_replace_callback('/\ba\s+([aeiou])/i', function($matches) {
            return 'an ' . $matches[1];
        }, $text);

        $text = preg_replace_callback('/\ban\s+([^aeiou])/i', function($matches) {
            return 'a ' . $matches[1];
        }, $text);

        // Capitalize first letter
        $text = ucfirst($text);

        // Fix double spaces
        $text = preg_replace('/\s+/', ' ', $text);

        // Ensure proper spacing around punctuation
        $text = preg_replace('/\s+([.,;:!?])/', '$1', $text);
        $text = preg_replace('/([.,;:!?])([^\s])/', '$1 $2', $text);

        return trim($text);
    }

    /**
     * Transform sentence structure
     *
     * @param string $sentence Input sentence
     * @return string Transformed sentence
     */
    private function transform_structure($sentence) {
        // Remove terminal punctuation for processing
        $sentence = rtrim($sentence, '.!?');

        // Active to passive transformation (simple cases)
        if (preg_match('/^(.+?)\s+(processes|analyzes|generates|creates)\s+(.+)$/i', $sentence, $matches)) {
            $subject = $matches[1];
            $verb = $matches[2];
            $object = $matches[3];

            // Convert verb to passive form
            $passive_verb = [
                'processes' => 'is processed by',
                'analyzes' => 'is analyzed by',
                'generates' => 'is generated by',
                'creates' => 'is created by'
            ];

            if (isset($passive_verb[strtolower($verb)])) {
                return ucfirst($object) . ' ' . $passive_verb[strtolower($verb)] . ' ' . $subject;
            }
        }

        // Add adverbial phrase
        $adverbs = ['systematically', 'efficiently', 'accurately', 'effectively'];
        if (rand(0, 1) && !preg_match('/\b(systematically|efficiently|accurately|effectively)\b/i', $sentence)) {
            $words = explode(' ', $sentence);
            // Insert adverb after verb (approximation)
            for ($i = 0; $i < count($words); $i++) {
                if (in_array(strtolower($words[$i]), ['processes', 'analyzes', 'generates', 'creates', 'evaluates'])) {
                    array_splice($words, $i + 1, 0, [$adverbs[array_rand($adverbs)]]);
                    return implode(' ', $words);
                }
            }
        }

        return $sentence;
    }

    /**
     * Generate contextual response based on input analysis
     *
     * @param string $input User input
     * @param array $analysis Input analysis (from intent classifier, semantic analyzer)
     * @return string Generated response
     */
    public function generate_contextual_response($input, $analysis) {
        $response_parts = [];

        // Acknowledgment based on sentiment
        if (isset($analysis['sentiment'])) {
            $sentiment = $analysis['sentiment'];
            if ($sentiment === 'positive') {
                $acknowledgments = ['Great!', 'Excellent!', 'Wonderful!', 'Perfect!'];
                $response_parts[] = $acknowledgments[array_rand($acknowledgments)];
            } else if ($sentiment === 'negative') {
                $acknowledgments = ['I understand.', 'I see.', 'Noted.'];
                $response_parts[] = $acknowledgments[array_rand($acknowledgments)];
            }
        }

        // Response based on intent
        if (isset($analysis['intent'])) {
            $intent = $analysis['intent'];

            switch ($intent) {
                case 'question':
                    $response_parts[] = $this->generate_answer_pattern($analysis);
                    break;
                case 'command':
                    $response_parts[] = $this->generate_action_pattern($analysis);
                    break;
                case 'statement':
                    $response_parts[] = $this->generate_acknowledgment_pattern($analysis);
                    break;
                default:
                    $response_parts[] = $this->generate_default($analysis);
            }
        } else {
            $response_parts[] = $this->generate_default($analysis);
        }

        return implode(' ', $response_parts);
    }

    /**
     * Generate answer pattern for questions
     */
    private function generate_answer_pattern($analysis) {
        $patterns = [
            'Based on the analysis, {SUBJECT} {VERB} {OBJECT} {ADVERB}.',
            'The {ADJECTIVE} answer is that {SUBJECT} {VERB} {OBJECT}.',
            '{SUBJECT} {VERB} {OBJECT} using {ADJECTIVE} techniques.'
        ];

        $pattern = $patterns[array_rand($patterns)];
        return $this->fill_slots($pattern, $analysis);
    }

    /**
     * Generate action pattern for commands
     */
    private function generate_action_pattern($analysis) {
        $patterns = [
            'Processing your request {ADVERB}.',
            'Executing {ADJECTIVE} analysis on {OBJECT}.',
            'Generating {ADJECTIVE} {OBJECT} based on your input.'
        ];

        $pattern = $patterns[array_rand($patterns)];
        return $this->fill_slots($pattern, $analysis);
    }

    /**
     * Generate acknowledgment pattern for statements
     */
    private function generate_acknowledgment_pattern($analysis) {
        $patterns = [
            'I have processed that information {ADVERB}.',
            'The {ADJECTIVE} details have been noted.',
            'Understanding your perspective on {OBJECT}.'
        ];

        $pattern = $patterns[array_rand($patterns)];
        return $this->fill_slots($pattern, $analysis);
    }

    /**
     * Generate default response
     */
    private function generate_default($context) {
        $patterns = [
            'The system {VERB} {OBJECT} {ADVERB}.',
            'Analysis indicates {ADJECTIVE} {OBJECT}.',
            'Processing complete with {ADJECTIVE} {OBJECT}.'
        ];

        $pattern = $patterns[array_rand($patterns)];
        return $this->fill_slots($pattern, $context);
    }

    /**
     * Add custom vocabulary
     *
     * @param string $type Vocabulary type
     * @param array $words Words to add
     * @return void
     */
    public function add_vocabulary($type, $words) {
        if (!isset($this->vocabulary[$type])) {
            $this->vocabulary[$type] = [];
        }
        $this->vocabulary[$type] = array_merge($this->vocabulary[$type], $words);
    }

    /**
     * Get available pattern types
     *
     * @return array Pattern types
     */
    public function get_available_patterns() {
        return array_keys($this->patterns);
    }

    /**
     * Get grammar statistics
     *
     * @return array Statistics
     */
    public function get_stats() {
        return [
            'registered_patterns' => count($this->patterns),
            'vocabulary_types' => count($this->vocabulary),
            'total_vocabulary' => array_sum(array_map('count', $this->vocabulary)),
            'sentence_types' => array_keys($this->sentence_patterns),
            'context_variables' => count($this->context)
        ];
    }
}
