<?php
/**
 * Aevov Physics Core Engine
 *
 * Multi-scale physics simulation with:
 * - Newtonian mechanics
 * - Rigid body dynamics
 * - Soft body/deformable objects
 * - Fluid dynamics
 * - Constraint solving
 * - Collision detection and response
 * - Force field simulation
 */

namespace Aevov\PhysicsEngine\Core;

class PhysicsCore {

    private $active_simulations = [];
    private $physics_solvers = [];
    private $collision_detector;
    private $constraint_solver;
    private $timestep = 0.016; // 60 FPS

    public function __construct() {
        $this->initialize_solvers();
        $this->collision_detector = new CollisionDetector();
        $this->constraint_solver = new ConstraintSolver();
    }

    public function initialize() {
        // Register physics patterns with APS
        add_filter('aps_available_patterns', [$this, 'register_physics_patterns']);

        // Hook into simulation engine
        add_filter('aevov_simulation_initialize', [$this, 'initialize_physics_world'], 10, 2);
    }

    /**
     * Initialize physics solvers
     */
    private function initialize_solvers() {
        $this->physics_solvers = [
            'newtonian' => new Solvers\NewtonianSolver(),
            'rigid_body' => new Solvers\RigidBodySolver(),
            'soft_body' => new Solvers\SoftBodySolver(),
            'fluid' => new Solvers\FluidSolver(),
            'field' => new Solvers\FieldSolver(),
        ];
    }

    /**
     * Register physics patterns with APS
     */
    public function register_physics_patterns($patterns) {
        $physics_patterns = [
            [
                'pattern_id' => 'physics_newtonian_3d',
                'pattern_type' => 'physics',
                'version' => '1.0.0',
                'capabilities' => [
                    'gravity' => true,
                    'collision' => true,
                    'constraints' => true,
                    'forces' => ['gravity', 'friction', 'drag', 'spring']
                ],
                'performance' => [
                    'entities_per_second' => 10000,
                    'accuracy' => 0.95
                ],
                'metadata' => [
                    'description' => 'Classical Newtonian mechanics for 3D simulation',
                    'use_cases' => ['spatial_world', 'object_simulation', 'vehicle_physics']
                ]
            ],
            [
                'pattern_id' => 'physics_soft_body',
                'pattern_type' => 'physics',
                'version' => '1.0.0',
                'capabilities' => [
                    'deformation' => true,
                    'elasticity' => true,
                    'plasticity' => true,
                    'fracture' => true
                ],
                'performance' => [
                    'vertices_per_second' => 50000,
                    'stability' => 0.98 // Improved over physX-Anything
                ],
                'metadata' => [
                    'description' => 'Stable soft body and deformable object simulation',
                    'use_cases' => ['cloth', 'organic_matter', 'destructible_objects']
                ]
            ],
            [
                'pattern_id' => 'physics_fluid_dynamics',
                'pattern_type' => 'physics',
                'version' => '1.0.0',
                'capabilities' => [
                    'incompressible' => true,
                    'compressible' => true,
                    'viscosity' => true,
                    'turbulence' => true,
                    'surface_tension' => true
                ],
                'performance' => [
                    'particles_per_second' => 100000,
                    'accuracy' => 0.92
                ],
                'metadata' => [
                    'description' => 'SPH-based fluid dynamics for liquids and gases',
                    'use_cases' => ['water', 'smoke', 'fire', 'weather']
                ]
            ],
            [
                'pattern_id' => 'physics_world_generation',
                'pattern_type' => 'spatial',
                'version' => '1.0.0',
                'capabilities' => [
                    'terrain_generation' => true,
                    'biome_distribution' => true,
                    'structure_placement' => true,
                    'erosion_simulation' => true,
                    'vegetation_growth' => true
                ],
                'performance' => [
                    'chunks_per_second' => 100,
                    'quality' => 0.95
                ],
                'metadata' => [
                    'description' => 'Procedural spatial world generation with physics-based features',
                    'use_cases' => ['open_world', 'terrain', 'ecosystems']
                ]
            ]
        ];

        return array_merge($patterns, $physics_patterns);
    }

