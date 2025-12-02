<?php
/**
 * Self-Improvement Engine
 * Revolutionary system that continuously improves AROS performance through
 * neural architecture evolution, reinforcement learning, and experience-based optimization
 *
 * This is the core of AROS's self-improving capabilities, integrating:
 * - NeuroArchitect for evolving optimal neural architectures
 * - Reinforcement Learning for policy optimization
 * - Experience Replay for efficient learning
 * - Model Optimization for deployment efficiency
 */

namespace AROS\Learning;

class SelfImprovementEngine {

    private $generation = 0;
    private $best_performance = 0.0;
    private $improvement_threshold = 0.01;
    private $evolution_active = true;

    public function __construct() {
        $this->generation = get_option('aros_current_generation', 0);
        $this->best_performance = get_option('aros_best_performance', 0.0);
    }

    /**
     * Main improvement loop - called periodically
     */
    public function improve() {
        if (!$this->evolution_active) {
            return;
        }

        $this->generation++;

        // 1. Collect recent experiences
        $experiences = $this->collect_experiences();

        // 2. Evaluate current performance
        $current_performance = $this->evaluate_performance($experiences);

        // 3. Evolve neural architecture if needed
        if ($this->should_evolve()) {
            $this->evolve_architecture($current_performance);
        }

        // 4. Update policy using reinforcement learning
        $this->update_policy($experiences);

        // 5. Optimize models for efficiency
        $this->optimize_models();

        // 6. Update metrics
        $this->update_metrics($current_performance);

        // 7. Prune old experiences
        $this->prune_experiences();

        update_option('aros_current_generation', $this->generation);

        do_action('aros_self_improvement_complete', [
            'generation' => $this->generation,
            'performance' => $current_performance,
            'improvement' => $current_performance - $this->best_performance,
        ]);
    }

    /**
     * Collect recent experiences from database
     */
    private function collect_experiences() {
        global $wpdb;

        $table = $wpdb->prefix . 'aros_experiences';

        // Get last 1000 experiences
        $experiences = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY id DESC LIMIT 1000",
            ARRAY_A
        );

