<?php
/**
 * Decision Maker
 *
 * Multi-criteria decision making with utility theory and heuristics
 * Based on prospect theory, expected utility, and dual-process theory
 *
 * Features:
 * - Multi-attribute utility theory (MAUT)
 * - Prospect theory (loss aversion, reference points)
 * - Fast heuristics (recognition, take-the-best)
 * - Satisficing (good enough vs. optimal)
 * - Risk assessment and uncertainty handling
 * - Temporal discounting (present bias)
 * - Regret minimization
 */

namespace AevovCognitiveEngine;

class DecisionMaker {

    private $decision_history = [];
    private $risk_tolerance = 0.5; // [0=risk-averse, 1=risk-seeking]
    private $loss_aversion = 2.0; // Losses hurt more than gains feel good
    private $discount_rate = 0.1; // Temporal discounting rate
    private $satisficing_threshold = 0.7; // Good enough threshold
    private $deliberation_time_limit = 5.0; // Max time for deliberation (seconds)

    /**
     * Constructor
     */
    public function __construct($config = []) {
        $this->risk_tolerance = $config['risk_tolerance'] ?? 0.5;
        $this->loss_aversion = $config['loss_aversion'] ?? 2.0;
        $this->discount_rate = $config['discount_rate'] ?? 0.1;
        $this->satisficing_threshold = $config['satisficing_threshold'] ?? 0.7;
    }

    /**
     * Make decision among alternatives
     *
     * @param array $alternatives Decision options
     * @param array $criteria Decision criteria with weights
     * @param string $strategy 'optimal', 'satisficing', or 'heuristic'
     * @return array Selected alternative with justification
     */
    public function decide($alternatives, $criteria, $strategy = 'optimal') {
        if (empty($alternatives)) {
            return null;
        }

        $start_time = microtime(true);

        $decision = null;

        switch ($strategy) {
            case 'optimal':
                $decision = $this->optimal_decision($alternatives, $criteria);
                break;

            case 'satisficing':
                $decision = $this->satisficing_decision($alternatives, $criteria);
                break;

            case 'heuristic':
                $decision = $this->heuristic_decision($alternatives, $criteria);
                break;

            default:
                // Adaptive: choose strategy based on context
                $decision = $this->adaptive_decision($alternatives, $criteria);
        }

        $elapsed_time = microtime(true) - $start_time;

        // Record decision in history
        $this->decision_history[] = [
            'alternatives' => $alternatives,
            'selected' => $decision,
            'strategy' => $strategy,
            'time' => microtime(true),
            'deliberation_time' => $elapsed_time
        ];

        return $decision;
    }

    /**
     * Optimal decision using multi-attribute utility theory
     */
    private function optimal_decision($alternatives, $criteria) {
        $utilities = [];

        foreach ($alternatives as $id => $alternative) {
            $utility = $this->calculate_expected_utility($alternative, $criteria);
            $utilities[$id] = $utility;
        }

        // Select maximum utility
        arsort($utilities);
        $best_id = array_key_first($utilities);

        return [
            'alternative' => $alternatives[$best_id],
            'id' => $best_id,
            'utility' => $utilities[$best_id],
            'justification' => 'Maximum expected utility',
            'all_utilities' => $utilities
        ];
    }

    /**
     * Satisficing decision: find first "good enough" option
     * Herbert Simon's bounded rationality
     */
    private function satisficing_decision($alternatives, $criteria) {
        // Randomize order to avoid order bias
        $shuffled_alternatives = $alternatives;
        shuffle($shuffled_alternatives);

        foreach ($shuffled_alternatives as $id => $alternative) {
            $utility = $this->calculate_expected_utility($alternative, $criteria);

            // Accept first option above threshold
            if ($utility >= $this->satisficing_threshold) {
                return [
                    'alternative' => $alternative,
                    'id' => $id,
                    'utility' => $utility,
                    'justification' => 'Satisficing: good enough',
                    'threshold' => $this->satisficing_threshold
                ];
            }
        }

        // If no satisficing option found, return best
        return $this->optimal_decision($alternatives, $criteria);
    }

