<?php
/**
 * Force Field Solver
 *
 * Electromagnetic, gravitational, and custom force fields
 */

namespace Aevov\PhysicsEngine\Core\Solvers;

class FieldSolver {

    /**
     * Solve field interactions
     */
    public function solve($entities, $fields, $dt) {
        foreach ($entities as &$entity) {
            if (!$entity['active']) continue;

            foreach ($fields as $field) {
                $force = $this->calculate_field_force($entity, $field);

                $entity['force_accumulator']['x'] += $force['x'];
                $entity['force_accumulator']['y'] += $force['y'];
                $entity['force_accumulator']['z'] += $force['z'];
            }
        }

        return $entities;
    }

    /**
     * Calculate force from field on entity
     */
    private function calculate_field_force($entity, $field) {
        $distance = $this->calculate_distance($entity['position'], $field['position']);

        // Check if entity is within field radius
        if ($distance > $field['radius']) {
            return ['x' => 0, 'y' => 0, 'z' => 0];
        }

        $field_type = $field['type'] ?? 'radial';

        switch ($field_type) {
            case 'gravitational':
                return $this->gravitational_field($entity, $field, $distance);

            case 'electromagnetic':
                return $this->electromagnetic_field($entity, $field, $distance);

            case 'radial':
                return $this->radial_field($entity, $field, $distance);

            case 'directional':
                return $this->directional_field($entity, $field);

            case 'vortex':
                return $this->vortex_field($entity, $field, $distance);

            case 'custom':
                return $this->custom_field($entity, $field, $distance);

            default:
                return ['x' => 0, 'y' => 0, 'z' => 0];
        }
    }

    /**
     * Gravitational field (Newton's law)
     */
    private function gravitational_field($entity, $field, $distance) {
        $G = 6.67430e-11; // Gravitational constant
        $field_mass = $field['mass'] ?? 1000;

        if ($distance < 0.1) $distance = 0.1; // Prevent singularity

        $force_magnitude = $G * $field_mass * $entity['mass'] / ($distance * $distance);

        return $this->radial_force($entity['position'], $field['position'], $distance, $force_magnitude);
    }

    /**
     * Electromagnetic field
     */
    private function electromagnetic_field($entity, $field, $distance) {
        if (!isset($entity['charge'])) {
            return ['x' => 0, 'y' => 0, 'z' => 0];
        }

        $k = 8.99e9; // Coulomb's constant
        $field_charge = $field['charge'] ?? 1;

        if ($distance < 0.1) $distance = 0.1;

        $force_magnitude = $k * $field_charge * $entity['charge'] / ($distance * $distance);

        return $this->radial_force($entity['position'], $field['position'], $distance, $force_magnitude);
    }

    /**
     * Simple radial field
     */
    private function radial_field($entity, $field, $distance) {
        $strength = $field['strength'];
        $falloff = $field['falloff'] ?? 'inverse_square';

        $force_magnitude = $this->apply_falloff($strength, $distance, $field['radius'], $falloff);

        return $this->radial_force($entity['position'], $field['position'], $distance, $force_magnitude);
    }

    /**
     * Directional field (wind, current)
     */
    private function directional_field($entity, $field) {
        $strength = $field['strength'];
        $direction = $field['direction'] ?? ['x' => 1, 'y' => 0, 'z' => 0];

        // Normalize direction
        $length = sqrt($direction['x']**2 + $direction['y']**2 + $direction['z']**2);
        if ($length < 0.0001) return ['x' => 0, 'y' => 0, 'z' => 0];

        return [
            'x' => ($direction['x'] / $length) * $strength,
            'y' => ($direction['y'] / $length) * $strength,
            'z' => ($direction['z'] / $length) * $strength
        ];
    }

    /**
     * Vortex field (tornado, whirlpool)
     */
    private function vortex_field($entity, $field, $distance) {
        $strength = $field['strength'];
        $axis = $field['axis'] ?? ['x' => 0, 'y' => 1, 'z' => 0];

        // Calculate tangential direction
        $to_entity = [
            'x' => $entity['position']['x'] - $field['position']['x'],
            'y' => $entity['position']['y'] - $field['position']['y'],
            'z' => $entity['position']['z'] - $field['position']['z']
        ];

        // Cross product: axis × to_entity
        $tangent = [
            'x' => $axis['y'] * $to_entity['z'] - $axis['z'] * $to_entity['y'],
            'y' => $axis['z'] * $to_entity['x'] - $axis['x'] * $to_entity['z'],
            'z' => $axis['x'] * $to_entity['y'] - $axis['y'] * $to_entity['x']
        ];

        $length = sqrt($tangent['x']**2 + $tangent['y']**2 + $tangent['z']**2);
        if ($length < 0.0001) return ['x' => 0, 'y' => 0, 'z' => 0];

        // Tangential force
        $tangential_strength = $strength / max($distance, 0.1);

        // Inward force (toward center)
        $inward_strength = $strength * 0.1;
        $inward_dir = [
            'x' => -$to_entity['x'] / max($distance, 0.1),
            'y' => -$to_entity['y'] / max($distance, 0.1),
            'z' => -$to_entity['z'] / max($distance, 0.1)
        ];

        return [
            'x' => ($tangent['x'] / $length) * $tangential_strength + $inward_dir['x'] * $inward_strength,
            'y' => ($tangent['y'] / $length) * $tangential_strength + $inward_dir['y'] * $inward_strength,
            'z' => ($tangent['z'] / $length) * $tangential_strength + $inward_dir['z'] * $inward_strength
        ];
    }

    /**
     * Custom field (uses user-defined function)
     */
    private function custom_field($entity, $field, $distance) {
        if (isset($field['force_function']) && is_callable($field['force_function'])) {
            return call_user_func($field['force_function'], $entity, $field, $distance);
        }

        return ['x' => 0, 'y' => 0, 'z' => 0];
    }

    /**
     * Calculate radial force vector
     */
    private function radial_force($pos_entity, $pos_field, $distance, $magnitude) {
        if ($distance < 0.0001) {
            return ['x' => 0, 'y' => 0, 'z' => 0];
        }

        $direction = [
            'x' => $pos_field['x'] - $pos_entity['x'],
            'y' => $pos_field['y'] - $pos_entity['y'],
            'z' => $pos_field['z'] - $pos_entity['z']
        ];

        return [
            'x' => ($direction['x'] / $distance) * $magnitude,
            'y' => ($direction['y'] / $distance) * $magnitude,
            'z' => ($direction['z'] / $distance) * $magnitude
        ];
    }

    /**
     * Apply falloff function
     */
    private function apply_falloff($strength, $distance, $radius, $falloff) {
        switch ($falloff) {
            case 'inverse_square':
                return $strength / max($distance * $distance, 0.01);

            case 'inverse':
                return $strength / max($distance, 0.1);

            case 'linear':
                return $strength * (1 - $distance / $radius);

            case 'constant':
                return $strength;

            case 'exponential':
                return $strength * exp(-$distance / $radius);

            default:
                return $strength;
        }
    }

    /**
     * Calculate distance between positions
     */
    private function calculate_distance($pos_a, $pos_b) {
        $dx = $pos_b['x'] - $pos_a['x'];
        $dy = $pos_b['y'] - $pos_a['y'];
        $dz = $pos_b['z'] - $pos_a['z'];
        return sqrt($dx*$dx + $dy*$dy + $dz*$dz);
    }
}
