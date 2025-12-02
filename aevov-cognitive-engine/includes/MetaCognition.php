<?php
/**
 * Meta-Cognition System
 *
 * Self-monitoring, self-regulation, and metacognitive awareness
 * Based on Flavell's metacognitive framework and Nelson & Narens' model
 *
 * Features:
 * - Metacognitive monitoring (knowing what you know)
 * - Metacognitive control (regulating cognition)
 * - Confidence calibration (accuracy of self-assessment)
 * - Strategy selection and adaptation
 * - Error detection and correction
 * - Learning rate estimation
 * - Cognitive load monitoring
 * - Performance prediction (feeling of knowing)
 */

namespace AevovCognitiveEngine;

class MetaCognition {

    private $performance_history = [];
    private $confidence_calibration = [];
    private $strategy_effectiveness = [];
    private $current_cognitive_load = 0.0;
    private $learning_rate = 0.1;
    private $overconfidence_bias = 0.1; // Tendency to be overconfident
    private $monitoring_frequency = 5; // Monitor every N operations

    /**
     * Constructor
     */
    public function __construct($config = []) {
        $this->learning_rate = $config['learning_rate'] ?? 0.1;
        $this->overconfidence_bias = $config['overconfidence_bias'] ?? 0.1;
        $this->monitoring_frequency = $config['monitoring_frequency'] ?? 5;
    }

    /**
     * Monitor cognitive performance
     *
     * @param string $task_type Type of cognitive task
     * @param mixed $output Task output
     * @param mixed $expected Expected output (if known)
     * @return array Monitoring report
     */
    public function monitor_performance($task_type, $output, $expected = null) {
        $confidence = $this->assess_confidence($task_type, $output);
        $feeling_of_knowing = $this->calculate_feeling_of_knowing($task_type);

        $report = [
            'task_type' => $task_type,
            'confidence' => $confidence,
            'feeling_of_knowing' => $feeling_of_knowing,
            'cognitive_load' => $this->current_cognitive_load,
            'timestamp' => microtime(true)
        ];

        // If expected output known, calculate accuracy
        if ($expected !== null) {
            $accuracy = $this->calculate_accuracy($output, $expected);
            $report['accuracy'] = $accuracy;

            // Update calibration
            $this->update_calibration($confidence, $accuracy);
        }

        // Record in history
        $this->performance_history[] = $report;

        // Limit history size
        if (count($this->performance_history) > 1000) {
            array_shift($this->performance_history);
        }

        return $report;
    }

    /**
     * Assess confidence in output
     * Based on internal consistency, fluency, and past performance
     */
    private function assess_confidence($task_type, $output) {
        $confidence = 0.5; // Base confidence

        // Confidence from past performance on similar tasks
        $past_performance = $this->get_past_performance($task_type);
        if ($past_performance !== null) {
            $confidence = $past_performance['avg_accuracy'] ?? 0.5;
        }

        // Adjust for output characteristics
        $output_confidence = $this->assess_output_confidence($output);
        $confidence = ($confidence + $output_confidence) / 2;

        // Apply overconfidence bias
        $confidence += $this->overconfidence_bias * (1 - $confidence);

        // Apply cognitive load penalty (high load reduces confidence)
        $load_penalty = $this->current_cognitive_load * 0.2;
        $confidence *= (1 - $load_penalty);

        return max(0.0, min(1.0, $confidence));
    }

    /**
     * Assess confidence based on output characteristics
     */
    private function assess_output_confidence($output) {
        $confidence = 0.5;

        // Check output completeness
        if ($output === null || $output === '') {
            return 0.1;
        }

        // For numeric outputs, check if reasonable
        if (is_numeric($output)) {
            if (is_finite($output) && !is_nan($output)) {
                $confidence = 0.7;
            } else {
                $confidence = 0.2;
            }
        }

        // For array outputs, check size and structure
        if (is_array($output)) {
            if (!empty($output)) {
                $confidence = 0.6 + (min(count($output), 10) / 10) * 0.2;
            } else {
                $confidence = 0.3;
            }
        }

        // For string outputs, check length and coherence
        if (is_string($output)) {
            $length = strlen($output);
            if ($length > 10 && $length < 1000) {
                $confidence = 0.7;
            } elseif ($length > 0) {
                $confidence = 0.5;
            } else {
                $confidence = 0.2;
            }
        }

        return $confidence;
    }

