<?php
/**
 * Reinforcement Learner
 * Implements Q-Learning and Deep Q-Network (DQN) for robot policy learning
 */

namespace AROS\Learning;

class ReinforcementLearner {

    private $learning_rate;
    private $discount_factor;
    private $exploration_rate;
    private $q_table = [];
    private $policy = [];

    public function __construct() {
        $this->learning_rate = get_option('aros_learning_rate', 0.001);
        $this->discount_factor = get_option('aros_discount_factor', 0.99);
        $this->exploration_rate = get_option('aros_exploration_rate', 0.1);

        $this->load_policy();
    }

    /**
     * Learn from experience (Q-Learning update)
     */
    public function learn($state, $action, $reward, $next_state, $done) {
        $state_key = $this->serialize_state($state);
        $next_state_key = $this->serialize_state($next_state);

        // Initialize Q-value if not exists
        if (!isset($this->q_table[$state_key])) {
            $this->q_table[$state_key] = [];
        }

        $action_key = $this->serialize_action($action);

        if (!isset($this->q_table[$state_key][$action_key])) {
            $this->q_table[$state_key][$action_key] = 0.0;
        }

        // Q-Learning update rule
        $current_q = $this->q_table[$state_key][$action_key];

        if ($done) {
            $target_q = $reward;
        } else {
            $max_next_q = $this->get_max_q($next_state_key);
            $target_q = $reward + ($this->discount_factor * $max_next_q);
        }

        $new_q = $current_q + ($this->learning_rate * ($target_q - $current_q));

        $this->q_table[$state_key][$action_key] = $new_q;

        // Update policy
        $this->update_policy_for_state($state_key);

        return $new_q;
    }

    /**
     * Get best action for state (epsilon-greedy)
     */
    public function get_action($state) {
        // Exploration vs exploitation
        if (mt_rand() / mt_getrandmax() < $this->exploration_rate) {
            return $this->get_random_action();
        }

        $state_key = $this->serialize_state($state);

        if (!isset($this->policy[$state_key])) {
            return $this->get_random_action();
        }

        return $this->policy[$state_key]['action'];
    }

    /**
     * Get maximum Q-value for state
     */
    private function get_max_q($state_key) {
        if (!isset($this->q_table[$state_key]) || empty($this->q_table[$state_key])) {
            return 0.0;
        }

        return max($this->q_table[$state_key]);
    }

    /**
     * Update policy for specific state
     */
    private function update_policy_for_state($state_key) {
        if (!isset($this->q_table[$state_key])) {
            return;
        }

        $best_action = null;
        $best_q = -PHP_FLOAT_MAX;

        foreach ($this->q_table[$state_key] as $action => $q_value) {
            if ($q_value > $best_q) {
                $best_q = $q_value;
                $best_action = $action;
            }
        }

        $this->policy[$state_key] = [
            'action' => $best_action,
            'q_value' => $best_q,
        ];
    }

    /**
     * Serialize state to string key
     */
    private function serialize_state($state) {
        return md5(json_encode($state));
    }

    /**
     * Serialize action to string key
     */
    private function serialize_action($action) {
        return md5(json_encode($action));
    }

    /**
     * Get random action
     */
    private function get_random_action() {
        $actions = $this->get_available_actions();
        return $actions[array_rand($actions)];
    }

    /**
     * Get available actions
     */
    private function get_available_actions() {
        return [
            'move_forward',
            'move_backward',
            'turn_left',
            'turn_right',
            'grasp',
            'release',
            'wait',
        ];
    }

    /**
     * Save policy to database
     */
    public function save_policy() {
        update_option('aros_q_table', $this->q_table);
        update_option('aros_policy', $this->policy);
    }

    /**
     * Load policy from database
     */
    private function load_policy() {
        $this->q_table = get_option('aros_q_table', []);
        $this->policy = get_option('aros_policy', []);
    }

    /**
     * Get policy statistics
     */
    public function get_stats() {
        return [
            'states_learned' => count($this->q_table),
            'policy_size' => count($this->policy),
            'learning_rate' => $this->learning_rate,
            'exploration_rate' => $this->exploration_rate,
        ];
    }
}
