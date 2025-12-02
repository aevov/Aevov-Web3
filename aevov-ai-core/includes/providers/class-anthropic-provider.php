<?php
/**
 * Anthropic Provider
 *
 * Provides integration with Anthropic's Claude models:
 * - Claude Sonnet 4.5 (latest flagship model)
 * - Claude 3.5 Sonnet (balanced performance)
 * - Claude 3 Opus (highest capability)
 * - Claude 3 Haiku (fastest)
 *
 * @package AevovAICore
 */

namespace Aevov\AICore\Providers;

/**
 * Anthropic Provider Class
 */
class AnthropicProvider implements AIProviderInterface
{
    /**
     * API endpoint
     *
     * @var string
     */
    private string $endpoint = 'https://api.anthropic.com/v1';

    /**
     * API key
     *
     * @var string
     */
    private string $api_key = '';

    /**
     * API version
     *
     * @var string
     */
    private string $api_version = '2023-06-01';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->api_key = get_option('aevov_anthropic_api_key', '');
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name(): string
    {
        return 'Anthropic';
    }

    /**
     * Get supported capabilities
     *
     * @return array
     */
    public function get_capabilities(): array
    {
        return [
            'text_completion' => true,
            'chat' => true,
            'streaming' => true,
            'function_calling' => true,
            'json_mode' => false,
            'vision' => true,
            'embeddings' => false,
            'extended_thinking' => true
        ];
    }

    /**
     * Get available models
     *
     * @return array
     */
    public function get_models(): array
    {
        return [
            [
                'id' => 'claude-sonnet-4-5-20250929',
                'name' => 'Claude Sonnet 4.5',
                'description' => 'Latest flagship model with advanced capabilities',
                'context_length' => 200000,
                'max_output' => 8192
            ],
            [
                'id' => 'claude-3-5-sonnet-20241022',
                'name' => 'Claude 3.5 Sonnet',
                'description' => 'Balanced performance and speed',
                'context_length' => 200000,
                'max_output' => 8192
            ],
            [
                'id' => 'claude-3-opus-20240229',
                'name' => 'Claude 3 Opus',
                'description' => 'Highest capability model',
                'context_length' => 200000,
                'max_output' => 4096
            ],
            [
                'id' => 'claude-3-haiku-20240307',
                'name' => 'Claude 3 Haiku',
                'description' => 'Fastest model for quick responses',
                'context_length' => 200000,
                'max_output' => 4096
            ]
        ];
    }

    /**
     * Complete text
     *
     * @param array $params Parameters
     * @return array Response
     */
    public function complete(array $params): array
    {
        $model = $params['model'] ?? 'claude-3-5-sonnet-20241022';
        $messages = $params['messages'] ?? [];
        $temperature = $params['temperature'] ?? 0.7;
        $max_tokens = $params['max_tokens'] ?? 2000;
        $stream = $params['stream'] ?? false;

        // Extract system message if present
        $system = '';
        if (!empty($messages) && $messages[0]['role'] === 'system') {
            $system = array_shift($messages)['content'];
        }

        $request_body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
            'stream' => $stream
        ];

        if (!empty($system)) {
            $request_body['system'] = $system;
        }

        if (isset($params['tools'])) {
            $request_body['tools'] = $params['tools'];
        }

        if (isset($params['extended_thinking'])) {
            $request_body['extended_thinking'] = $params['extended_thinking'];
        }

        $response = wp_remote_post($this->endpoint . '/messages', [
            'headers' => [
                'x-api-key' => $this->api_key,
                'anthropic-version' => $this->api_version,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($request_body),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('Anthropic API error: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            throw new \Exception('Anthropic API error: ' . $body['error']['message']);
        }

        // Extract content from Claude's response format
        $content = '';
        if (isset($body['content']) && is_array($body['content'])) {
            foreach ($body['content'] as $block) {
                if ($block['type'] === 'text') {
                    $content .= $block['text'];
                }
            }
        }

        return [
            'content' => $content,
            'model' => $body['model'],
            'usage' => $body['usage'] ?? [],
            'finish_reason' => $body['stop_reason'] ?? '',
            'raw_response' => $body
        ];
    }

    /**
     * Stream completion
     *
     * @param array $params Parameters
     * @param callable $callback Callback for each chunk
     * @return void
     */
    public function stream(array $params, callable $callback): void
    {
        $params['stream'] = true;

        // Anthropic streaming implementation
        // This would use SSE (Server-Sent Events) for real streaming
        // For now, we'll simulate with chunked response
        $result = $this->complete($params);
        $callback($result['content']);
    }

    /**
     * Complete with vision
     *
     * @param array $params Parameters with image content
     * @return array Response
     */
    public function complete_with_vision(array $params): array
    {
        // Claude supports vision through content blocks
        // Images should be in the format:
        // {
        //   "type": "image",
        //   "source": {
        //     "type": "base64",
        //     "media_type": "image/jpeg",
        //     "data": "base64_encoded_data"
        //   }
        // }

        return $this->complete($params);
    }

    /**
     * Get API endpoint
     *
     * @return string
     */
    public function get_endpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Get API key
     *
     * @return string
     */
    public function get_api_key(): string
    {
        return $this->api_key;
    }

    /**
     * Set API key
     *
     * @param string $api_key API key
     * @return void
     */
    public function set_api_key(string $api_key): void
    {
        $this->api_key = $api_key;
        update_option('aevov_anthropic_api_key', $api_key);
    }

    /**
     * Validate configuration
     *
     * @return bool
     */
    public function validate_config(): bool
    {
        if (empty($this->api_key)) {
            return false;
        }

        // Test API key with a minimal request
        try {
            $this->complete([
                'messages' => [['role' => 'user', 'content' => 'Hi']],
                'max_tokens' => 10
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get cost per token
     *
     * @param string $model Model name
     * @return array [input_cost, output_cost] in USD per 1M tokens
     */
    public function get_token_cost(string $model): array
    {
        $costs = [
            'claude-sonnet-4-5-20250929' => [3.00, 15.00], // Sonnet 4.5 pricing
            'claude-3-5-sonnet-20241022' => [3.00, 15.00],
            'claude-3-opus-20240229' => [15.00, 75.00],
            'claude-3-haiku-20240307' => [0.25, 1.25]
        ];

        // Match by model prefix
        foreach ($costs as $model_prefix => $cost) {
            if (strpos($model, $model_prefix) === 0) {
                return $cost;
            }
        }

        return [3.00, 15.00]; // Default to Sonnet pricing
    }

    /**
     * Count tokens (estimation)
     *
     * @param string $text Text to count
     * @return int Token count
     */
    public function count_tokens(string $text): int
    {
        // Rough estimation: 1 token ≈ 4 characters
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Get token limit
     *
     * @param string $model Model name
     * @return int Token limit
     */
    public function get_token_limit(string $model): int
    {
        // All Claude 3+ models support 200K context
        return 200000;
    }

    /**
     * Format messages for Claude
     *
     * @param array $messages Messages
     * @return array Formatted messages
     */
    private function format_messages(array $messages): array
    {
        $formatted = [];

        foreach ($messages as $message) {
            $role = $message['role'];
            $content = $message['content'];

            // Claude uses 'user' and 'assistant' roles
            if ($role === 'system') {
                continue; // System messages handled separately
            }

            $formatted[] = [
                'role' => $role,
                'content' => $content
            ];
        }

        return $formatted;
    }
}
