<?php
/**
 * Tile Scheduler - Task Decomposition and Scheduling
 *
 * Implements TileRT-inspired tile-based task decomposition:
 * - Breaks large AI inference tasks into smaller parallelizable "tiles"
 * - Creates optimal execution schedules for minimal latency
 * - Supports prefetch optimization for speculative execution
 * - Integrates with AevIP for distributed multi-node processing
 *
 * Based on TileRT's tile-based scheduling approach
 *
 * @package AevovRuntime
 * @since 1.0.0
 */

namespace AevovRuntime;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class TileScheduler
 */
class TileScheduler {

    /**
     * Minimum tile size in tokens
     */
    const MIN_TILE_SIZE = 128;

    /**
     * Maximum tile size in tokens
     */
    const MAX_TILE_SIZE = 2048;

    /**
     * Optimal tile size for balanced parallelization
     */
    const OPTIMAL_TILE_SIZE = 512;

    /**
     * Priority queue for tasks
     *
     * @var array
     */
    private $priority_queue = [];

    /**
     * Active schedules
     *
     * @var array
     */
    private $active_schedules = [];

    /**
     * Latency analyzer instance
     *
     * @var LatencyAnalyzer
     */
    private $latency_analyzer;

    /**
     * Constructor
     */
    public function __construct() {
        // Latency analyzer will be injected when available
        $this->latency_analyzer = null;
    }

    /**
     * Set latency analyzer
     *
     * @param LatencyAnalyzer $analyzer Latency analyzer
     */
    public function set_latency_analyzer($analyzer) {
        $this->latency_analyzer = $analyzer;
    }

    /**
     * Decompose task into tiles
     *
     * @param array $task Task to decompose
     * @return array Array of tiles
     */
    public function decompose_task($task) {
        $tiles = [];
        $task_type = $task['type'] ?? 'language';

        switch ($task_type) {
            case 'language':
                $tiles = $this->decompose_language_task($task);
                break;

            case 'image':
                $tiles = $this->decompose_image_task($task);
                break;

            case 'music':
                $tiles = $this->decompose_music_task($task);
                break;

            default:
                // Generic decomposition
                $tiles = $this->decompose_generic_task($task);
                break;
        }

        // Add metadata to each tile
        foreach ($tiles as $i => &$tile) {
            $tile['tile_id'] = uniqid('tile_', true);
            $tile['task_id'] = $task['task_id'] ?? uniqid('task_', true);
            $tile['tile_index'] = $i;
            $tile['total_tiles'] = count($tiles);
            $tile['priority'] = $task['priority'] ?? 0;
            $tile['created_at'] = microtime(true);
        }

        return $tiles;
    }

    /**
     * Decompose language task into tiles
     *
     * @param array $task Language task
     * @return array Tiles
     */
    private function decompose_language_task($task) {
        $input = $task['input'] ?? '';
        $tiles = [];

        // Estimate token count (rough approximation: 1 token ≈ 4 chars)
        $estimated_tokens = strlen($input) / 4;

        // If small enough, single tile
        if ($estimated_tokens <= self::OPTIMAL_TILE_SIZE) {
            return [[
                'type' => 'language',
                'input' => $input,
                'model' => $task['model'] ?? 'gpt-3.5-turbo',
                'max_tokens' => $task['max_tokens'] ?? null,
                'temperature' => $task['temperature'] ?? 0.7,
                'streaming' => $task['streaming'] ?? false,
                'estimated_tokens' => $estimated_tokens
            ]];
        }

        // Split into optimal tile sizes
        // For language models, we can split by sentences or chunks
        $chunks = $this->split_text_into_chunks($input, self::OPTIMAL_TILE_SIZE * 4);

        foreach ($chunks as $chunk) {
            $tiles[] = [
                'type' => 'language',
                'input' => $chunk,
                'model' => $task['model'] ?? 'gpt-3.5-turbo',
                'max_tokens' => $task['max_tokens'] ?? null,
                'temperature' => $task['temperature'] ?? 0.7,
                'streaming' => $task['streaming'] ?? false,
                'estimated_tokens' => strlen($chunk) / 4,
                'depends_on' => [] // Will be set if sequential dependency
            ];
        }

        // For sequential tasks (chat, completion), set dependencies
        if ($task['sequential'] ?? true) {
            for ($i = 1; $i < count($tiles); $i++) {
                $tiles[$i]['depends_on'][] = $i - 1;
            }
        }

        return $tiles;
    }

