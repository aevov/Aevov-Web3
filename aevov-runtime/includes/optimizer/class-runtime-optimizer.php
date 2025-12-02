<?php
/**
 * Runtime Optimizer - Optimizes execution strategies
 *
 * Implements runtime optimization techniques:
 * - Dynamic tile size adjustment
 * - Execution path optimization
 * - Resource allocation optimization
 * - Adaptive scheduling based on performance
 * - Cost-latency tradeoff optimization
 *
 * @package AevovRuntime
 * @since 1.0.0
 */

namespace AevovRuntime;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RuntimeOptimizer
 */
class RuntimeOptimizer {

    /**
     * Optimization history
     *
     * @var array
     */
    private $optimization_history = [];

    /**
     * Performance metrics
     *
     * @var array
     */
    private $performance_metrics = [];

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
        $this->load_performance_metrics();
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
     * Optimize task before execution
     *
     * @param array $task Task to optimize
     * @param array $constraints Optimization constraints
     * @return array Optimized task
     */
    public function optimize_task($task, $constraints = []) {
        $optimized = $task;

        // Extract constraints
        $target_latency = $constraints['target_latency'] ?? 100; // ms
        $max_cost = $constraints['max_cost'] ?? null;
        $quality_threshold = $constraints['quality_threshold'] ?? 0.8;

        // 1. Optimize tile decomposition
        $optimized = $this->optimize_tile_decomposition($optimized, $target_latency);

        // 2. Optimize model selection
        $optimized = $this->optimize_model_selection($optimized, $target_latency, $quality_threshold);

        // 3. Optimize parallelization strategy
        $optimized = $this->optimize_parallelization($optimized, $target_latency);

        // 4. Optimize resource allocation
        $optimized = $this->optimize_resource_allocation($optimized, $max_cost);

        // Record optimization
        $this->record_optimization($task, $optimized, $constraints);

        return $optimized;
    }

    /**
     * Optimize tile decomposition strategy
     *
     * @param array $task Task
     * @param float $target_latency Target latency in ms
     * @return array Optimized task
     */
    private function optimize_tile_decomposition($task, $target_latency) {
        $type = $task['type'] ?? 'language';

        // Get historical performance for this task type
        $historical_perf = $this->get_historical_performance($type);

        if (empty($historical_perf)) {
            // No history - use default decomposition
            return $task;
        }

        // Calculate optimal tile size based on target latency
        $optimal_tile_size = $this->calculate_optimal_tile_size(
            $type,
            $target_latency,
            $historical_perf
        );

        // Adjust tile size hint
        $task['optimal_tile_size'] = $optimal_tile_size;

        return $task;
    }

    /**
     * Optimize model selection
     *
     * @param array $task Task
     * @param float $target_latency Target latency in ms
     * @param float $quality_threshold Quality threshold
     * @return array Optimized task
     */
    private function optimize_model_selection($task, $target_latency, $quality_threshold) {
        $type = $task['type'] ?? 'language';
        $current_model = $task['model'] ?? null;

        // Get available models for this type
        $available_models = $this->get_available_models($type);

        if (empty($available_models) || !$current_model) {
            return $task;
        }

        // Evaluate models based on latency and quality
        $best_model = $current_model;
        $best_score = -INF;

        foreach ($available_models as $model) {
            // Get model metrics
            $metrics = $this->get_model_metrics($model);

            if (!$metrics) {
                continue;
            }

            // Check if meets quality threshold
            if (($metrics['quality_score'] ?? 0) < $quality_threshold) {
                continue;
            }

            // Check if meets latency target
            $estimated_latency = $this->estimate_model_latency($model, $task);

            if ($estimated_latency > $target_latency * 1.2) {
                continue; // Too slow
            }

            // Calculate score (balance quality and latency)
            $latency_score = max(0, 1 - ($estimated_latency / $target_latency));
            $quality_score = $metrics['quality_score'] ?? 0.5;
            $score = ($latency_score * 0.6) + ($quality_score * 0.4);

            if ($score > $best_score) {
                $best_score = $score;
                $best_model = $model;
            }
        }

        // Update model if better option found
        if ($best_model !== $current_model) {
            $task['model'] = $best_model;
            $task['model_optimized'] = true;
            $task['optimization_score'] = $best_score;
        }

        return $task;
    }

