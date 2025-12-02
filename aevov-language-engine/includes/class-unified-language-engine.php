<?php
/**
 * Unified Language Engine - Native-First Architecture
 *
 * This is the PRIMARY language engine that orchestrates all native NLP components.
 * External APIs (OpenAI, etc.) are OPTIONAL enhancements, not replacements.
 *
 * Native Components Used:
 * - N-gram Language Model (bigram/trigram)
 * - Tokenizer
 * - Semantic Analyzer
 * - Intent Classifier
 * - Text Processor
 * - Template Engine
 *
 * Architecture:
 * 1. Native processing ALWAYS runs first
 * 2. Confidence scoring determines if enhancement needed
 * 3. External APIs used only when native confidence < threshold
 * 4. Results are always combined (hybrid approach)
 *
 * @package AevovLanguageEngine
 * @since 1.0.0
 */

namespace AevovLanguageEngine\Core;

use AevovLanguageEngine\NLP\Tokenizer;
use AevovLanguageEngine\NLP\LanguageModel;
use AevovLanguageEngine\NLP\TextProcessor;
use AevovLanguageEngine\NLP\SemanticAnalyzer;
use AevovLanguageEngine\NLP\IntentClassifier;
use AevovLanguageEngine\NLP\TemplateEngine;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UnifiedLanguageEngine
 */
class UnifiedLanguageEngine {

    /**
     * Native NLP components
     */
    private $tokenizer;
    private $language_model;
    private $text_processor;
    private $semantic_analyzer;
    private $intent_classifier;
    private $template_engine;

    /**
     * Optional external adapters
     */
    private $openai_adapter = null;

    /**
     * Configuration
     */
    private $config = [
        'native_first' => true,
        'confidence_threshold' => 0.7,  // Use external API if native confidence < 0.7
        'hybrid_mode' => true,           // Combine native + external results
        'external_fallback_enabled' => true,
        'use_external_for_training' => false,
    ];

    /**
     * Statistics tracking
     */
    private $stats = [
        'native_requests' => 0,
        'native_successes' => 0,
        'external_requests' => 0,
        'hybrid_requests' => 0,
    ];

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct($config = []) {
        $this->config = array_merge($this->config, $config);

        // Initialize native components
        $this->tokenizer = new Tokenizer();
        $this->language_model = new LanguageModel();
        $this->text_processor = new TextProcessor();
        $this->semantic_analyzer = new SemanticAnalyzer();
        $this->intent_classifier = new IntentClassifier();
        $this->template_engine = new TemplateEngine();

        // Load trained model if available
        $this->load_trained_model();

        // Initialize external adapter if configured
        if ($this->config['external_fallback_enabled']) {
            $this->init_external_adapter();
        }

        // Load statistics
        $this->stats = get_option('aevov_language_engine_stats', $this->stats);

        error_log('[Unified Language Engine] Initialized with native-first architecture');
    }

    /**
     * Generate text - PRIMARY PUBLIC METHOD
     *
     * This is the main entry point for text generation
     * Uses native-first approach with optional external enhancement
     *
     * @param string $prompt Input prompt
     * @param array $options Generation options
     * @return array|\WP_Error Result with text and metadata
     */
    public function generate($prompt, $options = []) {
        error_log('[Unified Language Engine] Generation request: ' . substr($prompt, 0, 100));

        // Phase 1: Native processing (ALWAYS runs)
        $native_result = $this->native_generate($prompt, $options);

        $this->stats['native_requests']++;

        // Check if native result is sufficient
        if (!is_wp_error($native_result) && $native_result['confidence'] >= $this->config['confidence_threshold']) {
            $this->stats['native_successes']++;
            $this->save_stats();

            error_log('[Unified Language Engine] Native generation sufficient (confidence: ' . $native_result['confidence'] . ')');

            return $native_result;
        }

        // Phase 2: External enhancement (if enabled and needed)
        if ($this->config['external_fallback_enabled'] && $this->openai_adapter) {
            error_log('[Unified Language Engine] Native confidence low, using external enhancement');

            $this->stats['external_requests']++;

            if ($this->config['hybrid_mode']) {
                // Hybrid: Combine native + external
                $hybrid_result = $this->hybrid_generate($prompt, $native_result, $options);
                $this->stats['hybrid_requests']++;
                $this->save_stats();

                return $hybrid_result;
            } else {
                // Fallback: Use external only
                $external_result = $this->external_generate($prompt, $options);
                $this->save_stats();

                return $external_result;
            }
        }

        // Phase 3: Return native result even if confidence is low (no external available)
        $this->stats['native_successes']++;
        $this->save_stats();

        error_log('[Unified Language Engine] Returning native result (no external available)');

        return $native_result;
    }

