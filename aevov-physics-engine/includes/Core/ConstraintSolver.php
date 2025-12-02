<?php
/**
 * Constraint Solver
 *
 * Solves various physics constraints:
 * - Distance constraints (ropes, springs)
 * - Hinge constraints (doors, joints)
 * - Fixed constraints (welding)
 * - Slider constraints
 * - Motor constraints
 * - Evolutionary constraints (learned from simulation)
 */

namespace Aevov\PhysicsEngine\Core;

class ConstraintSolver {

    private $iteration_count = 10; // Solver iterations

    /**
     * Solve all constraints
     */
    public function solve_constraints($constraints, &$entities) {
        if (empty($constraints)) return;

        for ($iteration = 0; $iteration < $this->iteration_count; $iteration++) {
            foreach ($constraints as $constraint) {
                $this->solve_constraint($constraint, $entities);
            }
        }
    }

    /**
     * Solve individual constraint
     */
    private function solve_constraint($constraint, &$entities) {
        switch ($constraint['type']) {
            case 'distance':
                $this->solve_distance_constraint($constraint, $entities);
                break;
            case 'hinge':
                $this->solve_hinge_constraint($constraint, $entities);
                break;
            case 'fixed':
                $this->solve_fixed_constraint($constraint, $entities);
                break;
            case 'spring':
                $this->solve_spring_constraint($constraint, $entities);
                break;
            case 'ground_plane':
                $this->solve_ground_plane_constraint($constraint, $entities);
                break;
            case 'evolutionary':
                $this->solve_evolutionary_constraint($constraint, $entities);
                break;
        }
    }

    /**
     * Distance constraint (maintains fixed distance between two entities)
     */
    private function solve_distance_constraint($constraint, &$entities) {
        $entity_a = &$entities[$constraint['entity_a_index']];
        $entity_b = &$entities[$constraint['entity_b_index']];

        $target_distance = $constraint['distance'];

        // Calculate current distance
        $dx = $entity_b['position']['x'] - $entity_a['position']['x'];
        $dy = $entity_b['position']['y'] - $entity_a['position']['y'];
        $dz = ($entity_b['position']['z'] ?? 0) - ($entity_a['position']['z'] ?? 0);

        $current_distance = sqrt($dx * $dx + $dy * $dy + $dz * $dz);

        if ($current_distance < 0.0001) return;

        // Calculate correction
        $error = $current_distance - $target_distance;
        $correction_factor = $error / $current_distance * 0.5; // 50% correction per entity

        $correction = [
            'x' => $dx * $correction_factor,
            'y' => $dy * $correction_factor,
            'z' => $dz * $correction_factor
        ];

        // Apply correction based on inverse mass
        $total_inverse_mass = $entity_a['inverse_mass'] + $entity_b['inverse_mass'];
        if ($total_inverse_mass > 0) {
            $entity_a['position']['x'] += $correction['x'] * ($entity_a['inverse_mass'] / $total_inverse_mass);
            $entity_a['position']['y'] += $correction['y'] * ($entity_a['inverse_mass'] / $total_inverse_mass);
            $entity_a['position']['z'] = ($entity_a['position']['z'] ?? 0) + $correction['z'] * ($entity_a['inverse_mass'] / $total_inverse_mass);

            $entity_b['position']['x'] -= $correction['x'] * ($entity_b['inverse_mass'] / $total_inverse_mass);
            $entity_b['position']['y'] -= $correction['y'] * ($entity_b['inverse_mass'] / $total_inverse_mass);
            $entity_b['position']['z'] = ($entity_b['position']['z'] ?? 0) - $correction['z'] * ($entity_b['inverse_mass'] / $total_inverse_mass);
        }
    }

    /**
     * Spring constraint (Hooke's law)
     */
    private function solve_spring_constraint($constraint, &$entities) {
        $entity_a = &$entities[$constraint['entity_a_index']];
        $entity_b = &$entities[$constraint['entity_b_index']];

        $rest_length = $constraint['rest_length'];
        $stiffness = $constraint['stiffness'] ?? 100.0;
        $damping = $constraint['damping'] ?? 0.1;

        // Calculate current distance
        $dx = $entity_b['position']['x'] - $entity_a['position']['x'];
        $dy = $entity_b['position']['y'] - $entity_a['position']['y'];
        $dz = ($entity_b['position']['z'] ?? 0) - ($entity_a['position']['z'] ?? 0);

        $current_distance = sqrt($dx * $dx + $dy * $dy + $dz * $dz);

        if ($current_distance < 0.0001) return;

        // Spring force: F = -k * (x - x0)
        $displacement = $current_distance - $rest_length;
        $force_magnitude = -$stiffness * $displacement;

        // Damping force
        $rel_velocity = [
            'x' => $entity_b['velocity']['x'] - $entity_a['velocity']['x'],
            'y' => $entity_b['velocity']['y'] - $entity_a['velocity']['y'],
            'z' => $entity_b['velocity']['z'] - $entity_a['velocity']['z']
        ];

        $direction = [
            'x' => $dx / $current_distance,
            'y' => $dy / $current_distance,
            'z' => $dz / $current_distance
        ];

        $velocity_along_spring =
            $rel_velocity['x'] * $direction['x'] +
            $rel_velocity['y'] * $direction['y'] +
            $rel_velocity['z'] * $direction['z'];

        $damping_force = -$damping * $velocity_along_spring;

        $total_force = $force_magnitude + $damping_force;

        // Apply force
        $force = [
            'x' => $direction['x'] * $total_force,
            'y' => $direction['y'] * $total_force,
            'z' => $direction['z'] * $total_force
        ];

        $entity_a['force_accumulator']['x'] += $force['x'];
        $entity_a['force_accumulator']['y'] += $force['y'];
        $entity_a['force_accumulator']['z'] += $force['z'];

        $entity_b['force_accumulator']['x'] -= $force['x'];
        $entity_b['force_accumulator']['y'] -= $force['y'];
        $entity_b['force_accumulator']['z'] -= $force['z'];
    }

