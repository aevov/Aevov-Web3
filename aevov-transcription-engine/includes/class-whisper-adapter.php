<?php
/**
 * OpenAI Whisper Adapter for Transcription Engine
 *
 * Provides integration with OpenAI's Whisper ASR (Automatic Speech Recognition) API
 * Supports transcription and translation of audio files
 *
 * Features:
 * - Audio transcription (speech-to-text)
 * - Audio translation (to English)
 * - Multiple language support
 * - Timestamp generation
 * - Speaker diarization (via prompts)
 * - Multiple output formats (json, text, srt, vtt)
 * - Cost tracking
 * - Rate limiting
 *
 * @package AevovTranscriptionEngine
 * @since 1.0.0
 */

namespace AevovTranscriptionEngine;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WhisperAdapter
 */
class WhisperAdapter {

    /**
     * API base URL
     */
    const API_BASE_URL = 'https://api.openai.com/v1';

    /**
     * Supported models
     */
    const MODEL_WHISPER_1 = 'whisper-1';

    /**
     * Supported audio formats
     */
    const SUPPORTED_FORMATS = [
        'flac', 'mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'ogg', 'wav', 'webm',
    ];

    /**
     * Response formats
     */
    const FORMAT_JSON = 'json';
    const FORMAT_TEXT = 'text';
    const FORMAT_SRT = 'srt';
    const FORMAT_VTT = 'vtt';
    const FORMAT_VERBOSE_JSON = 'verbose_json';

    /**
     * Maximum file size (25MB)
     */
    const MAX_FILE_SIZE = 25 * 1024 * 1024;

    /**
     * @var string OpenAI API key
     */
    private $api_key;

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
    private $rate_limit_rpm = 50;

    /**
     * @var float Pricing per minute ($0.006 per minute)
     */
    private $price_per_minute = 0.006;

    /**
     * Constructor
     *
     * @param string $api_key OpenAI API key (optional)
     */
    public function __construct($api_key = null) {
        // Try to get API key from Aevov Core
        if (function_exists('aevov_get_api_key')) {
            $this->api_key = $api_key ?: aevov_get_api_key('transcription-engine', 'openai');
        } else {
            $this->api_key = $api_key;
        }

        if (empty($this->api_key)) {
            error_log('[Whisper Adapter] Warning: No API key configured');
        }

        // Load usage stats
        $this->usage_stats = get_option('aevov_whisper_usage_stats', [
            'total_transcriptions' => 0,
            'total_minutes' => 0,
            'total_cost' => 0,
            'by_language' => [],
        ]);
    }

