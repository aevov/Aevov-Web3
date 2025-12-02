<?php
/**
 * Inference Engine - Executes AI inference tasks
 *
 * Interfaces with Aevov AI engines to execute tiles:
 * - Language Engine for text generation/completion
 * - Image Engine for image generation/processing
 * - Music Engine for audio generation/processing
 * - Handles model loading, caching, and warm-up
 *
 * @package AevovRuntime
 * @since 1.0.0
 */

namespace AevovRuntime;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class InferenceEngine
 */
class InferenceEngine {

    /**
     * Model cache
     *
     * @var array
     */
    private $model_cache = [];

    /**
     * Prefetch queue
     *
     * @var array
     */
    private $prefetch_queue = [];

    /**
     * Engine adapters
     *
     * @var array
     */
    private $adapters = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->initialize_adapters();
    }

    /**
     * Initialize engine adapters
     */
    private function initialize_adapters() {
        // Language Engine adapter
        if (class_exists('LanguageEngineAdapter')) {
            $this->adapters['language'] = new \LanguageEngineAdapter();
        }

        // Image Engine adapter
        if (class_exists('ImageEngineAdapter')) {
            $this->adapters['image'] = new \ImageEngineAdapter();
        }

        // Music Engine adapter
        if (class_exists('MusicEngineAdapter')) {
            $this->adapters['music'] = new \MusicEngineAdapter();
        }
    }

    /**
     * Execute tile
     *
     * @param array $tile Tile to execute
     * @return mixed Execution output
     * @throws \Exception If execution fails
     */
    public function execute_tile($tile) {
        $type = $tile['type'] ?? 'language';

        // Check if adapter exists
        if (!isset($this->adapters[$type])) {
            // Fallback to direct engine call
            return $this->execute_tile_direct($tile);
        }

        // Execute through adapter
        return $this->adapters[$type]->execute($tile);
    }

    /**
     * Execute tile directly (without adapter)
     *
     * @param array $tile Tile to execute
     * @return mixed Execution output
     * @throws \Exception If execution fails
     */
    private function execute_tile_direct($tile) {
        $type = $tile['type'] ?? 'language';

        switch ($type) {
            case 'language':
                return $this->execute_language_tile($tile);

            case 'image':
                return $this->execute_image_tile($tile);

            case 'music':
                return $this->execute_music_tile($tile);

            default:
                throw new \Exception("Unknown tile type: {$type}");
        }
    }

    /**
     * Execute language tile
     *
     * @param array $tile Language tile
     * @return array Language generation result
     * @throws \Exception If execution fails
     */
    private function execute_language_tile($tile) {
        $input = $tile['input'] ?? '';
        $model = $tile['model'] ?? 'gpt-3.5-turbo';

        // Check if Language Engine is available
        if (!function_exists('aevov_language_generate')) {
            throw new \Exception('Language Engine not available');
        }

        // Prepare context from dependencies
        $context = '';
        if (isset($tile['dependency_context']) && !empty($tile['dependency_context'])) {
            foreach ($tile['dependency_context'] as $dep_output) {
                if (is_string($dep_output)) {
                    $context .= $dep_output . "\n";
                } elseif (is_array($dep_output) && isset($dep_output['text'])) {
                    $context .= $dep_output['text'] . "\n";
                }
            }
        }

        // Build request
        $request = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful assistant.'
                ]
            ],
            'max_tokens' => $tile['max_tokens'] ?? null,
            'temperature' => $tile['temperature'] ?? 0.7,
            'stream' => $tile['streaming'] ?? false
        ];

        // Add context if available
        if ($context) {
            $request['messages'][] = [
                'role' => 'assistant',
                'content' => $context
            ];
        }

        // Add user input
        $request['messages'][] = [
            'role' => 'user',
            'content' => $input
        ];

        // Execute through Language Engine
        $response = aevov_language_generate($request);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        return $response;
    }

    /**
     * Execute image tile
     *
     * @param array $tile Image tile
     * @return array Image generation result
     * @throws \Exception If execution fails
     */
    private function execute_image_tile($tile) {
        $prompt = $tile['prompt'] ?? '';
        $operation = $tile['operation'] ?? 'generate';

        // Check if Image Engine is available
        if (!function_exists('aevov_image_generate')) {
            throw new \Exception('Image Engine not available');
        }

        // Build request based on operation
        if ($operation === 'generate') {
            $request = [
                'prompt' => $prompt,
                'width' => $tile['width'] ?? 512,
                'height' => $tile['height'] ?? 512,
                'steps' => $tile['steps'] ?? 50,
                'cfg_scale' => $tile['cfg_scale'] ?? 7.5
            ];

            // Handle region-based generation
            if (isset($tile['region'])) {
                $request['region'] = $tile['region'];
                $request['full_width'] = $tile['full_width'] ?? 512;
                $request['full_height'] = $tile['full_height'] ?? 512;
            }

            $response = aevov_image_generate($request);

        } elseif ($operation === 'upscale') {
            $request = [
                'image' => $tile['image'] ?? '',
                'scale' => $tile['scale'] ?? 2,
                'region' => $tile['region'] ?? null
            ];

            if (function_exists('aevov_image_upscale')) {
                $response = aevov_image_upscale($request);
            } else {
                throw new \Exception('Image upscaling not available');
            }

        } elseif ($operation === 'enhance') {
            $request = [
                'image' => $tile['image'] ?? '',
                'enhancement_type' => $tile['enhancement_type'] ?? 'auto',
                'region' => $tile['region'] ?? null
            ];

            if (function_exists('aevov_image_enhance')) {
                $response = aevov_image_enhance($request);
            } else {
                throw new \Exception('Image enhancement not available');
            }

        } else {
            throw new \Exception("Unknown image operation: {$operation}");
        }

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        return $response;
    }

    /**
     * Execute music tile
     *
     * @param array $tile Music tile
     * @return array Music generation result
     * @throws \Exception If execution fails
     */
    private function execute_music_tile($tile) {
        $prompt = $tile['prompt'] ?? '';
        $operation = $tile['operation'] ?? 'generate';

        // Check if Music Engine is available
        if (!function_exists('aevov_music_generate')) {
            throw new \Exception('Music Engine not available');
        }

        // Build request
        $request = [
            'prompt' => $prompt,
            'duration' => $tile['duration'] ?? 30,
            'format' => $tile['format'] ?? 'mp3',
            'sample_rate' => $tile['sample_rate'] ?? 44100
        ];

        // Handle segment-based generation
        if (isset($tile['segment_index'])) {
            $request['segment_index'] = $tile['segment_index'];
            $request['start_time'] = $tile['start_time'] ?? 0;

            // Add context from previous segment
            if (isset($tile['dependency_context']) && !empty($tile['dependency_context'])) {
                $request['previous_segment'] = end($tile['dependency_context']);
            }
        }

        $response = aevov_music_generate($request);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        return $response;
    }

    /**
     * Prefetch tile (warm up for upcoming execution)
     *
     * @param array $tile Tile to prefetch
     */
    public function prefetch_tile($tile) {
        $type = $tile['type'] ?? 'language';
        $model = $tile['model'] ?? null;

        // Add to prefetch queue
        $this->prefetch_queue[] = [
            'type' => $type,
            'model' => $model,
            'tile_id' => $tile['tile_id'] ?? null,
            'queued_at' => microtime(true)
        ];

        // Execute prefetch operations
        $this->execute_prefetch($type, $model);
    }

    /**
     * Execute prefetch operations
     *
     * @param string $type Task type
     * @param string|null $model Model name
     */
    private function execute_prefetch($type, $model) {
        // Warm up model cache
        if ($model && !isset($this->model_cache[$model])) {
            $this->warm_up_model($type, $model);
        }

        // Additional prefetch operations based on type
        switch ($type) {
            case 'language':
                $this->prefetch_language_resources($model);
                break;

            case 'image':
                $this->prefetch_image_resources($model);
                break;

            case 'music':
                $this->prefetch_music_resources($model);
                break;
        }
    }

    /**
     * Warm up model cache
     *
     * @param string $type Task type
     * @param string $model Model name
     */
    private function warm_up_model($type, $model) {
        // Mark model as cached
        $this->model_cache[$model] = [
            'type' => $type,
            'loaded_at' => microtime(true),
            'status' => 'warming_up'
        ];

        // Notify engine to load model
        $this->notify_engine_load_model($type, $model);

        // Update status
        $this->model_cache[$model]['status'] = 'ready';
    }

    /**
     * Notify engine to load model
     *
     * @param string $type Task type
     * @param string $model Model name
     */
    private function notify_engine_load_model($type, $model) {
        // Hook into engine-specific model loading
        switch ($type) {
            case 'language':
                if (function_exists('aevov_language_load_model')) {
                    aevov_language_load_model($model);
                }
                break;

            case 'image':
                if (function_exists('aevov_image_load_model')) {
                    aevov_image_load_model($model);
                }
                break;

            case 'music':
                if (function_exists('aevov_music_load_model')) {
                    aevov_music_load_model($model);
                }
                break;
        }

        // Fire action for custom implementations
        do_action('aevrt_load_model', $type, $model);
    }

    /**
     * Prefetch language resources
     *
     * @param string|null $model Model name
     */
    private function prefetch_language_resources($model) {
        // Prefetch tokenizer
        if (function_exists('aevov_language_load_tokenizer')) {
            aevov_language_load_tokenizer($model);
        }

        // Prefetch embeddings
        if (function_exists('aevov_language_load_embeddings')) {
            aevov_language_load_embeddings($model);
        }

        // Fire action for custom prefetch
        do_action('aevrt_prefetch_language', $model);
    }

    /**
     * Prefetch image resources
     *
     * @param string|null $model Model name
     */
    private function prefetch_image_resources($model) {
        // Prefetch VAE
        if (function_exists('aevov_image_load_vae')) {
            aevov_image_load_vae($model);
        }

        // Prefetch CLIP
        if (function_exists('aevov_image_load_clip')) {
            aevov_image_load_clip($model);
        }

        // Fire action for custom prefetch
        do_action('aevrt_prefetch_image', $model);
    }

    /**
     * Prefetch music resources
     *
     * @param string|null $model Model name
     */
    private function prefetch_music_resources($model) {
        // Prefetch audio encoder
        if (function_exists('aevov_music_load_encoder')) {
            aevov_music_load_encoder($model);
        }

        // Fire action for custom prefetch
        do_action('aevrt_prefetch_music', $model);
    }

    /**
     * Get model cache status
     *
     * @param string $model Model name
     * @return array|null Cache status or null
     */
    public function get_model_cache_status($model) {
        return $this->model_cache[$model] ?? null;
    }

    /**
     * Clear model cache
     *
     * @param string|null $model Model name (null for all)
     */
    public function clear_model_cache($model = null) {
        if ($model === null) {
            $this->model_cache = [];
        } else {
            unset($this->model_cache[$model]);
        }

        // Fire action for custom cleanup
        do_action('aevrt_clear_model_cache', $model);
    }

    /**
     * Get prefetch queue status
     *
     * @return array Prefetch queue
     */
    public function get_prefetch_queue() {
        return $this->prefetch_queue;
    }

    /**
     * Clear prefetch queue
     */
    public function clear_prefetch_queue() {
        $this->prefetch_queue = [];
    }

    /**
     * Get engine statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        return [
            'models_cached' => count($this->model_cache),
            'prefetch_queue_size' => count($this->prefetch_queue),
            'adapters_loaded' => array_keys($this->adapters),
            'cache_details' => $this->model_cache
        ];
    }
}
