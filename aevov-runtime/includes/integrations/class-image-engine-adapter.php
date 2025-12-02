<?php
/**
 * Image Engine Adapter - Interfaces with Aevov Image Engine
 *
 * Provides optimized integration with Image Engine:
 * - Request formatting and optimization
 * - Region-based processing support
 * - Image stitching for tiled generation
 * - Format conversion
 * - Error handling and retry logic
 *
 * @package AevovRuntime
 * @since 1.0.0
 */

class ImageEngineAdapter {

    /**
     * Execute image tile
     *
     * @param array $tile Tile to execute
     * @return array Execution result
     * @throws Exception If execution fails
     */
    public function execute($tile) {
        // Check if Image Engine is available
        if (!function_exists('aevov_image_generate')) {
            throw new \Exception('Image Engine not available');
        }

        $operation = $tile['operation'] ?? 'generate';

        // Execute based on operation
        switch ($operation) {
            case 'generate':
                return $this->execute_generation($tile);

            case 'upscale':
                return $this->execute_upscale($tile);

            case 'enhance':
                return $this->execute_enhancement($tile);

            case 'edit':
                return $this->execute_edit($tile);

            default:
                throw new \Exception("Unknown image operation: {$operation}");
        }
    }

    /**
     * Execute image generation
     *
     * @param array $tile Tile
     * @return array Result
     * @throws Exception If execution fails
     */
    private function execute_generation($tile) {
        $request = [
            'prompt' => $tile['prompt'] ?? '',
            'width' => $tile['width'] ?? 512,
            'height' => $tile['height'] ?? 512,
            'steps' => $tile['steps'] ?? 50,
            'cfg_scale' => $tile['cfg_scale'] ?? 7.5,
            'seed' => $tile['seed'] ?? null,
            'sampler' => $tile['sampler'] ?? 'euler_a'
        ];

        // Handle region-based generation
        if (isset($tile['region'])) {
            $request['region'] = $tile['region'];
            $request['full_width'] = $tile['full_width'] ?? 512;
            $request['full_height'] = $tile['full_height'] ?? 512;
        }

        // Add optimization hints
        if (isset($tile['_aevrt_optimized'])) {
            $request['_aevrt_optimized'] = true;
        }

        $response = aevov_image_generate($request);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        return $this->normalize_image_response($response, $tile);
    }

    /**
     * Execute image upscaling
     *
     * @param array $tile Tile
     * @return array Result
     * @throws Exception If execution fails
     */
    private function execute_upscale($tile) {
        if (!function_exists('aevov_image_upscale')) {
            throw new \Exception('Image upscaling not available');
        }

        $request = [
            'image' => $tile['image'] ?? '',
            'scale' => $tile['scale'] ?? 2,
            'model' => $tile['upscale_model'] ?? 'RealESRGAN_x4plus'
        ];

        // Handle region-based upscaling
        if (isset($tile['region'])) {
            $request['region'] = $tile['region'];
        }

        $response = aevov_image_upscale($request);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        return $this->normalize_image_response($response, $tile);
    }

    /**
     * Execute image enhancement
     *
     * @param array $tile Tile
     * @return array Result
     * @throws Exception If execution fails
     */
    private function execute_enhancement($tile) {
        if (!function_exists('aevov_image_enhance')) {
            throw new \Exception('Image enhancement not available');
        }

        $request = [
            'image' => $tile['image'] ?? '',
            'enhancement_type' => $tile['enhancement_type'] ?? 'auto',
            'strength' => $tile['strength'] ?? 0.5
        ];

        // Handle region-based enhancement
        if (isset($tile['region'])) {
            $request['region'] = $tile['region'];
        }

        $response = aevov_image_enhance($request);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        return $this->normalize_image_response($response, $tile);
    }

    /**
     * Execute image editing
     *
     * @param array $tile Tile
     * @return array Result
     * @throws Exception If execution fails
     */
    private function execute_edit($tile) {
        if (!function_exists('aevov_image_edit')) {
            throw new \Exception('Image editing not available');
        }

        $request = [
            'image' => $tile['image'] ?? '',
            'mask' => $tile['mask'] ?? null,
            'prompt' => $tile['prompt'] ?? '',
            'strength' => $tile['strength'] ?? 0.8
        ];

        $response = aevov_image_edit($request);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        return $this->normalize_image_response($response, $tile);
    }

    /**
     * Normalize image response
     *
     * @param array $response Response from engine
     * @param array $tile Original tile
     * @return array Normalized response
     */
    private function normalize_image_response($response, $tile) {
        $normalized = [
            'type' => 'image',
            'raw_response' => $response
        ];

        // Extract image data
        if (isset($response['image'])) {
            $normalized['image'] = $response['image'];
        } elseif (isset($response['images'][0])) {
            $normalized['image'] = $response['images'][0];
        } elseif (isset($response['data'][0])) {
            $normalized['image'] = $response['data'][0];
        }

        // Add region info if applicable
        if (isset($tile['region'])) {
            $normalized['region'] = $tile['region'];
        }

        // Add metadata
        if (isset($response['metadata'])) {
            $normalized['metadata'] = $response['metadata'];
        }

        return $normalized;
    }

    /**
     * Stitch image regions together
     *
     * @param array $regions Array of region results
     * @param int $full_width Full image width
     * @param int $full_height Full image height
     * @return array Stitched image
     */
    public function stitch_regions($regions, $full_width, $full_height) {
        // This would use image processing libraries to stitch regions
        // For now, return metadata about stitching operation

        return [
            'type' => 'stitched_image',
            'width' => $full_width,
            'height' => $full_height,
            'regions' => $regions,
            'requires_processing' => true
        ];
    }

    /**
     * Estimate request cost
     *
     * @param array $tile Tile
     * @return float Estimated cost
     */
    public function estimate_cost($tile) {
        $operation = $tile['operation'] ?? 'generate';
        $width = $tile['width'] ?? 512;
        $height = $tile['height'] ?? 512;
        $steps = $tile['steps'] ?? 50;

        $pixels = $width * $height;
        $megapixels = $pixels / 1000000;

        // Base costs by operation
        $base_costs = [
            'generate' => 0.02,
            'upscale' => 0.015,
            'enhance' => 0.01,
            'edit' => 0.025
        ];

        $base_cost = $base_costs[$operation] ?? 0.02;

        // Adjust for image size
        $size_multiplier = $megapixels / 0.25; // 0.25 MP baseline (512x512)

        // Adjust for steps (for generation)
        $steps_multiplier = 1.0;
        if ($operation === 'generate') {
            $steps_multiplier = $steps / 50; // 50 steps baseline
        }

        return $base_cost * $size_multiplier * $steps_multiplier;
    }
}
