<?php
/**
 * OpenAI DALL-E Adapter for Image Engine
 *
 * Provides integration with OpenAI's DALL-E 3 and DALL-E 2 image generation APIs
 *
 * Features:
 * - Text-to-image generation
 * - Image editing (variations, inpainting)
 * - Multiple size support
 * - Quality settings (standard, hd)
 * - Style control (natural, vivid)
 * - Rate limiting and retry logic
 * - Cost tracking
 * - Error handling
 *
 * @package AevovImageEngine
 * @since 1.0.0
 */

namespace AevovImageEngine;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DalleAdapter
 */
class DalleAdapter {

    /**
     * API base URL
     */
    const API_BASE_URL = 'https://api.openai.com/v1';

    /**
     * Supported models
     */
    const MODEL_DALLE_3 = 'dall-e-3';
    const MODEL_DALLE_2 = 'dall-e-2';

    /**
     * Image sizes
     */
    const SIZES_DALLE_3 = ['1024x1024', '1024x1792', '1792x1024'];
    const SIZES_DALLE_2 = ['256x256', '512x512', '1024x1024'];

    /**
     * Quality settings
     */
    const QUALITY_STANDARD = 'standard';
    const QUALITY_HD = 'hd';

    /**
     * Style settings
     */
    const STYLE_NATURAL = 'natural';
    const STYLE_VIVID = 'vivid';

    /**
     * @var string OpenAI API key
     */
    private $api_key;

    /**
     * @var int Maximum retries for failed requests
     */
    private $max_retries = 3;

    /**
     * @var array Usage statistics
     */
    private $usage_stats = [];

    /**
     * @var int Rate limit: requests per minute
     */
    private $rate_limit_rpm = 5; // DALL-E 3 is expensive, limit heavily

    /**
     * @var array Pricing per model
     */
    private $pricing = [
        'dall-e-3' => [
            'standard_1024x1024' => 0.040,
            'standard_1024x1792' => 0.080,
            'standard_1792x1024' => 0.080,
            'hd_1024x1024' => 0.080,
            'hd_1024x1792' => 0.120,
            'hd_1792x1024' => 0.120,
        ],
        'dall-e-2' => [
            '1024x1024' => 0.020,
            '512x512' => 0.018,
            '256x256' => 0.016,
        ],
    ];

    /**
     * Constructor
     *
     * @param string $api_key OpenAI API key (optional, will try to retrieve from options)
     */
    public function __construct($api_key = null) {
        // Try to get API key from Aevov Core if available
        if (function_exists('aevov_get_api_key')) {
            $this->api_key = $api_key ?: aevov_get_api_key('image-engine', 'openai');
        } else {
            $this->api_key = $api_key;
        }

        if (empty($this->api_key)) {
            error_log('[DALL-E Adapter] Warning: No API key configured');
        }

        // Load usage stats
        $this->usage_stats = get_option('aevov_dalle_usage_stats', [
            'total_images' => 0,
            'total_cost' => 0,
            'by_model' => [],
        ]);
    }

