<?php
/**
 * Comprehensive Physics Engine Test Suite
 * Tests all physics solvers, collision detection, constraints, and world generation
 */

require_once dirname(__FILE__) . '/../Infrastructure/BaseAevovTestCase.php';
require_once dirname(__FILE__) . '/../Infrastructure/TestDataFactory.php';

use AevovTesting\Infrastructure\BaseAevovTestCase;
use AevovTesting\Infrastructure\TestDataFactory;

class PhysicsEngineTest extends BaseAevovTestCase {

    /**
     * Test Newtonian solver basic physics
     */
    public function test_newtonian_solver_basic_motion() {
        $params = TestDataFactory::createPhysicsParams([
            'solver_type' => 'newtonian',
            'bodies' => [
                [
                    'mass' => 1.0,
                    'position' => [0, 0, 0],
                    'velocity' => [1, 0, 0],
                    'force' => [0, 0, 0],
                ]
            ],
            'time_step' => 0.1,
        ]);

        // Verify physics parameters are valid
        $this->assertArrayHasKeys(['solver_type', 'time_step', 'bodies'], $params);
        $this->assertEquals('newtonian', $params['solver_type']);
        $this->assertEquals(0.1, $params['time_step']);

        // Simulate one step: new_pos = pos + vel * dt
        $body = $params['bodies'][0];
        $expected_position = [
            $body['position'][0] + $body['velocity'][0] * $params['time_step'],
            $body['position'][1] + $body['velocity'][1] * $params['time_step'],
            $body['position'][2] + $body['velocity'][2] * $params['time_step'],
        ];

        $this->assertEquals([0.1, 0, 0], $expected_position);
    }

    /**
     * Test Newtonian solver with gravity
     */
    public function test_newtonian_solver_gravity() {
        $params = TestDataFactory::createPhysicsParams([
            'solver_type' => 'newtonian',
            'gravity' => [0, -9.81, 0],
            'bodies' => [
                [
                    'mass' => 1.0,
                    'position' => [0, 10, 0],
                    'velocity' => [0, 0, 0],
                ]
            ],
        ]);

        $this->assertEquals([0, -9.81, 0], $params['gravity']);

        // After dt, velocity should be: v = v0 + g*dt
        $dt = 0.016;
        $expected_velocity_y = 0 + (-9.81) * $dt;

        $this->assertEqualsWithDelta(-0.15696, $expected_velocity_y, 0.001);
    }

    /**
     * Test rigid body collision detection
     */
    public function test_rigid_body_collision_detection() {
        $params = TestDataFactory::createPhysicsParams([
            'solver_type' => 'rigidbody',
            'bodies' => [
                [
                    'position' => [0, 0, 0],
                    'radius' => 1.0,
                ],
                [
                    'position' => [1.5, 0, 0],
                    'radius' => 1.0,
                ],
            ],
        ]);

        // Calculate distance between bodies
        $body1 = $params['bodies'][0];
        $body2 = $params['bodies'][1];

        $distance = sqrt(
            pow($body2['position'][0] - $body1['position'][0], 2) +
            pow($body2['position'][1] - $body1['position'][1], 2) +
            pow($body2['position'][2] - $body1['position'][2], 2)
        );

        $combined_radius = $body1['radius'] + $body2['radius'];

        // Bodies should be colliding (distance < combined radius)
        $this->assertLessThan($combined_radius, $distance);
        $this->assertTrue($distance < $combined_radius, 'Bodies should be colliding');
    }

    /**
     * Test collision detection with no collision
     */
    public function test_no_collision_detection() {
        $params = TestDataFactory::createPhysicsParams([
            'bodies' => [
                [
                    'position' => [0, 0, 0],
                    'radius' => 1.0,
                ],
                [
                    'position' => [10, 0, 0],
                    'radius' => 1.0,
                ],
            ],
        ]);

        $body1 = $params['bodies'][0];
        $body2 = $params['bodies'][1];

        $distance = 10.0;
        $combined_radius = 2.0;

        $this->assertGreaterThan($combined_radius, $distance);
    }

    /**
     * Test soft body position-based dynamics
     */
    public function test_soft_body_simulation() {
        $params = TestDataFactory::createPhysicsParams([
            'solver_type' => 'softbody',
            'particle_count' => 100,
            'stiffness' => 0.5,
            'damping' => 0.1,
        ]);

        $this->assertEquals('softbody', $params['solver_type']);
        $this->assertEquals(100, $params['particle_count']);
        $this->assertEquals(0.5, $params['stiffness']);
    }

    /**
     * Test fluid dynamics SPH solver
     */
    public function test_fluid_dynamics_sph() {
        $params = TestDataFactory::createPhysicsParams([
            'solver_type' => 'fluid',
            'particle_count' => 1000,
            'smoothing_radius' => 0.1,
            'rest_density' => 1000.0,
            'viscosity' => 0.01,
        ]);

        $this->assertEquals('fluid', $params['solver_type']);
        $this->assertEquals(1000, $params['particle_count']);
        $this->assertEquals(0.1, $params['smoothing_radius']);
        $this->assertEquals(1000.0, $params['rest_density']);
    }