    /**
     * Decompose image task into tiles
     *
     * @param array $task Image task
     * @return array Tiles
     */
    private function decompose_image_task($task) {
        $tiles = [];
        $width = $task['width'] ?? 512;
        $height = $task['height'] ?? 512;
        $operation = $task['operation'] ?? 'generate';

        // For image generation, can parallelize by regions or batch size
        if ($operation === 'generate') {
            $batch_size = $task['batch_size'] ?? 1;

            // If generating multiple images, each is a tile
            if ($batch_size > 1) {
                for ($i = 0; $i < $batch_size; $i++) {
                    $tiles[] = [
                        'type' => 'image',
                        'operation' => 'generate',
                        'prompt' => $task['prompt'] ?? '',
                        'width' => $width,
                        'height' => $height,
                        'batch_index' => $i
                    ];
                }
            } else {
                // Single image - can tile by region for large images
                if ($width > 1024 || $height > 1024) {
                    $tile_size = 512;
                    $tiles = $this->create_image_region_tiles($task, $tile_size);
                } else {
                    // Single tile for small image
                    $tiles[] = [
                        'type' => 'image',
                        'operation' => 'generate',
                        'prompt' => $task['prompt'] ?? '',
                        'width' => $width,
                        'height' => $height
                    ];
                }
            }
        } elseif ($operation === 'upscale' || $operation === 'enhance') {
            // Process by regions
            $tiles = $this->create_image_region_tiles($task, 512);
        } else {
            // Single tile for other operations
            $tiles[] = $task;
        }

        return $tiles;
    }

    /**
     * Decompose music task into tiles
     *
     * @param array $task Music task
     * @return array Tiles
     */
    private function decompose_music_task($task) {
        $tiles = [];
        $duration = $task['duration'] ?? 30; // seconds
        $operation = $task['operation'] ?? 'generate';

        // For music generation, can parallelize by time segments
        if ($duration > 10 && ($operation === 'generate' || $operation === 'synthesize')) {
            // Split into 5-second segments
            $segment_duration = 5;
            $num_segments = ceil($duration / $segment_duration);

            for ($i = 0; $i < $num_segments; $i++) {
                $tiles[] = [
                    'type' => 'music',
                    'operation' => $operation,
                    'prompt' => $task['prompt'] ?? '',
                    'start_time' => $i * $segment_duration,
                    'duration' => min($segment_duration, $duration - ($i * $segment_duration)),
                    'segment_index' => $i,
                    'depends_on' => $i > 0 ? [$i - 1] : [] // Sequential for continuity
                ];
            }
        } else {
            // Single tile for short music
            $tiles[] = $task;
        }

        return $tiles;
    }

    /**
     * Decompose generic task
     *
     * @param array $task Task
     * @return array Tiles
     */
    private function decompose_generic_task($task) {
        // If task has batch size, create tiles per batch item
        if (isset($task['batch_size']) && $task['batch_size'] > 1) {
            $tiles = [];
            for ($i = 0; $i < $task['batch_size']; $i++) {
                $tile = $task;
                $tile['batch_index'] = $i;
                unset($tile['batch_size']);
                $tiles[] = $tile;
            }
            return $tiles;
        }

        // Single tile
        return [$task];
    }

    /**
     * Create schedule from tiles
     *
     * @param array $tiles Tiles to schedule
     * @param array $options Scheduling options
     * @return array Schedule
     */
    public function create_schedule($tiles, $options = []) {
        $target_latency = $options['target_latency'] ?? 100; // ms
        $enable_prefetch = $options['enable_prefetch'] ?? true;
        $use_aevip = $options['use_aevip'] ?? false;

        $schedule = [
            'schedule_id' => uniqid('sched_', true),
            'tiles' => $tiles,
            'stages' => [],
            'estimated_latency' => 0,
            'target_latency' => $target_latency,
            'use_aevip' => $use_aevip,
            'created_at' => microtime(true)
        ];

        // Build dependency graph
        $dependency_graph = $this->build_dependency_graph($tiles);

        // Topological sort to determine execution order
        $execution_order = $this->topological_sort($dependency_graph);

        // Group tiles into parallel stages
        $schedule['stages'] = $this->create_parallel_stages($tiles, $execution_order, $dependency_graph);

        // Estimate latency for each stage
        foreach ($schedule['stages'] as $i => &$stage) {
            $stage_latency = 0;
            foreach ($stage['tiles'] as $tile_index) {
                $tile_latency = $this->estimate_tile_latency($tiles[$tile_index]);
                $stage_latency = max($stage_latency, $tile_latency); // Parallel execution
            }
            $stage['estimated_latency'] = $stage_latency;
            $schedule['estimated_latency'] += $stage_latency;
        }

        // Apply prefetch optimization if enabled
        if ($enable_prefetch && count($schedule['stages']) > 1) {
            $schedule = $this->apply_prefetch_optimization($schedule);
        }

        // Distribute across AevIP nodes if enabled
        if ($use_aevip) {
            $schedule['node_assignments'] = $this->assign_tiles_to_nodes($schedule);
        }

        // Store active schedule
        $this->active_schedules[$schedule['schedule_id']] = $schedule;

        return $schedule;
    }

