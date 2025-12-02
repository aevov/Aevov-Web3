<?php
/**
 * Attention Mechanism
 *
 * Implements selective attention with bottom-up and top-down processing
 * Based on Broadbent's filter theory and Treisman's attenuation model
 *
 * Features:
 * - Selective attention (filter irrelevant stimuli)
 * - Divided attention (multi-task processing)
 * - Sustained attention (vigilance over time)
 * - Attention switching (task switching)
 * - Saliency map (bottom-up attention)
 * - Goal-directed attention (top-down)
 * - Attentional blink (temporary blindness after attended event)
 */

namespace AevovCognitiveEngine;

class AttentionMechanism {

    private $attention_capacity = 100.0; // Total attention resources
    private $current_focus = [];
    private $attention_allocation = [];
    private $saliency_map = [];
    private $goals = [];
    private $vigilance_decay_rate = 0.02; // Attention fatigue
    private $current_vigilance = 1.0;
    private $switch_cost = 0.15; // Cost of switching attention
    private $blink_duration = 0.5; // Attentional blink in seconds
    private $blink_end_time = 0.0;

    /**
     * Constructor
     */
    public function __construct($config = []) {
        $this->attention_capacity = $config['capacity'] ?? 100.0;
        $this->vigilance_decay_rate = $config['vigilance_decay'] ?? 0.02;
        $this->switch_cost = $config['switch_cost'] ?? 0.15;
    }

    /**
     * Attend to stimulus using combined bottom-up and top-down processing
     *
     * @param array $stimuli Array of stimuli to process
     * @param array $current_goals Current cognitive goals
     * @return array Attended stimuli with attention weights
     */
    public function attend($stimuli, $current_goals = []) {
        $this->goals = $current_goals;

        // Check for attentional blink
        if ($this->is_in_attentional_blink()) {
            return [];
        }

        // Calculate saliency map (bottom-up attention)
        $this->compute_saliency_map($stimuli);

        // Calculate goal-relevance (top-down attention)
        $goal_relevance = $this->compute_goal_relevance($stimuli);

        // Combine bottom-up and top-down
        $attention_weights = $this->combine_attention_signals(
            $this->saliency_map,
            $goal_relevance
        );

        // Apply attention capacity constraint
        $attended_stimuli = $this->apply_capacity_constraint(
            $stimuli,
            $attention_weights
        );

        // Update vigilance (attention fatigue)
        $this->update_vigilance();

        // Update current focus
        $this->current_focus = $attended_stimuli;

        return $attended_stimuli;
    }

    /**
     * Compute bottom-up saliency map
     * Stimuli with unusual features grab attention automatically
     */
    private function compute_saliency_map($stimuli) {
        $this->saliency_map = [];

        if (empty($stimuli)) {
            return;
        }

        // Calculate feature statistics for normalization
        $feature_stats = $this->calculate_feature_statistics($stimuli);

        foreach ($stimuli as $id => $stimulus) {
            $saliency = 0.0;

            // Intensity saliency (bright, loud, etc.)
            if (isset($stimulus['intensity'])) {
                $intensity_deviation = abs(
                    $stimulus['intensity'] - $feature_stats['intensity']['mean']
                ) / max($feature_stats['intensity']['std'], 0.01);

                $saliency += $intensity_deviation * 0.3;
            }

            // Novelty saliency (unusual or unexpected)
            if (isset($stimulus['novelty'])) {
                $saliency += $stimulus['novelty'] * 0.25;
            }

            // Motion saliency (moving objects attract attention)
            if (isset($stimulus['motion'])) {
                $saliency += $stimulus['motion'] * 0.2;
            }

            // Contrast saliency (different from surroundings)
            $contrast = $this->calculate_contrast($stimulus, $stimuli);
            $saliency += $contrast * 0.25;

            $this->saliency_map[$id] = min($saliency, 1.0);
        }
    }

    /**
     * Compute goal relevance (top-down attention)
     */
    private function compute_goal_relevance($stimuli) {
        $relevance_map = [];

        if (empty($this->goals)) {
            // No goals: uniform relevance
            foreach ($stimuli as $id => $stimulus) {
                $relevance_map[$id] = 0.5;
            }
            return $relevance_map;
        }

        foreach ($stimuli as $id => $stimulus) {
            $max_relevance = 0.0;

            foreach ($this->goals as $goal) {
                $relevance = $this->calculate_goal_relevance($stimulus, $goal);
                $max_relevance = max($max_relevance, $relevance);
            }

            $relevance_map[$id] = $max_relevance;
        }

        return $relevance_map;
    }