        return $experiences;
    }

    /**
     * Evaluate current performance based on experiences
     */
    private function evaluate_performance($experiences) {
        if (empty($experiences)) {
            return 0.0;
        }

        // Calculate average reward
        $total_reward = 0;
        foreach ($experiences as $exp) {
            $total_reward += $exp['reward'];
        }

        $avg_reward = $total_reward / count($experiences);

        // Calculate success rate (reward > 0)
        $successes = count(array_filter($experiences, fn($e) => $e['reward'] > 0));
        $success_rate = $successes / count($experiences);

        // Combined performance metric
        $performance = ($avg_reward + $success_rate) / 2;

        return $performance;
    }

    /**
     * Determine if architecture evolution should occur
     */
    private function should_evolve() {
        // Evolve every 10 generations or if performance is stagnant
        $performance_delta = $this->get_performance_delta();

        return (
            $this->generation % 10 === 0 ||
            abs($performance_delta) < $this->improvement_threshold
        );
    }

    /**
     * Get performance change over last 5 generations
     */
    private function get_performance_delta() {
        $history = get_option('aros_performance_history', []);

        if (count($history) < 5) {
            return 1.0; // Not enough history
        }

        $recent = array_slice($history, -5);
        $first = $recent[0];
        $last = end($recent);

        return $last - $first;
    }

    /**
     * Evolve neural architecture using NeuroArchitect
     */
    private function evolve_architecture($current_performance) {
        // Integrate with NeuroArchitect to evolve blueprints

        // Create evolutionary parameters
        $params = [
            'population_size' => 20,
            'generations' => 5,
            'mutation_rate' => 0.1,
            'crossover_rate' => 0.7,
            'fitness_target' => $current_performance * 1.1, // Target 10% improvement
        ];

        // Make request to NeuroArchitect API
        $request = new \WP_REST_Request('POST', '/aevov-neuro/v1/blueprints/evolve');
        $request->set_param('population_size', $params['population_size']);
        $request->set_param('generations', $params['generations']);
        $request->set_param('mutation_rate', $params['mutation_rate']);

        $server = rest_get_server();
        $response = $server->dispatch($request);

        if (!is_wp_error($response) && $response->get_status() === 200) {
            $evolved_blueprint = $response->get_data();

            // Store evolved blueprint
            update_option('aros_current_blueprint', $evolved_blueprint);

            // Deploy new architecture
            $this->deploy_architecture($evolved_blueprint);
        }
    }

    /**
     * Deploy evolved architecture
     */
    private function deploy_architecture($blueprint) {
        // Store blueprint in memory for robot to use
        $memory_address = 'aros_active_blueprint';

        $request = new \WP_REST_Request('POST', '/aevov-memory/v1/memory');
        $request->set_param('address', $memory_address);
        $request->set_param('data', $blueprint);

        $server = rest_get_server();
        $server->dispatch($request);

        do_action('aros_architecture_deployed', $blueprint);
    }

    /**
     * Update policy using reinforcement learning
     */
    private function update_policy($experiences) {
        $learner = new ReinforcementLearner();

        foreach ($experiences as $exp) {
            $state = json_decode($exp['state'], true);
            $action = json_decode($exp['action'], true);
            $reward = $exp['reward'];
            $next_state = json_decode($exp['next_state'], true);
            $done = $exp['done'];

            $learner->learn($state, $action, $reward, $next_state, $done);
        }

        // Save updated policy
        $learner->save_policy();
    }

    /**
     * Optimize models for deployment efficiency
     */
    private function optimize_models() {
        $optimizer = new ModelOptimizer();

        // Get current models
        $models = $this->get_active_models();

        foreach ($models as $model_id => $model) {
            $optimized = $optimizer->optimize($model);

            // Deploy optimized model
            $this->deploy_model($model_id, $optimized);
        }
    }

    /**
     * Get currently active models
     */
    private function get_active_models() {
        return [
            'navigation' => get_option('aros_navigation_model'),
            'manipulation' => get_option('aros_manipulation_model'),
            'perception' => get_option('aros_perception_model'),
        ];
    }

    /**
     * Deploy optimized model
     */
    private function deploy_model($model_id, $model) {
        update_option("aros_{$model_id}_model", $model);

        do_action('aros_model_deployed', $model_id, $model);
    }

    /**
     * Update performance metrics
     */
    private function update_metrics($current_performance) {
        // Update best performance
        if ($current_performance > $this->best_performance) {
            $this->best_performance = $current_performance;
            update_option('aros_best_performance', $current_performance);

            do_action('aros_new_best_performance', $current_performance);
        }

        // Update performance history
        $history = get_option('aros_performance_history', []);
        $history[] = $current_performance;

        // Keep last 100 generations
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }

        update_option('aros_performance_history', $history);

        // Calculate improvement rate
        $improvement_rate = $this->calculate_improvement_rate($history);
        update_option('aros_improvement_rate', $improvement_rate);
    }

    /**
     * Calculate improvement rate
     */
    private function calculate_improvement_rate($history) {
        if (count($history) < 2) {
            return 0.0;
        }

        $first = $history[0];
        $last = end($history);

        if ($first == 0) {
            return 0.0;
        }

        return (($last - $first) / $first) * 100; // Percentage improvement
    }

    /**
     * Prune old experiences to manage database size
     */
    private function prune_experiences() {
        global $wpdb;

        $table = $wpdb->prefix . 'aros_experiences';

        // Keep only last 10,000 experiences
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        if ($count > 10000) {
            $delete_count = $count - 10000;

            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} ORDER BY id ASC LIMIT %d",
                    $delete_count
                )
            );
        }
    }

    /**
     * Get improvement status
     */
    public function get_status() {
        return [
            'generation' => $this->generation,
            'best_performance' => $this->best_performance,
            'improvement_rate' => get_option('aros_improvement_rate', 0.0),
            'active' => $this->evolution_active,
            'performance_history' => get_option('aros_performance_history', []),
        ];
    }

    /**
     * Enable/disable evolution
     */
    public function set_evolution_active($active) {
        $this->evolution_active = $active;
        update_option('aros_evolution_active', $active);
    }
}