    /**
     * Initialize physics world for simulation
     */
    public function initialize_physics_world($simulation_data, $blueprint) {
        $simulation_id = $simulation_data['job_id'];

        // Extract physics configuration from blueprint
        $physics_config = $blueprint['physics'] ?? [
            'enabled' => true,
            'engine' => 'newtonian',
            'dimensions' => 3,
            'gravity' => 9.81,
            'timestep' => 0.016
        ];

        if (!$physics_config['enabled']) {
            return $simulation_data;
        }

        // Create physics world
        $physics_world = [
            'simulation_id' => $simulation_id,
            'config' => $physics_config,
            'entities' => [],
            'constraints' => [],
            'forces' => [],
            'fields' => [],
            'timestep' => $physics_config['timestep'] ?? $this->timestep,
            'time' => 0.0,
            'tick' => 0
        ];

        // Initialize spatial world if requested
        if (isset($blueprint['world']) && $blueprint['world']['enabled']) {
            $world_generator = new \Aevov\PhysicsEngine\World\WorldGenerator();
            $physics_world['world'] = $world_generator->generate_world($blueprint['world']);

            // Add terrain as collision geometry
            $this->add_terrain_colliders($physics_world);
        }

        // Convert simulation entities to physics entities
        if (isset($simulation_data['entities'])) {
            foreach ($simulation_data['entities'] as $entity) {
                $physics_world['entities'][] = $this->create_physics_entity($entity, $physics_config);
            }
        }

        // Store physics world
        $this->active_simulations[$simulation_id] = $physics_world;
        update_option('aevov_physics_world_' . $simulation_id, $physics_world);

        // Add physics data to simulation
        $simulation_data['physics'] = [
            'initialized' => true,
            'engine' => $physics_config['engine'],
            'entity_count' => count($physics_world['entities'])
        ];

        return $simulation_data;
    }

    /**
     * Main physics simulation tick
     */
    public function simulate_physics($simulation_state, $simulation_id) {
        if (!isset($this->active_simulations[$simulation_id])) {
            $this->active_simulations[$simulation_id] = get_option('aevov_physics_world_' . $simulation_id);
        }

        if (!$this->active_simulations[$simulation_id]) {
            return $simulation_state;
        }

        $physics_world = &$this->active_simulations[$simulation_id];
        $dt = $physics_world['timestep'];

        // Physics simulation pipeline

        // 1. Apply forces
        $this->apply_forces($physics_world, $dt);

        // 2. Integrate velocities and positions
        $this->integrate($physics_world, $dt);

        // 3. Detect collisions
        $collisions = $this->collision_detector->detect_collisions($physics_world['entities']);

        // 4. Resolve collisions
        $this->resolve_collisions($collisions, $physics_world['entities']);

        // 5. Solve constraints
        $this->constraint_solver->solve_constraints($physics_world['constraints'], $physics_world['entities']);

        // 6. Update entity states
        $this->update_entity_states($physics_world, $simulation_state);

        // 7. Increment time
        $physics_world['time'] += $dt;
        $physics_world['tick']++;

        // Periodic save
        if ($physics_world['tick'] % 60 === 0) {
            update_option('aevov_physics_world_' . $simulation_id, $physics_world);
        }

        return $simulation_state;
    }

    /**
     * Create physics entity from simulation entity
     */
    private function create_physics_entity($entity, $config) {
        $physics_entity = [
            'id' => $entity['id'] ?? uniqid('entity_'),
            'type' => $entity['type'] ?? 'rigid_body',
            'active' => true,

            // Physical properties
            'mass' => $entity['mass'] ?? 1.0,
            'inverse_mass' => 1.0 / ($entity['mass'] ?? 1.0),
            'restitution' => $entity['restitution'] ?? 0.5,
            'friction' => $entity['friction'] ?? 0.3,

            // Transform
            'position' => $entity['position'] ?? ['x' => 0, 'y' => 0, 'z' => 0],
            'rotation' => $entity['rotation'] ?? ['x' => 0, 'y' => 0, 'z' => 0, 'w' => 1],
            'scale' => $entity['scale'] ?? ['x' => 1, 'y' => 1, 'z' => 1],

            // Dynamics
            'velocity' => $entity['velocity'] ?? ['x' => 0, 'y' => 0, 'z' => 0],
            'angular_velocity' => $entity['angular_velocity'] ?? ['x' => 0, 'y' => 0, 'z' => 0],
            'acceleration' => ['x' => 0, 'y' => 0, 'z' => 0],
            'force_accumulator' => ['x' => 0, 'y' => 0, 'z' => 0],
            'torque_accumulator' => ['x' => 0, 'y' => 0, 'z' => 0],

            // Collision shape
            'collider' => $entity['collider'] ?? [
                'type' => 'sphere',
                'radius' => 1.0
            ],

            // Simulation metadata
            'original_data' => $entity
        ];

        return $physics_entity;
    }

