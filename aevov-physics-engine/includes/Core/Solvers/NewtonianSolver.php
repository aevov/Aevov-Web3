<?php
/**
 * Newtonian Physics Solver
 *
 * Classical mechanics: F = ma, momentum conservation, energy conservation
 */

namespace Aevov\PhysicsEngine\Core\Solvers;

class NewtonianSolver {

    private $gravity = 9.81; // m/s²
    private $air_density = 1.225; // kg/m³ (sea level)
    private $integration_method = 'verlet'; // 'euler' or 'verlet'

    /**
     * Constructor
     */
    public function __construct($config = []) {
        $this->gravity = $config['gravity'] ?? 9.81;
        $this->air_density = $config['air_density'] ?? 1.225;
        $this->integration_method = $config['integration'] ?? 'verlet';
    }

    /**
     * Solve Newtonian mechanics for entities
     */
    public function solve($entities, $dt) {
        // Apply forces first
        $this->apply_gravity($entities);
        $this->apply_drag($entities);

        // Integrate based on method
        if ($this->integration_method === 'verlet') {
            $entities = $this->verlet_integration($entities, $dt);
        } else {
            $entities = $this->euler_integration($entities, $dt);
        }

        return $entities;
    }

    /**
     * Apply gravitational force to all entities
     */
    private function apply_gravity(&$entities) {
        foreach ($entities as &$entity) {
            if (!$entity['active'] || $entity['mass'] <= 0) continue;

            // Skip if entity ignores gravity
            if (isset($entity['ignore_gravity']) && $entity['ignore_gravity']) {
                continue;
            }

            // F = mg
            $entity['force_accumulator']['y'] -= $entity['mass'] * $this->gravity;
        }
    }

    /**
     * Apply drag force (air resistance)
     * F_drag = -0.5 * ρ * v² * C_d * A
     */
    private function apply_drag(&$entities) {
        foreach ($entities as &$entity) {
            if (!$entity['active']) continue;

            $velocity = $entity['velocity'];
            $speed_squared =
                $velocity['x'] ** 2 +
                $velocity['y'] ** 2 +
                $velocity['z'] ** 2;

            if ($speed_squared < 0.001) continue;

            $speed = sqrt($speed_squared);

            // Drag coefficient (sphere ≈ 0.47, box ≈ 1.05)
            $drag_coefficient = $entity['drag_coefficient'] ?? 0.47;

            // Cross-sectional area (estimated from collider)
            $area = $this->estimate_cross_sectional_area($entity);

            // Drag magnitude
            $drag_magnitude = 0.5 * $this->air_density * $speed_squared *
                             $drag_coefficient * $area;

            // Drag direction (opposite to velocity)
            $drag_direction = [
                'x' => -$velocity['x'] / $speed,
                'y' => -$velocity['y'] / $speed,
                'z' => -$velocity['z'] / $speed
            ];

            // Apply drag force
            $entity['force_accumulator']['x'] += $drag_direction['x'] * $drag_magnitude;
            $entity['force_accumulator']['y'] += $drag_direction['y'] * $drag_magnitude;
            $entity['force_accumulator']['z'] += $drag_direction['z'] * $drag_magnitude;
        }
    }

    /**
     * Euler integration (simple but less stable)
     */
    private function euler_integration($entities, $dt) {
        foreach ($entities as &$entity) {
            if (!$entity['active'] || $entity['mass'] <= 0) continue;

            // Newton's second law: a = F/m
            $entity['acceleration'] = [
                'x' => $entity['force_accumulator']['x'] / $entity['mass'],
                'y' => $entity['force_accumulator']['y'] / $entity['mass'],
                'z' => $entity['force_accumulator']['z'] / $entity['mass']
            ];

            // Update velocity: v = v0 + a*dt
            $entity['velocity']['x'] += $entity['acceleration']['x'] * $dt;
            $entity['velocity']['y'] += $entity['acceleration']['y'] * $dt;
            $entity['velocity']['z'] += $entity['acceleration']['z'] * $dt;

            // Update position: x = x0 + v*dt
            $entity['position']['x'] += $entity['velocity']['x'] * $dt;
            $entity['position']['y'] += $entity['velocity']['y'] * $dt;
            $entity['position']['z'] += $entity['velocity']['z'] * $dt;
        }

        return $entities;
    }

