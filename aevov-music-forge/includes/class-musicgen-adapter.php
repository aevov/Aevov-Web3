<?php
/**
 * MusicGen Adapter for Music Forge
 *
 * Provides integration with Meta's MusicGen via Replicate API
 * MusicGen is a state-of-the-art text-to-music generation model
 *
 * Features:
 * - Text-to-music generation
 * - Melody conditioning
 * - Multiple model sizes (small, medium, large, melody)
 * - Duration control
 * - Top-k, top-p sampling
 * - Temperature control
 * - Classifier-free guidance
 * - Format selection (wav, mp3)
 *
 * @package AevovMusicForge
 * @since 1.0.0
 */

namespace AevovMusicForge;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MusicGenAdapter
 */
class MusicGenAdapter {

    /**
     * Replicate API base URL
     */
    const API_BASE_URL = 'https://api.replicate.com/v1';

    /**
     * MusicGen model versions
     */
    const MODEL_SMALL = 'facebook/musicgen:small';
    const MODEL_MEDIUM = 'facebook/musicgen:medium';
    const MODEL_LARGE = 'facebook/musicgen:large';
    const MODEL_MELODY = 'facebook/musicgen:melody';

    /**
     * Replicate model IDs (these may need updating)
     */
    private $model_versions = [
        'small' => 'facebook/musicgen:7a76a8258b23fae65c5a22debb8841d1d7e816b75c2f24218cd2bd8573787906',
        'medium' => 'facebook/musicgen:b05b1dff1d8c6dc63d14b0cdb42135378dcb87f6373b0d3d341ede46e59e2b38',
        'large' => 'facebook/musicgen:7be0f12c54a8d033a0fbd14418c9af98962da9a86f5ff7811f9b3b17a8e8f255',
        'melody' => 'facebook/musicgen:671ac645ce5e552cc63a54a2bbff63fcf798043055d2dac5fc9e36a837eedcfb',
    ];

    /**
     * @var string Replicate API token
     */
    private $api_token;

    /**
     * @var int Maximum retries
     */
    private $max_retries = 3;

    /**
     * @var array Usage statistics
     */
    private $usage_stats = [];

    /**
     * @var int Rate limit: requests per minute
     */
    private $rate_limit_rpm = 10;

    /**
     * @var int Max polling attempts for async jobs
     */
    private $max_poll_attempts = 60; // 5 minutes at 5s intervals

    /**
     * Constructor
     *
     * @param string $api_token Replicate API token (optional)
     */
    public function __construct($api_token = null) {
        // Try to get API token from Aevov Core
        if (function_exists('aevov_get_api_key')) {
            $this->api_token = $api_token ?: aevov_get_api_key('music-forge', 'replicate');
        } else {
            $this->api_token = $api_token;
        }

        if (empty($this->api_token)) {
            error_log('[MusicGen Adapter] Warning: No API token configured');
        }

        // Load usage stats
        $this->usage_stats = get_option('aevov_musicgen_usage_stats', [
            'total_generations' => 0,
            'total_duration' => 0,
            'by_model' => [],
        ]);
    }

    /**
     * Generate music from text prompt
     *
     * @param string $prompt Text description of music to generate
     * @param array $options Generation options
     * @return array|\WP_Error Music data or error
     */
    public function generate($prompt, $options = []) {
        if (empty($this->api_token)) {
            return new \WP_Error('no_api_token', 'Replicate API token not configured');
        }

        // Parse options
        $model = $options['model'] ?? 'melody';
        $duration = $options['duration'] ?? 8;
        $temperature = $options['temperature'] ?? 1.0;
        $top_k = $options['top_k'] ?? 250;
        $top_p = $options['top_p'] ?? 0.0;
        $classifier_free_guidance = $options['classifier_free_guidance'] ?? 3.0;
        $output_format = $options['output_format'] ?? 'mp3';
        $normalization_strategy = $options['normalization_strategy'] ?? 'loudness';

        // Validate inputs
        $validation = $this->validate_params($model, $duration, $temperature, $top_k, $top_p);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Check rate limit
        $rate_check = $this->check_rate_limit();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        // Get model version
        $model_version = $this->model_versions[$model] ?? $this->model_versions['melody'];

        // Build input parameters
        $input = [
            'prompt' => $prompt,
            'duration' => $duration,
            'temperature' => $temperature,
            'top_k' => $top_k,
            'top_p' => $top_p,
            'classifier_free_guidance' => $classifier_free_guidance,
            'output_format' => $output_format,
            'normalization_strategy' => $normalization_strategy,
        ];

        // Add melody conditioning if provided and using melody model
        if ($model === 'melody' && !empty($options['melody_url'])) {
            $input['melody'] = $options['melody_url'];
        }

        // Create prediction
        $prediction = $this->create_prediction($model_version, $input);

        if (is_wp_error($prediction)) {
            return $prediction;
        }

        // Poll for completion
        $result = $this->wait_for_prediction($prediction['id']);

        if (is_wp_error($result)) {
            return $result;
        }

        // Track usage
        $this->track_usage($model, $duration);

        do_action('aevov_musicgen_generated', [
            'model' => $model,
            'duration' => $duration,
            'prompt' => $prompt,
        ]);

        return $result;
    }