    /**
     * Heuristic decision using fast-and-frugal trees
     */
    private function heuristic_decision($alternatives, $criteria) {
        // Recognition heuristic: prefer recognized options
        $recognized = array_filter($alternatives, function($alt) {
            return ($alt['recognized'] ?? false) === true;
        });

        if (count($recognized) === 1) {
            $id = array_key_first($recognized);
            return [
                'alternative' => $recognized[$id],
                'id' => $id,
                'utility' => 0.8,
                'justification' => 'Recognition heuristic',
                'heuristic' => 'recognition'
            ];
        }

        // Take-the-best heuristic: use most important criterion
        return $this->take_the_best_heuristic($alternatives, $criteria);
    }

    /**
     * Take-the-best heuristic
     * Use most important criterion to discriminate
     */
    private function take_the_best_heuristic($alternatives, $criteria) {
        // Sort criteria by weight (importance)
        uasort($criteria, function($a, $b) {
            $weight_a = $a['weight'] ?? 1.0;
            $weight_b = $b['weight'] ?? 1.0;
            return $b <=> $a;
        });

        // Try each criterion in order of importance
        foreach ($criteria as $criterion_name => $criterion_config) {
            $values = [];

            // Get criterion values for all alternatives
            foreach ($alternatives as $id => $alternative) {
                if (isset($alternative[$criterion_name])) {
                    $values[$id] = $alternative[$criterion_name];
                }
            }

            // If criterion discriminates, select best on this criterion
            if (count(array_unique($values)) > 1) {
                arsort($values);
                $best_id = array_key_first($values);

                return [
                    'alternative' => $alternatives[$best_id],
                    'id' => $best_id,
                    'utility' => 0.75,
                    'justification' => "Take-the-best: {$criterion_name}",
                    'heuristic' => 'take_the_best',
                    'discriminating_criterion' => $criterion_name
                ];
            }
        }

        // If no criterion discriminates, pick randomly
        $random_id = array_rand($alternatives);
        return [
            'alternative' => $alternatives[$random_id],
            'id' => $random_id,
            'utility' => 0.5,
            'justification' => 'Random selection (no discriminating criterion)',
            'heuristic' => 'random'
        ];
    }

    /**
     * Adaptive decision: choose strategy based on context
     */
    private function adaptive_decision($alternatives, $criteria) {
        $num_alternatives = count($alternatives);
        $num_criteria = count($criteria);

        // Simple problems: use heuristics
        if ($num_alternatives <= 3 && $num_criteria <= 2) {
            return $this->heuristic_decision($alternatives, $criteria);
        }

        // Medium complexity: satisficing
        if ($num_alternatives <= 10 && $num_criteria <= 5) {
            return $this->satisficing_decision($alternatives, $criteria);
        }

        // Complex problems: optimal (if time permits)
        // Otherwise fall back to satisficing
        $estimated_time = $num_alternatives * $num_criteria * 0.01;

        if ($estimated_time < $this->deliberation_time_limit) {
            return $this->optimal_decision($alternatives, $criteria);
        } else {
            return $this->satisficing_decision($alternatives, $criteria);
        }
    }