    /**
     * Verlet integration (more stable, better energy conservation)
     * x(t+dt) = 2*x(t) - x(t-dt) + a(t)*dt²
     */
    private function verlet_integration($entities, $dt) {
        foreach ($entities as &$entity) {
            if (!$entity['active'] || $entity['mass'] <= 0) continue;

            // Initialize previous position if not exists
            if (!isset($entity['prev_position'])) {
                $entity['prev_position'] = [
                    'x' => $entity['position']['x'] - $entity['velocity']['x'] * $dt,
                    'y' => $entity['position']['y'] - $entity['velocity']['y'] * $dt,
                    'z' => $entity['position']['z'] - $entity['velocity']['z'] * $dt
                ];
            }

            // Calculate acceleration
            $acceleration = [
                'x' => $entity['force_accumulator']['x'] / $entity['mass'],
                'y' => $entity['force_accumulator']['y'] / $entity['mass'],
                'z' => $entity['force_accumulator']['z'] / $entity['mass']
            ];

            // Store current position
            $current_pos = $entity['position'];

            // Verlet position update
            $entity['position']['x'] = 2 * $current_pos['x'] - $entity['prev_position']['x'] +
                                      $acceleration['x'] * $dt * $dt;
            $entity['position']['y'] = 2 * $current_pos['y'] - $entity['prev_position']['y'] +
                                      $acceleration['y'] * $dt * $dt;
            $entity['position']['z'] = 2 * $current_pos['z'] - $entity['prev_position']['z'] +
                                      $acceleration['z'] * $dt * $dt;

            // Update velocity (from position change)
            $entity['velocity']['x'] = ($entity['position']['x'] - $entity['prev_position']['x']) / (2 * $dt);
            $entity['velocity']['y'] = ($entity['position']['y'] - $entity['prev_position']['y']) / (2 * $dt);
            $entity['velocity']['z'] = ($entity['position']['z'] - $entity['prev_position']['z']) / (2 * $dt);

            // Store previous position for next iteration
            $entity['prev_position'] = $current_pos;
            $entity['acceleration'] = $acceleration;
        }

        return $entities;
    }

    /**
     * Apply friction force (surface contact)
     * F_friction = μ * F_normal
     */
    public function apply_friction(&$entity, $normal_force, $friction_coefficient = 0.3) {
        if (!$entity['active']) return;

        $velocity = $entity['velocity'];
        $speed = sqrt($velocity['x'] ** 2 + $velocity['y'] ** 2 + $velocity['z'] ** 2);

        if ($speed < 0.001) return;

        // Friction opposes motion
        $friction_magnitude = $friction_coefficient * $normal_force;

        // Static vs kinetic friction
        $kinetic_coefficient = $friction_coefficient * 0.8; // Kinetic < static
        if ($speed > 0.1) {
            $friction_magnitude = $kinetic_coefficient * $normal_force;
        }

        // Friction direction (opposite to velocity)
        $friction_direction = [
            'x' => -$velocity['x'] / $speed,
            'y' => -$velocity['y'] / $speed,
            'z' => -$velocity['z'] / $speed
        ];

        // Apply friction force
        $entity['force_accumulator']['x'] += $friction_direction['x'] * $friction_magnitude;
        $entity['force_accumulator']['y'] += $friction_direction['y'] * $friction_magnitude;
        $entity['force_accumulator']['z'] += $friction_direction['z'] * $friction_magnitude;
    }

    /**
     * Resolve collision with impulse-based method
     */
    public function resolve_collision(&$entity_a, &$entity_b, $collision_info) {
        $normal = $collision_info['normal'];

        // Relative velocity
        $rel_velocity = [
            'x' => $entity_b['velocity']['x'] - $entity_a['velocity']['x'],
            'y' => $entity_b['velocity']['y'] - $entity_a['velocity']['y'],
            'z' => $entity_b['velocity']['z'] - $entity_a['velocity']['z']
        ];

        // Velocity along normal
        $velocity_normal =
            $rel_velocity['x'] * $normal['x'] +
            $rel_velocity['y'] * $normal['y'] +
            $rel_velocity['z'] * $normal['z'];

        // Don't resolve if separating
        if ($velocity_normal > 0) return;

        // Coefficient of restitution (bounciness)
        $restitution = min($entity_a['restitution'] ?? 0.5, $entity_b['restitution'] ?? 0.5);

        // Calculate impulse scalar
        $impulse_scalar = -(1 + $restitution) * $velocity_normal;
        $impulse_scalar /= (1 / $entity_a['mass'] + 1 / $entity_b['mass']);

        // Apply impulse
        $impulse = [
            'x' => $impulse_scalar * $normal['x'],
            'y' => $impulse_scalar * $normal['y'],
            'z' => $impulse_scalar * $normal['z']
        ];

        $entity_a['velocity']['x'] -= $impulse['x'] / $entity_a['mass'];
        $entity_a['velocity']['y'] -= $impulse['y'] / $entity_a['mass'];
        $entity_a['velocity']['z'] -= $impulse['z'] / $entity_a['mass'];

        $entity_b['velocity']['x'] += $impulse['x'] / $entity_b['mass'];
        $entity_b['velocity']['y'] += $impulse['y'] / $entity_b['mass'];
        $entity_b['velocity']['z'] += $impulse['z'] / $entity_b['mass'];

        // Apply friction impulse (tangential)
        $this->apply_collision_friction($entity_a, $entity_b, $normal, $impulse_scalar);
    }