    /**
     * Optimize parallelization strategy
     *
     * @param array $task Task
     * @param float $target_latency Target latency in ms
     * @return array Optimized task
     */
    private function optimize_parallelization($task, $target_latency) {
        // Determine if task should be parallelized
        $estimated_sequential_time = $this->estimate_sequential_execution_time($task);

        if ($estimated_sequential_time <= $target_latency) {
            // Sequential execution is fast enough
            $task['parallelization_strategy'] = 'sequential';
            return $task;
        }

        // Calculate optimal parallelization degree
        $optimal_parallel_degree = $this->calculate_optimal_parallelization(
            $task,
            $target_latency,
            $estimated_sequential_time
        );

        $task['parallelization_strategy'] = 'parallel';
        $task['optimal_parallel_degree'] = $optimal_parallel_degree;

        return $task;
    }

    /**
     * Optimize resource allocation
     *
     * @param array $task Task
     * @param float|null $max_cost Maximum cost
     * @return array Optimized task
     */
    private function optimize_resource_allocation($task, $max_cost) {
        if ($max_cost === null) {
            return $task;
        }

        // Estimate cost for current configuration
        $estimated_cost = $this->estimate_task_cost($task);

        if ($estimated_cost <= $max_cost) {
            return $task; // Within budget
        }

        // Need to reduce cost
        $task = $this->reduce_task_cost($task, $max_cost);

        return $task;
    }

    /**
     * Calculate optimal tile size
     *
     * @param string $type Task type
     * @param float $target_latency Target latency in ms
     * @param array $historical_perf Historical performance
     * @return int Optimal tile size
     */
    private function calculate_optimal_tile_size($type, $target_latency, $historical_perf) {
        // Analyze historical data to find tile size that meets latency
        $tile_sizes = array_column($historical_perf, 'tile_size');
        $latencies = array_column($historical_perf, 'latency');

        if (empty($tile_sizes)) {
            return 512; // Default
        }

        // Find tile size with latency closest to target
        $best_tile_size = 512;
        $best_diff = INF;

        foreach ($tile_sizes as $i => $tile_size) {
            $latency = $latencies[$i];
            $diff = abs($latency - $target_latency);

            if ($diff < $best_diff) {
                $best_diff = $diff;
                $best_tile_size = $tile_size;
            }
        }

        return $best_tile_size;
    }

    /**
     * Get available models for task type
     *
     * @param string $type Task type
     * @return array Available models
     */
    private function get_available_models($type) {
        $models = [];

        switch ($type) {
            case 'language':
                $models = [
                    'gpt-4-turbo',
                    'gpt-3.5-turbo',
                    'claude-3-opus',
                    'claude-3-sonnet',
                    'claude-3-haiku'
                ];
                break;

            case 'image':
                $models = [
                    'stable-diffusion-xl',
                    'stable-diffusion-2',
                    'dall-e-3',
                    'dall-e-2'
                ];
                break;

            case 'music':
                $models = [
                    'musicgen-large',
                    'musicgen-medium',
                    'musicgen-small'
                ];
                break;
        }

        // Filter to only models configured in system
        return apply_filters('aevrt_available_models', $models, $type);
    }

    /**
     * Get model metrics
     *
     * @param string $model Model name
     * @return array|null Metrics or null
     */
    private function get_model_metrics($model) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aevov_runtime_metrics';

