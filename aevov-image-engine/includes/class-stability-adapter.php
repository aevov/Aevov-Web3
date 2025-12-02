<?php
/**
 * Stability AI Adapter for Image Engine
 *
 * Provides integration with Stability AI's image generation APIs
 * Supports: Stable Diffusion XL, Stable Diffusion 3, SD Upscale, etc.
 *
 * Features:
 * - Text-to-image generation
 * - Image-to-image transformation
 * - Upscaling (4x, 8x)
 * - Inpainting and outpainting
 * - Multiple models (SDXL, SD3, etc.)
 * - Style presets
 * - Advanced sampling parameters
 * - Rate limiting and cost tracking
 *
 * @package AevovImageEngine
 * @since 1.0.0
 */

namespace AevovImageEngine;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class StabilityAdapter
 */
class StabilityAdapter {

    /**
     * API base URL
     */
    const API_BASE_URL = 'https://api.stability.ai/v1';

    /**
     * Supported models (engines)
     */
    const MODEL_SDXL_1_0 = 'stable-diffusion-xl-1024-v1-0';
    const MODEL_SD_3 = 'stable-diffusion-v3-0';
    const MODEL_SD_1_6 = 'stable-diffusion-v1-6';
    const MODEL_SDXL_BETA = 'stable-diffusion-xl-beta-v2-2-2';

    /**
     * Sampler methods
     */
    const SAMPLERS = [
        'DDIM', 'DDPM', 'K_DPMPP_2M', 'K_DPMPP_2S_ANCESTRAL',
        'K_DPM_2', 'K_DPM_2_ANCESTRAL', 'K_EULER', 'K_EULER_ANCESTRAL',
        'K_HEUN', 'K_LMS',
    ];

    /**
     * Style presets
     */
    const STYLE_PRESETS = [
        '3d-model', 'analog-film', 'anime', 'cinematic', 'comic-book',
        'digital-art', 'enhance', 'fantasy-art', 'isometric', 'line-art',
        'low-poly', 'modeling-compound', 'neon-punk', 'origami',
        'photographic', 'pixel-art', 'tile-texture',
    ];

    /**
     * @var string Stability AI API key
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
    private $rate_limit_rpm = 10;

    /**
     * Constructor
     *
     * @param string $api_key Stability AI API key (optional)
     */
    public function __construct($api_key = null) {
        // Try to get API key from Aevov Core
        if (function_exists('aevov_get_api_key')) {
            $this->api_key = $api_key ?: aevov_get_api_key('image-engine', 'stability');
        } else {
            $this->api_key = $api_key;
        }

        if (empty($this->api_key)) {
            error_log('[Stability Adapter] Warning: No API key configured');
        }

        // Load usage stats
        $this->usage_stats = get_option('aevov_stability_usage_stats', [
            'total_images' => 0,
            'total_credits' => 0,
            'by_model' => [],
        ]);
    }