    /**
     * Calculate "feeling of knowing" (FOK)
     * Predict whether information can be retrieved before attempting retrieval
     */
    private function calculate_feeling_of_knowing($task_type) {
        // Based on familiarity and accessibility
        $past_encounters = $this->count_past_encounters($task_type);

        // More encounters = stronger FOK
        $fok = 1 - exp(-0.5 * $past_encounters);

        // Adjust for recency
        $recent_performance = $this->get_recent_performance($task_type, 5);
        if ($recent_performance !== null) {
            $recency_boost = $recent_performance['avg_accuracy'] * 0.3;
            $fok += $recency_boost;
        }

        return max(0.0, min(1.0, $fok));
    }

    /**
     * Control: Select optimal cognitive strategy
     *
     * @param string $task_type Type of task
     * @param array $available_strategies Available strategies
     * @return string Selected strategy
     */
    public function select_strategy($task_type, $available_strategies) {
        if (empty($available_strategies)) {
            return null;
        }

        // Calculate expected performance for each strategy
        $strategy_scores = [];

        foreach ($available_strategies as $strategy) {
            $score = $this->estimate_strategy_performance($strategy, $task_type);
            $strategy_scores[$strategy] = $score;
        }

        // Select best strategy
        arsort($strategy_scores);
        $selected = array_key_first($strategy_scores);

        // Exploration: occasionally try non-optimal strategies (epsilon-greedy)
        $exploration_rate = 0.1;
        if (mt_rand() / mt_getrandmax() < $exploration_rate) {
            $selected = $available_strategies[array_rand($available_strategies)];
        }

        return $selected;
    }

    /**
     * Estimate strategy performance based on past experience
     */
    private function estimate_strategy_performance($strategy, $task_type) {
        $key = $task_type . '_' . $strategy;

        if (isset($this->strategy_effectiveness[$key])) {
            $stats = $this->strategy_effectiveness[$key];
            $base_score = $stats['avg_accuracy'];

            // Penalize based on cognitive cost
            $cost_penalty = ($stats['avg_time'] ?? 1.0) * 0.1;
            return $base_score - $cost_penalty;
        }

        // No data: return neutral estimate
        return 0.5;
    }

    /**
     * Update strategy effectiveness based on outcome
     *
     * @param string $strategy Strategy used
     * @param string $task_type Task type
     * @param float $accuracy Achieved accuracy
     * @param float $time Time taken
     */
    public function update_strategy_effectiveness($strategy, $task_type, $accuracy, $time) {
        $key = $task_type . '_' . $strategy;

        if (!isset($this->strategy_effectiveness[$key])) {
            $this->strategy_effectiveness[$key] = [
                'avg_accuracy' => $accuracy,
                'avg_time' => $time,
                'count' => 1
            ];
        } else {
            $stats = &$this->strategy_effectiveness[$key];

            // Moving average
            $alpha = $this->learning_rate;
            $stats['avg_accuracy'] = (1 - $alpha) * $stats['avg_accuracy'] + $alpha * $accuracy;
            $stats['avg_time'] = (1 - $alpha) * $stats['avg_time'] + $alpha * $time;
            $stats['count']++;
        }
    }

    /**
     * Detect errors in cognitive processing
     *
     * @param mixed $output Output to check
     * @param array $constraints Known constraints
     * @return array Detected errors
     */
    public function detect_errors($output, $constraints = []) {
        $errors = [];

        // Null check
        if ($output === null && !($constraints['nullable'] ?? false)) {
            $errors[] = [
                'type' => 'null_output',
                'severity' => 'high',
                'message' => 'Output is null when value expected'
            ];
        }

        // Type checking
        if (isset($constraints['type'])) {
            $expected_type = $constraints['type'];
            $actual_type = gettype($output);

            if ($actual_type !== $expected_type) {
                $errors[] = [
                    'type' => 'type_mismatch',
                    'severity' => 'medium',
                    'message' => "Expected {$expected_type}, got {$actual_type}"
                ];
            }
        }

        // Range checking for numeric outputs
        if (is_numeric($output)) {
            if (isset($constraints['min']) && $output < $constraints['min']) {
                $errors[] = [
                    'type' => 'out_of_range',
                    'severity' => 'medium',
                    'message' => "Value below minimum: {$output} < {$constraints['min']}"
                ];
            }

            if (isset($constraints['max']) && $output > $constraints['max']) {
                $errors[] = [
                    'type' => 'out_of_range',
                    'severity' => 'medium',
                    'message' => "Value above maximum: {$output} > {$constraints['max']}"
                ];
            }

            // Check for invalid numeric values
            if (is_nan($output) || is_infinite($output)) {
                $errors[] = [
                    'type' => 'invalid_number',
                    'severity' => 'high',
                    'message' => 'Output is NaN or infinite'
                ];
            }
        }

        // Consistency checking for structured outputs
        if (is_array($output) && isset($constraints['required_keys'])) {
            $missing_keys = array_diff($constraints['required_keys'], array_keys($output));

            if (!empty($missing_keys)) {
                $errors[] = [
                    'type' => 'missing_keys',
                    'severity' => 'medium',
                    'message' => 'Missing required keys: ' . implode(', ', $missing_keys)
                ];
            }
        }

        return $errors;
    }

