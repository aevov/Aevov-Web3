<?php
/**
 * Soft Body Physics Solver
 *
 * Stable deformable object simulation (beyond physX-Anything)
 * Uses mass-spring system with position-based dynamics
 */

namespace Aevov\PhysicsEngine\Core\Solvers;

class SoftBodySolver {

    private $stability_iterations = 5;

    /**
     * Solve soft body dynamics
     */
    public function solve($soft_bodies, $dt) {
        foreach ($soft_bodies as &$body) {
            if (!$body['active']) continue;

            // Apply forces to vertices
            $this->apply_vertex_forces($body, $dt);

            // Integrate positions
            $this->integrate_vertices($body, $dt);

            // Solve constraints (position-based dynamics for stability)
            for ($i = 0; $i < $this->stability_iterations; $i++) {
                $this->solve_constraints($body);
            }

            // Update mesh
            $this->update_mesh($body);
        }

        return $soft_bodies;
    }

    /**
     * Apply forces to all vertices
     */
    private function apply_vertex_forces(&$body, $dt) {
        foreach ($body['vertices'] as &$vertex) {
            // Reset forces
            $vertex['force'] = ['x' => 0, 'y' => 0, 'z' => 0];

            // Gravity
            $vertex['force']['y'] -= $vertex['mass'] * 9.81;

            // Spring forces from connected edges
            foreach ($vertex['connected_edges'] as $edge_id) {
                $edge = $body['edges'][$edge_id];
                $other_vertex_id = ($edge['v1'] === $vertex['id']) ? $edge['v2'] : $edge['v1'];
                $other_vertex = &$body['vertices'][$other_vertex_id];

                // Spring force
                $dx = $other_vertex['position']['x'] - $vertex['position']['x'];
                $dy = $other_vertex['position']['y'] - $vertex['position']['y'];
                $dz = $other_vertex['position']['z'] - $vertex['position']['z'];

                $current_length = sqrt($dx*$dx + $dy*$dy + $dz*$dz);
                $rest_length = $edge['rest_length'];

                if ($current_length > 0.0001) {
                    $force_magnitude = $edge['stiffness'] * ($current_length - $rest_length);

                    $vertex['force']['x'] += ($dx / $current_length) * $force_magnitude;
                    $vertex['force']['y'] += ($dy / $current_length) * $force_magnitude;
                    $vertex['force']['z'] += ($dz / $current_length) * $force_magnitude;

                    // Damping
                    $rel_velocity = [
                        'x' => $other_vertex['velocity']['x'] - $vertex['velocity']['x'],
                        'y' => $other_vertex['velocity']['y'] - $vertex['velocity']['y'],
                        'z' => $other_vertex['velocity']['z'] - $vertex['velocity']['z']
                    ];

                    $damping_force = $edge['damping'] ?? 0.1;
                    $vertex['force']['x'] += $rel_velocity['x'] * $damping_force;
                    $vertex['force']['y'] += $rel_velocity['y'] * $damping_force;
                    $vertex['force']['z'] += $rel_velocity['z'] * $damping_force;
                }
            }
        }
    }

    /**
     * Integrate vertex positions
     */
    private function integrate_vertices(&$body, $dt) {
        foreach ($body['vertices'] as &$vertex) {
            if ($vertex['fixed']) continue;

            // Update velocity
            $vertex['velocity']['x'] += ($vertex['force']['x'] / $vertex['mass']) * $dt;
            $vertex['velocity']['y'] += ($vertex['force']['y'] / $vertex['mass']) * $dt;
            $vertex['velocity']['z'] += ($vertex['force']['z'] / $vertex['mass']) * $dt;

            // Update position
            $vertex['position']['x'] += $vertex['velocity']['x'] * $dt;
            $vertex['position']['y'] += $vertex['velocity']['y'] * $dt;
            $vertex['position']['z'] += $vertex['velocity']['z'] * $dt;
        }
    }

    /**
     * Solve constraints using position-based dynamics (for stability)
     */
    private function solve_constraints(&$body) {
        // Distance constraints
        foreach ($body['edges'] as $edge) {
            $v1 = &$body['vertices'][$edge['v1']];
            $v2 = &$body['vertices'][$edge['v2']];

            if ($v1['fixed'] && $v2['fixed']) continue;

            $dx = $v2['position']['x'] - $v1['position']['x'];
            $dy = $v2['position']['y'] - $v1['position']['y'];
            $dz = $v2['position']['z'] - $v1['position']['z'];

            $current_length = sqrt($dx*$dx + $dy*$dy + $dz*$dz);
            if ($current_length < 0.0001) continue;

            $error = $current_length - $edge['rest_length'];
            $correction_factor = $error / $current_length * 0.5;

            $correction = [
                'x' => $dx * $correction_factor,
                'y' => $dy * $correction_factor,
                'z' => $dz * $correction_factor
            ];

            // Apply correction
            if (!$v1['fixed']) {
                $v1['position']['x'] += $correction['x'];
                $v1['position']['y'] += $correction['y'];
                $v1['position']['z'] += $correction['z'];
            }

            if (!$v2['fixed']) {
                $v2['position']['x'] -= $correction['x'];
                $v2['position']['y'] -= $correction['y'];
                $v2['position']['z'] -= $correction['z'];
            }
        }

        // Volume preservation (for more stability)
        $this->preserve_volume($body);
    }

