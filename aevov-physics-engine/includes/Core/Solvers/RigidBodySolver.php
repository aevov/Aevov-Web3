<?php
/**
 * Rigid Body Physics Solver
 *
 * Handles rotational dynamics, torque, angular momentum
 */

namespace Aevov\PhysicsEngine\Core\Solvers;

class RigidBodySolver {

    /**
     * Solve rigid body dynamics with quaternion rotation
     */
    public function solve($entities, $dt) {
        foreach ($entities as &$entity) {
            if (!$entity['active']) continue;

            // Initialize quaternion if not exists
            if (!isset($entity['quaternion'])) {
                $entity['quaternion'] = $this->euler_to_quaternion(
                    $entity['rotation'] ?? ['x' => 0, 'y' => 0, 'z' => 0]
                );
            }

            // Calculate inertia tensor (3x3 matrix)
            $inertia_tensor = $this->calculate_inertia_tensor_matrix($entity);
            $inertia_inverse = $this->invert_3x3_matrix($inertia_tensor);

            // Initialize angular velocity and torque if not exists
            if (!isset($entity['angular_velocity'])) {
                $entity['angular_velocity'] = ['x' => 0, 'y' => 0, 'z' => 0];
            }
            if (!isset($entity['torque_accumulator'])) {
                $entity['torque_accumulator'] = ['x' => 0, 'y' => 0, 'z' => 0];
            }

            // Angular acceleration: α = I^-1 * (τ - ω × (I * ω))
            // Euler's rotation equations
            $angular_velocity = $entity['angular_velocity'];
            $inertia_times_omega = $this->multiply_matrix_vector($inertia_tensor, $angular_velocity);
            $gyroscopic_torque = $this->cross_product($angular_velocity, $inertia_times_omega);

            $net_torque = [
                'x' => $entity['torque_accumulator']['x'] - $gyroscopic_torque['x'],
                'y' => $entity['torque_accumulator']['y'] - $gyroscopic_torque['y'],
                'z' => $entity['torque_accumulator']['z'] - $gyroscopic_torque['z']
            ];

            $angular_acceleration = $this->multiply_matrix_vector($inertia_inverse, $net_torque);

            // Update angular velocity
            $entity['angular_velocity']['x'] += $angular_acceleration['x'] * $dt;
            $entity['angular_velocity']['y'] += $angular_acceleration['y'] * $dt;
            $entity['angular_velocity']['z'] += $angular_acceleration['z'] * $dt;

            // Apply angular damping (air resistance on rotation)
            $damping = $entity['angular_damping'] ?? 0.98;
            $entity['angular_velocity']['x'] *= $damping;
            $entity['angular_velocity']['y'] *= $damping;
            $entity['angular_velocity']['z'] *= $damping;

            // Integrate rotation using quaternions
            $this->integrate_quaternion($entity, $dt);

            // Store for next frame
            $entity['angular_acceleration'] = $angular_acceleration;
        }

        return $entities;
    }

    /**
     * Integrate quaternion rotation
     * dq/dt = 0.5 * ω * q (quaternion derivative)
     */
    private function integrate_quaternion(&$entity, $dt) {
        $q = $entity['quaternion'];
        $omega = $entity['angular_velocity'];

        // Angular velocity quaternion (pure imaginary quaternion)
        $omega_quat = [
            'w' => 0,
            'x' => $omega['x'],
            'y' => $omega['y'],
            'z' => $omega['z']
        ];

        // Quaternion derivative: dq/dt = 0.5 * omega_quat * q
        $q_dot = $this->multiply_quaternions($omega_quat, $q);
        $q_dot['w'] *= 0.5;
        $q_dot['x'] *= 0.5;
        $q_dot['y'] *= 0.5;
        $q_dot['z'] *= 0.5;

        // Integrate: q(t+dt) = q(t) + dq/dt * dt
        $entity['quaternion']['w'] += $q_dot['w'] * $dt;
        $entity['quaternion']['x'] += $q_dot['x'] * $dt;
        $entity['quaternion']['y'] += $q_dot['y'] * $dt;
        $entity['quaternion']['z'] += $q_dot['z'] * $dt;

        // Normalize quaternion (prevent drift)
        $entity['quaternion'] = $this->normalize_quaternion($entity['quaternion']);

        // Update Euler angles for compatibility
        $entity['rotation'] = $this->quaternion_to_euler($entity['quaternion']);
    }

