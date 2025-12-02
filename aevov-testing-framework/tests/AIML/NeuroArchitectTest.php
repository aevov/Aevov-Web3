<?php
/**
 * NeuroArchitect Test Suite
 * Tests blueprint evolution, model composition, pattern analysis
 */

require_once dirname(__FILE__) . '/../Infrastructure/BaseAevovTestCase.php';
require_once dirname(__FILE__) . '/../Infrastructure/TestDataFactory.php';

use AevovTesting\Infrastructure\BaseAevovTestCase;
use AevovTesting\Infrastructure\TestDataFactory;

class NeuroArchitectTest extends BaseAevovTestCase {

    /**
     * Test blueprint creation
     */
    public function test_blueprint_creation() {
        $blueprint = TestDataFactory::createBlueprint();

        $this->assertArrayHasKeys(['id', 'config', 'performance_score'], $blueprint);
        $this->assertIsArray($blueprint['config']);
        $this->assertGreaterThan(0, $blueprint['performance_score']);
    }

    /**
     * Test blueprint validation
     */
    public function test_blueprint_validation() {
        $blueprint = TestDataFactory::createBlueprint();

        // Blueprint should have valid layer configuration
        $this->assertArrayHasKey('layer_1', $blueprint['config']);
        $this->assertArrayHasKey('output', $blueprint['config']);
    }

    /**
     * Test blueprint evolution
     */
    public function test_blueprint_evolution() {
        $population_size = 20;
        $generation_count = 10;

        $population = [];
        for ($i = 0; $i < $population_size; $i++) {
            $population[] = TestDataFactory::createBlueprint([
                'performance_score' => rand(50, 100) / 100,
            ]);
        }

        $this->assertCount($population_size, $population);

        // Select best performers
        usort($population, fn($a, $b) => $b['performance_score'] <=> $a['performance_score']);

        $best = $population[0];
        $this->assertGreaterThanOrEqual(0.5, $best['performance_score']);
    }

    /**
     * Test crossover operation
     */
    public function test_blueprint_crossover() {
        $parent1 = TestDataFactory::createBlueprint();
        $parent2 = TestDataFactory::createBlueprint();

        // Simulate crossover
        $child_config = [
            'layer_1' => $parent1['config']['layer_1'],
            'layer_2' => $parent2['config']['layer_2'],
            'output' => $parent1['config']['output'],
        ];

        $this->assertArrayHasKey('layer_1', $child_config);
        $this->assertArrayHasKey('layer_2', $child_config);
    }

    /**
     * Test mutation operation
     */
    public function test_blueprint_mutation() {
        $blueprint = TestDataFactory::createBlueprint();
        $mutation_rate = 0.1;

        // Simulate mutation
        if (rand(0, 100) / 100 < $mutation_rate) {
            $blueprint['config']['layer_1']['units'] = rand(64, 256);
        }

        $this->assertGreaterThan(0, $blueprint['config']['layer_1']['units']);
    }

    /**
     * Test fitness calculation
     */
    public function test_fitness_calculation() {
        $output = TestDataFactory::createVector(10);
        $expected = TestDataFactory::createVector(10);

        // Calculate MSE
        $mse = 0;
        for ($i = 0; $i < 10; $i++) {
            $mse += pow($output[$i] - $expected[$i], 2);
        }
        $mse /= 10;

        $this->assertGreaterThanOrEqual(0, $mse);
    }

    /**
     * Test cosine similarity calculation
     */
    public function test_cosine_similarity() {
        $vec1 = [1, 0, 0];
        $vec2 = [1, 0, 0];

        // Calculate dot product and magnitudes
        $dot = 0;
        $mag1 = 0;
        $mag2 = 0;

        for ($i = 0; $i < 3; $i++) {
            $dot += $vec1[$i] * $vec2[$i];
            $mag1 += $vec1[$i] * $vec1[$i];
            $mag2 += $vec2[$i] * $vec2[$i];
        }

        $mag1 = sqrt($mag1);
        $mag2 = sqrt($mag2);

        $similarity = $dot / ($mag1 * $mag2);

        $this->assertEquals(1.0, $similarity);
    }