    /**
     * Regulate cognitive processes based on monitoring
     *
     * @param array $monitoring_report Report from monitor_performance
     * @return array Regulatory actions
     */
    public function regulate($monitoring_report) {
        $actions = [];

        // If confidence is low, suggest more careful processing
        if ($monitoring_report['confidence'] < 0.3) {
            $actions[] = [
                'action' => 'increase_deliberation',
                'reason' => 'Low confidence detected',
                'parameter' => 'processing_depth',
                'adjustment' => 1.5
            ];
        }

        // If cognitive load is high, suggest simplification
        if ($this->current_cognitive_load > 0.8) {
            $actions[] = [
                'action' => 'reduce_load',
                'reason' => 'High cognitive load',
                'parameter' => 'chunk_size',
                'adjustment' => 0.7
            ];

            $actions[] = [
                'action' => 'activate_working_memory_rehearsal',
                'reason' => 'Prevent working memory overflow'
            ];
        }

        // If past performance is poor, suggest strategy switch
        $recent_accuracy = $this->get_recent_average_accuracy(10);
        if ($recent_accuracy !== null && $recent_accuracy < 0.5) {
            $actions[] = [
                'action' => 'switch_strategy',
                'reason' => 'Poor recent performance',
                'current_accuracy' => $recent_accuracy
            ];
        }

        // If calibration is poor (over/under confident), adjust
        $calibration_error = $this->get_calibration_error();
        if (abs($calibration_error) > 0.2) {
            $actions[] = [
                'action' => 'adjust_confidence_bias',
                'reason' => 'Poor calibration',
                'adjustment' => -$calibration_error * 0.5
            ];

            // Update overconfidence bias
            $this->overconfidence_bias -= $calibration_error * 0.1;
            $this->overconfidence_bias = max(-0.2, min(0.3, $this->overconfidence_bias));
        }

        return $actions;
    }

    /**
     * Update confidence calibration
     */
    private function update_calibration($predicted_confidence, $actual_accuracy) {
        $this->confidence_calibration[] = [
            'predicted' => $predicted_confidence,
            'actual' => $actual_accuracy,
            'error' => $predicted_confidence - $actual_accuracy,
            'timestamp' => microtime(true)
        ];

        // Limit calibration history
        if (count($this->confidence_calibration) > 500) {
            array_shift($this->confidence_calibration);
        }
    }

    /**
     * Get calibration error (mean signed error)
     * Positive = overconfident, Negative = underconfident
     */
    private function get_calibration_error() {
        if (empty($this->confidence_calibration)) {
            return 0.0;
        }

        $errors = array_column($this->confidence_calibration, 'error');
        return array_sum($errors) / count($errors);
    }

    /**
     * Update cognitive load estimate
     *
     * @param float $load Current load [0, 1]
     */
    public function update_cognitive_load($load) {
        // Exponential moving average
        $alpha = 0.3;
        $this->current_cognitive_load = (1 - $alpha) * $this->current_cognitive_load + $alpha * $load;
    }