    /**
     * Generate image from text prompt
     *
     * @param string $prompt Text description
     * @param array $options Generation options
     * @return array|\WP_Error Image data or error
     */
    public function text_to_image($prompt, $options = []) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'Stability AI API key not configured');
        }

        // Parse options
        $model = $options['model'] ?? self::MODEL_SDXL_1_0;
        $negative_prompt = $options['negative_prompt'] ?? '';
        $width = $options['width'] ?? 1024;
        $height = $options['height'] ?? 1024;
        $cfg_scale = $options['cfg_scale'] ?? 7.0;
        $steps = $options['steps'] ?? 30;
        $sampler = $options['sampler'] ?? 'K_DPMPP_2M';
        $samples = $options['samples'] ?? 1;
        $seed = $options['seed'] ?? null;
        $style_preset = $options['style_preset'] ?? null;

        // Validate inputs
        $validation = $this->validate_text_to_image_params($width, $height, $cfg_scale, $steps, $sampler, $samples);
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
            'text_prompts' => [
                [
                    'text' => $prompt,
                    'weight' => 1,
                ],
            ],
            'cfg_scale' => $cfg_scale,
            'height' => $height,
            'width' => $width,
            'samples' => $samples,
            'steps' => $steps,
        ];

        // Add negative prompt if provided
        if (!empty($negative_prompt)) {
            $body['text_prompts'][] = [
                'text' => $negative_prompt,
                'weight' => -1,
            ];
        }

        // Add optional parameters
        if ($seed !== null) {
            $body['seed'] = $seed;
        }

        if ($sampler) {
            $body['sampler'] = $sampler;
        }

        if ($style_preset && in_array($style_preset, self::STYLE_PRESETS)) {
            $body['style_preset'] = $style_preset;
        }

        // Make request
        $response = $this->make_request("/generation/{$model}/text-to-image", $body);

        if (is_wp_error($response)) {
            return $response;
        }

        // Track usage
        $this->track_usage($model, $samples);

        do_action('aevov_stability_image_generated', [
            'model' => $model,
            'width' => $width,
            'height' => $height,
            'prompt' => $prompt,
        ]);

        return $response;
    }

    /**
     * Transform image using another image as input
     *
     * @param string $image_path Path to source image
     * @param string $prompt Text prompt for transformation
     * @param array $options Transformation options
     * @return array|\WP_Error Image data or error
     */
    public function image_to_image($image_path, $prompt, $options = []) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'Stability AI API key not configured');
        }

        // Validate image
        if (!file_exists($image_path)) {
            return new \WP_Error('invalid_image', 'Image file not found');
        }

        // Parse options
        $model = $options['model'] ?? self::MODEL_SDXL_1_0;
        $negative_prompt = $options['negative_prompt'] ?? '';
        $image_strength = $options['image_strength'] ?? 0.35;
        $cfg_scale = $options['cfg_scale'] ?? 7.0;
        $steps = $options['steps'] ?? 30;
        $sampler = $options['sampler'] ?? 'K_DPMPP_2M';
        $samples = $options['samples'] ?? 1;
        $style_preset = $options['style_preset'] ?? null;

        // Check rate limit
        $rate_check = $this->check_rate_limit();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        // Prepare multipart form data
        $boundary = wp_generate_password(24, false);

        $body = '';

        // Add init image
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="init_image"; filename="' . basename($image_path) . "\"\r\n";
        $body .= "Content-Type: image/png\r\n\r\n";
        $body .= file_get_contents($image_path) . "\r\n";

        // Add text prompts
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"text_prompts[0][text]\"\r\n\r\n";
        $body .= $prompt . "\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"text_prompts[0][weight]\"\r\n\r\n";
        $body .= "1\r\n";

        if (!empty($negative_prompt)) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"text_prompts[1][text]\"\r\n\r\n";
            $body .= $negative_prompt . "\r\n";

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"text_prompts[1][weight]\"\r\n\r\n";
            $body .= "-1\r\n";
        }

        // Add parameters
        $params = [
            'image_strength' => $image_strength,
            'cfg_scale' => $cfg_scale,
            'samples' => $samples,
            'steps' => $steps,
            'sampler' => $sampler,
        ];

        if ($style_preset) {
            $params['style_preset'] = $style_preset;
        }

        foreach ($params as $key => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
            $body .= $value . "\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        // Make request
        $response = $this->make_request("/generation/{$model}/image-to-image", $body, [
            'Content-Type' => "multipart/form-data; boundary={$boundary}",
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        // Track usage
        $this->track_usage($model, $samples);

        return $response;
    }

    /**
     * Upscale image
     *
     * @param string $image_path Path to source image
     * @param array $options Upscale options
     * @return array|\WP_Error Upscaled image data or error
     */
    public function upscale($image_path, $options = []) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'Stability AI API key not configured');
        }

        // Validate image
        if (!file_exists($image_path)) {
            return new \WP_Error('invalid_image', 'Image file not found');
        }

        // Parse options
        $width = $options['width'] ?? null;
        $height = $options['height'] ?? null;

        // Check rate limit
        $rate_check = $this->check_rate_limit();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        // Prepare multipart form data
        $boundary = wp_generate_password(24, false);

        $body = '';

        // Add image
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="image"; filename="' . basename($image_path) . "\"\r\n";
        $body .= "Content-Type: image/png\r\n\r\n";
        $body .= file_get_contents($image_path) . "\r\n";

        // Add dimensions if specified
        if ($width) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"width\"\r\n\r\n";
            $body .= $width . "\r\n";
        }

        if ($height) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"height\"\r\n\r\n";
            $body .= $height . "\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        // Make request
        $response = $this->make_request("/generation/esrgan-v1-x2plus/image-to-image/upscale", $body, [
            'Content-Type' => "multipart/form-data; boundary={$boundary}",
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        // Track usage
        $this->track_usage('esrgan-upscale', 1);

        return $response;
    }

    /**
     * Inpaint image (edit masked region)
     *
     * @param string $image_path Path to source image
     * @param string $mask_path Path to mask image (white = edit, black = keep)
     * @param string $prompt Description for inpainted region
     * @param array $options Inpainting options
     * @return array|\WP_Error Image data or error
     */
    public function inpaint($image_path, $mask_path, $prompt, $options = []) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'Stability AI API key not configured');
        }

        // Validate files
        if (!file_exists($image_path)) {
            return new \WP_Error('invalid_image', 'Image file not found');
        }

        if (!file_exists($mask_path)) {
            return new \WP_Error('invalid_mask', 'Mask file not found');
        }

        // Parse options
        $model = $options['model'] ?? self::MODEL_SDXL_1_0;
        $negative_prompt = $options['negative_prompt'] ?? '';
        $cfg_scale = $options['cfg_scale'] ?? 7.0;
        $steps = $options['steps'] ?? 30;

        // Check rate limit
        $rate_check = $this->check_rate_limit();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        // Prepare multipart form data
        $boundary = wp_generate_password(24, false);

        $body = '';

        // Add init image
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="init_image"; filename="' . basename($image_path) . "\"\r\n";
        $body .= "Content-Type: image/png\r\n\r\n";
        $body .= file_get_contents($image_path) . "\r\n";

        // Add mask image
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Disposition: form-data; name="mask_image"; filename="' . basename($mask_path) . "\"\r\n";
        $body .= "Content-Type: image/png\r\n\r\n";
        $body .= file_get_contents($mask_path) . "\r\n";

        // Add text prompts
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"text_prompts[0][text]\"\r\n\r\n";
        $body .= $prompt . "\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"text_prompts[0][weight]\"\r\n\r\n";
        $body .= "1\r\n";

        if (!empty($negative_prompt)) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"text_prompts[1][text]\"\r\n\r\n";
            $body .= $negative_prompt . "\r\n";

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"text_prompts[1][weight]\"\r\n\r\n";
            $body .= "-1\r\n";
        }

        // Add parameters
        $params = [
            'cfg_scale' => $cfg_scale,
            'steps' => $steps,
        ];

        foreach ($params as $key => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
            $body .= $value . "\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        // Make request
        $response = $this->make_request("/generation/{$model}/image-to-image/masking", $body, [
            'Content-Type' => "multipart/form-data; boundary={$boundary}",
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        // Track usage
        $this->track_usage($model, 1);

        return $response;
    }

    /**
     * Validate text-to-image parameters
     *
     * @param int $width Image width
     * @param int $height Image height
     * @param float $cfg_scale CFG scale
     * @param int $steps Generation steps
     * @param string $sampler Sampler method
     * @param int $samples Number of samples
     * @return bool|\WP_Error True if valid, error otherwise
     */
    private function validate_text_to_image_params($width, $height, $cfg_scale, $steps, $sampler, $samples) {
        // Validate dimensions (must be multiples of 64)
        if ($width % 64 !== 0 || $height % 64 !== 0) {
            return new \WP_Error('invalid_dimensions', 'Width and height must be multiples of 64');
        }

        // Validate dimension limits
        if ($width < 128 || $width > 2048 || $height < 128 || $height > 2048) {
            return new \WP_Error('invalid_dimensions', 'Dimensions must be between 128 and 2048');
        }

        // Validate CFG scale
        if ($cfg_scale < 0 || $cfg_scale > 35) {
            return new \WP_Error('invalid_cfg_scale', 'CFG scale must be between 0 and 35');
        }

        // Validate steps
        if ($steps < 10 || $steps > 150) {
            return new \WP_Error('invalid_steps', 'Steps must be between 10 and 150');
        }

        // Validate sampler
        if (!in_array($sampler, self::SAMPLERS)) {
            return new \WP_Error('invalid_sampler', 'Invalid sampler method');
        }

        // Validate samples
        if ($samples < 1 || $samples > 10) {
            return new \WP_Error('invalid_samples', 'Samples must be between 1 and 10');
        }

        return true;
    }

    /**
     * Make API request
     *
     * @param string $endpoint API endpoint
     * @param mixed $body Request body
     * @param array $extra_headers Additional headers
     * @param int $retry_count Retry attempt
     * @return array|\WP_Error Response data or error
     */
    private function make_request($endpoint, $body, $extra_headers = [], $retry_count = 0) {
        $url = self::API_BASE_URL . $endpoint;

        // Build headers
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->api_key,
            'Accept' => 'application/json',
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
            'timeout' => 180, // SD can take a while
        ];

        error_log("[Stability Adapter] Making request to {$endpoint}");

        $response = wp_remote_post($url, $args);

        // Handle HTTP errors
        if (is_wp_error($response)) {
            error_log('[Stability Adapter] Request failed: ' . $response->get_error_message());

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
        $data = json_decode($body_raw, true);

        // Handle API errors
        if ($status_code !== 200) {
            $error_message = $data['message'] ?? 'Unknown error';

            error_log("[Stability Adapter] API error ({$status_code}): {$error_message}");

            // Retry on rate limit or server errors
            if (in_array($status_code, [429, 500, 502, 503, 504]) && $retry_count < $this->max_retries) {
                $wait_time = pow(2, $retry_count);
                sleep($wait_time);
                return $this->make_request($endpoint, $body, $extra_headers, $retry_count + 1);
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
        $key = 'aevov_stability_rate_limit';

        $requests = get_transient($key) ?: 0;

        if ($requests >= $this->rate_limit_rpm) {
            $ttl = 60 - (time() % 60);

            return new \WP_Error(
                'rate_limit_exceeded',
                sprintf('Stability AI rate limit exceeded. Maximum %d requests per minute. Try again in %d seconds.', $this->rate_limit_rpm, $ttl),
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
     * @param int $samples Number of images generated
     */
    private function track_usage($model, $samples) {
        $this->usage_stats['total_images'] += $samples;
        $this->usage_stats['total_credits'] += $samples; // Simplified, actual credit usage varies

        if (!isset($this->usage_stats['by_model'][$model])) {
            $this->usage_stats['by_model'][$model] = [
                'count' => 0,
                'credits' => 0,
            ];
        }

        $this->usage_stats['by_model'][$model]['count'] += $samples;
        $this->usage_stats['by_model'][$model]['credits'] += $samples;

        // Save stats
        update_option('aevov_stability_usage_stats', $this->usage_stats, false);

        error_log(sprintf(
            '[Stability Adapter] Generated %d image(s) with %s - Total credits: %d',
            $samples,
            $model,
            $this->usage_stats['total_credits']
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
     * Save image from base64 data to WordPress uploads
     *
     * @param string $base64_data Base64 encoded image data
     * @param string $filename Optional filename
     * @return array|\WP_Error File data or error
     */
    public function save_image($base64_data, $filename = null) {
        // Generate filename if not provided
        if (!$filename) {
            $filename = 'stability-' . time() . '-' . wp_generate_password(8, false) . '.png';
        }

        // Decode base64
        $image_data = base64_decode($base64_data);

        if ($image_data === false) {
            return new \WP_Error('decode_failed', 'Failed to decode base64 image data');
        }

        // Get upload directory
        $upload_dir = wp_upload_dir();
        $target_path = $upload_dir['path'] . '/' . $filename;

        // Save file
        $saved = file_put_contents($target_path, $image_data);

        if ($saved === false) {
            return new \WP_Error('save_failed', 'Failed to save image file');
        }

        return [
            'path' => $target_path,
            'url' => $upload_dir['url'] . '/' . $filename,
            'filename' => $filename,
        ];
    }

    /**
     * List available models/engines
     *
     * @return array|\WP_Error List of engines or error
     */
    public function list_engines() {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'Stability AI API key not configured');
        }

        $url = self::API_BASE_URL . '/engines/list';

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
}