    /**
     * Ground plane constraint
     */
    private function solve_ground_plane_constraint($constraint, &$entities) {
        $plane_y = $constraint['position']['y'] ?? 0;

        foreach ($entities as &$entity) {
            if (!$entity['active']) continue;

            // Get entity bottom position
            $bottom_y = $entity['position']['y'];
            if (isset($entity['collider']['type']) && $entity['collider']['type'] === 'sphere') {
                $bottom_y -= $entity['collider']['radius'] ?? 1.0;
            } elseif (isset($entity['collider']['half_size'])) {
                $bottom_y -= $entity['collider']['half_size']['y'];
            }

            // Check if below ground
            if ($bottom_y < $plane_y) {
                // Correct position
                $penetration = $plane_y - $bottom_y;
                $entity['position']['y'] += $penetration;

                // Apply friction and restitution
                if ($entity['velocity']['y'] < 0) {
                    $entity['velocity']['y'] *= -($entity['restitution'] ?? 0.5);

                    // Friction
                    $friction = $entity['friction'] ?? 0.3;
                    $entity['velocity']['x'] *= (1 - $friction);
                    $entity['velocity']['z'] *= (1 - $friction);

                    // Stop tiny bounces
                    if (abs($entity['velocity']['y']) < 0.1) {
                        $entity['velocity']['y'] = 0;
                    }
                }
            }
        }
    }

    /**
     * Fixed constraint (welds two entities together)
     */
    private function solve_fixed_constraint($constraint, &$entities) {
        $entity_a = &$entities[$constraint['entity_a_index']];
        $entity_b = &$entities[$constraint['entity_b_index']];

        // Calculate offset from initial positions
        $target_offset = $constraint['offset'] ?? ['x' => 0, 'y' => 0, 'z' => 0];

        $current_offset = [
            'x' => $entity_b['position']['x'] - $entity_a['position']['x'],
            'y' => $entity_b['position']['y'] - $entity_a['position']['y'],
            'z' => ($entity_b['position']['z'] ?? 0) - ($entity_a['position']['z'] ?? 0)
        ];

        $error = [
            'x' => $current_offset['x'] - $target_offset['x'],
            'y' => $current_offset['y'] - $target_offset['y'],
            'z' => $current_offset['z'] - $target_offset['z']
        ];

        // Apply strong correction
        $correction_strength = 0.8;
        $total_inverse_mass = $entity_a['inverse_mass'] + $entity_b['inverse_mass'];

        if ($total_inverse_mass > 0) {
            $entity_a['position']['x'] += $error['x'] * $correction_strength * ($entity_a['inverse_mass'] / $total_inverse_mass);
            $entity_a['position']['y'] += $error['y'] * $correction_strength * ($entity_a['inverse_mass'] / $total_inverse_mass);
            $entity_a['position']['z'] = ($entity_a['position']['z'] ?? 0) + $error['z'] * $correction_strength * ($entity_a['inverse_mass'] / $total_inverse_mass);

            $entity_b['position']['x'] -= $error['x'] * $correction_strength * ($entity_b['inverse_mass'] / $total_inverse_mass);
            $entity_b['position']['y'] -= $error['y'] * $correction_strength * ($entity_b['inverse_mass'] / $total_inverse_mass);
            $entity_b['position']['z'] = ($entity_b['position']['z'] ?? 0) - $error['z'] * $correction_strength * ($entity_b['inverse_mass'] / $total_inverse_mass);
        }
    }

    /**
     * Hinge constraint (allows rotation around one axis)
     */
    private function solve_hinge_constraint($constraint, &$entities) {
        // Simplified hinge - maintains position, allows rotation
        $this->solve_fixed_constraint($constraint, $entities);

        // TODO: Add angular constraint to limit rotation to hinge axis
    }

    /**
     * Evolutionary constraint (learned from simulation)
     *
     * This constraint type uses neural networks or evolutionary algorithms
     * to learn optimal constraint parameters from simulation outcomes
     */
    private function solve_evolutionary_constraint($constraint, &$entities) {
        // Get learned parameters
        $learned_params = $constraint['learned_params'] ?? [];

        // Apply learned constraint behavior
        if (isset($learned_params['constraint_type'])) {
            $adapted_constraint = array_merge($constraint, [
                'type' => $learned_params['constraint_type']
            ]);
            $this->solve_constraint($adapted_constraint, $entities);
        }

        // Collect feedback for further learning
        do_action('aevov_physics_constraint_feedback', $constraint, $entities);
    }
}