    /**
     * Build dependency graph from tiles
     *
     * @param array $tiles Tiles
     * @return array Dependency graph
     */
    private function build_dependency_graph($tiles) {
        $graph = [];

        foreach ($tiles as $i => $tile) {
            $graph[$i] = [
                'tile' => $tile,
                'depends_on' => $tile['depends_on'] ?? [],
                'dependents' => []
            ];
        }

        // Build reverse edges (dependents)
        foreach ($graph as $i => $node) {
            foreach ($node['depends_on'] as $dep) {
                if (isset($graph[$dep])) {
                    $graph[$dep]['dependents'][] = $i;
                }
            }
        }

        return $graph;
    }

    /**
     * Topological sort for dependency resolution
     *
     * @param array $graph Dependency graph
     * @return array Sorted tile indices
     */
    private function topological_sort($graph) {
        $sorted = [];
        $visited = [];
        $temp_mark = [];

        $visit = function($index) use (&$visit, &$graph, &$sorted, &$visited, &$temp_mark) {
            if (isset($temp_mark[$index])) {
                // Cycle detected - shouldn't happen in valid dependency graph
                return;
            }
            if (isset($visited[$index])) {
                return;
            }

            $temp_mark[$index] = true;

            foreach ($graph[$index]['depends_on'] as $dep) {
                $visit($dep);
            }

            unset($temp_mark[$index]);
            $visited[$index] = true;
            array_unshift($sorted, $index);
        };

        foreach (array_keys($graph) as $index) {
            if (!isset($visited[$index])) {
                $visit($index);
            }
        }

        return $sorted;
    }

    /**
     * Create parallel execution stages
     *
     * @param array $tiles Tiles
     * @param array $execution_order Execution order
     * @param array $dependency_graph Dependency graph
     * @return array Stages
     */
    private function create_parallel_stages($tiles, $execution_order, $dependency_graph) {
        $stages = [];
        $tile_to_stage = [];
        $current_stage = 0;

        foreach ($execution_order as $tile_index) {
            $dependencies = $dependency_graph[$tile_index]['depends_on'];

            // Find the latest stage of any dependency
            $min_stage = 0;
            foreach ($dependencies as $dep) {
                if (isset($tile_to_stage[$dep])) {
                    $min_stage = max($min_stage, $tile_to_stage[$dep] + 1);
                }
            }

            // Assign to earliest possible stage
            $stage_index = $min_stage;

            if (!isset($stages[$stage_index])) {
                $stages[$stage_index] = [
                    'stage_index' => $stage_index,
                    'tiles' => [],
                    'parallel_count' => 0
                ];
            }

            $stages[$stage_index]['tiles'][] = $tile_index;
            $stages[$stage_index]['parallel_count']++;
            $tile_to_stage[$tile_index] = $stage_index;
        }

        return array_values($stages);
    }

    /**
     * Estimate tile execution latency
     *
     * @param array $tile Tile
     * @return float Estimated latency in milliseconds
     */
    private function estimate_tile_latency($tile) {
        $type = $tile['type'] ?? 'language';

        // Use latency analyzer if available
        if ($this->latency_analyzer) {
            return $this->latency_analyzer->predict_latency($tile);
        }

        // Fallback to heuristic estimates
        switch ($type) {
            case 'language':
                $tokens = $tile['estimated_tokens'] ?? 100;
                // Rough estimate: 10ms per token for inference
                return $tokens * 10;

            case 'image':
                $width = $tile['width'] ?? 512;
                $height = $tile['height'] ?? 512;
                $pixels = $width * $height;
                // Rough estimate: 1 second per megapixel
                return ($pixels / 1000000) * 1000;

            case 'music':
                $duration = $tile['duration'] ?? 5;
                // Rough estimate: 200ms per second of audio
                return $duration * 200;

            default:
                return 100; // Default 100ms
        }
    }

    /**
     * Apply prefetch optimization
     *
     * @param array $schedule Schedule
     * @return array Optimized schedule
     */
    private function apply_prefetch_optimization($schedule) {
        // Prefetch next stage while current stage is executing
        $schedule['prefetch_enabled'] = true;
        $schedule['prefetch_stages'] = [];

        for ($i = 0; $i < count($schedule['stages']) - 1; $i++) {
            // Next stage can be prefetched during current stage
            $schedule['prefetch_stages'][$i] = $i + 1;
        }

        // Reduce estimated latency by overlap
        if (count($schedule['stages']) > 1) {
            $overlap_reduction = 0;
            for ($i = 0; $i < count($schedule['stages']) - 1; $i++) {
                $current_latency = $schedule['stages'][$i]['estimated_latency'];
                $next_latency = $schedule['stages'][$i + 1]['estimated_latency'];
                // Can save up to 20% of next stage latency through prefetch
                $overlap_reduction += min($next_latency * 0.2, $current_latency * 0.3);
            }
            $schedule['estimated_latency'] -= $overlap_reduction;
            $schedule['prefetch_savings'] = $overlap_reduction;
        }

        return $schedule;
    }