    /**
     * Native text generation using pure NLP algorithms
     *
     * This is the CORE of Aevov's language capabilities
     *
     * @param string $prompt Input prompt
     * @param array $options Generation options
     * @return array Result with confidence score
     */
    private function native_generate($prompt, $options = []) {
        $start_time = microtime(true);

        // Step 1: Analyze input
        $analysis = $this->analyze_input($prompt);

        // Step 2: Determine generation strategy based on intent
        $intent = $analysis['intent']['primary'];
        $confidence = $analysis['intent']['confidence'];

        // Step 3: Generate using appropriate method
        switch ($intent) {
            case 'question':
                $generated_text = $this->generate_answer($prompt, $analysis);
                break;

            case 'completion':
                $generated_text = $this->generate_completion($prompt, $analysis);
                break;

            case 'creative':
                $generated_text = $this->generate_creative($prompt, $analysis);
                break;

            case 'summarization':
                $generated_text = $this->generate_summary($prompt, $analysis);
                break;

            default:
                $generated_text = $this->generate_generic($prompt, $analysis);
        }

        // Step 4: Post-process and validate
        $processed_text = $this->text_processor->clean($generated_text);

        // Step 5: Calculate confidence score
        $generation_confidence = $this->calculate_confidence($processed_text, $prompt, $analysis);

        $duration = microtime(true) - $start_time;

        return [
            'text' => $processed_text,
            'confidence' => $generation_confidence,
            'method' => 'native',
            'intent' => $intent,
            'analysis' => $analysis,
            'duration' => $duration,
            'model' => 'native-nlp-v1',
        ];
    }

    /**
     * Generate completion using N-gram language model
     *
     * @param string $prompt Input text
     * @param array $analysis Linguistic analysis
     * @return string Generated completion
     */
    private function generate_completion($prompt, $analysis) {
        $max_tokens = 50;
        $use_trigrams = true;

        // Extract the last few words as context
        $tokens = $this->tokenizer->tokenize($prompt);
        $start_word = !empty($tokens) ? end($tokens) : null;

        // Generate continuation
        $completion = $this->language_model->generate($max_tokens, $start_word, $use_trigrams);

        return $completion;
    }

    /**
     * Generate answer to question
     *
     * @param string $prompt Question
     * @param array $analysis Linguistic analysis
     * @return string Answer
     */
    private function generate_answer($prompt, $analysis) {
        // Extract key entities and keywords
        $keywords = $analysis['keywords'];

        // Check if we have template answers
        $template_answer = $this->template_engine->generate_response($prompt, $keywords);

        if ($template_answer) {
            return $template_answer;
        }

        // Fall back to language model generation
        return $this->generate_completion($prompt, $analysis);
    }

    /**
     * Generate creative text
     *
     * @param string $prompt Creative prompt
     * @param array $analysis Linguistic analysis
     * @return string Creative text
     */
    private function generate_creative($prompt, $analysis) {
        // Use language model with higher creativity (more randomness)
        $max_tokens = 100;
        $generated = $this->language_model->generate($max_tokens, null, true);

        // Optionally incorporate prompt themes
        $keywords = $analysis['keywords'];
        if (!empty($keywords)) {
            // Regenerate with keyword as seed
            $seed_word = $keywords[0]['word'];
            $generated = $this->language_model->generate($max_tokens, $seed_word, true);
        }

        return $generated;
    }

