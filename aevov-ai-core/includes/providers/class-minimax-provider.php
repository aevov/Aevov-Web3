<?php
/**
 * MiniMax AI Provider
 *
 * Provides integration with MiniMax's AI models including:
 * - MiniMax-Text-01 (text generation)
 * - MiniMax-Speech (text-to-speech)
 * - MiniMax-Music (music generation)
 *
 * @package AevovAICore
 */

namespace Aevov\AICore\Providers;

/**
 * MiniMax Provider Class
 */
class MiniMaxProvider implements AIProviderInterface
{
    /**
     * API endpoint
     *
     * @var string
     */
    private string $endpoint = 'https://api.minimax.chat/v1';

    /**
     * API key
     *
     * @var string
     */
    private string $api_key = '';

    /**
     * Group ID
     *
     * @var string
     */
    private string $group_id = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->api_key = get_option('aevov_minimax_api_key', '');
        $this->group_id = get_option('aevov_minimax_group_id', '');
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name(): string
    {
        return 'MiniMax';
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
            'vision' => false,
            'embeddings' => true,
            'text_to_speech' => true,
            'speech_to_text' => true,
            'music_generation' => true
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
                'id' => 'abab6.5s-chat',
                'name' => 'MiniMax-Text-01',
                'description' => 'Advanced text generation model',
                'context_length' => 245760, // 240K context!
                'max_output' => 8192
            ],
            [
                'id' => 'abab6.5-chat',
                'name' => 'MiniMax-Chat',
                'description' => 'General purpose chat model',
                'context_length' => 245760,
                'max_output' => 8192
            ],
            [
                'id' => 'abab6.5g-chat',
                'name' => 'MiniMax-Long',
                'description' => 'Long context model',
                'context_length' => 1048576, // 1M tokens!
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
        $model = $params['model'] ?? 'abab6.5s-chat';
        $messages = $params['messages'] ?? [];
        $temperature = $params['temperature'] ?? 0.7;
        $max_tokens = $params['max_tokens'] ?? 2000;

        $request_body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
            'stream' => false
        ];

        if (isset($params['functions'])) {
            $request_body['functions'] = $params['functions'];
        }

        if (isset($params['plugins'])) {
            $request_body['plugins'] = $params['plugins'];
        }

        $response = wp_remote_post($this->endpoint . '/text/chatcompletion_v2', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'GroupId' => $this->group_id
            ],
            'body' => wp_json_encode($request_body),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('MiniMax API error: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['base_resp']) && $body['base_resp']['status_code'] !== 0) {
            throw new \Exception('MiniMax API error: ' . ($body['base_resp']['status_msg'] ?? 'Unknown error'));
        }

        return [
            'content' => $body['choices'][0]['message']['content'] ?? '',
            'model' => $model,
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
        // MiniMax supports SSE streaming
        $params['stream'] = true;

        // Simulated streaming for now
        $result = $this->complete($params);
        $callback($result['content']);
    }

    /**
     * Text to speech
     *
     * @param string $text Text to convert
     * @param array $options Voice options
     * @return array Audio data
     */
    public function text_to_speech(string $text, array $options = []): array
    {
        $voice_id = $options['voice_id'] ?? 'male-qn-qingse';
        $speed = $options['speed'] ?? 1.0;
        $vol = $options['volume'] ?? 1.0;
        $pitch = $options['pitch'] ?? 0;

        $response = wp_remote_post($this->endpoint . '/text_to_speech', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'GroupId' => $this->group_id
            ],
            'body' => wp_json_encode([
                'text' => $text,
                'voice_id' => $voice_id,
                'speed' => $speed,
                'vol' => $vol,
                'pitch' => $pitch,
                'audio_sample_rate' => 32000,
                'bitrate' => 128000
            ]),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('MiniMax API error: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'audio_file' => $body['audio_file'] ?? '',
            'format' => 'mp3',
            'duration' => $body['duration'] ?? 0
        ];
    }

    /**
     * Generate music
     *
     * @param string $prompt Music description
     * @param array $options Generation options
     * @return array Music data
     */
    public function generate_music(string $prompt, array $options = []): array
    {
        $duration = $options['duration'] ?? 30; // seconds
        $genre = $options['genre'] ?? 'pop';

        $response = wp_remote_post($this->endpoint . '/music/generation', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'GroupId' => $this->group_id
            ],
            'body' => wp_json_encode([
                'prompt' => $prompt,
                'duration' => $duration,
                'genre' => $genre
            ]),
            'timeout' => 120
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('MiniMax API error: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'music_file' => $body['music_file'] ?? '',
            'format' => 'mp3',
            'duration' => $duration
        ];
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
        update_option('aevov_minimax_api_key', $api_key);
    }

    /**
     * Set group ID
     *
     * @param string $group_id Group ID
     * @return void
     */
    public function set_group_id(string $group_id): void
    {
        $this->group_id = $group_id;
        update_option('aevov_minimax_group_id', $group_id);
    }

    /**
     * Validate configuration
     *
     * @return bool
     */
    public function validate_config(): bool
    {
        if (empty($this->api_key) || empty($this->group_id)) {
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
            'abab6.5s-chat' => [0.10, 0.10], // Estimated pricing
            'abab6.5-chat' => [0.10, 0.10],
            'abab6.5g-chat' => [0.20, 0.20] // Long context premium
        ];

        return $costs[$model] ?? [0.10, 0.10];
    }

    /**
     * Get embeddings
     *
     * @param string $text Text to embed
     * @param string $model Model to use
     * @return array Embedding vector
     */
    public function get_embeddings(string $text, string $model = 'embo-01'): array
    {
        $response = wp_remote_post($this->endpoint . '/embeddings', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'GroupId' => $this->group_id
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'texts' => [$text],
                'type' => 'query'
            ]),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('MiniMax API error: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body['vectors'][0] ?? [];
    }
}