    /**
     * Assign tiles to AevIP nodes
     *
     * @param array $schedule Schedule
     * @return array Node assignments
     */
    private function assign_tiles_to_nodes($schedule) {
        // Get available AevIP nodes from physics engine
        $nodes = $this->get_available_aevip_nodes();

        if (empty($nodes)) {
            return [];
        }

        $assignments = [];

        // Simple load balancing: round-robin assignment
        $node_index = 0;

        foreach ($schedule['stages'] as $stage) {
            foreach ($stage['tiles'] as $tile_index) {
                $tile = $schedule['tiles'][$tile_index];

                // Assign to next available node
                $node = $nodes[$node_index % count($nodes)];

                $assignments[$tile_index] = [
                    'node_id' => $node['node_id'],
                    'node_address' => $node['address'],
                    'tile_id' => $tile['tile_id']
                ];

                $node_index++;
            }
        }

        return $assignments;
    }

    /**
     * Get available AevIP nodes
     *
     * @return array Available nodes
     */
    private function get_available_aevip_nodes() {
        global $wpdb;

        // Check if physics engine is available
        if (!class_exists('AevovPhysicsEngine\\AevIP')) {
            return [];
        }

        $table_name = $wpdb->prefix . 'aevov_physics_nodes';

        $nodes = $wpdb->get_results("
            SELECT node_id, address, status, capabilities
            FROM {$table_name}
            WHERE status = 'active'
            AND last_heartbeat > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            ORDER BY last_heartbeat DESC
        ", ARRAY_A);

        return $nodes ?: [];
    }

    /**
     * Split text into chunks
     *
     * @param string $text Text to split
     * @param int $max_chunk_size Maximum chunk size in characters
     * @return array Text chunks
     */
    private function split_text_into_chunks($text, $max_chunk_size) {
        $chunks = [];

        // Try to split by sentences first
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $current_chunk = '';

        foreach ($sentences as $sentence) {
            if (strlen($current_chunk) + strlen($sentence) > $max_chunk_size) {
                if ($current_chunk !== '') {
                    $chunks[] = $current_chunk;
                    $current_chunk = '';
                }

                // If single sentence is too large, split by words
                if (strlen($sentence) > $max_chunk_size) {
                    $words = explode(' ', $sentence);
                    foreach ($words as $word) {
                        if (strlen($current_chunk) + strlen($word) > $max_chunk_size) {
                            $chunks[] = $current_chunk;
                            $current_chunk = $word;
                        } else {
                            $current_chunk .= ($current_chunk ? ' ' : '') . $word;
                        }
                    }
                } else {
                    $current_chunk = $sentence;
                }
            } else {
                $current_chunk .= ($current_chunk ? ' ' : '') . $sentence;
            }
        }

        if ($current_chunk !== '') {
            $chunks[] = $current_chunk;
        }

        return $chunks;
    }

    /**
     * Create image region tiles
     *
     * @param array $task Image task
     * @param int $tile_size Tile size
     * @return array Region tiles
     */
    private function create_image_region_tiles($task, $tile_size) {
        $width = $task['width'] ?? 512;
        $height = $task['height'] ?? 512;
        $tiles = [];

        $cols = ceil($width / $tile_size);
        $rows = ceil($height / $tile_size);

        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $tiles[] = [
                    'type' => 'image',
                    'operation' => $task['operation'] ?? 'generate',
                    'prompt' => $task['prompt'] ?? '',
                    'region' => [
                        'x' => $col * $tile_size,
                        'y' => $row * $tile_size,
                        'width' => min($tile_size, $width - ($col * $tile_size)),
                        'height' => min($tile_size, $height - ($row * $tile_size))
                    ],
                    'full_width' => $width,
                    'full_height' => $height
                ];
            }
        }

        return $tiles;
    }

    /**
     * Get active schedule
     *
     * @param string $schedule_id Schedule ID
     * @return array|null Schedule or null
     */
    public function get_schedule($schedule_id) {
        return $this->active_schedules[$schedule_id] ?? null;
    }

    /**
     * Remove completed schedule
     *
     * @param string $schedule_id Schedule ID
     */
    public function remove_schedule($schedule_id) {
        unset($this->active_schedules[$schedule_id]);
    }
}