    /**
     * Apply friction during collision (tangential impulse)
     */
    private function apply_collision_friction(&$entity_a, &$entity_b, $normal, $normal_impulse) {
        // Tangent velocity (perpendicular to normal)
        $rel_velocity = [
            'x' => $entity_b['velocity']['x'] - $entity_a['velocity']['x'],
            'y' => $entity_b['velocity']['y'] - $entity_a['velocity']['y'],
            'z' => $entity_b['velocity']['z'] - $entity_a['velocity']['z']
        ];

        $normal_component =
            $rel_velocity['x'] * $normal['x'] +
            $rel_velocity['y'] * $normal['y'] +
            $rel_velocity['z'] * $normal['z'];

        $tangent_velocity = [
            'x' => $rel_velocity['x'] - $normal_component * $normal['x'],
            'y' => $rel_velocity['y'] - $normal_component * $normal['y'],
            'z' => $rel_velocity['z'] - $normal_component * $normal['z']
        ];

        $tangent_speed = sqrt(
            $tangent_velocity['x'] ** 2 +
            $tangent_velocity['y'] ** 2 +
            $tangent_velocity['z'] ** 2
        );

        if ($tangent_speed < 0.001) return;

        // Friction coefficient
        $friction = ($entity_a['friction'] ?? 0.3 + $entity_b['friction'] ?? 0.3) / 2;

        // Coulomb friction: F_friction ≤ μ * F_normal
        $friction_impulse = $friction * abs($normal_impulse);

        // Tangent direction
        $tangent = [
            'x' => $tangent_velocity['x'] / $tangent_speed,
            'y' => $tangent_velocity['y'] / $tangent_speed,
            'z' => $tangent_velocity['z'] / $tangent_speed
        ];

        // Apply friction impulse
        $entity_a['velocity']['x'] += $tangent['x'] * $friction_impulse / $entity_a['mass'];
        $entity_a['velocity']['y'] += $tangent['y'] * $friction_impulse / $entity_a['mass'];
        $entity_a['velocity']['z'] += $tangent['z'] * $friction_impulse / $entity_a['mass'];

        $entity_b['velocity']['x'] -= $tangent['x'] * $friction_impulse / $entity_b['mass'];
        $entity_b['velocity']['y'] -= $tangent['y'] * $friction_impulse / $entity_b['mass'];
        $entity_b['velocity']['z'] -= $tangent['z'] * $friction_impulse / $entity_b['mass'];
    }

    /**
     * Estimate cross-sectional area from collider
     */
    private function estimate_cross_sectional_area($entity) {
        if (!isset($entity['collider'])) return 1.0;

        $collider = $entity['collider'];

        if ($collider['type'] === 'sphere') {
            $radius = $collider['radius'] ?? 1.0;
            return M_PI * $radius * $radius;
        }

        if ($collider['type'] === 'aabb') {
            $half_size = $collider['half_size'] ?? ['x' => 1, 'y' => 1, 'z' => 1];
            // Average of front and side areas
            return ($half_size['x'] * $half_size['y'] + $half_size['y'] * $half_size['z']) / 2;
        }

        return 1.0;
    }

    /**
     * Calculate kinetic energy
     */
    public function calculate_kinetic_energy($entity) {
        $v_squared =
            $entity['velocity']['x'] ** 2 +
            $entity['velocity']['y'] ** 2 +
            $entity['velocity']['z'] ** 2;

        return 0.5 * $entity['mass'] * $v_squared;
    }

    /**
     * Calculate momentum
     */
    public function calculate_momentum($entity) {
        return [
            'x' => $entity['mass'] * $entity['velocity']['x'],
            'y' => $entity['mass'] * $entity['velocity']['y'],
            'z' => $entity['mass'] * $entity['velocity']['z']
        ];
    }
}