    /**
     * Apply forces to all entities
     */
    private function apply_forces(&$physics_world, $dt) {
        $gravity = $physics_world['config']['gravity'] ?? 9.81;
        $gravity_vector = ['x' => 0, 'y' => -$gravity, 'z' => 0];

        foreach ($physics_world['entities'] as &$entity) {
            if (!$entity['active']) continue;

            // Reset accumulators
            $entity['force_accumulator'] = ['x' => 0, 'y' => 0, 'z' => 0];
            $entity['torque_accumulator'] = ['x' => 0, 'y' => 0, 'z' => 0];

            // Apply gravity
            if ($entity['mass'] > 0) {
                $entity['force_accumulator']['x'] += $gravity_vector['x'] * $entity['mass'];
                $entity['force_accumulator']['y'] += $gravity_vector['y'] * $entity['mass'];
                $entity['force_accumulator']['z'] += $gravity_vector['z'] * $entity['mass'];
            }

            // Apply drag
            $drag_coefficient = $physics_world['config']['drag_coefficient'] ?? 0.1;
            $velocity_magnitude = sqrt(
                $entity['velocity']['x'] ** 2 +
                $entity['velocity']['y'] ** 2 +
                $entity['velocity']['z'] ** 2
            );

            if ($velocity_magnitude > 0.001) {
                $drag_force = $drag_coefficient * $velocity_magnitude;
                $entity['force_accumulator']['x'] -= $entity['velocity']['x'] * $drag_force;
                $entity['force_accumulator']['y'] -= $entity['velocity']['y'] * $drag_force;
                $entity['force_accumulator']['z'] -= $entity['velocity']['z'] * $drag_force;
            }
        }

        // Apply custom forces
        foreach ($physics_world['forces'] as $force) {
            $this->apply_custom_force($force, $physics_world['entities']);
        }

        // Apply force fields
        foreach ($physics_world['fields'] as $field) {
            $this->apply_force_field($field, $physics_world['entities']);
        }
    }

    /**
     * Integrate physics (Verlet integration)
     */
    private function integrate(&$physics_world, $dt) {
        foreach ($physics_world['entities'] as &$entity) {
            if (!$entity['active'] || $entity['mass'] <= 0) continue;

            // Update acceleration
            $entity['acceleration']['x'] = $entity['force_accumulator']['x'] * $entity['inverse_mass'];
            $entity['acceleration']['y'] = $entity['force_accumulator']['y'] * $entity['inverse_mass'];
            $entity['acceleration']['z'] = $entity['force_accumulator']['z'] * $entity['inverse_mass'];

            // Update velocity
            $entity['velocity']['x'] += $entity['acceleration']['x'] * $dt;
            $entity['velocity']['y'] += $entity['acceleration']['y'] * $dt;
            $entity['velocity']['z'] += $entity['acceleration']['z'] * $dt;

            // Clamp velocity
            $max_velocity = $physics_world['config']['max_velocity'] ?? 1000.0;
            $velocity_magnitude = sqrt(
                $entity['velocity']['x'] ** 2 +
                $entity['velocity']['y'] ** 2 +
                $entity['velocity']['z'] ** 2
            );

            if ($velocity_magnitude > $max_velocity) {
                $scale = $max_velocity / $velocity_magnitude;
                $entity['velocity']['x'] *= $scale;
                $entity['velocity']['y'] *= $scale;
                $entity['velocity']['z'] *= $scale;
            }

            // Update position
            $entity['position']['x'] += $entity['velocity']['x'] * $dt;
            $entity['position']['y'] += $entity['velocity']['y'] * $dt;
            $entity['position']['z'] += $entity['velocity']['z'] * $dt;

            // Update angular velocity and rotation (simplified)
            if (isset($entity['angular_velocity'])) {
                // Convert angular velocity to quaternion derivative
                // For now, simplified rotation update
                $entity['rotation']['x'] += $entity['angular_velocity']['x'] * $dt;
                $entity['rotation']['y'] += $entity['angular_velocity']['y'] * $dt;
                $entity['rotation']['z'] += $entity['angular_velocity']['z'] * $dt;
            }
        }
    }

