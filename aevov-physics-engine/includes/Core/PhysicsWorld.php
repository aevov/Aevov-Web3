<?php
/**
 * Physics World Simulation Loop
 *
 * Manages complete physics simulation with time integration,
 * solver orchestration, and state management
 *
 * Features:
 * - Fixed timestep simulation loop
 * - Sub-stepping for stability
 * - Solver orchestration (Newtonian, RigidBody, Fluid, etc.)
 * - Broad and narrow phase collision
 * - Constraint solving
 * - Sleep/wake optimization
 * - Spatial partitioning
 * - Performance profiling
 */

namespace Aevov\PhysicsEngine\Core;

use Aevov\PhysicsEngine\Core\Solvers\NewtonianSolver;
use Aevov\PhysicsEngine\Core\Solvers\RigidBodySolver;
use Aevov\PhysicsEngine\Core\Solvers\FluidSolver;

class PhysicsWorld {

    private $entities = [];
    private $constraints = [];
    private $time_accumulator = 0.0;
    private $simulation_time = 0.0;
    private $frame_count = 0;

    // Solvers
    private $newtonian_solver;
    private $rigid_body_solver;
    private $fluid_solver;
    private $collision_detector;
    private $constraint_solver;

    // Configuration
    private $timestep = 0.016; // 60 Hz
    private $max_substeps = 4;
    private $gravity = ['x' => 0, 'y' => -9.81, 'z' => 0];
    private $sleep_threshold = 0.01; // Speed below which entities sleep
    private $sleep_time_threshold = 1.0; // Time stationary before sleep

    // Performance tracking
    private $performance_stats = [
        'avg_step_time' => 0.0,
        'entity_count' => 0,
        'active_entity_count' => 0,
        'collision_count' => 0,
        'constraint_count' => 0
    ];

    /**
     * Constructor
     */
    public function __construct($config = []) {
        $this->timestep = $config['timestep'] ?? 0.016;
        $this->max_substeps = $config['max_substeps'] ?? 4;
        $this->gravity = $config['gravity'] ?? ['x' => 0, 'y' => -9.81, 'z' => 0];

        // Initialize solvers
        $this->newtonian_solver = new NewtonianSolver([
            'gravity' => abs($this->gravity['y']),
            'integration' => 'verlet'
        ]);
        $this->rigid_body_solver = new RigidBodySolver();
        $this->fluid_solver = new FluidSolver();
        $this->collision_detector = new CollisionDetector();
        $this->constraint_solver = new ConstraintSolver();
    }

    /**
     * Add entity to world
     */
    public function add_entity($entity) {
        $id = $entity['id'] ?? uniqid('entity_');

        // Initialize entity defaults
        $entity = array_merge([
            'id' => $id,
            'active' => true,
            'type' => 'rigid_body',
            'mass' => 1.0,
            'inverse_mass' => 1.0,
            'restitution' => 0.5,
            'friction' => 0.3,
            'position' => ['x' => 0, 'y' => 0, 'z' => 0],
            'velocity' => ['x' => 0, 'y' => 0, 'z' => 0],
            'acceleration' => ['x' => 0, 'y' => 0, 'z' => 0],
            'force_accumulator' => ['x' => 0, 'y' => 0, 'z' => 0],
            'rotation' => ['x' => 0, 'y' => 0, 'z' => 0],
            'angular_velocity' => ['x' => 0, 'y' => 0, 'z' => 0],
            'torque_accumulator' => ['x' => 0, 'y' => 0, 'z' => 0],
            'collider' => ['type' => 'sphere', 'radius' => 1.0],
            'sleep_timer' => 0.0,
            'sleeping' => false
        ], $entity);

        // Calculate inverse mass
        if ($entity['mass'] > 0) {
            $entity['inverse_mass'] = 1.0 / $entity['mass'];
        } else {
            $entity['inverse_mass'] = 0.0; // Infinite mass (static object)
        }

        $this->entities[$id] = $entity;

        return $id;
    }

    /**
     * Remove entity from world
     */
    public function remove_entity($entity_id) {
        unset($this->entities[$entity_id]);
    }

    /**
     * Add constraint to world
     */
    public function add_constraint($constraint) {
        $this->constraints[] = $constraint;
    }

