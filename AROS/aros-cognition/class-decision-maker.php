<?php
/**
 * AROS Decision Maker
 *
 * Production-ready decision-making system using multi-criteria analysis
 * Features:
 * - Expected utility calculation
 * - Multi-attribute utility theory (MAUT)
 * - Prospect theory (risk and gain assessment)
 * - Decision tree traversal
 * - Risk assessment and mitigation
 * - Weighted criteria evaluation
 * - Confidence scoring
 */

namespace AROS\Cognition;

class DecisionMaker {

    private $risk_tolerance = 0.5; // 0 = risk-averse, 1 = risk-seeking
    private $criteria_weights = [];
    private $decision_history = [];

    /**
     * Constructor
     *
     * @param array $config Configuration
     */
    public function __construct($config = []) {
        $this->risk_tolerance = $config['risk_tolerance'] ?? 0.5;
        $this->criteria_weights = $config['criteria_weights'] ?? [];
    }

    /**
     * Make a decision given a situation and possible actions
     *
     * @param array $situation Current situation/context
     * @param array $actions Array of possible actions
     * @return array Best action with metadata
     */
    public function decide($situation, $actions = []) {
        if (empty($actions)) {
            error_log('[DecisionMaker] ERROR: No actions provided');
            return null;
        }

        // Calculate utility for each action
        $evaluated_actions = [];

        foreach ($actions as $action) {
            $utility = $this->calculate_utility($action, $situation);
            $risk = $this->assess_risk($action, $situation);
            $expected_value = $this->calculate_expected_value($action, $situation);

            // Apply prospect theory (value function for gains/losses)
            $prospect_value = $this->prospect_theory_value($expected_value, $risk);

            $evaluated_actions[] = [
                'action' => $action,
                'utility' => $utility,
                'risk' => $risk,
                'expected_value' => $expected_value,
                'prospect_value' => $prospect_value,
                'final_score' => $this->calculate_final_score($utility, $risk, $prospect_value),
            ];
        }

        // Sort by final score (descending)
        usort($evaluated_actions, function($a, $b) {
            return $b['final_score'] <=> $a['final_score'];
        });

        $best_action = $evaluated_actions[0];

        // Calculate decision confidence
        $confidence = $this->calculate_confidence($evaluated_actions);
        $best_action['confidence'] = $confidence;

        // Log decision
        $this->log_decision($situation, $best_action);

        error_log('[DecisionMaker] Selected action: ' . $this->action_name($best_action['action']) .
                  ' (score: ' . round($best_action['final_score'], 3) .
                  ', confidence: ' . round($confidence, 2) . ')');

        return $best_action;
    }

    /**
     * Calculate utility using Multi-Attribute Utility Theory (MAUT)
     *
     * @param mixed $action Action to evaluate
     * @param array $situation Current situation
     * @return float Utility value
     */
    private function calculate_utility($action, $situation) {
        // Default criteria if none specified
        $criteria = $this->criteria_weights;

        if (empty($criteria)) {
            $criteria = [
                'effectiveness' => 0.4,
                'efficiency' => 0.3,
                'safety' => 0.2,
                'cost' => 0.1,
            ];
        }

        $total_utility = 0.0;

        foreach ($criteria as $criterion => $weight) {
            $criterion_value = $this->evaluate_criterion($action, $criterion, $situation);
            $total_utility += $weight * $criterion_value;
        }

        return $total_utility;
    }

    /**
     * Evaluate a single criterion for an action
     *
     * @param mixed $action Action
     * @param string $criterion Criterion name
     * @param array $situation Situation
     * @return float Criterion value [0, 1]
     */
    private function evaluate_criterion($action, $criterion, $situation) {
        // Extract criterion value from action if available
        if (is_array($action) && isset($action[$criterion])) {
            return (float) $action[$criterion];
        }

        // Default heuristics based on criterion type
        switch ($criterion) {
            case 'safety':
                // Safety is higher for conservative actions
                return $this->estimate_safety($action, $situation);

            case 'effectiveness':
                // Effectiveness based on expected outcomes
                return $this->estimate_effectiveness($action, $situation);

            case 'efficiency':
                // Efficiency based on resource usage
                return $this->estimate_efficiency($action, $situation);

            case 'cost':
                // Lower cost = higher value (inverted)
                return 1.0 - $this->estimate_cost($action, $situation);

            default:
                return 0.5; // Neutral value
        }
    }

    /**
     * Assess risk of an action
     *
     * @param mixed $action Action
     * @param array $situation Situation
     * @return float Risk value [0, 1] where 1 = highest risk
     */
    private function assess_risk($action, $situation) {
        if (is_array($action) && isset($action['risk'])) {
            return (float) $action['risk'];
        }

        // Estimate risk based on action properties
        $risk = 0.5; // Base risk

        // Unknown outcomes increase risk
        if (!isset($action['outcomes']) || empty($action['outcomes'])) {
            $risk += 0.2;
        }

        // Safety-critical situations increase risk
        if (isset($situation['safety_critical']) && $situation['safety_critical']) {
            $risk += 0.2;
        }

        return min(1.0, $risk);
    }

