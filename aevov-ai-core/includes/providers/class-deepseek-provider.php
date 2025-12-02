<?php
/**
 * DeepSeek AI Provider
 *
 * Provides integration with DeepSeek's AI models including:
 * - DeepSeek-V3 (671B parameters, MoE architecture)
 * - DeepSeek-Coder (coding specialist)
 * - DeepSeek-Math (mathematics specialist)
 *
 * @package AevovAICore
 */

namespace Aevov\AICore\Providers;

/**
 * DeepSeek Provider Class
 */
class DeepSeekProvider implements AIProviderInterface
{
    /**
     * API endpoint
     *
     * @var string
     */
    private string $endpoint = 'https://api.deepseek.com/v1';

    /**
     * API key
     *
     * @var string
     */
    private string $api_key = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->api_key = get_option('aevov_deepseek_api_key', '');
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name(): string
    {
        return 'DeepSeek';
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
            'json_mode' => true,
            'vision' => false,
            'embeddings' => true,
            'fine_tuning' => true
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
                'id' => 'deepseek-chat',
                'name' => 'DeepSeek Chat',
                'description' => 'General purpose chat model',
                'context_length' => 32768,
                'max_output' => 4096
            ],
            [
                'id' => 'deepseek-coder',
                'name' => 'DeepSeek Coder',
                'description' => 'Specialized coding model',
                'context_length' => 32768,
                'max_output' => 4096
            ],
            [
                'id' => 'deepseek-reasoner',
                'name' => 'DeepSeek Reasoner (R1)',
                'description' => 'Advanced reasoning model',
                'context_length' => 64000,
                'max_output' => 8192
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
        $model = $params['model'] ?? 'deepseek-chat';
        $messages = $params['messages'] ?? [];
        $temperature = $params['temperature'] ?? 0.7;
        $max_tokens = $params['max_tokens'] ?? 2000;
        $stream = $params['stream'] ?? false;

        $request_body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
            'stream' => $stream
        ];

        if (isset($params['functions'])) {
            $request_body['functions'] = $params['functions'];
        }

        if (isset($params['response_format'])) {
            $request_body['response_format'] = $params['response_format'];
        }

        $response = wp_remote_post($this->endpoint . '/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($request_body),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('DeepSeek API error: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            throw new \Exception('DeepSeek API error: ' . $body['error']['message']);
        }

        return [
            'content' => $body['choices'][0]['message']['content'] ?? '',
            'model' => $body['model'],
            'usage' => $body['usage'] ?? [],
            'finish_reason' => $body['choices'][0]['finish_reason'] ?? '',
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

        // DeepSeek streaming implementation
        // This would use SSE (Server-Sent Events) for real streaming
        // For now, we'll simulate with chunked response
        $result = $this->complete($params);
        $callback($result['content']);
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
        update_option('aevov_deepseek_api_key', $api_key);
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
            $response = wp_remote_get($this->endpoint . '/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key
                ],
                'timeout' => 10
            ]);

            return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
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
            'deepseek-chat' => [0.14, 0.28], // $0.14/$0.28 per 1M tokens
            'deepseek-coder' => [0.14, 0.28],
            'deepseek-reasoner' => [0.55, 2.19] // R1 pricing
        ];

        return $costs[$model] ?? [0.14, 0.28];
    }

    /**
     * Get embeddings
     *
     * @param string $text Text to embed
     * @param string $model Model to use
     * @return array Embedding vector
     */
    public function get_embeddings(string $text, string $model = 'deepseek-embedding'): array
    {
        $response = wp_remote_post($this->endpoint . '/embeddings', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'input' => $text
            ]),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('DeepSeek API error: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body['data'][0]['embedding'] ?? [];
    }
}