    /**
     * Main simulation step
     *
     * @param float $delta_time Time since last step (real time)
     */
    public function step($delta_time) {
        $step_start = microtime(true);

        // Accumulate time
        $this->time_accumulator += $delta_time;

        // Fixed timestep loop with sub-stepping
        $steps_taken = 0;

        while ($this->time_accumulator >= $this->timestep && $steps_taken < $this->max_substeps) {
            $this->fixed_step($this->timestep);

            $this->time_accumulator -= $this->timestep;
            $this->simulation_time += $this->timestep;
            $steps_taken++;
        }

        // If we took max substeps, clamp accumulator to prevent spiral of death
        if ($steps_taken >= $this->max_substeps) {
            $this->time_accumulator = 0.0;
        }

        $this->frame_count++;

        // Update performance stats
        $step_duration = microtime(true) - $step_start;
        $this->performance_stats['avg_step_time'] =
            0.9 * $this->performance_stats['avg_step_time'] +
            0.1 * $step_duration;

        return $steps_taken;
    }

    /**
     * Fixed timestep simulation
     */
    private function fixed_step($dt) {
        // 1. Wake entities if needed
        $this->update_sleep_state();

        // 2. Reset force accumulators
        $this->reset_force_accumulators();

        // 3. Apply gravity and external forces
        $this->apply_global_forces();

        // 4. Solve Newtonian mechanics (F=ma, integration)
        $this->entities = $this->newtonian_solver->solve($this->entities, $dt);

        // 5. Solve rigid body dynamics (rotation, torque)
        $this->entities = $this->rigid_body_solver->solve($this->entities, $dt);

        // 6. Detect collisions (broad + narrow phase)
        $collisions = $this->collision_detector->detect_collisions($this->entities);
        $this->performance_stats['collision_count'] = count($collisions);

        // 7. Resolve collisions
        $this->resolve_collisions($collisions);

        // 8. Solve constraints
        $this->constraint_solver->solve_constraints($this->constraints, $this->entities);

        // 9. Update performance stats
        $this->update_performance_stats();
    }

    /**
     * Reset force and torque accumulators
     */
    private function reset_force_accumulators() {
        foreach ($this->entities as &$entity) {
            $entity['force_accumulator'] = ['x' => 0, 'y' => 0, 'z' => 0];
            $entity['torque_accumulator'] = ['x' => 0, 'y' => 0, 'z' => 0];
        }
    }

    /**
     * Apply global forces (gravity, drag, etc.)
     */
    private function apply_global_forces() {
        // Gravity is applied in NewtonianSolver, but we can add other global forces here

        // Example: Wind force
        // foreach ($this->entities as &$entity) {
        //     if ($entity['affected_by_wind'] ?? false) {
        //         $entity['force_accumulator']['x'] += $wind_force_x;
        //     }
        // }
    }

    /**
     * Resolve collisions
     */
    private function resolve_collisions($collisions) {
        foreach ($collisions as $collision) {
            $entity_a = &$this->entities[$collision['index_a']];
            $entity_b = &$this->entities[$collision['index_b']];

            // Wake up sleeping entities on collision
            $entity_a['sleeping'] = false;
            $entity_b['sleeping'] = false;
            $entity_a['sleep_timer'] = 0.0;
            $entity_b['sleep_timer'] = 0.0;

            // Resolve using Newtonian solver
            $this->newtonian_solver->resolve_collision($entity_a, $entity_b, $collision);
        }
    }

    /**
     * Update sleep/wake state of entities
     * Sleeping entities are not simulated (optimization)
     */
    private function update_sleep_state() {
        foreach ($this->entities as &$entity) {
            if ($entity['sleeping']) {
                // Skip sleeping entities (unless woken by collision)
                continue;
            }

            // Check if entity is stationary enough to sleep
            $speed = sqrt(
                $entity['velocity']['x'] ** 2 +
                $entity['velocity']['y'] ** 2 +
                $entity['velocity']['z'] ** 2
            );

            $angular_speed = sqrt(
                $entity['angular_velocity']['x'] ** 2 +
                $entity['angular_velocity']['y'] ** 2 +
                $entity['angular_velocity']['z'] ** 2
            );

            if ($speed < $this->sleep_threshold && $angular_speed < $this->sleep_threshold) {
                $entity['sleep_timer'] += $this->timestep;

                if ($entity['sleep_timer'] > $this->sleep_time_threshold) {
                    $entity['sleeping'] = true;
                    $entity['velocity'] = ['x' => 0, 'y' => 0, 'z' => 0];
                    $entity['angular_velocity'] = ['x' => 0, 'y' => 0, 'z' => 0];
                }
            } else {
                $entity['sleep_timer'] = 0.0;
            }
        }
    }