    /**
     * Generate music with melody conditioning
     *
     * Uses melody model to generate music based on both text and melody input
     *
     * @param string $prompt Text description
     * @param string $melody_url URL to melody audio file
     * @param array $options Generation options
     * @return array|\WP_Error Music data or error
     */
    public function generate_with_melody($prompt, $melody_url, $options = []) {
        $options['model'] = 'melody';
        $options['melody_url'] = $melody_url;

        return $this->generate($prompt, $options);
    }

    /**
     * Create a prediction on Replicate
     *
     * @param string $model_version Model version ID
     * @param array $input Input parameters
     * @return array|\WP_Error Prediction data or error
     */
    private function create_prediction($model_version, $input) {
        $url = self::API_BASE_URL . '/predictions';

        $body = json_encode([
            'version' => $model_version,
            'input' => $input,
        ]);

        $response = $this->make_request($url, $body);

        if (is_wp_error($response)) {
            return $response;
        }

        return $response;
    }

    /**
     * Wait for prediction to complete
     *
     * Polls the prediction endpoint until completion or timeout
     *
     * @param string $prediction_id Prediction ID
     * @return array|\WP_Error Completed prediction data or error
     */
    private function wait_for_prediction($prediction_id) {
        $url = self::API_BASE_URL . '/predictions/' . $prediction_id;

        $attempts = 0;

        while ($attempts < $this->max_poll_attempts) {
            $response = $this->make_request($url, null, 'GET');

            if (is_wp_error($response)) {
                return $response;
            }

            $status = $response['status'] ?? 'unknown';

            error_log("[MusicGen Adapter] Prediction status: {$status}");

            if ($status === 'succeeded') {
                return $response;
            }

            if ($status === 'failed') {
                $error_message = $response['error'] ?? 'Generation failed';
                return new \WP_Error('generation_failed', $error_message);
            }

            if ($status === 'canceled') {
                return new \WP_Error('generation_canceled', 'Generation was canceled');
            }

            // Wait before next poll
            sleep(5);
            $attempts++;
        }

        return new \WP_Error('generation_timeout', 'Music generation timed out after ' . ($this->max_poll_attempts * 5) . ' seconds');
    }

    /**
     * Validate generation parameters
     *
     * @param string $model Model size
     * @param int $duration Duration in seconds
     * @param float $temperature Temperature
     * @param int $top_k Top-k sampling
     * @param float $top_p Top-p sampling
     * @return bool|\WP_Error True if valid, error otherwise
     */
    private function validate_params($model, $duration, $temperature, $top_k, $top_p) {
        // Validate model
        if (!isset($this->model_versions[$model])) {
            return new \WP_Error('invalid_model', 'Model must be one of: small, medium, large, melody');
        }

        // Validate duration (MusicGen supports up to 30 seconds)
        if ($duration < 1 || $duration > 30) {
            return new \WP_Error('invalid_duration', 'Duration must be between 1 and 30 seconds');
        }

        // Validate temperature
        if ($temperature < 0 || $temperature > 2) {
            return new \WP_Error('invalid_temperature', 'Temperature must be between 0 and 2');
        }

        // Validate top_k
        if ($top_k < 0 || $top_k > 500) {
            return new \WP_Error('invalid_top_k', 'Top-k must be between 0 and 500');
        }

        // Validate top_p
        if ($top_p < 0 || $top_p > 1) {
            return new \WP_Error('invalid_top_p', 'Top-p must be between 0 and 1');
        }

        return true;
    }

    /**
     * Make API request
     *
     * @param string $url Full URL
     * @param mixed $body Request body (null for GET)
     * @param string $method HTTP method
     * @param int $retry_count Retry attempt
     * @return array|\WP_Error Response data or error
     */
    private function make_request($url, $body = null, $method = 'POST', $retry_count = 0) {
        // Build headers
        $headers = [
            'Authorization' => 'Token ' . $this->api_token,
            'Content-Type' => 'application/json',
        ];

        // Make request
        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
        ];

        if ($body !== null && $method === 'POST') {
            $args['body'] = $body;
        }

        error_log("[MusicGen Adapter] Making {$method} request to: {$url}");

        $response = $method === 'POST' ? wp_remote_post($url, $args) : wp_remote_get($url, $args);