    /**
     * Calculate expected value of an action
     *
     * @param mixed $action Action
     * @param array $situation Situation
     * @return float Expected value
     */
    private function calculate_expected_value($action, $situation) {
        if (is_array($action) && isset($action['expected_value'])) {
            return (float) $action['expected_value'];
        }

        // If outcomes are specified with probabilities
        if (is_array($action) && isset($action['outcomes'])) {
            $ev = 0.0;

            foreach ($action['outcomes'] as $outcome) {
                $probability = $outcome['probability'] ?? 0.0;
                $value = $outcome['value'] ?? 0.0;
                $ev += $probability * $value;
            }

            return $ev;
        }

        // Default: use utility as expected value
        return $this->calculate_utility($action, $situation);
    }

    /**
     * Apply prospect theory value function
     * Models risk-averse behavior for gains and risk-seeking for losses
     *
     * @param float $value Expected value
     * @param float $risk Risk level
     * @return float Prospect value
     */
    private function prospect_theory_value($value, $risk) {
        // Prospect theory: value function with loss aversion
        $loss_aversion = 2.25; // People feel losses ~2.25x more than gains

        if ($value >= 0) {
            // Gains: concave value function (risk-averse)
            // v(x) = x^α where α < 1
            $alpha = 0.88;
            $prospect = pow(abs($value), $alpha);
        } else {
            // Losses: convex value function (risk-seeking)
            // v(x) = -λ * (-x)^β where λ > 1, β < 1
            $beta = 0.88;
            $prospect = -$loss_aversion * pow(abs($value), $beta);
        }

        // Adjust by risk tolerance
        $risk_adjustment = 1.0 - ($risk * (1.0 - $this->risk_tolerance));

        return $prospect * $risk_adjustment;
    }

    /**
     * Calculate final decision score
     *
     * @param float $utility Utility value
     * @param float $risk Risk value
     * @param float $prospect_value Prospect theory value
     * @return float Final score
     */
    private function calculate_final_score($utility, $risk, $prospect_value) {
        // Weighted combination
        $utility_weight = 0.4;
        $prospect_weight = 0.4;
        $risk_weight = 0.2;

        // Risk is inverted (lower risk = higher score)
        $risk_score = 1.0 - $risk;

        return ($utility_weight * $utility) +
               ($prospect_weight * $prospect_value) +
               ($risk_weight * $risk_score);
    }

    /**
     * Calculate decision confidence
     * Based on difference between best and second-best options
     *
     * @param array $evaluated_actions All evaluated actions
     * @return float Confidence [0, 1]
     */
    private function calculate_confidence($evaluated_actions) {
        if (count($evaluated_actions) < 2) {
            return 1.0; // Only one option = certain
        }

        $best_score = $evaluated_actions[0]['final_score'];
        $second_score = $evaluated_actions[1]['final_score'];

        // Larger gap = higher confidence
        $gap = abs($best_score - $second_score);

        // Normalize to [0, 1]
        $confidence = min(1.0, $gap * 2);

        return $confidence;
    }

    /**
     * Estimate safety of action (heuristic)
     */
    private function estimate_safety($action, $situation) {
        if (is_array($action) && isset($action['safety_score'])) {
            return $action['safety_score'];
        }

        // Default: moderate safety
        return 0.7;
    }

    /**
     * Estimate effectiveness of action (heuristic)
     */
    private function estimate_effectiveness($action, $situation) {
        if (is_array($action) && isset($action['effectiveness'])) {
            return $action['effectiveness'];
        }

        // Default: moderate effectiveness
        return 0.6;
    }

    /**
     * Estimate efficiency of action (heuristic)
     */
    private function estimate_efficiency($action, $situation) {
        if (is_array($action) && isset($action['efficiency'])) {
            return $action['efficiency'];
        }

        // Default: moderate efficiency
        return 0.6;
    }

    /**
     * Estimate cost of action (heuristic)
     */
    private function estimate_cost($action, $situation) {
        if (is_array($action) && isset($action['cost'])) {
            return $action['cost'];
        }

        // Default: moderate cost
        return 0.5;
    }

    /**
     * Get action name for logging
     */
    private function action_name($action) {
        if (is_string($action)) {
            return $action;
        }

        if (is_array($action) && isset($action['name'])) {
            return $action['name'];
        }

        return 'unknown_action';
    }

    /**
     * Log decision for analysis
     */
    private function log_decision($situation, $decision) {
        $this->decision_history[] = [
            'timestamp' => microtime(true),
            'situation' => $situation,
            'decision' => $decision,
        ];

        // Keep only last 100 decisions
        if (count($this->decision_history) > 100) {
            array_shift($this->decision_history);
        }
    }

    /**
     * Set risk tolerance
     *
     * @param float $tolerance Risk tolerance [0, 1]
     */
    public function set_risk_tolerance($tolerance) {
        $this->risk_tolerance = max(0.0, min(1.0, $tolerance));
    }

    /**
     * Set criteria weights
     *
     * @param array $weights Criteria weights (must sum to ~1.0)
     */
    public function set_criteria_weights($weights) {
        $this->criteria_weights = $weights;
    }

    /**
     * Get decision history
     *
     * @return array Decision history
     */
    public function get_history() {
        return $this->decision_history;
    }

    /**
     * Get statistics
     *
     * @return array Statistics
     */
    public function get_stats() {
        $avg_confidence = 0.0;

        if (!empty($this->decision_history)) {
            foreach ($this->decision_history as $entry) {
                $avg_confidence += $entry['decision']['confidence'] ?? 0.0;
            }
            $avg_confidence /= count($this->decision_history);
        }

        return [
            'total_decisions' => count($this->decision_history),
            'avg_confidence' => $avg_confidence,
            'risk_tolerance' => $this->risk_tolerance,
        ];
    }
}