    /**
     * Calculate 3x3 inertia tensor matrix
     * For axis-aligned shapes
     */
    private function calculate_inertia_tensor_matrix($entity) {
        $mass = $entity['mass'];

        if (!isset($entity['collider'])) {
            return $this->identity_matrix_3x3();
        }

        $type = $entity['collider']['type'];

        if ($type === 'sphere') {
            $radius = $entity['collider']['radius'] ?? 1.0;
            $I = (2.0 / 5.0) * $mass * $radius * $radius;

            // Sphere has uniform inertia tensor (diagonal)
            return [
                [$I, 0, 0],
                [0, $I, 0],
                [0, 0, $I]
            ];
        }

        if ($type === 'aabb' || $type === 'box') {
            $size = $entity['collider']['half_size'] ?? ['x' => 1, 'y' => 1, 'z' => 1];
            $w = $size['x'] * 2;
            $h = $size['y'] * 2;
            $d = $size['z'] * 2;

            // Box inertia tensor
            $Ixx = (1.0 / 12.0) * $mass * ($h * $h + $d * $d);
            $Iyy = (1.0 / 12.0) * $mass * ($w * $w + $d * $d);
            $Izz = (1.0 / 12.0) * $mass * ($w * $w + $h * $h);

            return [
                [$Ixx, 0, 0],
                [0, $Iyy, 0],
                [0, 0, $Izz]
            ];
        }

        if ($type === 'cylinder') {
            $radius = $entity['collider']['radius'] ?? 1.0;
            $height = $entity['collider']['height'] ?? 2.0;

            // Cylinder inertia tensor (axis along Y)
            $Ixx = (1.0 / 12.0) * $mass * (3 * $radius * $radius + $height * $height);
            $Iyy = 0.5 * $mass * $radius * $radius;
            $Izz = $Ixx;

            return [
                [$Ixx, 0, 0],
                [0, $Iyy, 0],
                [0, 0, $Izz]
            ];
        }

        // Default: unit sphere
        return $this->identity_matrix_3x3();
    }

    /**
     * Invert 3x3 matrix (for diagonal matrices, simplified)
     */
    private function invert_3x3_matrix($matrix) {
        // For diagonal matrices (common in physics), inversion is simple
        if ($matrix[0][1] == 0 && $matrix[0][2] == 0 &&
            $matrix[1][0] == 0 && $matrix[1][2] == 0 &&
            $matrix[2][0] == 0 && $matrix[2][1] == 0) {
            return [
                [1 / max($matrix[0][0], 0.0001), 0, 0],
                [0, 1 / max($matrix[1][1], 0.0001), 0],
                [0, 0, 1 / max($matrix[2][2], 0.0001)]
            ];
        }

        // General 3x3 matrix inversion
        $det = $this->determinant_3x3($matrix);

        if (abs($det) < 0.0001) {
            return $this->identity_matrix_3x3();
        }

        $inverse = [
            [
                ($matrix[1][1] * $matrix[2][2] - $matrix[1][2] * $matrix[2][1]) / $det,
                ($matrix[0][2] * $matrix[2][1] - $matrix[0][1] * $matrix[2][2]) / $det,
                ($matrix[0][1] * $matrix[1][2] - $matrix[0][2] * $matrix[1][1]) / $det
            ],
            [
                ($matrix[1][2] * $matrix[2][0] - $matrix[1][0] * $matrix[2][2]) / $det,
                ($matrix[0][0] * $matrix[2][2] - $matrix[0][2] * $matrix[2][0]) / $det,
                ($matrix[0][2] * $matrix[1][0] - $matrix[0][0] * $matrix[1][2]) / $det
            ],
            [
                ($matrix[1][0] * $matrix[2][1] - $matrix[1][1] * $matrix[2][0]) / $det,
                ($matrix[0][1] * $matrix[2][0] - $matrix[0][0] * $matrix[2][1]) / $det,
                ($matrix[0][0] * $matrix[1][1] - $matrix[0][1] * $matrix[1][0]) / $det
            ]
        ];

        return $inverse;
    }