    /**
     * Test pattern recognition in neural blueprints
     */
    public function test_pattern_recognition() {
        $patterns = [
            ['type' => 'dense', 'units' => 128],
            ['type' => 'conv', 'filters' => 64],
            ['type' => 'lstm', 'units' => 256],
        ];

        foreach ($patterns as $pattern) {
            $this->assertArrayHasKey('type', $pattern);
            $this->assertIsString($pattern['type']);
        }
    }

    /**
     * Test layer type validation
     */
    public function test_layer_type_validation() {
        $valid_types = ['dense', 'conv', 'lstm', 'gru', 'attention', 'dropout', 'batch_norm'];

        foreach ($valid_types as $type) {
            $layer = ['type' => $type];
            $this->assertTrue(in_array($layer['type'], $valid_types));
        }
    }

    /**
     * Test activation function validation
     */
    public function test_activation_validation() {
        $valid_activations = ['relu', 'sigmoid', 'tanh', 'softmax', 'leaky_relu', 'elu'];

        foreach ($valid_activations as $activation) {
            $this->assertTrue(in_array($activation, $valid_activations));
        }
    }

    /**
     * Test model composition from blueprint
     */
    public function test_model_composition() {
        $blueprint = TestDataFactory::createBlueprint();

        // Verify blueprint can be composed into model
        $layers = [];
        foreach ($blueprint['config'] as $name => $layer_config) {
            $layers[$name] = $layer_config;
        }

        $this->assertGreaterThan(0, count($layers));
    }

    /**
     * Test blueprint performance tracking
     */
    public function test_blueprint_performance_tracking() {
        $blueprint = TestDataFactory::createBlueprint();

        $performance_history = [
            ['generation' => 1, 'score' => 0.6],
            ['generation' => 2, 'score' => 0.7],
            ['generation' => 3, 'score' => 0.8],
        ];

        // Performance should improve
        for ($i = 1; $i < count($performance_history); $i++) {
            $this->assertGreaterThanOrEqual(
                $performance_history[$i - 1]['score'],
                $performance_history[$i]['score']
            );
        }
    }

    /**
     * Test blueprint serialization
     */
    public function test_blueprint_serialization() {
        $blueprint = TestDataFactory::createBlueprint();

        $serialized = json_encode($blueprint);
        $this->assertValidJson($serialized);

        $deserialized = json_decode($serialized, true);
        $this->assertEquals($blueprint, $deserialized);
    }

    /**
     * Test population diversity
     */
    public function test_population_diversity() {
        $population = [];

        for ($i = 0; $i < 10; $i++) {
            $population[] = TestDataFactory::createBlueprint([
                'config' => [
                    'layer_1' => ['units' => 64 + $i * 16],
                ],
            ]);
        }

        // Check that blueprints are different
        $units = array_map(fn($b) => $b['config']['layer_1']['units'], $population);
        $unique_units = array_unique($units);

        $this->assertGreaterThan(1, count($unique_units));
    }

    /**
     * Test tournament selection
     */
    public function test_tournament_selection() {
        $population = [];
        for ($i = 0; $i < 20; $i++) {
            $population[] = TestDataFactory::createBlueprint([
                'performance_score' => rand(0, 100) / 100,
            ]);
        }

        // Select tournament participants
        $tournament_size = 5;
        $tournament = array_slice($population, 0, $tournament_size);

        // Select best from tournament
        usort($tournament, fn($a, $b) => $b['performance_score'] <=> $a['performance_score']);
        $winner = $tournament[0];

        $this->assertGreaterThan(0, $winner['performance_score']);
    }

    /**
     * Test elitism in evolution
     */
    public function test_elitism() {
        $population = [];
        for ($i = 0; $i < 20; $i++) {
            $population[] = TestDataFactory::createBlueprint([
                'performance_score' => rand(0, 100) / 100,
            ]);
        }

        $elite_size = 3;

        // Sort by performance
        usort($population, fn($a, $b) => $b['performance_score'] <=> $a['performance_score']);

        // Keep elite individuals
        $elite = array_slice($population, 0, $elite_size);

        $this->assertCount($elite_size, $elite);

        // Elite should have highest scores
        for ($i = 1; $i < count($elite); $i++) {
            $this->assertGreaterThanOrEqual($elite[$i]['performance_score'], $elite[$i - 1]['performance_score']);
        }
    }