    /**
     * Calculate expected utility with prospect theory
     */
    private function calculate_expected_utility($alternative, $criteria) {
        $total_utility = 0.0;
        $total_weight = 0.0;

        // Reference point for gains/losses
        $reference = $this->get_reference_point($criteria);

        foreach ($criteria as $criterion_name => $criterion_config) {
            if (!isset($alternative[$criterion_name])) {
                continue;
            }

            $value = $alternative[$criterion_name];
            $weight = $criterion_config['weight'] ?? 1.0;
            $maximize = $criterion_config['maximize'] ?? true;

            // Normalize value
            $normalized_value = $this->normalize_criterion_value(
                $value,
                $criterion_config
            );

            // Apply prospect theory value function
            $utility_value = $this->prospect_theory_value(
                $normalized_value,
                $reference[$criterion_name] ?? 0.5,
                $maximize
            );

            // Apply probability weighting if uncertain
            if (isset($alternative[$criterion_name . '_probability'])) {
                $probability = $alternative[$criterion_name . '_probability'];
                $weighted_prob = $this->probability_weighting($probability);
                $utility_value *= $weighted_prob;
            }

            // Apply temporal discounting if future outcome
            if (isset($alternative['time_to_outcome'])) {
                $discount_factor = $this->temporal_discount($alternative['time_to_outcome']);
                $utility_value *= $discount_factor;
            }

            $total_utility += $utility_value * $weight;
            $total_weight += $weight;
        }

        // Normalize by total weight
        $normalized_utility = $total_weight > 0 ? $total_utility / $total_weight : 0.0;

        // Apply risk adjustment
        if (isset($alternative['risk'])) {
            $normalized_utility *= $this->risk_adjustment($alternative['risk']);
        }

        return max(0.0, min(1.0, $normalized_utility));
    }

    /**
     * Prospect theory value function
     * S-shaped: risk-averse for gains, risk-seeking for losses
     */
    private function prospect_theory_value($value, $reference, $maximize = true) {
        if (!$maximize) {
            $value = 1.0 - $value; // Invert for minimization criteria
        }

        $deviation = $value - $reference;

        if ($deviation >= 0) {
            // Gains: concave function (risk averse)
            return pow($deviation, 0.88);
        } else {
            // Losses: convex function (risk seeking) + loss aversion
            return -$this->loss_aversion * pow(-$deviation, 0.88);
        }
    }

    /**
     * Probability weighting function
     * People overweight small probabilities, underweight large ones
     */
    private function probability_weighting($p) {
        // Tversky & Kahneman's weighting function
        $gamma = 0.61;
        return pow($p, $gamma) / pow(
            pow($p, $gamma) + pow(1 - $p, $gamma),
            1 / $gamma
        );
    }

    /**
     * Temporal discounting
     * Future outcomes valued less than immediate ones
     */
    private function temporal_discount($time_delay) {
        // Hyperbolic discounting (more realistic than exponential)
        return 1.0 / (1.0 + $this->discount_rate * $time_delay);
    }

    /**
     * Risk adjustment based on risk tolerance
     */
    private function risk_adjustment($risk_level) {
        // risk_level: 0 (no risk) to 1 (high risk)
        // risk_tolerance: 0 (risk-averse) to 1 (risk-seeking)

        $risk_penalty = $risk_level * (1 - $this->risk_tolerance);
        return 1.0 - $risk_penalty;
    }

    /**
     * Normalize criterion value to [0, 1]
     */
    private function normalize_criterion_value($value, $config) {
        $min = $config['min'] ?? 0;
        $max = $config['max'] ?? 1;

        if ($max === $min) {
            return 0.5;
        }

        $normalized = ($value - $min) / ($max - $min);
        return max(0.0, min(1.0, $normalized));
    }

    /**
     * Get reference point for prospect theory
     * Can be status quo, aspiration level, or average
     */
    private function get_reference_point($criteria) {
        $reference = [];

        foreach ($criteria as $criterion_name => $config) {
            // Use aspiration level if specified
            if (isset($config['aspiration'])) {
                $reference[$criterion_name] = $this->normalize_criterion_value(
                    $config['aspiration'],
                    $config
                );
            } else {
                // Default to midpoint
                $reference[$criterion_name] = 0.5;
            }
        }

        return $reference;
    }