        $metrics = $wpdb->get_row($wpdb->prepare("
            SELECT
                AVG(actual_latency) as avg_latency,
                MIN(actual_latency) as min_latency,
                MAX(actual_latency) as max_latency,
                COUNT(*) as sample_count
            FROM {$table_name}
            WHERE task_type = %s
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ", $model), ARRAY_A);

        if (!$metrics || $metrics['sample_count'] < 5) {
            return null; // Not enough data
        }

        // Add quality score (would be computed from actual quality metrics)
        $metrics['quality_score'] = $this->estimate_model_quality($model);

        return $metrics;
    }

    /**
     * Estimate model latency
     *
     * @param string $model Model name
     * @param array $task Task
     * @return float Estimated latency in ms
     */
    private function estimate_model_latency($model, $task) {
        if ($this->latency_analyzer) {
            return $this->latency_analyzer->predict_model_latency($model, $task);
        }

        // Fallback to heuristic
        $base_latencies = [
            'gpt-4-turbo' => 200,
            'gpt-3.5-turbo' => 100,
            'claude-3-opus' => 250,
            'claude-3-sonnet' => 150,
            'claude-3-haiku' => 80
        ];

        return $base_latencies[$model] ?? 150;
    }

    /**
     * Estimate model quality
     *
     * @param string $model Model name
     * @return float Quality score (0-1)
     */
    private function estimate_model_quality($model) {
        // Quality scores for common models (would be computed from evaluations)
        $quality_scores = [
            'gpt-4-turbo' => 0.95,
            'gpt-3.5-turbo' => 0.85,
            'claude-3-opus' => 0.95,
            'claude-3-sonnet' => 0.90,
            'claude-3-haiku' => 0.80,
            'stable-diffusion-xl' => 0.90,
            'stable-diffusion-2' => 0.80
        ];

        return $quality_scores[$model] ?? 0.75;
    }

    /**
     * Estimate sequential execution time
     *
     * @param array $task Task
     * @return float Estimated time in ms
     */
    private function estimate_sequential_execution_time($task) {
        $type = $task['type'] ?? 'language';

        if ($this->latency_analyzer) {
            return $this->latency_analyzer->predict_latency(['type' => $type, 'input' => $task['input'] ?? '']);
        }

        // Fallback estimates
        return 500; // 500ms default
    }

    /**
     * Calculate optimal parallelization degree
     *
     * @param array $task Task
     * @param float $target_latency Target latency in ms
     * @param float $sequential_time Sequential execution time in ms
     * @return int Optimal parallelization degree
     */
    private function calculate_optimal_parallelization($task, $target_latency, $sequential_time) {
        // Using Amdahl's law to estimate parallelization benefit
        $parallel_fraction = 0.8; // Assume 80% of work is parallelizable

        $speedup_needed = $sequential_time / $target_latency;

        // Calculate parallelization degree
        $degree = ceil($speedup_needed / (1 - $parallel_fraction + ($parallel_fraction / $speedup_needed)));

        // Cap at reasonable maximum
        return min($degree, 16);
    }

    /**
     * Estimate task cost
     *
     * @param array $task Task
     * @return float Estimated cost
     */
    private function estimate_task_cost($task) {
        $type = $task['type'] ?? 'language';
        $model = $task['model'] ?? '';

        // Cost per token/pixel/second
        $cost_rates = [
            'gpt-4-turbo' => 0.00001,
            'gpt-3.5-turbo' => 0.000001,
            'stable-diffusion-xl' => 0.002,
            'musicgen-large' => 0.001
        ];

        $rate = $cost_rates[$model] ?? 0.00001;

        // Estimate units
        if ($type === 'language') {
            $tokens = (strlen($task['input'] ?? '') / 4) + ($task['max_tokens'] ?? 100);
            return $tokens * $rate;
        } elseif ($type === 'image') {
            $pixels = ($task['width'] ?? 512) * ($task['height'] ?? 512);
            return ($pixels / 1000) * $rate;
        } elseif ($type === 'music') {
            $seconds = $task['duration'] ?? 30;
            return $seconds * $rate;
        }

        return 0.01; // Default
    }

    /**
     * Reduce task cost
     *
     * @param array $task Task
     * @param float $max_cost Maximum cost
     * @return array Reduced cost task
     */
    private function reduce_task_cost($task, $max_cost) {
        $type = $task['type'] ?? 'language';

        // Try cheaper model first
        $cheaper_models = $this->get_cheaper_models($task['model'] ?? '');

        foreach ($cheaper_models as $model) {
            $task['model'] = $model;
            $estimated_cost = $this->estimate_task_cost($task);

            if ($estimated_cost <= $max_cost) {
                return $task;
            }
        }

        // Reduce quality/size parameters
        if ($type === 'language') {
            $current_max_tokens = $task['max_tokens'] ?? 1000;
            $task['max_tokens'] = min($current_max_tokens, 500);
        } elseif ($type === 'image') {
            $task['width'] = min($task['width'] ?? 512, 512);
            $task['height'] = min($task['height'] ?? 512, 512);
        } elseif ($type === 'music') {
            $task['duration'] = min($task['duration'] ?? 30, 15);
        }

        return $task;
    }

    /**
     * Get cheaper models
     *
     * @param string $current_model Current model
     * @return array Cheaper models
     */
    private function get_cheaper_models($current_model) {
        $model_tiers = [
            'gpt-4-turbo' => ['gpt-3.5-turbo', 'claude-3-haiku'],
            'claude-3-opus' => ['claude-3-sonnet', 'claude-3-haiku', 'gpt-3.5-turbo'],
            'stable-diffusion-xl' => ['stable-diffusion-2']
        ];

        return $model_tiers[$current_model] ?? [];
    }

    /**
     * Record optimization
     *
     * @param array $original Original task
     * @param array $optimized Optimized task
     * @param array $constraints Constraints
     */
    private function record_optimization($original, $optimized, $constraints) {
        $this->optimization_history[] = [
            'timestamp' => microtime(true),
            'original_model' => $original['model'] ?? null,
            'optimized_model' => $optimized['model'] ?? null,
            'constraints' => $constraints,
            'optimizations_applied' => $this->get_applied_optimizations($original, $optimized)
        ];

        // Keep last 1000 optimizations
        if (count($this->optimization_history) > 1000) {
            array_shift($this->optimization_history);
        }
    }

    /**
     * Get applied optimizations
     *
     * @param array $original Original task
     * @param array $optimized Optimized task
     * @return array Applied optimizations
     */
    private function get_applied_optimizations($original, $optimized) {
        $applied = [];

        if (($original['model'] ?? null) !== ($optimized['model'] ?? null)) {
            $applied[] = 'model_selection';
        }

        if (isset($optimized['optimal_tile_size'])) {
            $applied[] = 'tile_decomposition';
        }

        if (isset($optimized['parallelization_strategy'])) {
            $applied[] = 'parallelization';
        }

        return $applied;
    }

    /**
     * Get historical performance
     *
     * @param string $type Task type
     * @return array Historical performance data
     */
    private function get_historical_performance($type) {
        if (!isset($this->performance_metrics[$type])) {
            return [];
        }

        return $this->performance_metrics[$type];
    }

    /**
     * Load performance metrics
     */
    private function load_performance_metrics() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aevov_runtime_metrics';

        // Load recent performance data
        $metrics = $wpdb->get_results("
            SELECT task_type, num_tiles, estimated_latency, actual_latency
            FROM {$table_name}
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC
            LIMIT 1000
        ", ARRAY_A);

        foreach ($metrics as $metric) {
            $type = $metric['task_type'];

            if (!isset($this->performance_metrics[$type])) {
                $this->performance_metrics[$type] = [];
            }

            $this->performance_metrics[$type][] = [
                'tile_size' => $metric['num_tiles'],
                'latency' => $metric['actual_latency']
            ];
        }
    }

    /**
     * Get optimization statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        return [
            'total_optimizations' => count($this->optimization_history),
            'recent_optimizations' => array_slice($this->optimization_history, -10),
            'performance_metrics_loaded' => array_map('count', $this->performance_metrics)
        ];
    }
}
