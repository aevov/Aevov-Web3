<?php
/**
 * OpenAI API Adapter for Aevov Language Engine
 *
 * Handles communication with OpenAI API including:
 * - Authentication and API key management
 * - Request/response handling
 * - Rate limiting and retry logic
 * - Token counting and optimization
 * - Error handling and logging
 * - Streaming support
 * - Multi-model support (GPT-4, GPT-3.5, etc.)
 *
 * @package AevovLanguageEngine
 * @since 1.0.0
 */

namespace AevovLanguageEngine;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class OpenAIAdapter
 */
class OpenAIAdapter {

    /**
     * OpenAI API endpoint
     */
    const API_ENDPOINT = 'https://api.openai.com/v1';

    /**
     * @var string API key
     */
    private $api_key;

    /**
     * @var string Organization ID (optional)
     */
    private $organization_id;

    /**
     * @var array Rate limit tracking
     */
    private $rate_limits = [
        'requests_per_minute' => 0,
        'tokens_per_minute' => 0,
        'last_reset' => 0,
    ];

    /**
     * @var int Maximum retries for failed requests
     */
    private $max_retries = 3;

    /**
     * @var int Request timeout in seconds
     */
    private $timeout = 60;

    /**
     * @var array Supported models
     */
    private $supported_models = [
        'gpt-4-turbo-preview' => ['max_tokens' => 128000, 'cost_per_1k_input' => 0.01, 'cost_per_1k_output' => 0.03],
        'gpt-4' => ['max_tokens' => 8192, 'cost_per_1k_input' => 0.03, 'cost_per_1k_output' => 0.06],
        'gpt-4-32k' => ['max_tokens' => 32768, 'cost_per_1k_input' => 0.06, 'cost_per_1k_output' => 0.12],
        'gpt-3.5-turbo' => ['max_tokens' => 16385, 'cost_per_1k_input' => 0.0005, 'cost_per_1k_output' => 0.0015],
        'gpt-3.5-turbo-16k' => ['max_tokens' => 16385, 'cost_per_1k_input' => 0.003, 'cost_per_1k_output' => 0.004],
    ];

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct($config = []) {
        // Get API key from environment or options
        $this->api_key = $config['api_key'] ??
                        getenv('OPENAI_API_KEY') ?:
                        get_option('aevov_language_engine_api_key', '');

        $this->organization_id = $config['organization_id'] ?? getenv('OPENAI_ORG_ID') ?: '';

        if (isset($config['timeout'])) {
            $this->timeout = (int)$config['timeout'];
        }

        if (isset($config['max_retries'])) {
            $this->max_retries = (int)$config['max_retries'];
        }

        // Validate API key
        if (empty($this->api_key)) {
            error_log('[Aevov Language Engine] OpenAI API key not configured');
        }

        // Initialize rate limiting
        $this->rate_limits['last_reset'] = time();
    }