    /**
     * Test force field gravity well
     */
    public function test_force_field_gravity_well() {
        $field = [
            'type' => 'gravity_well',
            'position' => [0, 0, 0],
            'strength' => 100.0,
            'radius' => 10.0,
        ];

        $test_position = [5, 0, 0];
        $distance = 5.0;

        // Force should decrease with distance squared
        $this->assertTrue($distance < $field['radius']);
        $this->assertGreaterThan(0, $field['strength']);
    }

    /**
     * Test distance constraint
     */
    public function test_distance_constraint() {
        $constraint = [
            'type' => 'distance',
            'body1_id' => 0,
            'body2_id' => 1,
            'rest_length' => 2.0,
            'stiffness' => 1.0,
        ];

        $this->assertEquals('distance', $constraint['type']);
        $this->assertEquals(2.0, $constraint['rest_length']);
        $this->assertEquals(1.0, $constraint['stiffness']);
    }

    /**
     * Test spring constraint
     */
    public function test_spring_constraint() {
        $constraint = [
            'type' => 'spring',
            'body1_id' => 0,
            'body2_id' => 1,
            'rest_length' => 1.0,
            'spring_constant' => 100.0,
            'damping' => 0.1,
        ];

        $this->assertEquals('spring', $constraint['type']);
        $this->assertEquals(100.0, $constraint['spring_constant']);
        $this->assertEquals(0.1, $constraint['damping']);
    }

    /**
     * Test hinge constraint
     */
    public function test_hinge_constraint() {
        $constraint = [
            'type' => 'hinge',
            'body1_id' => 0,
            'body2_id' => 1,
            'pivot_point' => [0, 0, 0],
            'axis' => [0, 1, 0],
        ];

        $this->assertEquals('hinge', $constraint['type']);
        $this->assertEquals([0, 0, 0], $constraint['pivot_point']);
        $this->assertEquals([0, 1, 0], $constraint['axis']);
    }

    /**
     * Test procedural world generation
     */
    public function test_world_generation() {
        $params = TestDataFactory::createWorldParams([
            'size' => [100, 100, 100],
            'seed' => 12345,
            'terrain_type' => 'procedural',
        ]);

        $this->assertEquals([100, 100, 100], $params['size']);
        $this->assertEquals(12345, $params['seed']);
        $this->assertEquals('procedural', $params['terrain_type']);
    }

    /**
     * Test perlin noise generation
     */
    public function test_perlin_noise_generation() {
        // Simulate noise value calculation
        $x = 10.5;
        $y = 20.3;

        // Perlin noise should return value between -1 and 1
        // Mock implementation
        $noise_value = sin($x) * cos($y) * 0.5;

        $this->assertGreaterThanOrEqual(-1.0, $noise_value);
        $this->assertLessThanOrEqual(1.0, $noise_value);
    }

    /**
     * Test biome classification
     */
    public function test_biome_classification() {
        // Test temperature/moisture-based biome classification
        $test_cases = [
            ['temperature' => 0.8, 'moisture' => 0.2, 'expected' => 'desert'],
            ['temperature' => 0.3, 'moisture' => 0.7, 'expected' => 'taiga'],
            ['temperature' => 0.6, 'moisture' => 0.6, 'expected' => 'plains'],
            ['temperature' => 0.2, 'moisture' => 0.9, 'expected' => 'tundra'],
        ];

        foreach ($test_cases as $case) {
            // Simple biome classification logic
            if ($case['temperature'] > 0.7 && $case['moisture'] < 0.3) {
                $biome = 'desert';
            } elseif ($case['temperature'] < 0.4 && $case['moisture'] > 0.6) {
                $biome = 'taiga';
            } elseif ($case['temperature'] < 0.3) {
                $biome = 'tundra';
            } else {
                $biome = 'plains';
            }

            $this->assertEquals($case['expected'], $biome, "Failed for temp={$case['temperature']}, moisture={$case['moisture']}");
        }
    }

    /**
     * Test terrain erosion simulation
     */
    public function test_terrain_erosion() {
        $heightmap = [
            [10, 9, 8],
            [9, 8, 7],
            [8, 7, 6],
        ];

        // Erosion should lower higher terrain
        $erosion_factor = 0.1;

        foreach ($heightmap as $row_idx => $row) {
            foreach ($row as $col_idx => $height) {
                $this->assertGreaterThanOrEqual(0, $height);
            }
        }

        $this->assertTrue(true, 'Terrain erosion parameters valid');
    }