    /**
     * Calculate determinant of 3x3 matrix
     */
    private function determinant_3x3($m) {
        return $m[0][0] * ($m[1][1] * $m[2][2] - $m[1][2] * $m[2][1]) -
               $m[0][1] * ($m[1][0] * $m[2][2] - $m[1][2] * $m[2][0]) +
               $m[0][2] * ($m[1][0] * $m[2][1] - $m[1][1] * $m[2][0]);
    }

    /**
     * Multiply 3x3 matrix by vector
     */
    private function multiply_matrix_vector($matrix, $vector) {
        return [
            'x' => $matrix[0][0] * $vector['x'] + $matrix[0][1] * $vector['y'] + $matrix[0][2] * $vector['z'],
            'y' => $matrix[1][0] * $vector['x'] + $matrix[1][1] * $vector['y'] + $matrix[1][2] * $vector['z'],
            'z' => $matrix[2][0] * $vector['x'] + $matrix[2][1] * $vector['y'] + $matrix[2][2] * $vector['z']
        ];
    }

    /**
     * Cross product of two vectors
     */
    private function cross_product($a, $b) {
        return [
            'x' => $a['y'] * $b['z'] - $a['z'] * $b['y'],
            'y' => $a['z'] * $b['x'] - $a['x'] * $b['z'],
            'z' => $a['x'] * $b['y'] - $a['y'] * $b['x']
        ];
    }

    /**
     * Multiply two quaternions
     */
    private function multiply_quaternions($q1, $q2) {
        return [
            'w' => $q1['w'] * $q2['w'] - $q1['x'] * $q2['x'] - $q1['y'] * $q2['y'] - $q1['z'] * $q2['z'],
            'x' => $q1['w'] * $q2['x'] + $q1['x'] * $q2['w'] + $q1['y'] * $q2['z'] - $q1['z'] * $q2['y'],
            'y' => $q1['w'] * $q2['y'] - $q1['x'] * $q2['z'] + $q1['y'] * $q2['w'] + $q1['z'] * $q2['x'],
            'z' => $q1['w'] * $q2['z'] + $q1['x'] * $q2['y'] - $q1['y'] * $q2['x'] + $q1['z'] * $q2['w']
        ];
    }

    /**
     * Normalize quaternion
     */
    private function normalize_quaternion($q) {
        $magnitude = sqrt($q['w'] * $q['w'] + $q['x'] * $q['x'] + $q['y'] * $q['y'] + $q['z'] * $q['z']);

        if ($magnitude < 0.0001) {
            return ['w' => 1, 'x' => 0, 'y' => 0, 'z' => 0];
        }

        return [
            'w' => $q['w'] / $magnitude,
            'x' => $q['x'] / $magnitude,
            'y' => $q['y'] / $magnitude,
            'z' => $q['z'] / $magnitude
        ];
    }

    /**
     * Convert Euler angles to quaternion
     */
    private function euler_to_quaternion($euler) {
        $cx = cos($euler['x'] * 0.5);
        $sx = sin($euler['x'] * 0.5);
        $cy = cos($euler['y'] * 0.5);
        $sy = sin($euler['y'] * 0.5);
        $cz = cos($euler['z'] * 0.5);
        $sz = sin($euler['z'] * 0.5);

        return [
            'w' => $cx * $cy * $cz + $sx * $sy * $sz,
            'x' => $sx * $cy * $cz - $cx * $sy * $sz,
            'y' => $cx * $sy * $cz + $sx * $cy * $sz,
            'z' => $cx * $cy * $sz - $sx * $sy * $cz
        ];
    }