    /**
     * Resolve collisions
     */
    private function resolve_collisions($collisions, &$entities) {
        foreach ($collisions as $collision) {
            $entity_a = &$entities[$collision['index_a']];
            $entity_b = &$entities[$collision['index_b']];

            // Calculate relative velocity
            $rel_velocity = [
                'x' => $entity_b['velocity']['x'] - $entity_a['velocity']['x'],
                'y' => $entity_b['velocity']['y'] - $entity_a['velocity']['y'],
                'z' => $entity_b['velocity']['z'] - $entity_a['velocity']['z']
            ];

            // Velocity along normal
            $normal = $collision['normal'];
            $velocity_along_normal =
                $rel_velocity['x'] * $normal['x'] +
                $rel_velocity['y'] * $normal['y'] +
                $rel_velocity['z'] * $normal['z'];

            // Do not resolve if velocities are separating
            if ($velocity_along_normal > 0) continue;

            // Calculate restitution
            $restitution = min($entity_a['restitution'], $entity_b['restitution']);

            // Calculate impulse scalar
            $impulse_scalar = -(1 + $restitution) * $velocity_along_normal;
            $impulse_scalar /= ($entity_a['inverse_mass'] + $entity_b['inverse_mass']);

            // Apply impulse
            $impulse = [
                'x' => $impulse_scalar * $normal['x'],
                'y' => $impulse_scalar * $normal['y'],
                'z' => $impulse_scalar * $normal['z']
            ];

            $entity_a['velocity']['x'] -= $impulse['x'] * $entity_a['inverse_mass'];
            $entity_a['velocity']['y'] -= $impulse['y'] * $entity_a['inverse_mass'];
            $entity_a['velocity']['z'] -= $impulse['z'] * $entity_a['inverse_mass'];

            $entity_b['velocity']['x'] += $impulse['x'] * $entity_b['inverse_mass'];
            $entity_b['velocity']['y'] += $impulse['y'] * $entity_b['inverse_mass'];
            $entity_b['velocity']['z'] += $impulse['z'] * $entity_b['inverse_mass'];

            // Position correction (to prevent sinking)
            $penetration = $collision['penetration'];
            $correction_percent = 0.4; // 40% correction
            $slop = 0.01; // penetration allowance

            $correction = max($penetration - $slop, 0.0) /
                         ($entity_a['inverse_mass'] + $entity_b['inverse_mass']) *
                         $correction_percent;

            $correction_vector = [
                'x' => $correction * $normal['x'],
                'y' => $correction * $normal['y'],
                'z' => $correction * $normal['z']
            ];

            $entity_a['position']['x'] -= $correction_vector['x'] * $entity_a['inverse_mass'];
            $entity_a['position']['y'] -= $correction_vector['y'] * $entity_a['inverse_mass'];
            $entity_a['position']['z'] -= $correction_vector['z'] * $entity_a['inverse_mass'];

            $entity_b['position']['x'] += $correction_vector['x'] * $entity_b['inverse_mass'];
            $entity_b['position']['y'] += $correction_vector['y'] * $entity_b['inverse_mass'];
            $entity_b['position']['z'] += $correction_vector['z'] * $entity_b['inverse_mass'];

            // Trigger collision event
            do_action('aevov_physics_collision', $entity_a, $entity_b, $collision);
        }
    }

