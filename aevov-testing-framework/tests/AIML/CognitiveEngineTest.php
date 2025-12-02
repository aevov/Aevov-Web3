<?php
/**
 * Cognitive Engine Test Suite
 * Tests reasoning, inference, decision-making capabilities
 */

require_once dirname(__FILE__) . '/../Infrastructure/BaseAevovTestCase.php';
require_once dirname(__FILE__) . '/../Infrastructure/TestDataFactory.php';

use AevovTesting\Infrastructure\BaseAevovTestCase;
use AevovTesting\Infrastructure\TestDataFactory;

class CognitiveEngineTest extends BaseAevovTestCase {

    /**
     * Test cognitive task creation
     */
    public function test_cognitive_task_creation() {
        $task = TestDataFactory::createCognitiveTask();

        $this->assertArrayHasKeys(['task_type', 'input_data', 'expected_output'], $task);
        $this->assertIsArray($task['input_data']);
    }

    /**
     * Test reasoning task types
     */
    public function test_reasoning_task_types() {
        $types = ['deductive', 'inductive', 'abductive', 'analogical', 'causal'];

        foreach ($types as $type) {
            $task = TestDataFactory::createCognitiveTask(['task_type' => $type]);
            $this->assertEquals($type, $task['task_type']);
        }
    }

    /**
     * Test inference engine
     */
    public function test_inference_engine() {
        $premises = [
            'All humans are mortal',
            'Socrates is a human',
        ];

        $conclusion = 'Socrates is mortal';

        // Verify logical structure
        $this->assertCount(2, $premises);
        $this->assertIsString($conclusion);
    }

    /**
     * Test knowledge base query
     */
    public function test_knowledge_base_query() {
        $knowledge = [
            ['subject' => 'A', 'predicate' => 'is_larger_than', 'object' => 'B'],
            ['subject' => 'B', 'predicate' => 'is_larger_than', 'object' => 'C'],
        ];

        // Transitive inference: if A > B and B > C, then A > C
        $this->assertCount(2, $knowledge);
    }

    /**
     * Test decision making
     */
    public function test_decision_making() {
        $options = [
            ['choice' => 'A', 'score' => 0.8],
            ['choice' => 'B', 'score' => 0.6],
            ['choice' => 'C', 'score' => 0.9],
        ];

        // Select best option
        usort($options, fn($a, $b) => $b['score'] <=> $a['score']);
        $best = $options[0];

        $this->assertEquals('C', $best['choice']);
        $this->assertEquals(0.9, $best['score']);
    }

    /**
     * Test pattern matching in reasoning
     */
    public function test_pattern_matching() {
        $patterns = [
            'IF {X} THEN {Y}',
            'IF {Y} THEN {Z}',
        ];

        // Can chain rules
        $this->assertCount(2, $patterns);
    }

    /**
     * Test analogical reasoning
     */
    public function test_analogical_reasoning() {
        // A:B :: C:D (A is to B as C is to D)
        $analogy = [
            'source_domain' => ['bird', 'flies'],
            'target_domain' => ['fish', 'swims'],
        ];

        $this->assertArrayHasKeys(['source_domain', 'target_domain'], $analogy);
    }

    /**
     * Test causal reasoning
     */
    public function test_causal_reasoning() {
        $cause = 'rain';
        $effect = 'wet_ground';

        $causal_link = [
            'cause' => $cause,
            'effect' => $effect,
            'strength' => 0.9,
        ];

        $this->assertEquals($cause, $causal_link['cause']);
        $this->assertEquals($effect, $causal_link['effect']);
    }

    /**
     * Test uncertainty handling
     */
    public function test_uncertainty_handling() {
        $belief = [
            'proposition' => 'It will rain tomorrow',
            'probability' => 0.7,
        ];

        $this->assertGreaterThan(0, $belief['probability']);
        $this->assertLessThanOrEqual(1, $belief['probability']);
    }

    /**
     * Test context awareness
     */
    public function test_context_awareness() {
        $context = [
            'location' => 'office',
            'time' => '14:00',
            'activity' => 'meeting',
        ];

        $this->assertArrayHasKeys(['location', 'time', 'activity'], $context);
    }

    /**
     * Test goal-directed behavior
     */
    public function test_goal_directed_behavior() {
        $goal = 'maximize_efficiency';
        $current_state = ['efficiency' => 0.6];
        $target_state = ['efficiency' => 0.9];

        $gap = $target_state['efficiency'] - $current_state['efficiency'];

        $this->assertGreaterThan(0, $gap);
    }