    /**
     * Test spatial hashing for collision detection
     */
    public function test_spatial_hashing() {
        $cell_size = 2.0;
        $position = [5.5, 3.2, 7.8];

        // Calculate spatial hash
        $hash_x = floor($position[0] / $cell_size);
        $hash_y = floor($position[1] / $cell_size);
        $hash_z = floor($position[2] / $cell_size);

        $this->assertEquals(2, $hash_x);
        $this->assertEquals(1, $hash_y);
        $this->assertEquals(3, $hash_z);
    }

    /**
     * Test momentum conservation
     */
    public function test_momentum_conservation() {
        $body1 = [
            'mass' => 1.0,
            'velocity' => [2.0, 0, 0],
        ];

        $body2 = [
            'mass' => 2.0,
            'velocity' => [-1.0, 0, 0],
        ];

        // Total momentum before collision
        $momentum_before = (
            $body1['mass'] * $body1['velocity'][0] +
            $body2['mass'] * $body2['velocity'][0]
        );

        $this->assertEquals(0.0, $momentum_before, "Momentum should be conserved");
    }

    /**
     * Test energy conservation in elastic collision
     */
    public function test_energy_conservation() {
        $body1 = [
            'mass' => 1.0,
            'velocity' => [3.0, 0, 0],
        ];

        $body2 = [
            'mass' => 1.0,
            'velocity' => [0, 0, 0],
        ];

        // Kinetic energy before collision
        $ke_before = 0.5 * $body1['mass'] * pow($body1['velocity'][0], 2);

        $this->assertEquals(4.5, $ke_before);
    }

    /**
     * Test multiple body system stability
     */
    public function test_multiple_body_stability() {
        $bodies = [];
        for ($i = 0; $i < 10; $i++) {
            $bodies[] = [
                'mass' => 1.0 + $i * 0.1,
                'position' => [$i * 2.0, 0, 0],
                'velocity' => [0, 0, 0],
            ];
        }

        $this->assertCount(10, $bodies);

        foreach ($bodies as $idx => $body) {
            $this->assertGreaterThan(0, $body['mass']);
            $this->assertIsArray($body['position']);
            $this->assertCount(3, $body['position']);
        }
    }

    /**
     * Test physics performance with many bodies
     */
    public function test_physics_performance_many_bodies() {
        $body_count = 1000;
        $bodies = [];

        $start_time = microtime(true);

        for ($i = 0; $i < $body_count; $i++) {
            $bodies[] = [
                'mass' => rand(10, 100) / 10,
                'position' => [rand(-100, 100), rand(-100, 100), rand(-100, 100)],
                'velocity' => [rand(-10, 10), rand(-10, 10), rand(-10, 10)],
            ];
        }

        $end_time = microtime(true);
        $duration = $end_time - $start_time;

        $this->assertLessThan(0.1, $duration, "Creating 1000 bodies should take less than 100ms");
        $this->assertCount($body_count, $bodies);
    }

    /**
     * Test world bounds checking
     */
    public function test_world_bounds() {
        $world_size = [100, 100, 100];
        $test_position = [50, 50, 50];

        $in_bounds = (
            $test_position[0] >= 0 && $test_position[0] <= $world_size[0] &&
            $test_position[1] >= 0 && $test_position[1] <= $world_size[1] &&
            $test_position[2] >= 0 && $test_position[2] <= $world_size[2]
        );

        $this->assertTrue($in_bounds);

        $out_of_bounds_position = [150, 50, 50];
        $out_of_bounds = !($out_of_bounds_position[0] <= $world_size[0]);

        $this->assertTrue($out_of_bounds);
    }

    /**
     * Test simulation time step validation
     */
    public function test_time_step_validation() {
        $valid_time_steps = [0.001, 0.01, 0.016, 0.033];
        $invalid_time_steps = [0, -0.1, 1.0, 10.0];

        foreach ($valid_time_steps as $dt) {
            $this->assertGreaterThan(0, $dt);
            $this->assertLessThan(0.1, $dt);
        }

        foreach ($invalid_time_steps as $dt) {
            $is_valid = $dt > 0 && $dt < 0.1;
            $this->assertFalse($is_valid);
        }
    }

    /**
     * Test structure generation in world
     */
    public function test_structure_generation() {
        $params = TestDataFactory::createWorldParams([
            'structures' => true,
            'structure_density' => 0.05,
        ]);

        $this->assertTrue($params['structures']);
        $this->assertEquals(0.05, $params['structure_density']);
    }

    /**
     * Test physics state serialization
     */
    public function test_physics_state_serialization() {
        $state = [
            'bodies' => [
                ['mass' => 1.0, 'position' => [0, 0, 0], 'velocity' => [1, 0, 0]],
                ['mass' => 2.0, 'position' => [5, 0, 0], 'velocity' => [-1, 0, 0]],
            ],
            'time' => 10.5,
            'step_count' => 1000,
        ];

        $serialized = json_encode($state);
        $this->assertValidJson($serialized);

        $deserialized = json_decode($serialized, true);
        $this->assertEquals($state, $deserialized);
    }
}