        // Handle HTTP errors
        if (is_wp_error($response)) {
            error_log('[MusicGen Adapter] Request failed: ' . $response->get_error_message());

            // Retry on network errors
            if ($retry_count < $this->max_retries) {
                $wait_time = pow(2, $retry_count);
                sleep($wait_time);
                return $this->make_request($url, $body, $method, $retry_count + 1);
            }

            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $data = json_decode($body_raw, true);

        // Handle API errors
        if ($status_code >= 400) {
            $error_message = $data['detail'] ?? $data['error'] ?? 'Unknown error';

            error_log("[MusicGen Adapter] API error ({$status_code}): {$error_message}");

            // Retry on rate limit or server errors
            if (in_array($status_code, [429, 500, 502, 503, 504]) && $retry_count < $this->max_retries) {
                $wait_time = pow(2, $retry_count);
                sleep($wait_time);
                return $this->make_request($url, $body, $method, $retry_count + 1);
            }

            return new \WP_Error('api_error', $error_message, ['status' => $status_code]);
        }

        return $data;
    }

    /**
     * Check rate limit
     *
     * @return bool|\WP_Error True if OK, error if exceeded
     */
    private function check_rate_limit() {
        $key = 'aevov_musicgen_rate_limit';

        $requests = get_transient($key) ?: 0;

        if ($requests >= $this->rate_limit_rpm) {
            $ttl = 60 - (time() % 60);

            return new \WP_Error(
                'rate_limit_exceeded',
                sprintf('MusicGen rate limit exceeded. Maximum %d requests per minute. Try again in %d seconds.', $this->rate_limit_rpm, $ttl),
                ['retry_after' => $ttl]
            );
        }

        // Increment counter
        set_transient($key, $requests + 1, 60);

        return true;
    }

    /**
     * Track usage statistics
     *
     * @param string $model Model used
     * @param int $duration Duration in seconds
     */
    private function track_usage($model, $duration) {
        $this->usage_stats['total_generations']++;
        $this->usage_stats['total_duration'] += $duration;

        if (!isset($this->usage_stats['by_model'][$model])) {
            $this->usage_stats['by_model'][$model] = [
                'count' => 0,
                'duration' => 0,
            ];
        }

        $this->usage_stats['by_model'][$model]['count']++;
        $this->usage_stats['by_model'][$model]['duration'] += $duration;

        // Save stats
        update_option('aevov_musicgen_usage_stats', $this->usage_stats, false);

        error_log(sprintf(
            '[MusicGen Adapter] Generated music with %s model (%ds) - Total: %d generations, %ds',
            $model,
            $duration,
            $this->usage_stats['total_generations'],
            $this->usage_stats['total_duration']
        ));
    }

    /**
     * Get usage statistics
     *
     * @return array Usage stats
     */
    public function get_usage_stats() {
        return $this->usage_stats;
    }

    /**
     * Download music from URL to WordPress uploads
     *
     * @param string $url Music URL from prediction output
     * @param string $filename Optional filename
     * @return array|\WP_Error File data or error
     */
    public function download_music($url, $filename = null) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        // Detect format from URL or default to mp3
        $extension = 'mp3';
        if (strpos($url, '.wav') !== false) {
            $extension = 'wav';
        }

        // Generate filename if not provided
        if (!$filename) {
            $filename = 'musicgen-' . time() . '-' . wp_generate_password(8, false) . '.' . $extension;
        }

        // Download to temp file
        $temp_file = download_url($url);

        if (is_wp_error($temp_file)) {
            return $temp_file;
        }

        // Move to uploads directory
        $upload_dir = wp_upload_dir();
        $target_path = $upload_dir['path'] . '/' . $filename;

        if (!@rename($temp_file, $target_path)) {
            @unlink($temp_file);
            return new \WP_Error('move_failed', 'Failed to move downloaded music file');
        }

        return [
            'path' => $target_path,
            'url' => $upload_dir['url'] . '/' . $filename,
            'filename' => $filename,
            'extension' => $extension,
        ];
    }

    /**
     * Cancel a running prediction
     *
     * @param string $prediction_id Prediction ID
     * @return bool|\WP_Error True if canceled, error otherwise
     */
    public function cancel_prediction($prediction_id) {
        if (empty($this->api_token)) {
            return new \WP_Error('no_api_token', 'Replicate API token not configured');
        }

        $url = self::API_BASE_URL . '/predictions/' . $prediction_id . '/cancel';

        $response = $this->make_request($url, '{}', 'POST');

        if (is_wp_error($response)) {
            return $response;
        }

        return true;
    }

    /**
     * Get prediction status
     *
     * @param string $prediction_id Prediction ID
     * @return array|\WP_Error Prediction data or error
     */
    public function get_prediction_status($prediction_id) {
        if (empty($this->api_token)) {
            return new \WP_Error('no_api_token', 'Replicate API token not configured');
        }

        $url = self::API_BASE_URL . '/predictions/' . $prediction_id;

        return $this->make_request($url, null, 'GET');
    }
}