    /**
     * Preserve volume to prevent collapse
     */
    private function preserve_volume(&$body) {
        if (!isset($body['original_volume'])) return;

        // Calculate current volume (simplified)
        $current_volume = $this->calculate_volume($body);
        $volume_error = ($body['original_volume'] - $current_volume) / $body['original_volume'];

        if (abs($volume_error) > 0.01) {
            // Scale vertices from center to preserve volume
            $center = $this->calculate_center($body);
            $scale_factor = 1.0 + $volume_error * 0.1;

            foreach ($body['vertices'] as &$vertex) {
                if ($vertex['fixed']) continue;

                $vertex['position']['x'] = $center['x'] + ($vertex['position']['x'] - $center['x']) * $scale_factor;
                $vertex['position']['y'] = $center['y'] + ($vertex['position']['y'] - $center['y']) * $scale_factor;
                $vertex['position']['z'] = $center['z'] + ($vertex['position']['z'] - $center['z']) * $scale_factor;
            }
        }
    }

    /**
     * Calculate volume (simplified using bounding box)
     */
    private function calculate_volume($body) {
        $min = ['x' => PHP_FLOAT_MAX, 'y' => PHP_FLOAT_MAX, 'z' => PHP_FLOAT_MAX];
        $max = ['x' => PHP_FLOAT_MIN, 'y' => PHP_FLOAT_MIN, 'z' => PHP_FLOAT_MIN];

        foreach ($body['vertices'] as $vertex) {
            $min['x'] = min($min['x'], $vertex['position']['x']);
            $min['y'] = min($min['y'], $vertex['position']['y']);
            $min['z'] = min($min['z'], $vertex['position']['z']);

            $max['x'] = max($max['x'], $vertex['position']['x']);
            $max['y'] = max($max['y'], $vertex['position']['y']);
            $max['z'] = max($max['z'], $vertex['position']['z']);
        }

        return ($max['x'] - $min['x']) * ($max['y'] - $min['y']) * ($max['z'] - $min['z']);
    }

    /**
     * Calculate geometric center
     */
    private function calculate_center($body) {
        $center = ['x' => 0, 'y' => 0, 'z' => 0];
        $count = count($body['vertices']);

        foreach ($body['vertices'] as $vertex) {
            $center['x'] += $vertex['position']['x'];
            $center['y'] += $vertex['position']['y'];
            $center['z'] += $vertex['position']['z'];
        }

        return [
            'x' => $center['x'] / $count,
            'y' => $center['y'] / $count,
            'z' => $center['z'] / $count
        ];
    }

    /**
     * Update mesh representation
     */
    private function update_mesh(&$body) {
        // Recalculate normals for rendering
        foreach ($body['faces'] as &$face) {
            $v1 = $body['vertices'][$face['v1']]['position'];
            $v2 = $body['vertices'][$face['v2']]['position'];
            $v3 = $body['vertices'][$face['v3']]['position'];

            // Calculate normal using cross product
            $edge1 = ['x' => $v2['x'] - $v1['x'], 'y' => $v2['y'] - $v1['y'], 'z' => $v2['z'] - $v1['z']];
            $edge2 = ['x' => $v3['x'] - $v1['x'], 'y' => $v3['y'] - $v1['y'], 'z' => $v3['z'] - $v1['z']];

            $normal = [
                'x' => $edge1['y'] * $edge2['z'] - $edge1['z'] * $edge2['y'],
                'y' => $edge1['z'] * $edge2['x'] - $edge1['x'] * $edge2['z'],
                'z' => $edge1['x'] * $edge2['y'] - $edge1['y'] * $edge2['x']
            ];

            $length = sqrt($normal['x']**2 + $normal['y']**2 + $normal['z']**2);
            if ($length > 0.0001) {
                $face['normal'] = [
                    'x' => $normal['x'] / $length,
                    'y' => $normal['y'] / $length,
                    'z' => $normal['z'] / $length
                ];
            }
        }
    }
}