    /**
     * Calculate how relevant a stimulus is to a goal
     */
    private function calculate_goal_relevance($stimulus, $goal) {
        $relevance = 0.0;

        // Feature matching
        if (isset($goal['target_features'])) {
            $matching_features = 0;
            $total_features = count($goal['target_features']);

            foreach ($goal['target_features'] as $feature => $target_value) {
                if (isset($stimulus[$feature])) {
                    // Calculate feature similarity
                    $similarity = $this->calculate_feature_similarity(
                        $stimulus[$feature],
                        $target_value
                    );
                    $matching_features += $similarity;
                }
            }

            if ($total_features > 0) {
                $relevance = $matching_features / $total_features;
            }
        }

        // Category matching
        if (isset($goal['target_category']) && isset($stimulus['category'])) {
            if ($stimulus['category'] === $goal['target_category']) {
                $relevance = max($relevance, 0.8);
            }
        }

        // Location matching (spatial attention)
        if (isset($goal['target_location']) && isset($stimulus['location'])) {
            $location_match = $this->calculate_location_match(
                $stimulus['location'],
                $goal['target_location']
            );
            $relevance = max($relevance, $location_match);
        }

        // Weight by goal priority
        $goal_priority = $goal['priority'] ?? 0.5;
        $relevance *= (0.5 + $goal_priority * 0.5);

        return $relevance;
    }

    /**
     * Combine bottom-up (saliency) and top-down (relevance) attention
     */
    private function combine_attention_signals($saliency_map, $relevance_map) {
        $combined = [];

        // Weight parameters (adjustable based on task)
        $bottom_up_weight = 0.3;
        $top_down_weight = 0.7;

        // If no goals, rely more on bottom-up
        if (empty($this->goals)) {
            $bottom_up_weight = 0.7;
            $top_down_weight = 0.3;
        }

        foreach ($saliency_map as $id => $saliency) {
            $relevance = $relevance_map[$id] ?? 0.5;

            $combined_score =
                ($bottom_up_weight * $saliency) +
                ($top_down_weight * $relevance);

            // Apply vigilance modulation
            $combined_score *= $this->current_vigilance;

            $combined[$id] = $combined_score;
        }

        return $combined;
    }

    /**
     * Apply attention capacity constraint
     * Can only attend to limited number of stimuli simultaneously
     */
    private function apply_capacity_constraint($stimuli, $attention_weights) {
        $attended = [];
        $total_weight = 0.0;

        // Sort by attention weight (descending)
        arsort($attention_weights);

        foreach ($attention_weights as $id => $weight) {
            // Check if we have capacity remaining
            if ($total_weight + $weight <= $this->attention_capacity) {
                $attended[] = [
                    'stimulus' => $stimuli[$id],
                    'attention_weight' => $weight,
                    'id' => $id
                ];
                $total_weight += $weight;
            } else {
                // Partial attention if some capacity remains
                $remaining_capacity = $this->attention_capacity - $total_weight;
                if ($remaining_capacity > 0.1) {
                    $attended[] = [
                        'stimulus' => $stimuli[$id],
                        'attention_weight' => $remaining_capacity,
                        'id' => $id,
                        'partial' => true
                    ];
                }
                break;
            }
        }

        return $attended;
    }

    /**
     * Update vigilance (sustained attention decays over time)
     */
    private function update_vigilance() {
        // Vigilance decreases with time (attention fatigue)
        $this->current_vigilance *= (1 - $this->vigilance_decay_rate);

        // Minimum vigilance floor
        $this->current_vigilance = max($this->current_vigilance, 0.3);
    }

    /**
     * Switch attention to new target
     *
     * @param mixed $new_target New attention target
     * @return float Switch cost (processing time penalty)
     */
    public function switch_attention($new_target) {
        // Calculate similarity to current focus
        $similarity = 0.0;

        if (!empty($this->current_focus)) {
            foreach ($this->current_focus as $focused_item) {
                $sim = $this->calculate_stimulus_similarity(
                    $focused_item['stimulus'],
                    $new_target
                );
                $similarity = max($similarity, $sim);
            }
        }

        // Switch cost inversely proportional to similarity
        // Similar tasks: low switch cost
        // Dissimilar tasks: high switch cost
        $actual_switch_cost = $this->switch_cost * (1 - $similarity);

        // Apply switch cost to vigilance
        $this->current_vigilance *= (1 - $actual_switch_cost);

        // Trigger attentional blink
        $this->trigger_attentional_blink();

        return $actual_switch_cost;
    }

    /**
     * Divided attention: allocate attention across multiple tasks
     *
     * @param array $tasks Array of concurrent tasks
     * @return array Attention allocation per task
     */
    public function divide_attention($tasks) {
        $this->attention_allocation = [];

        if (empty($tasks)) {
            return [];
        }

        // Calculate task difficulty and priority
        $task_scores = [];
        $total_score = 0.0;

        foreach ($tasks as $id => $task) {
            $difficulty = $task['difficulty'] ?? 0.5;
            $priority = $task['priority'] ?? 0.5;

            // Higher difficulty and priority require more attention
            $score = ($difficulty * 0.6 + $priority * 0.4);
            $task_scores[$id] = $score;
            $total_score += $score;
        }

        // Allocate attention proportionally
        foreach ($task_scores as $id => $score) {
            $allocation = ($score / $total_score) * $this->attention_capacity;
            $this->attention_allocation[$id] = $allocation;
        }

        // Performance decreases with divided attention
        $division_penalty = 1.0 - (count($tasks) - 1) * 0.15;
        $division_penalty = max($division_penalty, 0.4);

        foreach ($this->attention_allocation as &$allocation) {
            $allocation *= $division_penalty;
        }

        return $this->attention_allocation;
    }