    /**
     * Estimate learning progress
     *
     * @param string $task_type Task type
     * @return array Learning metrics
     */
    public function estimate_learning_progress($task_type) {
        $history = array_filter($this->performance_history, function($record) use ($task_type) {
            return $record['task_type'] === $task_type;
        });

        if (count($history) < 2) {
            return [
                'trials' => count($history),
                'learning_rate' => 0.0,
                'asymptotic_performance' => 0.5,
                'progress' => 0.0
            ];
        }

        // Fit power law of learning: P(n) = a + b * n^(-c)
        $trials = array_keys($history);
        $accuracies = array_column($history, 'accuracy');

        // Simple linear regression on log-transformed data
        $learning_rate = $this->estimate_learning_rate_slope($trials, $accuracies);

        // Estimate asymptotic performance (ceiling)
        $recent_avg = array_sum(array_slice($accuracies, -10)) / min(10, count($accuracies));

        return [
            'trials' => count($history),
            'learning_rate' => $learning_rate,
            'asymptotic_performance' => $recent_avg,
            'progress' => min($recent_avg / 0.95, 1.0) // Progress toward mastery (95%)
        ];
    }

    /**
     * Estimate learning rate from trial data
     */
    private function estimate_learning_rate_slope($trials, $accuracies) {
        if (count($trials) < 2) return 0.0;

        // Simple linear regression
        $n = count($trials);
        $mean_x = array_sum($trials) / $n;
        $mean_y = array_sum($accuracies) / $n;

        $numerator = 0;
        $denominator = 0;

        for ($i = 0; $i < $n; $i++) {
            $numerator += ($trials[$i] - $mean_x) * ($accuracies[$i] - $mean_y);
            $denominator += pow($trials[$i] - $mean_x, 2);
        }

        return $denominator > 0 ? $numerator / $denominator : 0.0;
    }

    /**
     * Get past performance on task type
     */
    private function get_past_performance($task_type) {
        $history = array_filter($this->performance_history, function($record) use ($task_type) {
            return $record['task_type'] === $task_type && isset($record['accuracy']);
        });

        if (empty($history)) {
            return null;
        }

        $accuracies = array_column($history, 'accuracy');

        return [
            'avg_accuracy' => array_sum($accuracies) / count($accuracies),
            'count' => count($history),
            'recent_accuracy' => end($accuracies)
        ];
    }

    /**
     * Get recent performance
     */
    private function get_recent_performance($task_type, $n = 10) {
        $history = array_filter($this->performance_history, function($record) use ($task_type) {
            return $record['task_type'] === $task_type && isset($record['accuracy']);
        });

        if (empty($history)) {
            return null;
        }

        $recent = array_slice($history, -$n);
        $accuracies = array_column($recent, 'accuracy');

        return [
            'avg_accuracy' => array_sum($accuracies) / count($accuracies),
            'count' => count($recent)
        ];
    }

    /**
     * Count past encounters with task type
     */
    private function count_past_encounters($task_type) {
        return count(array_filter($this->performance_history, function($record) use ($task_type) {
            return $record['task_type'] === $task_type;
        }));
    }

    /**
     * Get recent average accuracy across all tasks
     */
    private function get_recent_average_accuracy($n = 10) {
        $recent = array_slice($this->performance_history, -$n);
        $accuracies = array_filter(array_column($recent, 'accuracy'), function($val) {
            return $val !== null;
        });

        if (empty($accuracies)) {
            return null;
        }

        return array_sum($accuracies) / count($accuracies);
    }

    /**
     * Calculate accuracy
     */
    private function calculate_accuracy($output, $expected) {
        if ($output === $expected) {
            return 1.0;
        }

        // For numeric values
        if (is_numeric($output) && is_numeric($expected)) {
            $error = abs($output - $expected);
            $magnitude = max(abs($expected), 1);
            return max(0.0, 1.0 - ($error / $magnitude));
        }

        // For strings
        if (is_string($output) && is_string($expected)) {
            $max_len = max(strlen($output), strlen($expected));
            if ($max_len === 0) return 1.0;

            $distance = levenshtein(
                substr($output, 0, 255),
                substr($expected, 0, 255)
            );
            return max(0.0, 1.0 - ($distance / $max_len));
        }

        // For arrays
        if (is_array($output) && is_array($expected)) {
            $intersection = count(array_intersect_assoc($output, $expected));
            $union = count(array_unique(array_merge(array_keys($output), array_keys($expected))));
            return $union > 0 ? $intersection / $union : 0.0;
        }

        return 0.0;
    }

    /**
     * Get metacognitive state
     */
    public function get_state() {
        return [
            'cognitive_load' => $this->current_cognitive_load,
            'overconfidence_bias' => $this->overconfidence_bias,
            'calibration_error' => $this->get_calibration_error(),
            'recent_accuracy' => $this->get_recent_average_accuracy(10),
            'total_trials' => count($this->performance_history)
        ];
    }
}