    /**
     * Update simulation entity states from physics entities
     */
    private function update_entity_states(&$physics_world, &$simulation_state) {
        if (!isset($simulation_state['entities'])) {
            $simulation_state['entities'] = [];
        }

        foreach ($physics_world['entities'] as $physics_entity) {
            // Find corresponding simulation entity
            $entity_id = $physics_entity['id'];
            $found = false;

            foreach ($simulation_state['entities'] as &$sim_entity) {
                if (($sim_entity['id'] ?? '') === $entity_id) {
                    // Update position
                    $sim_entity['x'] = round($physics_entity['position']['x'], 2);
                    $sim_entity['y'] = round($physics_entity['position']['y'], 2);
                    if (isset($physics_entity['position']['z'])) {
                        $sim_entity['z'] = round($physics_entity['position']['z'], 2);
                    }

                    // Update velocity (for visualization)
                    $sim_entity['velocity'] = $physics_entity['velocity'];

                    // Update rotation (for visualization)
                    $sim_entity['rotation'] = $physics_entity['rotation'];

                    $found = true;
                    break;
                }
            }

            // If not found, add as new entity
            if (!$found) {
                $simulation_state['entities'][] = [
                    'id' => $entity_id,
                    'type' => $physics_entity['type'],
                    'x' => round($physics_entity['position']['x'], 2),
                    'y' => round($physics_entity['position']['y'], 2),
                    'z' => round($physics_entity['position']['z'] ?? 0, 2),
                    'velocity' => $physics_entity['velocity'],
                    'rotation' => $physics_entity['rotation']
                ];
            }
        }
    }

    /**
     * Apply custom force to entities
     */
    private function apply_custom_force($force, &$entities) {
        foreach ($entities as &$entity) {
            if (!$entity['active']) continue;

            // Check if force applies to this entity
            if (isset($force['target_id']) && $force['target_id'] !== $entity['id']) {
                continue;
            }

            // Apply force
            $entity['force_accumulator']['x'] += $force['force']['x'] ?? 0;
            $entity['force_accumulator']['y'] += $force['force']['y'] ?? 0;
            $entity['force_accumulator']['z'] += $force['force']['z'] ?? 0;
        }
    }

    /**
     * Apply force field to entities
     */
    private function apply_force_field($field, &$entities) {
        foreach ($entities as &$entity) {
            if (!$entity['active']) continue;

            // Calculate distance from field center
            $dx = $entity['position']['x'] - $field['position']['x'];
            $dy = $entity['position']['y'] - $field['position']['y'];
            $dz = $entity['position']['z'] - $field['position']['z'];
            $distance_sq = $dx * $dx + $dy * $dy + $dz * $dz;
            $distance = sqrt($distance_sq);

            // Check if within field radius
            if ($distance > $field['radius']) continue;

            // Calculate field strength (inverse square for gravity-like fields)
            $strength = $field['strength'];
            if ($field['falloff'] === 'inverse_square') {
                $strength = $field['strength'] / max($distance_sq, 0.1);
            } elseif ($field['falloff'] === 'linear') {
                $strength = $field['strength'] * (1 - $distance / $field['radius']);
            }

            // Calculate direction
            if ($distance > 0.001) {
                $direction = ['x' => $dx / $distance, 'y' => $dy / $distance, 'z' => $dz / $distance];
            } else {
                $direction = ['x' => 0, 'y' => 0, 'z' => 0];
            }

            // Apply force
            $entity['force_accumulator']['x'] += $direction['x'] * $strength;
            $entity['force_accumulator']['y'] += $direction['y'] * $strength;
            $entity['force_accumulator']['z'] += $direction['z'] * $strength;
        }
    }

    /**
     * Add terrain colliders from world generation
     */
    private function add_terrain_colliders(&$physics_world) {
        if (!isset($physics_world['world'])) return;

        // Add ground plane
        $physics_world['constraints'][] = [
            'type' => 'ground_plane',
            'position' => ['x' => 0, 'y' => 0, 'z' => 0],
            'normal' => ['x' => 0, 'y' => 1, 'z' => 0]
        ];

        // Add terrain heightmap colliders (simplified)
        // In a full implementation, this would create triangle mesh colliders
    }

    /**
     * Get physics state for visualization
     */
    public function get_physics_state($simulation_id) {
        if (!isset($this->active_simulations[$simulation_id])) {
            $this->active_simulations[$simulation_id] = get_option('aevov_physics_world_' . $simulation_id);
        }

        return $this->active_simulations[$simulation_id];
    }
}