    /**
     * Generate image from text prompt
     *
     * @param string $prompt Text description of the image to generate
     * @param array $options Generation options
     * @return array|\WP_Error Image data or error
     */
    public function generate($prompt, $options = []) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'OpenAI API key not configured');
        }

        // Parse options
        $model = $options['model'] ?? self::MODEL_DALLE_3;
        $size = $options['size'] ?? '1024x1024';
        $quality = $options['quality'] ?? self::QUALITY_STANDARD;
        $style = $options['style'] ?? self::STYLE_VIVID;
        $n = $options['n'] ?? 1;

        // Validate inputs
        $validation = $this->validate_generation_params($model, $size, $quality, $style, $n);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Check rate limit
        $rate_check = $this->check_rate_limit();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        // Build request body
        $body = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => $n,
            'size' => $size,
        ];

        // DALL-E 3 specific options
        if ($model === self::MODEL_DALLE_3) {
            $body['quality'] = $quality;
            $body['style'] = $style;

            // DALL-E 3 only supports n=1
            if ($n > 1) {
                error_log('[DALL-E Adapter] DALL-E 3 only supports n=1, overriding');
                $body['n'] = 1;
                $n = 1;
            }
        }

        // Add response format
        $body['response_format'] = $options['response_format'] ?? 'url';

        // Make API request
        $response = $this->make_request('/images/generations', $body);

        if (is_wp_error($response)) {
            return $response;
        }

        // Track usage and cost
        $this->track_usage($model, $size, $quality, $n);

        // Fire action for monitoring
        do_action('aevov_dalle_image_generated', [
            'model' => $model,
            'size' => $size,
            'quality' => $quality,
            'prompt' => $prompt,
            'cost' => $this->calculate_cost($model, $size, $quality, $n),
        ]);

        return $response;
    }

    /**
     * Create image variation
     *
     * Only supported by DALL-E 2
     *
     * @param string $image_path Path to source image (PNG, must be square, <4MB)
     * @param array $options Variation options
     * @return array|\WP_Error Image data or error
     */
    public function create_variation($image_path, $options = []) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'OpenAI API key not configured');
        }

        // Validate image file
        if (!file_exists($image_path)) {
            return new \WP_Error('invalid_image', 'Image file not found');
        }

        $file_size = filesize($image_path);
        if ($file_size > 4 * 1024 * 1024) {
            return new \WP_Error('file_too_large', 'Image must be less than 4MB');
        }

        // Parse options
        $n = $options['n'] ?? 1;
        $size = $options['size'] ?? '1024x1024';

        // Validate size for DALL-E 2
        if (!in_array($size, self::SIZES_DALLE_2)) {
            return new \WP_Error('invalid_size', 'Size must be one of: ' . implode(', ', self::SIZES_DALLE_2));
        }

        // Check rate limit
        $rate_check = $this->check_rate_limit();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        // Prepare multipart form data
        $boundary = wp_generate_password(24, false);

        $body = '';

        // Add image file
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="image"; filename="' . basename($image_path) . "\"\r\n";
        $body .= "Content-Type: image/png\r\n\r\n";
        $body .= file_get_contents($image_path) . "\r\n";

        // Add n parameter
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"n\"\r\n\r\n";
        $body .= $n . "\r\n";

        // Add size parameter
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"size\"\r\n\r\n";
        $body .= $size . "\r\n";

        $body .= "--{$boundary}--\r\n";

        // Make request
        $response = $this->make_request('/images/variations', $body, [
            'Content-Type' => "multipart/form-data; boundary={$boundary}",
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        // Track usage
        $this->track_usage(self::MODEL_DALLE_2, $size, self::QUALITY_STANDARD, $n);

        return $response;
    }

    /**
     * Create edited image (inpainting)
     *
     * Only supported by DALL-E 2
     *
     * @param string $image_path Path to source image
     * @param string $mask_path Path to mask image (transparent areas will be edited)
     * @param string $prompt Description of what to generate in masked area
     * @param array $options Edit options
     * @return array|\WP_Error Image data or error
     */
    public function edit($image_path, $mask_path, $prompt, $options = []) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'OpenAI API key not configured');
        }

        // Validate files
        if (!file_exists($image_path)) {
            return new \WP_Error('invalid_image', 'Image file not found');
        }

        if (!file_exists($mask_path)) {
            return new \WP_Error('invalid_mask', 'Mask file not found');
        }

        // Parse options
        $n = $options['n'] ?? 1;
        $size = $options['size'] ?? '1024x1024';

        // Check rate limit
        $rate_check = $this->check_rate_limit();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        // Prepare multipart form data
        $boundary = wp_generate_password(24, false);

        $body = '';

        // Add image file
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="image"; filename="' . basename($image_path) . "\"\r\n";
        $body .= "Content-Type: image/png\r\n\r\n";
        $body .= file_get_contents($image_path) . "\r\n";

        // Add mask file
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="mask"; filename="' . basename($mask_path) . "\"\r\n";
        $body .= "Content-Type: image/png\r\n\r\n";
        $body .= file_get_contents($mask_path) . "\r\n";

        // Add prompt
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"prompt\"\r\n\r\n";
        $body .= $prompt . "\r\n";

        // Add n parameter
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"n\"\r\n\r\n";
        $body .= $n . "\r\n";

        // Add size parameter
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"size\"\r\n\r\n";
        $body .= $size . "\r\n";

        $body .= "--{$boundary}--\r\n";

        // Make request
        $response = $this->make_request('/images/edits', $body, [
            'Content-Type' => "multipart/form-data; boundary={$boundary}",
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        // Track usage
        $this->track_usage(self::MODEL_DALLE_2, $size, self::QUALITY_STANDARD, $n);

        return $response;
    }

    /**
     * Validate generation parameters
     *
     * @param string $model Model name
     * @param string $size Image size
     * @param string $quality Quality setting
     * @param string $style Style setting
     * @param int $n Number of images
     * @return bool|\WP_Error True if valid, WP_Error if invalid
     */
    private function validate_generation_params($model, $size, $quality, $style, $n) {
        // Validate model
        if (!in_array($model, [self::MODEL_DALLE_3, self::MODEL_DALLE_2])) {
            return new \WP_Error('invalid_model', 'Model must be dall-e-3 or dall-e-2');
        }

        // Validate size based on model
        $valid_sizes = $model === self::MODEL_DALLE_3 ? self::SIZES_DALLE_3 : self::SIZES_DALLE_2;

        if (!in_array($size, $valid_sizes)) {
            return new \WP_Error('invalid_size', sprintf('Size must be one of: %s for %s', implode(', ', $valid_sizes), $model));
        }

        // Validate quality
        if (!in_array($quality, [self::QUALITY_STANDARD, self::QUALITY_HD])) {
            return new \WP_Error('invalid_quality', 'Quality must be standard or hd');
        }

        // Validate style
        if (!in_array($style, [self::STYLE_NATURAL, self::STYLE_VIVID])) {
            return new \WP_Error('invalid_style', 'Style must be natural or vivid');
        }

        // Validate n
        if ($n < 1 || $n > 10) {
            return new \WP_Error('invalid_n', 'n must be between 1 and 10');
        }

        return true;
    }

    /**
     * Make API request
     *
     * @param string $endpoint API endpoint
     * @param mixed $body Request body
     * @param array $extra_headers Additional headers
     * @param int $retry_count Current retry attempt
     * @return array|\WP_Error Response data or error
     */
    private function make_request($endpoint, $body, $extra_headers = [], $retry_count = 0) {
        $url = self::API_BASE_URL . $endpoint;

        // Build headers
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->api_key,
        ], $extra_headers);

        // If body is array, JSON encode it
        if (is_array($body)) {
            $headers['Content-Type'] = 'application/json';
            $body = json_encode($body);
        }

        // Make request
        $args = [
            'method' => 'POST',
            'headers' => $headers,
            'body' => $body,
            'timeout' => 120, // Image generation can take time
        ];

        error_log("[DALL-E Adapter] Making request to {$endpoint}");

        $response = wp_remote_post($url, $args);

        // Handle HTTP errors
        if (is_wp_error($response)) {
            error_log('[DALL-E Adapter] Request failed: ' . $response->get_error_message());

            // Retry on network errors
            if ($retry_count < $this->max_retries) {
                $wait_time = pow(2, $retry_count); // Exponential backoff
                sleep($wait_time);
                return $this->make_request($endpoint, $body, $extra_headers, $retry_count + 1);
            }

            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $data = json_decode($body_raw, true);

        // Handle API errors
        if ($status_code !== 200) {
            $error_message = $data['error']['message'] ?? 'Unknown error';
            $error_type = $data['error']['type'] ?? 'api_error';

            error_log("[DALL-E Adapter] API error ({$status_code}): {$error_message}");

            // Retry on rate limit or server errors
            if (in_array($status_code, [429, 500, 502, 503, 504]) && $retry_count < $this->max_retries) {
                $retry_after = wp_remote_retrieve_header($response, 'retry-after') ?: pow(2, $retry_count);
                sleep((int)$retry_after);
                return $this->make_request($endpoint, $body, $extra_headers, $retry_count + 1);
            }

            return new \WP_Error($error_type, $error_message, ['status' => $status_code]);
        }

        return $data;
    }

    /**
     * Check rate limit
     *
     * @return bool|\WP_Error True if OK, WP_Error if exceeded
     */
    private function check_rate_limit() {
        $key = 'aevov_dalle_rate_limit';

        $requests = get_transient($key) ?: 0;

        if ($requests >= $this->rate_limit_rpm) {
            $ttl = 60 - (time() % 60);

            return new \WP_Error(
                'rate_limit_exceeded',
                sprintf('DALL-E rate limit exceeded. Maximum %d images per minute. Try again in %d seconds.', $this->rate_limit_rpm, $ttl),
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
     * @param string $size Image size
     * @param string $quality Quality setting
     * @param int $n Number of images
     */
    private function track_usage($model, $size, $quality, $n) {
        $cost = $this->calculate_cost($model, $size, $quality, $n);

        $this->usage_stats['total_images'] += $n;
        $this->usage_stats['total_cost'] += $cost;

        if (!isset($this->usage_stats['by_model'][$model])) {
            $this->usage_stats['by_model'][$model] = [
                'count' => 0,
                'cost' => 0,
            ];
        }

        $this->usage_stats['by_model'][$model]['count'] += $n;
        $this->usage_stats['by_model'][$model]['cost'] += $cost;

        // Save stats
        update_option('aevov_dalle_usage_stats', $this->usage_stats, false);

        error_log(sprintf(
            '[DALL-E Adapter] Generated %d image(s) with %s (%s, %s) - Cost: $%.4f - Total: $%.2f',
            $n,
            $model,
            $size,
            $quality,
            $cost,
            $this->usage_stats['total_cost']
        ));
    }

    /**
     * Calculate cost for image generation
     *
     * @param string $model Model name
     * @param string $size Image size
     * @param string $quality Quality setting
     * @param int $n Number of images
     * @return float Cost in USD
     */
    private function calculate_cost($model, $size, $quality, $n) {
        if ($model === self::MODEL_DALLE_3) {
            $key = $quality . '_' . $size;
            $unit_cost = $this->pricing['dall-e-3'][$key] ?? 0.040;
        } else {
            $unit_cost = $this->pricing['dall-e-2'][$size] ?? 0.020;
        }

        return $unit_cost * $n;
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
            'total_images' => 0,
            'total_cost' => 0,
            'by_model' => [],
        ];

        update_option('aevov_dalle_usage_stats', $this->usage_stats, false);
    }

    /**
     * Download image from URL to WordPress uploads
     *
     * @param string $url Image URL from DALL-E response
     * @param string $filename Optional filename
     * @return array|\WP_Error File data or error
     */
    public function download_image($url, $filename = null) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        // Generate filename if not provided
        if (!$filename) {
            $filename = 'dalle-' . time() . '-' . wp_generate_password(8, false) . '.png';
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
            return new \WP_Error('move_failed', 'Failed to move downloaded image');
        }

        return [
            'path' => $target_path,
            'url' => $upload_dir['url'] . '/' . $filename,
            'filename' => $filename,
        ];
    }
}
