<?php
/**
 * Language Engine Adapter - Interfaces with Aevov Language Engine
 *
 * Provides optimized integration with Language Engine:
 * - Request formatting and optimization
 * - Response parsing and normalization
 * - Streaming support
 * - Token management
 * - Error handling and retry logic
 *
 * @package AevovRuntime
 * @since 1.0.0
 */

class LanguageEngineAdapter {

    /**
     * Execute language tile
     *
     * @param array $tile Tile to execute
     * @return array Execution result
     * @throws Exception If execution fails
     */
    public function execute($tile) {
        // Check if Language Engine is available
        if (!function_exists('aevov_language_generate')) {
            throw new \Exception('Language Engine not available');
        }

        // Build request
        $request = $this->build_request($tile);

        // Execute through Language Engine
        $response = aevov_language_generate($request);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        // Normalize response
        return $this->normalize_response($response, $tile);
    }

    /**
     * Build request from tile
     *
     * @param array $tile Tile
     * @return array Request
     */
    private function build_request($tile) {
        $input = $tile['input'] ?? '';
        $model = $tile['model'] ?? 'gpt-3.5-turbo';

        // Prepare context from dependencies
        $messages = [
            [
                'role' => 'system',
                'content' => $tile['system_message'] ?? 'You are a helpful assistant.'
            ]
        ];

        // Add dependency context
        if (isset($tile['dependency_context']) && !empty($tile['dependency_context'])) {
            foreach ($tile['dependency_context'] as $context) {
                if (is_string($context)) {
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => $context
                    ];
                } elseif (is_array($context) && isset($context['text'])) {
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => $context['text']
                    ];
                }
            }
        }

        // Add user input
        $messages[] = [
            'role' => 'user',
            'content' => $input
        ];

        // Build request
        $request = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $tile['temperature'] ?? 0.7,
            'max_tokens' => $tile['max_tokens'] ?? null,
            'stream' => $tile['streaming'] ?? false,
            'top_p' => $tile['top_p'] ?? 1.0,
            'frequency_penalty' => $tile['frequency_penalty'] ?? 0.0,
            'presence_penalty' => $tile['presence_penalty'] ?? 0.0
        ];

        // Add optimization hints
        if (isset($tile['_aevrt_optimized']) && $tile['_aevrt_optimized']) {
            $request['_aevrt_optimized'] = true;

            if (isset($tile['_aevrt_node'])) {
                $request['_aevrt_preferred_node'] = $tile['_aevrt_node'];
            }
        }

        return $request;
    }

    /**
     * Normalize response
     *
     * @param array $response Response from engine
     * @param array $tile Original tile
     * @return array Normalized response
     */
    private function normalize_response($response, $tile) {
        // Extract text from various response formats
        $text = '';

        if (isset($response['choices'][0]['message']['content'])) {
            // Chat completion format
            $text = $response['choices'][0]['message']['content'];
        } elseif (isset($response['choices'][0]['text'])) {
            // Text completion format
            $text = $response['choices'][0]['text'];
        } elseif (isset($response['text'])) {
            // Direct text format
            $text = $response['text'];
        } elseif (is_string($response)) {
            // String response
            $text = $response;
        }

        return [
            'text' => $text,
            'raw_response' => $response,
            'model' => $tile['model'] ?? 'unknown',
            'usage' => $response['usage'] ?? null
        ];
    }

    /**
     * Estimate request cost
     *
     * @param array $tile Tile
     * @return float Estimated cost
     */
    public function estimate_cost($tile) {
        $model = $tile['model'] ?? 'gpt-3.5-turbo';
        $input_tokens = (strlen($tile['input'] ?? '') / 4);
        $output_tokens = $tile['max_tokens'] ?? 100;

        // Cost rates per 1K tokens (example rates)
        $rates = [
            'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
            'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
            'claude-3-opus' => ['input' => 0.015, 'output' => 0.075],
            'claude-3-sonnet' => ['input' => 0.003, 'output' => 0.015],
            'claude-3-haiku' => ['input' => 0.00025, 'output' => 0.00125]
        ];

        $rate = $rates[$model] ?? ['input' => 0.001, 'output' => 0.002];

        $input_cost = ($input_tokens / 1000) * $rate['input'];
        $output_cost = ($output_tokens / 1000) * $rate['output'];

        return $input_cost + $output_cost;
    }
}