    /**
     * Generate summary
     *
     * @param string $prompt Text to summarize
     * @param array $analysis Linguistic analysis
     * @return string Summary
     */
    private function generate_summary($prompt, $analysis) {
        // Extract most important sentences using semantic analysis
        $keywords = $analysis['keywords'];
        $sentences = $this->text_processor->split_sentences($prompt);

        // Score sentences by keyword density
        $scored_sentences = [];
        foreach ($sentences as $sentence) {
            $score = 0;
            foreach ($keywords as $kw) {
                if (stripos($sentence, $kw['word']) !== false) {
                    $score += $kw['score'];
                }
            }
            $scored_sentences[] = ['sentence' => $sentence, 'score' => $score];
        }

        // Sort by score and take top 3
        usort($scored_sentences, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $top_sentences = array_slice($scored_sentences, 0, 3);

        return implode(' ', array_column($top_sentences, 'sentence'));
    }

    /**
     * Generate generic response
     *
     * @param string $prompt Input
     * @param array $analysis Linguistic analysis
     * @return string Response
     */
    private function generate_generic($prompt, $analysis) {
        return $this->generate_completion($prompt, $analysis);
    }

    /**
     * Hybrid generation - combine native + external results
     *
     * @param string $prompt Input prompt
     * @param array $native_result Native generation result
     * @param array $options Generation options
     * @return array Combined result
     */
    private function hybrid_generate($prompt, $native_result, $options = []) {
        $start_time = microtime(true);

        // Get external result
        $external_result = $this->external_generate($prompt, $options);

        if (is_wp_error($external_result)) {
            // External failed, return native
            return $native_result;
        }

        // Combine results intelligently
        $combined_text = $this->combine_results($native_result['text'], $external_result['text'], $native_result['analysis']);

        $duration = microtime(true) - $start_time;

        return [
            'text' => $combined_text,
            'confidence' => max($native_result['confidence'], $external_result['confidence']),
            'method' => 'hybrid',
            'native_result' => $native_result,
            'external_result' => $external_result,
            'duration' => $duration,
            'model' => 'hybrid-nlp-v1',
        ];
    }

    /**
     * External generation using OpenAI adapter
     *
     * @param string $prompt Input prompt
     * @param array $options Generation options
     * @return array|\WP_Error Result
     */
    private function external_generate($prompt, $options = []) {
        if (!$this->openai_adapter) {
            return new \WP_Error('no_external_adapter', 'External adapter not available');
        }

        try {
            $result = $this->openai_adapter->chat_complete([
                ['role' => 'user', 'content' => $prompt]
            ], [
                'model' => $options['model'] ?? 'gpt-3.5-turbo',
                'max_tokens' => $options['max_tokens'] ?? 256,
                'temperature' => $options['temperature'] ?? 0.7,
            ]);

            if (is_wp_error($result)) {
                return $result;
            }

            return [
                'text' => $result['choices'][0]['message']['content'] ?? '',
                'confidence' => 0.9, // External APIs generally high confidence
                'method' => 'external',
                'model' => $result['model'] ?? 'unknown',
                'usage' => $result['usage'] ?? [],
            ];

        } catch (\Exception $e) {
            return new \WP_Error('external_generation_failed', $e->getMessage());
        }
    }

    /**
     * Combine native and external results intelligently
     *
     * @param string $native_text Native generation
     * @param string $external_text External generation
     * @param array $analysis Input analysis
     * @return string Combined text
     */
    private function combine_results($native_text, $external_text, $analysis) {
        $intent = $analysis['intent']['primary'];

        // Strategy depends on intent
        switch ($intent) {
            case 'completion':
                // For completion, prefer external (usually better quality)
                return $external_text;

            case 'creative':
                // For creative, combine both for more variety
                return $native_text . "\n\nAlternative: " . $external_text;

            case 'question':
                // For questions, prefer external accuracy
                return $external_text;

            default:
                // Default: prefer external but note native attempt
                return $external_text;
        }
    }

    /**
     * Analyze input using all native NLP components
     *
     * @param string $prompt Input text
     * @return array Comprehensive analysis
     */
    private function analyze_input($prompt) {
        return [
            'tokenization' => $this->tokenizer->analyze($prompt),
            'intent' => $this->intent_classifier->analyze($prompt),
            'sentiment' => $this->semantic_analyzer->analyze_sentiment($prompt),
            'keywords' => $this->semantic_analyzer->extract_keywords($prompt, 5),
            'topics' => $this->semantic_analyzer->extract_topics($prompt, 2),
            'semantic_richness' => $this->semantic_analyzer->analyze_semantic_richness($prompt),
        ];
    }

    /**
     * Calculate confidence score for generated text
     *
     * @param string $generated_text Generated output
     * @param string $prompt Original prompt
     * @param array $analysis Input analysis
     * @return float Confidence [0, 1]
     */
    private function calculate_confidence($generated_text, $prompt, $analysis) {
        $confidence = 0.5; // Base confidence

        // Factor 1: Perplexity (lower is better)
        $perplexity = $this->language_model->perplexity($generated_text);
        if ($perplexity < 100) {
            $confidence += 0.2;
        } elseif ($perplexity < 200) {
            $confidence += 0.1;
        }

        // Factor 2: Length appropriateness
        $gen_len = str_word_count($generated_text);
        $prompt_len = str_word_count($prompt);
        if ($gen_len > $prompt_len * 0.5 && $gen_len < $prompt_len * 3) {
            $confidence += 0.1;
        }

        // Factor 3: Semantic coherence
        $gen_keywords = $this->semantic_analyzer->extract_keywords($generated_text, 5);
        $prompt_keywords = $analysis['keywords'];

        $keyword_overlap = 0;
        foreach ($gen_keywords as $gen_kw) {
            foreach ($prompt_keywords as $prompt_kw) {
                if (similar_text($gen_kw['word'], $prompt_kw['word']) / strlen($prompt_kw['word']) > 0.7) {
                    $keyword_overlap++;
                }
            }
        }

        if ($keyword_overlap >= 2) {
            $confidence += 0.2;
        } elseif ($keyword_overlap >= 1) {
            $confidence += 0.1;
        }

        return min(1.0, $confidence);
    }

    /**
     * Load trained language model from database
     */
    private function load_trained_model() {
        $model_data = get_option('aevov_language_model_data');

        if ($model_data) {
            $this->language_model->from_array($model_data);
            error_log('[Unified Language Engine] Loaded trained model with ' . count($model_data['vocabulary']) . ' vocabulary items');
        } else {
            error_log('[Unified Language Engine] No trained model found, using default');
        }
    }

    /**
     * Initialize external adapter if available
     */
    private function init_external_adapter() {
        // Try to load OpenAI adapter
        if (class_exists('\AevovLanguageEngine\OpenAIAdapter')) {
            try {
                $this->openai_adapter = new \AevovLanguageEngine\OpenAIAdapter();
                error_log('[Unified Language Engine] OpenAI adapter initialized as fallback');
            } catch (\Exception $e) {
                error_log('[Unified Language Engine] Failed to initialize OpenAI adapter: ' . $e->getMessage());
            }
        }
    }

    /**
     * Train the language model on a corpus
     *
     * @param array $corpus Array of text samples
     * @return bool Success
     */
    public function train($corpus) {
        error_log('[Unified Language Engine] Training on ' . count($corpus) . ' samples');

        $this->language_model->train($corpus);

        // Save trained model
        $model_data = $this->language_model->to_array();
        update_option('aevov_language_model_data', $model_data, false);

        error_log('[Unified Language Engine] Training complete, model saved');

        return true;
    }

    /**
     * Get statistics
     *
     * @return array Statistics
     */
    public function get_stats() {
        return array_merge($this->stats, [
            'native_success_rate' => $this->stats['native_requests'] > 0
                ? $this->stats['native_successes'] / $this->stats['native_requests']
                : 0,
            'external_usage_rate' => $this->stats['native_requests'] > 0
                ? $this->stats['external_requests'] / $this->stats['native_requests']
                : 0,
        ]);
    }

    /**
     * Save statistics
     */
    private function save_stats() {
        update_option('aevov_language_engine_stats', $this->stats, false);
    }
}