    /**
     * Update performance statistics
     */
    private function update_performance_stats() {
        $this->performance_stats['entity_count'] = count($this->entities);

        $active_count = 0;
        foreach ($this->entities as $entity) {
            if ($entity['active'] && !$entity['sleeping']) {
                $active_count++;
            }
        }

        $this->performance_stats['active_entity_count'] = $active_count;
        $this->performance_stats['constraint_count'] = count($this->constraints);
    }

    /**
     * Apply impulse to entity
     */
    public function apply_impulse($entity_id, $impulse, $point = null) {
        if (!isset($this->entities[$entity_id])) {
            return;
        }

        $entity = &$this->entities[$entity_id];

        // Wake up if sleeping
        $entity['sleeping'] = false;
        $entity['sleep_timer'] = 0.0;

        // Apply linear impulse
        $entity['velocity']['x'] += $impulse['x'] * $entity['inverse_mass'];
        $entity['velocity']['y'] += $impulse['y'] * $entity['inverse_mass'];
        $entity['velocity']['z'] += $impulse['z'] * $entity['inverse_mass'];

        // If point specified, also apply angular impulse
        if ($point !== null) {
            $r = [
                'x' => $point['x'] - $entity['position']['x'],
                'y' => $point['y'] - $entity['position']['y'],
                'z' => $point['z'] - $entity['position']['z']
            ];

            // Angular impulse = r × impulse
            $angular_impulse = [
                'x' => $r['y'] * $impulse['z'] - $r['z'] * $impulse['y'],
                'y' => $r['z'] * $impulse['x'] - $r['x'] * $impulse['z'],
                'z' => $r['x'] * $impulse['y'] - $r['y'] * $impulse['x']
            ];

            // Apply angular impulse (simplified - should use inverse inertia tensor)
            $entity['angular_velocity']['x'] += $angular_impulse['x'] * $entity['inverse_mass'];
            $entity['angular_velocity']['y'] += $angular_impulse['y'] * $entity['inverse_mass'];
            $entity['angular_velocity']['z'] += $angular_impulse['z'] * $entity['inverse_mass'];
        }
    }

    /**
     * Apply force to entity
     */
    public function apply_force($entity_id, $force, $point = null) {
        if (!isset($this->entities[$entity_id])) {
            return;
        }

        $entity = &$this->entities[$entity_id];

        // Wake up if sleeping
        $entity['sleeping'] = false;
        $entity['sleep_timer'] = 0.0;

        if ($point !== null) {
            // Apply force at point (generates torque)
            $this->rigid_body_solver->apply_force_at_point($entity, $force, $point);
        } else {
            // Apply force at center of mass
            $entity['force_accumulator']['x'] += $force['x'];
            $entity['force_accumulator']['y'] += $force['y'];
            $entity['force_accumulator']['z'] += $force['z'];
        }
    }

    /**
     * Ray cast through world
     *
     * @param array $origin Ray origin
     * @param array $direction Ray direction (normalized)
     * @param float $max_distance Maximum ray distance
     * @return array|null Hit information or null
     */
    public function raycast($origin, $direction, $max_distance = 1000.0) {
        $closest_hit = null;
        $closest_distance = $max_distance;

        foreach ($this->entities as $entity) {
            if (!$entity['active']) continue;

            $hit = $this->raycast_entity($origin, $direction, $entity);

            if ($hit && $hit['distance'] < $closest_distance) {
                $closest_distance = $hit['distance'];
                $closest_hit = $hit;
                $closest_hit['entity_id'] = $entity['id'];
            }
        }

        return $closest_hit;
    }

    /**
     * Ray cast against single entity
     */
    private function raycast_entity($origin, $direction, $entity) {
        $type = $entity['collider']['type'];

        if ($type === 'sphere') {
            return $this->raycast_sphere($origin, $direction, $entity);
        }

        if ($type === 'aabb') {
            return $this->raycast_aabb($origin, $direction, $entity);
        }

        return null;
    }