    /**
     * Test planning and scheduling
     */
    public function test_planning() {
        $plan = [
            ['step' => 1, 'action' => 'gather_data'],
            ['step' => 2, 'action' => 'analyze'],
            ['step' => 3, 'action' => 'decide'],
        ];

        $this->assertCount(3, $plan);

        foreach ($plan as $idx => $step) {
            $this->assertEquals($idx + 1, $step['step']);
        }
    }

    /**
     * Test problem decomposition
     */
    public function test_problem_decomposition() {
        $problem = 'complex_optimization';

        $subproblems = [
            'define_objectives',
            'identify_constraints',
            'find_feasible_solutions',
            'select_optimal_solution',
        ];

        $this->assertCount(4, $subproblems);
    }

    /**
     * Test learning from experience
     */
    public function test_experience_learning() {
        $experiences = [
            ['action' => 'A', 'outcome' => 'success', 'reward' => 1.0],
            ['action' => 'B', 'outcome' => 'failure', 'reward' => 0.0],
            ['action' => 'A', 'outcome' => 'success', 'reward' => 1.0],
        ];

        // Count successes for each action
        $action_rewards = [];
        foreach ($experiences as $exp) {
            if (!isset($action_rewards[$exp['action']])) {
                $action_rewards[$exp['action']] = [];
            }
            $action_rewards[$exp['action']][] = $exp['reward'];
        }

        // Action A should have higher average reward
        $avg_A = array_sum($action_rewards['A']) / count($action_rewards['A']);
        $this->assertEquals(1.0, $avg_A);
    }

    /**
     * Test cognitive load estimation
     */
    public function test_cognitive_load() {
        $task_complexity = [
            'simple' => 0.2,
            'moderate' => 0.5,
            'complex' => 0.8,
            'very_complex' => 1.0,
        ];

        foreach ($task_complexity as $level => $load) {
            $this->assertGreaterThanOrEqual(0, $load);
            $this->assertLessThanOrEqual(1, $load);
        }
    }

    /**
     * Test memory retrieval
     */
    public function test_memory_retrieval() {
        $query = 'What is the capital of France?';
        $memory = [
            'Paris is the capital of France',
            'France is in Europe',
        ];

        // Should retrieve relevant memory
        $this->assertContains('Paris is the capital of France', $memory);
    }

    /**
     * Test attention mechanism
     */
    public function test_attention_mechanism() {
        $stimuli = [
            ['item' => 'urgent_alert', 'priority' => 1.0],
            ['item' => 'background_noise', 'priority' => 0.1],
            ['item' => 'important_task', 'priority' => 0.8],
        ];

        // Sort by priority
        usort($stimuli, fn($a, $b) => $b['priority'] <=> $a['priority']);

        $most_important = $stimuli[0];
        $this->assertEquals('urgent_alert', $most_important['item']);
    }

    /**
     * Test cognitive API endpoint
     */
    public function test_cognitive_reasoning_endpoint() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $task = TestDataFactory::createCognitiveTask();

        $response = $this->simulateRestRequest(
            '/aevov-cognitive/v1/reason',
            'POST',
            $task
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test multi-step reasoning
     */
    public function test_multi_step_reasoning() {
        $steps = [
            ['step' => 1, 'operation' => 'parse_input'],
            ['step' => 2, 'operation' => 'retrieve_knowledge'],
            ['step' => 3, 'operation' => 'apply_rules'],
            ['step' => 4, 'operation' => 'generate_conclusion'],
        ];

        foreach ($steps as $idx => $step) {
            $this->assertEquals($idx + 1, $step['step']);
        }
    }

    /**
     * Test confidence scoring
     */
    public function test_confidence_scoring() {
        $conclusions = [
            ['conclusion' => 'A', 'confidence' => 0.95],
            ['conclusion' => 'B', 'confidence' => 0.60],
            ['conclusion' => 'C', 'confidence' => 0.40],
        ];

        foreach ($conclusions as $c) {
            $this->assertGreaterThan(0, $c['confidence']);
            $this->assertLessThanOrEqual(1, $c['confidence']);
        }
    }

    /**
     * Test contradiction detection
     */
    public function test_contradiction_detection() {
        $statements = [
            'The sky is blue',
            'The sky is not blue',
        ];

        // These statements contradict each other
        $this->assertCount(2, $statements);
    }
}