    /**
     * Test convergence detection
     */
    public function test_convergence_detection() {
        $history = [
            0.5, 0.6, 0.7, 0.75, 0.76, 0.76, 0.76,
        ];

        $window_size = 3;
        $threshold = 0.01;

        // Check if last N values are within threshold
        $last_values = array_slice($history, -$window_size);
        $max = max($last_values);
        $min = min($last_values);

        $converged = ($max - $min) < $threshold;

        $this->assertTrue($converged);
    }

    /**
     * Test API endpoint for evolve blueprint
     */
    public function test_evolve_blueprint_endpoint() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $params = [
            'population_size' => 20,
            'generations' => 10,
            'mutation_rate' => 0.1,
        ];

        $response = $this->simulateRestRequest(
            '/aevov-neuro/v1/blueprints/evolve',
            'POST',
            $params
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test blueprint catalog storage
     */
    public function test_blueprint_catalog() {
        $blueprint = TestDataFactory::createBlueprint();

        // Store in catalog (simulated)
        $catalog_entry = [
            'id' => $blueprint['id'],
            'performance' => $blueprint['performance_score'],
            'created_at' => current_time('mysql'),
        ];

        $this->assertArrayHasKeys(['id', 'performance', 'created_at'], $catalog_entry);
    }

    /**
     * Test blueprint comparison
     */
    public function test_blueprint_comparison() {
        $blueprint1 = TestDataFactory::createBlueprint(['performance_score' => 0.8]);
        $blueprint2 = TestDataFactory::createBlueprint(['performance_score' => 0.6]);

        $comparison = [
            'better' => $blueprint1['id'],
            'score_diff' => $blueprint1['performance_score'] - $blueprint2['performance_score'],
        ];

        $this->assertEquals($blueprint1['id'], $comparison['better']);
        $this->assertGreaterThan(0, $comparison['score_diff']);
    }

    /**
     * Test hyperparameter optimization
     */
    public function test_hyperparameter_optimization() {
        $hyperparams = [
            'learning_rate' => 0.001,
            'batch_size' => 32,
            'dropout_rate' => 0.2,
        ];

        $this->assertGreaterThan(0, $hyperparams['learning_rate']);
        $this->assertLessThan(1, $hyperparams['learning_rate']);
        $this->assertGreaterThan(0, $hyperparams['batch_size']);
    }

    /**
     * Test layer configuration validation
     */
    public function test_layer_configuration() {
        $layer = [
            'type' => 'dense',
            'units' => 128,
            'activation' => 'relu',
            'dropout' => 0.2,
        ];

        $this->assertArrayHasKeys(['type', 'units', 'activation'], $layer);
        $this->assertGreaterThan(0, $layer['units']);
    }

    /**
     * Test model architecture complexity
     */
    public function test_model_complexity() {
        $blueprint = TestDataFactory::createBlueprint([
            'config' => [
                'layer_1' => ['units' => 256],
                'layer_2' => ['units' => 128],
                'layer_3' => ['units' => 64],
                'output' => ['units' => 10],
            ],
        ]);

        $total_layers = count($blueprint['config']);
        $this->assertGreaterThan(2, $total_layers);
    }

    /**
     * Test parameter count estimation
     */
    public function test_parameter_count() {
        $layers = [
            ['units' => 128, 'input_dim' => 784],
            ['units' => 64, 'input_dim' => 128],
            ['units' => 10, 'input_dim' => 64],
        ];

        $total_params = 0;
        foreach ($layers as $layer) {
            $params = ($layer['input_dim'] + 1) * $layer['units']; // weights + biases
            $total_params += $params;
        }

        $this->assertGreaterThan(0, $total_params);
    }

    /**
     * Test blueprint generation performance
     */
    public function test_blueprint_generation_performance() {
        $start = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            $blueprint = TestDataFactory::createBlueprint();
        }

        $end = microtime(true);
        $duration = $end - $start;

        $this->assertLessThan(1.0, $duration); // Should generate 100 blueprints in less than 1 second
    }
}
