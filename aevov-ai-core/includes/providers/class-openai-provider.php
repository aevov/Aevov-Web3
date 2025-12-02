<?php
/**
 * OpenAI Provider
 *
 * Provides integration with OpenAI's models including:
 * - GPT-4o (flagship multimodal model)
 * - GPT-4 Turbo (advanced reasoning)
 * - GPT-3.5 Turbo (fast and efficient)
 * - DALL-E 3 (image generation)
 * - Whisper (speech-to-text)
 * - TTS (text-to-speech)
 *
 * @package AevovAICore
 */

namespace Aevov\AICore\Providers;

/**
 * OpenAI Provider Class
 */
class OpenAIProvider implements AIProviderInterface
{
    /**
     * API endpoint
     *
     * @var string
     */
    private string $endpoint = 'https://api.openai.com/v1';

    /**
     * API key
     *
     * @var string
     */
    private string $api_key = '';

    /**
     * Organization ID
     *
     * @var string
     */
    private string $organization = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->api_key = get_option('aevov_openai_api_key', '');
        $this->organization = get_option('aevov_openai_organization', '');
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name(): string
    {
        return 'OpenAI';
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
            'vision' => true,
            'embeddings' => true,
            'text_to_speech' => true,
            'speech_to_text' => true,
            'image_generation' => true,
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
                'id' => 'gpt-4o',
                'name' => 'GPT-4o',
                'description' => 'Most advanced multimodal model',
                'context_length' => 128000,
                'max_output' => 16384
            ],
            [
                'id' => 'gpt-4-turbo',
                'name' => 'GPT-4 Turbo',
                'description' => 'Latest GPT-4 with improved performance',
                'context_length' => 128000,
                'max_output' => 4096
            ],
            [
                'id' => 'gpt-4',
                'name' => 'GPT-4',
                'description' => 'Advanced reasoning model',
                'context_length' => 8192,
                'max_output' => 4096
            ],
            [
                'id' => 'gpt-3.5-turbo',
                'name' => 'GPT-3.5 Turbo',
                'description' => 'Fast and efficient model',
                'context_length' => 16385,
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
        $model = $params['model'] ?? 'gpt-3.5-turbo';
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

        if (isset($params['tools'])) {
            $request_body['tools'] = $params['tools'];
        }

        if (isset($params['response_format'])) {
            $request_body['response_format'] = $params['response_format'];
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        ];

        if (!empty($this->organization)) {
            $headers['OpenAI-Organization'] = $this->organization;
        }

        $response = wp_remote_post($this->endpoint . '/chat/completions', [
            'headers' => $headers,
            'body' => wp_json_encode($request_body),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('OpenAI API error: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            throw new \Exception('OpenAI API error: ' . $body['error']['message']);
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

        // OpenAI streaming implementation
        // This would use SSE (Server-Sent Events) for real streaming
        // For now, we'll simulate with chunked response
        $result = $this->complete($params);
        $callback($result['content']);
    }

    /**
     * Generate image
     *
     * @param string $prompt Image description
     * @param array $options Generation options
     * @return array Image data
     */
    public function generate_image(string $prompt, array $options = []): array
    {
        $model = $options['model'] ?? 'dall-e-3';
        $size = $options['size'] ?? '1024x1024';
        $quality = $options['quality'] ?? 'standard';
        $n = $options['n'] ?? 1;

        $response = wp_remote_post($this->endpoint . '/images/generations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'prompt' => $prompt,
                'size' => $size,
                'quality' => $quality,
                'n' => $n
            ]),
            'timeout' => 120
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('OpenAI API error: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            throw new \Exception('OpenAI API error: ' . $body['error']['message']);
        }

        return [
            'images' => $body['data'] ?? [],
            'model' => $model
        ];
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
        $model = $options['model'] ?? 'tts-1';
        $voice = $options['voice'] ?? 'alloy';
        $speed = $options['speed'] ?? 1.0;

        $response = wp_remote_post($this->endpoint . '/audio/speech', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'input' => $text,
                'voice' => $voice,
                'speed' => $speed
            ]),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('OpenAI API error: ' . $response->get_error_message());
        }

        return [
            'audio_data' => wp_remote_retrieve_body($response),
            'format' => 'mp3'
        ];
    }

    /**
     * Speech to text
     *
     * @param string $audio_file Audio file path
     * @param array $options Transcription options
     * @return array Transcription data
     */
    public function speech_to_text(string $audio_file, array $options = []): array
    {
        $model = $options['model'] ?? 'whisper-1';
        $language = $options['language'] ?? null;

        $boundary = wp_generate_password(24, false);

        $body = '';
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"" . basename($audio_file) . "\"\r\n";
        $body .= "Content-Type: audio/mpeg\r\n\r\n";
        $body .= file_get_contents($audio_file) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
        $body .= "{$model}\r\n";

        if ($language) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"language\"\r\n\r\n";
            $body .= "{$language}\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        $response = wp_remote_post($this->endpoint . '/audio/transcriptions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => "multipart/form-data; boundary={$boundary}"
            ],
            'body' => $body,
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('OpenAI API error: ' . $response->get_error_message());
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'text' => $result['text'] ?? '',
            'language' => $result['language'] ?? ''
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
        update_option('aevov_openai_api_key', $api_key);
    }

    /**
     * Set organization
     *
     * @param string $organization Organization ID
     * @return void
     */
    public function set_organization(string $organization): void
    {
        $this->organization = $organization;
        update_option('aevov_openai_organization', $organization);
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
            'gpt-4o' => [2.50, 10.00],
            'gpt-4-turbo' => [10.00, 30.00],
            'gpt-4' => [30.00, 60.00],
            'gpt-3.5-turbo' => [0.50, 1.50]
        ];

        return $costs[$model] ?? [0.50, 1.50];
    }

    /**
     * Get embeddings
     *
     * @param string $text Text to embed
     * @param string $model Model to use
     * @return array Embedding vector
     */
    public function get_embeddings(string $text, string $model = 'text-embedding-3-small'): array
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
            throw new \Exception('OpenAI API error: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body['data'][0]['embedding'] ?? [];
    }
}
