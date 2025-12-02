<?php
/**
 * Music Engine Adapter - Interfaces with Aevov Music Engine
 *
 * Provides optimized integration with Music Engine:
 * - Request formatting and optimization
 * - Segment-based music generation
 * - Audio concatenation for tiled generation
 * - Format conversion
 * - Error handling and retry logic
 *
 * @package AevovRuntime
 * @since 1.0.0
 */

class MusicEngineAdapter {

    /**
     * Execute music tile
     *
     * @param array $tile Tile to execute
     * @return array Execution result
     * @throws Exception If execution fails
     */
    public function execute($tile) {
        // Check if Music Engine is available
        if (!function_exists('aevov_music_generate')) {
            throw new \Exception('Music Engine not available');
        }

        $operation = $tile['operation'] ?? 'generate';

        // Execute based on operation
        switch ($operation) {
            case 'generate':
                return $this->execute_generation($tile);

            case 'synthesize':
                return $this->execute_synthesis($tile);

            case 'transform':
                return $this->execute_transformation($tile);

            default:
                throw new \Exception("Unknown music operation: {$operation}");
        }
    }

    /**
     * Execute music generation
     *
     * @param array $tile Tile
     * @return array Result
     * @throws Exception If execution fails
     */
    private function execute_generation($tile) {
        $request = [
            'prompt' => $tile['prompt'] ?? '',
            'duration' => $tile['duration'] ?? 30,
            'model' => $tile['model'] ?? 'musicgen-medium',
            'temperature' => $tile['temperature'] ?? 1.0,
            'top_k' => $tile['top_k'] ?? 250,
            'top_p' => $tile['top_p'] ?? 0.0,
            'cfg_coef' => $tile['cfg_coef'] ?? 3.0,
            'sample_rate' => $tile['sample_rate'] ?? 44100,
            'format' => $tile['format'] ?? 'mp3'
        ];

        // Handle segment-based generation
        if (isset($tile['segment_index'])) {
            $request['segment_index'] = $tile['segment_index'];
            $request['start_time'] = $tile['start_time'] ?? 0;

            // Add context from previous segment for continuity
            if (isset($tile['dependency_context']) && !empty($tile['dependency_context'])) {
                $previous_segment = end($tile['dependency_context']);

                if (is_array($previous_segment) && isset($previous_segment['audio_data'])) {
                    $request['previous_segment'] = $previous_segment['audio_data'];
                } elseif (is_string($previous_segment)) {
                    $request['previous_segment'] = $previous_segment;
                }
            }
        }

        // Add optimization hints
        if (isset($tile['_aevrt_optimized'])) {
            $request['_aevrt_optimized'] = true;
        }

        $response = aevov_music_generate($request);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        return $this->normalize_music_response($response, $tile);
    }

    /**
     * Execute music synthesis
     *
     * @param array $tile Tile
     * @return array Result
     * @throws Exception If execution fails
     */
    private function execute_synthesis($tile) {
        if (!function_exists('aevov_music_synthesize')) {
            throw new \Exception('Music synthesis not available');
        }

        $request = [
            'notes' => $tile['notes'] ?? [],
            'instrument' => $tile['instrument'] ?? 'piano',
            'duration' => $tile['duration'] ?? 30,
            'tempo' => $tile['tempo'] ?? 120,
            'sample_rate' => $tile['sample_rate'] ?? 44100
        ];

        $response = aevov_music_synthesize($request);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        return $this->normalize_music_response($response, $tile);
    }

    /**
     * Execute music transformation
     *
     * @param array $tile Tile
     * @return array Result
     * @throws Exception If execution fails
     */
    private function execute_transformation($tile) {
        if (!function_exists('aevov_music_transform')) {
            throw new \Exception('Music transformation not available');
        }

        $request = [
            'audio' => $tile['audio'] ?? '',
            'transformation' => $tile['transformation'] ?? 'remix',
            'target_style' => $tile['target_style'] ?? null,
            'strength' => $tile['strength'] ?? 0.7
        ];

        $response = aevov_music_transform($request);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        return $this->normalize_music_response($response, $tile);
    }

    /**
     * Normalize music response
     *
     * @param array $response Response from engine
     * @param array $tile Original tile
     * @return array Normalized response
     */
    private function normalize_music_response($response, $tile) {
        $normalized = [
            'type' => 'audio',
            'raw_response' => $response
        ];

        // Extract audio data
        if (isset($response['audio'])) {
            $normalized['audio_data'] = $response['audio'];
        } elseif (isset($response['audio_url'])) {
            $normalized['audio_url'] = $response['audio_url'];
        } elseif (isset($response['data'])) {
            $normalized['audio_data'] = $response['data'];
        }

        // Add segment info if applicable
        if (isset($tile['segment_index'])) {
            $normalized['segment_index'] = $tile['segment_index'];
            $normalized['start_time'] = $tile['start_time'] ?? 0;
            $normalized['duration'] = $tile['duration'] ?? 5;
        }

        // Add metadata
        if (isset($response['metadata'])) {
            $normalized['metadata'] = $response['metadata'];
        }

        // Add format info
        $normalized['format'] = $tile['format'] ?? 'mp3';
        $normalized['sample_rate'] = $tile['sample_rate'] ?? 44100;

        return $normalized;
    }

    /**
     * Concatenate audio segments
     *
     * @param array $segments Array of segment results
     * @return array Concatenated audio
     */
    public function concatenate_segments($segments) {
        // Sort segments by index
        usort($segments, function($a, $b) {
            return ($a['segment_index'] ?? 0) <=> ($b['segment_index'] ?? 0);
        });

        // This would use audio processing libraries to concatenate
        // For now, return metadata about concatenation operation

        return [
            'type' => 'concatenated_audio',
            'segments' => $segments,
            'total_duration' => array_sum(array_column($segments, 'duration')),
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
        $duration = $tile['duration'] ?? 30;
        $model = $tile['model'] ?? 'musicgen-medium';

        // Base costs per second
        $model_costs = [
            'musicgen-large' => 0.005,
            'musicgen-medium' => 0.003,
            'musicgen-small' => 0.001
        ];

        $cost_per_second = $model_costs[$model] ?? 0.003;

        // Adjust for operation
        $operation_multiplier = [
            'generate' => 1.0,
            'synthesize' => 0.5,
            'transform' => 0.7
        ];

        $multiplier = $operation_multiplier[$operation] ?? 1.0;

        return $duration * $cost_per_second * $multiplier;
    }
}