    /**
     * Trigger attentional blink
     * Brief period where new stimuli are not fully processed
     */
    private function trigger_attentional_blink() {
        $this->blink_end_time = microtime(true) + $this->blink_duration;
    }

    /**
     * Check if currently in attentional blink
     */
    private function is_in_attentional_blink() {
        return microtime(true) < $this->blink_end_time;
    }

    /**
     * Restore vigilance (e.g., after rest or task switch)
     *
     * @param float $amount Amount to restore [0, 1]
     */
    public function restore_vigilance($amount = 0.5) {
        $this->current_vigilance = min(
            $this->current_vigilance + $amount,
            1.0
        );
    }

    /**
     * Calculate contrast of stimulus relative to context
     */
    private function calculate_contrast($stimulus, $all_stimuli) {
        if (count($all_stimuli) <= 1) {
            return 0.5;
        }

        $differences = [];

        foreach ($all_stimuli as $other) {
            if ($other === $stimulus) continue;

            $diff = $this->calculate_stimulus_similarity($stimulus, $other);
            $differences[] = 1 - $diff; // Invert similarity to get difference
        }

        return !empty($differences) ? array_sum($differences) / count($differences) : 0.5;
    }

    /**
     * Calculate feature statistics for saliency computation
     */
    private function calculate_feature_statistics($stimuli) {
        $stats = [
            'intensity' => ['values' => [], 'mean' => 0, 'std' => 1],
            'motion' => ['values' => [], 'mean' => 0, 'std' => 1]
        ];

        // Collect values
        foreach ($stimuli as $stimulus) {
            if (isset($stimulus['intensity'])) {
                $stats['intensity']['values'][] = $stimulus['intensity'];
            }
            if (isset($stimulus['motion'])) {
                $stats['motion']['values'][] = $stimulus['motion'];
            }
        }

        // Calculate mean and std
        foreach ($stats as $feature => &$data) {
            if (!empty($data['values'])) {
                $data['mean'] = array_sum($data['values']) / count($data['values']);

                $variance = 0;
                foreach ($data['values'] as $value) {
                    $variance += pow($value - $data['mean'], 2);
                }
                $data['std'] = sqrt($variance / count($data['values']));
            }
        }

        return $stats;
    }

    /**
     * Calculate similarity between two stimuli
     */
    private function calculate_stimulus_similarity($a, $b) {
        $similarity = 0.0;
        $feature_count = 0;

        // Compare common features
        $common_features = array_intersect_key($a, $b);

        foreach ($common_features as $feature => $value_a) {
            if (in_array($feature, ['id', 'timestamp'])) continue;

            $value_b = $b[$feature];
            $feature_sim = $this->calculate_feature_similarity($value_a, $value_b);

            $similarity += $feature_sim;
            $feature_count++;
        }

        return $feature_count > 0 ? $similarity / $feature_count : 0.0;
    }

    /**
     * Calculate similarity between feature values
     */
    private function calculate_feature_similarity($a, $b) {
        if (is_numeric($a) && is_numeric($b)) {
            $diff = abs($a - $b);
            $max_val = max(abs($a), abs($b), 1);
            return 1.0 - min($diff / $max_val, 1.0);
        }

        if (is_string($a) && is_string($b)) {
            return $a === $b ? 1.0 : 0.0;
        }

        if (is_array($a) && is_array($b)) {
            $intersection = count(array_intersect_assoc($a, $b));
            $union = count(array_unique(array_merge(array_keys($a), array_keys($b))));
            return $union > 0 ? $intersection / $union : 0.0;
        }

        return $a === $b ? 1.0 : 0.0;
    }

    /**
     * Calculate location match for spatial attention
     */
    private function calculate_location_match($loc_a, $loc_b) {
        if (is_array($loc_a) && is_array($loc_b)) {
            $dx = ($loc_a['x'] ?? 0) - ($loc_b['x'] ?? 0);
            $dy = ($loc_a['y'] ?? 0) - ($loc_b['y'] ?? 0);
            $distance = sqrt($dx * $dx + $dy * $dy);

            // Gaussian attention spotlight
            $sigma = 10.0; // Attention window size
            return exp(-($distance * $distance) / (2 * $sigma * $sigma));
        }

        return $loc_a === $loc_b ? 1.0 : 0.0;
    }

    /**
     * Get current attention state
     */
    public function get_state() {
        return [
            'vigilance' => $this->current_vigilance,
            'focus_count' => count($this->current_focus),
            'in_blink' => $this->is_in_attentional_blink(),
            'attention_allocation' => $this->attention_allocation,
            'goals' => $this->goals
        ];
    }

    /**
     * Set attention goals
     */
    public function set_goals($goals) {
        $this->goals = $goals;
    }
}