    /**
     * Transcribe audio file
     *
     * @param string $file_path Path to audio file
     * @param array $options Transcription options
     * @return array|\WP_Error Transcription data or error
     */
    public function transcribe($file_path, $options = []) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'OpenAI API key not configured');
        }

        // Validate file
        $validation = $this->validate_audio_file($file_path);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Parse options
        $model = $options['model'] ?? self::MODEL_WHISPER_1;
        $language = $options['language'] ?? null; // Auto-detect if not specified
        $prompt = $options['prompt'] ?? null;
        $response_format = $options['response_format'] ?? self::FORMAT_JSON;
        $temperature = $options['temperature'] ?? 0.0;
        $timestamp_granularities = $options['timestamp_granularities'] ?? null;

        // Check rate limit
        $rate_check = $this->check_rate_limit();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        // Prepare multipart form data
        $boundary = wp_generate_password(24, false);

        $body = '';

        // Add audio file
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file_path) . "\"\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= file_get_contents($file_path) . "\r\n";

        // Add model
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
        $body .= $model . "\r\n";

        // Add optional parameters
        if ($language) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"language\"\r\n\r\n";
            $body .= $language . "\r\n";
        }

        if ($prompt) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"prompt\"\r\n\r\n";
            $body .= $prompt . "\r\n";
        }

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"response_format\"\r\n\r\n";
        $body .= $response_format . "\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"temperature\"\r\n\r\n";
        $body .= $temperature . "\r\n";

        if ($timestamp_granularities) {
            if (is_array($timestamp_granularities)) {
                foreach ($timestamp_granularities as $granularity) {
                    $body .= "--{$boundary}\r\n";
                    $body .= "Content-Disposition: form-data; name=\"timestamp_granularities[]\"\r\n\r\n";
                    $body .= $granularity . "\r\n";
                }
            }
        }

        $body .= "--{$boundary}--\r\n";

        // Make request
        $response = $this->make_request('/audio/transcriptions', $body, [
            'Content-Type' => "multipart/form-data; boundary={$boundary}",
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        // Estimate duration and track usage
        $duration = $this->estimate_audio_duration($file_path);
        $this->track_usage('transcribe', $duration, $language);

        do_action('aevov_whisper_transcribed', [
            'file' => basename($file_path),
            'language' => $language,
            'duration' => $duration,
        ]);

        return $response;
    }

    /**
     * Translate audio to English
     *
     * @param string $file_path Path to audio file
     * @param array $options Translation options
     * @return array|\WP_Error Translation data or error
     */
    public function translate($file_path, $options = []) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'OpenAI API key not configured');
        }

        // Validate file
        $validation = $this->validate_audio_file($file_path);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Parse options
        $model = $options['model'] ?? self::MODEL_WHISPER_1;
        $prompt = $options['prompt'] ?? null;
        $response_format = $options['response_format'] ?? self::FORMAT_JSON;
        $temperature = $options['temperature'] ?? 0.0;

        // Check rate limit
        $rate_check = $this->check_rate_limit();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        // Prepare multipart form data
        $boundary = wp_generate_password(24, false);

        $body = '';

        // Add audio file
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file_path) . "\"\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= file_get_contents($file_path) . "\r\n";

        // Add model
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
        $body .= $model . "\r\n";

        // Add optional parameters
        if ($prompt) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"prompt\"\r\n\r\n";
            $body .= $prompt . "\r\n";
        }

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"response_format\"\r\n\r\n";
        $body .= $response_format . "\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"temperature\"\r\n\r\n";
        $body .= $temperature . "\r\n";

        $body .= "--{$boundary}--\r\n";

        // Make request
        $response = $this->make_request('/audio/translations', $body, [
            'Content-Type' => "multipart/form-data; boundary={$boundary}",
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        // Estimate duration and track usage
        $duration = $this->estimate_audio_duration($file_path);
        $this->track_usage('translate', $duration, 'auto');

        do_action('aevov_whisper_translated', [
            'file' => basename($file_path),
            'duration' => $duration,
        ]);

        return $response;
    }

    /**
     * Transcribe with timestamps
     *
     * Returns detailed transcription with word-level or segment-level timestamps
     *
     * @param string $file_path Path to audio file
     * @param string $granularity Timestamp granularity ('word' or 'segment')
     * @param array $options Additional options
     * @return array|\WP_Error Transcription with timestamps or error
     */
    public function transcribe_with_timestamps($file_path, $granularity = 'segment', $options = []) {
        $options['response_format'] = self::FORMAT_VERBOSE_JSON;
        $options['timestamp_granularities'] = [$granularity];

        return $this->transcribe($file_path, $options);
    }

    /**
     * Generate subtitles in SRT format
     *
     * @param string $file_path Path to audio file
     * @param array $options Transcription options
     * @return string|\WP_Error SRT subtitles or error
     */
    public function generate_srt($file_path, $options = []) {
        $options['response_format'] = self::FORMAT_SRT;

        $result = $this->transcribe($file_path, $options);

        if (is_wp_error($result)) {
            return $result;
        }

        // Result is already in SRT format as a string
        return $result;
    }

    /**
     * Generate subtitles in WebVTT format
     *
     * @param string $file_path Path to audio file
     * @param array $options Transcription options
     * @return string|\WP_Error WebVTT subtitles or error
     */
    public function generate_vtt($file_path, $options = []) {
        $options['response_format'] = self::FORMAT_VTT;

        $result = $this->transcribe($file_path, $options);

        if (is_wp_error($result)) {
            return $result;
        }

        // Result is already in VTT format as a string
        return $result;
    }

    /**
     * Validate audio file
     *
     * @param string $file_path Path to audio file
     * @return bool|\WP_Error True if valid, error otherwise
     */
    private function validate_audio_file($file_path) {
        // Check file exists
        if (!file_exists($file_path)) {
            return new \WP_Error('file_not_found', 'Audio file not found');
        }

        // Check file size
        $file_size = filesize($file_path);
        if ($file_size > self::MAX_FILE_SIZE) {
            return new \WP_Error('file_too_large', sprintf('Audio file must be less than %dMB', self::MAX_FILE_SIZE / (1024 * 1024)));
        }

        // Check file format
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (!in_array($extension, self::SUPPORTED_FORMATS)) {
            return new \WP_Error('unsupported_format', sprintf('Unsupported audio format. Supported formats: %s', implode(', ', self::SUPPORTED_FORMATS)));
        }

        return true;
    }

    /**
     * Estimate audio duration
     *
     * Uses getID3 library if available, otherwise estimates based on file size
     *
     * @param string $file_path Path to audio file
     * @return float Duration in minutes
     */
    private function estimate_audio_duration($file_path) {
        // Try to use getID3 if available
        if (class_exists('getID3')) {
            try {
                $getID3 = new \getID3();
                $file_info = $getID3->analyze($file_path);

                if (isset($file_info['playtime_seconds'])) {
                    return $file_info['playtime_seconds'] / 60;
                }
            } catch (\Exception $e) {
                error_log('[Whisper Adapter] getID3 error: ' . $e->getMessage());
            }
        }

        // Fallback: Rough estimate based on file size
        // Assume ~1MB per minute for typical audio (very rough)
        $file_size = filesize($file_path);
        $estimated_minutes = $file_size / (1024 * 1024);

        return max(0.1, $estimated_minutes); // Minimum 0.1 minute
    }

    /**
     * Make API request
     *
     * @param string $endpoint API endpoint
     * @param mixed $body Request body
     * @param array $extra_headers Additional headers
     * @param int $retry_count Retry attempt
     * @return mixed Response data or error
     */
    private function make_request($endpoint, $body, $extra_headers = [], $retry_count = 0) {
        $url = self::API_BASE_URL . $endpoint;

        // Build headers
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->api_key,
        ], $extra_headers);

        // Make request
        $args = [
            'method' => 'POST',
            'headers' => $headers,
            'body' => $body,
            'timeout' => 120, // Transcription can take time
        ];

        error_log("[Whisper Adapter] Making request to {$endpoint}");

        $response = wp_remote_post($url, $args);

        // Handle HTTP errors
        if (is_wp_error($response)) {
            error_log('[Whisper Adapter] Request failed: ' . $response->get_error_message());

            // Retry on network errors
            if ($retry_count < $this->max_retries) {
                $wait_time = pow(2, $retry_count);
                sleep($wait_time);
                return $this->make_request($endpoint, $body, $extra_headers, $retry_count + 1);
            }

            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);

        // Check if response is JSON
        $data = json_decode($body_raw, true);

        // Handle API errors
        if ($status_code !== 200) {
            $error_message = is_array($data) && isset($data['error']['message'])
                ? $data['error']['message']
                : 'Unknown error';

            error_log("[Whisper Adapter] API error ({$status_code}): {$error_message}");

            // Retry on rate limit or server errors
            if (in_array($status_code, [429, 500, 502, 503, 504]) && $retry_count < $this->max_retries) {
                $retry_after = wp_remote_retrieve_header($response, 'retry-after') ?: pow(2, $retry_count);
                sleep((int)$retry_after);
                return $this->make_request($endpoint, $body, $extra_headers, $retry_count + 1);
            }

            return new \WP_Error('api_error', $error_message, ['status' => $status_code]);
        }

        // Return JSON data if available, otherwise return raw body (for SRT/VTT formats)
        return $data ?: $body_raw;
    }

    /**
     * Check rate limit
     *
     * @return bool|\WP_Error True if OK, error if exceeded
     */
    private function check_rate_limit() {
        $key = 'aevov_whisper_rate_limit';

        $requests = get_transient($key) ?: 0;

        if ($requests >= $this->rate_limit_rpm) {
            $ttl = 60 - (time() % 60);

            return new \WP_Error(
                'rate_limit_exceeded',
                sprintf('Whisper rate limit exceeded. Maximum %d requests per minute. Try again in %d seconds.', $this->rate_limit_rpm, $ttl),
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
     * @param string $operation Operation type (transcribe or translate)
     * @param float $duration Duration in minutes
     * @param string $language Language code
     */
    private function track_usage($operation, $duration, $language) {
        $cost = $duration * $this->price_per_minute;

        $this->usage_stats['total_transcriptions']++;
        $this->usage_stats['total_minutes'] += $duration;
        $this->usage_stats['total_cost'] += $cost;

        if ($language) {
            if (!isset($this->usage_stats['by_language'][$language])) {
                $this->usage_stats['by_language'][$language] = [
                    'count' => 0,
                    'minutes' => 0,
                    'cost' => 0,
                ];
            }

            $this->usage_stats['by_language'][$language]['count']++;
            $this->usage_stats['by_language'][$language]['minutes'] += $duration;
            $this->usage_stats['by_language'][$language]['cost'] += $cost;
        }

        // Save stats
        update_option('aevov_whisper_usage_stats', $this->usage_stats, false);

        error_log(sprintf(
            '[Whisper Adapter] %s completed - Duration: %.2f min, Language: %s, Cost: $%.4f - Total: %d transcriptions, %.2f min, $%.2f',
            ucfirst($operation),
            $duration,
            $language ?: 'auto',
            $cost,
            $this->usage_stats['total_transcriptions'],
            $this->usage_stats['total_minutes'],
            $this->usage_stats['total_cost']
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
     * Reset usage statistics
     */
    public function reset_usage_stats() {
        $this->usage_stats = [
            'total_transcriptions' => 0,
            'total_minutes' => 0,
            'total_cost' => 0,
            'by_language' => [],
        ];

        update_option('aevov_whisper_usage_stats', $this->usage_stats, false);
    }
}
