<?php
/**
 * Task Executor - Executes scheduled tiles
 *
 * Implements tile execution with:
 * - Parallel tile execution within stages
 * - Prefetch optimization for next stage
 * - AevIP distributed execution
 * - Result aggregation and merging
 * - Error handling and retry logic
 *
 * @package AevovRuntime
 * @since 1.0.0
 */

namespace AevovRuntime;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TaskExecutor
 */
class TaskExecutor {

    /**
     * Maximum concurrent tile executions
     */
    const MAX_CONCURRENT = 8;

    /**
     * Retry attempts for failed tiles
     */
    const MAX_RETRIES = 3;

    /**
     * Inference engine instance
     *
     * @var InferenceEngine
     */
    private $inference_engine;

    /**
     * AevIP coordinator instance
     *
     * @var mixed
     */
    private $aevip_coordinator;

    /**
     * Active executions
     *
     * @var array
     */
    private $active_executions = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->inference_engine = new InferenceEngine();
    }

    /**
     * Set AevIP coordinator
     *
     * @param mixed $coordinator AevIP coordinator
     */
    public function set_aevip_coordinator($coordinator) {
        $this->aevip_coordinator = $coordinator;
    }

    /**
     * Execute schedule
     *
     * @param array $schedule Schedule to execute
     * @return array Execution result
     */
    public function execute_schedule($schedule) {
        $start_time = microtime(true);
        $schedule_id = $schedule['schedule_id'];

        $result = [
            'schedule_id' => $schedule_id,
            'success' => true,
            'tile_results' => [],
            'aggregated_result' => null,
            'actual_latency' => 0,
            'estimated_latency' => $schedule['estimated_latency'],
            'started_at' => $start_time,
            'errors' => []
        ];

        // Store active execution
        $this->active_executions[$schedule_id] = [
            'schedule' => $schedule,
            'result' => &$result,
            'stage_index' => 0
        ];

        try {
            // Execute each stage sequentially
            foreach ($schedule['stages'] as $stage_index => $stage) {
                $this->active_executions[$schedule_id]['stage_index'] = $stage_index;

                $stage_result = $this->execute_stage($schedule, $stage, $result);

                // Merge stage results
                foreach ($stage_result['tile_results'] as $tile_index => $tile_result) {
                    $result['tile_results'][$tile_index] = $tile_result;

                    if (!$tile_result['success']) {
                        $result['errors'][] = [
                            'stage' => $stage_index,
                            'tile' => $tile_index,
                            'error' => $tile_result['error']
                        ];
                    }
                }

                // Prefetch next stage if enabled
                if ($schedule['prefetch_enabled'] ?? false) {
                    if (isset($schedule['prefetch_stages'][$stage_index])) {
                        $next_stage_index = $schedule['prefetch_stages'][$stage_index];
                        if (isset($schedule['stages'][$next_stage_index])) {
                            $this->prefetch_stage($schedule, $schedule['stages'][$next_stage_index]);
                        }
                    }
                }
            }

            // Aggregate results from all tiles
            $result['aggregated_result'] = $this->aggregate_tile_results($schedule, $result['tile_results']);

            // Calculate actual latency
            $result['actual_latency'] = (microtime(true) - $start_time) * 1000; // milliseconds

            // Determine success
            $result['success'] = empty($result['errors']);

        } catch (\Exception $e) {
            $result['success'] = false;
            $result['errors'][] = [
                'type' => 'execution_exception',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }

        // Cleanup
        unset($this->active_executions[$schedule_id]);

        // Log execution metrics
        $this->log_execution_metrics($schedule, $result);

        return $result;
    }

    /**
     * Execute a single stage
     *
     * @param array $schedule Schedule
     * @param array $stage Stage
     * @param array $previous_results Previous tile results
     * @return array Stage execution result
     */
    private function execute_stage($schedule, $stage, $previous_results) {
        $stage_result = [
            'stage_index' => $stage['stage_index'],
            'tile_results' => [],
            'started_at' => microtime(true)
        ];

        $tiles_to_execute = [];

        // Prepare tiles for execution
        foreach ($stage['tiles'] as $tile_index) {
            $tile = $schedule['tiles'][$tile_index];

            // Check if dependencies are met
            $dependencies_met = true;
            foreach ($tile['depends_on'] ?? [] as $dep_index) {
                if (!isset($previous_results['tile_results'][$dep_index]) ||
                    !$previous_results['tile_results'][$dep_index]['success']) {
                    $dependencies_met = false;
                    break;
                }
            }

            if (!$dependencies_met) {
                $stage_result['tile_results'][$tile_index] = [
                    'success' => false,
                    'error' => 'Dependencies not met',
                    'tile_index' => $tile_index
                ];
                continue;
            }

            // Inject dependency results into tile context
            $tile_with_context = $this->inject_dependency_context($tile, $previous_results['tile_results']);

            $tiles_to_execute[$tile_index] = $tile_with_context;
        }

        // Execute tiles (parallel within stage)
        if ($schedule['use_aevip'] && !empty($schedule['node_assignments'])) {
            // Distributed execution via AevIP
            $execution_results = $this->execute_tiles_distributed($tiles_to_execute, $schedule['node_assignments']);
        } else {
            // Local parallel execution
            $execution_results = $this->execute_tiles_local($tiles_to_execute);
        }

        // Merge execution results
        foreach ($execution_results as $tile_index => $exec_result) {
            $stage_result['tile_results'][$tile_index] = $exec_result;
        }

        $stage_result['completed_at'] = microtime(true);
        $stage_result['latency'] = ($stage_result['completed_at'] - $stage_result['started_at']) * 1000;

        return $stage_result;
    }

    /**
     * Execute tiles locally in parallel
     *
     * @param array $tiles Tiles to execute
     * @return array Execution results
     */
    private function execute_tiles_local($tiles) {
        $results = [];

        // PHP doesn't have true threading, but we can simulate parallel execution
        // by executing tiles in batches and using async patterns if available

        $batches = array_chunk($tiles, self::MAX_CONCURRENT, true);

        foreach ($batches as $batch) {
            // Execute batch (simulated parallel)
            foreach ($batch as $tile_index => $tile) {
                $results[$tile_index] = $this->execute_single_tile($tile, $tile_index);
            }
        }

        return $results;
    }

    /**
     * Execute tiles distributed across AevIP nodes
     *
     * @param array $tiles Tiles to execute
     * @param array $node_assignments Node assignments
     * @return array Execution results
     */
    private function execute_tiles_distributed($tiles, $node_assignments) {
        if (!$this->aevip_coordinator) {
            // Fallback to local execution
            return $this->execute_tiles_local($tiles);
        }

        $results = [];

        // Group tiles by node
        $tiles_by_node = [];
        foreach ($tiles as $tile_index => $tile) {
            if (isset($node_assignments[$tile_index])) {
                $node_id = $node_assignments[$tile_index]['node_id'];
                if (!isset($tiles_by_node[$node_id])) {
                    $tiles_by_node[$node_id] = [];
                }
                $tiles_by_node[$node_id][$tile_index] = $tile;
            } else {
                // No assignment - execute locally
                $results[$tile_index] = $this->execute_single_tile($tile, $tile_index);
            }
        }

        // Send tiles to nodes
        foreach ($tiles_by_node as $node_id => $node_tiles) {
            $node_results = $this->aevip_coordinator->execute_tiles_on_node($node_id, $node_tiles);

            foreach ($node_results as $tile_index => $result) {
                $results[$tile_index] = $result;
            }
        }

        return $results;
    }

    /**
     * Execute single tile
     *
     * @param array $tile Tile to execute
     * @param int $tile_index Tile index
     * @return array Execution result
     */
    private function execute_single_tile($tile, $tile_index) {
        $start_time = microtime(true);

        $result = [
            'tile_index' => $tile_index,
            'tile_id' => $tile['tile_id'] ?? null,
            'success' => false,
            'output' => null,
            'error' => null,
            'latency' => 0,
            'retries' => 0
        ];

        $attempts = 0;
        $max_retries = self::MAX_RETRIES;

        while ($attempts <= $max_retries) {
            try {
                // Execute tile through inference engine
                $output = $this->inference_engine->execute_tile($tile);

                $result['success'] = true;
                $result['output'] = $output;
                break;

            } catch (\Exception $e) {
                $result['error'] = $e->getMessage();
                $attempts++;

                if ($attempts > $max_retries) {
                    $result['success'] = false;
                    break;
                }

                // Exponential backoff
                usleep(100000 * pow(2, $attempts - 1)); // 100ms, 200ms, 400ms
            }
        }

        $result['retries'] = $attempts - 1;
        $result['latency'] = (microtime(true) - $start_time) * 1000;

        return $result;
    }

    /**
     * Prefetch next stage
     *
     * @param array $schedule Schedule
     * @param array $stage Stage to prefetch
     */
    private function prefetch_stage($schedule, $stage) {
        // Prefetch: prepare resources for next stage
        // This could include:
        // - Warming up model caches
        // - Pre-allocating memory
        // - Pre-loading data

        foreach ($stage['tiles'] as $tile_index) {
            $tile = $schedule['tiles'][$tile_index];

            // Notify inference engine to prepare for this tile
            $this->inference_engine->prefetch_tile($tile);
        }
    }

    /**
     * Inject dependency context into tile
     *
     * @param array $tile Tile
     * @param array $tile_results Previous tile results
     * @return array Tile with context
     */
    private function inject_dependency_context($tile, $tile_results) {
        $context = [];

        foreach ($tile['depends_on'] ?? [] as $dep_index) {
            if (isset($tile_results[$dep_index]) && $tile_results[$dep_index]['success']) {
                $context[] = $tile_results[$dep_index]['output'];
            }
        }

        if (!empty($context)) {
            $tile['dependency_context'] = $context;
        }

        return $tile;
    }

    /**
     * Aggregate tile results
     *
     * @param array $schedule Schedule
     * @param array $tile_results Tile results
     * @return mixed Aggregated result
     */
    private function aggregate_tile_results($schedule, $tile_results) {
        // Get first tile to determine task type
        if (empty($schedule['tiles'])) {
            return null;
        }

        $first_tile = $schedule['tiles'][0];
        $type = $first_tile['type'] ?? 'language';

        switch ($type) {
            case 'language':
                return $this->aggregate_language_results($schedule['tiles'], $tile_results);

            case 'image':
                return $this->aggregate_image_results($schedule['tiles'], $tile_results);

            case 'music':
                return $this->aggregate_music_results($schedule['tiles'], $tile_results);

            default:
                return $this->aggregate_generic_results($schedule['tiles'], $tile_results);
        }
    }

    /**
     * Aggregate language results
     *
     * @param array $tiles Tiles
     * @param array $tile_results Tile results
     * @return string Aggregated text
     */
    private function aggregate_language_results($tiles, $tile_results) {
        $aggregated = '';

        // Sort by tile index to maintain order
        ksort($tile_results);

        foreach ($tile_results as $tile_index => $result) {
            if ($result['success'] && isset($result['output'])) {
                $output = $result['output'];

                // Extract text from various response formats
                if (is_array($output)) {
                    if (isset($output['choices'][0]['message']['content'])) {
                        $aggregated .= $output['choices'][0]['message']['content'];
                    } elseif (isset($output['choices'][0]['text'])) {
                        $aggregated .= $output['choices'][0]['text'];
                    } elseif (isset($output['text'])) {
                        $aggregated .= $output['text'];
                    }
                } else {
                    $aggregated .= $output;
                }
            }
        }

        return $aggregated;
    }

    /**
     * Aggregate image results
     *
     * @param array $tiles Tiles
     * @param array $tile_results Tile results
     * @return array Aggregated images
     */
    private function aggregate_image_results($tiles, $tile_results) {
        $images = [];

        // Check if tiles have regions (need stitching)
        $has_regions = false;
        foreach ($tiles as $tile) {
            if (isset($tile['region'])) {
                $has_regions = true;
                break;
            }
        }

        if ($has_regions) {
            // Stitch image regions together
            return $this->stitch_image_regions($tiles, $tile_results);
        } else {
            // Just collect all images
            foreach ($tile_results as $result) {
                if ($result['success'] && isset($result['output'])) {
                    $images[] = $result['output'];
                }
            }
        }

        return $images;
    }

    /**
     * Aggregate music results
     *
     * @param array $tiles Tiles
     * @param array $tile_results Tile results
     * @return array Aggregated audio
     */
    private function aggregate_music_results($tiles, $tile_results) {
        $segments = [];

        // Sort by segment index
        ksort($tile_results);

        foreach ($tile_results as $tile_index => $result) {
            if ($result['success'] && isset($result['output'])) {
                $segments[] = [
                    'index' => $tiles[$tile_index]['segment_index'] ?? $tile_index,
                    'data' => $result['output']
                ];
            }
        }

        // Concatenate audio segments
        return [
            'type' => 'audio',
            'segments' => $segments,
            'requires_stitching' => true
        ];
    }

    /**
     * Aggregate generic results
     *
     * @param array $tiles Tiles
     * @param array $tile_results Tile results
     * @return array All results
     */
    private function aggregate_generic_results($tiles, $tile_results) {
        $results = [];

        foreach ($tile_results as $tile_index => $result) {
            if ($result['success']) {
                $results[] = $result['output'];
            }
        }

        return $results;
    }

    /**
     * Stitch image regions
     *
     * @param array $tiles Tiles
     * @param array $tile_results Tile results
     * @return array Stitched image metadata
     */
    private function stitch_image_regions($tiles, $tile_results) {
        // Find full dimensions
        $full_width = 0;
        $full_height = 0;

        foreach ($tiles as $tile) {
            if (isset($tile['full_width'])) {
                $full_width = max($full_width, $tile['full_width']);
            }
            if (isset($tile['full_height'])) {
                $full_height = max($full_height, $tile['full_height']);
            }
        }

        // Collect regions
        $regions = [];
        foreach ($tile_results as $tile_index => $result) {
            if ($result['success'] && isset($tiles[$tile_index]['region'])) {
                $regions[] = [
                    'region' => $tiles[$tile_index]['region'],
                    'image_data' => $result['output']
                ];
            }
        }

        return [
            'type' => 'stitched_image',
            'width' => $full_width,
            'height' => $full_height,
            'regions' => $regions,
            'requires_stitching' => true
        ];
    }

    /**
     * Log execution metrics
     *
     * @param array $schedule Schedule
     * @param array $result Execution result
     */
    private function log_execution_metrics($schedule, $result) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aevov_runtime_metrics';

        $wpdb->insert($table_name, [
            'schedule_id' => $schedule['schedule_id'],
            'task_type' => $schedule['tiles'][0]['type'] ?? 'unknown',
            'num_tiles' => count($schedule['tiles']),
            'num_stages' => count($schedule['stages']),
            'estimated_latency' => $schedule['estimated_latency'],
            'actual_latency' => $result['actual_latency'],
            'success' => $result['success'] ? 1 : 0,
            'used_aevip' => $schedule['use_aevip'] ? 1 : 0,
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Get active execution status
     *
     * @param string $schedule_id Schedule ID
     * @return array|null Status or null
     */
    public function get_execution_status($schedule_id) {
        if (!isset($this->active_executions[$schedule_id])) {
            return null;
        }

        $execution = $this->active_executions[$schedule_id];

        return [
            'schedule_id' => $schedule_id,
            'current_stage' => $execution['stage_index'],
            'total_stages' => count($execution['schedule']['stages']),
            'progress' => ($execution['stage_index'] / count($execution['schedule']['stages'])) * 100,
            'tiles_completed' => count($execution['result']['tile_results']),
            'total_tiles' => count($execution['schedule']['tiles'])
        ];
    }
}