    /**
     * Complete a chat conversation
     *
     * @param array $messages Array of messages [{role, content}]
     * @param array $options Additional options
     * @return array|WP_Error Response or error
     */
    public function chat_complete($messages, $options = []) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'OpenAI API key not configured');
        }

        $model = $options['model'] ?? 'gpt-3.5-turbo';
        $max_tokens = $options['max_tokens'] ?? 1000;
        $temperature = $options['temperature'] ?? 0.7;
        $stream = $options['stream'] ?? false;

        // Validate model
        if (!isset($this->supported_models[$model])) {
            return new \WP_Error('invalid_model', "Model {$model} is not supported");
        }

        // Check token limits
        $estimated_tokens = $this->estimate_tokens($messages);
        $model_max = $this->supported_models[$model]['max_tokens'];

        if ($estimated_tokens + $max_tokens > $model_max) {
            return new \WP_Error(
                'token_limit_exceeded',
                sprintf('Estimated tokens (%d) + max_tokens (%d) exceeds model limit (%d)',
                    $estimated_tokens, $max_tokens, $model_max)
            );
        }

        // Check rate limits
        $rate_check = $this->check_rate_limit($estimated_tokens);
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        // Prepare request
        $body = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
        ];

        // Add optional parameters
        if (isset($options['top_p'])) {
            $body['top_p'] = $options['top_p'];
        }

        if (isset($options['presence_penalty'])) {
            $body['presence_penalty'] = $options['presence_penalty'];
        }

        if (isset($options['frequency_penalty'])) {
            $body['frequency_penalty'] = $options['frequency_penalty'];
        }

        if (isset($options['functions'])) {
            $body['functions'] = $options['functions'];
        }

        if (isset($options['function_call'])) {
            $body['function_call'] = $options['function_call'];
        }

        if ($stream) {
            $body['stream'] = true;
            return $this->stream_request('/chat/completions', $body, $options['stream_callback'] ?? null);
        }

        // Make request with retry logic
        $response = $this->make_request('/chat/completions', $body);

        if (is_wp_error($response)) {
            return $response;
        }

        // Track usage
        if (isset($response['usage'])) {
            $this->track_usage($response['usage']);
        }

        return $response;
    }

    /**
     * Create a text completion
     *
     * @param string $prompt The prompt
     * @param array $options Additional options
     * @return array|WP_Error Response or error
     */
    public function text_complete($prompt, $options = []) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'OpenAI API key not configured');
        }

        $model = $options['model'] ?? 'gpt-3.5-turbo-instruct';
        $max_tokens = $options['max_tokens'] ?? 1000;
        $temperature = $options['temperature'] ?? 0.7;

        // Check rate limits
        $estimated_tokens = $this->estimate_tokens_from_text($prompt);
        $rate_check = $this->check_rate_limit($estimated_tokens);
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        $body = [
            'model' => $model,
            'prompt' => $prompt,
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
        ];

        $response = $this->make_request('/completions', $body);

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['usage'])) {
            $this->track_usage($response['usage']);
        }

        return $response;
    }

    /**
     * Create embeddings
     *
     * @param string|array $input Text or array of texts
     * @param array $options Additional options
     * @return array|WP_Error Response or error
     */
    public function create_embeddings($input, $options = []) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'OpenAI API key not configured');
        }

        $model = $options['model'] ?? 'text-embedding-ada-002';

        $body = [
            'model' => $model,
            'input' => $input,
        ];

        $response = $this->make_request('/embeddings', $body);

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['usage'])) {
            $this->track_usage($response['usage']);
        }

        return $response;
    }

    /**
     * Make API request with retry logic
     *
     * @param string $endpoint API endpoint (e.g., '/chat/completions')
     * @param array $body Request body
     * @param int $retry_count Current retry attempt
     * @return array|WP_Error Response or error
     */
    private function make_request($endpoint, $body, $retry_count = 0) {
        $url = self::API_ENDPOINT . $endpoint;

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
        ];

        if (!empty($this->organization_id)) {
            $headers['OpenAI-Organization'] = $this->organization_id;
        }

        $args = [
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'timeout' => $this->timeout,
            'method' => 'POST',
        ];

        $start_time = microtime(true);
        $response = wp_remote_post($url, $args);
        $duration = microtime(true) - $start_time;

        // Log request (without API key)
        $this->log_request($endpoint, $body, $duration);

        // Handle HTTP errors
        if (is_wp_error($response)) {
            error_log('[OpenAI Adapter] HTTP Error: ' . $response->get_error_message());

            // Retry on network errors
            if ($retry_count < $this->max_retries) {
                $wait_time = pow(2, $retry_count); // Exponential backoff
                error_log("[OpenAI Adapter] Retrying in {$wait_time} seconds (attempt " . ($retry_count + 1) . ")");
                sleep($wait_time);
                return $this->make_request($endpoint, $body, $retry_count + 1);
            }

            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        // Handle API errors
        if ($status_code !== 200) {
            $error_message = $data['error']['message'] ?? 'Unknown error';
            $error_type = $data['error']['type'] ?? 'api_error';

            error_log("[OpenAI Adapter] API Error ({$status_code}): {$error_message}");

            // Retry on rate limit or server errors
            if (in_array($status_code, [429, 500, 502, 503, 504]) && $retry_count < $this->max_retries) {
                $retry_after = wp_remote_retrieve_header($response, 'retry-after') ?: pow(2, $retry_count);
                error_log("[OpenAI Adapter] Rate limited or server error. Retrying in {$retry_after} seconds");
                sleep((int)$retry_after);
                return $this->make_request($endpoint, $body, $retry_count + 1);
            }

            return new \WP_Error($error_type, $error_message, ['status' => $status_code, 'data' => $data]);
        }

        // Track rate limit headers
        $this->update_rate_limits_from_headers($response);

        return $data;
    }

    /**
     * Stream request (for real-time responses)
     *
     * @param string $endpoint API endpoint
     * @param array $body Request body
     * @param callable|null $callback Stream callback function
     * @return array|WP_Error Response or error
     */
    private function stream_request($endpoint, $body, $callback = null) {
        // Note: WordPress wp_remote_post doesn't support streaming natively
        // This is a simplified implementation. For production, use cURL or Guzzle

        if (!function_exists('curl_init')) {
            return new \WP_Error('no_curl', 'cURL is required for streaming');
        }

        $url = self::API_ENDPOINT . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key,
            (!empty($this->organization_id) ? 'OpenAI-Organization: ' . $this->organization_id : ''),
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, wp_json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        // Buffer for collecting chunks
        $buffer = '';

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$buffer, $callback) {
            $buffer .= $data;

            // Process complete chunks (ending with \n\n)
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $chunk = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                // Parse SSE format
                if (strpos($chunk, 'data: ') === 0) {
                    $json = substr($chunk, 6);

                    if ($json === '[DONE]') {
                        break;
                    }

                    $parsed = json_decode($json, true);

                    if ($parsed && $callback && is_callable($callback)) {
                        call_user_func($callback, $parsed);
                    }
                }
            }

            return strlen($data);
        });

        $result = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            return new \WP_Error('curl_error', $error);
        }

        if ($http_code !== 200) {
            return new \WP_Error('http_error', "HTTP {$http_code}");
        }

        return ['status' => 'streaming_complete'];
    }

    /**
     * Check rate limits
     *
     * @param int $estimated_tokens Estimated tokens for request
     * @return bool|WP_Error True if OK, WP_Error if rate limited
     */
    private function check_rate_limit($estimated_tokens) {
        $current_time = time();

        // Reset counters every minute
        if ($current_time - $this->rate_limits['last_reset'] >= 60) {
            $this->rate_limits['requests_per_minute'] = 0;
            $this->rate_limits['tokens_per_minute'] = 0;
            $this->rate_limits['last_reset'] = $current_time;
        }

        // Get configured limits (with defaults)
        $max_requests = apply_filters('aevov_openai_max_requests_per_minute', 60);
        $max_tokens = apply_filters('aevov_openai_max_tokens_per_minute', 90000);

        // Check limits
        if ($this->rate_limits['requests_per_minute'] >= $max_requests) {
            $wait_time = 60 - ($current_time - $this->rate_limits['last_reset']);
            return new \WP_Error('rate_limit_requests', "Rate limit exceeded. Wait {$wait_time} seconds.");
        }

        if ($this->rate_limits['tokens_per_minute'] + $estimated_tokens > $max_tokens) {
            $wait_time = 60 - ($current_time - $this->rate_limits['last_reset']);
            return new \WP_Error('rate_limit_tokens', "Token rate limit exceeded. Wait {$wait_time} seconds.");
        }

        // Update counters
        $this->rate_limits['requests_per_minute']++;
        $this->rate_limits['tokens_per_minute'] += $estimated_tokens;

        return true;
    }

    /**
     * Update rate limits from response headers
     *
     * @param array $response HTTP response
     */
    private function update_rate_limits_from_headers($response) {
        // OpenAI includes rate limit info in headers
        $headers = wp_remote_retrieve_headers($response);

        if (isset($headers['x-ratelimit-limit-requests'])) {
            // Could store these for more accurate tracking
            $limit_requests = $headers['x-ratelimit-limit-requests'];
            $remaining_requests = $headers['x-ratelimit-remaining-requests'] ?? 0;
            $limit_tokens = $headers['x-ratelimit-limit-tokens'] ?? 0;
            $remaining_tokens = $headers['x-ratelimit-remaining-tokens'] ?? 0;

            // Log if approaching limits
            if ($remaining_requests < 10 || $remaining_tokens < 1000) {
                error_log("[OpenAI Adapter] Approaching rate limits: {$remaining_requests} requests, {$remaining_tokens} tokens remaining");
            }
        }
    }

    /**
     * Estimate tokens from messages
     *
     * @param array $messages Messages array
     * @return int Estimated token count
     */
    private function estimate_tokens($messages) {
        $total = 0;

        foreach ($messages as $message) {
            // Rough estimation: ~4 characters per token
            $total += strlen($message['content']) / 4;

            // Add overhead for message structure
            $total += 4; // Role, formatting, etc.
        }

        return (int)ceil($total);
    }

    /**
     * Estimate tokens from text
     *
     * @param string $text Text content
     * @return int Estimated token count
     */
    private function estimate_tokens_from_text($text) {
        // Rough estimation: ~4 characters per token
        return (int)ceil(strlen($text) / 4);
    }

    /**
     * Track API usage
     *
     * @param array $usage Usage data from API response
     */
    private function track_usage($usage) {
        // Get current usage stats
        $stats = get_option('aevov_language_engine_usage_stats', [
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
            'requests_by_model' => [],
        ]);

        // Update stats
        $stats['total_requests']++;
        $stats['total_tokens'] += $usage['total_tokens'] ?? 0;

        // Calculate cost if model info available
        if (isset($usage['model']) && isset($this->supported_models[$usage['model']])) {
            $model_info = $this->supported_models[$usage['model']];
            $input_tokens = $usage['prompt_tokens'] ?? 0;
            $output_tokens = $usage['completion_tokens'] ?? 0;

            $cost = ($input_tokens / 1000 * $model_info['cost_per_1k_input']) +
                    ($output_tokens / 1000 * $model_info['cost_per_1k_output']);

            $stats['total_cost'] += $cost;
        }

        // Track by model
        $model = $usage['model'] ?? 'unknown';
        if (!isset($stats['requests_by_model'][$model])) {
            $stats['requests_by_model'][$model] = 0;
        }
        $stats['requests_by_model'][$model]++;

        // Save stats
        update_option('aevov_language_engine_usage_stats', $stats, false);

        // Fire action for external tracking
        do_action('aevov_openai_usage_tracked', $usage, $stats);
    }

    /**
     * Log API request
     *
     * @param string $endpoint API endpoint
     * @param array $body Request body (sanitized)
     * @param float $duration Request duration in seconds
     */
    private function log_request($endpoint, $body, $duration) {
        if (!apply_filters('aevov_openai_enable_logging', false)) {
            return;
        }

        $log_entry = [
            'timestamp' => current_time('mysql'),
            'endpoint' => $endpoint,
            'model' => $body['model'] ?? 'unknown',
            'estimated_tokens' => $this->estimate_tokens($body['messages'] ?? []),
            'duration' => round($duration, 3),
        ];

        // Get log history
        $logs = get_option('aevov_language_engine_request_logs', []);

        // Add new entry
        array_unshift($logs, $log_entry);

        // Keep only last 100 entries
        $logs = array_slice($logs, 0, 100);

        // Save logs
        update_option('aevov_language_engine_request_logs', $logs, false);
    }

    /**
     * Get usage statistics
     *
     * @return array Usage statistics
     */
    public function get_usage_stats() {
        return get_option('aevov_language_engine_usage_stats', [
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
            'requests_by_model' => [],
        ]);
    }

    /**
     * Get supported models
     *
     * @return array Supported models with metadata
     */
    public function get_supported_models() {
        return $this->supported_models;
    }

    /**
     * Validate API key
     *
     * @param string|null $api_key API key to validate (uses configured key if null)
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_api_key($api_key = null) {
        $test_key = $api_key ?? $this->api_key;

        if (empty($test_key)) {
            return new \WP_Error('empty_key', 'API key is empty');
        }

        // Make a minimal request to validate
        $url = self::API_ENDPOINT . '/models';

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $test_key,
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 200) {
            return true;
        } elseif ($status_code === 401) {
            return new \WP_Error('invalid_key', 'API key is invalid');
        } else {
            return new \WP_Error('validation_failed', 'Could not validate API key');
        }
    }

    /**
     * Clear usage statistics
     */
    public function clear_usage_stats() {
        delete_option('aevov_language_engine_usage_stats');
        delete_option('aevov_language_engine_request_logs');
    }
}
