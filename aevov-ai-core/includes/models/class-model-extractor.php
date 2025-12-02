<?php
/**
 * Model Extractor
 *
 * Extracts knowledge and patterns from conversations to create .aev models:
 * - Conversation analysis and pattern detection
 * - Knowledge extraction from interactions
 * - Automatic example generation
 * - Quality scoring and filtering
 *
 * @package AevovAICore
 */

namespace Aevov\AICore\Models;

use Aevov\AICore\Debug\DebugEngine;

/**
 * Model Extractor Class
 */
class ModelExtractor
{
    /**
     * Debug engine
     *
     * @var DebugEngine
     */
    private DebugEngine $debug;

    /**
     * Minimum quality score
     *
     * @var float
     */
    private float $min_quality_score = 0.6;

    /**
     * Maximum examples per extraction
     *
     * @var int
     */
    private int $max_examples = 1000;

    /**
     * Constructor
     *
     * @param DebugEngine $debug Debug engine
     */
    public function __construct(DebugEngine $debug)
    {
        $this->debug = $debug;
    }

    /**
     * Extract model from conversations
     *
     * @param array $conversations Array of conversation data
     * @param array $config Extraction configuration
     * @return AevModel
     */
    public function extract_from_conversations(array $conversations, array $config = []): AevModel
    {
        $this->debug->log('info', 'ModelExtractor', 'Starting extraction', [
            'conversation_count' => count($conversations)
        ]);

        $model = new AevModel([
            'name' => $config['name'] ?? 'Extracted Model',
            'base_provider' => $config['base_provider'] ?? 'deepseek',
            'base_model' => $config['base_model'] ?? 'deepseek-chat',
            'metadata' => [
                'extraction_date' => current_time('mysql'),
                'source_conversations' => count($conversations),
                'created_at' => current_time('mysql')
            ]
        ]);

        // Extract system prompt from patterns
        $system_prompt = $this->extract_system_prompt($conversations);
        $model->set_system_prompt($system_prompt);

        // Extract training examples
        $examples = $this->extract_examples($conversations);

        // Filter by quality
        $quality_examples = $this->filter_by_quality($examples);

        // Add to model
        $added = 0;
        foreach ($quality_examples as $example) {
            if ($added >= $this->max_examples) {
                break;
            }

            $model->add_training_example(
                $example['input'],
                $example['output'],
                $example['metadata']
            );
            $added++;
        }

        // Set default parameters
        $model->set_parameter('temperature', $config['temperature'] ?? 0.7);
        $model->set_parameter('max_tokens', $config['max_tokens'] ?? 2000);
        $model->set_parameter('top_p', $config['top_p'] ?? 0.9);

        $this->debug->log('info', 'ModelExtractor', 'Extraction complete', [
            'examples_extracted' => $added,
            'quality_threshold' => $this->min_quality_score
        ]);

        return $model;
    }

    /**
     * Extract system prompt from conversation patterns
     *
     * @param array $conversations Conversations
     * @return string System prompt
     */
    private function extract_system_prompt(array $conversations): string
    {
        $behaviors = [];
        $topics = [];
        $styles = [];

        foreach ($conversations as $conversation) {
            $messages = $conversation['messages'] ?? [];

            foreach ($messages as $message) {
                if (($message['role'] ?? '') === 'assistant') {
                    // Analyze assistant behavior
                    $content = $message['content'] ?? '';

                    // Detect technical topics
                    if (preg_match('/\b(code|function|class|api|database)\b/i', $content)) {
                        $topics['technical'] = ($topics['technical'] ?? 0) + 1;
                    }

                    // Detect explanatory style
                    if (preg_match('/\b(because|therefore|however|for example)\b/i', $content)) {
                        $styles['explanatory'] = ($styles['explanatory'] ?? 0) + 1;
                    }

                    // Detect helpful behavior
                    if (preg_match('/\b(help|assist|guide|support)\b/i', $content)) {
                        $behaviors['helpful'] = ($behaviors['helpful'] ?? 0) + 1;
                    }
                }
            }
        }

        // Build system prompt from patterns
        $prompt_parts = ['You are a helpful AI assistant'];

        if (($topics['technical'] ?? 0) > count($conversations) * 0.5) {
            $prompt_parts[] = 'with expertise in technical topics and programming';
        }

        if (($styles['explanatory'] ?? 0) > count($conversations) * 0.5) {
            $prompt_parts[] = 'that provides detailed explanations and examples';
        }

        return implode(' ', $prompt_parts) . '.';
    }