    /**
     * Convert quaternion to Euler angles
     */
    private function quaternion_to_euler($q) {
        // Roll (x-axis rotation)
        $sinr_cosp = 2 * ($q['w'] * $q['x'] + $q['y'] * $q['z']);
        $cosr_cosp = 1 - 2 * ($q['x'] * $q['x'] + $q['y'] * $q['y']);
        $roll = atan2($sinr_cosp, $cosr_cosp);

        // Pitch (y-axis rotation)
        $sinp = 2 * ($q['w'] * $q['y'] - $q['z'] * $q['x']);
        $pitch = abs($sinp) >= 1 ? copysign(M_PI / 2, $sinp) : asin($sinp);

        // Yaw (z-axis rotation)
        $siny_cosp = 2 * ($q['w'] * $q['z'] + $q['x'] * $q['y']);
        $cosy_cosp = 1 - 2 * ($q['y'] * $q['y'] + $q['z'] * $q['z']);
        $yaw = atan2($siny_cosp, $cosy_cosp);

        return [
            'x' => $roll,
            'y' => $pitch,
            'z' => $yaw
        ];
    }

    /**
     * Calculate angular momentum
     */
    public function calculate_angular_momentum($entity) {
        $inertia_tensor = $this->calculate_inertia_tensor_matrix($entity);
        $angular_velocity = $entity['angular_velocity'] ?? ['x' => 0, 'y' => 0, 'z' => 0];

        return $this->multiply_matrix_vector($inertia_tensor, $angular_velocity);
    }

    /**
     * Calculate rotational kinetic energy
     */
    public function calculate_rotational_energy($entity) {
        $angular_velocity = $entity['angular_velocity'] ?? ['x' => 0, 'y' => 0, 'z' => 0];
        $angular_momentum = $this->calculate_angular_momentum($entity);

        // KE_rot = 0.5 * ω · L
        return 0.5 * (
            $angular_velocity['x'] * $angular_momentum['x'] +
            $angular_velocity['y'] * $angular_momentum['y'] +
            $angular_velocity['z'] * $angular_momentum['z']
        );
    }

    /**
     * Apply torque from force at point
     * τ = r × F (cross product)
     */
    public function apply_force_at_point(&$entity, $force, $point) {
        // Vector from center of mass to point
        $r = [
            'x' => $point['x'] - $entity['position']['x'],
            'y' => $point['y'] - $entity['position']['y'],
            'z' => $point['z'] - $entity['position']['z']
        ];

        // Torque = r × F
        $torque = $this->cross_product($r, $force);

        // Add to torque accumulator
        if (!isset($entity['torque_accumulator'])) {
            $entity['torque_accumulator'] = ['x' => 0, 'y' => 0, 'z' => 0];
        }

        $entity['torque_accumulator']['x'] += $torque['x'];
        $entity['torque_accumulator']['y'] += $torque['y'];
        $entity['torque_accumulator']['z'] += $torque['z'];

        // Also apply linear force
        if (!isset($entity['force_accumulator'])) {
            $entity['force_accumulator'] = ['x' => 0, 'y' => 0, 'z' => 0];
        }

        $entity['force_accumulator']['x'] += $force['x'];
        $entity['force_accumulator']['y'] += $force['y'];
        $entity['force_accumulator']['z'] += $force['z'];
    }

    /**
     * Identity matrix 3x3
     */
    private function identity_matrix_3x3() {
        return [
            [1, 0, 0],
            [0, 1, 0],
            [0, 0, 1]
        ];
    }
}