    /**
     * Evaluate decision quality post-hoc
     *
     * @param mixed $outcome Actual outcome
     * @param mixed $expected_outcome Expected outcome
     * @return array Evaluation metrics
     */
    public function evaluate_decision($outcome, $expected_outcome) {
        // Prediction error
        $error = abs($outcome - $expected_outcome);

        // Regret calculation
        $regret = $this->calculate_regret($outcome);

        // Update risk tolerance based on outcomes
        $this->update_risk_tolerance($outcome, $expected_outcome);

        return [
            'prediction_error' => $error,
            'regret' => $regret,
            'outcome' => $outcome,
            'expected' => $expected_outcome
        ];
    }

    /**
     * Calculate regret (comparing to foregone alternatives)
     */
    private function calculate_regret($actual_outcome) {
        if (empty($this->decision_history)) {
            return 0.0;
        }

        $last_decision = end($this->decision_history);

        // Find best alternative that wasn't chosen
        $best_foregone = 0.0;
        if (isset($last_decision['all_utilities'])) {
            $utilities = $last_decision['all_utilities'];
            unset($utilities[$last_decision['id']]);

            if (!empty($utilities)) {
                $best_foregone = max($utilities);
            }
        }

        // Regret = what could have been - what was
        $regret = max(0, $best_foregone - $actual_outcome);

        return $regret;
    }

    /**
     * Update risk tolerance based on experience
     * Learning from outcomes
     */
    private function update_risk_tolerance($outcome, $expected) {
        $learning_rate = 0.05;

        if ($outcome > $expected) {
            // Positive surprise: slightly increase risk tolerance
            $this->risk_tolerance += $learning_rate * ($outcome - $expected);
        } else {
            // Negative surprise: decrease risk tolerance
            $this->risk_tolerance -= $learning_rate * ($expected - $outcome);
        }

        // Keep in bounds [0, 1]
        $this->risk_tolerance = max(0.0, min(1.0, $this->risk_tolerance));
    }

    /**
     * Multi-objective optimization (Pareto frontier)
     *
     * @param array $alternatives Alternatives to evaluate
     * @param array $objectives Objectives (criteria)
     * @return array Pareto-optimal alternatives
     */
    public function find_pareto_optimal($alternatives, $objectives) {
        $pareto_set = [];

        foreach ($alternatives as $id_a => $alt_a) {
            $is_dominated = false;

            foreach ($alternatives as $id_b => $alt_b) {
                if ($id_a === $id_b) continue;

                // Check if alt_b dominates alt_a
                if ($this->dominates($alt_b, $alt_a, $objectives)) {
                    $is_dominated = true;
                    break;
                }
            }

            if (!$is_dominated) {
                $pareto_set[$id_a] = $alt_a;
            }
        }

        return $pareto_set;
    }

    /**
     * Check if alternative A dominates alternative B
     */
    private function dominates($a, $b, $objectives) {
        $better_in_at_least_one = false;
        $better_or_equal_in_all = true;

        foreach ($objectives as $objective => $config) {
            $maximize = $config['maximize'] ?? true;

            $value_a = $a[$objective] ?? 0;
            $value_b = $b[$objective] ?? 0;

            if ($maximize) {
                if ($value_a > $value_b) {
                    $better_in_at_least_one = true;
                } elseif ($value_a < $value_b) {
                    $better_or_equal_in_all = false;
                }
            } else {
                if ($value_a < $value_b) {
                    $better_in_at_least_one = true;
                } elseif ($value_a > $value_b) {
                    $better_or_equal_in_all = false;
                }
            }
        }

        return $better_in_at_least_one && $better_or_equal_in_all;
    }

    /**
     * Get decision history
     */
    public function get_decision_history() {
        return $this->decision_history;
    }

    /**
     * Get current decision-making parameters
     */
    public function get_parameters() {
        return [
            'risk_tolerance' => $this->risk_tolerance,
            'loss_aversion' => $this->loss_aversion,
            'discount_rate' => $this->discount_rate,
            'satisficing_threshold' => $this->satisficing_threshold
        ];
    }
}