    /**
     * Extract training examples from conversations
     *
     * @param array $conversations Conversations
     * @return array Examples
     */
    private function extract_examples(array $conversations): array
    {
        $examples = [];

        foreach ($conversations as $conversation) {
            $messages = $conversation['messages'] ?? [];
            $context = [];

            for ($i = 0; $i < count($messages); $i++) {
                $message = $messages[$i];
                $role = $message['role'] ?? '';
                $content = $message['content'] ?? '';

                if ($role === 'user') {
                    // Look for assistant response
                    if (isset($messages[$i + 1]) && $messages[$i + 1]['role'] === 'assistant') {
                        $response = $messages[$i + 1]['content'];

                        // Create example with context
                        $input = $content;
                        if (!empty($context)) {
                            $input = implode("\n", $context) . "\n" . $content;
                        }

                        $examples[] = [
                            'input' => $input,
                            'output' => $response,
                            'metadata' => [
                                'conversation_id' => $conversation['id'] ?? '',
                                'timestamp' => $message['timestamp'] ?? current_time('mysql'),
                                'has_context' => !empty($context)
                            ],
                            'quality_score' => $this->calculate_quality_score($input, $response)
                        ];

                        // Update context window
                        $context[] = "User: {$content}";
                        $context[] = "Assistant: {$response}";

                        // Keep only last 3 exchanges as context
                        if (count($context) > 6) {
                            $context = array_slice($context, -6);
                        }
                    }
                }
            }
        }

        return $examples;
    }

    /**
     * Calculate quality score for example
     *
     * @param string $input Input text
     * @param string $output Output text
     * @return float Quality score (0-1)
     */
    private function calculate_quality_score(string $input, string $output): float
    {
        $score = 1.0;

        // Penalize very short inputs/outputs
        if (strlen($input) < 10) {
            $score *= 0.5;
        }
        if (strlen($output) < 20) {
            $score *= 0.7;
        }

        // Penalize very long outputs (likely errors or logs)
        if (strlen($output) > 5000) {
            $score *= 0.6;
        }

        // Reward detailed responses
        if (strlen($output) > 200 && strlen($output) < 2000) {
            $score *= 1.2;
        }

        // Check for code blocks (valuable for technical models)
        if (preg_match('/```[\s\S]*?```/', $output)) {
            $score *= 1.3;
        }

        // Check for structured output
        if (preg_match('/^(#+\s|1\.|•|-)/m', $output)) {
            $score *= 1.1;
        }

        // Penalize error messages
        if (preg_match('/\b(error|failed|exception|invalid)\b/i', $output)) {
            $score *= 0.8;
        }

        // Penalize repetitive content
        $words = str_word_count($output, 1);
        $unique_words = array_unique($words);
        if (count($words) > 0) {
            $uniqueness = count($unique_words) / count($words);
            if ($uniqueness < 0.3) {
                $score *= 0.5;
            }
        }

        return min(1.0, max(0.0, $score));
    }

    /**
     * Filter examples by quality score
     *
     * @param array $examples Examples with quality scores
     * @return array Filtered examples
     */
    private function filter_by_quality(array $examples): array
    {
        return array_filter($examples, function ($example) {
            return ($example['quality_score'] ?? 0) >= $this->min_quality_score;
        });
    }

    /**
     * Extract from database
     *
     * @param array $filters Database filters
     * @param array $config Extraction config
     * @return AevModel
     */
    public function extract_from_database(array $filters = [], array $config = []): AevModel
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aev_conversations';
        $where = ['1=1'];
        $params = [];

        if (isset($filters['start_date'])) {
            $where[] = 'created_at >= %s';
            $params[] = $filters['start_date'];
        }

        if (isset($filters['end_date'])) {
            $where[] = 'created_at <= %s';
            $params[] = $filters['end_date'];
        }

        if (isset($filters['provider'])) {
            $where[] = 'provider = %s';
            $params[] = $filters['provider'];
        }

        $where_clause = implode(' AND ', $where);

        if (!empty($params)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT 1000",
                ...$params
            );
        } else {
            $query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT 1000";
        }

        $results = $wpdb->get_results($query, ARRAY_A);

        $conversations = array_map(function ($row) {
            return [
                'id' => $row['id'],
                'messages' => json_decode($row['messages'], true),
                'timestamp' => $row['created_at']
            ];
        }, $results);

        return $this->extract_from_conversations($conversations, $config);
    }

    /**
     * Set minimum quality score
     *
     * @param float $score Quality score threshold (0-1)
     * @return void
     */
    public function set_min_quality_score(float $score): void
    {
        $this->min_quality_score = max(0.0, min(1.0, $score));
    }

    /**
     * Set maximum examples
     *
     * @param int $max Maximum examples
     * @return void
     */
    public function set_max_examples(int $max): void
    {
        $this->max_examples = max(1, $max);
    }
}
