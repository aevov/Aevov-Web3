<?php
/**
 * Latency Analyzer - Predicts and analyzes execution latency
 *
 * Implements latency prediction using:
 * - Machine learning-based prediction models
 * - Historical performance analysis
 * - Statistical regression models
 * - Real-time latency monitoring
 *
 * @package AevovRuntime
 * @since 1.0.0
 */

namespace AevovRuntime;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LatencyAnalyzer
 */
class LatencyAnalyzer {

    /**
     * Prediction model cache
     *
     * @var array
     */
    private $prediction_models = [];

    /**
     * Historical data cache
     *
     * @var array
     */
    private $historical_data = [];

    /**
     * Real-time measurements
     *
     * @var array
     */
    private $realtime_measurements = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_historical_data();
        $this->build_prediction_models();
    }

    /**
     * Predict tile latency
     *
     * @param array $tile Tile to predict
     * @return float Predicted latency in milliseconds
     */
    public function predict_latency($tile) {
        $type = $tile['type'] ?? 'language';

        // Get prediction model for this type
        if (isset($this->prediction_models[$type])) {
            return $this->predict_with_model($tile, $this->prediction_models[$type]);
        }

        // Fallback to heuristic prediction
        return $this->predict_with_heuristic($tile);
    }

    /**
     * Predict model latency
     *
     * @param string $model Model name
     * @param array $task Task
     * @return float Predicted latency in milliseconds
     */
    public function predict_model_latency($model, $task) {
        $type = $task['type'] ?? 'language';

        // Get historical data for this model
        $model_data = $this->get_model_historical_data($model, $type);

        if (empty($model_data)) {
            // No historical data - use baseline
            return $this->get_baseline_latency($model, $type);
        }

        // Calculate weighted average based on similar tasks
        $similar_tasks = $this->find_similar_tasks($task, $model_data);

        if (empty($similar_tasks)) {
            return $this->calculate_average_latency($model_data);
        }

        return $this->calculate_weighted_average_latency($similar_tasks);
    }

    /**
     * Predict with model
     *
     * @param array $tile Tile
     * @param array $model Prediction model
     * @return float Predicted latency
     */
    private function predict_with_model($tile, $model) {
        // Extract features from tile
        $features = $this->extract_features($tile);

        // Apply linear regression model: latency = β0 + β1*x1 + β2*x2 + ...
        $prediction = $model['intercept'];

        foreach ($model['coefficients'] as $feature_name => $coefficient) {
            $prediction += $coefficient * ($features[$feature_name] ?? 0);
        }

        // Apply non-linear adjustments if available
        if (isset($model['non_linear_factor'])) {
            $prediction *= $model['non_linear_factor'];
        }

        // Ensure positive prediction
        return max($prediction, 1.0);
    }

    /**
     * Predict with heuristic
     *
     * @param array $tile Tile
     * @return float Predicted latency
     */
    private function predict_with_heuristic($tile) {
        $type = $tile['type'] ?? 'language';

        switch ($type) {
            case 'language':
                $tokens = $tile['estimated_tokens'] ?? 100;
                $base_latency = 50; // 50ms base
                $per_token_latency = 5; // 5ms per token
                return $base_latency + ($tokens * $per_token_latency);

            case 'image':
                $width = $tile['width'] ?? 512;
                $height = $tile['height'] ?? 512;
                $pixels = $width * $height;
                $megapixels = $pixels / 1000000;
                return 500 + ($megapixels * 1000); // 500ms base + 1s per megapixel

            case 'music':
                $duration = $tile['duration'] ?? 5;
                return 300 + ($duration * 150); // 300ms base + 150ms per second

            default:
                return 100; // 100ms default
        }
    }

    /**
     * Extract features from tile
     *
     * @param array $tile Tile
     * @return array Features
     */
    private function extract_features($tile) {
        $features = [];
        $type = $tile['type'] ?? 'language';

        // Common features
        $features['priority'] = $tile['priority'] ?? 0;
        $features['has_dependencies'] = !empty($tile['depends_on']) ? 1 : 0;

        // Type-specific features
        switch ($type) {
            case 'language':
                $features['tokens'] = $tile['estimated_tokens'] ?? 100;
                $features['max_tokens'] = $tile['max_tokens'] ?? 100;
                $features['temperature'] = $tile['temperature'] ?? 0.7;
                $features['streaming'] = $tile['streaming'] ? 1 : 0;
                break;

            case 'image':
                $features['width'] = $tile['width'] ?? 512;
                $features['height'] = $tile['height'] ?? 512;
                $features['pixels'] = $features['width'] * $features['height'];
                $features['steps'] = $tile['steps'] ?? 50;
                $features['has_region'] = isset($tile['region']) ? 1 : 0;
                break;

            case 'music':
                $features['duration'] = $tile['duration'] ?? 5;
                $features['sample_rate'] = $tile['sample_rate'] ?? 44100;
                $features['has_previous'] = isset($tile['dependency_context']) ? 1 : 0;
                break;
        }

        return $features;
    }

    /**
     * Build prediction models from historical data
     */
    private function build_prediction_models() {
        foreach (['language', 'image', 'music'] as $type) {
            if (!isset($this->historical_data[$type]) || count($this->historical_data[$type]) < 10) {
                continue; // Not enough data
            }

            $this->prediction_models[$type] = $this->train_regression_model(
                $this->historical_data[$type],
                $type
            );
        }
    }

    /**
     * Train regression model
     *
     * @param array $data Training data
     * @param string $type Task type
     * @return array Trained model
     */
    private function train_regression_model($data, $type) {
        // Extract features and latencies
        $X = []; // Features matrix
        $y = []; // Latencies vector

        foreach ($data as $sample) {
            $features = $this->extract_features($sample);
            $X[] = $features;
            $y[] = $sample['actual_latency'];
        }

        // Simple linear regression using least squares
        $coefficients = $this->calculate_least_squares($X, $y);

        return [
            'type' => $type,
            'intercept' => $coefficients['intercept'] ?? 50,
            'coefficients' => $coefficients['features'] ?? [],
            'samples' => count($data),
            'trained_at' => time()
        ];
    }

    /**
     * Calculate least squares regression
     *
     * @param array $X Features matrix
     * @param array $y Target values
     * @return array Regression coefficients
     */
    private function calculate_least_squares($X, $y) {
        if (empty($X) || empty($y)) {
            return ['intercept' => 0, 'features' => []];
        }

        $n = count($y);

        // Get feature names from first sample
        $feature_names = array_keys($X[0]);

        // Calculate means
        $y_mean = array_sum($y) / $n;
        $x_means = [];

        foreach ($feature_names as $feature) {
            $x_means[$feature] = array_sum(array_column($X, $feature)) / $n;
        }

        // Calculate coefficients for each feature
        $coefficients = [];

        foreach ($feature_names as $feature) {
            $numerator = 0;
            $denominator = 0;

            for ($i = 0; $i < $n; $i++) {
                $x_diff = $X[$i][$feature] - $x_means[$feature];
                $y_diff = $y[$i] - $y_mean;

                $numerator += $x_diff * $y_diff;
                $denominator += $x_diff * $x_diff;
            }

            if ($denominator != 0) {
                $coefficients[$feature] = $numerator / $denominator;
            } else {
                $coefficients[$feature] = 0;
            }
        }

        // Calculate intercept
        $intercept = $y_mean;
        foreach ($feature_names as $feature) {
            $intercept -= $coefficients[$feature] * $x_means[$feature];
        }

        return [
            'intercept' => $intercept,
            'features' => $coefficients
        ];
    }

    /**
     * Get model historical data
     *
     * @param string $model Model name
     * @param string $type Task type
     * @return array Historical data
     */
    private function get_model_historical_data($model, $type) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aevov_runtime_metrics';

        $data = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$table_name}
            WHERE task_type = %s
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY created_at DESC
            LIMIT 100
        ", $type), ARRAY_A);

        return $data ?: [];
    }

    /**
     * Find similar tasks
     *
     * @param array $task Task to match
     * @param array $dataset Dataset to search
     * @return array Similar tasks
     */
    private function find_similar_tasks($task, $dataset) {
        $similar = [];
        $task_features = $this->extract_features($task);

        foreach ($dataset as $sample) {
            $sample_features = $this->extract_features($sample);

            // Calculate similarity (inverse of Euclidean distance)
            $similarity = $this->calculate_similarity($task_features, $sample_features);

            if ($similarity > 0.7) { // Threshold for similarity
                $similar[] = [
                    'sample' => $sample,
                    'similarity' => $similarity
                ];
            }
        }

        // Sort by similarity
        usort($similar, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        // Return top 10
        return array_slice($similar, 0, 10);
    }

    /**
     * Calculate similarity between feature sets
     *
     * @param array $features1 First feature set
     * @param array $features2 Second feature set
     * @return float Similarity score (0-1)
     */
    private function calculate_similarity($features1, $features2) {
        $sum_squared_diff = 0;
        $count = 0;

        foreach ($features1 as $key => $value1) {
            if (isset($features2[$key])) {
                $value2 = $features2[$key];

                // Normalize to 0-1 range
                $max_value = max(abs($value1), abs($value2), 1);
                $normalized_diff = abs($value1 - $value2) / $max_value;

                $sum_squared_diff += $normalized_diff * $normalized_diff;
                $count++;
            }
        }

        if ($count === 0) {
            return 0;
        }

        // Calculate similarity (1 - normalized distance)
        $distance = sqrt($sum_squared_diff / $count);
        return max(0, 1 - $distance);
    }

    /**
     * Calculate average latency
     *
     * @param array $data Data samples
     * @return float Average latency
     */
    private function calculate_average_latency($data) {
        $latencies = array_column($data, 'actual_latency');

        if (empty($latencies)) {
            return 100; // Default
        }

        return array_sum($latencies) / count($latencies);
    }

    /**
     * Calculate weighted average latency
     *
     * @param array $similar_tasks Similar tasks with similarity scores
     * @return float Weighted average latency
     */
    private function calculate_weighted_average_latency($similar_tasks) {
        $weighted_sum = 0;
        $weight_sum = 0;

        foreach ($similar_tasks as $task) {
            $latency = $task['sample']['actual_latency'] ?? 0;
            $weight = $task['similarity'];

            $weighted_sum += $latency * $weight;
            $weight_sum += $weight;
        }

        if ($weight_sum === 0) {
            return 100; // Default
        }

        return $weighted_sum / $weight_sum;
    }

    /**
     * Get baseline latency
     *
     * @param string $model Model name
     * @param string $type Task type
     * @return float Baseline latency
     */
    private function get_baseline_latency($model, $type) {
        $baselines = [
            'language' => [
                'gpt-4-turbo' => 200,
                'gpt-3.5-turbo' => 100,
                'claude-3-opus' => 250,
                'claude-3-sonnet' => 150,
                'claude-3-haiku' => 80
            ],
            'image' => [
                'stable-diffusion-xl' => 2000,
                'stable-diffusion-2' => 1500,
                'dall-e-3' => 3000
            ],
            'music' => [
                'musicgen-large' => 1500,
                'musicgen-medium' => 1000,
                'musicgen-small' => 700
            ]
        ];

        return $baselines[$type][$model] ?? 150;
    }

    /**
     * Load historical data
     */
    private function load_historical_data() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aevov_runtime_metrics';

        // Load recent execution data
        $data = $wpdb->get_results("
            SELECT *
            FROM {$table_name}
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY created_at DESC
            LIMIT 500
        ", ARRAY_A);

        // Group by task type
        foreach ($data as $row) {
            $type = $row['task_type'];

            if (!isset($this->historical_data[$type])) {
                $this->historical_data[$type] = [];
            }

            $this->historical_data[$type][] = $row;
        }
    }

    /**
     * Record measurement
     *
     * @param array $tile Tile
     * @param float $actual_latency Actual latency in ms
     */
    public function record_measurement($tile, $actual_latency) {
        $predicted_latency = $this->predict_latency($tile);

        $this->realtime_measurements[] = [
            'tile_id' => $tile['tile_id'] ?? null,
            'type' => $tile['type'] ?? 'unknown',
            'predicted' => $predicted_latency,
            'actual' => $actual_latency,
            'error' => abs($actual_latency - $predicted_latency),
            'error_percentage' => abs($actual_latency - $predicted_latency) / max($actual_latency, 1) * 100,
            'timestamp' => microtime(true)
        ];

        // Keep last 1000 measurements
        if (count($this->realtime_measurements) > 1000) {
            array_shift($this->realtime_measurements);
        }

        // Periodically retrain models
        if (count($this->realtime_measurements) % 100 === 0) {
            $this->retrain_models();
        }
    }

    /**
     * Retrain models with new data
     */
    private function retrain_models() {
        // Reload historical data
        $this->load_historical_data();

        // Rebuild prediction models
        $this->build_prediction_models();
    }

    /**
     * Get prediction accuracy
     *
     * @return array Accuracy metrics
     */
    public function get_prediction_accuracy() {
        if (empty($this->realtime_measurements)) {
            return [
                'sample_count' => 0,
                'mean_error' => 0,
                'mean_error_percentage' => 0
            ];
        }

        $errors = array_column($this->realtime_measurements, 'error');
        $error_percentages = array_column($this->realtime_measurements, 'error_percentage');

        return [
            'sample_count' => count($this->realtime_measurements),
            'mean_error' => array_sum($errors) / count($errors),
            'mean_error_percentage' => array_sum($error_percentages) / count($error_percentages),
            'recent_measurements' => array_slice($this->realtime_measurements, -10)
        ];
    }

    /**
     * Get statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        return [
            'models_trained' => count($this->prediction_models),
            'historical_samples' => array_map('count', $this->historical_data),
            'realtime_measurements' => count($this->realtime_measurements),
            'prediction_accuracy' => $this->get_prediction_accuracy()
        ];
    }
}