    /**
     * Ray-sphere intersection
     */
    private function raycast_sphere($origin, $direction, $entity) {
        $center = $entity['position'];
        $radius = $entity['collider']['radius'] ?? 1.0;

        // Vector from ray origin to sphere center
        $oc = [
            'x' => $origin['x'] - $center['x'],
            'y' => $origin['y'] - $center['y'],
            'z' => $origin['z'] - $center['z']
        ];

        // Quadratic coefficients
        $a = $direction['x'] ** 2 + $direction['y'] ** 2 + $direction['z'] ** 2;
        $b = 2 * ($oc['x'] * $direction['x'] + $oc['y'] * $direction['y'] + $oc['z'] * $direction['z']);
        $c = $oc['x'] ** 2 + $oc['y'] ** 2 + $oc['z'] ** 2 - $radius ** 2;

        $discriminant = $b * $b - 4 * $a * $c;

        if ($discriminant < 0) {
            return null; // No intersection
        }

        // Calculate hit distance
        $t = (-$b - sqrt($discriminant)) / (2 * $a);

        if ($t < 0) {
            return null; // Behind ray
        }

        // Hit point
        $hit_point = [
            'x' => $origin['x'] + $direction['x'] * $t,
            'y' => $origin['y'] + $direction['y'] * $t,
            'z' => $origin['z'] + $direction['z'] * $t
        ];

        // Normal (from center to hit point)
        $normal = [
            'x' => ($hit_point['x'] - $center['x']) / $radius,
            'y' => ($hit_point['y'] - $center['y']) / $radius,
            'z' => ($hit_point['z'] - $center['z']) / $radius
        ];

        return [
            'distance' => $t,
            'point' => $hit_point,
            'normal' => $normal
        ];
    }

    /**
     * Ray-AABB intersection
     */
    private function raycast_aabb($origin, $direction, $entity) {
        $half_size = $entity['collider']['half_size'] ?? ['x' => 1, 'y' => 1, 'z' => 1];
        $center = $entity['position'];

        $min = [
            'x' => $center['x'] - $half_size['x'],
            'y' => $center['y'] - $half_size['y'],
            'z' => $center['z'] - $half_size['z']
        ];

        $max = [
            'x' => $center['x'] + $half_size['x'],
            'y' => $center['y'] + $half_size['y'],
            'z' => $center['z'] + $half_size['z']
        ];

        $t_min = 0.0;
        $t_max = PHP_FLOAT_MAX;

        // Check each axis
        foreach (['x', 'y', 'z'] as $axis) {
            if (abs($direction[$axis]) < 0.0001) {
                // Ray parallel to axis
                if ($origin[$axis] < $min[$axis] || $origin[$axis] > $max[$axis]) {
                    return null;
                }
            } else {
                $t1 = ($min[$axis] - $origin[$axis]) / $direction[$axis];
                $t2 = ($max[$axis] - $origin[$axis]) / $direction[$axis];

                if ($t1 > $t2) {
                    $temp = $t1;
                    $t1 = $t2;
                    $t2 = $temp;
                }

                $t_min = max($t_min, $t1);
                $t_max = min($t_max, $t2);

                if ($t_min > $t_max) {
                    return null;
                }
            }
        }

        if ($t_min < 0) {
            return null;
        }

        $hit_point = [
            'x' => $origin['x'] + $direction['x'] * $t_min,
            'y' => $origin['y'] + $direction['y'] * $t_min,
            'z' => $origin['z'] + $direction['z'] * $t_min
        ];

        // Approximate normal (which face was hit)
        $normal = ['x' => 0, 'y' => 0, 'z' => 0];
        $epsilon = 0.001;

        if (abs($hit_point['x'] - $min['x']) < $epsilon) $normal['x'] = -1;
        if (abs($hit_point['x'] - $max['x']) < $epsilon) $normal['x'] = 1;
        if (abs($hit_point['y'] - $min['y']) < $epsilon) $normal['y'] = -1;
        if (abs($hit_point['y'] - $max['y']) < $epsilon) $normal['y'] = 1;
        if (abs($hit_point['z'] - $min['z']) < $epsilon) $normal['z'] = -1;
        if (abs($hit_point['z'] - $max['z']) < $epsilon) $normal['z'] = 1;

        return [
            'distance' => $t_min,
            'point' => $hit_point,
            'normal' => $normal
        ];
    }

    /**
     * Get all entities in world
     */
    public function get_entities() {
        return $this->entities;
    }

    /**
     * Get entity by ID
     */
    public function get_entity($entity_id) {
        return $this->entities[$entity_id] ?? null;
    }

    /**
     * Get performance statistics
     */
    public function get_performance_stats() {
        return $this->performance_stats;
    }

    /**
     * Get simulation time
     */
    public function get_simulation_time() {
        return $this->simulation_time;
    }

    /**
     * Get frame count
     */
    public function get_frame_count() {
        return $this->frame_count;
    }
}
